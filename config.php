<?php
// config.php
// Database defaults - CHANGE THESE ON PRODUCTION
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_systems');

// App Settings
define('APP_NAME', 'Beetech Mini Super Shop');
define('CURRENCY', '৳');
define('TIMEZONE', 'Asia/Dhaka');

// Business Logic Settings


// Error Reporting (Turn off for public production, On for dev)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start Session globally
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set Timezone
date_default_timezone_set(TIMEZONE);
