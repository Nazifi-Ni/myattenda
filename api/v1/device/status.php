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
    $stmt = $db->prepare("SELECT id, device_id, hardware_uid, device_name, status, firmware_version, last_seen, battery_voltage, wifi_rssi, sd_status, fingerprint_count, sync_status, created_at, updated_at FROM devices WHERE id = :id");
    $stmt->execute(['id' => $deviceId]);
    $device = $stmt->fetch();

    $lastSeenTs = $device['last_seen'] ? strtotime($device['last_seen']) : 0;
    $isOnline = ($lastSeenTs > 0 && (time() - $lastSeenTs) <= 300);

    echo json_encode([
        'success' => true,
        'data' => array_merge($device, [
            'is_online' => $isOnline,
            'server_timestamp' => time()
        ])
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
