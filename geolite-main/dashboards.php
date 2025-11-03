<?php
// Start output buffering to prevent header issues
ob_start();

// Include required files
require_once 'incl/const.php';
require_once 'incl/Database.php';
require_once 'incl/Auth.php';

// Require authentication
requireAuth();

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $dashboardId = intval($_POST['dashboard_id']);
    try {
        deleteDashboard($dashboardId);
        ob_end_clean();
        header('Location: dashboards.php?deleted=1');
        exit;
    } catch (Exception $e) {
        $error = "Failed to delete dashboard.";
    }
}

// Get all saved dashboards
try {
    $dashboards = getAllDashboards();
} catch (Exception $e) {
    $error = "Failed to connect to database. Please check your configuration.";
    $dashboards = [];
}

$savedMessage = isset($_GET['saved']) ? "Dashboard saved successfully!" : null;
$deletedMessage = isset($_GET['deleted']) ? "Dashboard deleted successfully!" : null;

// Flush output buffer
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoLite - Saved Dashboards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-weight: 700;
        }
        .header p {
            margin: 10px 0 0 0;
            color: #666;
        }
        .dashboard-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .dashboard-card:hover {
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
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }
        .empty-state p {
            color: #999;
            margin-bottom: 30px;
        }
        .create-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s ease;
            margin-right: 10px;
        }
        .create-btn:hover {
            transform: scale(1.05);
            color: white;
        }
        .back-btn {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .back-btn:hover {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-speedometer2"></i> GeoLite Dashboard Library</h1>
                    <p>View and manage your saved dashboards</p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="text-muted">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars(getCurrentUsername()); ?>
                    </span>
                    <a href="dashboard_builder.php" class="create-btn">
                        <i class="bi bi-plus-circle"></i> Create New Dashboard
                    </a>
                    <a href="index.php" class="back-btn">
                        <i class="bi bi-map"></i> View Maps
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <?php if ($savedMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $savedMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($deletedMessage): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle"></i> <?php echo $deletedMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($dashboards)): ?>
            <div class="empty-state">
                <i class="bi bi-speedometer2"></i>
                <h3>No Dashboards Yet</h3>
                <p>Create your first dashboard to get started with GeoLite</p>
                <a href="dashboard_builder.php" class="create-btn">
                    <i class="bi bi-plus-circle"></i> Create Your First Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($dashboards as $dashboard): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="dashboard-card">
                            <div class="card-header-custom">
                                <h3><i class="bi bi-speedometer2"></i> <?php echo htmlspecialchars($dashboard['title']); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="card-description">
                                    <?php if (!empty($dashboard['description'])): ?>
                                        <?php echo htmlspecialchars($dashboard['description']); ?>
                                    <?php else: ?>
                                        <em style="color: #ccc;">No description provided</em>
                                    <?php endif; ?>
                                </div>
                                <div class="card-meta">
                                    <div><i class="bi bi-calendar3"></i> Created: <?php echo date('M j, Y g:i A', strtotime($dashboard['created_at'])); ?></div>
                                    <?php if ($dashboard['updated_at'] != $dashboard['created_at']): ?>
                                        <div><i class="bi bi-clock-history"></i> Updated: <?php echo date('M j, Y g:i A', strtotime($dashboard['updated_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-actions">
                                    <a href="view_dashboard.php?id=<?php echo $dashboard['id']; ?>" class="btn btn-primary btn-custom" target="_blank">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="dashboard_builder.php?id=<?php echo $dashboard['id']; ?>" class="btn btn-warning btn-custom">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-danger btn-custom" onclick="confirmDelete(<?php echo $dashboard['id']; ?>, '<?php echo htmlspecialchars(addslashes($dashboard['title'])); ?>')">
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
                    <p>Are you sure you want to delete the dashboard "<strong id="dashboardTitle"></strong>"?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="dashboard_id" id="deleteDashboardId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Dashboard
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(dashboardId, dashboardTitle) {
            document.getElementById('dashboardTitle').textContent = dashboardTitle;
            document.getElementById('deleteDashboardId').value = dashboardId;
            var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
