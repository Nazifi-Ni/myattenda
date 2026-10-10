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
    $stmt = $db->prepare("SELECT s.id as session_id, s.course_id, c.course_code, c.course_title, s.session_date, s.start_time, u.full_name as lecturer_name 
                          FROM attendance_sessions s
                          JOIN courses c ON s.course_id = c.id
                          JOIN users u ON s.lecturer_id = u.id
                          WHERE s.device_id = :did AND s.status = 'active'
                          ORDER BY s.start_time DESC LIMIT 1");
    $stmt->execute(['did' => $deviceId]);
    $session = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'active' => (bool)$session,
        'session' => $session ?: null,
        'server_time' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
