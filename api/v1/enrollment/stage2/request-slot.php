<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../../core/Middleware.php';
require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// 1. Authenticate device via Bearer token
$authDeviceId = Middleware::requireDevice();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (empty($input['course_id']) || empty($input['temporary_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'course_id and temporary_id are required']);
    exit;
}

$courseId = (int)$input['course_id'];
$rawTempId = trim((string)$input['temporary_id']);
$tempIdNormalized = str_starts_with($rawTempId, 'T-') ? $rawTempId : ('T-' . str_pad($rawTempId, 3, '0', STR_PAD_LEFT));

$db = Database::getConnection();

// Ensure status enum allows 'reserved'
try {
    $db->exec("ALTER TABLE fingerprints MODIFY COLUMN status ENUM('reserved', 'active', 'revoked') NOT NULL DEFAULT 'reserved'");
} catch (Exception $ignored) {}

try {
    $db->beginTransaction();

    // Verify passed device_id matches authenticated device if provided
    if (!empty($input['device_id'])) {
        $stmt = $db->prepare("SELECT id, device_id FROM devices WHERE id = :id");
        $stmt->execute(['id' => $authDeviceId]);
        $deviceRow = $stmt->fetch();
        if ($deviceRow && $deviceRow['id'] != $input['device_id'] && $deviceRow['device_id'] !== $input['device_id']) {
            throw new Exception("Device ID mismatch with authenticated credentials");
        }
    }

    // 2. Validate active semester
    $semStmt = $db->query("SELECT id FROM semesters WHERE status = 'active' LIMIT 1");
    $semester = $semStmt->fetch();
    if (!$semester) {
        throw new Exception("No active semester found on platform");
    }
    $semesterId = (int)$semester['id'];

    // 3. Resolve lecturer / device ownership (graceful fallback)
    $assignStmt = $db->prepare("SELECT lecturer_id FROM device_assignments WHERE device_id = :did AND status = 'active' LIMIT 1");
    $assignStmt->execute(['did' => $authDeviceId]);
    $assignment = $assignStmt->fetch();
    $lecturerId = $assignment ? (int)$assignment['lecturer_id'] : null;

    // 4. Build all representations of the 4-digit / temporary ID
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

    $enrollment = null;

    // Tier A: Check in specified course if valid
    if ($courseId > 0) {
        $params = array_merge([$courseId, $semesterId], $idVariants);
        $stmt = $db->prepare("SELECT e.id, e.course_id, e.student_id 
                              FROM enrollments e 
                              WHERE e.course_id = ? 
                                AND e.semester_id = ? 
                                AND e.temporary_id IN ($inPlaceholders) 
                                AND e.status = 'active' 
                              LIMIT 1");
        $stmt->execute($params);
        $enrollment = $stmt->fetch();
    }

    // Tier B: Check in any course belonging to this device's lecturer
    if (!$enrollment && $lecturerId) {
        $params = array_merge([$lecturerId, $semesterId], $idVariants);
        $stmt = $db->prepare("SELECT e.id, e.course_id, e.student_id 
                              FROM enrollments e 
                              JOIN courses c ON e.course_id = c.id 
                              WHERE c.lecturer_id = ? 
                                AND e.semester_id = ? 
                                AND e.temporary_id IN ($inPlaceholders) 
                                AND e.status = 'active' 
                              LIMIT 1");
        $stmt->execute($params);
        $enrollment = $stmt->fetch();
    }

    // Tier C: Check in any active course in the active semester
    if (!$enrollment) {
        $params = array_merge([$semesterId], $idVariants);
        $stmt = $db->prepare("SELECT e.id, e.course_id, e.student_id 
                              FROM enrollments e 
                              WHERE e.semester_id = ? 
                                AND e.temporary_id IN ($inPlaceholders) 
                                AND e.status = 'active' 
                              LIMIT 1");
        $stmt->execute($params);
        $enrollment = $stmt->fetch();
    }

    // Tier D: Match student whose Matric/Registration number ends with these digits
    if (!$enrollment) {
        $stmt = $db->prepare("SELECT e.id, e.course_id, e.student_id 
                              FROM enrollments e 
                              JOIN students s ON e.student_id = s.id 
                              WHERE e.semester_id = ? 
                                AND (s.student_id LIKE ? OR s.student_id = ?) 
                                AND e.status = 'active' 
                              LIMIT 1");
        $stmt->execute([$semesterId, '%' . $rawTempId, $rawTempId]);
        $enrollment = $stmt->fetch();
    }

    if (!$enrollment) {
        throw new Exception("Biometric ID '{$rawTempId}' not found. Please register via link first.");
    }
    $enrollmentId = (int)$enrollment['id'];

    // Check if an existing active/reserved slot is already assigned to this enrollment on this device
    $existingSlotStmt = $db->prepare("SELECT id, slot_number, status FROM fingerprints WHERE enrollment_id = :eid AND device_id = :did AND status IN ('active', 'reserved')");
    $existingSlotStmt->execute(['eid' => $enrollmentId, 'did' => $authDeviceId]);
    $existing = $existingSlotStmt->fetch();

    if ($existing) {
        $assignedSlot = (int)$existing['slot_number'];
    } else {
        // 5. Find an available R558 slot (1 to 100)
        $slotsStmt = $db->prepare("SELECT slot_number FROM fingerprints WHERE device_id = :did AND status IN ('active', 'reserved')");
        $slotsStmt->execute(['did' => $authDeviceId]);
        $usedSlots = $slotsStmt->fetchAll(PDO::FETCH_COLUMN);
        $usedMap = array_flip($usedSlots);

        $assignedSlot = null;
        for ($i = 1; $i <= 100; $i++) {
            if (!isset($usedMap[$i])) {
                $assignedSlot = $i;
                break;
            }
        }

        if ($assignedSlot === null) {
            throw new Exception("R558 fingerprint sensor capacity reached (100 slots full)");
        }

        // 6. Reserve the slot
        $insertStmt = $db->prepare("INSERT INTO fingerprints (enrollment_id, device_id, slot_number, status) VALUES (:eid, :did, :slot, 'reserved')");
        $insertStmt->execute([
            'eid' => $enrollmentId,
            'did' => $authDeviceId,
            'slot' => $assignedSlot
        ]);
    }

    $db->commit();

    // 7. Return slot number
    echo json_encode([
        'success' => true,
        'slot' => $assignedSlot,
        'temporary_id' => $tempIdNormalized
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
