<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

global $pdo;

// Get export type
$exportType = isset($_GET['type']) ? $_GET['type'] : 'households';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// If export is requested, generate file
if (isset($_GET['download'])) {
    $exportType = $_GET['download'];
    $format = $_GET['format'] ?? 'csv';
    generateExport($exportType, $format);
    exit();
}

function generateExport($type, $format) {
    global $pdo;
    
    // Get data
    switch ($type) {
        case 'households':
            $data = $pdo->query("
                SELECT 
                    h.household_code as 'Household Code',
                    h.head_of_household as 'Head of Household',
                    h.phone as 'Phone',
                    h.lga as 'LGA',
                    h.ward as 'Ward',
                    h.community as 'Community',
                    h.enumeration_area as 'Enumeration Area',
                    h.address as 'Address',
                    h.building_type as 'Building Type',
                    h.house_ownership as 'House Ownership',
                    h.number_of_rooms as 'Rooms',
                    h.number_of_households as 'Households in Building',
                    h.status as 'Status',
                    DATE_FORMAT(h.created_at, '%Y-%m-%d') as 'Created Date',
                    u.username as 'Enumerator'
                FROM households h
                LEFT JOIN users u ON h.enumerator_id = u.id
                ORDER BY h.created_at DESC
            ")->fetchAll();
            $filename = 'households_' . date('Y-m-d');
            break;
            
        case 'members':
            $data = $pdo->query("
                SELECT 
                    h.household_code as 'Household Code',
                    m.surname as 'Surname',
                    m.first_name as 'First Name',
                    m.other_name as 'Other Name',
                    m.gender as 'Gender',
                    m.age as 'Age',
                    m.relationship as 'Relationship',
                    m.marital_status as 'Marital Status',
                    m.nationality as 'Nationality',
                    m.state_of_origin as 'State of Origin',
                    m.lga_of_origin as 'LGA of Origin',
                    m.highest_qualification as 'Highest Qualification',
                    m.employment_status as 'Employment Status',
                    m.occupation as 'Occupation',
                    m.disability as 'Disability',
                    m.health_insurance as 'Health Insurance'
                FROM household_members m
                LEFT JOIN households h ON m.household_id = h.id
                ORDER BY h.household_code, m.is_head DESC
            ")->fetchAll();
            $filename = 'members_' . date('Y-m-d');
            break;
            
        case 'summary':
            $data = $pdo->query("
                SELECT 
                    h.lga as 'LGA',
                    COUNT(DISTINCT h.id) as 'Households',
                    COUNT(m.id) as 'Total Members',
                    AVG(m.age) as 'Average Age',
                    SUM(CASE WHEN m.gender = 'Male' THEN 1 ELSE 0 END) as 'Males',
                    SUM(CASE WHEN m.gender = 'Female' THEN 1 ELSE 0 END) as 'Females',
                    SUM(CASE WHEN h.status = 'verified' THEN 1 ELSE 0 END) as 'Verified'
                FROM households h
                LEFT JOIN household_members m ON h.id = m.household_id
                GROUP BY h.lga
                ORDER BY h.lga
            ")->fetchAll();
            $filename = 'summary_' . date('Y-m-d');
            break;
            
        default:
            $data = [];
            $filename = 'export_' . date('Y-m-d');
    }
    
    if (empty($data)) {
        die('No data to export');
    }
    
    // Generate CSV
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel
        fputs($output, "\xEF\xBB\xBF");
        
        // Headers
        if (!empty($data)) {
            fputcsv($output, array_keys((array)$data[0]));
        }
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, (array)$row);
        }
        
        fclose($output);
        exit();
    }
    
    // Generate Excel (simple HTML table with xls header)
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Data</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
        echo '<body><table>';
        
        // Headers
        if (!empty($data)) {
            echo '<tr>';
            foreach (array_keys((array)$data[0]) as $header) {
                echo '<th style="font-weight:bold;background:#f1f5f9;">' . htmlspecialchars($header) . '</th>';
            }
            echo '</tr>';
        }
        
        // Data
        foreach ($data as $row) {
            echo '<tr>';
            foreach ((array)$row as $value) {
                echo '<td>' . htmlspecialchars($value) . '</td>';
            }
            echo '</tr>';
        }
        
        echo '</table></body></html>';
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Data Export - Delta Census</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .app-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
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
        
        .export-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-top: 20px; }
        .export-card { background: white; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; transition: all 0.3s ease; }
        .export-card:hover { transform: translateY(-4px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .export-card .icon { font-size: 48px; margin-bottom: 12px; }
        .export-card h3 { margin-bottom: 8px; }
        .export-card p { color: #64748b; font-size: 14px; margin-bottom: 16px; }
        .export-card .btn-group { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .export-card .btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; border: none; cursor: pointer; }
        
        @media (max-width: 768px) {
            .admin-nav { flex-direction: column; align-items: stretch; }
            .admin-nav .btn { text-align: center; }
            .export-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <nav class="mobile-nav">
            <div class="nav-header">
                <h2 style="font-size: 20px;">📤 Data Export</h2>
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
            <a href="admin_reports.php" class="btn btn-secondary">📊 Reports</a>
            <a href="admin_export.php" class="btn btn-primary">📤 Export</a>
        </div>

        <h3 style="margin-bottom: 8px;">📤 Export Data</h3>
        <p style="color: #64748b; margin-bottom: 20px;">Export census data in various formats for analysis and reporting.</p>

        <div class="export-grid">
            <!-- Households Export -->
            <div class="export-card">
                <div class="icon">🏠</div>
                <h3>Households</h3>
                <p>Export all household records with complete details.</p>
                <div class="btn-group">
                    <a href="admin_export.php?download=households&format=csv" class="btn btn-success">📄 CSV</a>
                    <a href="admin_export.php?download=households&format=excel" class="btn btn-primary">📊 Excel</a>
                </div>
            </div>

            <!-- Members Export -->
            <div class="export-card">
                <div class="icon">👥</div>
                <h3>Members</h3>
                <p>Export all household members with demographic data.</p>
                <div class="btn-group">
                    <a href="admin_export.php?download=members&format=csv" class="btn btn-success">📄 CSV</a>
                    <a href="admin_export.php?download=members&format=excel" class="btn btn-primary">📊 Excel</a>
                </div>
            </div>

            <!-- Summary Export -->
            <div class="export-card">
                <div class="icon">📊</div>
                <h3>Summary Report</h3>
                <p>Export summary statistics by LGA.</p>
                <div class="btn-group">
                    <a href="admin_export.php?download=summary&format=csv" class="btn btn-success">📄 CSV</a>
                    <a href="admin_export.php?download=summary&format=excel" class="btn btn-primary">📊 Excel</a>
                </div>
            </div>
        </div>

        <div style="background: #f8fafc; border-radius: 8px; padding: 16px; margin-top: 24px; border: 1px solid #e2e8f0;">
            <h4 style="margin-bottom: 8px;">💡 Tips</h4>
            <ul style="color: #64748b; font-size: 14px; padding-left: 20px;">
                <li><strong>CSV</strong> - Compatible with most spreadsheet applications including Microsoft Excel, Google Sheets, and LibreOffice.</li>
                <li><strong>Excel</strong> - Native Excel format with better formatting support for complex data.</li>
                <li>Reports include all verified and submitted households. Draft households are excluded.</li>
                <li>Large datasets may take a few seconds to generate.</li>
            </ul>
        </div>
    </div>
</body>
</html>