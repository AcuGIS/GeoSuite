<?php
require_once 'incl/Auth.php';
require_once 'incl/Database.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

// Load brand settings
require_once 'incl/const.php';
require_once 'incl/db.php';
require_once 'incl/Settings.php';

$brand = [
    'site_name'     => 'GeoLite',
    'logo_url'      => null,
    'primary_color' => '#667eea',
    'hero_image'    => null,
    'footer_text'   => '© ' . date('Y') . ' GeoLite'
];

// Load settings from database
$settingsService = new Settings($pdo, 'assets/brand', 'assets/brand');
$brand = array_merge($brand, $settingsService->load());

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $color = trim($_POST['color']);
                $icon = trim($_POST['icon']);
                
                if (empty($name)) {
                    $error = 'Category name is required.';
                } else {
                    try {
                        createCategory($name, $description, $color, $icon);
                        $message = 'Category created successfully!';
                    } catch (Exception $e) {
                        $error = 'Error creating category: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $color = trim($_POST['color']);
                $icon = trim($_POST['icon']);
                
                if (empty($name)) {
                    $error = 'Category name is required.';
                } else {
                    try {
                        if (updateCategory($id, $name, $description, $color, $icon)) {
                            $message = 'Category updated successfully!';
                        } else {
                            $error = 'Error updating category.';
                        }
                    } catch (Exception $e) {
                        $error = 'Error updating category: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                try {
                    if (deleteCategory($id)) {
                        $message = 'Category deleted successfully!';
                    } else {
                        $error = 'Error deleting category.';
                    }
                } catch (Exception $e) {
                    $error = 'Error deleting category: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all categories
try {
    $categories = getAllCategories();
} catch (Exception $e) {
    $error = 'Error loading categories: ' . $e->getMessage();
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - <?= htmlspecialchars($brand['site_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --brand-primary: <?= htmlspecialchars($brand['primary_color']) ?>;
        }
        
        .brand-header {
            background: linear-gradient(135deg, var(--brand-primary) 0%, #667eea 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .brand-logo {
            width: 32px;
            height: 32px;
            border-radius: 8px;
        }
        
        .brand-bar {
            height: 4px;
            background: linear-gradient(90deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.1) 100%);
        }
        
        .category-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .category-header {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
        }
        
        .category-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .color-preview {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px #dee2e6;
        }
        
        .btn-category {
            background: var(--brand-primary);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-category:hover {
            background: #5a67d8;
            color: white;
            transform: translateY(-1px);
        }
        
        .modal-header {
            background: var(--brand-primary);
            color: white;
        }
        
        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            border-color: #5a67d8;
        }
        
        .site-footer {
            color: #6b7280;
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body>
    <!-- Set header variables for the include -->
    <?php 
    $headerTitle = 'Categories';
    $headerSubtitle = 'Manage categories for organizing your maps and content';
    $headerIcon = 'tags';
    include 'incl/header.php'; 
    ?>

    <div class="container-fluid" style="max-width: 98%; padding: 20px;">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="bi bi-tags"></i> Categories</h2>
                        <p class="text-muted">Manage categories for organizing your maps and content</p>
                    </div>
                    <button class="btn btn-category" data-bs-toggle="modal" data-bs-target="#categoryModal">
                        <i class="bi bi-plus-circle"></i> Add Category
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="category-card">
                                <div class="category-header">
                                    <div class="d-flex align-items-center">
                                        <div class="category-icon me-3" style="background-color: <?= htmlspecialchars($category['color']) ?>">
                                            <i class="<?= htmlspecialchars($category['icon']) ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="mb-1"><?= htmlspecialchars($category['name']) ?></h5>
                                            <div class="d-flex align-items-center">
                                                <div class="color-preview me-2" style="background-color: <?= htmlspecialchars($category['color']) ?>"></div>
                                                <small class="text-muted"><?= htmlspecialchars($category['icon']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <p class="text-muted mb-3"><?= htmlspecialchars($category['description']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Created: <?= date('M j, Y', strtotime($category['created_at'])) ?>
                                        </small>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editCategory(<?= htmlspecialchars(json_encode($category)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-tags text-muted" style="font-size: 3rem;"></i>
                        <h4 class="text-muted mt-3">No categories found</h4>
                        <p class="text-muted">Create your first category to get started.</p>
                        <button class="btn btn-category" data-bs-toggle="modal" data-bs-target="#categoryModal">
                            <i class="bi bi-plus-circle"></i> Add Category
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalLabel">Add Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="categoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="categoryId">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="color" class="form-label">Color</label>
                                    <input type="color" class="form-control form-control-color" id="color" name="color" value="#667eea">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="icon" class="form-label">Icon</label>
                                    <select class="form-select" id="icon" name="icon">
                                        <option value="bi-tag">Tag</option>
                                        <option value="bi-building">Building</option>
                                        <option value="bi-car-front">Transportation</option>
                                        <option value="bi-droplet">Water</option>
                                        <option value="bi-tree">Land Use</option>
                                        <option value="bi-mountain">Elevation</option>
                                        <option value="bi-people">Population</option>
                                        <option value="bi-flower1">Environment</option>
                                        <option value="bi-lightning">Utilities</option>
                                        <option value="bi-hospital">Emergency</option>
                                        <option value="bi-tree-fill">Recreation</option>
                                        <option value="bi-shop">Economic</option>
                                        <option value="bi-cloud-sun">Weather</option>
                                        <option value="bi-gear">Infrastructure</option>
                                        <option value="bi-diagram-2">Boundaries</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the category "<span id="deleteCategoryName"></span>"?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteCategoryId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Site Footer -->
    <footer class="site-footer mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0"><?= htmlspecialchars($brand['footer_text']) ?></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(category) {
            document.getElementById('categoryModalLabel').textContent = 'Edit Category';
            document.getElementById('formAction').value = 'update';
            document.getElementById('categoryId').value = category.id;
            document.getElementById('name').value = category.name;
            document.getElementById('description').value = category.description || '';
            document.getElementById('color').value = category.color;
            document.getElementById('icon').value = category.icon;
            
            new bootstrap.Modal(document.getElementById('categoryModal')).show();
        }
        
        function deleteCategory(id, name) {
            document.getElementById('deleteCategoryId').value = id;
            document.getElementById('deleteCategoryName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Reset form when modal is hidden
        document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryModalLabel').textContent = 'Add Category';
            document.getElementById('formAction').value = 'create';
            document.getElementById('categoryId').value = '';
        });
    </script>
</body>
</html>
