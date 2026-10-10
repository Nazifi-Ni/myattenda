<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

// Ensures the user is logged in (could be lecturer or admin testing as lecturer)
Middleware::requireAuth();

$db = Database::getConnection();
$lecturerId = $_SESSION['user_id'];

try {
    // 1. Current Active Semester
    $stmt = $db->query("SELECT id, name FROM semesters WHERE status = 'active' LIMIT 1");
    $semester = $stmt->fetch();
    $semesterId = $semester ? $semester['id'] : null;
    $semesterName = $semester ? $semester['name'] : 'None';

    // 2. Assigned Device & Telemetry
    $stmt = $db->prepare("SELECT d.device_id, d.hardware_uid, d.status, d.last_seen, d.battery_voltage, d.wifi_rssi, d.fingerprint_count, d.sync_status 
                          FROM device_assignments da 
                          JOIN devices d ON da.device_id = d.id 
                          WHERE da.lecturer_id = :lid AND da.status = 'active'");
    $stmt->execute(['lid' => $lecturerId]);
    $device = $stmt->fetch();
    
    // Determine device online status
    $deviceStatus = 'Not Assigned';
    $lastSync = 'Never';
    $lastSyncRelative = 'Never';
    if ($device) {
        $lastSeenTime = strtotime($device['last_seen']);
        $secondsAgo = $lastSeenTime ? (time() - $lastSeenTime) : 999999;
        if ($lastSeenTime && $secondsAgo <= 120 && $secondsAgo >= -60) {
            $deviceStatus = 'Online';
        } else {
            $deviceStatus = 'Offline';
        }
        $lastSync = $device['last_seen'] ?? 'Never';

        if ($lastSeenTime) {
            if ($secondsAgo < 10) {
                $lastSyncRelative = 'Just now';
            } elseif ($secondsAgo < 60) {
                $lastSyncRelative = $secondsAgo . 's ago';
            } elseif ($secondsAgo < 3600) {
                $lastSyncRelative = round($secondsAgo / 60) . 'm ago';
            } else {
                $lastSyncRelative = round($secondsAgo / 3600) . 'h ago';
            }
        }
    }

    // 3. Courses
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM courses WHERE lecturer_id = :lid AND status = 'active'");
    $stmt->execute(['lid' => $lecturerId]);
    $totalCourses = $stmt->fetch()['count'];

    // 4. Students
    $totalStudents = 0;
    if ($semesterId) {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT student_id) as count FROM enrollments e 
                              JOIN courses c ON e.course_id = c.id 
                              WHERE c.lecturer_id = :lid AND e.semester_id = :sid");
        $stmt->execute(['lid' => $lecturerId, 'sid' => $semesterId]);
        $totalStudents = $stmt->fetch()['count'];
    }

    // 5. Active Sessions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM attendance_sessions WHERE lecturer_id = :lid AND status = 'active'");
    $stmt->execute(['lid' => $lecturerId]);
    $activeSessions = $stmt->fetch()['count'];

    // 6. Pending events
    $pendingEvents = 0;
    if ($device && $device['sync_status'] === 'pending_events') {
        $pendingEvents = 1; // Simplified boolean indicator for now
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'assigned_courses' => (int)$totalCourses,
            'total_students' => (int)$totalStudents,
            'active_sessions' => (int)$activeSessions,
            'device_status' => $deviceStatus,
            'last_synchronization' => $lastSync,
            'fingerprint_capacity' => $device ? $device['fingerprint_count'] . '/100' : 'Not available',
            'fingerprint_count' => $device ? (int)$device['fingerprint_count'] : 0,
            'max_capacity' => 100,
            'current_semester' => $semesterName,
            'pending_events' => $pendingEvents,
            'device' => $device ?: null
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
}
