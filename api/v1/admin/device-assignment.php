<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireAdmin();
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Assign a device to a lecturer
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['device_id']) || empty($input['lecturer_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'device_id and lecturer_id are required']);
        exit;
    }

    $deviceId = (int)$input['device_id'];
    $lecturerId = (int)$input['lecturer_id'];

    try {
        $db->beginTransaction();

        // Check if device is suspended
        $stmt = $db->prepare("SELECT status FROM devices WHERE id = :id");
        $stmt->execute(['id' => $deviceId]);
        $device = $stmt->fetch();
        if (!$device) {
            throw new Exception("Device not found");
        }
        if ($device['status'] === 'suspended') {
            throw new Exception("Cannot assign a suspended device");
        }

        // Check if lecturer exists and is active
        $stmt = $db->prepare("SELECT status FROM users WHERE id = :id AND role = 'lecturer'");
        $stmt->execute(['id' => $lecturerId]);
        $lecturer = $stmt->fetch();
        if (!$lecturer) {
            throw new Exception("Lecturer not found");
        }
        if ($lecturer['status'] !== 'active') {
            throw new Exception("Cannot assign to a disabled lecturer");
        }

        // Rule: A device can only have one active lecturer
        $stmt = $db->prepare("SELECT id FROM device_assignments WHERE device_id = :device_id AND status = 'active'");
        $stmt->execute(['device_id' => $deviceId]);
        if ($stmt->fetch()) {
            // Unassign current active assignment for this device
            $upd = $db->prepare("UPDATE device_assignments SET status = 'historical', unassigned_at = CURRENT_TIMESTAMP WHERE device_id = :device_id AND status = 'active'");
            $upd->execute(['device_id' => $deviceId]);
        }

        // Rule: A lecturer may have one assigned primary device
        $stmt = $db->prepare("SELECT id FROM device_assignments WHERE lecturer_id = :lecturer_id AND status = 'active'");
        $stmt->execute(['lecturer_id' => $lecturerId]);
        if ($stmt->fetch()) {
            // Unassign current active assignment for this lecturer
            $upd = $db->prepare("UPDATE device_assignments SET status = 'historical', unassigned_at = CURRENT_TIMESTAMP WHERE lecturer_id = :lecturer_id AND status = 'active'");
            $upd->execute(['lecturer_id' => $lecturerId]);
        }

        // Create new assignment
        $stmt = $db->prepare("INSERT INTO device_assignments (device_id, lecturer_id) VALUES (:device_id, :lecturer_id)");
        $stmt->execute([
            'device_id' => $deviceId,
            'lecturer_id' => $lecturerId
        ]);

        // Log action
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'ASSIGN_DEVICE', :desc)");
        $logStmt->execute([
            'uid' => $_SESSION['user_id'] ?? null,
            'desc' => "Assigned device ID {$deviceId} to lecturer ID {$lecturerId}"
        ]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Device assigned successfully']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
