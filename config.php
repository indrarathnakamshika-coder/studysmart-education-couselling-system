<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'studysmart_db';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

// Ensure consistent character set
$mysqli->set_charset('utf8mb4');

// Auto-create users table if it does not exist yet.
$createUsersTableSql = "
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

if (!$mysqli->query($createUsersTableSql)) {
    die('Failed to initialize users table: ' . $mysqli->error);
}
