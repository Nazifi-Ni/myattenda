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
    // Device info
    $devStmt = $db->prepare("SELECT id, device_id, hardware_uid, device_name, status, firmware_version FROM devices WHERE id = :id");
    $devStmt->execute(['id' => $deviceId]);
    $device = $devStmt->fetch();

    // Assignment info
    $assignStmt = $db->prepare("SELECT da.lecturer_id, u.full_name as lecturer_name, u.email as lecturer_email 
                                FROM device_assignments da 
                                JOIN users u ON da.lecturer_id = u.id 
                                WHERE da.device_id = :did AND da.status = 'active' LIMIT 1");
    $assignStmt->execute(['did' => $deviceId]);
    $assignment = $assignStmt->fetch();

    // Active semester
    $semStmt = $db->query("SELECT id, name, status FROM semesters WHERE status = 'active' LIMIT 1");
    $semester = $semStmt->fetch();

    echo json_encode([
        'success' => true,
        'device' => [
            'id' => $device['id'],
            'device_id' => $device['device_id'],
            'hardware_uid' => $device['hardware_uid'],
            'device_name' => $device['device_name'],
            'status' => $device['status']
        ],
        'assigned_lecturer' => $assignment ?: null,
        'active_semester' => $semester ?: null,
        'config' => [
            'heartbeat_interval_seconds' => 60,
            'attendance_sync_interval_seconds' => 30,
            'max_fingerprint_capacity' => 100,
            'time_zone' => date_default_timezone_get(),
            'server_time' => date('Y-m-d H:i:s'),
            'timestamp' => time()
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
