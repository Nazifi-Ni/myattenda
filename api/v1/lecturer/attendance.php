<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireAuth();
$lecturerId = $_SESSION['user_id'];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input['session_id']) || empty($input['enrollment_id']) || empty($input['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'session_id, enrollment_id, and status are required']);
        exit;
    }

    $sessionId = (int)$input['session_id'];
    $enrollmentId = (int)$input['enrollment_id'];
    $status = strtolower($input['status']);

    if (!in_array($status, ['present', 'late', 'excused', 'absent'])) {
        http_response_code(400);
        echo json_encode(['error' => 'status must be present, late, excused, or absent']);
        exit;
    }

    // Verify session belongs to this lecturer
    $sCheck = $db->prepare("SELECT id FROM attendance_sessions WHERE id = :sid AND lecturer_id = :lid");
    $sCheck->execute(['sid' => $sessionId, 'lid' => $lecturerId]);
    if (!$sCheck->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if ($status === 'absent') {
        $del = $db->prepare("DELETE FROM attendance_records WHERE session_id = :sid AND enrollment_id = :eid");
        $del->execute(['sid' => $sessionId, 'eid' => $enrollmentId]);
    } else {
        $eventUid = 'MANUAL-' . bin2hex(random_bytes(8));
        $upsert = $db->prepare("INSERT INTO attendance_records (session_id, enrollment_id, event_uid, status, timestamp, synced_at)
                                VALUES (:sid, :eid, :uid, :status, NOW(), NOW())
                                ON DUPLICATE KEY UPDATE status = :status_upd, synced_at = NOW()");
        $upsert->execute([
            'sid' => $sessionId,
            'eid' => $enrollmentId,
            'uid' => $eventUid,
            'status' => $status,
            'status_upd' => $status
        ]);
    }

    echo json_encode(['success' => true, 'message' => "Attendance updated to {$status}"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

if (empty($_GET['session_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'session_id query parameter is required']);
    exit;
}

$sessionId = (int)$_GET['session_id'];

try {
    // 1. Verify session belongs to this lecturer
    $sessionStmt = $db->prepare("SELECT s.id, s.course_id, c.course_code, c.course_title, s.session_date, s.start_time, s.end_time, s.status, s.semester_id, d.device_id as device_code
                                FROM attendance_sessions s
                                JOIN courses c ON s.course_id = c.id
                                JOIN devices d ON s.device_id = d.id
                                WHERE s.id = :sid AND s.lecturer_id = :lid");
    $sessionStmt->execute(['sid' => $sessionId, 'lid' => $lecturerId]);
    $session = $sessionStmt->fetch();

    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Attendance session not found or unauthorized']);
        exit;
    }

    $courseId = (int)$session['course_id'];
    $semesterId = (int)$session['semester_id'];

    // 2. Query all enrolled students and left join with attendance_records for this session
    $recordsStmt = $db->prepare("SELECT e.id as enrollment_id, e.temporary_id, s.student_id, s.full_name, s.department, s.level,
                                        r.id as record_id, r.event_uid,
                                        COALESCE(r.status, 'absent') as attendance_status,
                                        r.timestamp as attended_at,
                                        r.synced_at
                                 FROM enrollments e
                                 JOIN students s ON e.student_id = s.id
                                 LEFT JOIN attendance_records r ON r.enrollment_id = e.id AND r.session_id = :sid
                                 WHERE e.course_id = :cid AND e.semester_id = :semid AND e.status = 'active'
                                 ORDER BY s.student_id ASC");
    $recordsStmt->execute([
        'sid' => $sessionId,
        'cid' => $courseId,
        'semid' => $semesterId
    ]);
    $roster = $recordsStmt->fetchAll();

    $totalEnrolled = count($roster);
    $presentCount = 0;
    $lateCount = 0;
    $excusedCount = 0;
    $absentCount = 0;

    foreach ($roster as $row) {
        switch ($row['attendance_status']) {
            case 'present': $presentCount++; break;
            case 'late': $lateCount++; break;
            case 'excused': $excusedCount++; break;
            default: $absentCount++; break;
        }
    }

    $attendancePercentage = $totalEnrolled > 0 ? round((($presentCount + $lateCount) / $totalEnrolled) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'session' => $session,
        'summary' => [
            'total_enrolled' => $totalEnrolled,
            'present' => $presentCount,
            'late' => $lateCount,
            'excused' => $excusedCount,
            'absent' => $absentCount,
            'percentage' => $attendancePercentage
        ],
        'records' => $roster
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
