<?php
// includes/functions.php

/**
 * Sanitize input data
 */
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Format currency
 */
function format_money($amount) {
    return CURRENCY . ' ' . number_format($amount, 2);
}

/**
 * Generate a random alphanumeric string for file naming or temp IDs
 */
function generate_random_string($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

/**
 * Flash message helper (Set)
 */
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning'
        'message' => $message
    ];
}

/**
 * Flash message helper (Display)
 */
function display_flash_message() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $msg = $_SESSION['flash']['message'];
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$msg}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        unset($_SESSION['flash']);
    }
}
?>
