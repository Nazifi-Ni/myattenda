<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Auth.php'; // Not using middleware because device isn't active yet
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['hardware_uid']) || empty($input['activation_code'])) {
    http_response_code(400);
    echo json_encode(['error' => 'hardware_uid and activation_code are required']);
    exit;
}

$hardwareUid = trim($input['hardware_uid']);
$activationCode = trim($input['activation_code']);

$db = Database::getConnection();

try {
    $db->beginTransaction();

    // 1. Find device by hardware_uid
    $stmt = $db->prepare("SELECT id, status FROM devices WHERE hardware_uid = :uid");
    $stmt->execute(['uid' => $hardwareUid]);
    $device = $stmt->fetch();

    $codeRecord = null;

    if (!$device) {
        // Smart fallback: Check if the activation code belongs to any pending/unbound device
        $codeStmt = $db->query("SELECT a.id, a.device_id, a.code_hash, d.status 
                                FROM activation_codes a 
                                JOIN devices d ON a.device_id = d.id 
                                WHERE a.used = 0 AND a.expires_at > NOW()");
        $candidates = $codeStmt->fetchAll();
        foreach ($candidates as $cand) {
            if (password_verify($activationCode, $cand['code_hash']) && $cand['status'] !== 'suspended') {
                $deviceId = (int)$cand['device_id'];
                // Bind device to this physical ESP32's exact hardware_uid
                $bindStmt = $db->prepare("UPDATE devices SET hardware_uid = :huid WHERE id = :did");
                $bindStmt->execute(['huid' => $hardwareUid, 'did' => $deviceId]);
                $device = ['id' => $deviceId, 'status' => $cand['status']];
                $codeRecord = $cand;
                break;
            }
        }

        if (!$device) {
            throw new Exception("Device not found or invalid activation code.");
        }
    }

    if ($device['status'] === 'suspended') {
        throw new Exception("Device is suspended.");
    }

    $deviceId = (int)$device['id'];

    // 2. Validate activation code if not already validated in fallback
    if (!$codeRecord) {
        $stmt = $db->prepare("SELECT id, code_hash, expires_at FROM activation_codes WHERE device_id = :device_id AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute(['device_id' => $deviceId]);
        $codeRecord = $stmt->fetch();

        if (!$codeRecord || !password_verify($activationCode, $codeRecord['code_hash'])) {
            throw new Exception("Invalid or expired activation code.");
        }
    }

    // 3. Mark code as used
    $upd = $db->prepare("UPDATE activation_codes SET used = 1 WHERE id = :id");
    $upd->execute(['id' => $codeRecord['id']]);

    // 4. Generate DEVICE_API_KEY
    $apiKey = bin2hex(random_bytes(32)); // 64 char long token
    $apiKeyHash = password_hash($apiKey, PASSWORD_DEFAULT);

    // 5. Update device status and store API Key
    $stmt = $db->prepare("UPDATE devices SET status = 'active', api_key_hash = :hash, updated_at = NOW() WHERE id = :id");
    $stmt->execute([
        'hash' => $apiKeyHash,
        'id' => $deviceId
    ]);

    // 6. Log it
    $logStmt = $db->prepare("INSERT INTO audit_logs (device_id, action, description) VALUES (:did, 'DEVICE_ACTIVATED', :desc)");
    $logStmt->execute([
        'did' => $deviceId,
        'desc' => "Device hardware UID {$hardwareUid} activated successfully."
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Device activated successfully',
        'api_key' => $apiKey // Device MUST save this!
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
