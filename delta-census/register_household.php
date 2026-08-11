<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

global $pdo;

// Get enumerator's location
$stmt = $pdo->prepare("
    SELECT ul.* 
    FROM user_locations ul
    WHERE ul.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$location = $stmt->fetch();

if (!$location) {
    header('Location: no_location.php');
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$householdId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : null;

// Get household data if editing
$household = null;
if ($householdId) {
    $stmt = $pdo->prepare("SELECT * FROM households WHERE id = ? AND enumerator_id = ?");
    $stmt->execute([$householdId, $_SESSION['user_id']]);
    $household = $stmt->fetch();
}

// Get members if viewing household
$members = [];
if ($householdId) {
    $stmt = $pdo->prepare("SELECT * FROM household_members WHERE household_id = ? ORDER BY is_head DESC, id ASC");
    $stmt->execute([$householdId]);
    $members = $stmt->fetchAll();
}

// Handle Household Information Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_household') {
        $result = saveHousehold($_POST, $_SESSION['user_id'], $location);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        if ($result['success'] && isset($result['household_id'])) {
            // ADD AUDIT LOG HERE - After household is created
            logActivity(
                $_SESSION['user_id'], 
                'create', 
                'Created new household: ' . $result['household_code'], 
                'household', 
                ['household_id' => $result['household_id']]
            );
            
            header('Location: register_household.php?step=2&id=' . $result['household_id']);
            exit();
        }
    }
    
    if ($_POST['action'] === 'save_member') {
        $result = saveMember($_POST, $_SESSION['user_id']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        if ($result['success']) {
            // ADD AUDIT LOG HERE - Member added
            logActivity(
                $_SESSION['user_id'], 
                'add_member', 
                'Added member to household ID: ' . $_POST['household_id'] . ' - ' . $_POST['first_name'] . ' ' . $_POST['surname'], 
                'household',
                ['household_id' => $_POST['household_id'], 'member_name' => $_POST['first_name'] . ' ' . $_POST['surname']]
            );
            
            header('Location: register_household.php?step=2&id=' . $_POST['household_id']);
            exit();
        }
    }
    
    if ($_POST['action'] === 'submit_household') {
        $result = submitHousehold($_POST['household_id']);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        if ($result['success']) {
            // ADD AUDIT LOG HERE - After household is submitted
            // Get household code
            $stmt = $pdo->prepare("SELECT household_code FROM households WHERE id = ?");
            $stmt->execute([$_POST['household_id']]);
            $householdData = $stmt->fetch();
            $householdCode = $householdData['household_code'] ?? 'Unknown';
            
            logActivity(
                $_SESSION['user_id'], 
                'submit', 
                'Submitted household: ' . $householdCode . ' for verification', 
                'household',
                ['household_id' => $_POST['household_id']]
            );
            
            header('Location: register_household.php?step=5&id=' . $_POST['household_id']);
            exit();
        }
    }
}

function generateHouseholdNumber($pdo) {
    $stmt = $pdo->query("SELECT household_code FROM households ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch();
    
    if ($last) {
        $num = intval(substr($last['household_code'], 3)) + 1;
    } else {
        $num = 1;
    }
    
    return 'HH-' . str_pad($num, 6, '0', STR_PAD_LEFT);
}

function saveHousehold($data, $userId, $location) {
    global $pdo;
    
    try {
        $householdCode = generateHouseholdNumber($pdo);
        
        $stmt = $pdo->prepare("
            INSERT INTO households (
                household_code, 
                head_of_household,
                household_head,
                phone, 
                lga, 
                ward, 
                community,
                enumeration_area, 
                house_number, 
                street_name, 
                landmark,
                building_type, 
                house_ownership, 
                number_of_households,
                number_of_rooms, 
                enumerator_id, 
                ip_address, 
                device_info,
                status, 
                created_by,
                address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)
        ");
        
        $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        // Build address from street name and house number
        $address = trim(($data['house_number'] ?? '') . ' ' . ($data['street_name'] ?? ''));
        
        $stmt->execute([
            $householdCode,
            $data['household_head'], // head_of_household
            $data['household_head'], // household_head (duplicate for compatibility)
            $data['phone'] ?? null,
            $location['lga'],
            $location['ward'],
            $data['community'],
            $data['enumeration_area'],
            $data['house_number'] ?? null,
            $data['street_name'],
            $data['landmark'] ?? null,
            $data['building_type'],
            $data['house_ownership'],
            (int)$data['number_of_households'],
            (int)$data['number_of_rooms'],
            $userId,
            $ipAddress,
            $deviceInfo,
            $userId,
            $address
        ]);
        
        $householdId = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Household information saved successfully',
            'household_id' => $householdId,
            'household_code' => $householdCode // Return the code for logging
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error saving household: ' . $e->getMessage()];
    }
}

function saveMember($data, $userId) {
    global $pdo;
    
    try {
        // Calculate age from date of birth
        $dob = new DateTime($data['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
        
        // Check if this household already has a head
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM household_members WHERE household_id = ? AND is_head = 1");
        $stmt->execute([$data['household_id']]);
        $hasHead = $stmt->fetchColumn() > 0;
        
        // If this is the first member or no head exists, make them head
        $isHead = 0;
        if (!$hasHead) {
            $isHead = 1;
        }
        
        // If user selected 'Head' but there's already a head, prevent it
        if ($data['relationship'] === 'Head' && $hasHead) {
            return ['success' => false, 'message' => 'This household already has a Head. Only one person can be the Household Head.'];
        }
        
        $relationship = $data['relationship'];
        if ($isHead) {
            $relationship = 'Head';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO household_members (
                household_id, 
                surname, 
                first_name, 
                other_name, 
                gender,
                date_of_birth, 
                age, 
                relationship, 
                is_head,
                marital_status, 
                nationality, 
                state_of_origin,
                lga_of_origin, 
                state_of_birth, 
                lga_of_birth,
                ethnicity, 
                religion, 
                language_spoken,
                currently_in_school, 
                highest_qualification,
                literacy_read, 
                literacy_write,
                employment_status, 
                occupation, 
                industry,
                place_of_work, 
                disability, 
                disability_type,
                health_insurance, 
                nin, 
                phone, 
                email
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['household_id'],
            $data['surname'],
            $data['first_name'],
            $data['other_name'] ?? null,
            $data['gender'],
            $data['date_of_birth'],
            $age,
            $relationship,
            $isHead,
            $data['marital_status'] ?? null,
            $data['nationality'] ?? 'Nigerian',
            $data['state_of_origin'] ?? '',
            $data['lga_of_origin'] ?? '',
            $data['state_of_birth'] ?? '',
            $data['lga_of_birth'] ?? '',
            $data['ethnicity'] ?? null,
            $data['religion'] ?? null,
            $data['language_spoken'] ?? null,
            $data['currently_in_school'] ?? 'No',
            $data['highest_qualification'] ?? 'No Formal Education',
            $data['literacy_read'] ?? 'No',
            $data['literacy_write'] ?? 'No',
            $data['employment_status'] ?? 'Unemployed',
            $data['occupation'] ?? '',
            $data['industry'] ?? null,
            $data['place_of_work'] ?? null,
            $data['disability'] ?? 'No',
            $data['disability_type'] ?? null,
            $data['health_insurance'] ?? 'No',
            $data['nin'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null
        ]);
        
        // Update household total members count
        $stmt = $pdo->prepare("UPDATE households SET total_members = total_members + 1 WHERE id = ?");
        $stmt->execute([$data['household_id']]);
        
        return ['success' => true, 'message' => 'Member added successfully'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error saving member: ' . $e->getMessage()];
    }
}

function submitHousehold($householdId) {
    global $pdo;
    
    try {
        // Check if household has at least one member
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM household_members WHERE household_id = ?");
        $stmt->execute([$householdId]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            return ['success' => false, 'message' => 'Household must have at least one member'];
        }
        
        $stmt = $pdo->prepare("
            UPDATE households 
            SET status = 'submitted', submitted_at = NOW() 
            WHERE id = ? AND status = 'draft'
        ");
        $stmt->execute([$householdId]);
        
        return ['success' => true, 'message' => 'Household submitted successfully'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error submitting: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Register Household - Delta Census</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Your existing styles here */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
            padding: 0 10px;
        }
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 30px;
            right: 30px;
            height: 2px;
            background: #e2e8f0;
            z-index: 0;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .step-item.active .step-number {
            background: #2563eb;
            color: white;
        }
        .step-item.completed .step-number {
            background: #22c55e;
            color: white;
        }
        .step-label {
            margin-top: 8px;
            font-size: 12px;
            color: #64748b;
            text-align: center;
            font-weight: 500;
        }
        .step-item.active .step-label {
            color: #2563eb;
        }
        .step-item.completed .step-label {
            color: #22c55e;
        }
        .form-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        .form-section h4 {
            margin-bottom: 16px;
            color: #0f172a;
            font-size: 16px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
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
            color: #0f172a;
        }
        .form-group label .required {
            color: #ef4444;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-group .hint {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 4px;
        }
        .form-group .auto-value {
            background: #f1f5f9;
            padding: 10px 12px;
            border-radius: 6px;
            color: #475569;
            font-weight: 500;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-success {
            background: #22c55e;
            color: white;
        }
        .btn-success:hover {
            background: #16a34a;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        .btn-secondary:hover {
            background: #475569;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .btn-warning:hover {
            background: #d97706;
        }
        .members-list {
            margin-top: 20px;
        }
        .member-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .member-card .member-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .member-card .badge-head {
            background: #2563eb;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .member-card .member-actions {
            display: flex;
            gap: 8px;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin: 16px 0;
        }
        .summary-item {
            padding: 12px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .summary-item strong {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 4px;
        }
        .summary-item span {
            font-size: 16px;
            font-weight: 600;
        }
        .app-container {
            max-width: 900px;
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
        .dashboard-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .progress-steps {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding: 10px 0;
            }
            .step-item {
                min-width: 60px;
            }
            .step-label {
                font-size: 10px;
            }
            .summary-grid {
                grid-template-columns: 1fr;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn-group .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Navigation -->
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2 style="font-size: 20px;">🏠 Register Household</h2>
                <div class="user-menu">
                    <span class="user-name" style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Enumerator'); ?></span>
                    <a href="enumerator_dashboard.php" class="btn btn-sm btn-secondary" style="padding: 6px 12px; background: #64748b; color: white; border-radius: 4px; text-decoration: none; font-size: 14px;">Dashboard</a>
                    <a href="logout.php" class="btn btn-sm btn-danger" style="padding: 6px 12px; background: #ef4444; color: white; border-radius: 4px; text-decoration: none; font-size: 14px;">Logout</a>
                </div>
            </div>
        </nav>

        <!-- Progress Steps -->
        <div class="progress-steps">
            <div class="step-item <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-label">Household Info</div>
            </div>
            <div class="step-item <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-label">Add Members</div>
            </div>
            <div class="step-item <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-label">Personal Info</div>
            </div>
            <div class="step-item <?php echo $step >= 4 ? 'active' : ''; ?> <?php echo $step > 4 ? 'completed' : ''; ?>">
                <div class="step-number">4</div>
                <div class="step-label">Review</div>
            </div>
            <div class="step-item <?php echo $step >= 5 ? 'active' : ''; ?> <?php echo $step > 5 ? 'completed' : ''; ?>">
                <div class="step-number">5</div>
                <div class="step-label">Submit</div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: Household Information -->
        <?php if ($step == 1 || (!$householdId && $step < 2)): ?>
        <div class="dashboard-card">
            <h3 style="margin-bottom: 20px;">📋 Section 1: Household Information</h3>
            <p style="color: #64748b; margin-bottom: 20px;">This information is collected once for each household.</p>
            
            <form method="POST" action="" id="householdForm">
                <input type="hidden" name="action" value="save_household">
                
                <div class="form-section">
                    <h4>📍 Location & Identification</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Household Number</label>
                            <div class="auto-value">
                                <?php echo generateHouseholdNumber($pdo); ?>
                                <small style="display: block; color: #64748b; font-size: 12px;">Auto-generated</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="household_head">Household Head <span class="required">*</span></label>
                            <input type="text" id="household_head" name="household_head" required placeholder="Full name of household head">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="08031234567">
                        </div>
                        <div class="form-group">
                            <label>LGA <span class="required">*</span></label>
                            <div class="auto-value"><?php echo htmlspecialchars($location['lga']); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ward <span class="required">*</span></label>
                            <div class="auto-value"><?php echo htmlspecialchars($location['ward']); ?></div>
                        </div>
                        <div class="form-group">
                            <label for="community">Community <span class="required">*</span></label>
                            <input type="text" id="community" name="community" required placeholder="Community name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="enumeration_area">Enumeration Area <span class="required">*</span></label>
                            <input type="text" id="enumeration_area" name="enumeration_area" required placeholder="EA-012">
                        </div>
                        <div class="form-group">
                            <label for="house_number">House Number</label>
                            <input type="text" id="house_number" name="house_number" placeholder="No. 15">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="street_name">Street Name <span class="required">*</span></label>
                            <input type="text" id="street_name" name="street_name" required placeholder="Street name">
                        </div>
                        <div class="form-group">
                            <label for="landmark">Landmark</label>
                            <input type="text" id="landmark" name="landmark" placeholder="Opposite Primary School">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>🏗️ Building Information</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="building_type">Building Type <span class="required">*</span></label>
                            <select id="building_type" name="building_type" required>
                                <option value="">Select Building Type</option>
                                <option value="Bungalow">Bungalow</option>
                                <option value="Storey Building">Storey Building</option>
                                <option value="Duplex">Duplex</option>
                                <option value="Flat">Flat</option>
                                <option value="Mansion">Mansion</option>
                                <option value="Hut">Hut</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="house_ownership">House Ownership <span class="required">*</span></label>
                            <select id="house_ownership" name="house_ownership" required>
                                <option value="">Select Ownership Type</option>
                                <option value="Owned">Owned</option>
                                <option value="Rented">Rented</option>
                                <option value="Family Owned">Family Owned</option>
                                <option value="Leased">Leased</option>
                                <option value="Squatter">Squatter</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="number_of_households">Number of Households in Building <span class="required">*</span></label>
                            <input type="number" id="number_of_households" name="number_of_households" required min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label for="number_of_rooms">Number of Rooms <span class="required">*</span></label>
                            <input type="number" id="number_of_rooms" name="number_of_rooms" required min="1" value="1">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>📍 GPS Coordinates</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>GPS Latitude</label>
                            <div class="auto-value">
                                <span id="gps_latitude">6.204565</span>
                                <small style="display: block; color: #64748b; font-size: 12px;">Auto-captured from device</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>GPS Longitude</label>
                            <div class="auto-value">
                                <span id="gps_longitude">6.421545</span>
                                <small style="display: block; color: #64748b; font-size: 12px;">Auto-captured from device</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>GPS Accuracy</label>
                        <div class="auto-value">
                            <span id="gps_accuracy">8.2m</span>
                            <small style="display: block; color: #64748b; font-size: 12px;">Auto-captured from device</small>
                        </div>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Save & Continue →</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Step 2 & 3: Add Members -->
        <?php if ($step == 2 && $householdId): ?>
        <div class="dashboard-card">
            <h3 style="margin-bottom: 20px;">👥 Section 2: Household Members</h3>
            <p style="color: #64748b; margin-bottom: 20px;">Add all members of this household. The first member will be automatically set as the Head.</p>
            
            <!-- Member Form -->
<!-- Member Form -->
            <div class="form-section">
                <h4>➕ Add Member</h4>
                <form method="POST" action="" id="memberForm">
                    <input type="hidden" name="action" value="save_member">
                    <input type="hidden" name="household_id" value="<?php echo $householdId; ?>">
                    
                    <?php 
                    // Check if household already has a head
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM household_members WHERE household_id = ? AND is_head = 1");
                    $stmt->execute([$householdId]);
                    $hasHead = $stmt->fetchColumn() > 0;
                    ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="surname">Surname <span class="required">*</span></label>
                            <input type="text" id="surname" name="surname" required>
                        </div>
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="other_name">Other Name</label>
                            <input type="text" id="other_name" name="other_name">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender <span class="required">*</span></label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" required onchange="calculateAge()">
                        </div>
                        <div class="form-group">
                            <label for="age">Age</label>
                            <input type="number" id="age" name="age" readonly style="background: #f1f5f9;">
                            <span class="hint">Auto-calculated from Date of Birth</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="relationship">Relationship to Household Head <span class="required">*</span></label>
                            <select id="relationship" name="relationship" required>
                                <option value="">Select Relationship</option>
                                <?php if (!$hasHead): ?>
                                    <option value="Head">Head</option>
                                <?php endif; ?>
                                <option value="Wife">Wife</option>
                                <option value="Husband">Husband</option>
                                <option value="Son">Son</option>
                                <option value="Daughter">Daughter</option>
                                <option value="Father">Father</option>
                                <option value="Mother">Mother</option>
                                <option value="Brother">Brother</option>
                                <option value="Sister">Sister</option>
                                <option value="Relative">Relative</option>
                                <option value="Domestic Staff">Domestic Staff</option>
                                <option value="Tenant">Tenant</option>
                                <option value="Other">Other</option>
                            </select>
                            <?php if ($hasHead): ?>
                                <span class="hint" style="color: #f59e0b;">⚠️ This household already has a Head. Select a different relationship.</span>
                            <?php else: ?>
                                <span class="hint">The first member will be automatically set as the Household Head</span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="marital_status">Marital Status <span class="required">(Adults)</span></label>
                            <select id="marital_status" name="marital_status">
                                <option value="">Select Marital Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Demographic Information -->
                    <div class="form-section" style="margin-top: 16px;">
                        <h4>📊 Demographic Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nationality">Nationality <span class="required">*</span></label>
                                <input type="text" id="nationality" name="nationality" required value="Nigerian" readonly style="background: #f1f5f9; cursor: not-allowed;">
                            </div>
                            <div class="form-group">
                                <label for="state_of_origin">State of Origin <span class="required">*</span></label>
                                <select id="state_of_origin" name="state_of_origin" required onchange="updateLGAs()">
                                    <option value="">Select State of Origin</option>
                                    <option value="abia">Abia</option>
                                    <option value="adamawa">Adamawa</option>
                                    <option value="akwa_ibom">Akwa Ibom</option>
                                    <option value="anambra">Anambra</option>
                                    <option value="bauchi">Bauchi</option>
                                    <option value="bayelsa">Bayelsa</option>
                                    <option value="benue">Benue</option>
                                    <option value="borno">Borno</option>
                                    <option value="cross_river">Cross River</option>
                                    <option value="delta">Delta</option>
                                    <option value="ebonyi">Ebonyi</option>
                                    <option value="edo">Edo</option>
                                    <option value="ekiti">Ekiti</option>
                                    <option value="enugu">Enugu</option>
                                    <option value="gombe">Gombe</option>
                                    <option value="imo">Imo</option>
                                    <option value="jigawa">Jigawa</option>
                                    <option value="kaduna">Kaduna</option>
                                    <option value="kano">Kano</option>
                                    <option value="katsina">Katsina</option>
                                    <option value="kebbi">Kebbi</option>
                                    <option value="kogi">Kogi</option>
                                    <option value="kwara">Kwara</option>
                                    <option value="lagos">Lagos</option>
                                    <option value="nasarawa">Nasarawa</option>
                                    <option value="niger">Niger</option>
                                    <option value="ogun">Ogun</option>
                                    <option value="ondo">Ondo</option>
                                    <option value="osun">Osun</option>
                                    <option value="oyo">Oyo</option>
                                    <option value="plateau">Plateau</option>
                                    <option value="rivers">Rivers</option>
                                    <option value="sokoto">Sokoto</option>
                                    <option value="taraba">Taraba</option>
                                    <option value="yobe">Yobe</option>
                                    <option value="zamfara">Zamfara</option>
                                    <option value="abuja">FCT Abuja</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="lga_of_origin">LGA of Origin <span class="required">*</span></label>
                                <select id="lga_of_origin" name="lga_of_origin" required>
                                    <option value="">Select LGA of Origin</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="state_of_birth">State of Birth <span class="required">*</span></label>
                                <select id="state_of_birth" name="state_of_birth" required onchange="updateBirthLGAs()">
                                    <option value="">Select State of Birth</option>
                                    <option value="abia">Abia</option>
                                    <option value="adamawa">Adamawa</option>
                                    <option value="akwa_ibom">Akwa Ibom</option>
                                    <option value="anambra">Anambra</option>
                                    <option value="bauchi">Bauchi</option>
                                    <option value="bayelsa">Bayelsa</option>
                                    <option value="benue">Benue</option>
                                    <option value="borno">Borno</option>
                                    <option value="cross_river">Cross River</option>
                                    <option value="delta">Delta</option>
                                    <option value="ebonyi">Ebonyi</option>
                                    <option value="edo">Edo</option>
                                    <option value="ekiti">Ekiti</option>
                                    <option value="enugu">Enugu</option>
                                    <option value="gombe">Gombe</option>
                                    <option value="imo">Imo</option>
                                    <option value="jigawa">Jigawa</option>
                                    <option value="kaduna">Kaduna</option>
                                    <option value="kano">Kano</option>
                                    <option value="katsina">Katsina</option>
                                    <option value="kebbi">Kebbi</option>
                                    <option value="kogi">Kogi</option>
                                    <option value="kwara">Kwara</option>
                                    <option value="lagos">Lagos</option>
                                    <option value="nasarawa">Nasarawa</option>
                                    <option value="niger">Niger</option>
                                    <option value="ogun">Ogun</option>
                                    <option value="ondo">Ondo</option>
                                    <option value="osun">Osun</option>
                                    <option value="oyo">Oyo</option>
                                    <option value="plateau">Plateau</option>
                                    <option value="rivers">Rivers</option>
                                    <option value="sokoto">Sokoto</option>
                                    <option value="taraba">Taraba</option>
                                    <option value="yobe">Yobe</option>
                                    <option value="zamfara">Zamfara</option>
                                    <option value="abuja">FCT Abuja</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="lga_of_birth">LGA of Birth <span class="required">*</span></label>
                                <select id="lga_of_birth" name="lga_of_birth" required>
                                    <option value="">Select LGA of Birth</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="ethnicity">Ethnicity</label>
                                <input type="text" id="ethnicity" name="ethnicity" placeholder="e.g., Igbo, Yoruba, Hausa">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="religion">Religion</label>
                                <select id="religion" name="religion">
                                    <option value="">Select Religion</option>
                                    <option value="Christianity">Christianity</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Traditional">Traditional</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="language_spoken">Language Spoken</label>
                                <input type="text" id="language_spoken" name="language_spoken" placeholder="e.g., Igbo, Yoruba, Hausa">
                            </div>
                        </div>
                    </div>
                                        
                    <!-- Education -->
                    <div class="form-section" style="margin-top: 16px;">
                        <h4>🎓 Education</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="currently_in_school">Currently in School <span class="required">*</span></label>
                                <select id="currently_in_school" name="currently_in_school" required>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="highest_qualification">Highest Qualification <span class="required">*</span></label>
                                <select id="highest_qualification" name="highest_qualification" required>
                                    <option value="">Select Qualification</option>
                                    <option value="No Formal Education">No Formal Education</option>
                                    <option value="Primary">Primary</option>
                                    <option value="Secondary">Secondary</option>
                                    <option value="NCE">NCE</option>
                                    <option value="OND">OND</option>
                                    <option value="HND">HND</option>
                                    <option value="Bachelor's Degree">Bachelor's Degree</option>
                                    <option value="Master's Degree">Master's Degree</option>
                                    <option value="PhD">PhD</option>
                                    <option value="Vocational Training">Vocational Training</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="literacy_read">Literacy (Can Read) <span class="required">*</span></label>
                                <select id="literacy_read" name="literacy_read" required>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="literacy_write">Literacy (Can Write) <span class="required">*</span></label>
                                <select id="literacy_write" name="literacy_write" required>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Employment -->
                    <div class="form-section" style="margin-top: 16px;">
                        <h4>💼 Employment</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employment_status">Employment Status <span class="required">*</span></label>
                                <select id="employment_status" name="employment_status" required>
                                    <option value="">Select Status</option>
                                    <option value="Employed">Employed</option>
                                    <option value="Self-employed">Self-employed</option>
                                    <option value="Unemployed">Unemployed</option>
                                    <option value="Student">Student</option>
                                    <option value="Retired">Retired</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="occupation">Occupation <span class="required">*</span></label>
                                <select id="occupation" name="occupation" required>
                                    <option value="">-- Select Occupation --</option>
                                    <option value="Farmer">Farmer</option>
                                    <option value="Trader">Trader</option>
                                    <option value="Civil Servant">Civil Servant</option>
                                    <option value="Teacher">Teacher</option>
                                    <option value="Lecturer">Lecturer</option>
                                    <option value="Student">Student</option>
                                    <option value="Doctor">Doctor</option>
                                    <option value="Nurse">Nurse</option>
                                    <option value="Engineer">Engineer</option>
                                    <option value="Lawyer">Lawyer</option>
                                    <option value="Accountant">Accountant</option>
                                    <option value="Artisan">Artisan</option>
                                    <option value="Commercial Driver">Commercial Driver</option>
                                    <option value="Private Driver">Private Driver</option>
                                    <option value="Mechanic">Mechanic</option>
                                    <option value="Tailor/Fashion Designer">Tailor/Fashion Designer</option>
                                    <option value="Hairdresser/Barber">Hairdresser/Barber</option>
                                    <option value="Business Owner">Business Owner</option>
                                    <option value="Self-Employed">Self-Employed</option>
                                    <option value="Security Personnel">Security Personnel</option>
                                    <option value="Military">Military</option>
                                    <option value="Police">Police</option>
                                    <option value="Clergy">Clergy</option>
                                    <option value="Retired">Retired</option>
                                    <option value="Unemployed">Unemployed</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="industry">Industry</label>
                                <select id="industry" name="industry">
                                    <option value="">-- Select Industry --</option>
                                    <option value="Agriculture">Agriculture</option>
                                    <option value="Construction">Construction</option>
                                    <option value="Education">Education</option>
                                    <option value="Energy">Energy</option>
                                    <option value="Finance">Finance</option>
                                    <option value="Government/Public Service">Government/Public Service</option>
                                    <option value="Healthcare">Healthcare</option>
                                    <option value="Hospitality & Tourism">Hospitality & Tourism</option>
                                    <option value="Information Technology">Information Technology</option>
                                    <option value="Manufacturing">Manufacturing</option>
                                    <option value="Media & Communications">Media & Communications</option>
                                    <option value="Mining">Mining</option>
                                    <option value="Oil & Gas">Oil & Gas</option>
                                    <option value="Real Estate">Real Estate</option>
                                    <option value="Retail & Wholesale">Retail & Wholesale</option>
                                    <option value="Security">Security</option>
                                    <option value="Telecommunications">Telecommunications</option>
                                    <option value="Transportation & Logistics">Transportation & Logistics</option>
                                    <option value="Utilities">Utilities</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="place_of_work">Place of Work</label>
                                <input type="text" id="place_of_work" name="place_of_work">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Health & Disability -->
                    <div class="form-section" style="margin-top: 16px;">
                        <h4>🏥 Health & Disability</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="disability">Disability <span class="required">*</span></label>
                                <select id="disability" name="disability" required onchange="toggleDisabilityType()">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="disability_type">Disability Type</label>
                                <select id="disability_type" name="disability_type" disabled>
                                    <option value="">Select Disability Type</option>
                                    <option value="None">None</option>
                                    <option value="Visual">Visual</option>
                                    <option value="Hearing">Hearing</option>
                                    <option value="Physical">Physical</option>
                                    <option value="Speech">Speech</option>
                                    <option value="Mental">Mental</option>
                                    <option value="Multiple">Multiple</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="health_insurance">Health Insurance</label>
                                <select id="health_insurance" name="health_insurance">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="nin">National Identification Number (NIN)</label>
                                <input type="text" id="nin" name="nin" placeholder="NIN number">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="member_phone">Phone Number</label>
                                <input type="tel" id="member_phone" name="phone" placeholder="Phone number">
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" placeholder="Email address">
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Add Member</button>
                        <a href="register_household.php?step=4&id=<?php echo $householdId; ?>" class="btn btn-primary">Review & Continue →</a>
                    </div>
                </form>
            </div>

                      
            
            <!-- Members List -->
            <?php if (count($members) > 0): ?>
            <div class="members-list">
                <h4 style="margin-bottom: 12px;">📋 Added Members (<?php echo count($members); ?>)</h4>
                <?php foreach ($members as $member): ?>
                <div class="member-card">
                    <div class="member-info">
                        <div>
                            <strong><?php echo htmlspecialchars($member['surname'] . ' ' . $member['first_name']); ?></strong>
                            <?php if ($member['is_head']): ?>
                                <span class="badge-head">HEAD</span>
                            <?php endif; ?>
                            <br>
                            <small><?php echo htmlspecialchars($member['relationship']); ?> • Age: <?php echo $member['age']; ?> • <?php echo $member['gender']; ?></small>
                        </div>
                    </div>
                    <div class="member-actions">
                        <a href="register_household.php?step=3&id=<?php echo $householdId; ?>&member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <button class="btn btn-sm btn-danger" onclick="if(confirm('Remove this member?')) { window.location.href='?delete_member=<?php echo $member['id']; ?>&id=<?php echo $householdId; ?>'; }">Remove</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Step 4: Review -->
        <?php if ($step == 4 && $householdId): ?>
        <div class="dashboard-card">
            <h3 style="margin-bottom: 20px;">✅ Review Entries</h3>
            
            <?php if ($household): ?>
            <!-- Household Summary -->
            <div class="form-section">
                <h4>🏠 Household Information</h4>
                <div class="summary-grid">
                    <div class="summary-item">
                        <strong>Household Number</strong>
                        <span><?php echo htmlspecialchars($household['household_code'] ?? $household['household_number'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Household Head</strong>
                        <span><?php echo htmlspecialchars($household['head_of_household'] ?? $household['household_head'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Location</strong>
                        <span><?php echo htmlspecialchars($household['lga'] . ' - ' . $household['ward'] . ' - ' . $household['community']); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Enumeration Area</strong>
                        <span><?php echo htmlspecialchars($household['enumeration_area']); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Address</strong>
                        <span><?php echo htmlspecialchars(($household['house_number'] ?? '') . ', ' . $household['street_name']); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Building</strong>
                        <span><?php echo htmlspecialchars($household['building_type'] . ' - ' . $household['house_ownership']); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Rooms</strong>
                        <span><?php echo $household['number_of_rooms']; ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Households in Building</strong>
                        <span><?php echo $household['number_of_households']; ?></span>
                    </div>
                </div>
                <a href="register_household.php?step=1&id=<?php echo $householdId; ?>" class="btn btn-sm btn-secondary">Edit Household Info</a>
            </div>
            
            <!-- Members Summary -->
            <div class="form-section">
                <h4>👥 Members (<?php echo count($members); ?>)</h4>
                <?php if (count($members) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                            <thead>
                                <tr style="background: #f1f5f9;">
                                    <th style="padding: 8px; text-align: left;">Name</th>
                                    <th style="padding: 8px; text-align: left;">Gender</th>
                                    <th style="padding: 8px; text-align: left;">Age</th>
                                    <th style="padding: 8px; text-align: left;">Relationship</th>
                                    <th style="padding: 8px; text-align: left;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">
                                        <?php echo htmlspecialchars($member['surname'] . ' ' . $member['first_name']); ?>
                                        <?php if ($member['is_head']): ?> <span style="background: #2563eb; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px;">HEAD</span> <?php endif; ?>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;"><?php echo $member['gender']; ?></td>
                                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;"><?php echo $member['age']; ?></td>
                                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;"><?php echo $member['relationship']; ?></td>
                                    <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">
                                        <?php if ($member['currently_in_school'] == 'Yes'): ?>
                                            <span style="color: #2563eb;">In School</span>
                                        <?php else: ?>
                                            <span style="color: #64748b;">Not in School</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="register_household.php?step=2&id=<?php echo $householdId; ?>" class="btn btn-sm btn-secondary" style="margin-top: 12px;">Edit Members</a>
                <?php else: ?>
                    <p style="color: #ef4444;">⚠️ No members added yet. Please add at least one member.</p>
                <?php endif; ?>
            </div>
            
            <div class="btn-group">
                <?php if (count($members) > 0): ?>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="submit_household">
                        <input type="hidden" name="household_id" value="<?php echo $householdId; ?>">
                        <button type="submit" class="btn btn-success" onclick="return confirm('Submit this household for verification?')">📤 Submit Household</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled>Add at least one member to submit</button>
                <?php endif; ?>
                <a href="enumerator_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Step 5: Success -->
        <?php if ($step == 5 && $householdId): ?>
        <div class="dashboard-card" style="text-align: center;">
            <div style="font-size: 64px; margin-bottom: 16px;">✅</div>
            <h3 style="margin-bottom: 12px;">Household Registered Successfully!</h3>
            <p style="color: #64748b; margin-bottom: 20px;">
                Household #<?php echo htmlspecialchars($household['household_code'] ?? $household['household_number'] ?? ''); ?> has been submitted for verification.
            </p>
            
            <div class="summary-grid" style="max-width: 400px; margin: 0 auto 24px;">
                <div class="summary-item">
                    <strong>Status</strong>
                    <span style="color: #f59e0b;">Submitted</span>
                </div>
                <div class="summary-item">
                    <strong>Members</strong>
                    <span><?php echo count($members); ?></span>
                </div>
            </div>
            
            <div class="btn-group" style="justify-content: center;">
                <a href="register_household.php?step=1" class="btn btn-primary">Register Another Household</a>
                <a href="enumerator_dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script>
        // Calculate age from date of birth
        function calculateAge() {
            const dob = document.getElementById('date_of_birth').value;
            if (dob) {
                const birthDate = new Date(dob);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                document.getElementById('age').value = age;
            }
        }

        // Toggle disability type field
        function toggleDisabilityType() {
            const disability = document.getElementById('disability').value;
            const disabilityType = document.getElementById('disability_type');
            if (disability === 'Yes') {
                disabilityType.disabled = false;
                disabilityType.required = true;
            } else {
                disabilityType.disabled = true;
                disabilityType.required = false;
                disabilityType.value = '';
            }
        }

        // Get GPS coordinates
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    document.getElementById('gps_latitude').textContent = position.coords.latitude.toFixed(6);
                    document.getElementById('gps_longitude').textContent = position.coords.longitude.toFixed(6);
                    document.getElementById('gps_accuracy').textContent = position.coords.accuracy.toFixed(1) + 'm';
                },
                function(error) {
                    console.log('GPS Error:', error);
                }
            );
        }

        // Auto-set relationship for first member
        document.addEventListener('DOMContentLoaded', function() {
            const relationship = document.getElementById('relationship');
            const membersCount = <?php echo count($members); ?>;
            
            if (membersCount === 0) {
                // First member should be Head
                const headOption = Array.from(relationship.options).find(opt => opt.value === 'Head');
                if (headOption) {
                    headOption.selected = true;
                }
            }
        });

        // Prevent duplicate household head
        document.getElementById('relationship')?.addEventListener('change', function() {
            const isHead = this.value === 'Head';
            const existingHead = <?php echo json_encode(array_filter($members, function($m) { return $m['is_head']; })); ?>;
            
            if (isHead && existingHead.length > 0) {
                alert('This household already has a Head. Only one person can be the Household Head.');
                this.value = '';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const relationship = document.getElementById('relationship');
            const hasHead = <?php echo $hasHead ? 'true' : 'false'; ?>;
            
            // If there's already a head, prevent selecting "Head"
            if (hasHead && relationship) {
                const headOption = Array.from(relationship.options).find(opt => opt.value === 'Head');
                if (headOption) {
                    headOption.disabled = true;
                    headOption.textContent = 'Head (Already exists)';
                }
            }
            
            // When relationship changes, check for head
            relationship?.addEventListener('change', function() {
                if (this.value === 'Head' && <?php echo $hasHead ? 'true' : 'false'; ?>) {
                    alert('This household already has a Head. Only one person can be the Household Head.');
                    this.value = '';
                }
            });
        });
    </script>
    <script src="js/lga.js"></script>
</body>
</html>