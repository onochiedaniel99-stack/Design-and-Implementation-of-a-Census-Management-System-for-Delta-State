<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

global $pdo;

// Handle actions
$message = '';
$messageType = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$enumeratorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_enumerator':
                $result = updateEnumerator($_POST, $_FILES);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    // ADD AUDIT LOG HERE - After successful update
                    logActivity(
                        $_SESSION['user_id'], 
                        'update', 
                        'Updated enumerator: ' . $_POST['first_name'] . ' ' . $_POST['surname'], 
                        'user_management', 
                        ['user_id' => $_POST['user_id']]
                    );
                    header('Location: admin_enumerators.php?action=list&message=' . urlencode($message));
                    exit();
                }
                break;
                
            case 'reset_password':
                $result = resetEnumeratorPassword($_POST['user_id'], $_POST['new_password']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    // ADD AUDIT LOG HERE - After successful password reset
                    logActivity(
                        $_SESSION['user_id'], 
                        'reset_password', 
                        'Reset password for enumerator ID: ' . $_POST['user_id'], 
                        'user_management'
                    );
                }
                break;
                
            case 'toggle_status':
                $result = toggleEnumeratorStatus($_POST['user_id'], $_POST['status']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    // ADD AUDIT LOG HERE - After status change
                    logActivity(
                        $_SESSION['user_id'], 
                        'toggle_status', 
                        'Changed status to ' . $_POST['status'] . ' for enumerator ID: ' . $_POST['user_id'], 
                        'user_management'
                    );
                }
                break;
                
            case 'delete_enumerator':
                $result = deleteEnumerator($_POST['user_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
        }
    }
}

// Get enumerator data for editing
$enumerator = null;
if ($action === 'edit' && $enumeratorId > 0) {
    $stmt = $pdo->prepare("
        SELECT u.*, ul.lga, ul.ward, ul.community, ul.enumeration_area, ul.id as location_id
        FROM users u
        LEFT JOIN user_locations ul ON u.id = ul.user_id
        WHERE u.id = ? AND u.role = 'enumerator'
    ");
    $stmt->execute([$enumeratorId]);
    $enumerator = $stmt->fetch();
    
    if (!$enumerator) {
        header('Location: admin_enumerators.php?action=list');
        exit();
    }
}

// Get enumerators list with filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$lgaFilter = isset($_GET['lga']) ? trim($_GET['lga']) : '';
$wardFilter = isset($_GET['ward']) ? trim($_GET['ward']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

$query = "
    SELECT u.*, ul.lga, ul.ward, ul.community, ul.enumeration_area,
           (SELECT COUNT(*) FROM households WHERE enumerator_id = u.id) as household_count
    FROM users u
    LEFT JOIN user_locations ul ON u.id = ul.user_id
    WHERE u.role = 'enumerator'
";
$params = [];

// Apply filters
if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.surname LIKE ? OR u.first_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($lgaFilter)) {
    $query .= " AND ul.lga = ?";
    $params[] = $lgaFilter;
}

if (!empty($wardFilter)) {
    $query .= " AND ul.ward = ?";
    $params[] = $wardFilter;
}

if (!empty($statusFilter)) {
    $query .= " AND u.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$enumerators = $stmt->fetchAll();

// Get unique LGAs and Wards for filters
$lgas = $pdo->query("SELECT DISTINCT lga FROM user_locations ORDER BY lga")->fetchAll();
$wards = $pdo->query("SELECT DISTINCT ward FROM user_locations ORDER BY ward")->fetchAll();

// ============================================
// FUNCTIONS
// ============================================

function updateEnumerator($data, $files) {
    global $pdo;
    
    try {
        $userId = (int)$data['user_id'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update user basic info
        $stmt = $pdo->prepare("
            UPDATE users SET
                surname = ?,
                first_name = ?,
                other_name = ?,
                gender = ?,
                date_of_birth = ?,
                phone = ?,
                email = ?
            WHERE id = ? AND role = 'enumerator'
        ");
        
        $stmt->execute([
            $data['surname'],
            $data['first_name'],
            $data['other_name'] ?? null,
            $data['gender'],
            $data['date_of_birth'],
            $data['phone'],
            $data['email'],
            $userId
        ]);
        
        // Handle passport photo upload
        if (isset($files['passport_photo']) && $files['passport_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handlePhotoUpload($files['passport_photo'], $userId);
            if ($uploadResult['success']) {
                $stmt = $pdo->prepare("UPDATE users SET passport_photo = ? WHERE id = ?");
                $stmt->execute([$uploadResult['path'], $userId]);
            }
        }
        
        // Update location
        $stmt = $pdo->prepare("
            UPDATE user_locations SET
                lga = ?,
                ward = ?,
                community = ?,
                enumeration_area = ?
            WHERE user_id = ?
        ");
        
        $stmt->execute([
            $data['lga'],
            $data['ward'],
            $data['community'] ?? null,
            $data['enumeration_area'] ?? null,
            $userId
        ]);
        
        // REMOVE THIS - We're already logging in the main POST handler
        // logActivity($_SESSION['user_id'], 'update_enumerator', 
        //            "Updated enumerator: {$data['first_name']} {$data['surname']} (ID: $userId)");
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Enumerator updated successfully'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error updating: ' . $e->getMessage()];
    }
}

function resetEnumeratorPassword($userId, $newPassword) {
    global $pdo;
    
    try {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'enumerator'");
        $stmt->execute([$passwordHash, $userId]);
        
        // REMOVE THIS - We're already logging in the main POST handler
        // logActivity($_SESSION['user_id'], 'reset_password', 
        //            "Reset password for enumerator ID: $userId");
        
        return ['success' => true, 'message' => 'Password reset successfully'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error resetting password: ' . $e->getMessage()];
    }
}

function toggleEnumeratorStatus($userId, $status) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'enumerator'");
        $stmt->execute([$status, $userId]);
        
        // REMOVE THIS - We're already logging in the main POST handler
        // logActivity($_SESSION['user_id'], 'toggle_status', 
        //            "Changed status to $status for enumerator ID: $userId");
        
        return ['success' => true, 'message' => 'Status updated successfully'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}

function deleteEnumerator($userId) {
    global $pdo;
    
    try {
        // Check if enumerator has households
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM households WHERE enumerator_id = ?");
        $stmt->execute([$userId]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            return ['success' => false, 'message' => "Cannot delete enumerator with $count registered households. Please reassign or delete households first."];
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'enumerator'");
        $stmt->execute([$userId]);
        
        // Log activity inside the function since we're not in the main POST handler
        logActivity($_SESSION['user_id'], 'delete', 
                   "Deleted enumerator ID: $userId", 
                   'user_management');
        
        return ['success' => true, 'message' => 'Enumerator deleted successfully'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error deleting: ' . $e->getMessage()];
    }
}

function handlePhotoUpload($file, $userId) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error'];
    }
    
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large (max 2MB)'];
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $uploadDir = __DIR__ . '/uploads/passports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'passport_' . $userId . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Failed to save file'];
    }
    
    return ['success' => true, 'path' => 'uploads/passports/' . $filename];
}

// ============================================
// END FUNCTIONS
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Manage Enumerators - Delta Census</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Your CSS styles here */
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
        .dashboard-card { background: white; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
        .filters { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .filters input, .filters select { padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; flex: 1; min-width: 150px; }
        .table-responsive { overflow-x: auto; }
        .user-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .user-table th { background: #f1f5f9; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        .user-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .user-table tr:hover { background: #f8fafc; }
        .avatar-sm { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; background: #2563eb; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; }
        .badge { padding: 4px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fef3c7; color: #92400e; }
        .badge-suspended { background: #fee2e2; color: #991b1b; }
        .action-buttons { display: flex; gap: 4px; flex-wrap: wrap; }
        .action-buttons .btn { padding: 4px 8px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 8px; padding: 32px; max-width: 600px; width: 95%; margin: 20px auto; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
        .modal-header h3 { margin: 0; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 4px; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #2563eb; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .text-muted { color: #64748b; font-size: 13px; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .filters { flex-direction: column; }
            .filters input, .filters select { width: 100%; }
            .user-table { font-size: 12px; }
            .user-table th, .user-table td { padding: 8px 4px; }
            .modal-content { padding: 16px; margin: 10px; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Navigation -->
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2 style="font-size: 20px;">👥 Manage Enumerators</h2>
                <div class="user-menu">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    <a href="admin_dashboard.php" class="btn-sm btn-secondary">Dashboard</a>
                    <a href="admin_users.php" class="btn-sm btn-primary">+ Create</a>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="dashboard-card">
            <form method="GET" action="" class="filters">
                <input type="hidden" name="action" value="list">
                <input type="text" name="search" placeholder="Search by name, email, ID..." value="<?php echo htmlspecialchars($search); ?>">
                
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
                
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
                
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer;">Apply Filters</button>
                
                <?php if (!empty($search) || !empty($lgaFilter) || !empty($wardFilter) || !empty($statusFilter)): ?>
                    <a href="admin_enumerators.php?action=list" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 6px; text-decoration: none;">Clear Filters</a>
                <?php endif; ?>
            </form>
            
            <div class="text-muted">
                Showing <?php echo count($enumerators); ?> enumerators
            </div>
        </div>

        <!-- Enumerators List -->
        <div class="dashboard-card">
            <div class="table-responsive">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Employee ID</th>
                            <th>Contact</th>
                            <th>Assigned Location</th>
                            <th>Households</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($enumerators) > 0): ?>
                            <?php foreach ($enumerators as $enumerator): ?>
                                <tr>
                                    <td>
                                        <?php if ($enumerator['passport_photo']): ?>
                                            <img src="<?php echo htmlspecialchars($enumerator['passport_photo']); ?>" alt="Photo" class="avatar-sm">
                                        <?php else: ?>
                                            <div class="avatar-placeholder">
                                                <?php echo strtoupper(substr($enumerator['first_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($enumerator['surname'] . ', ' . $enumerator['first_name']); ?></strong>
                                        <?php if ($enumerator['other_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($enumerator['other_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($enumerator['employee_id']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($enumerator['email']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($enumerator['phone']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($enumerator['lga']): ?>
                                            <strong><?php echo htmlspecialchars($enumerator['lga']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($enumerator['ward']); ?></small>
                                            <?php if ($enumerator['community']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($enumerator['community']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: #2563eb;">
                                            <?php echo $enumerator['household_count'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $enumerator['status']; ?>">
                                            <?php echo ucfirst($enumerator['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                                Actions ▼
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="admin_enumerators.php?action=edit&id=<?php echo $enumerator['id']; ?>" class="dropdown-item">
                                                    <span class="icon">✏️</span> Edit Details
                                                </a>
                                                <a href="#" onclick="openResetModal(<?php echo $enumerator['id']; ?>, '<?php echo htmlspecialchars($enumerator['first_name'] . ' ' . $enumerator['surname']); ?>'); return false;" class="dropdown-item">
                                                    <span class="icon">🔑</span> Reset Password
                                                </a>
                                                <a href="#" onclick="toggleStatus(<?php echo $enumerator['id']; ?>, '<?php echo $enumerator['status']; ?>'); return false;" class="dropdown-item">
                                                    <span class="icon"><?php echo $enumerator['status'] === 'active' ? '⛔' : '✅'; ?></span>
                                                    <?php echo $enumerator['status'] === 'active' ? 'Suspend Account' : 'Activate Account'; ?>
                                                </a>
                                                <?php if ($enumerator['status'] === 'suspended'): ?>
                                                    <a href="#" onclick="toggleStatus(<?php echo $enumerator['id']; ?>, 'active'); return false;" class="dropdown-item">
                                                        <span class="icon">✅</span> Reactivate Account
                                                    </a>
                                                <?php endif; ?>
                                                <div class="dropdown-divider"></div>
                                                <a href="#" onclick="deleteEnumerator(<?php echo $enumerator['id']; ?>); return false;" class="dropdown-item danger">
                                                    <span class="icon">🗑️</span> Delete Permanently
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    <div style="font-size: 48px; margin-bottom: 16px;">👤</div>
                                    <h3>No Enumerators Found</h3>
                                    <p class="text-muted">
                                        <?php if (!empty($search) || !empty($lgaFilter) || !empty($wardFilter) || !empty($statusFilter)): ?>
                                            No enumerators match your search criteria.
                                        <?php else: ?>
                                            Start by creating your first enumerator.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (empty($search) && empty($lgaFilter) && empty($wardFilter) && empty($statusFilter)): ?>
                                        <a href="admin_users.php" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 6px; margin-top: 12px;">+ Create Enumerator</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Enumerator Modal -->
    <?php if ($action === 'edit' && $enumerator): ?>
    <div class="modal active" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit Enumerator</h3>
                <button class="modal-close" onclick="window.location.href='admin_enumerators.php?action=list'">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_enumerator">
                <input type="hidden" name="user_id" value="<?php echo $enumerator['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($enumerator['employee_id']); ?>" disabled style="background: #f1f5f9;">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($enumerator['username']); ?>" disabled style="background: #f1f5f9;">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="surname">Surname <span class="required" style="color: red;">*</span></label>
                        <input type="text" id="surname" name="surname" value="<?php echo htmlspecialchars($enumerator['surname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required" style="color: red;">*</span></label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($enumerator['first_name']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="other_name">Other Name</label>
                        <input type="text" id="other_name" name="other_name" value="<?php echo htmlspecialchars($enumerator['other_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender <span class="required" style="color: red;">*</span></label>
                        <select id="gender" name="gender" required>
                            <option value="Male" <?php echo $enumerator['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $enumerator['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth <span class="required" style="color: red;">*</span></label>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $enumerator['date_of_birth']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required" style="color: red;">*</span></label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($enumerator['phone']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address <span class="required" style="color: red;">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($enumerator['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="passport_photo">Passport Photograph</label>
                    <?php if ($enumerator['passport_photo']): ?>
                        <div style="margin: 8px 0;">
                            <img src="<?php echo htmlspecialchars($enumerator['passport_photo']); ?>" alt="Current Photo" style="max-width: 100px; border-radius: 8px;">
                            <br><small class="text-muted">Current photo</small>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="passport_photo" name="passport_photo" accept="image/*">
                    <small class="text-muted">Max size: 2MB. Allowed: JPEG, PNG, GIF, WEBP</small>
                </div>
                
                <hr style="margin: 16px 0;">
                <h4>📍 Assignment Information</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_lga">LGA <span class="required" style="color: red;">*</span></label>
                        <select id="edit_lga" name="lga" required>
                            <option value="">Select LGA</option>
                            <?php 
                            $allLgas = $pdo->query("SELECT DISTINCT lga FROM user_locations ORDER BY lga")->fetchAll();
                            foreach ($allLgas as $lga): 
                            ?>
                                <option value="<?php echo htmlspecialchars($lga['lga']); ?>" <?php echo $enumerator['lga'] === $lga['lga'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lga['lga']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_ward">Ward <span class="required" style="color: red;">*</span></label>
                        <select id="edit_ward" name="ward" required>
                            <option value="">Select Ward</option>
                            <?php 
                            $allWards = $pdo->query("SELECT DISTINCT ward FROM user_locations ORDER BY ward")->fetchAll();
                            foreach ($allWards as $ward): 
                            ?>
                                <option value="<?php echo htmlspecialchars($ward['ward']); ?>" <?php echo $enumerator['ward'] === $ward['ward'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ward['ward']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_community">Community</label>
                        <input type="text" id="edit_community" name="community" value="<?php echo htmlspecialchars($enumerator['community'] ?? ''); ?>" placeholder="e.g., Community A">
                    </div>
                    <div class="form-group">
                        <label for="edit_enumeration_area">Enumeration Area</label>
                        <input type="text" id="edit_enumeration_area" name="enumeration_area" value="<?php echo htmlspecialchars($enumerator['enumeration_area'] ?? ''); ?>" placeholder="e.g., EA-012">
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer;">Update Enumerator</button>
                    <a href="admin_enumerators.php?action=list" class="btn btn-secondary" style="padding: 10px 24px; border-radius: 6px; text-decoration: none;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reset Password Modal -->
    <div class="modal" id="resetModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>🔑 Reset Password</h3>
                <button class="modal-close" onclick="closeResetModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="form-group">
                    <label>Enumerator</label>
                    <p id="reset_user_name" style="font-weight: 600; color: #2563eb;"></p>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password <span class="required" style="color: red;">*</span></label>
                    <input type="password" id="new_password" name="new_password" required minlength="8" placeholder="Min 8 characters">
                    <small class="text-muted">Password must be at least 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required" style="color: red;">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="btn btn-warning" style="padding: 10px 24px; border: none; border-radius: 6px; color: white; cursor: pointer;">Reset Password</button>
                    <button type="button" class="btn btn-secondary" onclick="closeResetModal()" style="padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Reset Password Modal
        function openResetModal(userId, userName) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_user_name').textContent = userName;
            document.getElementById('resetModal').classList.add('active');
        }
        
        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('active');
        }
        
        // Confirm password match
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            if (this.value && this.value !== password) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Toggle Status
        function toggleStatus(userId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
            const confirmMsg = currentStatus === 'active' 
                ? 'Are you sure you want to suspend this enumerator?' 
                : 'Are you sure you want to activate this enumerator?';
            
            if (confirm(confirmMsg)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Delete Enumerator
        function deleteEnumerator(userId) {
            if (confirm('Are you sure you want to delete this enumerator? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_enumerator">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal on outside click
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('resetModal');
            if (event.target === modal) {
                closeResetModal();
            }
        });
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
    </script>
</body>
</html>