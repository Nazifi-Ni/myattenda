<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../../core/Middleware.php';
require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$deviceId = Middleware::requireDevice();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (empty($input['reset_request_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'reset_request_id is required']);
    exit;
}

$resetId = (int)$input['reset_request_id'];
$isSuccess = !isset($input['success']) || $input['success'] !== false;

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT id, semester_id, status FROM device_reset_requests WHERE id = :id AND device_id = :did");
    $stmt->execute(['id' => $resetId, 'did' => $deviceId]);
    $req = $stmt->fetch();

    if (!$req) {
        throw new Exception("Reset request not found for this device");
    }

    if ($isSuccess) {
        // Mark as wiped
        $upd = $db->prepare("UPDATE device_reset_requests SET status = 'wiped', wiped_at = NOW() WHERE id = :id");
        $upd->execute(['id' => $resetId]);

        // Reset device fingerprint count to 0
        $devUpd = $db->prepare("UPDATE devices SET fingerprint_count = 0, last_seen = NOW(), sync_status = 'synced' WHERE id = :did");
        $devUpd->execute(['did' => $deviceId]);

        // Audit log
        $logStmt = $db->prepare("INSERT INTO audit_logs (device_id, action, description) VALUES (:did, 'DEVICE_WIPE_CONFIRMED', :desc)");
        $logStmt->execute([
            'did' => $deviceId,
            'desc' => "Device confirmed successful R558 sensor wipe and SD roster reset for semester ID {$req['semester_id']}."
        ]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Reset confirmation recorded. Device is ready for new semester.'
        ]);
    } else {
        $upd = $db->prepare("UPDATE device_reset_requests SET status = 'failed' WHERE id = :id");
        $upd->execute(['id' => $resetId]);

        $logStmt = $db->prepare("INSERT INTO audit_logs (device_id, action, description) VALUES (:did, 'DEVICE_WIPE_FAILED', :desc)");
        $logStmt->execute([
            'did' => $deviceId,
            'desc' => "Device reported failure to wipe sensor for semester ID {$req['semester_id']}."
        ]);

        $db->commit();

        echo json_encode([
            'success' => false,
            'message' => 'Wipe failure recorded'
        ]);
    }

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
