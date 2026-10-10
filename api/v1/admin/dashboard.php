<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

// Ensure only Super Admins can access this endpoint
Middleware::requireAdmin();

try {
    $db = Database::getConnection();

    // 1. Total lecturers
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'lecturer'");
    $totalLecturers = $stmt->fetch()['count'];

    // 2. Devices stats
    $stmt = $db->query("SELECT COUNT(*) as count FROM devices");
    $totalDevices = $stmt->fetch()['count'];

    $stmt = $db->query("SELECT COUNT(*) as count FROM devices WHERE status = 'suspended'");
    $suspendedDevices = $stmt->fetch()['count'];

    // Online/Offline threshold: 5 minutes
    $stmt = $db->query("SELECT COUNT(*) as count FROM devices WHERE status = 'active' AND last_seen >= (NOW() - INTERVAL 5 MINUTE)");
    $onlineDevices = $stmt->fetch()['count'];

    $stmt = $db->query("SELECT COUNT(*) as count FROM devices WHERE status = 'active' AND (last_seen < (NOW() - INTERVAL 5 MINUTE) OR last_seen IS NULL)");
    $offlineDevices = $stmt->fetch()['count'];

    // 3. Courses
    $stmt = $db->query("SELECT COUNT(*) as count FROM courses");
    $totalCourses = $stmt->fetch()['count'];

    // 4. Active Semester
    $stmt = $db->query("SELECT name FROM semesters WHERE status = 'active' LIMIT 1");
    $activeSemesterRow = $stmt->fetch();
    $activeSemester = $activeSemesterRow ? $activeSemesterRow['name'] : 'None';

    // 5. Active Attendance Sessions
    $stmt = $db->query("SELECT COUNT(*) as count FROM attendance_sessions WHERE status = 'active'");
    $activeSessions = $stmt->fetch()['count'];

    // 6. Synchronization
    $stmt = $db->query("SELECT COUNT(*) as count FROM devices WHERE sync_status = 'pending_events'");
    $pendingSync = $stmt->fetch()['count'];

    $stmt = $db->query("SELECT COUNT(*) as count FROM devices WHERE sync_status = 'error'");
    $failedSync = $stmt->fetch()['count'];

    // 7. Recent System Alerts (Audit Logs)
    $stmt = $db->query("SELECT id, action, description, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 5");
    $recentAlerts = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'total_lecturers' => (int)$totalLecturers,
            'total_devices' => (int)$totalDevices,
            'online_devices' => (int)$onlineDevices,
            'offline_devices' => (int)$offlineDevices,
            'suspended_devices' => (int)$suspendedDevices,
            'total_courses' => (int)$totalCourses,
            'active_semester' => $activeSemester,
            'active_attendance_sessions' => (int)$activeSessions,
            'pending_synchronization' => (int)$pendingSync,
            'failed_synchronization' => (int)$failedSync,
            'recent_alerts' => $recentAlerts
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
}
