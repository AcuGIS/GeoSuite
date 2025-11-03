<?php
// Start output buffering to prevent header issues
ob_start();


// Include required files
require_once 'incl/const.php';
require_once 'incl/Database.php';
require_once 'incl/Auth.php';

// Require authentication
requireAuth();

// Only admins can manage HTML pages
if (!isAdmin()) {
    ob_end_clean();
    header('Location: index.php?error=access_denied');
    exit;
}

$error = null;
$success = null;

// Handle HTML page save/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $htmlContent = $_POST['html_content'] ?? '';
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
    
    if (empty($title)) {
        $error = "Title is required.";
    } elseif (empty($htmlContent)) {
        $error = "HTML content is required.";
    } else {
        try {
            if ($_POST['action'] === 'create') {
                saveHtmlPage($title, $description, $htmlContent, $categoryId);
                ob_end_clean();
                header('Location: html_pages.php?saved=1');
                exit;
            } elseif ($_POST['action'] === 'update') {
                $id = intval($_POST['page_id']);
                updateHtmlPage($id, $title, $description, $htmlContent, $categoryId);
                ob_end_clean();
                header('Location: html_pages.php?updated=1');
                exit;
            }
        } catch (Exception $e) {
            $error = "Failed to save HTML page.";
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = intval($_POST['page_id']);
    
    try {
        deleteHtmlPage($id);
        ob_end_clean();
        header('Location: html_pages.php?deleted=1');
        exit;
    } catch (Exception $e) {
        $error = "Failed to delete HTML page.";
    }
}

// Get all HTML pages
try {
    $htmlPages = getAllHtmlPages();
} catch (Exception $e) {
    $error = "Failed to connect to database. Please check your configuration.";
    $htmlPages = [];
}

// Get HTML page for editing if edit mode
$editPage = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editPage = getHtmlPageById($editId);
}

if (isset($_GET['saved'])) {
    $success = "HTML page saved successfully!";
} elseif (isset($_GET['updated'])) {
    $success = "HTML page updated successfully!";
} elseif (isset($_GET['deleted'])) {
    $success = "HTML page deleted successfully!";
}

// Flush output buffer
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTML Pages - GeoLite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Quill Editor Styles -->
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <style>
        body {
            background: #f3f4f6;
            min-height: 100vh;
            padding: 0;
        }
        .editor-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .page-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .page-card:hover {
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
        /* Quill Editor Styling */
        #editor {
            min-height: 300px;
            background: white;
        }
        .ql-container {
            font-size: 16px;
            min-height: 300px;
        }
        .ql-editor {
            min-height: 300px;
        }
        .ql-toolbar {
            background: #f8f9fa;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }
    </style>
</head>
<body>
    <!-- Set header variables for the include -->
    <?php 
    $headerTitle = 'HTML Pages';
    $headerSubtitle = 'Create and manage custom HTML pages';
    $headerIcon = 'code-square';
    include 'incl/header.php'; 
    ?>

    <div class="container-fluid" style="max-width: 98%; padding: 20px; margin: 0 auto;">
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

        <!-- HTML Editor Form -->
        <div class="editor-card">
            <h3 class="mb-4">
                <i class="bi bi-pencil-square"></i> 
                <?php echo $editPage ? 'Edit HTML Page' : 'Create New HTML Page'; ?>
            </h3>
            <form method="POST" id="htmlPageForm">
                <input type="hidden" name="action" value="<?php echo $editPage ? 'update' : 'create'; ?>">
                <?php if ($editPage): ?>
                    <input type="hidden" name="page_id" value="<?php echo $editPage['id']; ?>">
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="title" class="form-label">Title *</label>
                    <input type="text" class="form-control" id="title" name="title" 
                           value="<?php echo $editPage ? htmlspecialchars($editPage['title']) : ''; ?>" 
                           required>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="2"><?php echo $editPage ? htmlspecialchars($editPage['description']) : ''; ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-control" id="category_id" name="category_id">
                        <option value="">Select a category (optional)</option>
                        <?php
                        try {
                            $categories = getCategoriesForDropdown();
                            foreach ($categories as $category) {
                                $selected = ($editPage && $editPage['category_id'] == $category['id']) ? 'selected' : '';
                                echo '<option value="' . $category['id'] . '" ' . $selected . '>' . htmlspecialchars($category['name']) . '</option>';
                            }
                        } catch (Exception $e) {
                            // Categories table might not exist yet
                        }
                        ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="html_content" class="form-label">HTML Content *</label>
                    <div id="editor"></div>
                    <textarea id="html_content" name="html_content" style="display:none;"><?php echo $editPage ? htmlspecialchars($editPage['html_content']) : ''; ?></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> 
                        <?php echo $editPage ? 'Update Page' : 'Create Page'; ?>
                    </button>
                    <?php if ($editPage): ?>
                    <a href="html_pages.php" class="btn btn-secondary">
                        <i class="bi bi-x"></i> Cancel Edit
                    </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-info" onclick="previewHTML()">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                </div>
            </form>
        </div>

        <!-- HTML Pages List -->
        <h2 class="text-white mb-4"><i class="bi bi-code-square"></i> My HTML Pages</h2>
        
        <?php if (empty($htmlPages)): ?>
            <div class="empty-state">
                <i class="bi bi-code-square"></i>
                <h3>No HTML Pages Yet</h3>
                <p>Create your first HTML page to get started</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($htmlPages as $page): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="page-card">
                            <div class="card-header-custom">
                                <h3><i class="bi bi-file-code"></i> <?php echo htmlspecialchars($page['title']); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="card-description">
                                    <?php if (!empty($page['description'])): ?>
                                        <?php echo nl2br(htmlspecialchars($page['description'])); ?>
                                    <?php else: ?>
                                        <em style="color: #ccc;">No description provided</em>
                                    <?php endif; ?>
                                </div>
                                <div class="card-meta">
                                    <div><i class="bi bi-calendar3"></i> Created: <?php echo date('M j, Y g:i A', strtotime($page['created_at'])); ?></div>
                                    <?php if ($page['updated_at'] != $page['created_at']): ?>
                                        <div><i class="bi bi-clock-history"></i> Updated: <?php echo date('M j, Y g:i A', strtotime($page['updated_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-actions">
                                    <a href="view_html_page.php?id=<?php echo $page['id']; ?>" class="btn btn-primary btn-custom" target="_blank">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="html_pages.php?edit=<?php echo $page['id']; ?>" class="btn btn-warning btn-custom">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-danger btn-custom" onclick="confirmDelete(<?php echo $page['id']; ?>, '<?php echo htmlspecialchars(addslashes($page['title'])); ?>')">
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
                    <p>Are you sure you want to delete "<strong id="pageTitle"></strong>"?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="page_id" id="deletePageId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Page
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye"></i> HTML Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <iframe id="previewFrame" style="width: 100%; height: 70vh; border: 1px solid #ddd;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Quill WYSIWYG Editor -->
    <script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
    <script>
        let quill;
        
        // Initialize Quill
        document.addEventListener('DOMContentLoaded', function() {
            quill = new Quill('#editor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                        [{ 'font': [] }],
                        [{ 'size': ['small', false, 'large', 'huge'] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'script': 'sub'}, { 'script': 'super' }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'indent': '-1'}, { 'indent': '+1' }],
                        [{ 'direction': 'rtl' }],
                        [{ 'align': [] }],
                        ['blockquote', 'code-block'],
                        ['link', 'image', 'video'],
                        ['clean']
                    ]
                },
                placeholder: 'Enter your HTML content here...'
            });
            
            // Load initial content if editing
            const initialContent = document.getElementById('html_content').value;
            if (initialContent) {
                quill.clipboard.dangerouslyPasteHTML(initialContent);
            }
        });

        function confirmDelete(pageId, pageTitle) {
            document.getElementById('pageTitle').textContent = pageTitle;
            document.getElementById('deletePageId').value = pageId;
            var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        function previewHTML() {
            // Get HTML content from Quill
            var htmlContent = quill.root.innerHTML;
            
            // Create preview with proper CSS for alignment
            var previewFrame = document.getElementById('previewFrame');
            var previewDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
            previewDoc.open();
            
            // Write HTML with CSS that preserves alignment
            previewDoc.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        body { 
                            margin: 0; 
                            padding: 20px; 
                            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        }
                        
                        /* Preserve Quill.js alignment styles */
                        * {
                            text-align: inherit;
                        }
                        
                        /* Ensure alignment styles from Quill are preserved */
                        [style*="text-align"] {
                            text-align: inherit !important;
                        }
                        
                        /* Specific alignment classes for better compatibility */
                        .ql-align-center {
                            text-align: center !important;
                        }
                        .ql-align-right {
                            text-align: right !important;
                        }
                        .ql-align-left {
                            text-align: left !important;
                        }
                        .ql-align-justify {
                            text-align: justify !important;
                        }
                        
                        /* Ensure paragraphs and other elements respect alignment */
                        p, div, h1, h2, h3, h4, h5, h6 {
                            margin: 0.5em 0;
                        }
                        
                        /* Preserve any inline styles that might contain alignment */
                        [style] {
                            /* This ensures inline styles are preserved */
                        }
                    </style>
                </head>
                <body>
                    ${htmlContent}
                </body>
                </html>
            `);
            previewDoc.close();
            
            // Show modal
            var modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
        }

        // Before form submit, update textarea with Quill content
        document.getElementById('htmlPageForm').addEventListener('submit', function(e) {
            document.getElementById('html_content').value = quill.root.innerHTML;
        });
    </script>
</body>
</html>
