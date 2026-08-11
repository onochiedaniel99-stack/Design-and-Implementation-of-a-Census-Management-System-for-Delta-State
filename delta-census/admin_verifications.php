<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

global $pdo;

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'verify':
                $result = verifyHousehold($_POST['household_id'], $_POST['notes'] ?? '');
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
            case 'reject':
                $result = rejectHousehold($_POST['household_id'], $_POST['rejection_reason'] ?? '');
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
        }
    }
}

// Get submitted households
$stmt = $pdo->prepare("
    SELECT h.*, u.username as enumerator_name,
           (SELECT COUNT(*) FROM household_members WHERE household_id = h.id) as member_count
    FROM households h
    LEFT JOIN users u ON h.enumerator_id = u.id
    WHERE h.status = 'submitted'
    ORDER BY h.submitted_at ASC
");
$stmt->execute();
$pendingHouseholds = $stmt->fetchAll();

// Get verified and rejected for history
$stmt = $pdo->prepare("
    SELECT h.*, u.username as enumerator_name,
           (SELECT COUNT(*) FROM household_members WHERE household_id = h.id) as member_count
    FROM households h
    LEFT JOIN users u ON h.enumerator_id = u.id
    WHERE h.status IN ('verified', 'rejected')
    ORDER BY h.updated_at DESC
    LIMIT 20
");
$stmt->execute();
$history = $stmt->fetchAll();

// Functions
function verifyHousehold($householdId, $notes) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE households 
            SET status = 'verified', verified_at = NOW(), updated_at = NOW()
            WHERE id = ? AND status = 'submitted'
        ");
        $stmt->execute([$householdId]);
        logActivity($_SESSION['user_id'], 'verify_household', "Verified household ID: $householdId" . ($notes ? " - Notes: $notes" : ""));
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
        logActivity($_SESSION['user_id'], 'reject_household', "Rejected household ID: $householdId - Reason: $reason");
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
    <title>Verifications - Delta Census</title>
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
        
        .admin-nav { background: white; border-radius: 8px; padding: 12px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .admin-nav .btn { padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s ease; }
        
        .verification-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #2563eb; }
        .verification-card .header { display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 12px; }
        .verification-card .code { font-weight: 700; font-size: 18px; color: #2563eb; }
        .verification-card .info { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 12px 0; }
        .verification-card .info-item { font-size: 14px; }
        .verification-card .info-item strong { color: #64748b; }
        .verification-card .actions { display: flex; gap: 12px; margin-top: 12px; flex-wrap: wrap; }
        .verification-card .notes { margin-top: 12px; }
        .verification-card .notes textarea { width: 100%; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; min-height: 60px; font-size: 14px; }
        .verification-card .notes textarea:focus { outline: none; border-color: #2563eb; }
        .verification-card.verified { border-left-color: #22c55e; }
        .verification-card.rejected { border-left-color: #ef4444; }
        
        .status-badge { padding: 4px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .status-badge.submitted { background: #dbeafe; color: #1e40af; }
        .status-badge.verified { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.draft { background: #fef3c7; color: #92400e; }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .text-muted { color: #64748b; font-size: 13px; }
        .text-center { text-align: center; }
        
        @media (max-width: 768px) {
            .verification-card .info { grid-template-columns: 1fr; }
            .admin-nav { flex-direction: column; align-items: stretch; }
            .admin-nav .btn { text-align: center; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="mobile-nav">
            <div class="nav-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <img src="images/logodelta.png" alt="Logo" style="height: 35px; width: auto;">
                    <h2 style="font-size: 20px;"> Verification Management</h2>
                </div>
                <div class="user-menu">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                    <a href="admin_dashboard.php" class="btn-sm btn-secondary">Dashboard</a>
                    <a href="logout.php" class="btn-sm btn-danger">Logout</a>
                </div>
            </div>
        </nav>

        <div class="admin-nav">
            <span class="nav-label">📋 Menu:</span>
            <a href="admin_dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="admin_households.php" class="btn btn-secondary">🏠 Households</a>
            <a href="admin_verifications.php" class="btn btn-primary">✅ Verifications</a>
            <a href="admin_reports.php" class="btn btn-secondary">📊 Reports</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Pending Verifications -->
        <h3>⏳ Pending Verifications (<?php echo count($pendingHouseholds); ?>)</h3>
        <?php if (count($pendingHouseholds) > 0): ?>
            <?php foreach ($pendingHouseholds as $household): ?>
                <div class="verification-card">
                    <div class="header">
                        <div>
                            <span class="code"><?php echo htmlspecialchars($household['household_code'] ?? $household['household_number']); ?></span>
                            <span class="status-badge submitted">Submitted</span>
                        </div>
                        <span style="font-size: 14px; color: #64748b;">
                            <?php echo date('M d, Y H:i', strtotime($household['submitted_at'])); ?>
                        </span>
                    </div>
                    
                    <div class="info">
                        <div class="info-item"><strong>Head:</strong> <?php echo htmlspecialchars($household['head_of_household'] ?? $household['household_head']); ?></div>
                        <div class="info-item"><strong>Community:</strong> <?php echo htmlspecialchars($household['community']); ?></div>
                        <div class="info-item"><strong>LGA:</strong> <?php echo htmlspecialchars($household['lga']); ?></div>
                        <div class="info-item"><strong>Ward:</strong> <?php echo htmlspecialchars($household['ward']); ?></div>
                        <div class="info-item"><strong>Members:</strong> <?php echo $household['member_count'] ?? 0; ?></div>
                        <div class="info-item"><strong>Enumerator:</strong> <?php echo htmlspecialchars($household['enumerator_name'] ?? 'Unknown'); ?></div>
                    </div>
                    
                    <form method="POST" action="" style="margin-top: 12px;">
                        <input type="hidden" name="household_id" value="<?php echo $household['id']; ?>">
                        <div class="notes">
                            <textarea name="notes" placeholder="Add verification notes or rejection reason..."></textarea>
                        </div>
                        <div class="actions">
                            <button type="submit" name="action" value="verify" class="btn btn-success" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">✅ Verify</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">❌ Reject</button>
                            <a href="admin_households.php?action=view&id=<?php echo $household['id']; ?>" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 4px; text-decoration: none;">View Details</a>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="background: white; border-radius: 8px; padding: 40px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
                <h3>No Pending Verifications</h3>
                <p class="text-muted">All submitted households have been reviewed.</p>
            </div>
        <?php endif; ?>

        <!-- Recent History -->
        <h3 style="margin-top: 32px;">📋 Recent History</h3>
        <?php if (count($history) > 0): ?>
            <?php foreach ($history as $household): ?>
                <div class="verification-card <?php echo $household['status']; ?>">
                    <div class="header">
                        <div>
                            <span class="code"><?php echo htmlspecialchars($household['household_code'] ?? $household['household_number']); ?></span>
                            <span class="status-badge <?php echo $household['status']; ?>"><?php echo ucfirst($household['status']); ?></span>
                        </div>
                        <span style="font-size: 14px; color: #64748b;">
                            <?php echo date('M d, Y', strtotime($household['updated_at'])); ?>
                        </span>
                    </div>
                    <div class="info">
                        <div class="info-item"><strong>Head:</strong> <?php echo htmlspecialchars($household['head_of_household'] ?? $household['household_head']); ?></div>
                        <div class="info-item"><strong>Community:</strong> <?php echo htmlspecialchars($household['community']); ?></div>
                        <div class="info-item"><strong>Enumerator:</strong> <?php echo htmlspecialchars($household['enumerator_name'] ?? 'Unknown'); ?></div>
                        <div class="info-item"><strong>Members:</strong> <?php echo $household['member_count'] ?? 0; ?></div>
                    </div>
                    <a href="admin_households.php?action=view&id=<?php echo $household['id']; ?>" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 4px; text-decoration: none; display: inline-block; margin-top: 8px;">View Details</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No verification history yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>