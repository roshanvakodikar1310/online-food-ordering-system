
<?php
require_once __DIR__ . '/config.php';

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "MySQL Connected Successfully!<br>";
    echo "Database: food_ordering_db<br>";
    echo "Tables found: " . implode(", ", $tables);
} catch (PDOException $e) {
    echo "Table check failed. Please check the database setup.";
}
?>
