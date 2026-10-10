<?php

require_once __DIR__ . '/Auth.php';

class Middleware {
    /**
     * Ensure the request has a valid logged-in web session.
     */
    public static function requireAuth(): void {
        if (!Auth::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized - Please log in"]);
            exit;
        }
    }

    /**
     * Ensure the logged-in user is a Super Admin.
     */
    public static function requireAdmin(): void {
        self::requireAuth();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(["error" => "Forbidden - Admin access required"]);
            exit;
        }
    }

    /**
     * Ensure the logged-in user is a Lecturer.
     */
    public static function requireLecturer(): void {
        self::requireAuth();
        if (($_SESSION['role'] ?? '') !== 'lecturer') {
            http_response_code(403);
            echo json_encode(["error" => "Forbidden - Lecturer access required"]);
            exit;
        }
    }

    /**
     * Ensure the request provides a valid CSRF token in the headers.
     */
    public static function requireCsrf(): void {
        $headers = apache_request_headers();
        $token = $headers['X-CSRF-Token'] ?? null;

        if (!Auth::verifyCsrfToken($token)) {
            http_response_code(403);
            echo json_encode(["error" => "Forbidden - CSRF token invalid or missing"]);
            exit;
        }
    }

    /**
     * Ensure the request originates from an authenticated ESP32 device.
     * Uses Bearer Token authorization.
     * 
     * @return int The ID of the authenticated device.
     */
    public static function requireDevice(): int {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized - Missing or invalid Bearer token"]);
            exit;
        }

        $apiKey = $matches[1];
        
        $db = Database::getConnection();
        // Look up devices that are active and check the key hash
        $stmt = $db->prepare("SELECT id, api_key_hash, status FROM devices WHERE status = 'active'");
        $stmt->execute();
        $devices = $stmt->fetchAll();

        foreach ($devices as $device) {
            if ($device['api_key_hash'] && password_verify($apiKey, $device['api_key_hash'])) {
                return (int)$device['id'];
            }
        }

        http_response_code(401);
        echo json_encode(["error" => "Unauthorized - Invalid Device API Key"]);
        exit;
    }
}

// Polyfill for apache_request_headers if running in CLI or non-apache environment
if (!function_exists('apache_request_headers')) {
    function apache_request_headers() {
        $arh = array();
        $rx_http = '/\AHTTP_/';
        foreach($_SERVER as $key => $val) {
            if( preg_match($rx_http, $key) ) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = array();
                // do some nasty string manipulations to restore the original letter case
                // this should work in most cases
                $rx_matches = explode('_', $arh_key);
                if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
                    foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst(strtolower($ak_val));
                    $arh_key = implode('-', $rx_matches);
                }
                $arh[$arh_key] = $val;
            }
        }
        if(isset($_SERVER['CONTENT_TYPE'])) $arh['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        if(isset($_SERVER['CONTENT_LENGTH'])) $arh['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        return $arh;
    }
}
