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

    // 3. Validate lecturer / device ownership
    $assignStmt = $db->prepare("SELECT lecturer_id FROM device_assignments WHERE device_id = :did AND status = 'active' LIMIT 1");
    $assignStmt->execute(['did' => $authDeviceId]);
    $assignment = $assignStmt->fetch();
    if (!$assignment) {
        throw new Exception("Device is not assigned to any lecturer");
    }
    $lecturerId = (int)$assignment['lecturer_id'];

    // Validate or auto-resolve course for this lecturer
    $courseStmt = $db->prepare("SELECT id FROM courses WHERE id = :cid AND lecturer_id = :lid AND status = 'active'");
    $courseStmt->execute(['cid' => $courseId, 'lid' => $lecturerId]);
    if (!$courseStmt->fetch()) {
        // Fallback 1: check if an active session is currently running for this device
        $activeSess = $db->prepare("SELECT course_id FROM attendance_sessions WHERE device_id = :did AND status = 'active' LIMIT 1");
        $activeSess->execute(['did' => $authDeviceId]);
        $row = $activeSess->fetch();
        if ($row) {
            $courseId = (int)$row['course_id'];
        } else {
            // Fallback 2: grab the lecturer's primary course
            $primaryCourse = $db->prepare("SELECT id FROM courses WHERE lecturer_id = :lid AND status = 'active' ORDER BY id ASC LIMIT 1");
            $primaryCourse->execute(['lid' => $lecturerId]);
            $pRow = $primaryCourse->fetch();
            if ($pRow) {
                $courseId = (int)$pRow['id'];
            } else {
                throw new Exception("No active course found for this device's lecturer");
            }
        }
    }

    // 4. Validate temporary ID within this course and active semester
    $enrollStmt = $db->prepare("SELECT id, student_id FROM enrollments WHERE course_id = :cid AND semester_id = :sid AND (temporary_id = :tid1 OR temporary_id = :tid2) AND status = 'active'");
    $enrollStmt->execute([
        'cid' => $courseId,
        'sid' => $semesterId,
        'tid1' => $tempIdNormalized,
        'tid2' => $rawTempId
    ]);
    $enrollment = $enrollStmt->fetch();
    if (!$enrollment) {
        throw new Exception("Student temporary ID '{$tempIdNormalized}' not enrolled in course for active semester");
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
