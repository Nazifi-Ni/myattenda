<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireAdmin();
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List devices
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT d.id, d.device_id, d.hardware_uid, d.device_name, d.status, d.firmware_version, d.last_seen, d.battery_voltage, d.wifi_rssi, d.sync_status, da.lecturer_id, u.full_name as lecturer_name 
              FROM devices d
              LEFT JOIN device_assignments da ON d.id = da.device_id AND da.status = 'active'
              LEFT JOIN users u ON da.lecturer_id = u.id
              ORDER BY d.created_at DESC LIMIT $limit OFFSET $offset";
              
    $stmt = $db->query($query);
    $devices = $stmt->fetchAll();
    
    $countStmt = $db->query("SELECT COUNT(*) as total FROM devices");
    $total = $countStmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'data' => $devices,
        'meta' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit
        ]
    ]);
} elseif ($method === 'POST') {
    // Register new device
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['hardware_uid'])) {
        http_response_code(400);
        echo json_encode(['error' => 'hardware_uid is required']);
        exit;
    }

    $hardwareUid = trim($input['hardware_uid']);
    $deviceName = $input['device_name'] ?? null;
    
    // Generate a human friendly device ID
    $prefix = (stripos($hardwareUid, 'ESP32') !== false) ? 'MYA-ESP32-' : 'MYA-DEV-';
    $deviceId = $prefix . strtoupper(substr(md5($hardwareUid . time()), 0, 6));

    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT INTO devices (device_id, hardware_uid, device_name, status) VALUES (:device_id, :hardware_uid, :device_name, 'pending')");
        $stmt->execute([
            'device_id' => $deviceId,
            'hardware_uid' => $hardwareUid,
            'device_name' => $deviceName
        ]);
        
        $insertId = $db->lastInsertId();
        
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'REGISTER_DEVICE', :desc)");
        $logStmt->execute([
            'uid' => $_SESSION['user_id'] ?? null,
            'desc' => "Registered device {$deviceId} ({$hardwareUid})"
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Device registered successfully',
            'data' => [
                'id' => $insertId,
                'device_id' => $deviceId,
                'hardware_uid' => $hardwareUid
            ]
        ]);
    } catch (PDOException $e) {
        $db->rollBack();
        http_response_code(400);
        if ($e->getCode() == 23000) {
            echo json_encode(['error' => 'Device hardware UID already exists']);
        } else {
            echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
        }
    }
} elseif ($method === 'PUT' || $method === 'PATCH') {
    // Edit/Suspend device
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Device ID is required']);
        exit;
    }
    
    $id = (int)$input['id'];
    $updates = [];
    $params = ['id' => $id];
    
    if (isset($input['device_name'])) {
        $updates[] = "device_name = :device_name";
        $params['device_name'] = $input['device_name'];
    }
    if (isset($input['status']) && in_array($input['status'], ['pending', 'active', 'suspended'])) {
        $updates[] = "status = :status";
        $params['status'] = $input['status'];
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $setClause = implode(', ', $updates);
    
    try {
        $stmt = $db->prepare("UPDATE devices SET {$setClause} WHERE id = :id");
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Device updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
