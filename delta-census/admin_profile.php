<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

global $pdo;

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Get admin data
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active') as total_admins
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$admin = $stmt->fetch();

if (!$admin) {
    header('Location: admin_dashboard.php');
    exit();
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_profile':
            $result = updateAdminProfile($_POST, $_FILES);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            if ($result['success']) {
                // Refresh session data
                $_SESSION['full_name'] = $_POST['first_name'] . ' ' . $_POST['surname'];
                header('Location: admin_profile.php?tab=profile&message=' . urlencode($message));
                exit();
            }
            break;
            
        case 'change_password':
            $result = changeAdminPassword($_POST);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
            
        case 'create_admin':
            $result = createAdmin($_POST);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
    }
}

// Get admin count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$adminCount = $stmt->fetch()['total'];

// Get all admins
$stmt = $pdo->query("
    SELECT id, username, surname, first_name, email, phone, status, created_at, last_login 
    FROM users 
    WHERE role = 'admin' 
    ORDER BY id ASC
");
$admins = $stmt->fetchAll();

// Functions
function updateAdminProfile($data, $files) {
    global $pdo, $userId;
    
    try {
        // Update user basic info
        $stmt = $pdo->prepare("
            UPDATE users SET
                surname = ?,
                first_name = ?,
                other_name = ?,
                phone = ?,
                email = ?
            WHERE id = ? AND role = 'admin'
        ");
        
        $stmt->execute([
            $data['surname'],
            $data['first_name'],
            $data['other_name'] ?? null,
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
        
        logActivity($userId, 'update_profile', 'Updated admin profile', 'user_management');
        
        return ['success' => true, 'message' => 'Profile updated successfully'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error updating profile: ' . $e->getMessage()];
    }
}

function changeAdminPassword($data) {
    global $pdo, $userId;
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        if (strlen($data['new_password']) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters'];
        }
        
        if ($data['new_password'] !== $data['confirm_password']) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        
        $passwordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $userId]);
        
        logActivity($userId, 'change_password', 'Changed admin password', 'user_management');
        
        return ['success' => true, 'message' => 'Password changed successfully'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error changing password: ' . $e->getMessage()];
    }
}

function createAdmin($data) {
    global $pdo, $userId;
    
    try {
        // Check admin limit (max 5 admins)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
        $adminCount = $stmt->fetch()['total'];
        
        if ($adminCount >= 5) {
            return ['success' => false, 'message' => 'Maximum admin limit reached (5). Cannot create more admins.'];
        }
        
        // Validate required fields
        $required = ['surname', 'first_name', 'email', 'phone', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Field '$field' is required"];
            }
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Generate username
        $username = strtolower($data['first_name'] . '.' . $data['surname']);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $username = strtolower($data['first_name'] . '.' . $data['surname'] . rand(1, 99));
        }
        
        // Generate employee ID
        $stmt = $pdo->query("SELECT employee_id FROM users WHERE employee_id LIKE 'ADMIN%' ORDER BY id DESC LIMIT 1");
        $lastId = $stmt->fetch();
        if ($lastId) {
            $num = intval(substr($lastId['employee_id'], 5)) + 1;
        } else {
            $num = 1;
        }
        $employeeId = 'ADMIN' . str_pad($num, 3, '0', STR_PAD_LEFT);
        
        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert admin
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, employee_id, surname, first_name, other_name,
                phone, email, password_hash, role, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin', 'active', ?)
        ");
        
        $stmt->execute([
            $username,
            $employeeId,
            $data['surname'],
            $data['first_name'],
            $data['other_name'] ?? null,
            $data['phone'],
            $data['email'],
            $passwordHash,
            $userId
        ]);
        
        $newAdminId = $pdo->lastInsertId();
        
        logActivity($userId, 'create_admin', "Created new admin: {$data['first_name']} {$data['surname']}", 'user_management');
        
        return ['success' => true, 'message' => "Admin created successfully! Username: $username, Employee ID: $employeeId"];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error creating admin: ' . $e->getMessage()];
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin Profile - Delta Census</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .app-container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .mobile-nav { background: white; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .nav-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .user-menu { display: flex; align-items: center; gap: 12px; }
        .btn-sm { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; }
        .btn-primary { background: #2563eb; color: white; border: none; cursor: pointer; }
        .btn-secondary { background: #64748b; color: white; border: none; cursor: pointer; }
        .btn-danger { background: #ef4444; color: white; border: none; cursor: pointer; }
        .btn-success { background: #22c55e; color: white; border: none; cursor: pointer; }
        
        .profile-header { display: flex; align-items: center; gap: 24px; padding: 24px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; flex-wrap: wrap; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #e2e8f0; }
        .profile-avatar-placeholder { width: 100px; height: 100px; border-radius: 50%; background: #2563eb; color: white; display: flex; align-items: center; justify-content: center; font-size: 40px; font-weight: 700; border: 4px solid #e2e8f0; }
        .profile-info h2 { margin: 0; font-size: 24px; }
        .profile-info .role { color: #64748b; font-size: 14px; }
        .profile-info .admin-count { display: inline-block; padding: 4px 12px; background: #dbeafe; color: #1e40af; border-radius: 9999px; font-size: 12px; font-weight: 600; margin-top: 8px; }
        
        .tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 2px solid #e2e8f0; }
        .tab { padding: 12px 24px; border: none; background: none; cursor: pointer; font-size: 15px; font-weight: 500; color: #64748b; border-bottom: 3px solid transparent; transition: all 0.2s ease; }
        .tab:hover { color: #0f172a; }
        .tab.active { color: #2563eb; border-bottom-color: #2563eb; }
        
        .dashboard-card { background: white; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 4px; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .form-group .hint { display: block; color: #64748b; font-size: 12px; margin-top: 4px; }
        .form-group .required { color: #ef4444; }
        
        .admin-list { margin-top: 16px; }
        .admin-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .admin-item:last-child { border-bottom: none; }
        .admin-item .admin-name { font-weight: 500; }
        .admin-item .admin-role { font-size: 12px; color: #64748b; }
        .admin-item .status-badge { padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
        .status-badge.active { background: #dcfce7; color: #166534; }
        .status-badge.inactive { background: #fef3c7; color: #92400e; }
        .status-badge.suspended { background: #fee2e2; color: #991b1b; }
        .admin-item .admin-actions { display: flex; gap: 8px; }
        .admin-item .admin-actions .btn { padding: 4px 8px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-block; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-success { background: #22c55e; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        
        .text-muted { color: #64748b; font-size: 13px; }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; }
            .tabs { flex-wrap: wrap; }
            .tab { flex: 1; text-align: center; padding: 10px 12px; font-size: 13px; }
            .admin-item { flex-direction: column; align-items: stretch; gap: 8px; }
            .admin-item .admin-actions { justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2 style="font-size: 20px;">👤 Admin Profile</h2>
                <div class="user-menu">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    <a href="admin_dashboard.php" class="btn-sm btn-secondary">Dashboard</a>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>

        <!-- Profile Header -->
        <div class="profile-header">
            <?php if ($admin['passport_photo']): ?>
                <img src="<?php echo htmlspecialchars($admin['passport_photo']); ?>" alt="Profile" class="profile-avatar">
            <?php else: ?>
                <div class="profile-avatar-placeholder">
                    <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['surname']); ?></h2>
                <div class="role">Administrator • <?php echo htmlspecialchars($admin['email']); ?></div>
                <div class="admin-count">
                    👥 <?php echo $adminCount; ?>/5 Admins
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" onclick="window.location.href='admin_profile.php?tab=profile'">
                👤 Profile
            </button>
            <button class="tab <?php echo $activeTab === 'password' ? 'active' : ''; ?>" onclick="window.location.href='admin_profile.php?tab=password'">
                🔑 Change Password
            </button>
            <button class="tab <?php echo $activeTab === 'admins' ? 'active' : ''; ?>" onclick="window.location.href='admin_profile.php?tab=admins'">
                👥 Admins (<?php echo $adminCount; ?>/5)
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Tab Content -->
        <?php if ($activeTab === 'profile'): ?>
        <!-- Profile Tab -->
        <div class="dashboard-card">
            <h3 style="margin-bottom: 20px;">✏️ Edit Profile</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="surname">Surname <span class="required">*</span></label>
                        <input type="text" id="surname" name="surname" value="<?php echo htmlspecialchars($admin['surname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="other_name">Other Name</label>
                    <input type="text" id="other_name" name="other_name" value="<?php echo htmlspecialchars($admin['other_name'] ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="passport_photo">Passport Photograph</label>
                    <?php if ($admin['passport_photo']): ?>
                        <div style="margin: 8px 0;">
                            <img src="<?php echo htmlspecialchars($admin['passport_photo']); ?>" alt="Current Photo" style="max-width: 100px; border-radius: 8px;">
                            <br><small class="text-muted">Current photo</small>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="passport_photo" name="passport_photo" accept="image/*">
                    <small class="text-muted">Max size: 2MB. Allowed: JPEG, PNG, GIF, WEBP</small>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'password'): ?>
        <!-- Change Password Tab -->
        <div class="dashboard-card">
            <h3 style="margin-bottom: 20px;">🔑 Change Password</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password <span class="required">*</span></label>
                    <input type="password" id="current_password" name="current_password" required placeholder="Enter current password">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" required minlength="8" placeholder="Min 8 characters">
                    <span class="hint">Password must be at least 8 characters with a mix of letters, numbers, and symbols</span>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="btn btn-warning">Change Password</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'admins'): ?>
        <!-- Admins Management Tab -->
        <div class="dashboard-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
                <h3 style="margin: 0;">👥 Administrators (<?php echo $adminCount; ?>/5)</h3>
                <?php if ($adminCount < 5): ?>
                    <button class="btn btn-success" onclick="toggleCreateAdmin()">➕ Create Admin</button>
                <?php else: ?>
                    <span class="alert alert-warning" style="margin: 0;">⚠️ Maximum admin limit reached (5)</span>
                <?php endif; ?>
            </div>

            <?php if ($adminCount < 5): ?>
            <!-- Create Admin Form -->
            <div id="createAdminForm" style="display: none; background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin-bottom: 16px;">Create New Admin</h4>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_admin">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_surname">Surname <span class="required">*</span></label>
                            <input type="text" id="create_surname" name="surname" required>
                        </div>
                        <div class="form-group">
                            <label for="create_first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="create_first_name" name="first_name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_other_name">Other Name</label>
                        <input type="text" id="create_other_name" name="other_name">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_phone">Phone Number <span class="required">*</span></label>
                            <input type="tel" id="create_phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label for="create_email">Email Address <span class="required">*</span></label>
                            <input type="email" id="create_email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_password">Password <span class="required">*</span></label>
                        <input type="password" id="create_password" name="password" required minlength="8" placeholder="Min 8 characters">
                        <span class="hint">Password must be at least 8 characters</span>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 16px;">
                        <button type="submit" class="btn btn-success">Create Admin</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleCreateAdmin()">Cancel</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Admin List -->
            <div class="admin-list">
                <h4 style="margin-bottom: 12px;">Current Administrators</h4>
                <?php if (count($admins) > 0): ?>
                    <?php foreach ($admins as $adminUser): ?>
                        <div class="admin-item">
                            <div>
                                <span class="admin-name">
                                    <?php echo htmlspecialchars($adminUser['first_name'] . ' ' . $adminUser['surname']); ?>
                                    <?php if ($adminUser['id'] == $_SESSION['user_id']): ?>
                                        <span style="font-size: 11px; color: #2563eb; font-weight: 600;">(You)</span>
                                    <?php endif; ?>
                                </span>
                                <br>
                                <span class="admin-role"><?php echo htmlspecialchars($adminUser['email']); ?></span>
                                <span style="margin-left: 12px; font-size: 12px; color: #64748b;">
                                    <?php echo htmlspecialchars($adminUser['phone']); ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span class="status-badge <?php echo $adminUser['status']; ?>">
                                    <?php echo ucfirst($adminUser['status']); ?>
                                </span>
                                <?php if ($adminUser['id'] != $_SESSION['user_id'] && $adminUser['id'] != 1): ?>
                                    <div class="admin-actions">
                                        <button class="btn btn-secondary" onclick="alert('Edit functionality coming soon')">Edit</button>
                                        <button class="btn btn-danger" onclick="if(confirm('Warning: Are you sure you want to remove this admin?')) { alert('Remove functionality coming soon'); }">Remove</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No admins found.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleCreateAdmin() {
            const form = document.getElementById('createAdminForm');
            if (form.style.display === 'none') {
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                form.style.display = 'none';
            }
        }

        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            if (this.value && this.value !== password) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Toggle password visibility (optional)
        document.querySelectorAll('.toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                if (input.type === 'password') {
                    input.type = 'text';
                    this.textContent = '🙈';
                } else {
                    input.type = 'password';
                    this.textContent = '👁️';
                }
            });
        });
    </script>
</body>
</html>