<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

Middleware::requireAdmin();
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = max(0, ($page - 1) * $limit);

$action = $_GET['action'] ?? null;
$search = $_GET['search'] ?? null;
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

try {
    $where = ["1=1"];
    $params = [];

    if ($action) {
        $where[] = "a.action = :action";
        $params['action'] = $action;
    }
    if ($search) {
        $where[] = "(a.description LIKE :search OR u.email LIKE :search2 OR d.device_id LIKE :search3)";
        $params['search'] = "%{$search}%";
        $params['search2'] = "%{$search}%";
        $params['search3'] = "%{$search}%";
    }
    if ($dateFrom) {
        $where[] = "a.created_at >= :dfrom";
        $params['dfrom'] = $dateFrom . " 00:00:00";
    }
    if ($dateTo) {
        $where[] = "a.created_at <= :dto";
        $params['dto'] = $dateTo . " 23:59:59";
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM audit_logs a 
                               LEFT JOIN users u ON a.user_id = u.id 
                               LEFT JOIN devices d ON a.device_id = d.id 
                               WHERE {$whereClause}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['total'];

    $query = "SELECT a.id, a.action, a.description, a.ip_address, a.created_at,
                     u.id as user_id, u.email as user_email, u.full_name as user_name, u.role as user_role,
                     d.id as device_id, d.device_id as device_code, d.hardware_uid
              FROM audit_logs a
              LEFT JOIN users u ON a.user_id = u.id
              LEFT JOIN devices d ON a.device_id = d.id
              WHERE {$whereClause}
              ORDER BY a.created_at DESC
              LIMIT {$limit} OFFSET {$offset}";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $logs,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Audit log retrieval failed', 'message' => $e->getMessage()]);
}
