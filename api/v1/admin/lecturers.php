<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireAdmin();
$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List lecturers
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : null;

    $query = "SELECT id, email, full_name, faculty, department, status, created_at FROM users WHERE role = 'lecturer'";
    $countQuery = "SELECT COUNT(*) as total FROM users WHERE role = 'lecturer'";
    
    $params = [];
    if ($search) {
        $query .= " AND (full_name LIKE :search OR email LIKE :search2)";
        $countQuery .= " AND (full_name LIKE :search OR email LIKE :search2)";
        $params['search'] = $search;
        $params['search2'] = $search;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $lecturers = $stmt->fetchAll();
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'data' => $lecturers,
        'meta' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit
        ]
    ]);
} elseif ($method === 'POST') {
    // Create new lecturer
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['email']) || empty($input['full_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and full_name are required']);
        exit;
    }

    $email = trim($input['email']);
    $fullName = trim($input['full_name']);
    $faculty = $input['faculty'] ?? null;
    $department = $input['department'] ?? null;
    
    // Generate secure temporary password
    $tempPassword = bin2hex(random_bytes(6)); // 12 chars
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, full_name, faculty, department, force_password_change) VALUES (:email, :password_hash, 'lecturer', :full_name, :faculty, :department, 1)");
        $stmt->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'full_name' => $fullName,
            'faculty' => $faculty,
            'department' => $department
        ]);
        
        $userId = $db->lastInsertId();
        
        // Log action
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'CREATE_LECTURER', :desc)");
        $logStmt->execute([
            'uid' => $_SESSION['user_id'] ?? null,
            'desc' => "Created lecturer {$email}"
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Lecturer created successfully',
            'data' => [
                'id' => $userId,
                'email' => $email,
                'temporary_password' => $tempPassword // In a real system, send this via email
            ]
        ]);
    } catch (PDOException $e) {
        $db->rollBack();
        http_response_code(400);
        if ($e->getCode() == 23000) {
            echo json_encode(['error' => 'Email already exists']);
        } else {
            echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
        }
    }
} elseif ($method === 'PUT' || $method === 'PATCH') {
    // Edit/Disable/Enable or Reset Password
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Lecturer ID is required']);
        exit;
    }
    
    $id = (int)$input['id'];
    
    // Check if it's a reset password request
    if (isset($input['action']) && $input['action'] === 'reset_password') {
        $tempPassword = bin2hex(random_bytes(6));
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        try {
            $stmt = $db->prepare("UPDATE users SET password_hash = :hash, force_password_change = 1 WHERE id = :id AND role = 'lecturer'");
            $stmt->execute(['hash' => $passwordHash, 'id' => $id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Password reset successfully',
                'temporary_password' => $tempPassword
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }

    $updates = [];
    $params = ['id' => $id];
    
    if (isset($input['email'])) {
        $updates[] = "email = :email";
        $params['email'] = $input['email'];
    }
    if (isset($input['full_name'])) {
        $updates[] = "full_name = :full_name";
        $params['full_name'] = $input['full_name'];
    }
    if (isset($input['faculty'])) {
        $updates[] = "faculty = :faculty";
        $params['faculty'] = $input['faculty'];
    }
    if (isset($input['department'])) {
        $updates[] = "department = :department";
        $params['department'] = $input['department'];
    }
    if (isset($input['status']) && in_array($input['status'], ['active', 'disabled'])) {
        $updates[] = "status = :status";
        $params['status'] = $input['status'];
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $setClause = implode(', ', $updates);
    
    try {
        $stmt = $db->prepare("UPDATE users SET {$setClause} WHERE id = :id AND role = 'lecturer'");
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Lecturer updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} elseif ($method === 'DELETE') {
    // Delete lecturer
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Lecturer ID is required']);
        exit;
    }
    
    $id = (int)$input['id'];
    
    try {
        $db->beginTransaction();
        
        // Delete related courses, which should cascade delete enrollments, attendance, etc.
        // Assuming foreign keys are set up with ON DELETE CASCADE, if not we should handle them.
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'lecturer'");
        $stmt->execute(['id' => $id]);
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Lecturer deleted successfully']);
    } catch (PDOException $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error: Ensure related records are deleted first.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
