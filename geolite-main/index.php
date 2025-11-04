<?php
// Start output buffering to prevent header issues
ob_start();

// Include required files
require_once 'incl/const.php';
require_once 'incl/Database.php';
require_once 'incl/Auth.php';

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Require authentication
    requireAuth();

    if (isset($_POST['map_id'])) {
        $mapId = intval($_POST['map_id']);
        // Check if user has delete permission
        if (!canDelete('map', $mapId)) {
            $error = "You do not have permission to delete this map.";
        } else {
            try {
                deleteMap($mapId);
                ob_end_clean();
                header('Location: index.php?deleted=map');
                exit;
            } catch (Exception $e) {
                $error = "Failed to delete map.";
            }
        }
    } elseif (isset($_POST['dashboard_id'])) {
        $dashboardId = intval($_POST['dashboard_id']);
        // Check if user has delete permission
        if (!canDelete('dashboard', $dashboardId)) {
            $error = "You do not have permission to delete this dashboard.";
        } else {
            try {
                deleteDashboard($dashboardId);
                ob_end_clean();
                header('Location: index.php?deleted=dashboard');
                exit;
            } catch (Exception $e) {
                $error = "Failed to delete dashboard.";
            }
        }
    }
}

// Get all saved maps,dashboard,documents, html pages and filter by permissions
try {
    $allMaps = getAllMaps();
    $maps = filterItemsByPermission('map', $allMaps);
    
    $allDashboards = getAllDashboards();
    $dashboards = filterItemsByPermission('dashboard', $allDashboards);
    
    $allDocuments = getAllDocuments();
    $documents = filterItemsByPermission('document', $allDocuments);
    
    $allHtmlPages = getAllHtmlPages();
    $htmlPages = filterItemsByPermission('html_page', $allHtmlPages);
    
} catch (Exception $e) {
    $error = "Failed to connect to database. Please check your configuration.";
    $maps = [];
    $dashboards = [];
    $documents = [];
    $htmlPages = [];
}

$savedMessage = isset($_GET['saved']) ? ($_GET['saved'] === 'dashboard' ? "Dashboard saved successfully!" : "Map saved successfully!") : null;
$deletedMessage = isset($_GET['deleted']) ? ($_GET['deleted'] === 'dashboard' ? "Dashboard deleted successfully!" : "Map deleted successfully!") : null;

// Flush output buffer
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoLite - Maps & Dashboards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
/* Material Design Tokens */
:root {
    --md-surface: #ffffff;
    --md-surface-variant: #f3f4f6;
    --md-outline: #d0d5dd;
    --md-on-surface: #1f2937;
    --md-on-surface-secondary: #6b7280;
    --md-primary: #3b82f6;
    --md-primary-container: #e0edff;
    --md-on-primary: #ffffff;
    --md-radius-lg: 16px;
    --md-radius-md: 10px;
    --elev-card: 0 8px 24px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.06);
    --elev-card-hover: 0 16px 32px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.08);
}

        body {
            background: #fff;
            min-height: 100vh;
            padding: 0;
        }
        .header {
            background: whitesmoke;
            border-radius: 0;
            padding: 1.5rem; 
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border-bottom: 1px solid var(--md-outline);
            width: 100%;
        }
        .header h1 {
            margin: 0;
            color: var(--md-on-surface);
            font-weight: 600;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .header h1 i {
            color: var(--md-primary);
        }
        .header p {
            margin: .25rem 0 0 0;
            color: var(--md-on-surface-secondary);
            font-size: .8rem;
        }
        .map-card, .dashboard-card {
            background: var(--md-surface);
            border-radius: var(--md-radius-lg);
            border: 1px solid var(--md-outline);
            overflow: hidden;
            transition: box-shadow .2s ease, transform .2s ease;
            box-shadow: var(--elev-card);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .map-card:hover, .dashboard-card:hover {
            box-shadow: var(--elev-card-hover);
            transform: translateY(-2px);
        }
        .clickable-card {
            cursor: pointer;
        }
        .clickable-card:hover {
            cursor: pointer;
        }
        .card-header-custom {
            background: var(--md-surface);
            border-bottom: 1px solid var(--md-outline);
            padding: .75rem;
            color: var(--md-on-surface);
        }
        .card-header-custom h3 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--md-on-surface);
        }
        .card-header-custom h3 i {
            color: var(--md-primary);
        }
        .card-body {
            padding: 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .card-description {
            color: #666;
            margin-bottom: 10px;
            flex-grow: 1;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .card-meta {
            font-size: 0.8rem;
            color: #999;
            margin-top: auto;
            padding-top: 8px;
            border-top: 0px solid #eee;
            line-height: 1.3;
            display:none;
        }
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 8px;
            align-items: center;
        }
        .card-category {
	    padding-bottom:7px;
	}
        .btn-custom {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .btn-view {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .dropdown-toggle-custom {
            background: #6c757d;
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .dropdown-toggle-custom:hover {
            background: #5a6268;
            color: white;
        }
        .dropdown-menu-custom {
            min-width: 160px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: none;
            border-radius: 8px;
            padding: 8px 0;
        }
        .dropdown-item-custom {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .dropdown-item-custom:hover {
            background-color: #f8f9fa;
            color: #333;
        }
        .dropdown-menu-custom {
            position: relative;
            z-index: 1000;
        }
        .dropdown-menu-custom a {
            cursor: pointer;
        }
        .dropdown-item-custom i {
            width: 16px;
            text-align: center;
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
            background: whitesmoke;
            border: none;
            padding: 12px 10px;
            border-radius: 25px;
            color: black;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s ease;
        }
        .create-btn:hover {
            transform: scale(1.05);
            color: blue;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
        }
        .dropdown-item i {
            width: 20px;
        }
        .card-thumbnail {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-bottom: 1px solid #eee;
        }
        .thumbnail-placeholder {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 2rem;
            border-bottom: 1px solid #eee;
        }
        .btn-thumbnail {
            background: #6c757d;
            border: none;
            color: white;
        }
        .btn-thumbnail:hover {
            background: #5a6268;
            color: white;
        }

.nav-link {
  font-weight: 500;
  text-decoration: underline;
  transition: color 0.2s ease;
}

.nav-link:hover {
  text-decoration: none;
  color: #0a58ca; /* slightly darker blue on hover */
}

.link-clean {
  text-decoration: none !important;
}

.link-clean:hover {
  text-decoration: underline; /* optional hover underline */
  opacity: 0.85; /* optional subtle fade */
}


/* ? Smaller, more compact card layout */
.map-card, .dashboard-card {
  border-radius: 10px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.08);
  transform: scale(0.95);     /* reduce overall visual size slightly */
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.map-card:hover, .dashboard-card:hover {
  transform: scale(0.97);     /* smaller hover lift */
  box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* ? Card image height (controls card height) */
.card-thumbnail,
.thumbnail-placeholder {
  height: 120px;  /* Reasonable height for card thumbnails */
}

/* ? Compact padding and text */
.card-body {
  padding: 12px;  /* was 20px */
}
.card-header-custom {
  padding: 12px;   
}
.card-header-custom h3 {
  font-size: 0.9rem;  /* smaller title */
}
.card-description {
  font-size: 0.9rem;
}

/* Set card width to approximately 350px while keeping fluid */
@media (min-width: 992px) {
  .col-lg-3 {
    width: 350px;
    max-width: 350px;
    flex: 0 0 350px;
  }
}

/* Center the row container to keep cards centered */
@media (min-width: 992px) {
  .row.g-4 {
    max-width: 90%;
    margin: 0 auto;
  }
}

/* Search Panel Styles */
.search-panel {
    position: fixed;
    top: 0;
    left: -350px;
    width: 350px;
    height: 100vh;
    background: white;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    z-index: 1050;
    transition: left 0.3s ease;
    overflow-y: auto;
}

.search-panel.open {
    left: 0;
}

.search-panel-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.search-panel-header h3 {
    margin: 0;
    color: #333;
    font-size: 1.2rem;
}

.search-panel-content {
    padding: 20px;
}

.search-input-group {
    margin-bottom: 20px;
}

.search-input-group .input-group {
    display: flex;
}

.search-input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px 0 0 6px;
    font-size: 14px;
    border-right: none;
}

.search-input:focus {
    outline: none;
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.search-input-group .btn {
    border-radius: 0 6px 6px 0;
    border-left: none;
}

.search-filters {
    margin-bottom: 20px;
}

.search-filters h4 {
    font-size: 1rem;
    margin-bottom: 10px;
    color: #555;
}

.filter-checkbox {
    margin-bottom: 8px;
}

.filter-checkbox input[type="checkbox"] {
    margin-right: 8px;
}

.search-results {
    margin-top: 20px;
}

.search-result-item {
    padding: 12px;
    border: 1px solid #eee;
    border-radius: 6px;
    margin-bottom: 10px;
    background: #f9f9f9;
    cursor: pointer;
    transition: background-color 0.2s;
}

.search-result-item:hover {
    background: #e9ecef;
}

.search-result-item h5 {
    margin: 0 0 5px 0;
    font-size: 0.9rem;
    color: #333;
}

.search-result-item p {
    margin: 0;
    font-size: 0.8rem;
    color: #666;
}

.search-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.1);
    z-index: 1040;
    display: none;
    pointer-events: none;
}

.search-overlay.show {
    display: block;
}

/* Adjust main content when search panel is open */
body.search-panel-open {
    margin-left: 350px;
}

@media (max-width: 768px) {
    .search-panel {
        width: 100%;
        left: -100%;
    }
    
    body.search-panel-open {
        margin-left: 0;
    }
}

    </style>
</head>
<body>
    <!-- Search Panel -->
    <div class="search-panel" id="searchPanel">
        <div class="search-panel-header">
            <h3><i class="bi bi-search"></i> Search</h3>
            <button type="button" class="btn-close" onclick="toggleSearchPanel()"></button>
        </div>
        <div class="search-panel-content">
            <div class="search-input-group">
                <div class="input-group">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search maps, dashboards, documents...">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" onclick="clearSearch()">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
            
            <div class="search-filters">
                <h4>Filter by Type</h4>
                <div class="filter-checkbox">
                    <input type="checkbox" id="filterMaps" checked>
                    <label for="filterMaps">Maps</label>
                </div>
                <div class="filter-checkbox">
                    <input type="checkbox" id="filterDashboards" checked>
                    <label for="filterDashboards">Dashboards</label>
                </div>
                <div class="filter-checkbox">
                    <input type="checkbox" id="filterDocuments" checked>
                    <label for="filterDocuments">Documents</label>
                </div>
                <div class="filter-checkbox">
                    <input type="checkbox" id="filterHtmlPages" checked>
                    <label for="filterHtmlPages">HTML Pages</label>
                </div>
            </div>
            
            <div class="search-filters">
                <h4>Filter by Category</h4>
                <div class="category-filter">
                    <select id="categoryFilter" multiple size="6" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                        <?php
                        try {
                            $categories = getAllCategories();
                            foreach ($categories as $category) {
                                echo '<option value="' . $category['id'] . '">';
                                echo '<i class="' . htmlspecialchars($category['icon']) . '"></i> ' . htmlspecialchars($category['name']);
                                echo '</option>';
                            }
                        } catch (Exception $e) {
                            // Categories table might not exist yet
                            echo '<option disabled>No categories available</option>';
                        }
                        ?>
                    </select>
                    <div style="margin-top: 8px;">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearCategoryFilter()" style="font-size: 0.8rem;">
                            <i class="bi bi-x-circle"></i> Clear Categories
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="search-results" id="searchResults">
                <!-- Search results will be populated here -->
            </div>
        </div>
    </div>
    
    <!-- Search Overlay -->
    <div class="search-overlay" id="searchOverlay"></div>

    <!-- Full-width header -->
    <div class="header" style="max-width: 100%; margin-left: 0; margin-right: 0;">
        <div class="container-fluid" style="max-width: 98%;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-map"></i> <?php echo isLoggedIn() ? htmlspecialchars(getCurrentFullname()) : 'GeoLite'; ?>
</h1>
                    <p>Welcome <?php echo isLoggedIn() ? htmlspecialchars(getCurrentFullname()) : 'to GeoLite'; ?></p>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Search Button -->
                    <?php if(isLoggedIn()){ ?>
                    <span class="text-muted">
                        <!-- <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars(getCurrentUsername()); ?>
                        <small class="badge bg-<?php echo isAdmin() ? 'danger' : 'secondary'; ?>">
                            <?php echo htmlspecialchars(getCurrentUserGroupName()); ?>
                        </small> -->
                    </span>&nbsp;&nbsp;
                    <?php } ?>
                    <?php if (isAdmin()): ?>
                    <!-- Administration Dropdown -->

<div class="dropdown">
                        <button class="create-btn dropdown-toggle" type="button" id="createDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-plus-circle"></i> New Resource
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="createDropdown">
                            <li><a class="dropdown-item" href="map_builder.php"><i class="bi bi-map"></i> Map</a></li>
                            <li><a class="dropdown-item" href="dashboard_builder.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="documents.php"><i class="bi bi-file-earmark-text"></i> Document</a></li>
                            <li><a class="dropdown-item" href="html_pages.php"><i class="bi bi-code-square"></i> HTML Page</a></li>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <button class="create-btn dropdown-toggle" type="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear"></i> Administration
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="users.php"><i class="bi bi-people"></i> Manage Users</a></li>
                            <li><a class="dropdown-item" href="admin/settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                            <li><a class="dropdown-item" href="categories.php"><i class="bi bi-tags"></i> Categories</a></li>
                        </ul>
                    </div>

                    <!-- Create New Dropdown -->
                    &nbsp;&nbsp;

                    <?php endif; ?>
                    <a href="quick-start.php" class="link-clean" style="text-decoration: none !important;">
                        <i class="bi bi-lightning-charge"></i> Quick Start
                    </a>&nbsp;&nbsp;
                    <a href="#" class="link-clean" id="searchToggleBtn" onclick="toggleSearchPanel(); return false;" style="display: none;">
                        <i class="bi bi-funnel"></i> Filters
                    </a>
                    <?php if(isLoggedIn()){ ?>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                    <?php }else{ ?>
                    <a href="login.php" class="btn btn-outline-success">
                        <i class="bi bi-box-arrow-left"></i> Login
                    </a>
                    <?php } ?>

                   

                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid" style="max-width: 98%;">
        <div style="padding: 20px;">
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

        <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-shield-exclamation"></i> Access denied. You do not have permission to access that page.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($maps) && empty($dashboards) && empty($documents) && empty($htmlPages)): ?>
            <div class="empty-state">
                <i class="bi bi-folder2-open"></i>
                <h3>No Content Available</h3>
                <?php if (isAdmin()): ?>
                <p>Create your first map, dashboard, document, or HTML page to get started with GeoLite</p>
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="map_builder.php" class="create-btn">
                        <i class="bi bi-plus-circle"></i> Create Your First Map
                    </a>
                    <a href="dashboard_builder.php" class="create-btn">
                        <i class="bi bi-plus-circle"></i> Create Your First Dashboard
                    </a>
                    <a href="documents.php" class="create-btn">
                        <i class="bi bi-file-earmark-text"></i> Upload Document
                    </a>
                    <a href="html_pages.php" class="create-btn">
                        <i class="bi bi-code-square"></i> Create HTML Page
                    </a>
                </div>
                <?php else: ?>
                <p>You don't have access to any content yet. Please contact your administrator for access.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- All Items Section -->
            <div style="max-width: 90%; margin: 0 auto;">
                <div class="row g-4">
                    <div class="col-12 mb-2">
                        <div class="d-flex justify-content-start">
                            <a href="#" class="link-clean" id="searchToggleBtnMain" onclick="toggleSearchPanel(); return false;" style="text-decoration: none !important; color:black">
                                <i class="bi bi-funnel"></i> Filters
                            </a>
                        </div>
                    </div>
                    <?php foreach ($maps as $map): ?>
                        <div class="col-md-6 col-lg-3">
                        <div class="map-card clickable-card" data-category-id="<?= $map['category_id'] ?? '' ?>" data-href="view_map.php?id=<?php echo $map['id']; ?>">
                            <div class="card-header-custom">
                                <h3><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($map['title']); ?></h3>
                            </div>
                            <?php if (!empty($map['thumbnail'])): ?>
                                <img src="<?php echo htmlspecialchars($map['thumbnail']); ?>" alt="Thumbnail" class="card-thumbnail">
                            <?php else: ?>
                                <div class="thumbnail-placeholder">
                                    <i class="bi bi-image"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <div class="card-description">
                                    <?php if (!empty($map['description'])): ?>
                                        <?php echo htmlspecialchars($map['description']); ?>
                                    <?php else: ?>
                                        <em style="color: #ccc;">No description provided</em>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($map['category_name'])): ?>
                                    <div class="card-category">
                                        <span class="category-badge" style="background-color: <?= htmlspecialchars($map['category_color']) ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="<?= htmlspecialchars($map['category_icon']) ?>"></i>
                                            <?= htmlspecialchars($map['category_name']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-meta">
                                    <div><i class="bi bi-calendar3"></i> Created: <?php echo date('M j, Y g:i A', strtotime($map['created_at'])); ?></div>
                                    <?php if ($map['updated_at'] != $map['created_at']): ?>
                                        <div><i class="bi bi-clock-history"></i> Updated: <?php echo date('M j, Y g:i A', strtotime($map['updated_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (canEdit('map', $map['id']) || canDelete('map', $map['id'])): ?>
                                <div class="card-actions">
                                    <div class="dropdown">
                                        <button class="dropdown-toggle-custom" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.stopPropagation();">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-custom">
                                            <?php if (canEdit('map', $map['id'])): ?>
                                            <li><a class="dropdown-item-custom" href="map_builder.php?id=<?php echo $map['id']; ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                            <li><a class="dropdown-item-custom" href="#" onclick="openThumbnailModal('map', <?php echo $map['id']; ?>, '<?php echo htmlspecialchars(addslashes($map['title'])); ?>'); return false;"><i class="bi bi-image"></i> Thumbnail</a></li>
                                            <?php endif; ?>
                                            <?php if (canDelete('map', $map['id'])): ?>
                                            <li><a class="dropdown-item-custom" href="#" onclick="confirmDeleteMap(<?php echo $map['id']; ?>, '<?php echo htmlspecialchars(addslashes($map['title'])); ?>'); return false;"><i class="bi bi-trash"></i> Delete</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php foreach ($dashboards as $dashboard): ?>
                        <div class="col-md-6 col-lg-3">
                        <div class="dashboard-card clickable-card" data-category-id="<?= $dashboard['category_id'] ?? '' ?>" data-href="view_dashboard.php?id=<?php echo $dashboard['id']; ?>">
                            <div class="card-header-custom">
                                <h3><i class="bi bi-speedometer2"></i> <?php echo htmlspecialchars($dashboard['title']); ?></h3>
                            </div>
                            <?php if (!empty($dashboard['thumbnail'])): ?>
                                <img src="<?php echo htmlspecialchars($dashboard['thumbnail']); ?>" alt="Thumbnail" class="card-thumbnail">
                            <?php else: ?>
                                <div class="thumbnail-placeholder">
                                    <i class="bi bi-image"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <div class="card-description">
                                    <?php if (!empty($dashboard['description'])): ?>
                                        <?php echo htmlspecialchars($dashboard['description']); ?>
                                    <?php else: ?>
                                        <em style="color: #ccc;">No description provided</em>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($dashboard['category_name'])): ?>
                                    <div class="card-category">
                                        <span class="category-badge" style="background-color: <?= htmlspecialchars($dashboard['category_color']) ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="<?= htmlspecialchars($dashboard['category_icon']) ?>"></i>
                                            <?= htmlspecialchars($dashboard['category_name']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-meta">
                                    <div><i class="bi bi-calendar3"></i> Created: <?php echo date('M j, Y g:i A', strtotime($dashboard['created_at'])); ?></div>
                                    <?php if ($dashboard['updated_at'] != $dashboard['created_at']): ?>
                                        <div><i class="bi bi-clock-history"></i> Updated: <?php echo date('M j, Y g:i A', strtotime($dashboard['updated_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (canEdit('dashboard', $dashboard['id']) || canDelete('dashboard', $dashboard['id'])): ?>
                                <div class="card-actions">
                                    <div class="dropdown">
                                        <button class="dropdown-toggle-custom" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.stopPropagation();">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-custom">
                                            <?php if (canEdit('dashboard', $dashboard['id'])): ?>
                                            <li><a class="dropdown-item-custom" href="dashboard_builder.php?id=<?php echo $dashboard['id']; ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                            <li><a class="dropdown-item-custom" href="#" onclick="openThumbnailModal('dashboard', <?php echo $dashboard['id']; ?>, '<?php echo htmlspecialchars(addslashes($dashboard['title'])); ?>'); return false;"><i class="bi bi-image"></i> Thumbnail</a></li>
                                            <?php endif; ?>
                                            <?php if (canDelete('dashboard', $dashboard['id'])): ?>
                                            <li><a class="dropdown-item-custom" href="#" onclick="confirmDeleteDashboard(<?php echo $dashboard['id']; ?>, '<?php echo htmlspecialchars(addslashes($dashboard['title'])); ?>'); return false;"><i class="bi bi-trash"></i> Delete</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php foreach ($documents as $doc): ?>
                        <div class="col-md-6 col-lg-3">
                        <div class="map-card clickable-card" data-category-id="<?= $doc['category_id'] ?? '' ?>" data-href="view_document.php?id=<?php echo $doc['id']; ?>" data-target="_blank">
                            <div class="card-header-custom">
                                <h3><i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($doc['title']); ?></h3>
                            </div>
                            <?php if (!empty($doc['thumbnail'])): ?>
                                <img src="<?php echo htmlspecialchars($doc['thumbnail']); ?>" alt="Thumbnail" class="card-thumbnail">
                            <?php else: ?>
                                <div class="thumbnail-placeholder">
                                    <i class="bi bi-image"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <div class="card-description">
                                    <?php if (!empty($doc['description'])): ?>
                                        <?php echo nl2br(htmlspecialchars($doc['description'])); ?>
                                    <?php else: ?>
                                        <em style="color: #ccc;">No description provided</em>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($doc['category_name'])): ?>
                                    <div class="card-category">
                                        <span class="category-badge" style="background-color: <?= htmlspecialchars($doc['category_color']) ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="<?= htmlspecialchars($doc['category_icon']) ?>"></i>
                                            <?= htmlspecialchars($doc['category_name']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-meta">
                                    <!-- <div><i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($doc['original_filename']); ?></div> -->
                                    <div><i class="bi bi-hdd"></i> <?php echo number_format($doc['file_size'] / 1024, 2); ?> KB</div>
                                    <div><i class="bi bi-calendar3"></i> Created: <?php echo date('M j, Y g:i A', strtotime($doc['created_at'])); ?></div>
                                </div>

                                <?php if (canEdit('document', $doc['id']) || canDelete('document', $doc['id'])): ?>
                                <div class="card-actions">
                                    <div class="dropdown">
                                        <button class="dropdown-toggle-custom" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.stopPropagation();">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-custom">
                                            <?php if (canEdit('document', $doc['id'])): ?>
                                            <li><a class="dropdown-item-custom" href="#" onclick="openThumbnailModal('document', <?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['title'])); ?>'); return false;"><i class="bi bi-image"></i> Thumbnail</a></li>
                                            <li><a class="dropdown-item-custom" href="documents.php"><i class="bi bi-folder"></i> Manage</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php foreach ($htmlPages as $page): ?>
                        <div class="col-md-6 col-lg-3">
                        <div class="map-card clickable-card" data-category-id="<?= $page['category_id'] ?? '' ?>" data-href="view_html_page.php?id=<?php echo $page['id']; ?>">
                            <div class="card-header-custom">
                                <h3><i class="bi bi-file-code"></i> <?php echo htmlspecialchars($page['title']); ?></h3>
                            </div>
                            <?php if (!empty($page['thumbnail'])): ?>
                                <img src="<?php echo htmlspecialchars($page['thumbnail']); ?>" alt="Thumbnail" class="card-thumbnail">
                            <?php else: ?>
                                <div class="thumbnail-placeholder">
                                    <i class="bi bi-image"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <div class="card-description">
                                    <?php if (!empty($page['description'])): ?>
                                        <?php echo nl2br(htmlspecialchars($page['description'])); ?>
                                    <?php else: ?>
                                        <em style="color: #ccc;">No description provided</em>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($page['category_name'])): ?>
                                    <div class="card-category">
                                        <span class="category-badge" style="background-color: <?= htmlspecialchars($page['category_color']) ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="<?= htmlspecialchars($page['category_icon']) ?>"></i>
                                            <?= htmlspecialchars($page['category_name']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-meta">
                                    <div><i class="bi bi-calendar3"></i> Created: <?php echo date('M j, Y g:i A', strtotime($page['created_at'])); ?></div>
                                    <?php if ($page['updated_at'] != $page['created_at']): ?>
                                        <div><i class="bi bi-clock-history"></i> Updated: <?php echo date('M j, Y g:i A', strtotime($page['updated_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (canEdit('html_page', $page['id']) || canDelete('html_page', $page['id'])): ?>
                                <div class="card-actions">
                                    <div class="dropdown">
                                        <button class="dropdown-toggle-custom" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.stopPropagation();">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-custom">
                                            <?php if (canEdit('html_page', $page['id'])): ?>
                                            <li><a class="dropdown-item-custom" href="#" onclick="openThumbnailModal('html_page', <?php echo $page['id']; ?>, '<?php echo htmlspecialchars(addslashes($page['title'])); ?>'); return false;"><i class="bi bi-image"></i> Thumbnail</a></li>
                                            <li><a class="dropdown-item-custom" href="html_pages.php"><i class="bi bi-folder"></i> Manage</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- Delete Map Confirmation Modal -->
    <div class="modal fade" id="deleteMapModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the map "<strong id="mapTitle"></strong>"?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="map_id" id="deleteMapId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Map
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Dashboard Confirmation Modal -->
    <div class="modal fade" id="deleteDashboardModal" tabindex="-1" aria-hidden="true">
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

    <!-- Thumbnail Upload Modal -->
    <div class="modal fade" id="thumbnailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-image"></i> Upload Thumbnail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Upload a thumbnail image for "<strong id="thumbnailItemTitle"></strong>"</p>
                    <div class="mb-3">
                        <label for="thumbnailFile" class="form-label">Select Image (JPG, PNG, GIF, WebP)</label>
                        <input type="file" class="form-control" id="thumbnailFile" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <div class="form-text">Maximum file size: 5MB</div>
                    </div>
                    <div id="thumbnailPreview" style="display: none;">
                        <label class="form-label">Preview:</label>
                        <img id="thumbnailPreviewImage" src="" alt="Preview" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div id="thumbnailAlert" class="alert d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="uploadThumbnailBtn">
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentItemType = '';
        let currentItemId = 0;

        function confirmDeleteMap(mapId, mapTitle) {
            document.getElementById('mapTitle').textContent = mapTitle;
            document.getElementById('deleteMapId').value = mapId;
            var modal = new bootstrap.Modal(document.getElementById('deleteMapModal'));
            modal.show();
        }

        function confirmDeleteDashboard(dashboardId, dashboardTitle) {
            document.getElementById('dashboardTitle').textContent = dashboardTitle;
            document.getElementById('deleteDashboardId').value = dashboardId;
            var modal = new bootstrap.Modal(document.getElementById('deleteDashboardModal'));
            modal.show();
        }

        function openThumbnailModal(itemType, itemId, itemTitle) {
            currentItemType = itemType;
            currentItemId = itemId;
            
            document.getElementById('thumbnailItemTitle').textContent = itemTitle;
            document.getElementById('thumbnailFile').value = '';
            document.getElementById('thumbnailPreview').style.display = 'none';
            document.getElementById('thumbnailAlert').classList.add('d-none');
            
            var modal = new bootstrap.Modal(document.getElementById('thumbnailModal'));
            modal.show();
        }

        // Preview image when selected
        document.getElementById('thumbnailFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('thumbnailPreviewImage').src = e.target.result;
                    document.getElementById('thumbnailPreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Upload thumbnail
        document.getElementById('uploadThumbnailBtn').addEventListener('click', function() {
            const fileInput = document.getElementById('thumbnailFile');
            const file = fileInput.files[0];
            
            if (!file) {
                showAlert('Please select an image file', 'danger');
                return;
            }

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                showAlert('File size must be less than 5MB', 'danger');
                return;
            }

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showAlert('Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.', 'danger');
                return;
            }

            // Create FormData and upload
            const formData = new FormData();
            formData.append('thumbnail', file);
            formData.append('item_type', currentItemType);
            formData.append('item_id', currentItemId);

            const uploadBtn = document.getElementById('uploadThumbnailBtn');
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';

            fetch('upload_thumbnail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Get the response text first for debugging
                return response.text().then(text => {
                    console.log('Server response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    showAlert('Thumbnail uploaded successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="bi bi-upload"></i> Upload';
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                showAlert('Error uploading thumbnail: ' + error.message, 'danger');
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="bi bi-upload"></i> Upload';
            });
        });

        function showAlert(message, type) {
            const alertDiv = document.getElementById('thumbnailAlert');
            alertDiv.className = 'alert alert-' + type;
            alertDiv.textContent = message;
            alertDiv.classList.remove('d-none');
        }

        // Search Panel Functionality
        function toggleSearchPanel() {
            const searchPanel = document.getElementById('searchPanel');
            const searchOverlay = document.getElementById('searchOverlay');
            const body = document.body;
            
            if (searchPanel.classList.contains('open')) {
                // Close panel and reset search
                searchPanel.classList.remove('open');
                searchOverlay.classList.remove('show');
                body.classList.remove('search-panel-open');
                clearSearch();
            } else {
                // Open panel
                searchPanel.classList.add('open');
                searchOverlay.classList.add('show');
                body.classList.add('search-panel-open');
                // Focus on search input
                document.getElementById('searchInput').focus();
            }
        }

        // Clear search function
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            clearCategoryFilter();
            // Reset all cards to visible
            const allCards = document.querySelectorAll('.col-md-6.col-lg-3');
            allCards.forEach(card => {
                card.style.display = 'block';
            });
            // Reset search results message
            document.getElementById('searchResults').innerHTML = '<p class="text-muted">Enter a search term or select categories to filter content...</p>';
        }

        // Search functionality
        function performSearch() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterMaps = document.getElementById('filterMaps').checked;
            const filterDashboards = document.getElementById('filterDashboards').checked;
            const filterDocuments = document.getElementById('filterDocuments').checked;
            const filterHtmlPages = document.getElementById('filterHtmlPages').checked;
            
            // Get selected categories
            const categoryFilter = document.getElementById('categoryFilter');
            const selectedCategories = Array.from(categoryFilter.selectedOptions).map(option => option.value);
            const hasCategoryFilter = selectedCategories.length > 0;
            
            const searchResults = document.getElementById('searchResults');
            
            // Get all card elements
            const allCards = document.querySelectorAll('.col-md-6.col-lg-3');
            let visibleCount = 0;
            
            allCards.forEach(card => {
                const cardElement = card.querySelector('.map-card, .dashboard-card');
                if (!cardElement) return;
                
                // Get card title and description
                const titleElement = cardElement.querySelector('.card-header-custom h3');
                const descriptionElement = cardElement.querySelector('.card-description');
                
                const title = titleElement ? titleElement.textContent.toLowerCase() : '';
                const description = descriptionElement ? descriptionElement.textContent.toLowerCase() : '';
                const cardText = title + ' ' + description;
                
                // Determine card type based on icon
                const iconElement = titleElement ? titleElement.querySelector('i') : null;
                let cardType = '';
                if (iconElement) {
                    if (iconElement.classList.contains('bi-geo-alt-fill')) {
                        cardType = 'map';
                    } else if (iconElement.classList.contains('bi-speedometer2')) {
                        cardType = 'dashboard';
                    } else if (iconElement.classList.contains('bi-file-earmark')) {
                        cardType = 'document';
                    } else if (iconElement.classList.contains('bi-file-code')) {
                        cardType = 'html_page';
                    }
                }
                
                // Check if card should be visible
                let shouldShow = false;
                
                // Check type filter
                if (cardType === 'map' && !filterMaps) shouldShow = false;
                else if (cardType === 'dashboard' && !filterDashboards) shouldShow = false;
                else if (cardType === 'document' && !filterDocuments) shouldShow = false;
                else if (cardType === 'html_page' && !filterHtmlPages) shouldShow = false;
                else shouldShow = true;
                
                // Check category filter
                if (shouldShow && hasCategoryFilter) {
                    const categoryBadge = cardElement.querySelector('.category-badge');
                    if (categoryBadge) {
                        // Extract category ID from the card (we'll need to add a data attribute)
                        const cardCategoryId = cardElement.getAttribute('data-category-id');
                        shouldShow = cardCategoryId && selectedCategories.includes(cardCategoryId);
                    } else {
                        // If no category assigned and categories are filtered, hide the card
                        shouldShow = false;
                    }
                }
                
                // Check search term
                if (shouldShow && searchTerm.trim()) {
                    shouldShow = cardText.includes(searchTerm);
                }
                
                // Show/hide card
                if (shouldShow) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update search results message
            if (searchTerm.trim() || hasCategoryFilter) {
                if (visibleCount === 0) {
                    let message = 'No results found';
                    if (searchTerm.trim()) message += ' for "' + searchTerm + '"';
                    if (hasCategoryFilter) message += ' in selected categories';
                    searchResults.innerHTML = '<p class="text-muted">' + message + '</p>';
                } else {
                    let message = 'Found ' + visibleCount + ' result' + (visibleCount !== 1 ? 's' : '');
                    if (searchTerm.trim()) message += ' for "' + searchTerm + '"';
                    if (hasCategoryFilter) message += ' in selected categories';
                    searchResults.innerHTML = '<p class="text-success">' + message + '</p>';
                }
            } else {
                searchResults.innerHTML = '<p class="text-muted">Enter a search term or select categories to filter content...</p>';
            }
        }

        // Clear category filter function
        function clearCategoryFilter() {
            const categoryFilter = document.getElementById('categoryFilter');
            if (categoryFilter) {
                // Deselect all options
                Array.from(categoryFilter.options).forEach(option => {
                    option.selected = false;
                });
                // Trigger search to update results
                performSearch();
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const filterCheckboxes = document.querySelectorAll('.filter-checkbox input[type="checkbox"]');
            const categoryFilter = document.getElementById('categoryFilter');
            
            // Make cards clickable
            document.querySelectorAll('.clickable-card').forEach(function(card) {
                card.addEventListener('click', function(e) {
                    // Don't navigate if clicking on dropdown or dropdown menu
                    if (e.target.closest('.dropdown') || e.target.closest('.dropdown-menu')) {
                        return;
                    }
                    
                    const href = card.getAttribute('data-href');
                    const target = card.getAttribute('data-target');
                    
                    if (href) {
                        if (target === '_blank') {
                            window.open(href, '_blank');
                        } else {
                            window.location.href = href;
                        }
                    }
                });
            });
            
            // Prevent dropdown menu clicks from triggering card navigation
            document.querySelectorAll('.dropdown-menu-custom a').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
            
            // Search on input
            searchInput.addEventListener('input', performSearch);
            
            // Search on filter change
            filterCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', performSearch);
            });
            
            // Search on category filter change
            if (categoryFilter) {
                categoryFilter.addEventListener('change', performSearch);
            }
            
            // Close panel on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const searchPanel = document.getElementById('searchPanel');
                    if (searchPanel.classList.contains('open')) {
                        toggleSearchPanel();
                    }
                }
            });
        });
    </script>
</body>
</html>
