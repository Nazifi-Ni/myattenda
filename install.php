<?php
require_once __DIR__ . '/api/config/database.php';

echo "<h1>MyAttenda Database Installer</h1>";

try {
    $db = Database::getConnection();
    echo "<p style='color:green;'>1. Successfully connected to the database.</p>";
    
    $sqlFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($sqlFile)) {
        die("<p style='color:red;'>Error: database/schema.sql not found.</p>");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Execute the SQL file. PDO execute() can handle multiple statements if configured,
    // but the safest way is to split by semicolon, or just use exec() for the whole string.
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
    $result = $db->exec($sql);
    
    echo "<p style='color:green;'>2. Schema successfully imported!</p>";
    
    // Create a default admin user if one doesn't exist
    $stmt = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if (!$stmt->fetch()) {
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $ins = $db->prepare("INSERT INTO users (email, password_hash, full_name, role) VALUES ('admin@myattenda.com', :pass, 'System Admin', 'admin')");
        $ins->execute(['pass' => $adminPass]);
        echo "<p style='color:green;'>3. Default Super Admin created! (Email: <b>admin@myattenda.com</b> | Password: <b>admin123</b>)</p>";
    }
    
    echo "<h3>Installation Complete!</h3>";
    echo "<p><a href='/login.php'>Go to Login</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Installation Failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
