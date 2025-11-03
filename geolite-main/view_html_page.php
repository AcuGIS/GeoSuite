<?php

// Include required files
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Database.php';

// Require authentication
//requireAuth();

// Get page ID
if (!isset($_GET['id'])) {
    header('Location: html_pages.php');
    exit;
}

$id = intval($_GET['id']);

// Check view permission
if (!canView('html_page', $id)) {
    header('Location: index.php?error=access_denied');
    exit;
}

try {
    $page = getHtmlPageById($id);
    
    if (!$page) {
        header('Location: index.php?error=not_found');
        exit;
    }
    
    // Output header with Return button
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            body { margin: 0; padding: 0; }
            .return-header {
                background: #696969;
                padding: 10px 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                position: sticky;
                top: 0;
                z-index: 1000;
            }
            .return-btn {
                background: white;
                color: #696969;
                border: none;
                padding: 8px 20px;
                border-radius: 6px;
                font-weight: 600;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s;
            }
            .return-btn:hover {
                background: #f8f9fa;
                color: #333;
                transform: translateX(-2px);
            }
            .page-title {
                color: white;
                margin: 0;
                font-size: 1.1rem;
                font-weight: 600;
            }
            .page-content { 
                height: calc(100vh - 60px);
                overflow: auto;
                padding: 20px;
            }
            
            /* Preserve Quill.js alignment styles */
            .page-content * {
                text-align: inherit;
            }
            
            /* Ensure alignment styles from Quill are preserved */
            .page-content [style*="text-align"] {
                text-align: inherit !important;
            }
            
            /* Specific alignment classes for better compatibility */
            .page-content .ql-align-center {
                text-align: center !important;
            }
            .page-content .ql-align-right {
                text-align: right !important;
            }
            .page-content .ql-align-left {
                text-align: left !important;
            }
            .page-content .ql-align-justify {
                text-align: justify !important;
            }
            
            /* Ensure paragraphs and other elements respect alignment */
            .page-content p,
            .page-content div,
            .page-content h1,
            .page-content h2,
            .page-content h3,
            .page-content h4,
            .page-content h5,
            .page-content h6 {
                margin: 0.5em 0;
            }
            
            /* Preserve any inline styles that might contain alignment */
            .page-content [style] {
                /* This ensures inline styles are preserved */
            }
            
            /* Video styling */
            .page-content video {
                max-width: 100%;
                height: auto;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .page-content .video-small {
                width: 200px !important;
                height: auto !important;
            }
            
            .page-content .video-medium {
                width: 400px !important;
                height: auto !important;
            }
            
            .page-content .video-large {
                width: 600px !important;
                height: auto !important;
            }
            
            .page-content .video-align-left {
                float: left !important;
                margin: 0 10px 10px 0 !important;
            }
            
            .page-content .video-align-center {
                float: none !important;
                margin: 10px auto !important;
                display: block !important;
            }
            
            .page-content .video-align-right {
                float: right !important;
                margin: 0 0 10px 10px !important;
            }
        </style>
    </head>
    <body>
        <div class="return-header d-flex justify-content-between align-items-center">
            <div style="width: 100px;"></div>
            <h1 class="page-title">' . htmlspecialchars($page['title']) . '</h1>
            <a href="index.php" class="return-btn">
                <i class="bi bi-arrow-left"></i> Return
            </a>
        </div>
        <div class="page-content">';
    
    // Output the HTML page content
    echo $page['html_content'];
    
    echo '</div>
    </body>
    </html>';
    
} catch (Exception $e) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-danger">
                <h4>Error</h4>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <a href="html_pages.php" class="btn btn-primary">Back to HTML Pages</a>
            </div>
        </div>
    </body>
    </html>';
}
