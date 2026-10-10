<?php

require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireAuth();
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'lecturer';
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header("Content-Type: application/json");
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$format = strtolower($_GET['format'] ?? 'json');
$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$semesterId = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : null;
$studentId = isset($_GET['student_id']) ? trim($_GET['student_id']) : null;
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

try {
    // If lecturer, ensure they can only query their own courses
    if ($role === 'lecturer' && $courseId) {
        $cStmt = $db->prepare("SELECT id FROM courses WHERE id = :cid AND lecturer_id = :lid");
        $cStmt->execute(['cid' => $courseId, 'lid' => $userId]);
        if (!$cStmt->fetch()) {
            header("Content-Type: application/json");
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized course access']);
            exit;
        }
    }

    // Default to active semester if semester_id is not passed
    if (!$semesterId) {
        $semStmt = $db->query("SELECT id FROM semesters WHERE status = 'active' LIMIT 1");
        $sem = $semStmt->fetch();
        if ($sem) {
            $semesterId = (int)$sem['id'];
        }
    }

    // 1. Fetch total sessions in scope
    $sessionQuery = "SELECT id FROM attendance_sessions WHERE 1=1";
    $sessParams = [];
    if ($courseId) {
        $sessionQuery .= " AND course_id = :scid";
        $sessParams['scid'] = $courseId;
    }
    if ($semesterId) {
        $sessionQuery .= " AND semester_id = :ssid";
        $sessParams['ssid'] = $semesterId;
    }
    if ($role === 'lecturer') {
        $sessionQuery .= " AND lecturer_id = :slid";
        $sessParams['slid'] = $userId;
    }
    if ($dateFrom) {
        $sessionQuery .= " AND session_date >= :sdfrom";
        $sessParams['sdfrom'] = $dateFrom;
    }
    if ($dateTo) {
        $sessionQuery .= " AND session_date <= :sdto";
        $sessParams['sdto'] = $dateTo;
    }
    $sessionStmt = $db->prepare($sessionQuery);
    $sessionStmt->execute($sessParams);
    $sessionIds = $sessionStmt->fetchAll(PDO::FETCH_COLUMN);
    $totalSessionsHeld = count($sessionIds);

    // 2. Student attendance roll with positional binds
    $reportQuery = "SELECT e.id as enrollment_id, e.temporary_id, s.student_id, s.full_name, s.department, s.level,
                           c.id as course_id, c.course_code, c.course_title, sem.name as semester_name,
                           COUNT(DISTINCT r.id) as sessions_attended
                    FROM enrollments e
                    JOIN students s ON e.student_id = s.id
                    JOIN courses c ON e.course_id = c.id
                    JOIN semesters sem ON e.semester_id = sem.id
                    LEFT JOIN attendance_records r ON r.enrollment_id = e.id ";

    $repParams = [];
    if (!empty($sessionIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $reportQuery .= " AND r.session_id IN ($inPlaceholders) ";
        $repParams = array_values($sessionIds);
    } else {
        $reportQuery .= " AND 1=0 ";
    }

    $reportQuery .= " WHERE 1=1 ";

    if ($courseId) {
        $reportQuery .= " AND e.course_id = ? ";
        $repParams[] = $courseId;
    }
    if ($semesterId) {
        $reportQuery .= " AND e.semester_id = ? ";
        $repParams[] = $semesterId;
    }
    if ($studentId) {
        $reportQuery .= " AND (s.student_id LIKE ? OR s.full_name LIKE ?) ";
        $repParams[] = "%{$studentId}%";
        $repParams[] = "%{$studentId}%";
    }
    if ($role === 'lecturer') {
        $reportQuery .= " AND c.lecturer_id = ? ";
        $repParams[] = $userId;
    }

    $reportQuery .= " GROUP BY e.id ORDER BY s.student_id ASC";
    $reportStmt = $db->prepare($reportQuery);
    $reportStmt->execute($repParams);
    $rows = $reportStmt->fetchAll();

    $reportData = [];
    foreach ($rows as $row) {
        $attended = (int)$row['sessions_attended'];
        $percentage = $totalSessionsHeld > 0 ? round(($attended / $totalSessionsHeld) * 100, 1) : 0;
        $reportData[] = [
            'temporary_id' => $row['temporary_id'],
            'student_id' => $row['student_id'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'level' => $row['level'],
            'course_id' => $row['course_id'],
            'course_code' => $row['course_code'],
            'course_title' => $row['course_title'],
            'semester' => $row['semester_name'],
            'sessions_attended' => $attended,
            'total_sessions_held' => $totalSessionsHeld,
            'attendance_percentage' => $percentage
        ];
    }

    // CSV Export
    if ($format === 'csv') {
        $filename = "attendance_report_" . date('Ymd_His') . ".csv";
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        $output = fopen("php://output", "w");

        // Header line
        fputcsv($output, [
            "Temp ID", "Student ID", "Full Name", "Department", "Level",
            "Course Code", "Course Title", "Semester", "Attended", "Total Held", "Percentage"
        ]);

        foreach ($reportData as $record) {
            fputcsv($output, [
                $record['temporary_id'],
                $record['student_id'],
                $record['full_name'],
                $record['department'],
                $record['level'],
                $record['course_code'],
                $record['course_title'],
                $record['semester'],
                $record['sessions_attended'],
                $record['total_sessions_held'],
                $record['attendance_percentage'] . '%'
            ]);
        }
        fclose($output);
        exit;
    }

    // JSON response
    header("Content-Type: application/json");
    echo json_encode([
        'success' => true,
        'filters' => [
            'course_id' => $courseId,
            'semester_id' => $semesterId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ],
        'total_sessions_held' => $totalSessionsHeld,
        'total_students' => count($reportData),
        'records' => $reportData
    ]);

} catch (Exception $e) {
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode(['error' => 'Report generation failed', 'message' => $e->getMessage()]);
}
