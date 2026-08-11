<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function loginUser($username, $password) {
    global $pdo;
    
    try {
        // Get user by username or email
        $stmt = $pdo->prepare("
            SELECT u.*, ul.lga, ul.ward, ul.community, ul.enumeration_area 
            FROM users u
            LEFT JOIN user_locations ul ON u.id = ul.user_id
            WHERE (u.username = ? OR u.email = ?)
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            logLoginAttempt(null, $username, false, 'Invalid credentials');
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Check status
        if ($user['status'] === 'suspended') {
            logLoginAttempt($user['id'], $user['username'], false, 'Account suspended');
            return ['success' => false, 'message' => 'Your account has been suspended. Please contact admin.'];
        }
        
        if ($user['status'] === 'inactive') {
            logLoginAttempt($user['id'], $user['username'], false, 'Account inactive');
            return ['success' => false, 'message' => 'Your account is inactive. Please contact admin.'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            logLoginAttempt($user['id'], $user['username'], false, 'Invalid password');
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Check for excessive failed attempts
        if (isAccountLocked($user['id'])) {
            logLoginAttempt($user['id'], $user['username'], false, 'Account locked');
            return ['success' => false, 'message' => 'Account temporarily locked due to multiple failed attempts'];
        }
        
        // Update last login
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        logLoginAttempt($user['id'], $user['username'], true);
        logActivity($user['id'], 'login', 'User logged in successfully', 'authentication');
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['surname'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        // Store location for enumerators
        if ($user['role'] === 'enumerator') {
            $_SESSION['location'] = [
                'lga' => $user['lga'],
                'ward' => $user['ward'],
                'community' => $user['community'],
                'enumeration_area' => $user['enumeration_area']
            ];
        }
        
        return ['success' => true, 'role' => $user['role']];
        
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
    }
}

function isAccountLocked($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                          WHERE user_id = ? AND success = 0 
                          AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    return $result['attempts'] >= 5;
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role && $_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit();
    }
}

function logout() {
    // Log logout activity
    if (isset($_SESSION['user_id'])) {
        logLogout($_SESSION['user_id']);
    }
    $_SESSION = array();
    session_destroy();
    header('Location: login.php');
    exit();
}

function getUserById($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id, username, employee_id, surname, first_name, other_name,
               email, phone, gender, date_of_birth, role, status, 
               created_at, last_login 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function updateUser($user_id, $data) {
    global $pdo;
    
    $fields = [];
    $values = [];
    
    foreach ($data as $key => $value) {
        if ($key !== 'id' && $key !== 'password_hash') {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
    }
    
    if (!empty($fields)) {
        $values[] = $user_id;
        $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }
    
    return false;
}
?>