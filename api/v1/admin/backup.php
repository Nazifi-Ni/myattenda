<?php

require_once __DIR__ . '/../../core/Middleware.php';
require_once __DIR__ . '/../../config/database.php';

// Super Admin access required
Middleware::requireAdmin();

$db = Database::getConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'status');

try {
    if ($action === 'status') {
        header("Content-Type: application/json");

        // Fetch database name and version
        $dbNameStmt = $db->query("SELECT DATABASE() as db_name, VERSION() as version, NOW() as server_time");
        $meta = $dbNameStmt->fetch();
        $dbName = $meta['db_name'] ?? 'myattenda';
        $version = $meta['version'] ?? 'Unknown';
        $serverTime = $meta['server_time'] ?? date('Y-m-d H:i:s');

        // Fetch all tables with sizes and approximate row counts
        $tablesStmt = $db->prepare("
            SELECT 
                table_name AS name,
                ROUND(((data_length + index_length) / 1024), 2) AS size_kb,
                table_rows AS estimated_rows
            FROM information_schema.TABLES 
            WHERE table_schema = :db
            ORDER BY table_name ASC
        ");
        $tablesStmt->execute(['db' => $dbName]);
        $rawTables = $tablesStmt->fetchAll();

        $tables = [];
        $totalRecords = 0;
        $totalSizeKb = 0;

        foreach ($rawTables as $t) {
            $tName = $t['name'];
            // Accurate row count via SELECT COUNT(*)
            $cntStmt = $db->query("SELECT COUNT(*) as cnt FROM `{$tName}`");
            $exactRows = (int)$cntStmt->fetch()['cnt'];
            $sizeKb = (float)$t['size_kb'];

            $tables[] = [
                'name' => $tName,
                'rows' => $exactRows,
                'size_kb' => $sizeKb
            ];

            $totalRecords += $exactRows;
            $totalSizeKb += $sizeKb;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'database_name' => $dbName,
                'mysql_version' => $version,
                'server_time' => $serverTime,
                'total_tables' => count($tables),
                'total_records' => $totalRecords,
                'total_size_mb' => round($totalSizeKb / 1024, 2),
                'tables' => $tables
            ]
        ]);
        exit;

    } elseif ($action === 'export_sql') {
        // Clear any previous output buffers to avoid corrupting the download stream
        while (ob_get_level()) {
            ob_end_clean();
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = "myattenda_backup_{$timestamp}.sql";

        header('Content-Type: application/sql; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        fwrite($out, "-- ================================================================\n");
        fwrite($out, "-- MyAttenda V2 Complete Database Backup\n");
        fwrite($out, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($out, "-- Platform: PHP " . PHP_VERSION . " / MySQL PDO\n");
        fwrite($out, "-- ================================================================\n\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($out, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($out, "SET time_zone = '+01:00';\n\n");

        // Get list of tables
        $tablesStmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            fwrite($out, "\n-- --------------------------------------------------------\n");
            fwrite($out, "-- Table structure for `{$table}`\n");
            fwrite($out, "-- --------------------------------------------------------\n");
            fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");

            // Create table statement
            $createStmt = $db->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch();
            $createTableSql = $createRow['Create Table'] ?? '';
            fwrite($out, $createTableSql . ";\n\n");

            // Dump table data
            fwrite($out, "-- Dumping data for `{$table}`\n");
            $rowsStmt = $db->query("SELECT * FROM `{$table}`");
            $columns = [];
            for ($i = 0; $i < $rowsStmt->columnCount(); $i++) {
                $meta = $rowsStmt->getColumnMeta($i);
                $columns[] = '`' . $meta['name'] . '`';
            }
            $colList = implode(', ', $columns);

            $batchSize = 250;
            $batch = [];

            while ($row = $rowsStmt->fetch(PDO::FETCH_NUM)) {
                $escapedVals = array_map(function ($val) use ($db) {
                    if ($val === null) return 'NULL';
                    return $db->quote($val);
                }, $row);
                $batch[] = "(" . implode(', ', $escapedVals) . ")";

                if (count($batch) >= $batchSize) {
                    fwrite($out, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                fwrite($out, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n\n");
            } else {
                fwrite($out, "-- Table `{$table}` has no records.\n\n");
            }
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($out, "-- ================================================================\n");
        fwrite($out, "-- End of Backup\n");
        fwrite($out, "-- ================================================================\n");
        fclose($out);

        // Record in audit log
        try {
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'DATABASE_BACKUP', 'Downloaded complete database SQL backup')");
            $logStmt->execute(['uid' => $_SESSION['user_id'] ?? null]);
        } catch (Exception $e) {
            // Silently continue if audit log table is locked
        }
        exit;

    } elseif ($action === 'export_json') {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = "myattenda_backup_{$timestamp}.json";

        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $tablesStmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

        $dump = [
            'app' => 'MyAttenda V2',
            'exported_at' => date('Y-m-d H:i:s'),
            'tables' => []
        ];

        foreach ($tables as $table) {
            $rowsStmt = $db->query("SELECT * FROM `{$table}`");
            $dump['tables'][$table] = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        try {
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'DATABASE_BACKUP_JSON', 'Downloaded complete database JSON backup')");
            $logStmt->execute(['uid' => $_SESSION['user_id'] ?? null]);
        } catch (Exception $e) {}
        exit;

    } elseif ($action === 'restore_sql') {
        header("Content-Type: application/json");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $sqlContent = '';

        if (!empty($_FILES['backup_file']['tmp_name'])) {
            $sqlContent = file_get_contents($_FILES['backup_file']['tmp_name']);
        } else {
            $rawInput = json_decode(file_get_contents("php://input"), true);
            $sqlContent = $rawInput['sql'] ?? '';
        }

        if (empty(trim($sqlContent))) {
            http_response_code(400);
            echo json_encode(['error' => 'No SQL backup data provided for restore']);
            exit;
        }

        // Execute SQL script
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $db->exec($sqlContent);
        $db->exec("SET FOREIGN_KEY_CHECKS=1");

        try {
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (:uid, 'DATABASE_RESTORE', 'Restored database from SQL backup file')");
            $logStmt->execute(['uid' => $_SESSION['user_id'] ?? null]);
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'message' => 'Database successfully restored from backup!'
        ]);
        exit;

    } else {
        header("Content-Type: application/json");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action parameter']);
        exit;
    }

} catch (Exception $e) {
    if (!headers_sent()) {
        header("Content-Type: application/json");
        http_response_code(500);
    }
    echo json_encode([
        'error' => 'Database operation failed',
        'details' => $e->getMessage()
    ]);
    exit;
}
