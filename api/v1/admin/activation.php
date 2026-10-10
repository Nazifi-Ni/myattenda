<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireAdmin();
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Generate activation code for a device
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['device_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Device ID is required']);
        exit;
    }

    $deviceId = (int)$input['device_id'];
    $forceRegenerate = !empty($input['regenerate']);

    $db->exec("CREATE TABLE IF NOT EXISTS `activation_codes` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `device_id` BIGINT UNSIGNED NOT NULL,
        `code_hash` VARCHAR(255) NOT NULL,
        `code_plain` VARCHAR(10) NULL,
        `expires_at` TIMESTAMP NOT NULL,
        `used` TINYINT(1) DEFAULT 0,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    try {
        $db->beginTransaction();
        
        // Ensure device exists and is active/pending (not suspended)
        $stmt = $db->prepare("SELECT status FROM devices WHERE id = :id");
        $stmt->execute(['id' => $deviceId]);
        $device = $stmt->fetch();
        
        if (!$device || $device['status'] === 'suspended') {
            throw new Exception("Invalid device or device is suspended");
        }

        // Check if there is already an active, unused code for this device
        if (!$forceRegenerate) {
            $existingStmt = $db->prepare("SELECT code_plain, expires_at FROM activation_codes 
                                          WHERE device_id = :device_id AND used = 0 AND expires_at > NOW() AND code_plain IS NOT NULL 
                                          ORDER BY id DESC LIMIT 1");
            $existingStmt->execute(['device_id' => $deviceId]);
            $existing = $existingStmt->fetch();
            if ($existing && !empty($existing['code_plain'])) {
                $db->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Active activation code retrieved',
                    'data' => [
                        'activation_code' => $existing['code_plain'],
                        'expires_in' => '24 hours'
                    ]
                ]);
                exit;
            }
        }

        // Generate a 6-digit one-time activation code
        $activationCode = sprintf("%06d", mt_rand(1, 999999));
        $hashedCode = password_hash($activationCode, PASSWORD_DEFAULT);

        // Expire old codes for this device
        $upd = $db->prepare("UPDATE activation_codes SET used = 1 WHERE device_id = :device_id");
        $upd->execute(['device_id' => $deviceId]);

        // Insert new code, expires in 24 hours
        $stmt = $db->prepare("INSERT INTO activation_codes (device_id, code_hash, code_plain, expires_at) VALUES (:device_id, :hash, :plain, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
        $stmt->execute([
            'device_id' => $deviceId,
            'hash' => $hashedCode,
            'plain' => $activationCode
        ]);

        // Log it
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'GENERATE_ACTIVATION', :desc)");
        $logStmt->execute([
            'uid' => $_SESSION['user_id'] ?? null,
            'desc' => "Generated activation code for device ID {$deviceId}"
        ]);

        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Activation code generated',
            'data' => [
                'activation_code' => $activationCode,
                'expires_in' => '24 hours'
            ]
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
