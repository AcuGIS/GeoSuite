<?php

// Include required files
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Database.php';

// Require authentication
//requireAuth();

// Get document ID
if (!isset($_GET['id'])) {
    header('Location: documents.php');
    exit;
}

$id = intval($_GET['id']);

// Check view permission
if (!canView('document', $id)) {
    header('Location: index.php?error=access_denied');
    exit;
}

try {
    $document = getDocumentById($id);
    
    if (!$document) {
        header('Location: index.php?error=not_found');
        exit;
    }
    
    // Check if file exists
    if (!file_exists(DATA_DIR.'/uploads/'.$document['filename'])) {
        die('File not found.');
    }
    
    // Set headers for file download
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Disposition: inline; filename="' . $document['original_filename'] . '"');
    header('Content-Length: ' . $document['file_size']);
    header('Cache-Control: public, max-age=3600');
    header('Pragma: public');
    
    // Output file
    readfile(DATA_DIR.'/uploads/'.$document['filename']);
    exit;
    
} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}
