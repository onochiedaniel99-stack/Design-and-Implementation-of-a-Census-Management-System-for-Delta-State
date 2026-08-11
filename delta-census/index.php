<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: enumerator_dashboard.php');
    }
    exit();
}

header('Location: login.php');
exit();
?>