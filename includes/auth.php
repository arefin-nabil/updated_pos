<?php
// includes/auth.php

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Require login to access page
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Check if current user is admin
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require admin role to access page
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        die("Access Denied: You do not have permission to view this page.");
    }
}

/**
 * Get current user ID
 */
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}
?>
