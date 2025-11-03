<?php
// Start session before ANY output
session_start();

// Start output buffering
ob_start();

// Include Auth and Database
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Database.php';

// Function to send JSON and exit cleanly
function sendJson($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Check authentication
ensureSession();
if (!isLoggedIn()) {
    sendJson(['success' => false, 'message' => 'Not authenticated']);
}

// Check method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['success' => false, 'message' => 'Invalid request method']);
}

// Get parameters
$itemType = $_POST['item_type'] ?? '';
$itemId = intval($_POST['item_id'] ?? 0);

if (empty($itemType) || $itemId <= 0) {
    sendJson(['success' => false, 'message' => 'Invalid parameters']);
}

// Check if user has edit permission
if (!canEdit($itemType, $itemId)) {
    sendJson(['success' => false, 'message' => 'You do not have permission to edit this item']);
}

// Validate item type
$validTypes = ['map', 'dashboard', 'document', 'html_page'];
if (!in_array($itemType, $validTypes)) {
    sendJson(['success' => false, 'message' => 'Invalid item type']);
}

// Check file upload
if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
    sendJson(['success' => false, 'message' => 'No file uploaded or upload error']);
}

// Validate file type - use extension like documents.php does
$file = $_FILES['thumbnail'];
$originalFilename = basename($file['name']);
$extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($extension, $allowedExtensions)) {
    sendJson(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.']);
}

// Validate file size (max 5MB)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    sendJson(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
}

// Create thumbnails directory
$uploadDir = 'assets/thumbnails';

// Generate unique filename
$filename = $itemType . '_' . $itemId . '_' . uniqid() . '.' . $extension;
$uploadPath = $uploadDir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    sendJson(['success' => false, 'message' => 'Failed to save file']);
}

// Update database
$relativePath = 'assets/thumbnails/' . $filename;

try {
    switch ($itemType) {
        case 'map':
            updateMapThumbnail($itemId, $relativePath);
            break;
        case 'dashboard':
            updateDashboardThumbnail($itemId, $relativePath);
            break;
        case 'document':
            updateDocumentThumbnail($itemId, $relativePath);
            break;
        case 'html_page':
            updateHtmlPageThumbnail($itemId, $relativePath);
            break;
    }
    
    sendJson([
        'success' => true,
        'message' => 'Thumbnail uploaded successfully',
        'thumbnail_path' => $relativePath
    ]);
    
} catch (Exception $e) {
    // Delete uploaded file if database fails
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    sendJson([
        'success' => false,
        'message' => 'Failed to update database: ' . $e->getMessage()
    ]);
}
