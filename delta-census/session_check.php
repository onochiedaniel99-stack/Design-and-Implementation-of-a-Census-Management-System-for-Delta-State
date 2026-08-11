<?php
/**
 * Session Check Helper
 * Include this at the top of files that need session
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function checkAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        header('Location: login.php');
        exit();
    }
}

// Function to check admin role
function checkAdmin() {
    checkAuth();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit();
    }
}
?>