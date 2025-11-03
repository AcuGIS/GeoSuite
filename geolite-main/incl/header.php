<?php
/**
 * Header include for all pages
 * 
 * Usage: include 'incl/header.php';
 * 
 * This header matches the index.php header style but with a "Home" link instead of "Filters"
 */

// Get current user info if logged in
$isLoggedIn = isLoggedIn();
$currentUsername = $isLoggedIn ? getCurrentUsername() : '';
$currentFullname = $isLoggedIn ? getCurrentFullname() : '';
$isAdmin = $isLoggedIn && isAdmin();

// Detect if we're in a subdirectory and set path prefix
$scriptPath = $_SERVER['SCRIPT_FILENAME'];
$scriptDir = dirname($scriptPath);
$rootDir = dirname(__DIR__); // incl is in root, so go up one level
$pathPrefix = '';

// If we're not in the root directory, we need to go up
if (strpos($scriptDir, $rootDir) !== false && $scriptDir !== $rootDir) {
    $relativePath = str_replace($rootDir, '', $scriptDir);
    $depth = substr_count($relativePath, DIRECTORY_SEPARATOR);
    $pathPrefix = str_repeat('../', $depth);
}
?>

<!-- Full-width header -->
<div class="header" style="max-width: 100%; margin-left: 0; margin-right: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.04); border-bottom: 1px solid #e0e7ff; padding: 1.5rem; margin-bottom: 1.5rem; background: white;">
    <div class="container-fluid" style="max-width: 98%;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 style="margin: 0; color: #1f2937; font-weight: 600; font-size: 1.1rem; display: flex; align-items: center; gap: .5rem;">
                    <i class="bi bi-<?php echo isset($headerIcon) ? $headerIcon : 'house'; ?>" style="color: #3b82f6;"></i> 
                    <?php echo isset($headerTitle) ? htmlspecialchars($headerTitle) : 'GeoLite'; ?>
                </h1>
                <p style="margin: .25rem 0 0 0; color: #6b7280; font-size: .8rem;">
                    <?php echo isset($headerSubtitle) ? htmlspecialchars($headerSubtitle) : ($isLoggedIn ? 'Welcome ' . htmlspecialchars($currentFullname) : 'Welcome to GeoLite'); ?>
                </p>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <?php if($isLoggedIn){ ?>
                <?php } ?>
                <?php if ($isAdmin): ?>
                <!-- Administration Dropdown -->
                <div class="dropdown">
                    <button class="create-btn dropdown-toggle" type="button" id="createDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background: #fff; border: none; padding: 12px 10px; border-radius: 25px; color: black; font-weight: 600; text-decoration: none; display: inline-block; transition: transform 0.2s ease;">
                        <i class="bi bi-plus-circle"></i> New Resource
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="createDropdown">
                        <li><a class="dropdown-item" href="<?php echo $pathPrefix; ?>map_builder.php"><i class="bi bi-map"></i> Map</a></li>
                        <li><a class="dropdown-item" href="<?php echo $pathPrefix; ?>dashboard_builder.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?php echo $pathPrefix; ?>documents.php"><i class="bi bi-file-earmark-text"></i> Document</a></li>
                        <li><a class="dropdown-item" href="<?php echo $pathPrefix; ?>html_pages.php"><i class="bi bi-code-square"></i> HTML Page</a></li>
                    </ul>
                </div>

                <div class="dropdown">
                    <button class="create-btn dropdown-toggle" type="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background: #fff; border: none; padding: 12px 10px; border-radius: 25px; color: black; font-weight: 600; text-decoration: none; display: inline-block; transition: transform 0.2s ease;">
                        <i class="bi bi-gear"></i> Administration
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="<?php echo $pathPrefix; ?>users.php"><i class="bi bi-people"></i> Manage Users</a></li>
                        <li><a class="dropdown-item" href="<?php echo $pathPrefix; ?>admin/settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                        <li><a class="dropdown-item" href="<?php echo $pathPrefix; ?>categories.php"><i class="bi bi-tags"></i> Categories</a></li>
                    </ul>
                </div>

                &nbsp;&nbsp;

                <?php endif; ?>
                <a href="<?php echo $pathPrefix; ?>index.php" class="link-clean" style="text-decoration: none !important;">
                    <i class="bi bi-house"></i> Home
                </a>
                <?php if($isLoggedIn){ ?>
                <a href="<?php echo $pathPrefix; ?>logout.php" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
                <?php }else{ ?>
                <a href="<?php echo $pathPrefix; ?>login.php" class="btn btn-outline-success">
                    <i class="bi bi-box-arrow-left"></i> Login
                </a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

