<?php
// Start output buffering to prevent header issues
ob_start();

// Include required files
require_once 'incl/const.php';
require_once 'incl/Database.php';
require_once 'incl/Auth.php';

// Require authentication
requireAuth();

// Only admins can manage documents
if (!isAdmin()) {
    ob_end_clean();
    header('Location: index.php?error=access_denied');
    exit;
}

// Create uploads directory if it doesn't exist
$uploadsDir = DATA_DIR . '/uploads';

$error = null;
$success = null;

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
    
    if (empty($title)) {
        $error = "Title is required.";
    } elseif (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a file to upload.";
    } else {
        $file = $_FILES['document'];
        $originalFilename = basename($file['name']);
        $fileSize = $file['size'];
        
        // Get MIME type from file extension (most compatible approach)
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'html' => 'text/html',
            'htm' => 'text/html',
            'xml' => 'text/xml',
            'json' => 'application/json'
        ];
        
        // Use browser-provided MIME type as fallback, or determine from extension
        $mimeType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 
                    (!empty($file['type']) ? $file['type'] : 'application/octet-stream');
        
        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filePath = DATA_DIR.'/uploads/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            try {
                saveDocument($title, $description, $filename, $originalFilename, $filePath, $fileSize, $mimeType, $categoryId);
                ob_end_clean();
                header('Location: documents.php?uploaded=1');
                exit;
            } catch (Exception $e) {
                $error = "Failed to save document to database.";
                // Remove uploaded file if database save fails
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        } else {
            $error = "Failed to upload file.";
        }
    }
}

// Handle document update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = intval($_POST['document_id']);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
    
    if (empty($title)) {
        $error = "Title is required.";
    } else {
        try {
            updateDocument($id, $title, $description, $categoryId);
            ob_end_clean();
            header('Location: documents.php?updated=1');
            exit;
        } catch (Exception $e) {
            $error = "Failed to update document.";
        }
    }
}

// Handle document delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = intval($_POST['document_id']);
    
    try {
        // Get document info to delete the file
        $document = getDocumentById($id);
        if ($document) {
            // Delete from database
            deleteDocument($id);
            // Delete file
            $file_path = DATA_DIR.'/uploads/'.$document['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            ob_end_clean();
            header('Location: documents.php?deleted=1');
            exit;
        }
    } catch (Exception $e) {
        $error = "Failed to delete document.";
    }
}

// Get all documents
try {
    $documents = getAllDocuments();
} catch (Exception $e) {
    $error = "Failed to connect to database. Please check your configuration.";
    $documents = [];
}

// Get document for editing if edit mode
$editDocument = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editDocument = getDocumentById($editId);
}

if (isset($_GET['uploaded'])) {
    $success = "Document uploaded successfully!";
} elseif (isset($_GET['updated'])) {
    $success = "Document updated successfully!";
} elseif (isset($_GET['deleted'])) {
    $success = "Document deleted successfully!";
}

// Flush output buffer
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - GeoLite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f3f4f6;
            min-height: 100vh;
            padding: 0;
        }
        .upload-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .document-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: white;
        }
        .card-header-custom h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        .card-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .card-description {
            color: #666;
            margin-bottom: 15px;
            flex-grow: 1;
        }
        .card-meta {
            font-size: 0.85rem;
            color: #999;
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-custom {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .back-btn {
            color: white;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .back-btn:hover {
            opacity: 0.8;
            color: white;
        }
        .empty-state {
            background: white;
            border-radius: 15px;
            padding: 60px 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        .file-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <!-- Set header variables for the include -->
    <?php 
    $headerTitle = 'Documents';
    $headerSubtitle = 'Upload and manage your documents';
    $headerIcon = 'file-earmark-text';
    include 'incl/header.php'; 
    ?>

    <div class="container" style="max-width: 95%; margin: 0 auto; padding: 20px;">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="upload-card">
            <h3 class="mb-4">
                <i class="bi bi-cloud-upload"></i> 
                <?php echo $editDocument ? 'Edit Document' : 'Upload New Document'; ?>
            </h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $editDocument ? 'update' : 'upload'; ?>">
                <?php if ($editDocument): ?>
                    <input type="hidden" name="document_id" value="<?php echo $editDocument['id']; ?>">
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="title" class="form-label">Title *</label>
                    <input type="text" class="form-control" id="title" name="title" 
                           value="<?php echo $editDocument ? htmlspecialchars($editDocument['title']) : ''; ?>" 
                           required>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo $editDocument ? htmlspecialchars($editDocument['description']) : ''; ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-control" id="category_id" name="category_id">
                        <option value="">Select a category (optional)</option>
                        <?php
                        try {
                            $categories = getCategoriesForDropdown();
                            foreach ($categories as $category) {
                                $selected = ($editDocument && $editDocument['category_id'] == $category['id']) ? 'selected' : '';
                                echo '<option value="' . $category['id'] . '" ' . $selected . '>' . htmlspecialchars($category['name']) . '</option>';
                            }
                        } catch (Exception $e) {
                            // Categories table might not exist yet
                        }
                        ?>
                    </select>
                </div>
                
                <?php if (!$editDocument): ?>
                <div class="mb-3">
                    <label for="document" class="form-label">Document File *</label>
                    <input type="file" class="form-control" id="document" name="document" required>
                    <div class="form-text">Supported formats: PDF, DOCX, XLSX, TXT, etc. Max size: <?=min(ini_get('upload_max_filesize'),ini_get('post_max_size'))?>B</div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Current file: <strong><?php echo htmlspecialchars($editDocument['original_filename']); ?></strong>
                    <br><small>Note: You can only update the title and description. To change the file, please upload a new document.</small>
                </div>
                <?php endif; ?>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?php echo $editDocument ? 'save' : 'upload'; ?>"></i> 
                        <?php echo $editDocument ? 'Update Document' : 'Upload Document'; ?>
                    </button>
                    <?php if ($editDocument): ?>
                    <a href="documents.php" class="btn btn-secondary">
                        <i class="bi bi-x"></i> Cancel Edit
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Documents List -->
        <h2 class="text-white mb-4"><i class="bi bi-file-earmark-text"></i> My Documents</h2>
        
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <i class="bi bi-file-earmark-text"></i>
                <h3>No Documents Yet</h3>
                <p>Upload your first document to get started</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($documents as $doc): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="document-card">
                            <div class="card-header-custom">
                                <h3><i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($doc['title']); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="text-center file-icon">
                                    <?php
                                    // Show appropriate icon based on mime type
                                    if (strpos($doc['mime_type'], 'pdf') !== false) {
                                        echo '<i class="bi bi-file-earmark-pdf text-danger"></i>';
                                    } elseif (strpos($doc['mime_type'], 'word') !== false || strpos($doc['mime_type'], 'document') !== false) {
                                        echo '<i class="bi bi-file-earmark-word text-primary"></i>';
                                    } elseif (strpos($doc['mime_type'], 'excel') !== false || strpos($doc['mime_type'], 'spreadsheet') !== false) {
                                        echo '<i class="bi bi-file-earmark-excel text-success"></i>';
                                    } elseif (strpos($doc['mime_type'], 'text') !== false) {
                                        echo '<i class="bi bi-file-earmark-text text-secondary"></i>';
                                    } else {
                                        echo '<i class="bi bi-file-earmark text-secondary"></i>';
                                    }
                                    ?>
                                </div>
                                <div class="card-description">
                                    <?php if (!empty($doc['description'])): ?>
                                        <?php echo nl2br(htmlspecialchars($doc['description'])); ?>
                                    <?php else: ?>
                                        <em style="color: #ccc;">No description provided</em>
                                    <?php endif; ?>
                                </div>
                                <div class="card-meta">
                                    <div><i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($doc['original_filename']); ?></div>
                                    <div><i class="bi bi-hdd"></i> <?php echo number_format($doc['file_size'] / 1024, 2); ?> KB</div>
                                    <div><i class="bi bi-calendar3"></i> <?php echo date('M j, Y g:i A', strtotime($doc['created_at'])); ?></div>
                                </div>
                                <div class="card-actions">
                                    <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="btn btn-primary btn-custom" target="_blank">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                    <a href="documents.php?edit=<?php echo $doc['id']; ?>" class="btn btn-warning btn-custom">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-danger btn-custom" onclick="confirmDelete(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['title'])); ?>')">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<strong id="documentTitle"></strong>"?</p>
                    <p class="text-muted mb-0">This action cannot be undone. The file will be permanently deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="document_id" id="deleteDocumentId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Document
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(docId, docTitle) {
            document.getElementById('documentTitle').textContent = docTitle;
            document.getElementById('deleteDocumentId').value = docId;
            var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
