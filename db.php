<?php
// ============================================
// config/db.php
// Database connection using MySQLi
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'isdn_db');

// Create connection
try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (mysqli_sql_exception $e) {
    die("<div style='padding:20px; background:#fff5f5; color:#c53030; border:1px solid #feb2b2; margin:20px; font-family:sans-serif;'>
            <h3 style='margin-top:0;'>Database Connection Failed</h3>
            <p>Could not connect to the database. Please ensure your <strong>XAMPP MySQL service</strong> is running.</p>
            <p><small>Error details: " . $e->getMessage() . "</small></p>
         </div>");
}

// Check connection (for older PHP versions)
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Set charset for security
mysqli_set_charset($conn, 'utf8mb4');
