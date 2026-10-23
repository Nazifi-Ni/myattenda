<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../../core/Middleware.php';
require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// 1. Authenticate device
$authDeviceId = Middleware::requireDevice();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (empty($input['temporary_id']) || !isset($input['slot'])) {
    http_response_code(400);
    echo json_encode(['error' => 'temporary_id and slot number are required']);
    exit;
}

$slot = (int)$input['slot'];
$rawTempId = trim((string)$input['temporary_id']);
$tempIdNormalized = str_starts_with($rawTempId, 'T-') ? $rawTempId : ('T-' . str_pad($rawTempId, 3, '0', STR_PAD_LEFT));
$isSuccess = !isset($input['status']) || ($input['status'] !== 'failed' && $input['status'] !== 'cancelled');
if (isset($input['success']) && $input['success'] === false) {
    $isSuccess = false;
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    // Find enrollment for this temporary_id
    $idVariants = array_values(array_unique(array_filter([
        $rawTempId,
        $tempIdNormalized,
        ltrim($rawTempId, '0'),
        str_pad($rawTempId, 3, '0', STR_PAD_LEFT),
        str_pad($rawTempId, 4, '0', STR_PAD_LEFT),
        'T-' . $rawTempId,
        'T-' . str_pad($rawTempId, 3, '0', STR_PAD_LEFT),
        'T-' . str_pad($rawTempId, 4, '0', STR_PAD_LEFT),
        'T-' . ltrim($rawTempId, '0')
    ])));
    $inPlaceholders = implode(',', array_fill(0, count($idVariants), '?'));

    $enrollStmt = $db->prepare("SELECT e.id, e.student_id, e.course_id, s.name as semester_name 
                                FROM enrollments e 
                                JOIN semesters s ON e.semester_id = s.id 
                                WHERE s.status = 'active' 
                                  AND e.status = 'active' 
                                  AND e.temporary_id IN ($inPlaceholders) 
                                LIMIT 1");
    $enrollStmt->execute($idVariants);
    $enrollment = $enrollStmt->fetch();

    if (!$enrollment) {
        $stmt2 = $db->prepare("SELECT e.id, e.student_id, e.course_id, s.name as semester_name 
                               FROM enrollments e 
                               JOIN students st ON e.student_id = st.id 
                               JOIN semesters s ON e.semester_id = s.id 
                               WHERE s.status = 'active' 
                                 AND e.status = 'active' 
                                 AND (st.student_id LIKE ? OR st.student_id = ?) 
                               LIMIT 1");
        $stmt2->execute(['%' . $rawTempId, $rawTempId]);
        $enrollment = $stmt2->fetch();
    }

    if (!$enrollment) {
        throw new Exception("Enrollment matching '{$rawTempId}' not found in active semester");
    }
    $enrollmentId = (int)$enrollment['id'];

    if (!$isSuccess) {
        // Release the reserved slot
        $delStmt = $db->prepare("DELETE FROM fingerprints WHERE enrollment_id = :eid AND device_id = :did AND slot_number = :slot AND status = 'reserved'");
        $delStmt->execute([
            'eid' => $enrollmentId,
            'did' => $authDeviceId,
            'slot' => $slot
        ]);
        
        $db->commit();
        echo json_encode([
            'success' => true,
            'message' => "Slot {$slot} released due to enrollment failure/cancellation"
        ]);
        exit;
    }

    // Confirm slot reservation
    $checkStmt = $db->prepare("SELECT id FROM fingerprints WHERE enrollment_id = :eid AND device_id = :did AND slot_number = :slot");
    $checkStmt->execute([
        'eid' => $enrollmentId,
        'did' => $authDeviceId,
        'slot' => $slot
    ]);
    $fp = $checkStmt->fetch();

    if ($fp) {
        $upd = $db->prepare("UPDATE fingerprints SET status = 'active' WHERE id = :id");
        $upd->execute(['id' => $fp['id']]);
    } else {
        // If not pre-reserved, directly insert as active
        $ins = $db->prepare("INSERT INTO fingerprints (enrollment_id, device_id, slot_number, status) VALUES (:eid, :did, :slot, 'active')");
        $ins->execute([
            'eid' => $enrollmentId,
            'did' => $authDeviceId,
            'slot' => $slot
        ]);
    }

    // Update fingerprint count on device
    $countStmt = $db->prepare("SELECT COUNT(*) as count FROM fingerprints WHERE device_id = :did AND status = 'active'");
    $countStmt->execute(['did' => $authDeviceId]);
    $count = (int)$countStmt->fetch()['count'];

    $devUpd = $db->prepare("UPDATE devices SET fingerprint_count = :count, last_seen = NOW() WHERE id = :did");
    $devUpd->execute(['count' => $count, 'did' => $authDeviceId]);

    // Audit log
    $logStmt = $db->prepare("INSERT INTO audit_logs (device_id, action, description) VALUES (:did, 'FINGERPRINT_ENROLLED', :desc)");
    $logStmt->execute([
        'did' => $authDeviceId,
        'desc' => "Fingerprint confirmed for temporary ID {$tempIdNormalized} on slot {$slot}"
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Fingerprint slot {$slot} confirmed and permanently mapped to {$tempIdNormalized}",
        'slot' => $slot,
        'temporary_id' => $tempIdNormalized,
        'fingerprint_count' => $count
    ]);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
