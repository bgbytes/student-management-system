<?php
// Database configuration settings
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'student_db');

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if (!$conn) {
    die("ERROR: Could not connect to database. " . mysqli_connect_error());
}

// Set character set to utf8mb4 for proper encoding support
mysqli_set_charset($conn, "utf8mb4");
?>
