<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireLecturer();
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List all registered students globally
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : null;

    $query = "SELECT id, student_id, full_name, department, level, created_at FROM students";
    $countQuery = "SELECT COUNT(*) as total FROM students";
    
    $params = [];
    if ($search) {
        $query .= " WHERE student_id LIKE :search OR full_name LIKE :search2";
        $countQuery .= " WHERE student_id LIKE :search OR full_name LIKE :search2";
        $params['search'] = $search;
        $params['search2'] = $search;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'data' => $students,
        'meta' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit
        ]
    ]);
} elseif ($method === 'POST') {
    // Create new student record
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['student_id']) || empty($input['full_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'student_id (matriculation number) and full_name are required']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO students (student_id, full_name, department, level) VALUES (:sid, :fname, :dept, :level)");
        $stmt->execute([
            'sid' => trim($input['student_id']),
            'fname' => trim($input['full_name']),
            'dept' => $input['department'] ?? null,
            'level' => $input['level'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Student created successfully',
            'data' => ['id' => $db->lastInsertId()]
        ]);
    } catch (PDOException $e) {
        http_response_code(400);
        if ($e->getCode() == 23000) {
            echo json_encode(['error' => 'Student ID already exists in the system']);
        } else {
            echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
        }
    }
} elseif ($method === 'PUT') {
    // Edit student record
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id']) || empty($input['student_id']) || empty($input['full_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'id, student_id and full_name are required']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE students SET student_id = :sid, full_name = :fname, department = :dept, level = :level WHERE id = :id");
        $stmt->execute([
            'id' => (int)$input['id'],
            'sid' => trim($input['student_id']),
            'fname' => trim($input['full_name']),
            'dept' => $input['department'] ?? null,
            'level' => $input['level'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Student updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(400);
        if ($e->getCode() == 23000) {
            echo json_encode(['error' => 'Student ID already exists in the system']);
        } else {
            echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
