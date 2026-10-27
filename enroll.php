<?php
require_once __DIR__ . '/api/config/database.php';
$db = Database::getConnection();

$courseId = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$courseTitle = "Unknown Course";
$lecturerName = "Unknown Lecturer";

if ($courseId > 0) {
    $stmt = $db->prepare("
        SELECT c.course_code, c.course_title, u.full_name as lecturer_name 
        FROM courses c 
        JOIN users u ON c.lecturer_id = u.id 
        WHERE c.id = :cid AND c.status = 'active'
    ");
    $stmt->execute(['cid' => $courseId]);
    $c = $stmt->fetch();
    if ($c) {
        $courseTitle = $c['course_code'] . " - " . $c['course_title'];
        $lecturerName = $c['lecturer_name'];
    } else {
        die("Invalid or inactive course.");
    }
} else {
    die("Course ID missing.");
}

$message = "";
$biometricId = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $regNumber = trim($_POST['reg_number'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $level = trim($_POST['level'] ?? '');

    if (empty($fullName) || empty($regNumber)) {
        $message = "<div style='color: #ef4444; background: #fee2e2; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;'>Name and Registration Number are required.</div>";
    } else {
        try {
            $db->beginTransaction();

            // 1. Get Active Semester
            $semStmt = $db->query("SELECT id FROM semesters WHERE status = 'active' LIMIT 1");
            $semester = $semStmt->fetch();
            if (!$semester) throw new Exception("No active semester found.");
            $semesterId = $semester['id'];

            // 2. Insert or update student
            $stuStmt = $db->prepare("SELECT id FROM students WHERE student_id = :reg");
            $stuStmt->execute(['reg' => $regNumber]);
            $student = $stuStmt->fetch();
            
            $internalStudentId = 0;
            if ($student) {
                $internalStudentId = $student['id'];
                // Update name/dept/level
                $upd = $db->prepare("UPDATE students SET full_name = :n, department = :d, level = :lvl WHERE id = :id");
                $upd->execute(['n' => $fullName, 'd' => $department, 'lvl' => $level, 'id' => $internalStudentId]);
            } else {
                $ins = $db->prepare("INSERT INTO students (student_id, full_name, department, level) VALUES (:reg, :n, :d, :lvl)");
                $ins->execute(['reg' => $regNumber, 'n' => $fullName, 'd' => $department, 'lvl' => $level]);
                $internalStudentId = $db->lastInsertId();
            }

            // 3. Extract last 4 digits for biometric ID
            $digitsOnly = preg_replace('/[^0-9]/', '', $regNumber);
            if (strlen($digitsOnly) >= 4) {
                $temporaryId = substr($digitsOnly, -4);
            } else {
                // If reg number has fewer than 4 digits, pad it
                $temporaryId = str_pad($digitsOnly, 4, '0', STR_PAD_LEFT);
            }
            if ($temporaryId === '0000' || empty($temporaryId)) {
                $temporaryId = (string)rand(1000, 9999);
            }

            // Check collision
            $colStmt = $db->prepare("SELECT id FROM enrollments WHERE course_id = :cid AND semester_id = :sid AND temporary_id = :tid AND student_id != :stuid");
            $colStmt->execute(['cid' => $courseId, 'sid' => $semesterId, 'tid' => $temporaryId, 'stuid' => $internalStudentId]);
            if ($colStmt->fetch()) {
                throw new Exception("Biometric ID collision. Another student in this course has the same last 4 digits. Please contact your lecturer.");
            }

            // 4. Check if already enrolled
            $enrStmt = $db->prepare("SELECT temporary_id FROM enrollments WHERE student_id = :stuid AND course_id = :cid AND semester_id = :sid");
            $enrStmt->execute(['stuid' => $internalStudentId, 'cid' => $courseId, 'sid' => $semesterId]);
            $enr = $enrStmt->fetch();

            if ($enr) {
                $biometricId = $enr['temporary_id'];
                $message = "<div style='color: #059669; background: #d1fae5; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;'>You are already enrolled! Your Biometric ID is <strong>{$biometricId}</strong>.</div>";
            } else {
                $insEnr = $db->prepare("INSERT INTO enrollments (student_id, course_id, semester_id, temporary_id) VALUES (:stuid, :cid, :sid, :tid)");
                $insEnr->execute(['stuid' => $internalStudentId, 'cid' => $courseId, 'sid' => $semesterId, 'tid' => $temporaryId]);
                $biometricId = $temporaryId;
                $message = "<div style='color: #059669; background: #d1fae5; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;'>Successfully enrolled! Your Biometric ID is <strong>{$biometricId}</strong>.</div>";
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message = "<div style='color: #ef4444; background: #fee2e2; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;'>" . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Enrollment | MyAttenda</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@500;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #1d4ed8;
            --brand-dark: #1e40af;
            --brand-soft: #eff6ff;
            --surface: #ffffff;
            --background: #f8fafc;
            --foreground: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --radius-md: 0.65rem;
            --radius-lg: 1rem;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background);
            color: var(--foreground);
            margin: 0;
            padding: 1.5rem 1rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-image: radial-gradient(rgba(29, 78, 216, 0.06) 1px, transparent 1px);
            background-size: 24px 24px;
        }
        .enroll-container {
            width: 100%;
            max-width: 460px;
        }
        .brand-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            margin-bottom: 1.5rem;
        }
        .brand-badge {
            width: 2.2rem;
            height: 2.2rem;
            border-radius: var(--radius-md);
            background: var(--brand);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 1.15rem;
            box-shadow: 0 4px 14px rgba(29, 78, 216, 0.25);
        }
        .brand-text {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .brand-sub {
            color: var(--muted);
            font-size: 0.72rem;
            font-family: 'DM Mono', monospace;
            background: #e2e8f0;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            font-weight: 600;
        }
        .card {
            background: var(--surface);
            padding: 2.25rem 2rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08);
            width: 100%;
        }
        .card-header {
            margin-bottom: 1.5rem;
        }
        .card-header h1 {
            margin: 0 0 0.4rem;
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--foreground);
            letter-spacing: -0.02em;
        }
        .course-info-box {
            background: var(--brand-soft);
            border: 1px solid rgba(29, 78, 216, 0.15);
            border-radius: var(--radius-md);
            padding: 0.9rem 1rem;
            margin-top: 0.75rem;
        }
        .course-info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--brand);
            font-weight: 700;
            font-family: 'DM Mono', monospace;
            margin-bottom: 0.2rem;
        }
        .course-info-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #1e3a8a;
            margin-bottom: 0.2rem;
        }
        .course-info-lecturer {
            font-size: 0.8rem;
            color: var(--muted);
        }
        .form-group {
            margin-bottom: 1.15rem;
        }
        label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            font-size: 0.82rem;
            color: #334155;
        }
        .req { color: #ef4444; }
        input, select {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-md);
            font-size: 0.92rem;
            font-family: inherit;
            color: var(--foreground);
            background: #ffffff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        }
        .input-hint {
            font-size: 0.72rem;
            color: var(--muted);
            margin-top: 0.35rem;
        }
        .btn-submit {
            width: 100%;
            padding: 0.85rem 1.25rem;
            background: var(--brand);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 0.5rem;
            box-shadow: 0 4px 14px rgba(29, 78, 216, 0.25);
            transition: background 0.15s ease, transform 0.1s ease;
        }
        .btn-submit:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
        }
        .btn-submit:active {
            transform: translateY(0);
        }

        /* Success & Biometric Display */
        .success-wrapper {
            text-align: center;
        }
        .success-icon-badge {
            width: 3.5rem;
            height: 3.5rem;
            background: #dcfce7;
            color: #15803d;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }
        .success-title {
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0 0 0.3rem;
            color: #0f172a;
        }
        .success-subtitle {
            font-size: 0.85rem;
            color: var(--muted);
            margin: 0 0 1.5rem;
        }
        .biometric-card {
            background: #f8fafc;
            border: 2px dashed #93c5fd;
            border-radius: var(--radius-lg);
            padding: 1.5rem 1rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .biometric-card-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--brand);
            font-weight: 700;
            font-family: 'DM Mono', monospace;
            margin-bottom: 0.5rem;
        }
        .biometric-digits {
            font-size: 3rem;
            font-weight: 800;
            font-family: 'DM Mono', monospace;
            color: #1d4ed8;
            letter-spacing: 0.25em;
            text-indent: 0.25em;
            line-height: 1.1;
            margin: 0.5rem 0;
        }
        .copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #ffffff;
            border: 1px solid var(--border);
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            color: #334155;
            transition: all 0.15s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .copy-btn:hover {
            border-color: var(--brand);
            color: var(--brand);
        }
        .instructions-list {
            text-align: left;
            background: #f1f5f9;
            border-radius: var(--radius-md);
            padding: 1rem 1.15rem;
            margin-bottom: 1.5rem;
        }
        .instruction-item {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            font-size: 0.82rem;
            color: #334155;
            margin-bottom: 0.6rem;
            line-height: 1.45;
        }
        .instruction-item:last-child {
            margin-bottom: 0;
        }
        .inst-num {
            background: var(--brand);
            color: #ffffff;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.68rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }
        .back-link {
            display: inline-block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
            margin-top: 1rem;
        }
        .back-link:hover {
            color: var(--brand);
        }
        .site-copy {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.72rem;
            color: var(--muted);
        }

        @media (max-width: 480px) {
            body { padding: 1rem 0.75rem; }
            .card { padding: 1.5rem 1.15rem; }
            .card-header h1 { font-size: 1.35rem; }
            .biometric-digits { font-size: 2.5rem; }
        }
    </style>
</head>
<body>
    <div class="enroll-container">
        <!-- Brand Header -->
        <div class="brand-header">
            <span class="brand-badge">⚡</span>
            <span class="brand-text">MyAttenda</span>
            <span class="brand-sub">STUDENT PORTAL</span>
        </div>

        <div class="card">
            <div class="card-header">
                <h1>Course Enrollment</h1>
                <div class="course-info-box">
                    <div class="course-info-label">Enrolling In</div>
                    <div class="course-info-title"><?php echo htmlspecialchars($courseTitle); ?></div>
                    <div class="course-info-lecturer">Lecturer: <?php echo htmlspecialchars($lecturerName); ?></div>
                </div>
            </div>

            <?php echo $message; ?>

            <?php if ($biometricId): ?>
                <div class="success-wrapper">
                    <div class="success-icon-badge">✓</div>
                    <h2 class="success-title">Enrollment Successful</h2>
                    <p class="success-subtitle">Save your 4-digit biometric ID below to complete fingerprint registration in class.</p>

                    <div class="biometric-card">
                        <div class="biometric-card-label">Your Biometric Device PIN</div>
                        <div class="biometric-digits" id="pin-display"><?php echo htmlspecialchars($biometricId); ?></div>
                        <button type="button" class="copy-btn" onclick="copyPin()">
                            <span id="copy-icon">📋</span> <span id="copy-text">Copy 4-Digit ID</span>
                        </button>
                    </div>

                    <div class="instructions-list">
                        <div class="instruction-item">
                            <span class="inst-num">1</span>
                            <span>Locate your lecturer's <strong>MyAttenda Attendance Device</strong> in the lecture hall.</span>
                        </div>
                        <div class="instruction-item">
                            <span class="inst-num">2</span>
                            <span>Press the <strong>#</strong> key on the device keypad to enter enrollment mode.</span>
                        </div>
                        <div class="instruction-item">
                            <span class="inst-num">3</span>
                            <span>Type your 4-digit ID (<strong><?php echo htmlspecialchars($biometricId); ?></strong>) and place your finger twice on the sensor when prompted.</span>
                        </div>
                    </div>

                    <a href="?course=<?php echo urlencode($courseId); ?>" class="back-link">← Enroll Another Student</a>
                </div>
                <script>
                    function copyPin() {
                        const pin = document.getElementById('pin-display').innerText.trim();
                        navigator.clipboard.writeText(pin).then(() => {
                            document.getElementById('copy-text').innerText = 'Copied to Clipboard!';
                            document.getElementById('copy-icon').innerText = '✓';
                            setTimeout(() => {
                                document.getElementById('copy-text').innerText = 'Copy 4-Digit ID';
                                document.getElementById('copy-icon').innerText = '📋';
                            }, 2500);
                        });
                    }
                </script>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" required placeholder="e.g. John Doe">
                    </div>
                    <div class="form-group">
                        <label>Registration Number <span class="req">*</span></label>
                        <input type="text" name="reg_number" required placeholder="e.g. U18/FNS/CSC/1043">
                        <div class="input-hint">Your 4-digit device PIN will match the last 4 digits of your reg number.</div>
                    </div>
                    <div class="form-group">
                        <label>Department (Optional)</label>
                        <input type="text" name="department" placeholder="e.g. Computer Science">
                    </div>
                    <div class="form-group">
                        <label>Academic Level <span class="req">*</span></label>
                        <select name="level" required>
                            <option value="">-- Select Academic Level --</option>
                            <option value="100L">100L</option>
                            <option value="200L">200L</option>
                            <option value="300L">300L</option>
                            <option value="400L">400L</option>
                            <option value="500L">500L</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">Complete Enrollment →</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="site-copy">
            MyAttenda V2 · Secured Biometric Attendance System
        </div>
    </div>
</body>
</html>
