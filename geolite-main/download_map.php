<?php

// Include required files
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Config.php';
require_once 'incl/Database.php';

// Require authentication
requireAuth();

// Only admins can download/generate maps
if (!isAdmin()) {
    die('Access denied');
}

// Get dashboard ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    http_response_code(404);
    die('Bad request! Map ID is required.');
}

// Check view permission
if (!canView('dashboard', $id)) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Get the form data
$basemaps = isset($_POST['basemaps']) ? $_POST['basemaps'] : [];
$layers = isset($_POST['layers']) ? $_POST['layers'] : [];
$features = isset($_POST['features']) ? $_POST['features'] : [];

// Include only the template generation functions
//require_once 'map_builder.php';

// Generate the template directly
$template = generateMapTemplate($id, $basemaps, $layers, $features);

// Set headers for download
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="custom_map.html"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Remove any PHP session headers that might interfere
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Output only the template
echo $template;
exit;
?>
