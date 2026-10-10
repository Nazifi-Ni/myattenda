<?php
require_once __DIR__ . '/../api/config/database.php';

try {
    $db = Database::getConnection();
    echo "Connected to database successfully.\n";

    // 1. Create or Update Super Admin
    $adminEmail = 'admin@test.com';
    $password = 'password123';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $adminEmail]);
    $admin = $stmt->fetch();

    if ($admin) {
        $upd = $db->prepare("UPDATE users SET password_hash = :hash, role = 'admin', status = 'active', full_name = 'System Super Admin' WHERE id = :id");
        $upd->execute(['hash' => $hash, 'id' => $admin['id']]);
        echo "Updated existing Super Admin user: {$adminEmail}\n";
    } else {
        $ins = $db->prepare("INSERT INTO users (email, password_hash, role, full_name, status) VALUES (:email, :hash, 'admin', 'System Super Admin', 'active')");
        $ins->execute(['email' => $adminEmail, 'hash' => $hash]);
        echo "Created new Super Admin user: {$adminEmail}\n";
    }

    echo "\n=== SEEDING COMPLETED SUCCESSFULLY ===\n";
    echo "You can now log in with:\n";
    echo "Super Admin: admin@test.com / password123\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
