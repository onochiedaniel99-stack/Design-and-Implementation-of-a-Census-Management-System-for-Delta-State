<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

global $pdo;

$householdId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$householdId) {
    header('Location: view_households.php');
    exit();
}

// Get household details - only if enumerator owns it
$stmt = $pdo->prepare("
    SELECT h.*, u.surname as enumerator_surname, u.first_name as enumerator_first_name
    FROM households h
    LEFT JOIN users u ON h.enumerator_id = u.id
    WHERE h.id = ? AND h.enumerator_id = ?
");
$stmt->execute([$householdId, $_SESSION['user_id']]);
$household = $stmt->fetch();

if (!$household) {
    header('Location: view_households.php');
    exit();
}

// Get household members
$stmt = $pdo->prepare("SELECT * FROM household_members WHERE household_id = ? ORDER BY is_head DESC, id ASC");
$stmt->execute([$householdId]);
$members = $stmt->fetchAll();

// Get audit trail
$stmt = $pdo->prepare("
    SELECT * FROM activity_log 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$activities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Household Details - Delta Census</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .app-container {
            max-width: 1000px;
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
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-success { background: #22c55e; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .dashboard-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-badge.draft { background: #fef3c7; color: #92400e; }
        .status-badge.submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.verified { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .detail-item {
            padding: 12px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .detail-item .label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            display: block;
            margin-bottom: 4px;
        }
        .detail-item .value {
            font-size: 15px;
            font-weight: 600;
        }
        .member-list {
            margin-top: 16px;
        }
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .member-item:last-child {
            border-bottom: none;
        }
        .member-item .head-badge {
            background: #2563eb;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        .member-item .member-name {
            font-weight: 500;
        }
        .member-item .member-details {
            font-size: 13px;
            color: #64748b;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .action-buttons .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            .nav-header {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2 style="font-size: 20px;">🏠 Household Details</h2>
                <div class="user-menu">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Enumerator'); ?></span>
                    <a href="view_households.php" class="btn-sm btn-secondary">← Back</a>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>

        <!-- Household Header -->
        <div class="dashboard-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3 style="margin: 0;"><?php echo htmlspecialchars($household['household_code'] ?? $household['household_number']); ?></h3>
                    <p style="margin: 4px 0 0; color: #64748b;">
                        <?php echo htmlspecialchars($household['community'] . ', ' . $household['ward'] . ' LGA'); ?>
                    </p>
                </div>
                <span class="status-badge <?php echo $household['status']; ?>">
                    <?php echo ucfirst($household['status']); ?>
                </span>
            </div>
        </div>

        <!-- Household Information -->
        <div class="dashboard-card">
            <h4 style="margin-bottom: 16px;">📋 Household Information</h4>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="label">Household Head</span>
                    <span class="value"><?php echo htmlspecialchars($household['head_of_household'] ?? $household['household_head']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Phone Number</span>
                    <span class="value"><?php echo htmlspecialchars($household['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">LGA</span>
                    <span class="value"><?php echo htmlspecialchars($household['lga']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Ward</span>
                    <span class="value"><?php echo htmlspecialchars($household['ward']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Community</span>
                    <span class="value"><?php echo htmlspecialchars($household['community']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Enumeration Area</span>
                    <span class="value"><?php echo htmlspecialchars($household['enumeration_area']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Address</span>
                    <span class="value"><?php echo htmlspecialchars(($household['house_number'] ?? '') . ' ' . ($household['street_name'] ?? '')); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Landmark</span>
                    <span class="value"><?php echo htmlspecialchars($household['landmark'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Building Type</span>
                    <span class="value"><?php echo htmlspecialchars($household['building_type']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">House Ownership</span>
                    <span class="value"><?php echo htmlspecialchars($household['house_ownership']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Number of Rooms</span>
                    <span class="value"><?php echo $household['number_of_rooms']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Households in Building</span>
                    <span class="value"><?php echo $household['number_of_households']; ?></span>
                </div>
            </div>
        </div>

        <!-- Members -->
        <div class="dashboard-card">
            <h4 style="margin-bottom: 16px;">👥 Household Members (<?php echo count($members); ?>)</h4>
            <?php if (count($members) > 0): ?>
                <div class="member-list">
                    <?php foreach ($members as $member): ?>
                        <div class="member-item">
                            <div>
                                <span class="member-name">
                                    <?php echo htmlspecialchars($member['surname'] . ' ' . $member['first_name']); ?>
                                    <?php if ($member['other_name']): ?>
                                        (<?php echo htmlspecialchars($member['other_name']); ?>)
                                    <?php endif; ?>
                                </span>
                                <?php if ($member['is_head']): ?>
                                    <span class="head-badge">HEAD</span>
                                <?php endif; ?>
                                <div class="member-details">
                                    <?php echo $member['relationship']; ?> • Age: <?php echo $member['age']; ?> • <?php echo $member['gender']; ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($member['occupation']): ?>
                                    <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($member['occupation']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #64748b; text-align: center;">No members added to this household yet.</p>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <?php if ($household['status'] === 'draft'): ?>
            <div class="dashboard-card">
                <h4 style="margin-bottom: 16px;">⚡ Actions</h4>
                <div class="action-buttons">
                    <a href="register_household.php?step=2&id=<?php echo $household['id']; ?>" class="btn btn-secondary">✏️ Edit Members</a>
                    <a href="register_household.php?step=4&id=<?php echo $household['id']; ?>" class="btn btn-primary">📤 Review & Submit</a>
                    <a href="register_household.php?step=1&id=<?php echo $household['id']; ?>" class="btn btn-secondary">📝 Edit Household Info</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Audit Trail -->
        <div class="dashboard-card">
            <h4 style="margin-bottom: 16px;">🕐 Recent Activity</h4>
            <?php if (count($activities) > 0): ?>
                <div style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($activities as $activity): ?>
                        <div style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px;">
                            <span style="color: #2563eb;"><?php echo ucfirst($activity['action']); ?></span>
                            <span style="color: #64748b;"><?php echo htmlspecialchars($activity['details'] ?? ''); ?></span>
                            <span style="float: right; color: #94a3b8; font-size: 12px;">
                                <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #64748b;">No activity recorded for this household.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>