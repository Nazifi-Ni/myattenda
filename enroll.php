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
    <title>Course Enrollment - MyAttenda</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        h1 { margin-top: 0; font-size: 1.5rem; color: #1e293b; }
        .subtitle { color: #64748b; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; color: #334155; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .btn { width: 100%; padding: 0.75rem; background: #0f172a; color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #1e293b; }
        .success-box { text-align: center; margin-top: 2rem; }
        .biometric-id { font-size: 2.5rem; font-weight: bold; color: #0f172a; letter-spacing: 2px; margin: 1rem 0; font-family: monospace; }
        .instruction { font-size: 0.9rem; color: #64748b; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Course Enrollment</h1>
        <div class="subtitle">
            <strong>Course:</strong> <?php echo htmlspecialchars($courseTitle); ?><br>
            <strong>Lecturer:</strong> <?php echo htmlspecialchars($lecturerName); ?>
        </div>

        <?php echo $message; ?>

        <?php if ($biometricId): ?>
            <div class="success-box">
                <div style="font-size: 1rem; color: #334155;">Your Biometric ID</div>
                <div class="biometric-id"><?php echo htmlspecialchars($biometricId); ?></div>
                <div class="instruction">
                    Please go to the ESP32 Scanner during class.<br>
                    Press <strong>#</strong> to enter enrollment mode, and type this 4-digit ID to register your fingerprint.
                </div>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required placeholder="e.g. John Doe">
                </div>
                <div class="form-group">
                    <label>Registration Number</label>
                    <input type="text" name="reg_number" required placeholder="e.g. U18/FNS/CSC/1043">
                </div>
                <div class="form-group">
                    <label>Department (Optional)</label>
                    <input type="text" name="department" placeholder="e.g. Computer Science">
                </div>
                <div class="form-group">
                    <label>Academic Level</label>
                    <select name="level" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; background-color: #fff;">
                        <option value="">-- Select Level --</option>
                        <option value="100L">100L</option>
                        <option value="200L">200L</option>
                        <option value="300L">300L</option>
                        <option value="400L">400L</option>
                        <option value="500L">500L</option>
                    </select>
                </div>
                <button type="submit" class="btn">Join Course</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
