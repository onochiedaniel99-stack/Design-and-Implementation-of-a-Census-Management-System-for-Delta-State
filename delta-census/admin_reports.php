<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

global $pdo;

// Get report type
$reportType = isset($_GET['type']) ? $_GET['type'] : 'population';
$lgaFilter = isset($_GET['lga']) ? trim($_GET['lga']) : '';
$wardFilter = isset($_GET['ward']) ? trim($_GET['ward']) : '';

// Get LGAs for filter
$lgas = $pdo->query("SELECT DISTINCT lga FROM households ORDER BY lga")->fetchAll();
$wards = $pdo->query("SELECT DISTINCT ward FROM households ORDER BY ward")->fetchAll();

// Build where clause for filters
$where = "WHERE 1=1";
$params = [];
if (!empty($lgaFilter)) {
    $where .= " AND h.lga = ?";
    $params[] = $lgaFilter;
}
if (!empty($wardFilter)) {
    $where .= " AND h.ward = ?";
    $params[] = $wardFilter;
}

// Function to execute report queries
function getReportData($query, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get chart data for each report type
function getChartData($reportType, $where, $params) {
    global $pdo;
    
    switch ($reportType) {
        case 'population':
            $query = "
                SELECT 
                    h.lga as label,
                    COUNT(DISTINCT h.id) as households,
                    COUNT(m.id) as members,
                    AVG(m.age) as avg_age
                FROM households h
                LEFT JOIN household_members m ON h.id = m.household_id
                $where
                GROUP BY h.lga
                ORDER BY members DESC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            return [
                'labels' => array_column($data, 'label'),
                'datasets' => [
                    [
                        'label' => 'Households',
                        'data' => array_column($data, 'households'),
                        'backgroundColor' => 'rgba(37, 99, 235, 0.6)',
                        'borderColor' => '#2563eb',
                        'borderWidth' => 2
                    ],
                    [
                        'label' => 'Members',
                        'data' => array_column($data, 'members'),
                        'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                        'borderColor' => '#22c55e',
                        'borderWidth' => 2
                    ]
                ]
            ];
            
        case 'gender':
            $query = "
                SELECT 
                    m.gender as label,
                    COUNT(m.id) as count
                FROM households h
                LEFT JOIN household_members m ON h.id = m.household_id
                $where
                AND m.gender IS NOT NULL
                GROUP BY m.gender
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            $colors = [
                'Male' => 'rgba(37, 99, 235, 0.8)',
                'Female' => 'rgba(236, 72, 153, 0.8)'
            ];
            
            return [
                'labels' => array_column($data, 'label'),
                'datasets' => [
                    [
                        'data' => array_column($data, 'count'),
                        'backgroundColor' => array_map(function($label) use ($colors) {
                            return $colors[$label] ?? 'rgba(100, 116, 139, 0.8)';
                        }, array_column($data, 'label')),
                        'borderWidth' => 2
                    ]
                ]
            ];
            
        case 'age':
            $query = "
                SELECT 
                    CASE 
                        WHEN m.age BETWEEN 0 AND 5 THEN '0-5'
                        WHEN m.age BETWEEN 6 AND 12 THEN '6-12'
                        WHEN m.age BETWEEN 13 AND 18 THEN '13-18'
                        WHEN m.age BETWEEN 19 AND 30 THEN '19-30'
                        WHEN m.age BETWEEN 31 AND 45 THEN '31-45'
                        WHEN m.age BETWEEN 46 AND 60 THEN '46-60'
                        WHEN m.age > 60 THEN '60+'
                        ELSE 'Unknown'
                    END as label,
                    COUNT(m.id) as count
                FROM households h
                LEFT JOIN household_members m ON h.id = m.household_id
                $where
                AND m.age IS NOT NULL
                GROUP BY label
                ORDER BY FIELD(label, '0-5', '6-12', '13-18', '19-30', '31-45', '46-60', '60+', 'Unknown')
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            $colors = [
                '0-5' => 'rgba(52, 211, 153, 0.8)',
                '6-12' => 'rgba(52, 211, 153, 0.7)',
                '13-18' => 'rgba(96, 165, 250, 0.8)',
                '19-30' => 'rgba(96, 165, 250, 0.7)',
                '31-45' => 'rgba(251, 191, 36, 0.8)',
                '46-60' => 'rgba(251, 191, 36, 0.7)',
                '60+' => 'rgba(248, 113, 113, 0.8)',
                'Unknown' => 'rgba(148, 163, 184, 0.8)'
            ];
            
            return [
                'labels' => array_column($data, 'label'),
                'datasets' => [
                    [
                        'label' => 'Population by Age Group',
                        'data' => array_column($data, 'count'),
                        'backgroundColor' => array_map(function($label) use ($colors) {
                            return $colors[$label] ?? 'rgba(100, 116, 139, 0.8)';
                        }, array_column($data, 'label')),
                        'borderWidth' => 2,
                        'borderColor' => '#ffffff'
                    ]
                ]
            ];
            
        case 'education':
            $query = "
                SELECT 
                    m.highest_qualification as label,
                    COUNT(m.id) as count
                FROM households h
                LEFT JOIN household_members m ON h.id = m.household_id
                $where
                AND m.highest_qualification IS NOT NULL
                GROUP BY m.highest_qualification
                ORDER BY count DESC
                LIMIT 10
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            return [
                'labels' => array_column($data, 'label'),
                'datasets' => [
                    [
                        'label' => 'Education Level',
                        'data' => array_column($data, 'count'),
                        'backgroundColor' => [
                            'rgba(37, 99, 235, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(96, 165, 250, 0.8)',
                            'rgba(147, 197, 253, 0.8)',
                            'rgba(191, 219, 254, 0.8)',
                            'rgba(219, 234, 254, 0.8)'
                        ],
                        'borderWidth' => 2,
                        'borderColor' => '#ffffff'
                    ]
                ]
            ];
            
        case 'employment':
            $query = "
                SELECT 
                    m.employment_status as label,
                    COUNT(m.id) as count
                FROM households h
                LEFT JOIN household_members m ON h.id = m.household_id
                $where
                AND m.employment_status IS NOT NULL
                GROUP BY m.employment_status
                ORDER BY count DESC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            $colors = [
                'Employed' => 'rgba(34, 197, 94, 0.8)',
                'Self-employed' => 'rgba(59, 130, 246, 0.8)',
                'Unemployed' => 'rgba(239, 68, 68, 0.8)',
                'Student' => 'rgba(251, 191, 36, 0.8)',
                'Retired' => 'rgba(148, 163, 184, 0.8)'
            ];
            
            return [
                'labels' => array_column($data, 'label'),
                'datasets' => [
                    [
                        'label' => 'Employment Status',
                        'data' => array_column($data, 'count'),
                        'backgroundColor' => array_map(function($label) use ($colors) {
                            return $colors[$label] ?? 'rgba(100, 116, 139, 0.8)';
                        }, array_column($data, 'label')),
                        'borderWidth' => 2
                    ]
                ]
            ];
            
        case 'disability':
            $query = "
                SELECT 
                    m.disability_type as label,
                    COUNT(m.id) as count
                FROM households h
                LEFT JOIN household_members m ON h.id = m.household_id
                $where
                AND m.disability = 'Yes'
                AND m.disability_type IS NOT NULL
                GROUP BY m.disability_type
                ORDER BY count DESC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            return [
                'labels' => array_column($data, 'label'),
                'datasets' => [
                    [
                        'label' => 'Disability Types',
                        'data' => array_column($data, 'count'),
                        'backgroundColor' => [
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(249, 115, 22, 0.8)',
                            'rgba(251, 191, 36, 0.8)',
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(37, 99, 235, 0.8)',
                            'rgba(139, 92, 246, 0.8)'
                        ],
                        'borderWidth' => 2
                    ]
                ]
            ];
            
        case 'housing':
            $query = "
                SELECT 
                    h.building_type as label,
                    COUNT(h.id) as count
                FROM households h
                $where
                AND h.building_type IS NOT NULL
                GROUP BY h.building_type
                ORDER BY count DESC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            return [
                'labels' => array_column($data, 'label'),
                'datasets' => [
                    [
                        'label' => 'Building Types',
                        'data' => array_column($data, 'count'),
                        'backgroundColor' => [
                            'rgba(37, 99, 235, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(96, 165, 250, 0.8)',
                            'rgba(147, 197, 253, 0.8)',
                            'rgba(191, 219, 254, 0.8)'
                        ],
                        'borderWidth' => 2
                    ]
                ]
            ];
            
        default:
            return ['labels' => [], 'datasets' => []];
    }
}

// Report data
$reportData = [];
$reportTitle = '';

switch ($reportType) {
    case 'population':
        $reportTitle = 'Population by LGA';
        $query = "
            SELECT 
                h.lga,
                COUNT(DISTINCT h.id) as households,
                COUNT(m.id) as total_members,
                ROUND(AVG(m.age), 1) as avg_age
            FROM households h
            LEFT JOIN household_members m ON h.id = m.household_id
            $where
            GROUP BY h.lga
            ORDER BY total_members DESC
        ";
        $reportData = getReportData($query, $params);
        break;
        
    case 'gender':
        $reportTitle = 'Population by Gender';
        $query = "
            SELECT 
                m.gender,
                COUNT(m.id) as count
            FROM households h
            LEFT JOIN household_members m ON h.id = m.household_id
            $where
            AND m.gender IS NOT NULL
            GROUP BY m.gender
        ";
        $reportData = getReportData($query, $params);
        break;
        
    case 'age':
        $reportTitle = 'Population by Age Group';
        $query = "
            SELECT 
                CASE 
                    WHEN m.age BETWEEN 0 AND 5 THEN '0-5'
                    WHEN m.age BETWEEN 6 AND 12 THEN '6-12'
                    WHEN m.age BETWEEN 13 AND 18 THEN '13-18'
                    WHEN m.age BETWEEN 19 AND 30 THEN '19-30'
                    WHEN m.age BETWEEN 31 AND 45 THEN '31-45'
                    WHEN m.age BETWEEN 46 AND 60 THEN '46-60'
                    WHEN m.age > 60 THEN '60+'
                    ELSE 'Unknown'
                END as age_group,
                COUNT(m.id) as count
            FROM households h
            LEFT JOIN household_members m ON h.id = m.household_id
            $where
            AND m.age IS NOT NULL
            GROUP BY age_group
            ORDER BY FIELD(age_group, '0-5', '6-12', '13-18', '19-30', '31-45', '46-60', '60+', 'Unknown')
        ";
        $reportData = getReportData($query, $params);
        break;
        
    case 'education':
        $reportTitle = 'Education Statistics';
        $query = "
            SELECT 
                m.highest_qualification as education_level,
                COUNT(m.id) as count
            FROM households h
            LEFT JOIN household_members m ON h.id = m.household_id
            $where
            AND m.highest_qualification IS NOT NULL
            GROUP BY m.highest_qualification
            ORDER BY count DESC
            LIMIT 10
        ";
        $reportData = getReportData($query, $params);
        break;
        
    case 'employment':
        $reportTitle = 'Employment Statistics';
        $query = "
            SELECT 
                m.employment_status,
                COUNT(m.id) as count
            FROM households h
            LEFT JOIN household_members m ON h.id = m.household_id
            $where
            AND m.employment_status IS NOT NULL
            GROUP BY m.employment_status
            ORDER BY count DESC
        ";
        $reportData = getReportData($query, $params);
        break;
        
    case 'disability':
        $reportTitle = 'Disability Statistics';
        $query = "
            SELECT 
                m.disability_type,
                COUNT(m.id) as count
            FROM households h
            LEFT JOIN household_members m ON h.id = m.household_id
            $where
            AND m.disability = 'Yes'
            AND m.disability_type IS NOT NULL
            GROUP BY m.disability_type
            ORDER BY count DESC
        ";
        $reportData = getReportData($query, $params);
        break;
        
    case 'housing':
        $reportTitle = 'Housing Statistics';
        $query = "
            SELECT 
                h.building_type,
                h.house_ownership,
                COUNT(h.id) as count,
                ROUND(AVG(h.number_of_rooms), 1) as avg_rooms,
                ROUND(AVG(h.number_of_households), 1) as avg_households
            FROM households h
            $where
            GROUP BY h.building_type, h.house_ownership
            ORDER BY count DESC
        ";
        $reportData = getReportData($query, $params);
        break;
        
    default:
        $reportTitle = 'Population by LGA';
        $query = "
            SELECT 
                h.lga,
                COUNT(DISTINCT h.id) as households,
                COUNT(m.id) as total_members,
                ROUND(AVG(m.age), 1) as avg_age
            FROM households h
            LEFT JOIN household_members m ON h.id = m.household_id
            $where
            GROUP BY h.lga
            ORDER BY total_members DESC
        ";
        $reportData = getReportData($query, $params);
}

// Get chart data
$chartData = getChartData($reportType, $where, $params);
$chartType = 'bar'; // default

// Determine chart type
switch ($reportType) {
    case 'gender':
    case 'disability':
    case 'housing':
        $chartType = 'doughnut';
        break;
    case 'age':
    case 'education':
    case 'employment':
        $chartType = 'bar';
        break;
    case 'population':
        $chartType = 'bar';
        break;
    default:
        $chartType = 'bar';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Reports - Delta Census</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .app-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .mobile-nav { background: white; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .nav-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .user-menu { display: flex; align-items: center; gap: 12px; }
        .btn-sm { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; }
        .btn-primary { background: #2563eb; color: white; border: none; cursor: pointer; }
        .btn-secondary { background: #64748b; color: white; border: none; cursor: pointer; }
        .btn-success { background: #22c55e; color: white; border: none; cursor: pointer; }
        .btn-warning { background: #f59e0b; color: white; border: none; cursor: pointer; }
        
        .admin-nav { background: white; border-radius: 8px; padding: 12px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .admin-nav .btn { padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s ease; }
        .admin-nav .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-nav .btn-primary { background: #2563eb; color: white; }
        .admin-nav .btn-secondary { background: #64748b; color: white; }
        .admin-nav .btn-success { background: #22c55e; color: white; }
        .admin-nav .btn-warning { background: #f59e0b; color: white; }
        .admin-nav .btn-danger { background: #ef4444; color: white; }
        
        .report-grid { display: grid; grid-template-columns: 220px 1fr; gap: 24px; }
        .report-sidebar { background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: sticky; top: 20px; max-height: calc(100vh - 100px); overflow-y: auto; }
        .report-sidebar h4 { margin-bottom: 12px; color: #64748b; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .report-sidebar a { display: block; padding: 8px 12px; border-radius: 4px; text-decoration: none; color: #0f172a; margin-bottom: 4px; transition: all 0.2s ease; font-size: 14px; }
        .report-sidebar a:hover { background: #f1f5f9; }
        .report-sidebar a.active { background: #2563eb; color: white; }
        .report-sidebar a .icon { margin-right: 8px; }
        
        .report-content { background: white; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .report-content .header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .report-content .header h3 { margin: 0; font-size: 22px; }
        
        .report-filters { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; padding: 16px; background: #f8fafc; border-radius: 8px; }
        .report-filters select { padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; min-width: 150px; }
        .report-filters select:focus { outline: none; border-color: #2563eb; }
        .report-filters .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 14px; }
        
        .chart-container { 
            position: relative; 
            height: 400px; 
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .chart-container canvas { width: 100% !important; height: 100% !important; }
        
        .report-table-wrapper { margin-top: 30px; border-top: 2px solid #e2e8f0; padding-top: 24px; }
        .report-table-wrapper h4 { margin-bottom: 12px; color: #64748b; }
        .report-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .report-table th { background: #f1f5f9; padding: 10px 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        .report-table td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .report-table tr:hover { background: #f8fafc; }
        
        .stats-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: #f8fafc; border-radius: 8px; padding: 16px; text-align: center; border: 1px solid #e2e8f0; }
        .stat-box .number { font-size: 24px; font-weight: 700; color: #2563eb; }
        .stat-box .label { font-size: 13px; color: #64748b; margin-top: 4px; }
        
        .text-muted { color: #64748b; }
        .text-center { text-align: center; }
        
        @media (max-width: 1024px) {
            .report-grid { grid-template-columns: 1fr; }
            .report-sidebar { position: relative; top: 0; max-height: none; display: flex; flex-wrap: wrap; gap: 4px; padding: 12px; }
            .report-sidebar h4 { display: none; }
            .report-sidebar a { display: inline-block; padding: 6px 12px; font-size: 13px; margin-bottom: 0; }
        }
        
        @media (max-width: 768px) {
            .admin-nav { flex-direction: column; align-items: stretch; }
            .admin-nav .btn { text-align: center; }
            .report-filters { flex-direction: column; }
            .report-filters select { width: 100%; }
            .chart-container { height: 300px; padding: 10px; }
            .stats-summary { grid-template-columns: 1fr 1fr; }
            .report-content .header { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2 style="font-size: 20px;">📊 Reports & Analytics</h2>
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
            <a href="admin_verifications.php" class="btn btn-secondary">✅ Verifications</a>
            <a href="admin_reports.php" class="btn btn-primary">📊 Reports</a>
            <a href="admin_export.php" class="btn btn-success">📤 Export</a>
        </div>

        <div class="report-grid">
            <!-- Sidebar -->
            <div class="report-sidebar">
                <h4>📋 Report Types</h4>
                <a href="admin_reports.php?type=population" class="<?php echo $reportType === 'population' ? 'active' : ''; ?>">
                    <span class="icon">👥</span> Population by LGA
                </a>
                <a href="admin_reports.php?type=gender" class="<?php echo $reportType === 'gender' ? 'active' : ''; ?>">
                    <span class="icon">⚧️</span> Population by Gender
                </a>
                <a href="admin_reports.php?type=age" class="<?php echo $reportType === 'age' ? 'active' : ''; ?>">
                    <span class="icon">📅</span> Age Distribution
                </a>
                <a href="admin_reports.php?type=education" class="<?php echo $reportType === 'education' ? 'active' : ''; ?>">
                    <span class="icon">🎓</span> Education Statistics
                </a>
                <a href="admin_reports.php?type=employment" class="<?php echo $reportType === 'employment' ? 'active' : ''; ?>">
                    <span class="icon">💼</span> Employment Statistics
                </a>
                <a href="admin_reports.php?type=disability" class="<?php echo $reportType === 'disability' ? 'active' : ''; ?>">
                    <span class="icon">🏥</span> Disability Statistics
                </a>
                <a href="admin_reports.php?type=housing" class="<?php echo $reportType === 'housing' ? 'active' : ''; ?>">
                    <span class="icon">🏠</span> Housing Statistics
                </a>
            </div>

            <!-- Report Content -->
            <div class="report-content">
                <div class="header">
                    <h3><?php echo $reportTitle; ?></h3>
                    <div>
                        <a href="admin_export.php?type=<?php echo $reportType; ?>" class="btn btn-success" style="padding: 8px 16px; border-radius: 4px; text-decoration: none;">📤 Export</a>
                        <button onclick="window.print()" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 4px; border: none; cursor: pointer;">🖨️ Print</button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="report-filters">
                    <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; width: 100%;">
                        <input type="hidden" name="type" value="<?php echo $reportType; ?>">
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
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Apply Filters</button>
                        <a href="admin_reports.php?type=<?php echo $reportType; ?>" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 4px; text-decoration: none;">Clear</a>
                    </form>
                </div>

                <!-- Chart -->
                <?php if (!empty($chartData['labels']) && !empty($chartData['datasets'])): ?>
                    <div class="chart-container">
                        <canvas id="reportChart"></canvas>
                    </div>
                <?php endif; ?>

                <!-- Report Table -->
                <?php if (count($reportData) > 0): ?>
                    <div class="report-table-wrapper">
                        <h4>📋 Data Table</h4>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <?php 
                                    $headers = array_keys((array)$reportData[0]);
                                    foreach ($headers as $header): 
                                        $label = str_replace('_', ' ', $header);
                                        $label = ucwords($label);
                                    ?>
                                        <th><?php echo $label; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ($reportData as $row): 
                                    $rowArray = (array)$row;
                                ?>
                                    <tr>
                                        <?php foreach ($rowArray as $key => $value): ?>
                                            <td>
                                                <?php 
                                                if (is_numeric($value) && strpos($key, 'count') !== false) {
                                                    echo number_format($value);
                                                } elseif (is_numeric($value) && strpos($key, 'avg') !== false) {
                                                    echo $value;
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="text-muted" style="margin-top: 12px; font-size: 13px;">
                            Total Records: <?php echo count($reportData); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center" style="padding: 40px;">
                        <div style="font-size: 48px; margin-bottom: 16px;">📊</div>
                        <h3>No Data Available</h3>
                        <p class="text-muted">No data found for the selected filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart data from PHP
            const chartData = <?php echo json_encode($chartData); ?>;
            const chartType = '<?php echo $chartType; ?>';
            
            if (chartData.labels && chartData.labels.length > 0) {
                const ctx = document.getElementById('reportChart').getContext('2d');
                
                // Check if chart already exists and destroy it
                if (window.myChart) {
                    window.myChart.destroy();
                }
                
                const config = {
                    type: chartType,
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 12,
                                        weight: '500'
                                    },
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            }
                        },
                        scales: chartType === 'doughnut' ? {} : {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                };
                
                // Special configuration for different chart types
                if (chartType === 'doughnut') {
                    config.options.plugins.legend.position = 'right';
                }
                
                if (chartType === 'bar') {
                    config.options.scales = {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    };
                }
                
                window.myChart = new Chart(ctx, config);
            }
        });
    </script>
</body>
</html>