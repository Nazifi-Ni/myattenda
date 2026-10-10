<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$deviceId = Middleware::requireDevice();
$db = Database::getConnection();

try {
    // 1. Get active semester
    $semStmt = $db->query("SELECT id, name FROM semesters WHERE status = 'active' LIMIT 1");
    $semester = $semStmt->fetch();
    if (!$semester) {
        echo json_encode([
            'success' => true,
            'message' => 'No active semester',
            'semester' => null,
            'roster' => []
        ]);
        exit;
    }
    $semesterId = (int)$semester['id'];

    // 2. Query all enrolled students with active fingerprints mapped to this device in the active semester
    $query = "SELECT f.slot_number, e.temporary_id, s.student_id, s.full_name, c.id as course_id, c.course_code, c.course_title 
              FROM fingerprints f
              JOIN enrollments e ON f.enrollment_id = e.id
              JOIN students s ON e.student_id = s.id
              JOIN courses c ON e.course_id = c.id
              WHERE f.device_id = :did 
                AND f.status = 'active'
                AND e.semester_id = :sid 
                AND e.status = 'active'
              ORDER BY f.slot_number ASC";

    $stmt = $db->prepare($query);
    $stmt->execute(['did' => $deviceId, 'sid' => $semesterId]);
    $roster = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'semester' => [
            'id' => $semesterId,
            'name' => $semester['name']
        ],
        'count' => count($roster),
        'roster' => $roster
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
