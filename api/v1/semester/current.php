<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../config/database.php';

$db = Database::getConnection();

try {
    $stmt = $db->query("SELECT id, name, status, start_date, end_date, created_at FROM semesters WHERE status = 'active' LIMIT 1");
    $semester = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'has_active_semester' => (bool)$semester,
        'semester' => $semester ?: null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
