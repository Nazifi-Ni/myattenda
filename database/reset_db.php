<?php
// database/reset_db.php
require_once __DIR__ . '/../api/config/database.php';

try {
    $db = Database::getConnection();
    echo "Connected to database successfully.<br><br>";

    // Disable foreign key checks to allow dropping tables
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Get all table names
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($tables)) {
        foreach ($tables as $table) {
            $db->exec("DROP TABLE IF EXISTS `$table`");
            echo "Dropped table: $table<br>";
        }
        echo "<br>All old tables dropped successfully.<br><br>";
    } else {
        echo "No existing tables found.<br><br>";
    }

    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Read and execute schema.sql
    $schemaPath = __DIR__ . '/schema.sql';
    if (file_exists($schemaPath)) {
        $sql = file_get_contents($schemaPath);
        // We must split by semicolon because PDO exec doesn't always handle multi-statement perfectly if not configured,
        // but PDO with MySQL usually allows it if emulate prepares is on. 
        // Actually, for robust multi-query, let's just run it directly.
        try {
            $db->exec($sql);
            echo "Successfully imported fresh schema.sql without mock data!<br><br>";
        } catch (PDOException $e) {
            echo "Error executing schema: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "Error: schema.sql not found at $schemaPath<br>";
    }

    echo "<b>Now redirecting you to seed the admin account... please wait.</b>";
    echo "<script>setTimeout(function() { window.location.href = 'seed_admin.php'; }, 2000);</script>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
