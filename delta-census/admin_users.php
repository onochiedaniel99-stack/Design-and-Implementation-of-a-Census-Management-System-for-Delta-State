<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

global $pdo;

// Handle user creation
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_user') {
        $result = createEnumerator($_POST, $_FILES);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif ($_POST['action'] === 'update_status') {
        $result = updateUserStatus($_POST['user_id'], $_POST['status']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// Get all enumerators with location info
$stmt = $pdo->query("
    SELECT u.*, 
           ul.lga, ul.ward, ul.community, ul.enumeration_area,
           creator.username as created_by_name,
           u.employee_id
    FROM users u
    LEFT JOIN user_locations ul ON u.id = ul.user_id
    LEFT JOIN users creator ON u.created_by = creator.id
    WHERE u.role = 'enumerator'
    ORDER BY u.created_at DESC
");
$enumerators = $stmt->fetchAll();

function generateEmployeeId($pdo) {
    // Get the last employee ID
    $stmt = $pdo->query("SELECT employee_id FROM users WHERE employee_id LIKE 'DEL%' ORDER BY id DESC LIMIT 1");
    $lastId = $stmt->fetch();
    
    if ($lastId) {
        // Extract the number from DEL-XXXXX
        $num = intval(substr($lastId['employee_id'], 4));
        $newNum = $num + 1;
    } else {
        $newNum = 1;
    }
    
    // Format: DEL-00001
    return 'DEL-' . str_pad($newNum, 5, '0', STR_PAD_LEFT);
}

function handleFileUpload($file, $userId) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
    }
    
    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size exceeds 2MB limit'];
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WEBP are allowed'];
    }
    
    // Create uploads directory if it doesn't exist - using absolute path
    $uploadDir = __DIR__ . '/uploads/passports/';
    
    // Debug: Check if directory exists or create it
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory: ' . $uploadDir];
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        return ['success' => false, 'message' => 'Upload directory is not writable: ' . $uploadDir];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'passport_' . $userId . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file to: ' . $filePath];
    }
    
    // Return the relative path for database storage
    $relativePath = 'uploads/passports/' . $filename;
    
    return ['success' => true, 'path' => $relativePath];
}

function createEnumerator($data, $files) {
    global $pdo;
    
    try {
        // Validate required fields
        $required = ['surname', 'first_name', 'gender', 
                    'date_of_birth', 'phone', 'email', 'password', 'lga', 'ward'];
        
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
        
        // Generate employee ID
        $employeeId = generateEmployeeId($pdo);
        
        // Generate username
        $username = strtolower($data['first_name'] . '.' . $data['surname']);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $username = strtolower($data['first_name'] . '.' . $data['surname'] . rand(1, 99));
        }
        
        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, employee_id, surname, first_name, other_name,
                gender, date_of_birth, phone, email, 
                password_hash, role, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'enumerator', 'active', ?)
        ");
        
        $stmt->execute([
            $username,
            $employeeId,
            $data['surname'],
            $data['first_name'],
            $data['other_name'] ?? null,
            $data['gender'],
            $data['date_of_birth'],
            $data['phone'],
            $data['email'],
            $passwordHash,
            $_SESSION['user_id']
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Handle passport photo upload
        $passportPath = null;
        if (isset($files['passport_photo']) && $files['passport_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleFileUpload($files['passport_photo'], $userId);
            if ($uploadResult['success']) {
                $passportPath = $uploadResult['path'];
                // Update user with passport path
                $stmt = $pdo->prepare("UPDATE users SET passport_photo = ? WHERE id = ?");
                $stmt->execute([$passportPath, $userId]);
            } else {
                // Rollback if upload fails
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Photo upload failed: ' . $uploadResult['message']];
            }
        }
        
        // Assign location
        $stmt = $pdo->prepare("
            INSERT INTO user_locations (
                user_id, lga, ward, community, enumeration_area, assigned_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['lga'],
            $data['ward'],
            $data['community'] ?? null,
            $data['enumeration_area'] ?? null,
            $_SESSION['user_id']
        ]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'create_enumerator', 
                   "Created enumerator: {$data['first_name']} {$data['surname']} (ID: $employeeId)");
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => "Enumerator created successfully! Employee ID: $employeeId"
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function updateUserStatus($userId, $status) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $userId]);
        
        logActivity($_SESSION['user_id'], 'update_status', 
                   "Updated user status to: $status for user ID: $userId");
        
        return ['success' => true, 'message' => 'Status updated successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// function logActivity($userId, $action, $details) {
//     global $pdo;
    
//     try {
//         $stmt = $pdo->prepare("
//             INSERT INTO activity_log (user_id, action, details, ip_address) 
//             VALUES (?, ?, ?, ?)
//         ");
//         $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR']]);
//     } catch (PDOException $e) {
//         // Silently fail if activity log fails
//     }
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Manage Enumerators - Delta Census</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Your styles here */
        .photo-preview {
            margin-top: 10px;
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            display: none;
        }
        .photo-preview.has-image {
            display: block;
        }
        .avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }
        .employee-id-display {
            background: #f1f5f9;
            padding: 10px;
            border-radius: 6px;
            font-weight: 600;
            color: #2563eb;
            font-size: 16px;
            margin-top: 5px;
        }
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
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        .dashboard-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        .form-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 4px;
            font-size: 14px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .btn-primary {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active {
            background: #dcfce7;
            color: #166534;
        }
        .badge-inactive {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-suspended {
            background: #fee2e2;
            color: #991b1b;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .user-table th {
            background: #f1f5f9;
            padding: 10px;
            text-align: left;
            font-weight: 600;
        }
        .user-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .user-table {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Navigation -->
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2>👥 Manage Enumerators</h2>
                <div class="user-menu">
                    <span class="user-name" style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    <a href="admin_dashboard.php" class="btn-sm btn-secondary">Dashboard</a>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Create User Form -->
            <div class="dashboard-card">
                <h3 style="margin-bottom: 20px;">➕ Create New Enumerator</h3>
                <form method="POST" action="" class="user-form" enctype="multipart/form-data" id="enumeratorForm">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="form-section">
                        <h4 style="margin-bottom: 16px; color: #475569; font-size: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Personal Information</h4>
                        
                        <!-- Employee ID - Auto Generated -->
                        <div class="form-group">
                            <label>Employee/Staff ID *</label>
                            <div class="employee-id-display">
                                <?php 
                                // Generate a preview ID
                                $previewId = generateEmployeeId($pdo);
                                echo htmlspecialchars($previewId);
                                ?>
                                <small style="display: block; font-weight: normal; color: #64748b; font-size: 12px; margin-top: 4px;">
                                    Auto-generated. Will be assigned when you create the enumerator.
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="surname">Surname *</label>
                                <input type="text" id="surname" name="surname" required>
                            </div>
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="other_name">Other Name</label>
                                <input type="text" id="other_name" name="other_name">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender *</label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth *</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" required placeholder="e.g., 08012345678">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="passport_photo">Passport Photograph</label>
                            <input type="file" id="passport_photo" name="passport_photo" accept="image/*" onchange="previewPhoto(this)">
                            <small style="display: block; color: #64748b; font-size: 12px; margin-top: 4px;">
                                Max size: 2MB. Allowed formats: JPEG, PNG, GIF, WEBP
                            </small>
                            <img id="photoPreview" class="photo-preview" alt="Passport Preview">
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 style="margin-bottom: 16px; color: #475569; font-size: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Assignment Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="lga">LGA *</label>
                                <select id="lga" name="lga" required>
                                    <option value="">Select LGA</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="ward">Ward *</label>
                                <select id="ward" name="ward" required>
                                    <option value="">Select Ward</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="community">Community</label>
                                <input type="text" id="community" name="community" placeholder="e.g., Community A">
                            </div>
                            <div class="form-group">
                                <label for="enumeration_area">Enumeration Area</label>
                                <input type="text" id="enumeration_area" name="enumeration_area" placeholder="e.g., EA001">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 style="margin-bottom: 16px; color: #475569; font-size: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Login Credentials</h4>
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required minlength="8" placeholder="Min 8 characters">
                            <small style="display: block; color: #64748b; font-size: 12px; margin-top: 4px;">Password must be at least 8 characters</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">Create Enumerator</button>
                </form>
            </div>
            
            <!-- Enumerator List -->
            <div class="dashboard-card">
                <h3 style="margin-bottom: 20px;">📋 Enumerators List</h3>
                <div class="table-responsive">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Employee ID</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Location</th>
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
                                            <img src="<?php echo htmlspecialchars($enumerator['passport_photo']); ?>" alt="Photo" class="avatar-sm" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="avatar-placeholder">
                                                <?php echo strtoupper(substr($enumerator['first_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($enumerator['surname'] . ', ' . $enumerator['first_name']);
                                        if ($enumerator['other_name']) {
                                            echo ' ' . htmlspecialchars($enumerator['other_name']);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($enumerator['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($enumerator['email']); ?></td>
                                    <td><?php echo htmlspecialchars($enumerator['phone']); ?></td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($enumerator['lga'] . ' - ' . $enumerator['ward']);
                                        if ($enumerator['community']) {
                                            echo '<br><small>' . htmlspecialchars($enumerator['community']) . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $enumerator['status']; ?>">
                                            <?php echo ucfirst($enumerator['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="user_id" value="<?php echo $enumerator['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="status-select" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; font-size: 12px;">
                                                <option value="active" <?php echo $enumerator['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $enumerator['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="suspended" <?php echo $enumerator['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 20px;">
                                        No enumerators found. Create your first enumerator above.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script src="js/script.js"></script>
</body>
</html>