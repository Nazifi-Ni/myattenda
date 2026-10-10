<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireLecturer();
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List courses
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $lecturerId = $_SESSION['user_id'];
    $query = "SELECT c.id, c.course_code, c.course_title, c.department, c.credit_unit, c.status, c.lecturer_id, u.full_name as lecturer_name, c.semester_id, s.name as semester_name
              FROM courses c
              JOIN users u ON c.lecturer_id = u.id
              LEFT JOIN semesters s ON c.semester_id = s.id
              WHERE c.lecturer_id = :lid
              ORDER BY c.course_code ASC LIMIT $limit OFFSET $offset";
              
    $stmt = $db->prepare($query);
    $stmt->execute(['lid' => $lecturerId]);
    $courses = $stmt->fetchAll();
    
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM courses WHERE lecturer_id = :lid");
    $countStmt->execute(['lid' => $lecturerId]);
    $total = $countStmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'data' => $courses,
        'meta' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit
        ]
    ]);
} elseif ($method === 'POST') {
    // Create new course
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['course_code']) || empty($input['course_title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'course_code and course_title are required']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO courses (course_code, course_title, department, credit_unit, lecturer_id, semester_id) VALUES (:code, :title, :dept, :credits, :lecturer, :semester)");
        $stmt->execute([
            'code' => $input['course_code'],
            'title' => $input['course_title'],
            'dept' => $input['department'] ?? null,
            'credits' => $input['credit_unit'] ?? null,
            'lecturer' => $_SESSION['user_id'],
            'semester' => $input['semester_id'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Course created successfully',
            'data' => ['id' => $db->lastInsertId()]
        ]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
} elseif ($method === 'PUT' || $method === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Course ID is required']);
        exit;
    }
    
    $id = (int)$input['id'];
    $updates = [];
    $params = ['id' => $id];
    
    $allowedFields = ['course_code', 'course_title', 'department', 'credit_unit', 'status', 'semester_id'];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $input)) {
            $updates[] = "{$field} = :{$field}";
            $params[$field] = $input[$field];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $setClause = implode(', ', $updates);
    $params['lid'] = $_SESSION['user_id'];
    
    try {
        $stmt = $db->prepare("UPDATE courses SET {$setClause} WHERE id = :id AND lecturer_id = :lid");
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
