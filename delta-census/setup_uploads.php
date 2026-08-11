<?php
// Run this once to set up the upload directory with proper permissions
$uploadDir = __DIR__ . '/uploads/passports/';

if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0777, true)) {
        echo "Upload directory created successfully!<br>";
    } else {
        echo "Failed to create upload directory!<br>";
    }
} else {
    echo "Upload directory already exists.<br>";
}

// Check if directory is writable
if (is_writable($uploadDir)) {
    echo "Directory is writable.<br>";
} else {
    echo "Directory is NOT writable. Please set permissions.<br>";
    echo "On Windows, right-click the 'uploads' folder > Properties > Security > Edit > Give Full Control to 'Everyone' or your user account.<br>";
}

// Create .htaccess file
$htaccess = __DIR__ . '/uploads/.htaccess';
if (!file_exists($htaccess)) {
    $content = "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|cgi)$\">\n";
    $content .= "    Order Deny,Allow\n";
    $content .= "    Deny from all\n";
    $content .= "</FilesMatch>\n\n";
    $content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">\n";
    $content .= "    Order Allow,Deny\n";
    $content .= "    Allow from all\n";
    $content .= "</FilesMatch>\n";
    
    file_put_contents($htaccess, $content);
    echo ".htaccess file created.<br>";
}

echo "<br>Setup complete. <a href='admin_users.php'>Go to Admin Users</a>";
?>