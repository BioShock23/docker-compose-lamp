<?php
define('DB_HOST', 'database');
define('DB_NAME', 'gas_tracking');
define('DB_USER', 'gas_app');
define('DB_PASS', 'gas_app_password');
define('DB_CHARSET', 'utf8mb4');

function getConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        exit('Database connection failed: ' . $e->getMessage());
    }
} 