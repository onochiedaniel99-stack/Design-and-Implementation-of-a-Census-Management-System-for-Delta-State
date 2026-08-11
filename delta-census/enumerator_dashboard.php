<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

global $pdo;

// Get enumerator's location
$stmt = $pdo->prepare("
    SELECT ul.*, u.surname, u.first_name 
    FROM user_locations ul
    JOIN users u ON ul.user_id = u.id
    WHERE ul.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$location = $stmt->fetch();

if (!$location) {
    header('Location: no_location.php');
    exit();
}

// Get statistics - Check if households table has status column
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM households 
        WHERE enumerator_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    // If status column doesn't exist, use a simpler query
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM households WHERE enumerator_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetch();
    $stats = [
        'total' => $total['total'],
        'draft' => 0,
        'submitted' => 0,
        'verified' => 0,
        'rejected' => 0
    ];
}

// Get recent households
try {
    $stmt = $pdo->prepare("
        SELECT * FROM households 
        WHERE enumerator_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recentHouseholds = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentHouseholds = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Enumerator Dashboard - Delta Census</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .app-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .mobile-nav {
            background: white;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-sm {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .dashboard-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .location-card {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }
        .location-card h3 {
            color: white;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        .quick-action-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: #0f172a;
        }
        .quick-action-card:hover {
            border-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .quick-action-card .icon {
            font-size: 32px;
            margin-bottom: 8px;
        }
        .quick-action-card .label {
            font-weight: 600;
        }
        .quick-action-card .description {
            font-size: 12px;
            color: #64748b;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        .stat-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .stat-box .number {
            font-size: 28px;
            font-weight: 700;
            color: #2563eb;
        }
        .stat-box .label {
            font-size: 14px;
            color: #64748b;
        }
        .stat-box.draft .number { color: #f59e0b; }
        .stat-box.submitted .number { color: #2563eb; }
        .stat-box.verified .number { color: #22c55e; }
        .stat-box.rejected .number { color: #ef4444; }
        .household-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .household-list-item:last-child {
            border-bottom: none;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.draft { background: #fef3c7; color: #92400e; }
        .status-badge.submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.verified { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        @media (max-width: 768px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
            .stat-grid {
                grid-template-columns: 1fr 1fr;
            }
            .household-list-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="mobile-nav">
            <div class="nav-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <img src="images/logodelta.png" alt="Logo" style="height: 35px; width: auto;">
                    <h2 style="font-size: 20px;">📊 Enumerator Dashboard</h2>
                </div>
                <div class="user-menu">
                    <span class="user-name" style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Enumerator'); ?></span>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>

        <!-- Assigned Location -->
        <div class="dashboard-card location-card">
            <h3 style="margin-bottom: 12px;">📍 Assigned Enumeration Area</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                <div><strong>LGA:</strong> <?php echo htmlspecialchars($location['lga']); ?></div>
                <div><strong>Ward:</strong> <?php echo htmlspecialchars($location['ward']); ?></div>
                <?php if ($location['community']): ?>
                    <div><strong>Community:</strong> <?php echo htmlspecialchars($location['community']); ?></div>
                <?php endif; ?>
                <?php if ($location['enumeration_area']): ?>
                    <div><strong>Enumeration Area:</strong> <?php echo htmlspecialchars($location['enumeration_area']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="register_household.php?step=1" class="quick-action-card">
                <div class="icon">🏠</div>
                <div class="label">Register Household</div>
                <div class="description">Start new household registration</div>
            </a>
            <a href="view_households.php" class="quick-action-card">
                <div class="icon">📋</div>
                <div class="label">View Households</div>
                <div class="description">View all your registered households</div>
            </a>
            <a href="view_households.php?status=draft" class="quick-action-card">
                <div class="icon">📝</div>
                <div class="label">My Drafts</div>
                <div class="description">View households in draft status</div>
            </a>
            <a href="view_households.php?status=submitted" class="quick-action-card">
                <div class="icon">📤</div>
                <div class="label">Submitted</div>
                <div class="description">View submitted households</div>
            </a>
        </div>

        <!-- Statistics -->
        <div class="dashboard-card">
            <h3 style="margin-bottom: 16px;">📈 Registration Statistics</h3>
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="label">Total Households</div>
                </div>
                <div class="stat-box draft">
                    <div class="number"><?php echo $stats['draft'] ?? 0; ?></div>
                    <div class="label">Draft</div>
                </div>
                <div class="stat-box submitted">
                    <div class="number"><?php echo $stats['submitted'] ?? 0; ?></div>
                    <div class="label">Submitted</div>
                </div>
                <div class="stat-box verified">
                    <div class="number"><?php echo $stats['verified'] ?? 0; ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="stat-box rejected">
                    <div class="number"><?php echo $stats['rejected'] ?? 0; ?></div>
                    <div class="label">Rejected</div>
                </div>
            </div>
        </div>

        <!-- Recent Households -->
        <div class="dashboard-card">
            <h3 style="margin-bottom: 16px;">🕐 Recent Households</h3>
            <?php if (count($recentHouseholds) > 0): ?>
                <?php foreach ($recentHouseholds as $household): ?>
                <div class="household-list-item">
                    <div>
                        <strong><?php echo htmlspecialchars($household['household_number'] ?? 'N/A'); ?></strong>
                        <span style="color: #64748b; margin-left: 8px;"><?php echo htmlspecialchars($household['household_head'] ?? ''); ?></span>
                        <br>
                        <small style="color: #64748b;">
                            <?php echo htmlspecialchars($household['community'] ?? ''); ?>
                            <?php if (isset($household['created_at'])): ?>
                                • <?php echo date('M d, Y', strtotime($household['created_at'])); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div>
                        <?php if (isset($household['status'])): ?>
                            <span class="status-badge <?php echo $household['status']; ?>">
                                <?php echo ucfirst($household['status']); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (isset($household['id'])): ?>
                            <a href="register_household.php?step=4&id=<?php echo $household['id']; ?>" 
                               style="margin-left: 8px; color: #2563eb; text-decoration: none;">View</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-data">
                    No households registered yet. 
                    <a href="register_household.php?step=1" style="color: #2563eb;">Register your first household</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>