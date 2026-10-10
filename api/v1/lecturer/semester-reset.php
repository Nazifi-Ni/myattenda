<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireLecturer();
$lecturerId = $_SESSION['user_id'];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$semesterId = isset($input['semester_id']) ? (int)$input['semester_id'] : null;

try {
    // Ensure reset tracking table exists
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

    $db->beginTransaction();

    // 1. Identify semester
    if ($semesterId) {
        $semStmt = $db->prepare("SELECT id, name, status FROM semesters WHERE id = :id");
        $semStmt->execute(['id' => $semesterId]);
    } else {
        $semStmt = $db->query("SELECT id, name, status FROM semesters WHERE status = 'active' LIMIT 1");
    }
    $semester = $semStmt->fetch();

    if (!$semester) {
        throw new Exception("No target active semester found to close/reset");
    }
    $targetSemesterId = (int)$semester['id'];
    $semesterName = $semester['name'];

    // 2. Stop active attendance sessions
    $stopSessionsStmt = $db->prepare("UPDATE attendance_sessions SET status = 'completed', end_time = NOW() WHERE semester_id = :sid AND status = 'active'");
    $stopSessionsStmt->execute(['sid' => $targetSemesterId]);
    $closedSessionsCount = $stopSessionsStmt->rowCount();

    // 3. Mark semester as CLOSED
    $closeSemStmt = $db->prepare("UPDATE semesters SET status = 'closed', end_date = CURDATE() WHERE id = :sid");
    $closeSemStmt->execute(['sid' => $targetSemesterId]);

    // 4. Revoke active fingerprint associations for this semester (clears sensor slot ownership)
    $revokeFpStmt = $db->prepare("UPDATE fingerprints f 
                                  JOIN enrollments e ON f.enrollment_id = e.id 
                                  SET f.status = 'revoked' 
                                  WHERE e.semester_id = :sid AND f.status = 'active'");
    $revokeFpStmt->execute(['sid' => $targetSemesterId]);
    $revokedFpCount = $revokeFpStmt->rowCount();

    // 5. Instruct all currently active devices to erase fingerprints and roster
    $devicesStmt = $db->query("SELECT id, device_id FROM devices WHERE status = 'active'");
    $devices = $devicesStmt->fetchAll();

    $resetStmt = $db->prepare("INSERT INTO device_reset_requests (semester_id, device_id, status) VALUES (:sid, :did, 'pending_wipe')");
    $instructedCount = 0;
    foreach ($devices as $dev) {
        $resetStmt->execute([
            'sid' => $targetSemesterId,
            'did' => $dev['id']
        ]);
        $instructedCount++;
    }

    // 6. Audit log
    $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'SEMESTER_RESET_INITIATED', :desc)");
    $logStmt->execute([
        'uid' => $lecturerId,
        'desc' => "Closed semester '{$semesterName}' (ID {$targetSemesterId}): stopped {$closedSessionsCount} sessions, revoked {$revokedFpCount} fingerprint slots, instructed {$instructedCount} devices to wipe sensor."
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Semester '{$semesterName}' closed successfully. Historical attendance preserved.",
        'details' => [
            'semester_id' => $targetSemesterId,
            'semester_name' => $semesterName,
            'stopped_sessions_count' => $closedSessionsCount,
            'revoked_fingerprints_count' => $revokedFpCount,
            'devices_pending_wipe' => $instructedCount
        ]
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
