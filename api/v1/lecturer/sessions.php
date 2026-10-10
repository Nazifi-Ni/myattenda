<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireAuth();
$lecturerId = $_SESSION['user_id'];
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // List sessions for this lecturer
        $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
        $status = $_GET['status'] ?? null;

        $query = "SELECT s.id, s.course_id, c.course_code, c.course_title, s.session_date, s.start_time, s.end_time, s.status, d.device_id as device_code,
                         (SELECT COUNT(*) FROM attendance_records r WHERE r.session_id = s.id) as attendance_count,
                         (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = s.course_id AND e.semester_id = s.semester_id AND e.status = 'active') as enrolled_count
                  FROM attendance_sessions s
                  JOIN courses c ON s.course_id = c.id
                  JOIN devices d ON s.device_id = d.id
                  WHERE s.lecturer_id = :lid";
        $params = ['lid' => $lecturerId];

        if ($courseId) {
            $query .= " AND s.course_id = :cid";
            $params['cid'] = $courseId;
        }
        if ($status && in_array($status, ['active', 'completed'])) {
            $query .= " AND s.status = :status";
            $params['status'] = $status;
        }

        $query .= " ORDER BY s.start_time DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'data' => $stmt->fetchAll()
        ]);
        exit;

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? 'start';

        if ($action === 'stop') {
            if (empty($input['session_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'session_id is required to stop session']);
                exit;
            }
            $sessionId = (int)$input['session_id'];

            $stmt = $db->prepare("UPDATE attendance_sessions SET status = 'completed', end_time = NOW() WHERE id = :id AND lecturer_id = :lid AND status = 'active'");
            $stmt->execute(['id' => $sessionId, 'lid' => $lecturerId]);

            // Audit log
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'STOP_ATTENDANCE_SESSION', :desc)");
            $logStmt->execute(['uid' => $lecturerId, 'desc' => "Closed attendance session ID {$sessionId}"]);

            echo json_encode([
                'success' => true,
                'message' => 'Attendance session completed successfully'
            ]);
            exit;
        }

        // Action: Start new attendance session
        if (empty($input['course_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'course_id is required to start session']);
            exit;
        }
        $courseId = (int)$input['course_id'];

        $db->beginTransaction();

        // 1. Get active semester
        $semStmt = $db->query("SELECT id, name FROM semesters WHERE status = 'active' LIMIT 1");
        $semester = $semStmt->fetch();
        if (!$semester) {
            throw new Exception("No active semester found on platform");
        }
        $semesterId = (int)$semester['id'];

        // 2. Validate course belongs to this lecturer
        $courseStmt = $db->prepare("SELECT id, course_code, course_title FROM courses WHERE id = :cid AND lecturer_id = :lid AND status = 'active'");
        $courseStmt->execute(['cid' => $courseId, 'lid' => $lecturerId]);
        $course = $courseStmt->fetch();
        if (!$course) {
            throw new Exception("Course not found or not assigned to you");
        }

        // 3. Get lecturer's assigned active device
        $assignStmt = $db->prepare("SELECT device_id FROM device_assignments WHERE lecturer_id = :lid AND status = 'active' LIMIT 1");
        $assignStmt->execute(['lid' => $lecturerId]);
        $assignment = $assignStmt->fetch();
        if (!$assignment) {
            throw new Exception("You do not have an active assigned device. Please contact Super Admin.");
        }
        $deviceId = (int)$assignment['device_id'];

        // 4. Ensure no existing active session for this device or lecturer
        $activeCheck = $db->prepare("SELECT id FROM attendance_sessions WHERE (device_id = :did OR lecturer_id = :lid) AND status = 'active'");
        $activeCheck->execute(['did' => $deviceId, 'lid' => $lecturerId]);
        if ($activeCheck->fetch()) {
            throw new Exception("There is already an active attendance session running for your device/account. Stop it first.");
        }

        // 5. Insert new session
        $ins = $db->prepare("INSERT INTO attendance_sessions (course_id, semester_id, device_id, lecturer_id, session_date, start_time, status)
                             VALUES (:cid, :sid, :did, :lid, CURDATE(), NOW(), 'active')");
        $ins->execute([
            'cid' => $courseId,
            'sid' => $semesterId,
            'did' => $deviceId,
            'lid' => $lecturerId
        ]);
        $sessionId = $db->lastInsertId();

        // Audit log
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, device_id, action, description) VALUES (:uid, :did, 'START_ATTENDANCE_SESSION', :desc)");
        $logStmt->execute([
            'uid' => $lecturerId,
            'did' => $deviceId,
            'desc' => "Started attendance session ID {$sessionId} for course {$course['course_code']}"
        ]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Attendance session started successfully',
            'session' => [
                'id' => $sessionId,
                'course_id' => $courseId,
                'course_code' => $course['course_code'],
                'course_title' => $course['course_title'],
                'device_id' => $deviceId,
                'status' => 'active',
                'start_time' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
    } elseif ($method === 'DELETE') {
        if (empty($_GET['session_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'session_id is required']);
            exit;
        }
        $sessionId = (int)$_GET['session_id'];

        $db->beginTransaction();

        // Ensure session belongs to lecturer
        $sCheck = $db->prepare("SELECT id FROM attendance_sessions WHERE id = :sid AND lecturer_id = :lid");
        $sCheck->execute(['sid' => $sessionId, 'lid' => $lecturerId]);
        if (!$sCheck->fetch()) {
            throw new Exception("Session not found or not assigned to you");
        }

        // Delete associated attendance records first
        $delRecords = $db->prepare("DELETE FROM attendance_records WHERE session_id = :sid");
        $delRecords->execute(['sid' => $sessionId]);

        // Delete the session
        $delSession = $db->prepare("DELETE FROM attendance_sessions WHERE id = :sid");
        $delSession->execute(['sid' => $sessionId]);

        // Audit log
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'DELETE_ATTENDANCE_SESSION', :desc)");
        $logStmt->execute(['uid' => $lecturerId, 'desc' => "Deleted attendance session ID {$sessionId} and all its records"]);

        $db->commit();

        echo json_encode(['success' => true, 'message' => 'Session deleted successfully']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
