<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireLecturer();
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Enroll a student into a course for the ACTIVE semester
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['student_id']) || empty($input['course_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'student_id (internal DB ID) and course_id are required']);
        exit;
    }

    $studentId = (int)$input['student_id'];
    $courseId = (int)$input['course_id'];

    try {
        $db->beginTransaction();

        // 1. Get the active semester
        $stmt = $db->query("SELECT id FROM semesters WHERE status = 'active' LIMIT 1");
        $semester = $stmt->fetch();
        if (!$semester) {
            throw new Exception("No active semester found. Cannot enroll students without an active semester.");
        }
        $semesterId = (int)$semester['id'];

        // 2. Validate course exists and belongs to lecturer
        $stmt = $db->prepare("SELECT id FROM courses WHERE id = :cid AND status = 'active' AND lecturer_id = :lid");
        $stmt->execute(['cid' => $courseId, 'lid' => $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Course not found or inactive.");
        }

        // 3. Validate student exists
        $stmt = $db->prepare("SELECT id FROM students WHERE id = :sid");
        $stmt->execute(['sid' => $studentId]);
        if (!$stmt->fetch()) {
            throw new Exception("Student not found.");
        }

        // 4. Generate a unique temporary_id using the last 4 digits of the reg number
        $stuStmt = $db->prepare("SELECT student_id FROM students WHERE id = :sid");
        $stuStmt->execute(['sid' => $studentId]);
        $studentObj = $stuStmt->fetch();
        
        $digitsOnly = preg_replace('/[^0-9]/', '', $studentObj['student_id']);
        if (strlen($digitsOnly) >= 4) {
            $temporaryId = substr($digitsOnly, -4);
        } else {
            $temporaryId = str_pad($digitsOnly, 4, '0', STR_PAD_LEFT);
        }
        if ($temporaryId === '0000' || empty($temporaryId)) {
            $temporaryId = (string)rand(1000, 9999);
        }

        // Check collision in the same course/semester
        $colStmt = $db->prepare("SELECT id FROM enrollments WHERE course_id = :cid AND semester_id = :sid AND temporary_id = :tid AND student_id != :stuid");
        $colStmt->execute(['cid' => $courseId, 'sid' => $semesterId, 'tid' => $temporaryId, 'stuid' => $studentId]);
        if ($colStmt->fetch()) {
            throw new Exception("Biometric ID collision. Another student in this course has the same last 4 digits. Please manually assign an ID.");
        }

        // 5. Create enrollment
        $stmt = $db->prepare("INSERT INTO enrollments (student_id, course_id, semester_id, temporary_id, status) VALUES (:sid, :cid, :semid, :tempid, 'active')");
        $stmt->execute([
            'sid' => $studentId,
            'cid' => $courseId,
            'semid' => $semesterId,
            'tempid' => $temporaryId
        ]);
        
        $enrollmentId = $db->lastInsertId();

        // Log
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'ENROLL_STUDENT', :desc)");
        $logStmt->execute([
            'uid' => $_SESSION['user_id'] ?? null,
            'desc' => "Enrolled student {$studentId} in course {$courseId} for semester {$semesterId} with temp ID {$temporaryId}"
        ]);

        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Student enrolled successfully',
            'data' => [
                'enrollment_id' => $enrollmentId,
                'temporary_id' => $temporaryId
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(400);
        // Catch duplicate constraint
        if ($e instanceof PDOException && $e->getCode() == 23000) {
            echo json_encode(['error' => 'Student is already enrolled in this course for the active semester']);
        } else {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
} elseif ($method === 'GET') {
    // List enrollments
    // Optional filters: course_id, semester_id (defaults to active)
    $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
    
    $query = "SELECT e.id, e.temporary_id, e.status, s.id as student_db_id, s.student_id, s.full_name, s.department, s.level, c.course_code, f.slot_number 
              FROM enrollments e 
              JOIN students s ON e.student_id = s.id 
              JOIN courses c ON e.course_id = c.id 
              LEFT JOIN fingerprints f ON f.enrollment_id = e.id AND f.status = 'active'
              WHERE c.lecturer_id = :lid";
    $params = ['lid' => $_SESSION['user_id']];
    
    if ($courseId) {
        $query .= " AND e.course_id = :cid";
        $params['cid'] = $courseId;
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'data' => $stmt->fetchAll()
    ]);
} elseif ($method === 'DELETE') {
    // Delete an enrollment
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['enrollment_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'enrollment_id is required']);
        exit;
    }
    
    $enrollmentId = (int)$input['enrollment_id'];
    
    try {
        $db->beginTransaction();
        
        // Ensure the enrollment belongs to a course owned by this lecturer
        $stmt = $db->prepare("SELECT e.id FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.id = :eid AND c.lecturer_id = :lid");
        $stmt->execute(['eid' => $enrollmentId, 'lid' => $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Enrollment not found or unauthorized.");
        }
        
        // Delete fingerprint map if exists
        $stmt = $db->prepare("DELETE FROM fingerprints WHERE enrollment_id = :eid");
        $stmt->execute(['eid' => $enrollmentId]);
        
        // Delete attendance records
        $stmt = $db->prepare("DELETE FROM attendance_records WHERE enrollment_id = :eid");
        $stmt->execute(['eid' => $enrollmentId]);
        
        // Delete enrollment
        $stmt = $db->prepare("DELETE FROM enrollments WHERE id = :eid");
        $stmt->execute(['eid' => $enrollmentId]);
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Enrollment deleted successfully']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
