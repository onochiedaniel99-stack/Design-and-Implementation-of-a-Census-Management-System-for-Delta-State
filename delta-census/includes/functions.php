<?php
// ============================================
// SANITIZATION & UTILITY FUNCTIONS
// ============================================

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function getErrorMessage($field, $errors) {
    return isset($errors[$field]) ? '<span class="error-message">' . $errors[$field] . '</span>' : '';
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function getTimeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

// ============================================
// BADGE & DROPDOWN OPTIONS FUNCTIONS
// ============================================

function getStatusBadge($status) {
    $badges = [
        'draft' => '<span class="badge badge-draft">Draft</span>',
        'submitted' => '<span class="badge badge-submitted">Submitted</span>',
        'verified' => '<span class="badge badge-verified">Verified</span>',
        'rejected' => '<span class="badge badge-rejected">Rejected</span>'
    ];
    return $badges[$status] ?? $badges['draft'];
}

function getRelationshipOptions($selected = null) {
    $relationships = ['Head', 'Wife', 'Husband', 'Son', 'Daughter', 'Father', 
                     'Mother', 'Brother', 'Sister', 'Relative', 'Domestic Staff', 
                     'Tenant', 'Other'];
    $html = '';
    foreach ($relationships as $rel) {
        $sel = ($selected == $rel) ? 'selected' : '';
        $html .= "<option value=\"$rel\" $sel>$rel</option>";
    }
    return $html;
}

function getQualificationOptions($selected = null) {
    $qualifications = ['No Formal Education', 'Primary', 'Secondary', 'NCE', 'OND', 
                       'HND', "Bachelor's Degree", "Master's Degree", 'PhD', 
                       'Vocational Training'];
    $html = '';
    foreach ($qualifications as $qual) {
        $sel = ($selected == $qual) ? 'selected' : '';
        $html .= "<option value=\"$qual\" $sel>$qual</option>";
    }
    return $html;
}

function getEmploymentOptions($selected = null) {
    $statuses = ['Employed', 'Self-employed', 'Unemployed', 'Student', 'Retired'];
    $html = '';
    foreach ($statuses as $status) {
        $sel = ($selected == $status) ? 'selected' : '';
        $html .= "<option value=\"$status\" $sel>$status</option>";
    }
    return $html;
}

function getDisabilityOptions($selected = null) {
    $types = ['None', 'Visual', 'Hearing', 'Physical', 'Speech', 'Mental', 'Multiple'];
    $html = '';
    foreach ($types as $type) {
        $sel = ($selected == $type) ? 'selected' : '';
        $html .= "<option value=\"$type\" $sel>$type</option>";
    }
    return $html;
}

function getGenderOptions($selected = null) {
    $genders = ['Male', 'Female'];
    $html = '';
    foreach ($genders as $gender) {
        $sel = ($selected == $gender) ? 'selected' : '';
        $html .= "<option value=\"$gender\" $sel>$gender</option>";
    }
    return $html;
}

// ============================================
// AUDIT LOG FUNCTIONS - SINGLE DEFINITION
// ============================================

/**
 * Log user activity to audit_logs table
 */
function logActivity($userId, $action, $description = '', $category = 'general', $details = null) {
    global $pdo;
    
    try {
        // Get user info
        $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        $audit_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id, username, user_role, action, category, 
                description, ip_address, user_agent, details
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $detailsJson = $details ? json_encode($details) : null;
        
        $stmt->execute([
            $userId,
            $user['username'],
            $user['role'],
            $action,
            $category,
            $description,
            $audit_ip,
            $userAgent,
            $detailsJson
        ]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log login attempt to login_history table
 */
function logLoginAttempt($userId, $username, $success = true, $failureReason = null) {
    global $pdo;
    
    try {
        // Get user role if userId provided
        $userRole = 'enumerator';
        if ($userId) {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                $userRole = $user['role'];
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO login_history (
                user_id, username, user_role, ip_address, user_agent, 
                success, failure_reason, session_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $sessionId = session_id() ?: null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt->execute([
            $userId,
            $username,
            $userRole,
            $ipAddress,
            $userAgent,
            $success ? 1 : 0,
            $failureReason,
            $sessionId
        ]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Login log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log logout
 */
function logLogout($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE login_history 
            SET logout_time = NOW() 
            WHERE user_id = ? AND logout_time IS NULL 
            ORDER BY login_time DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        
        // Also log to audit
        logActivity($userId, 'logout', 'User logged out', 'authentication');
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Logout log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit logs with filters
 */
function getAuditLogs($filters = []) {
    global $pdo;
    
    $query = "SELECT * FROM audit_logs WHERE 1=1";
    $params = [];
    
    if (!empty($filters['user_id'])) {
        $query .= " AND user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['action'])) {
        $query .= " AND action LIKE ?";
        $params[] = "%" . $filters['action'] . "%";
    }
    
    if (!empty($filters['category'])) {
        $query .= " AND category = ?";
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND DATE(created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND DATE(created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $query .= " ORDER BY created_at DESC LIMIT 100";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get login history
 */
function getLoginHistory($filters = []) {
    global $pdo;
    
    $query = "SELECT * FROM login_history WHERE 1=1";
    $params = [];
    
    if (!empty($filters['user_id'])) {
        $query .= " AND user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (isset($filters['success']) && $filters['success'] !== '') {
        $query .= " AND success = ?";
        $params[] = $filters['success'] ? 1 : 0;
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND DATE(login_time) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND DATE(login_time) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $query .= " ORDER BY login_time DESC LIMIT 200";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get recent failed login attempts
 */
function getFailedLoginAttempts($limit = 50) {
    global $pdo;
    
    $query = "
        SELECT * FROM login_history 
        WHERE success = 0 
        ORDER BY login_time DESC 
        LIMIT ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get activity statistics
 */
function getActivityStats() {
    global $pdo;
    
    $stats = [];
    
    // Total activities today
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM audit_logs WHERE DATE(created_at) = CURDATE()");
    $stats['today'] = $stmt->fetch()['count'];
    
    // Total activities this week
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM audit_logs WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())");
    $stats['week'] = $stmt->fetch()['count'];
    
    // Total activities this month
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM audit_logs WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['month'] = $stmt->fetch()['count'];
    
    // Failed logins today
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM login_history WHERE DATE(login_time) = CURDATE() AND success = 0");
    $stats['failed_today'] = $stmt->fetch()['count'];
    
    // Total users logged in today
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as count FROM login_history WHERE DATE(login_time) = CURDATE() AND success = 1");
    $stats['active_users'] = $stmt->fetch()['count'];
    
    return $stats;
}

// ============================================
// LEGACY ACTIVITY LOG (for backward compatibility)
// ============================================

/**
 * Legacy function - logs to activity_log table
 * Used for backward compatibility
 */
function logLegacyActivity($userId, $action, $details) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'Unknown']);
    } catch (PDOException $e) {
        error_log("Legacy activity log error: " . $e->getMessage());
    }
}
?>