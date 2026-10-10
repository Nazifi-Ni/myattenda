<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$deviceId = Middleware::requireDevice();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$db = Database::getConnection();

try {
    $updates = ["last_seen = NOW()"];
    $params = ['id' => $deviceId];

    if (!empty($input['firmware_version'])) {
        $updates[] = "firmware_version = :firmware_version";
        $params['firmware_version'] = substr(trim($input['firmware_version']), 0, 50);
    }
    if (isset($input['battery_voltage']) && is_numeric($input['battery_voltage'])) {
        $updates[] = "battery_voltage = :battery_voltage";
        $params['battery_voltage'] = (float)$input['battery_voltage'];
    }
    if (isset($input['wifi_rssi']) && is_numeric($input['wifi_rssi'])) {
        $updates[] = "wifi_rssi = :wifi_rssi";
        $params['wifi_rssi'] = (int)$input['wifi_rssi'];
    }
    if (!empty($input['sd_status']) && in_array($input['sd_status'], ['ok', 'error', 'missing'])) {
        $updates[] = "sd_status = :sd_status";
        $params['sd_status'] = $input['sd_status'];
    }
    if (isset($input['fingerprint_count']) && is_numeric($input['fingerprint_count'])) {
        $updates[] = "fingerprint_count = :fingerprint_count";
        $params['fingerprint_count'] = (int)$input['fingerprint_count'];
    }
    if (!empty($input['sync_status']) && in_array($input['sync_status'], ['synced', 'pending_events', 'error'])) {
        $updates[] = "sync_status = :sync_status";
        $params['sync_status'] = $input['sync_status'];
    }

    $setClause = implode(', ', $updates);
    $stmt = $db->prepare("UPDATE devices SET {$setClause} WHERE id = :id");
    $stmt->execute($params);

    // Check if there is an active session assigned to this device
    $sessionStmt = $db->prepare("SELECT s.id, s.course_id, c.course_code, c.course_title, s.session_date, s.start_time 
                                FROM attendance_sessions s
                                JOIN courses c ON s.course_id = c.id
                                WHERE s.device_id = :did AND s.status = 'active' 
                                ORDER BY s.start_time DESC LIMIT 1");
    $sessionStmt->execute(['did' => $deviceId]);
    $activeSession = $sessionStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Heartbeat acknowledged',
        'server_time' => date('Y-m-d H:i:s'),
        'timestamp' => time(),
        'has_active_session' => (bool)$activeSession,
        'active_session' => $activeSession ?: null
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
