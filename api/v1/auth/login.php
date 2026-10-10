<?php

header("Content-Type: application/json");
require_once __DIR__ . '/../../core/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(["error" => "Email and password are required"]);
    exit;
}

$email = trim($input['email']);
$password = $input['password'];

$result = Auth::login($email, $password);

if ($result['success']) {
    // Generate CSRF token for the frontend to use in subsequent requests
    $result['csrf_token'] = Auth::generateCsrfToken();
    echo json_encode($result);
} else {
    http_response_code(401);
    echo json_encode($result);
}
