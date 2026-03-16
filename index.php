<?php
// index.php
require_once 'config.php';
require_once 'includes/auth.php';

// Redirect to dashboard if already logged in, otherwise go to login page
if (is_logged_in()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
