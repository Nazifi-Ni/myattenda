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
    // Check if table exists
    $db->exec("CREATE TABLE IF NOT EXISTS `device_reset_requests` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `semester_id` BIGINT UNSIGNED NOT NULL,
        `device_id` BIGINT UNSIGNED NOT NULL,
        `status` ENUM('pending_wipe', 'wiped', 'failed') NOT NULL DEFAULT 'pending_wipe',
        `instructed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `wiped_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $stmt = $db->prepare("SELECT r.id as reset_request_id, r.semester_id, s.name as semester_name, r.instructed_at 
                          FROM device_reset_requests r
                          JOIN semesters s ON r.semester_id = s.id
                          WHERE r.device_id = :did AND r.status = 'pending_wipe'
                          ORDER BY r.instructed_at DESC LIMIT 1");
    $stmt->execute(['did' => $deviceId]);
    $request = $stmt->fetch();

    if ($request) {
        echo json_encode([
            'success' => true,
            'wipe_required' => true,
            'reset_request_id' => (int)$request['reset_request_id'],
            'semester_id' => (int)$request['semester_id'],
            'semester_name' => $request['semester_name'],
            'instruction' => 'ERASE_ALL_FINGERPRINTS_AND_LOCAL_ROSTER'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'wipe_required' => false
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
