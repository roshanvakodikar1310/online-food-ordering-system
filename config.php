
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// MySQL database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'food_ordering_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Connect to MySQL using PDO
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST .
        ';dbname=' . DB_NAME .
        ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please check your MySQL settings.');
}
