<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireLecturer();
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT id, name, status, start_date, end_date, created_at FROM semesters ORDER BY created_at DESC");
    echo json_encode([
        'success' => true,
        'data' => $stmt->fetchAll()
    ]);
} elseif ($method === 'POST') {
    // Create new semester
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Semester name is required']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO semesters (name, status, start_date, end_date) VALUES (:name, 'draft', :start_date, :end_date)");
        $stmt->execute([
            'name' => trim($input['name']),
            'start_date' => $input['start_date'] ?? null,
            'end_date' => $input['end_date'] ?? null
        ]);
        
        $insertId = $db->lastInsertId();
        
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'CREATE_SEMESTER', :desc)");
        $logStmt->execute([
            'uid' => $_SESSION['user_id'] ?? null,
            'desc' => "Created draft semester: " . trim($input['name'])
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Semester created successfully',
            'data' => ['id' => $insertId]
        ]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
} elseif ($method === 'PUT' || $method === 'PATCH') {
    // Update semester status (e.g. DRAFT -> ACTIVE -> CLOSED -> ARCHIVED)
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id']) || empty($input['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Semester ID and status are required']);
        exit;
    }
    
    $id = (int)$input['id'];
    $status = $input['status'];
    
    $validStatuses = ['draft', 'active', 'closed', 'archived'];
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        exit;
    }

    try {
        $db->beginTransaction();
        
        if ($status === 'active') {
            // Rule: Only ONE semester can be active at a time
            $db->exec("UPDATE semesters SET status = 'closed' WHERE status = 'active'");
        }
        
        $stmt = $db->prepare("UPDATE semesters SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);
        
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'UPDATE_SEMESTER_STATUS', :desc)");
        $logStmt->execute([
            'uid' => $_SESSION['user_id'] ?? null,
            'desc' => "Changed semester ID {$id} status to {$status}"
        ]);
        
        $db->commit();
        
        echo json_encode(['success' => true, 'message' => 'Semester status updated successfully']);
    } catch (PDOException $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
} elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $semesterId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($input['id']) ? (int)$input['id'] : 0);
    
    if ($semesterId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Semester ID is required']);
        exit;
    }
    
    try {
        $db->beginTransaction();
        
        $chk = $db->prepare("SELECT id, name FROM semesters WHERE id = :id");
        $chk->execute(['id' => $semesterId]);
        $sem = $chk->fetch();
        if (!$sem) {
            throw new Exception("Semester not found.");
        }

        // Clean up linked attendance events and sessions
        $db->exec("DELETE ae FROM attendance_events ae INNER JOIN attendance_sessions asess ON ae.session_id = asess.id WHERE asess.semester_id = {$semesterId}");
        $db->exec("DELETE FROM attendance_sessions WHERE semester_id = {$semesterId}");

        // Clean up fingerprints and enrollments
        $db->exec("DELETE f FROM fingerprints f INNER JOIN enrollments e ON f.enrollment_id = e.id WHERE e.semester_id = {$semesterId}");
        $db->exec("DELETE FROM enrollments WHERE semester_id = {$semesterId}");

        // Unlink courses referencing this semester
        $db->exec("UPDATE courses SET semester_id = NULL WHERE semester_id = {$semesterId}");

        // Delete semester
        $stmt = $db->prepare("DELETE FROM semesters WHERE id = :id");
        $stmt->execute(['id' => $semesterId]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => "Semester '{$sem['name']}' deleted successfully."]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete semester: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
