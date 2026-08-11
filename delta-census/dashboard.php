<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$user = getUserById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>User Dashboard - Delta Census</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Mobile Navigation -->
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2>Delta Census</h2>
                <div class="user-menu">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="logout.php" class="btn btn-sm btn-secondary">Logout</a>
                </div>
            </div>
        </nav>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h3>
                    <p>Role: <?php echo ucfirst($_SESSION['role']); ?></p>
                    <p>Last Login: <?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'First login'; ?></p>
                </div>
                
                <div class="dashboard-card">
                    <h3>Quick Actions</h3>
                    <div class="action-buttons">
                        <button class="btn btn-primary">View Census Data</button>
                        <button class="btn btn-secondary">Submit Report</button>
                        <button class="btn btn-secondary">View Profile</button>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3>Recent Activity</h3>
                    <div class="activity-list">
                        <p>No recent activity</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>