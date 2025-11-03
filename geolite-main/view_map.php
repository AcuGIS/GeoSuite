<?php
// Start output buffering to prevent header issues
ob_start();

// Include required files
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Database.php';
// Include the map template generator
require_once 'map_template.php';

// Require authentication
//requireAuth();

// Get map ID from query string
$mapId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($mapId <= 0) {
    die('Invalid map ID');
}

// Check view permission
if (!canView('map', $mapId)) {
    ob_end_clean();
    header('Location: index.php?error=access_denied');
    exit;
}

// Get map from database
try {
    $map = getMapById($mapId);
    
    if (!$map) {
        ob_end_clean();
        die('Map not found');
    }
    
    // Clear output buffer
    ob_end_clean();
   
    
    // Parse the stored map data
    $basemaps = json_decode($map['basemaps'], true) ?: ['osm'];
    $layers = json_decode($map['layers'], true) ?: [];
    $features = json_decode($map['features'], true) ?: [];
    $initialExtent = json_decode($map['initial_extent'], true) ?: null;
    $filters = isset($map['filters']) ? json_decode($map['filters'], true) ?: [] : [];
    
    // Prepare map metadata for the template
    $mapMetadata = [
        'title' => $map['title'],
        'description' => $map['description'],
        'created_at' => $map['created_at']
    ];
    
    // Generate the template with map metadata
    $template = generateMapTemplate($mapId, $basemaps, $layers, $features, $initialExtent, $mapMetadata, $filters);
    
    // Add Return button to sidebar after layer list
    $homeButton = '
            <div class="mt-3 pt-3 border-top">
                <a href="index.php" class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2" style="text-decoration: none;">
                    <i class="bi bi-house"></i> Return to Home
                </a>
            </div>';
    $template = str_replace('          </div>
        </div>

        <script>', $homeButton . '
          </div>
        </div>

        <script>', $template);
    
    // Output the modified template
    echo $template;
    
} catch (Exception $e) {
    ob_end_clean();
    die('Error loading map: ' . htmlspecialchars($e->getMessage()));
}
