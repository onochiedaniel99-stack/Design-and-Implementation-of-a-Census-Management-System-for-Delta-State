<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

global $pdo;

// Get statistics
// Total households
$stmt = $pdo->query("SELECT COUNT(*) as total FROM households");
$totalHouseholds = $stmt->fetch()['total'];

// Households by status
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM households 
    GROUP BY status
");
$statusStats = $stmt->fetchAll();

// Total enumerators
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'enumerator'");
$totalEnumerators = $stmt->fetch()['total'];

// Total members
$stmt = $pdo->query("SELECT COUNT(*) as total FROM household_members");
$totalMembers = $stmt->fetch()['total'];

// Recent households
$stmt = $pdo->query("
    SELECT h.*, u.username as enumerator_name 
    FROM households h
    LEFT JOIN users u ON h.enumerator_id = u.id
    ORDER BY h.created_at DESC 
    LIMIT 10
");
$recentHouseholds = $stmt->fetchAll();

// Pending verifications
$stmt = $pdo->query("
    SELECT COUNT(*) as pending 
    FROM households 
    WHERE status = 'submitted'
");
$pendingVerifications = $stmt->fetch()['pending'];

// Get status counts for chart
$statusCounts = [
    'draft' => 0,
    'submitted' => 0,
    'verified' => 0,
    'rejected' => 0
];
foreach ($statusStats as $stat) {
    $statusCounts[$stat['status']] = $stat['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard - Delta Census</title>
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
        .admin-nav .nav-label { font-weight: 600; color: #64748b; margin-right: 8px; font-size: 14px; }
        .admin-nav .btn { padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s ease; }
        .admin-nav .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-nav .btn-primary { background: #2563eb; color: white; }
        .admin-nav .btn-secondary { background: #64748b; color: white; }
        .admin-nav .btn-success { background: #22c55e; color: white; }
        .admin-nav .btn-warning { background: #f59e0b; color: white; }
        .admin-nav .btn-danger { background: #ef4444; color: white; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
        .stat-card .number { font-size: 32px; font-weight: 700; color: #2563eb; }
        .stat-card .label { font-size: 14px; color: #64748b; margin-top: 4px; }
        .stat-card .icon { font-size: 28px; margin-bottom: 8px; }
        .stat-card.pending .number { color: #f59e0b; }
        .stat-card.enumerators .number { color: #8b5cf6; }
        .stat-card.members .number { color: #22c55e; }
        
        .status-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
        .status-box { padding: 16px; border-radius: 8px; text-align: center; color: white; }
        .status-box.draft { background: #f59e0b; }
        .status-box.submitted { background: #2563eb; }
        .status-box.verified { background: #22c55e; }
        .status-box.rejected { background: #ef4444; }
        .status-box .number { font-size: 28px; font-weight: 700; }
        .status-box .label { font-size: 14px; opacity: 0.9; }
        
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .dashboard-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .dashboard-card h3 { margin-bottom: 16px; font-size: 18px; }
        .recent-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .recent-item:last-child { border-bottom: none; }
        .recent-item .code { font-weight: 600; color: #2563eb; }
        .recent-item .head { color: #0f172a; }
        .recent-item .status-badge { padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
        .status-badge.draft { background: #fef3c7; color: #92400e; }
        .status-badge.submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.verified { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        
        .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .quick-action { padding: 12px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #0f172a; text-align: center; transition: all 0.2s ease; border: 1px solid #e2e8f0; }
        .quick-action:hover { background: #f1f5f9; border-color: #2563eb; }
        .quick-action .icon { font-size: 24px; display: block; margin-bottom: 4px; }
        .quick-action .label { font-size: 12px; font-weight: 500; }
        
        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .status-grid { grid-template-columns: 1fr 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
            .admin-nav { flex-direction: column; align-items: stretch; }
            .admin-nav .btn { text-align: center; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Navigation -->
        <nav class="mobile-nav">
            <div class="nav-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <img src="images/logodelta.png" alt="Logo" style="height: 35px; width: auto;">
                    <h2 style="font-size: 20px;"> Admin Dashboard</h2>
                </div>
                <div class="user-menu">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>

        <!-- Admin Navigation -->
        <div class="admin-nav">
            <span class="nav-label">📋 Menu:</span>
            <a href="admin_dashboard.php" class="btn btn-primary">Dashboard</a>
            <a href="admin_enumerators.php" class="btn btn-secondary">👥 Enumerators</a>
            <a href="admin_households.php" class="btn btn-secondary">🏠 Households</a>
            <a href="admin_verifications.php" class="btn btn-secondary">✅ Verifications</a>
            <a href="admin_reports.php" class="btn btn-secondary">📊 Reports</a>
            <a href="admin_audit.php" class="btn btn-secondary">📋 Audit</a>
            <a href="admin_profile.php" class="btn btn-success">👤 Profile</a>
            <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">🏠</div>
                <div class="number"><?php echo $totalHouseholds; ?></div>
                <div class="label">Total Households</div>
            </div>
            <div class="stat-card members">
                <div class="icon">👥</div>
                <div class="number"><?php echo $totalMembers; ?></div>
                <div class="label">Total Members</div>
            </div>
            <div class="stat-card enumerators">
                <div class="icon">👤</div>
                <div class="number"><?php echo $totalEnumerators; ?></div>
                <div class="label">Enumerators</div>
            </div>
            <div class="stat-card pending">
                <div class="icon">⏳</div>
                <div class="number"><?php echo $pendingVerifications; ?></div>
                <div class="label">Pending Verification</div>
            </div>
        </div>

        <!-- Status Breakdown -->
        <div class="status-grid">
            <div class="status-box draft">
                <div class="number"><?php echo $statusCounts['draft']; ?></div>
                <div class="label">📝 Draft</div>
            </div>
            <div class="status-box submitted">
                <div class="number"><?php echo $statusCounts['submitted']; ?></div>
                <div class="label">📤 Submitted</div>
            </div>
            <div class="status-box verified">
                <div class="number"><?php echo $statusCounts['verified']; ?></div>
                <div class="label">✅ Verified</div>
            </div>
            <div class="status-box rejected">
                <div class="number"><?php echo $statusCounts['rejected']; ?></div>
                <div class="label">❌ Rejected</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="dashboard-grid">
            <!-- Recent Households -->
            <div class="dashboard-card">
                <h3>🕐 Recent Households</h3>
                <?php if (count($recentHouseholds) > 0): ?>
                    <?php foreach ($recentHouseholds as $household): ?>
                        <div class="recent-item">
                            <div>
                                <span class="code"><?php echo htmlspecialchars($household['household_code'] ?? $household['household_number']); ?></span>
                                <span class="head">- <?php echo htmlspecialchars($household['head_of_household'] ?? $household['household_head']); ?></span>
                                <br>
                                <small style="color: #64748b;"><?php echo htmlspecialchars($household['community']); ?> • <?php echo date('M d, Y', strtotime($household['created_at'])); ?></small>
                            </div>
                            <div>
                                <span class="status-badge <?php echo $household['status']; ?>">
                                    <?php echo ucfirst($household['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No households registered yet.</p>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-card">
                <h3>⚡ Quick Actions</h3>
                <div class="quick-actions">
                    <a href="admin_verifications.php" class="quick-action">
                        <span class="icon">✅</span>
                        <span class="label">Verify Submissions</span>
                    </a>
                    <a href="admin_households.php" class="quick-action">
                        <span class="icon">🏠</span>
                        <span class="label">View All Households</span>
                    </a>
                    <a href="admin_reports.php" class="quick-action">
                        <span class="icon">📊</span>
                        <span class="label">Generate Reports</span>
                    </a>
                    <a href="admin_export.php" class="quick-action">
                        <span class="icon">📤</span>
                        <span class="label">Export Data</span>
                    </a>
                    <a href="admin_enumerators.php" class="quick-action">
                        <span class="icon">👥</span>
                        <span class="label">Manage Enumerators</span>
                    </a>
                    <a href="admin_users.php" class="quick-action">
                        <span class="icon">➕</span>
                        <span class="label">Create Enumerator</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>