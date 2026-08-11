<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

global $pdo;

// Get enumerator's location
$stmt = $pdo->prepare("SELECT ul.* FROM user_locations ul WHERE ul.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$location = $stmt->fetch();

if (!$location) {
    header('Location: no_location.php');
    exit();
}

// Handle search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build the query
$query = "SELECT h.*, 
          (SELECT COUNT(*) FROM household_members WHERE household_id = h.id) as member_count 
          FROM households h 
          WHERE h.enumerator_id = ?";
$params = [$_SESSION['user_id']];

// Add search filter
if (!empty($search)) {
    $query .= " AND (h.household_code LIKE ? OR h.head_of_household LIKE ? OR h.household_head LIKE ? OR h.community LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add status filter
if (!empty($status)) {
    $query .= " AND h.status = ?";
    $params[] = $status;
}

// Add sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY h.created_at ASC";
        break;
    case 'status':
        $query .= " ORDER BY FIELD(h.status, 'draft', 'submitted', 'verified', 'rejected')";
        break;
    case 'head':
        $query .= " ORDER BY h.head_of_household ASC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY h.created_at DESC";
        break;
}

// Get households
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$households = $stmt->fetchAll();

// Get statistics
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Households - Delta Census</title>
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
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-success {
            background: #22c55e;
            color: white;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .dashboard-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
        }
        .stat-box {
            text-align: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
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
        .stat-box.total .number { color: #64748b; }
        
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .filters input, .filters select {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            flex: 1;
            min-width: 150px;
        }
        .filters input:focus, .filters select:focus {
            outline: none;
            border-color: #2563eb;
        }
        .filters .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .household-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }
        .household-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            transition: all 0.3s ease;
        }
        .household-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .household-card .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        .household-card .code {
            font-weight: 700;
            font-size: 16px;
            color: #2563eb;
        }
        .household-card .status-badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-badge.draft { background: #fef3c7; color: #92400e; }
        .status-badge.submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.verified { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        
        .household-card .details {
            font-size: 14px;
            color: #475569;
        }
        .household-card .details p {
            margin: 4px 0;
        }
        .household-card .details strong {
            color: #0f172a;
        }
        .household-card .actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .household-card .actions .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            cursor: pointer;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        .no-results .icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .no-results .btn {
            margin-top: 16px;
        }
        
        @media (max-width: 768px) {
            .household-grid {
                grid-template-columns: 1fr;
            }
            .filters {
                flex-direction: column;
            }
            .filters input, .filters select {
                width: 100%;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
        <!-- Navigation -->
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2 style="font-size: 20px;">📋 My Households</h2>
                <div class="user-menu">
                    <span class="user-name" style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Enumerator'); ?></span>
                    <a href="enumerator_dashboard.php" class="btn-sm btn-secondary">Dashboard</a>
                    <a href="register_household.php?step=1" class="btn-sm btn-primary">+ New</a>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>

        <!-- Statistics -->
        <div class="dashboard-card">
            <h3 style="margin-bottom: 16px;">📊 Your Statistics</h3>
            <div class="stats-grid">
                <div class="stat-box total">
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

        <!-- Filters -->
        <div class="dashboard-card">
            <form method="GET" action="" class="filters">
                <input type="text" name="search" placeholder="Search by code, head, or community..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="submitted" <?php echo $status === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="verified" <?php echo $status === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <select name="sort">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Sort by Status</option>
                    <option value="head" <?php echo $sort === 'head' ? 'selected' : ''; ?>>Sort by Head</option>
                </select>
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Apply Filters</button>
                <?php if (!empty($search) || !empty($status)): ?>
                    <a href="view_households.php" class="btn btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Households List -->
        <div class="dashboard-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0;">🏠 Households (<?php echo count($households); ?>)</h3>
                <a href="register_household.php?step=1" class="btn btn-primary" style="padding: 8px 16px; text-decoration: none; border-radius: 6px; font-size: 14px;">+ Register New</a>
            </div>

            <?php if (count($households) > 0): ?>
                <div class="household-grid">
                    <?php foreach ($households as $household): ?>
                        <div class="household-card">
                            <div class="header">
                                <span class="code"><?php echo htmlspecialchars($household['household_code'] ?? $household['household_number']); ?></span>
                                <span class="status-badge <?php echo $household['status']; ?>">
                                    <?php echo ucfirst($household['status']); ?>
                                </span>
                            </div>
                            <div class="details">
                                <p><strong>Head:</strong> <?php echo htmlspecialchars($household['head_of_household'] ?? $household['household_head']); ?></p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($household['community'] . ', ' . $household['ward']); ?></p>
                                <p><strong>Members:</strong> <?php echo $household['member_count'] ?? 0; ?></p>
                                <p><strong>Created:</strong> <?php echo date('M d, Y', strtotime($household['created_at'])); ?></p>
                            </div>
                            <div class="actions">
                                <a href="view_household.php?id=<?php echo $household['id']; ?>" class="btn btn-primary">View Details</a>
                                <?php if ($household['status'] === 'draft'): ?>
                                    <a href="register_household.php?step=2&id=<?php echo $household['id']; ?>" class="btn btn-secondary">Edit</a>
                                    <a href="register_household.php?step=4&id=<?php echo $household['id']; ?>" class="btn btn-success">Review & Submit</a>
                                <?php endif; ?>
                                <?php if ($household['status'] === 'submitted'): ?>
                                    <span style="color: #2563eb; font-size: 12px;">⏳ Pending Verification</span>
                                <?php endif; ?>
                                <?php if ($household['status'] === 'verified'): ?>
                                    <span style="color: #22c55e; font-size: 12px;">✅ Verified</span>
                                <?php endif; ?>
                                <?php if ($household['status'] === 'rejected'): ?>
                                    <span style="color: #ef4444; font-size: 12px;">❌ Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <div class="icon">🏠</div>
                    <h3>No Households Found</h3>
                    <p style="color: #64748b;">
                        <?php if (!empty($search) || !empty($status)): ?>
                            No households match your search criteria.
                        <?php else: ?>
                            You haven't registered any households yet.
                        <?php endif; ?>
                    </p>
                    <a href="register_household.php?step=1" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 6px;">Register Your First Household</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>