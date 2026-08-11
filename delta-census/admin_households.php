<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

global $pdo;

// Handle actions
$message = '';
$messageType = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$householdId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_household':
                $result = deleteHousehold($_POST['household_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
            case 'update_status':
                $result = updateHouseholdStatus($_POST['household_id'], $_POST['status']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
            case 'verify_household':
                // ADD AUDIT LOG HERE - After verification
                $result = verifyHousehold($_POST['household_id'], $_POST['notes'] ?? '');
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    // Get household code for the log
                    $stmt = $pdo->prepare("SELECT household_code FROM households WHERE id = ?");
                    $stmt->execute([$_POST['household_id']]);
                    $household = $stmt->fetch();
                    $householdCode = $household['household_code'] ?? 'Unknown';
                    
                    logActivity(
                        $_SESSION['user_id'], 
                        'verify', 
                        'Verified household: ' . $householdCode, 
                        'verification', 
                        ['household_id' => $_POST['household_id'], 'notes' => $_POST['notes'] ?? '']
                    );
                }
                break;
            case 'reject_household':
                // ADD AUDIT LOG HERE - After rejection
                $result = rejectHousehold($_POST['household_id'], $_POST['rejection_reason'] ?? '');
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    // Get household code for the log
                    $stmt = $pdo->prepare("SELECT household_code FROM households WHERE id = ?");
                    $stmt->execute([$_POST['household_id']]);
                    $household = $stmt->fetch();
                    $householdCode = $household['household_code'] ?? 'Unknown';
                    $reason = $_POST['rejection_reason'] ?? 'No reason provided';
                    
                    logActivity(
                        $_SESSION['user_id'], 
                        'reject', 
                        'Rejected household: ' . $householdCode . ' - Reason: ' . $reason, 
                        'verification', 
                        ['household_id' => $_POST['household_id'], 'reason' => $reason]
                    );
                }
                break;
        }
    }
}

// Get household details for viewing
$household = null;
$members = [];
if ($action === 'view' && $householdId > 0) {
    $stmt = $pdo->prepare("
        SELECT h.*, u.username as enumerator_name, u.surname as enum_surname, u.first_name as enum_first_name
        FROM households h
        LEFT JOIN users u ON h.enumerator_id = u.id
        WHERE h.id = ?
    ");
    $stmt->execute([$householdId]);
    $household = $stmt->fetch();
    
    if ($household) {
        $stmt = $pdo->prepare("SELECT * FROM household_members WHERE household_id = ? ORDER BY is_head DESC, id ASC");
        $stmt->execute([$householdId]);
        $members = $stmt->fetchAll();
    }
}

// Get households list with filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$lgaFilter = isset($_GET['lga']) ? trim($_GET['lga']) : '';
$wardFilter = isset($_GET['ward']) ? trim($_GET['ward']) : '';
$enumeratorFilter = isset($_GET['enumerator']) ? (int)$_GET['enumerator'] : 0;

$query = "
    SELECT h.*, u.username as enumerator_name,
           (SELECT COUNT(*) FROM household_members WHERE household_id = h.id) as member_count
    FROM households h
    LEFT JOIN users u ON h.enumerator_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (h.household_code LIKE ? OR h.household_head LIKE ? OR h.head_of_household LIKE ? OR h.community LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($statusFilter)) {
    $query .= " AND h.status = ?";
    $params[] = $statusFilter;
}

if (!empty($lgaFilter)) {
    $query .= " AND h.lga = ?";
    $params[] = $lgaFilter;
}

if (!empty($wardFilter)) {
    $query .= " AND h.ward = ?";
    $params[] = $wardFilter;
}

if ($enumeratorFilter > 0) {
    $query .= " AND h.enumerator_id = ?";
    $params[] = $enumeratorFilter;
}

$query .= " ORDER BY h.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$households = $stmt->fetchAll();

// Get filter options
$lgas = $pdo->query("SELECT DISTINCT lga FROM households ORDER BY lga")->fetchAll();
$wards = $pdo->query("SELECT DISTINCT ward FROM households ORDER BY ward")->fetchAll();
$enumerators = $pdo->query("SELECT id, username, surname, first_name FROM users WHERE role = 'enumerator' ORDER BY surname")->fetchAll();

// ============================================
// FUNCTIONS
// ============================================

function deleteHousehold($householdId) {
    global $pdo;
    try {
        // Get household code before deleting
        $stmt = $pdo->prepare("SELECT household_code FROM households WHERE id = ?");
        $stmt->execute([$householdId]);
        $household = $stmt->fetch();
        $householdCode = $household['household_code'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("DELETE FROM households WHERE id = ?");
        $stmt->execute([$householdId]);
        
        // Log the deletion
        logActivity(
            $_SESSION['user_id'], 
            'delete', 
            'Deleted household: ' . $householdCode . ' (ID: ' . $householdId . ')', 
            'household',
            ['household_id' => $householdId, 'household_code' => $householdCode]
        );
        
        return ['success' => true, 'message' => 'Household deleted successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error deleting: ' . $e->getMessage()];
    }
}

function updateHouseholdStatus($householdId, $status) {
    global $pdo;
    try {
        // Get household code before updating
        $stmt = $pdo->prepare("SELECT household_code FROM households WHERE id = ?");
        $stmt->execute([$householdId]);
        $household = $stmt->fetch();
        $householdCode = $household['household_code'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("UPDATE households SET status = ? WHERE id = ?");
        $stmt->execute([$status, $householdId]);
        
        // Log the status change
        logActivity(
            $_SESSION['user_id'], 
            'update_status', 
            'Changed status of household ' . $householdCode . ' to ' . ucfirst($status), 
            'household',
            ['household_id' => $householdId, 'new_status' => $status]
        );
        
        return ['success' => true, 'message' => 'Status updated successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}

function verifyHousehold($householdId, $notes) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE households 
            SET status = 'verified', verified_at = NOW(), updated_at = NOW()
            WHERE id = ? AND status = 'submitted'
        ");
        $stmt->execute([$householdId]);
        return ['success' => true, 'message' => 'Household verified successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error verifying: ' . $e->getMessage()];
    }
}

function rejectHousehold($householdId, $reason) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE households 
            SET status = 'rejected', updated_at = NOW()
            WHERE id = ? AND status = 'submitted'
        ");
        $stmt->execute([$householdId]);
        return ['success' => true, 'message' => 'Household rejected'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error rejecting: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Household Management - Delta Census</title>
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
        .btn-info { background: #06b6d4; color: white; border: none; cursor: pointer; }
        
        .admin-nav { background: white; border-radius: 8px; padding: 12px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .admin-nav .btn { padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s ease; }
        .admin-nav .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        .filters { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .filters input, .filters select { padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; flex: 1; min-width: 150px; }
        .filters input:focus, .filters select:focus { outline: none; border-color: #2563eb; }
        
        .table-responsive { overflow-x: auto; }
        .household-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .household-table th { background: #f1f5f9; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; position: sticky; top: 0; }
        .household-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .household-table tr:hover { background: #f8fafc; }
        
        .status-badge { padding: 4px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .status-badge.draft { background: #fef3c7; color: #92400e; }
        .status-badge.submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.verified { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        
        .action-buttons { display: flex; gap: 4px; flex-wrap: wrap; }
        .action-buttons .btn { padding: 4px 8px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 8px; padding: 32px; max-width: 800px; width: 95%; margin: 20px auto; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
        .modal-header h3 { margin: 0; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
        
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .detail-item { padding: 12px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; }
        .detail-item .label { font-size: 12px; color: #64748b; font-weight: 500; display: block; margin-bottom: 4px; }
        .detail-item .value { font-size: 15px; font-weight: 600; }
        
        .member-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #e2e8f0; }
        .member-item:last-child { border-bottom: none; }
        .member-item .head-badge { background: #2563eb; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-left: 8px; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .text-muted { color: #64748b; font-size: 13px; }
        .text-center { text-align: center; }
        
        @media (max-width: 768px) {
            .detail-grid { grid-template-columns: 1fr; }
            .filters { flex-direction: column; }
            .filters input, .filters select { width: 100%; }
            .household-table { font-size: 12px; }
            .household-table th, .household-table td { padding: 8px 4px; }
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
                <h2 style="font-size: 20px;">🏠 Household Management</h2>
                <div class="user-menu">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    <a href="admin_dashboard.php" class="btn-sm btn-secondary">Dashboard</a>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>

        <!-- Admin Navigation -->
        <div class="admin-nav">
            <span class="nav-label">📋 Menu:</span>
            <a href="admin_dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="admin_enumerators.php" class="btn btn-secondary">👥 Enumerators</a>
            <a href="admin_households.php" class="btn btn-primary">🏠 Households</a>
            <a href="admin_verifications.php" class="btn btn-warning">✅ Verifications</a>
            <a href="admin_reports.php" class="btn btn-secondary">📊 Reports</a>
            <a href="admin_export.php" class="btn btn-success">📤 Export</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 12px; width: 100%;">
                <input type="hidden" name="action" value="list">
                <input type="text" name="search" placeholder="Search by code, head, community..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <select name="lga">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo htmlspecialchars($lga['lga']); ?>" <?php echo $lgaFilter === $lga['lga'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lga['lga']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="ward">
                    <option value="">All Wards</option>
                    <?php foreach ($wards as $ward): ?>
                        <option value="<?php echo htmlspecialchars($ward['ward']); ?>" <?php echo $wardFilter === $ward['ward'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ward['ward']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="enumerator">
                    <option value="0">All Enumerators</option>
                    <?php foreach ($enumerators as $enum): ?>
                        <option value="<?php echo $enum['id']; ?>" <?php echo $enumeratorFilter === $enum['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($enum['surname'] . ', ' . $enum['first_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer;">Apply Filters</button>
                <a href="admin_households.php?action=list" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 6px; text-decoration: none;">Clear</a>
            </form>
        </div>

        <!-- Households Table -->
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0;">📋 Households (<?php echo count($households); ?>)</h3>
            </div>
            
            <div class="table-responsive">
                <table class="household-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Head</th>
                            <th>Location</th>
                            <th>Members</th>
                            <th>Enumerator</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($households) > 0): ?>
                            <?php foreach ($households as $household): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($household['household_code'] ?? $household['household_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($household['head_of_household'] ?? $household['household_head']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($household['community']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($household['ward']); ?></small>
                                    </td>
                                    <td><?php echo $household['member_count'] ?? 0; ?></td>
                                    <td><?php echo htmlspecialchars($household['enumerator_name'] ?? 'Unknown'); ?></td>
                                    <td><span class="status-badge <?php echo $household['status']; ?>"><?php echo ucfirst($household['status']); ?></span></td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                                Actions ▼
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="admin_households.php?action=view&id=<?php echo $household['id']; ?>" class="dropdown-item">
                                                    <span class="icon">👁️</span> View Details
                                                </a>
                                                <a href="#" onclick="changeStatus(<?php echo $household['id']; ?>); return false;" class="dropdown-item">
                                                    <span class="icon">📝</span> Change Status
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a href="#" onclick="deleteHousehold(<?php echo $household['id']; ?>); return false;" class="dropdown-item danger">
                                                    <span class="icon">🗑️</span> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center" style="padding: 40px;">
                                    <div style="font-size: 48px; margin-bottom: 16px;">🏠</div>
                                    <h3>No Households Found</h3>
                                    <p class="text-muted">No households match your search criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Household Modal -->
    <?php if ($action === 'view' && $household): ?>
    <div class="modal active" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📋 Household Details</h3>
                <button class="modal-close" onclick="window.location.href='admin_households.php?action=list'">&times;</button>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h4 style="margin: 0;"><?php echo htmlspecialchars($household['household_code'] ?? $household['household_number'] ?? 'N/A'); ?></h4>
                <span class="status-badge <?php echo $household['status'] ?? 'draft'; ?>"><?php echo ucfirst($household['status'] ?? 'Draft'); ?></span>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="label">Household Head</span>
                    <span class="value"><?php echo htmlspecialchars($household['head_of_household'] ?? $household['household_head'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Phone</span>
                    <span class="value"><?php echo htmlspecialchars($household['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">LGA</span>
                    <span class="value"><?php echo htmlspecialchars($household['lga'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Ward</span>
                    <span class="value"><?php echo htmlspecialchars($household['ward'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Community</span>
                    <span class="value"><?php echo htmlspecialchars($household['community'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Enumeration Area</span>
                    <span class="value"><?php echo htmlspecialchars($household['enumeration_area'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Address</span>
                    <span class="value"><?php echo htmlspecialchars($household['address'] ?? ($household['house_number'] ?? '') . ' ' . ($household['street_name'] ?? '')); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Enumerator</span>
                    <span class="value"><?php 
                        $enumeratorName = 'Unknown';
                        if (isset($household['enum_surname']) && isset($household['enum_first_name'])) {
                            $enumeratorName = $household['enum_surname'] . ' ' . $household['enum_first_name'];
                        } elseif (isset($household['enumerator_name'])) {
                            $enumeratorName = $household['enumerator_name'];
                        }
                        echo htmlspecialchars($enumeratorName); 
                    ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Created</span>
                    <span class="value"><?php echo isset($household['created_at']) ? date('M d, Y H:i', strtotime($household['created_at'])) : 'N/A'; ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Building Type</span>
                    <span class="value"><?php echo htmlspecialchars($household['building_type'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">House Ownership</span>
                    <span class="value"><?php echo htmlspecialchars($household['house_ownership'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Rooms</span>
                    <span class="value"><?php echo $household['number_of_rooms'] ?? 'N/A'; ?></span>
                </div>
            </div>
            
            <h4 style="margin-top: 20px;">👥 Household Members (<?php echo count($members); ?>)</h4>
            <?php if (count($members) > 0): ?>
                <?php foreach ($members as $member): ?>
                    <div class="member-item">
                        <div>
                            <strong><?php echo htmlspecialchars($member['surname'] . ' ' . $member['first_name']); ?></strong>
                            <?php if (isset($member['is_head']) && $member['is_head']): ?>
                                <span class="head-badge">HEAD</span>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted"><?php echo $member['relationship'] ?? 'Unknown'; ?> • Age: <?php echo $member['age'] ?? 'N/A'; ?> • <?php echo $member['gender'] ?? 'N/A'; ?></small>
                        </div>
                        <div>
                            <?php if (isset($member['occupation']) && $member['occupation']): ?>
                                <span class="text-muted"><?php echo htmlspecialchars($member['occupation']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No members recorded.</p>
            <?php endif; ?>
            
            <div style="margin-top: 20px; display: flex; gap: 12px;">
                <a href="admin_households.php?action=list" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 4px; text-decoration: none;">Close</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Status Change Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>📝 Change Status</h3>
                <button class="modal-close" onclick="closeStatusModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="household_id" id="status_household_id">
                <div class="form-group">
                    <label>New Status</label>
                    <select name="status" id="status_select" style="width: 100%; padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px;">
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Update</button>
                    <button type="button" class="btn btn-secondary" onclick="closeStatusModal()" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Dropdown toggle function
    function toggleDropdown(button) {
        // Close all other dropdowns
        document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
            if (menu !== button.nextElementSibling) {
                menu.classList.remove('show');
            }
        });
        
        // Toggle this dropdown
        const menu = button.nextElementSibling;
        menu.classList.toggle('show');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.action-dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
            });
        }
    });

    // Close dropdown on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
            });
        }
    });

    // Change Status function
    function changeStatus(householdId) {
        document.getElementById('status_household_id').value = householdId;
        document.getElementById('statusModal').classList.add('active');
        // Close the dropdown
        document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
            menu.classList.remove('show');
        });
    }

    // Delete Household function
    function deleteHousehold(householdId) {
        if (confirm('⚠️ Are you sure you want to delete this household?\n\nThis action cannot be undone and will remove all household data including members.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_household">
                <input type="hidden" name="household_id" value="${householdId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        // Close the dropdown
        document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
            menu.classList.remove('show');
        });
    }

    // Close modal on outside click
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('statusModal');
        if (event.target === modal) {
            closeStatusModal();
        }
    });
    </script>
</body>
</html>