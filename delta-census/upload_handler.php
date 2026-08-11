<?php
/**
 * File upload handler for passport photographs
 * This file handles the actual upload process and returns JSON response
 */

require_once 'includes/auth.php';
require_once 'config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['photo'];
$userId = $_SESSION['user_id'];

// Validate file
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit();
}

if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large (max 2MB)']);
    exit();
}

// Create upload directory
$uploadDir = __DIR__ . '/uploads/passports/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'passport_' . $userId . '_' . time() . '.' . $extension;
$filePath = $uploadDir . $filename;

// Move file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

// Save to database
$relativePath = 'uploads/passports/' . $filename;
$stmt = $pdo->prepare("
    INSERT INTO uploads (user_id, file_name, file_path, file_type, file_size) 
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$userId, $file['name'], $relativePath, $mimeType, $file['size']]);

// Update user's passport photo
$stmt = $pdo->prepare("UPDATE users SET passport_photo = ? WHERE id = ?");
$stmt->execute([$relativePath, $userId]);

echo json_encode([
    'success' => true,
    'message' => 'Photo uploaded successfully',
    'path' => $relativePath
]);
?>