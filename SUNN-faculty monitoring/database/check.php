<?php
require_once __DIR__ . '/../config/database.php';
echo "<h2>SUNN Faculty Monitoring System - Database Check</h2>";
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    echo "<p style='color:green'>✓ MySQL Connection successful</p>";

    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'sunn_faculty_monitoring'");
    if ($stmt->fetch()) {
        echo "<p style='color:green'>✓ Database 'sunn_faculty_monitoring' exists</p>";
        $pdo->exec("USE sunn_faculty_monitoring");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p style='color:green'>✓ Tables found: " . count($tables) . "</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<li>$table ($count records)</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:orange'>⚠ Database not yet created. <a href='init.php'>Click here to initialize</a></p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
?>
<p><a href="../login.php">Go to Login</a></p>
