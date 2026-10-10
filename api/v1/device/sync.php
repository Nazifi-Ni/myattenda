<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$deviceId = Middleware::requireDevice();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (empty($input['events']) || !is_array($input['events'])) {
    http_response_code(400);
    echo json_encode(['error' => 'events array is required']);
    exit;
}

$db = Database::getConnection();

$processed = 0;
$skipped = 0;
$errors = [];

try {
    $db->beginTransaction();

    $checkUidStmt = $db->prepare("SELECT id FROM attendance_records WHERE event_uid = :uid");
    $fpStmt = $db->prepare("SELECT enrollment_id FROM fingerprints WHERE device_id = :did AND slot_number = :slot AND status = 'active' LIMIT 1");
    $enrollStmt = $db->prepare("SELECT id FROM enrollments WHERE temporary_id = :tid AND course_id = :cid AND status = 'active' LIMIT 1");
    $dupAttendanceStmt = $db->prepare("SELECT id FROM attendance_records WHERE session_id = :sid AND enrollment_id = :eid LIMIT 1");
    $insertStmt = $db->prepare("INSERT INTO attendance_records (session_id, enrollment_id, event_uid, status, timestamp, synced_at)
                                VALUES (:sid, :eid, :uid, :status, :ts, NOW())");

    // Cache valid sessions for this device to prevent repeated queries
    $sessionCache = [];

    foreach ($input['events'] as $idx => $event) {
        if (empty($event['event_uid']) || empty($event['session_id'])) {
            $errors[] = ["index" => $idx, "error" => "Missing event_uid or session_id"];
            continue;
        }

        $eventUid = trim($event['event_uid']);
        $sessionId = (int)$event['session_id'];
        $eventStatus = in_array(strtolower($event['status'] ?? 'present'), ['present', 'late', 'excused']) ? strtolower($event['status']) : 'present';
        $timestamp = !empty($event['timestamp']) ? date('Y-m-d H:i:s', strtotime($event['timestamp'])) : date('Y-m-d H:i:s');

        // 1. Idempotency Check: event_uid
        $checkUidStmt->execute(['uid' => $eventUid]);
        if ($checkUidStmt->fetch()) {
            $skipped++;
            continue;
        }

        // Auto-resolve offline session (session_id = 0)
        if ($sessionId === 0) {
            $resolveStmt = $db->prepare("
                SELECT id 
                FROM attendance_sessions 
                WHERE device_id = :did 
                  AND CONCAT(session_date, ' ', start_time) <= :ts
                ORDER BY CONCAT(session_date, ' ', start_time) DESC 
                LIMIT 1
            ");
            $resolveStmt->execute(['did' => $deviceId, 'ts' => $timestamp]);
            $resolved = $resolveStmt->fetch();
            if ($resolved) {
                $sessionId = (int)$resolved['id'];
            }
        }

        // Validate session belongs to this device
        if (!isset($sessionCache[$sessionId])) {
            $sStmt = $db->prepare("SELECT id, course_id, status FROM attendance_sessions WHERE id = :sid AND device_id = :did");
            $sStmt->execute(['sid' => $sessionId, 'did' => $deviceId]);
            $sessionCache[$sessionId] = $sStmt->fetch();
        }

        $session = $sessionCache[$sessionId];
        if (!$session) {
            $errors[] = ["event_uid" => $eventUid, "error" => "Invalid session_id for this device"];
            continue;
        }

        $courseId = (int)$session['course_id'];
        $enrollmentId = null;

        // 2. Resolve enrollment by slot_number first
        if (isset($event['slot_number'])) {
            $fpStmt->execute(['did' => $deviceId, 'slot' => (int)$event['slot_number']]);
            $fp = $fpStmt->fetch();
            if ($fp) {
                $enrollmentId = (int)$fp['enrollment_id'];
            }
        }

        // 3. Fallback resolve by temporary_id
        if (!$enrollmentId && !empty($event['temporary_id'])) {
            $rawTid = trim($event['temporary_id']);
            $tidNorm = str_starts_with($rawTid, 'T-') ? $rawTid : ('T-' . str_pad($rawTid, 3, '0', STR_PAD_LEFT));
            $enrollStmt->execute(['tid' => $tidNorm, 'cid' => $courseId]);
            $en = $enrollStmt->fetch();
            if ($en) {
                $enrollmentId = (int)$en['id'];
            }
        }

        if (!$enrollmentId) {
            $errors[] = ["event_uid" => $eventUid, "error" => "Could not map slot or temporary_id to an enrolled student"];
            continue;
        }

        // 4. Duplicate Check: Ensure student is not already recorded for this session
        $dupAttendanceStmt->execute(['sid' => $sessionId, 'eid' => $enrollmentId]);
        if ($dupAttendanceStmt->fetch()) {
            $skipped++;
            continue;
        }

        // 5. Insert attendance record
        $insertStmt->execute([
            'sid' => $sessionId,
            'eid' => $enrollmentId,
            'uid' => $eventUid,
            'status' => $eventStatus,
            'ts' => $timestamp
        ]);

        $processed++;
    }

    // Update device status to synced
    $devStmt = $db->prepare("UPDATE devices SET last_seen = NOW(), sync_status = 'synced' WHERE id = :did");
    $devStmt->execute(['did' => $deviceId]);

    if ($processed > 0) {
        $logStmt = $db->prepare("INSERT INTO audit_logs (device_id, action, description) VALUES (:did, 'DEVICE_SYNC', :desc)");
        $logStmt->execute([
            'did' => $deviceId,
            'desc' => "Synchronized {$processed} attendance records (skipped {$skipped})"
        ]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sync processed successfully',
        'synced_count' => $processed,
        'skipped_count' => $skipped,
        'errors_count' => count($errors),
        'errors' => $errors,
        'server_timestamp' => time()
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Sync failed', 'message' => $e->getMessage()]);
}
