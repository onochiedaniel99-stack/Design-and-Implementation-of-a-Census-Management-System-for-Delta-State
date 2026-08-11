<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

global $pdo;

// Get filters
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'activities';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$success = isset($_GET['success']) ? (int)$_GET['success'] : null;

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get data based on tab
$activities = [];
$loginHistory = [];
$failedAttempts = [];
$stats = getActivityStats();
$totalRecords = 0;

switch ($tab) {
    case 'activities':
        $filters = [
            'user_id' => $userId,
            'action' => $action,
            'category' => $category,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        $activities = getAuditLogs($filters);
        $totalRecords = count($activities);
        // Apply pagination
        $activities = array_slice($activities, $offset, $perPage);
        break;
        
    case 'login':
        $filters = [
            'user_id' => $userId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        if (isset($success)) {
            $filters['success'] = $success;
        }
        $loginHistory = getLoginHistory($filters);
        $totalRecords = count($loginHistory);
        $loginHistory = array_slice($loginHistory, $offset, $perPage);
        break;
        
    case 'failed':
        $failedAttempts = getFailedLoginAttempts(100);
        $totalRecords = count($failedAttempts);
        break;
}

// Get users for filter
$users = $pdo->query("SELECT id, username, surname, first_name FROM users ORDER BY surname")->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM audit_logs ORDER BY category")->fetchAll();

// Get actions for filter
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Audit Logs - Delta Census</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .app-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .mobile-nav { background: white; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .nav-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .user-menu { display: flex; align-items: center; gap: 12px; }
        .btn-sm { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; }
        .btn-primary { background: #2563eb; color: white; border: none; cursor: pointer; }
        .btn-secondary { background: #64748b; color: white; border: none; cursor: pointer; }
        .btn-danger { background: #ef4444; color: white; border: none; cursor: pointer; }
        .btn-success { background: #22c55e; color: white; border: none; cursor: pointer; }
        .btn-warning { background: #f59e0b; color: white; border: none; cursor: pointer; }
        
        .admin-nav { background: white; border-radius: 8px; padding: 12px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .admin-nav .btn { padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s ease; }
        .admin-nav .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        .tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 2px solid #e2e8f0; }
        .tab { padding: 12px 24px; border: none; background: none; cursor: pointer; font-size: 15px; font-weight: 500; color: #64748b; border-bottom: 3px solid transparent; transition: all 0.2s ease; }
        .tab:hover { color: #0f172a; }
        .tab.active { color: #2563eb; border-bottom-color: #2563eb; }
        .tab .badge { background: #e2e8f0; padding: 2px 8px; border-radius: 9999px; font-size: 11px; margin-left: 6px; }
        .tab.active .badge { background: #dbeafe; color: #2563eb; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: white; border-radius: 8px; padding: 16px; text-align: center; border: 1px solid #e2e8f0; }
        .stat-box .number { font-size: 24px; font-weight: 700; color: #2563eb; }
        .stat-box .label { font-size: 13px; color: #64748b; margin-top: 4px; }
        .stat-box.danger .number { color: #ef4444; }
        .stat-box.success .number { color: #22c55e; }
        
        .filters { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; padding: 16px; background: #f8fafc; border-radius: 8px; }
        .filters input, .filters select { padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; flex: 1; min-width: 120px; }
        .filters input:focus, .filters select:focus { outline: none; border-color: #2563eb; }
        
        .table-responsive { overflow-x: auto; }
        .audit-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .audit-table th { background: #f1f5f9; padding: 10px 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; position: sticky; top: 0; }
        .audit-table td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .audit-table tr:hover { background: #f8fafc; }
        
        .badge-status { padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .pagination .btn { padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 4px; text-decoration: none; color: #0f172a; background: white; }
        .pagination .btn.active { background: #2563eb; color: white; border-color: #2563eb; }
        .pagination .btn:hover { background: #f1f5f9; }
        .pagination .btn.active:hover { background: #1d4ed8; }
        
        .action-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #64748b; }
        .action-tag.create { background: #dcfce7; color: #166534; }
        .action-tag.update { background: #dbeafe; color: #1e40af; }
        .action-tag.delete { background: #fee2e2; color: #991b1b; }
        .action-tag.login { background: #fef3c7; color: #92400e; }
        .action-tag.logout { background: #e0e7ff; color: #3730a3; }
        .action-tag.verify { background: #dcfce7; color: #166534; }
        .action-tag.reject { background: #fee2e2; color: #991b1b; }
        
        .text-muted { color: #64748b; }
        .text-center { text-align: center; }
        .text-truncate { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; }
        
        @media (max-width: 768px) {
            .tabs { flex-wrap: wrap; }
            .tab { flex: 1; text-align: center; padding: 10px 12px; font-size: 13px; }
            .filters { flex-direction: column; }
            .filters input, .filters select { width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .admin-nav { flex-direction: column; align-items: stretch; }
            .admin-nav .btn { text-align: center; }
            .audit-table { font-size: 12px; }
            .audit-table th, .audit-table td { padding: 6px 4px; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2 style="font-size: 20px;">📋 Audit Logs</h2>
                <div class="user-menu">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    <a href="admin_dashboard.php" class="btn-sm btn-secondary">Dashboard</a>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>

        <div class="admin-nav">
            <span class="nav-label">📋 Menu:</span>
            <a href="admin_dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="admin_households.php" class="btn btn-secondary">🏠 Households</a>
            <a href="admin_verifications.php" class="btn btn-secondary">✅ Verifications</a>
            <a href="admin_reports.php" class="btn btn-secondary">📊 Reports</a>
            <a href="admin_audit.php" class="btn btn-primary">📋 Audit Logs</a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="number"><?php echo $stats['today']; ?></div>
                <div class="label">Activities Today</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo $stats['week']; ?></div>
                <div class="label">This Week</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo $stats['month']; ?></div>
                <div class="label">This Month</div>
            </div>
            <div class="stat-box danger">
                <div class="number"><?php echo $stats['failed_today']; ?></div>
                <div class="label">Failed Logins Today</div>
            </div>
            <div class="stat-box success">
                <div class="number"><?php echo $stats['active_users']; ?></div>
                <div class="label">Active Users Today</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab <?php echo $tab === 'activities' ? 'active' : ''; ?>" onclick="window.location.href='admin_audit.php?tab=activities'">
                📝 Activities
                <span class="badge">All</span>
            </button>
            <button class="tab <?php echo $tab === 'login' ? 'active' : ''; ?>" onclick="window.location.href='admin_audit.php?tab=login'">
                🔑 Login History
                <span class="badge">Records</span>
            </button>
            <button class="tab <?php echo $tab === 'failed' ? 'active' : ''; ?>" onclick="window.location.href='admin_audit.php?tab=failed'">
                ❌ Failed Logins
                <span class="badge">Attempts</span>
            </button>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 12px; width: 100%;">
                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                
                <select name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['surname'] . ', ' . $user['first_name'] . ' (' . $user['username'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if ($tab === 'activities'): ?>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="action" placeholder="Search action..." value="<?php echo htmlspecialchars($action); ?>">
                <?php endif; ?>
                
                <?php if ($tab === 'login'): ?>
                    <select name="success">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $success === 1 ? 'selected' : ''; ?>>Successful</option>
                        <option value="0" <?php echo $success === 0 ? 'selected' : ''; ?>>Failed</option>
                    </select>
                <?php endif; ?>
                
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" placeholder="Date From">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" placeholder="Date To">
                
                <button type="submit" class="btn btn-primary" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Apply Filters</button>
                <a href="admin_audit.php?tab=<?php echo $tab; ?>" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 4px; text-decoration: none;">Clear</a>
            </form>
        </div>

        <!-- Content -->
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <?php if ($tab === 'activities'): ?>
                <h3 style="margin-bottom: 16px;">📝 Activity Log</h3>
                <div class="table-responsive">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($activities) > 0): ?>
                                <?php foreach ($activities as $log): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                            <br><small class="text-muted"><?php echo ucfirst($log['user_role']); ?></small>
                                        </td>
                                        <td>
                                            <span class="action-tag <?php echo strtolower($log['action']); ?>">
                                                <?php echo ucfirst($log['action']); ?>
                                            </span>
                                        </td>
                                        <td><span class="badge badge-info"><?php echo ucfirst($log['category']); ?></span></td>
                                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                                        <td><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                                        <td><small><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 40px;">
                                        <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                                        <h3>No Activities Found</h3>
                                        <p class="text-muted">No activity logs match your filters.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($tab === 'login'): ?>
                <h3 style="margin-bottom: 16px;">🔑 Login History</h3>
                <div class="table-responsive">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Login Time</th>
                                <th>Logout Time</th>
                                <th>Status</th>
                                <th>IP Address</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($loginHistory) > 0): ?>
                                <?php foreach ($loginHistory as $log): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                            <br><small class="text-muted"><?php echo ucfirst($log['user_role']); ?></small>
                                        </td>
                                        <td><small><?php echo date('M d, Y H:i', strtotime($log['login_time'])); ?></small></td>
                                        <td>
                                            <?php if ($log['logout_time']): ?>
                                                <small><?php echo date('M d, Y H:i', strtotime($log['logout_time'])); ?></small>
                                            <?php else: ?>
                                                <span class="badge-status badge-warning">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['success']): ?>
                                                <span class="badge-status badge-success">✅ Success</span>
                                            <?php else: ?>
                                                <span class="badge-status badge-danger">❌ Failed</span>
                                                <?php if ($log['failure_reason']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($log['failure_reason']); ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                                        <td>
                                            <?php if ($log['logout_time'] && $log['login_time']): ?>
                                                <small>
                                                    <?php 
                                                    $diff = strtotime($log['logout_time']) - strtotime($log['login_time']);
                                                    $hours = floor($diff / 3600);
                                                    $minutes = floor(($diff % 3600) / 60);
                                                    echo ($hours ? $hours . 'h ' : '') . $minutes . 'm';
                                                    ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 40px;">
                                        <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                                        <h3>No Login Records Found</h3>
                                        <p class="text-muted">No login history matches your filters.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($tab === 'failed'): ?>
                <h3 style="margin-bottom: 16px;">❌ Failed Login Attempts</h3>
                <div class="table-responsive">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Attempt Time</th>
                                <th>IP Address</th>
                                <th>Failure Reason</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($failedAttempts) > 0): ?>
                                <?php foreach ($failedAttempts as $attempt): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($attempt['username']); ?></strong>
                                            <br><small class="text-muted"><?php echo ucfirst($attempt['user_role']); ?></small>
                                        </td>
                                        <td><small><?php echo date('M d, Y H:i:s', strtotime($attempt['login_time'])); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($attempt['ip_address']); ?></small></td>
                                        <td>
                                            <?php if ($attempt['failure_reason']): ?>
                                                <span class="badge-status badge-danger"><?php echo htmlspecialchars($attempt['failure_reason']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-truncate" title="<?php echo htmlspecialchars($attempt['user_agent']); ?>">
                                            <?php echo htmlspecialchars(substr($attempt['user_agent'], 0, 50)) . '...'; ?>
                                        </small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center" style="padding: 40px;">
                                        <div style="font-size: 48px; margin-bottom: 16px;">🔒</div>
                                        <h3>No Failed Login Attempts</h3>
                                        <p class="text-muted">All login attempts have been successful.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalRecords > $perPage): ?>
                <div class="pagination">
                    <?php 
                    $totalPages = ceil($totalRecords / $perPage);
                    for ($i = 1; $i <= $totalPages; $i++): 
                        $params = $_GET;
                        $params['page'] = $i;
                        $url = '?' . http_build_query($params);
                    ?>
                        <a href="<?php echo $url; ?>" class="btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="text-muted" style="margin-top: 12px; font-size: 13px;">
                Showing <?php echo count($tab === 'activities' ? $activities : ($tab === 'login' ? $loginHistory : $failedAttempts)); ?> of <?php echo $totalRecords; ?> records
            </div>
        </div>
    </div>
</body>
</html>