<?php
require_once 'includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Location Assigned</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">📍</div>
                <h1>No Location Assigned</h1>
                <p class="subtitle">Your account hasn't been assigned to any location yet.</p>
            </div>
            
            <div class="alert alert-warning">
                Please contact your administrator to assign you to an LGA and Ward.
            </div>
            
            <a href="logout.php" class="btn btn-primary btn-block">Logout</a>
        </div>
    </div>
</body>
</html>