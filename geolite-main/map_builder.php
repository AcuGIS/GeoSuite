<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Start output buffering to prevent header issues
ob_start();

// Include required files
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Config.php';
require_once 'incl/Database.php';
require_once 'map_template.php';

// Require authentication
requireAuth();

// Get map ID if editing
$mapId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$existingMap = null;

if ($mapId > 0) {
    // Editing existing map - check permission
    try {
        $existingMap = getMapById($mapId);
        if (!$existingMap) {
            $error = "Map not found.";
            $mapId = 0;
        } elseif (!canEdit('map', $mapId)) {
            // User doesn't have permission to edit this map
            ob_end_clean();
            header('Location: index.php?error=access_denied');
            exit;
        }
    } catch (Exception $e) {
        $error = "Failed to load map.";
        $mapId = 0;
    }
} else {
    // Creating new map - only admins can create
    if (!isAdmin()) {
        ob_end_clean();
        header('Location: index.php?error=access_denied');
        exit;
    }
}

// Get available layers from GeoServer
$availableLayers = getAvailableLayers();

// Handle save to database request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $title = isset($_POST['map_title']) ? trim($_POST['map_title']) : 'Untitled Map';
    $description = isset($_POST['map_description']) ? trim($_POST['map_description']) : '';
    $categoryId = isset($_POST['map_category']) && $_POST['map_category'] !== '' ? intval($_POST['map_category']) : null;
    $basemaps = isset($_POST['basemaps']) ? $_POST['basemaps'] : ['osm'];
    $layers = isset($_POST['layers']) ? $_POST['layers'] : [];
    $features = isset($_POST['features']) ? $_POST['features'] : [];
    
    // Parse filters from POST (supporting multiple conditions per layer)
    $filters = [];
    if (isset($_POST['layer_filters']) && is_array($_POST['layer_filters'])) {
        foreach ($_POST['layer_filters'] as $layer => $layerFilters) {
            if (!is_array($layerFilters)) continue;
            
            $conditions = [];
            foreach ($layerFilters as $index => $condition) {
                if (!empty($condition['attribute']) && !empty($condition['operator']) && isset($condition['value'])) {
                    $conditions[] = [
                        'attribute' => $condition['attribute'],
                        'operator' => $condition['operator'],
                        'value' => $condition['value'],
                        'logic' => isset($condition['logic']) ? $condition['logic'] : 'AND'
                    ];
                }
            }
            
            if (!empty($conditions)) {
                $filters[$layer] = $conditions;
            }
        }
    }
    
    $initialExtent = [
        'center_lon' => isset($_POST['center_lon']) && $_POST['center_lon'] !== '' ? floatval($_POST['center_lon']) : null,
        'center_lat' => isset($_POST['center_lat']) && $_POST['center_lat'] !== '' ? floatval($_POST['center_lat']) : null,
        'zoom_level' => isset($_POST['zoom_level']) && $_POST['zoom_level'] !== '' ? floatval($_POST['zoom_level']) : null
    ];
        
    try {
        // Get the map ID from POST if updating
        $editMapId = isset($_POST['map_id']) ? intval($_POST['map_id']) : 0;
        
        if ($editMapId == 0) {
            // Create new map
            $editMapId = saveMap($title, $description, '', $basemaps, $layers, $features, $initialExtent, $categoryId, $filters);
        }
        
        // Generate the template
        $template = generateMapTemplate($editMapId, $basemaps, $layers, $features, $initialExtent, null, $filters);
        
        // Update existing map
        updateMap($editMapId, $title, $description, $template, $basemaps, $layers, $features, $initialExtent, $categoryId, $filters);

        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: index.php?saved=map');
        exit;
    } catch (Exception $e) {
        error_log("Error saving map: " . $e->getMessage());
        $error = "Failed to save map. Please check database configuration.";
    }
}

// Handle map generation request first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $basemaps = isset($_POST['basemaps']) ? $_POST['basemaps'] : [];
    $layers = isset($_POST['layers']) ? $_POST['layers'] : [];
    $features = isset($_POST['features']) ? $_POST['features'] : [];
    
    // Parse filters from POST (supporting multiple conditions per layer)
    $filters = [];
    if (isset($_POST['layer_filters']) && is_array($_POST['layer_filters'])) {
        foreach ($_POST['layer_filters'] as $layer => $layerFilters) {
            if (!is_array($layerFilters)) continue;
            
            $conditions = [];
            foreach ($layerFilters as $index => $condition) {
                if (!empty($condition['attribute']) && !empty($condition['operator']) && isset($condition['value'])) {
                    $conditions[] = [
                        'attribute' => $condition['attribute'],
                        'operator' => $condition['operator'],
                        'value' => $condition['value'],
                        'logic' => isset($condition['logic']) ? $condition['logic'] : 'AND'
                    ];
                }
            }
            
            if (!empty($conditions)) {
                $filters[$layer] = $conditions;
            }
        }
    }
    
    // Get initial extent settings
    $initialExtent = [
        'center_lon' => isset($_POST['center_lon']) && $_POST['center_lon'] !== '' ? floatval($_POST['center_lon']) : null,
        'center_lat' => isset($_POST['center_lat']) && $_POST['center_lat'] !== '' ? floatval($_POST['center_lat']) : null,
        'zoom_level' => isset($_POST['zoom_level']) && $_POST['zoom_level'] !== '' ? floatval($_POST['zoom_level']) : null
    ];
    
    // Ensure we have at least one basemap
    if (empty($basemaps)) {
        $basemaps = ['osm'];
    }
    
    // Generate the template with initial extent settings
    $template = generateMapTemplate(0, $basemaps, $layers, $features, $initialExtent, null, $filters);
    
    // Clear any previous output
    ob_clean();
    
    // Set proper headers for HTML content
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output the template directly
    echo $template;
    exit;
}

// If we get here, we're displaying the builder interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $mapId > 0 ? 'Edit Map' : 'Create Map'; ?> - GeoLite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
        body {
            font-family: Arial, sans-serif;
            width: 100%;
            margin: 0;
            padding: 0;
            background: #f3f4f6;
        }
        .map-builder-container {
            width: 90% !important;
            max-width: none !important;
            margin: 0 auto !important;
        }
        .map-builder-container.container {
            width: 90% !important;
            max-width: none !important;
        }
        .container {
            display: flex;
            height: calc(100vh - 100px);
            width: 100%;
            gap: 8px;
            padding: 8px 5px;
            box-sizing: border-box;
        }
        .form-container {
            flex: 0 0 200px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .form-container form {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .form-scrollable {
            flex: 1;
            overflow-y: auto;
            padding-right: 10px;
        }
        .form-buttons {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-top: 1px solid #ddd;
            position: sticky;
            bottom: 0;
        }
        .wms-layers-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .wms-layers-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding: 5px 0;
        }
        .wms-layers-pagination button {
            padding: 5px 10px;
            font-size: 14px;
            margin: 0;
        }
        .wms-layers-pagination span {
            font-size: 14px;
            color: #666;
        }
        .preview-container {
            flex: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .form-group {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group h3 {
            margin-top: 0;
        }
        label {
            display: block;
            margin: 10px 0;
        }
        input[type="checkbox"] {
            margin-right: 10px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        button:hover {
            background-color: #45a049;
        }
        .preview {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        #map-preview {
            flex: 1;
            width: 100%;
            height: 100%;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: opacity 0.3s ease;
        }
        .hidden {
            display: none;
        }
        #download-link {
            display: none;
        }
        .wms-layers-pagination button.pagination-btn {
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
            cursor: pointer;
        }
        .wms-layers-pagination button.pagination-btn:hover:not(:disabled) {
            background-color: #e0e0e0;
        }
        .wms-layers-pagination button.pagination-btn:disabled {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }
        /* Accordion Styles */
        .accordion-section {
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        .accordion-header {
            background-color: #f5f5f5;
            padding: 12px 15px;
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.2s;
        }
        .accordion-header:hover {
            background-color: #e8e8e8;
        }
        .accordion-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        .accordion-icon {
            transition: transform 0.3s ease;
            font-size: 14px;
            font-weight: bold;
        }
        .accordion-icon.expanded {
            transform: rotate(180deg);
        }
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .accordion-content.expanded {
            max-height: 1000px;
        }
        .accordion-content-inner {
            padding: 15px;
        }
    </style>
    <script>
        async function generateMap(event) {
            event.preventDefault();
            const form = document.getElementById('map-form');
            const formData = new FormData(form);
            
            // Show loading state
            const preview = document.getElementById('map-preview');
            const captureButton = document.getElementById('capture-position');
            preview.style.opacity = '0.5';
            captureButton.disabled = true;
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const html = await response.text();
                
                // Check if we got a proper HTML response
                if (html.includes('<!DOCTYPE html>')) {
                    // Update the preview iframe with the actual HTML content
                    preview.srcdoc = html;
                    preview.onload = function() {
                        preview.style.opacity = '1';
                        document.getElementById('save-button').classList.remove('hidden');
                        // Enable the capture position button after map is loaded
                        captureButton.disabled = false;
                    };
                } else {
                    throw new Error('Invalid response format');
                }
            } catch (error) {
                console.error('Error:', error);
                preview.style.opacity = '1';
                captureButton.disabled = false;
                alert('Error generating map preview. Please try again.');
            }
        }

        function saveMapToDatabase() {
            const form = document.getElementById('map-form');
            const mapTitle = document.getElementById('map_title').value.trim();
            
            if (!mapTitle) {
                alert('Please enter a map title before saving.');
                return;
            }
            
            // Create a new FormData object with current form data
            const formData = new FormData(form);
            formData.append('action', 'save');
            
            // Submit the form
            form.action = window.location.href;
            form.method = 'POST';
            
            // Create a hidden input for the action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'save';
            form.appendChild(actionInput);
            
            form.submit();
        }

        // Function to update form fields with map position
        function updateFormWithMapPosition() {
            const preview = document.getElementById('map-preview');
            if (preview && preview.contentWindow && preview.contentWindow.map) {
                try {
                    // Access the map through the iframe's window
                    const iframeWindow = preview.contentWindow;
                    const map = iframeWindow.map;
                    const view = map.getView();
                    const center = iframeWindow.ol.proj.transform(view.getCenter(), 'EPSG:3857', 'EPSG:4326');
                    
                    document.getElementById('center_lon').value = center[0].toFixed(6);
                    document.getElementById('center_lat').value = center[1].toFixed(6);
                    document.getElementById('zoom_level').value = view.getZoom().toFixed(1);
                } catch (error) {
                    console.error('Error capturing map position:', error);
                    alert('Error capturing map position. Please try again.');
                }
            } else {
                alert('Please generate a map preview first');
            }
        }

        // Add event listener for the capture position button
        document.addEventListener('DOMContentLoaded', function() {
            const captureButton = document.getElementById('capture-position');
            if (captureButton) {
                captureButton.addEventListener('click', updateFormWithMapPosition);
                // Initially disable the button
                captureButton.disabled = true;
            }
            
            // Initialize accordion functionality
            initAccordion();
        });
        
        // Accordion functionality
        function initAccordion() {
            const accordionHeaders = document.querySelectorAll('.accordion-header');
            
            accordionHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const icon = this.querySelector('.accordion-icon');
                    
                    // Toggle the content
                    content.classList.toggle('expanded');
                    icon.classList.toggle('expanded');
                });
            });
        }
        
        // Filter condition management
        let filterIndexes = {};
        
        function addFilterCondition(sanitizedLayerId) {
            const originalLayerId = sanitizedLayerId.replace(/_/g, ':');
            const container = document.querySelector('.filter-conditions-' + sanitizedLayerId);
            if (!container) return;
            
            if (!filterIndexes[sanitizedLayerId]) {
                filterIndexes[sanitizedLayerId] = container.querySelectorAll('div').length;
            }
            
            const index = filterIndexes[sanitizedLayerId]++;
            const conditionHtml = `
                <div style="margin-bottom: 10px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 4px; background: white;">
                    <div style="margin-bottom: 6px;">
                        <select name="layer_filters[${originalLayerId}][${index}][logic]" style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 11px;">
                            <option value="AND">AND</option>
                            <option value="OR">OR</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px; margin-bottom: 8px;">
                        <input type="text" name="layer_filters[${originalLayerId}][${index}][attribute]" placeholder="Attribute name" style="padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                        <select name="layer_filters[${originalLayerId}][${index}][operator]" style="padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                            <option value="=">equals (=)</option>
                            <option value=">">greater (&gt;)</option>
                            <option value="<">less (&lt;)</option>
                            <option value=">=">&gt;=</option>
                            <option value="<=">&lt;=</option>
                            <option value="!=">not equals (!=)</option>
                            <option value="LIKE">contains (LIKE)</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" name="layer_filters[${originalLayerId}][${index}][value]" placeholder="Filter value" style="flex: 1; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                        <button type="button" onclick="this.parentElement.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 11px;">
                            Remove
                        </button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', conditionHtml);
        }

        // Add pagination functionality for WMS Layers
        document.addEventListener('DOMContentLoaded', function() {
            const itemsPerPage = 10;
            const wmsLayersContainer = document.querySelector('.wms-layers-container');
            const wmsLabels = wmsLayersContainer ? wmsLayersContainer.querySelectorAll('label') : [];
            const totalPages = Math.ceil(wmsLabels.length / itemsPerPage);
            let currentPage = 1;

            function updateWmsLayersDisplay() {
                if (!wmsLayersContainer) return;
                
                const start = (currentPage - 1) * itemsPerPage;
                const end = start + itemsPerPage;
                
                wmsLabels.forEach((label, index) => {
                    label.style.display = (index >= start && index < end) ? 'block' : 'none';
                });

                const pageInfo = document.querySelector('.wms-layers-pagination span');
                if (pageInfo) {
                    pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
                }

                const prevButton = document.querySelector('.wms-layers-pagination button:first-child');
                const nextButton = document.querySelector('.wms-layers-pagination button:last-child');
                
                if (prevButton) prevButton.disabled = currentPage === 1;
                if (nextButton) nextButton.disabled = currentPage === totalPages;
            }

            function handlePaginationClick(event, direction) {
                event.preventDefault();
                event.stopPropagation();
                
                if (direction === 'prev' && currentPage > 1) {
                    currentPage--;
                } else if (direction === 'next' && currentPage < totalPages) {
                    currentPage++;
                }
                updateWmsLayersDisplay();
            }

            // Add pagination controls if there are WMS layers
            if (wmsLabels.length > 0) {
                const paginationDiv = document.createElement('div');
                paginationDiv.className = 'wms-layers-pagination';
                paginationDiv.innerHTML = `
                    <button type="button" class="pagination-btn" data-direction="prev" disabled>Previous</button>
                    <span>Page 1 of ${totalPages}</span>
                    <button type="button" class="pagination-btn" data-direction="next" ${totalPages === 1 ? 'disabled' : ''}>Next</button>
                `;
                wmsLayersContainer.parentNode.insertBefore(paginationDiv, wmsLayersContainer.nextSibling);

                // Add click handlers to pagination buttons
                const paginationButtons = paginationDiv.querySelectorAll('.pagination-btn');
                paginationButtons.forEach(button => {
                    button.addEventListener('click', (e) => {
                        handlePaginationClick(e, button.dataset.direction);
                    });
                });
                
                // Initial display
                updateWmsLayersDisplay();
            }
        });
    </script>
</head>
<body>
    <?php 
    $headerTitle = 'Map Builder';
    $headerSubtitle = $mapId > 0 ? 'Edit Map' : 'Create New Map';
    $headerIcon = 'map';
    include 'incl/header.php'; 
    ?>
    
    <?php if (isset($error)): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border: 1px solid #f5c6cb; border-radius: 4px;">
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    <div class="container map-builder-container">
        <div class="form-container">
            <form method="post" action="" id="map-form" onsubmit="generateMap(event)">
                <?php if ($mapId > 0): ?>
                    <input type="hidden" name="map_id" value="<?php echo $mapId; ?>">
                <?php endif; ?>
                <div class="form-scrollable">
                    <?php
                    // Parse existing map data if editing
                    $existingBasemaps = [];
                    $existingLayers = [];
                    $existingFeatures = [];
                    $existingExtent = null;
                    
                    if ($existingMap) {
                        $existingBasemaps = json_decode($existingMap['basemaps'], true) ?: [];
                        $existingLayers = json_decode($existingMap['layers'], true) ?: [];
                        $existingFeatures = json_decode($existingMap['features'], true) ?: [];
                        $existingExtent = json_decode($existingMap['initial_extent'], true) ?: null;
                    }
                    ?>
                    <div class="accordion-section">
                        <div class="accordion-header">
                            <h3>Map Info</h3>
                            <span class="accordion-icon expanded">▼</span>
                        </div>
                        <div class="accordion-content expanded">
                            <div class="accordion-content-inner">
                                <p style="color: #666; margin-bottom: 10px;">Enter a title and description for your map.</p>
                                <div>
                                    <label for="map_title">Map Title:*</label>
                                    <input type="text" id="map_title" name="map_title" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;" placeholder="Enter map title"
                                        value="<?php echo $existingMap ? htmlspecialchars($existingMap['title']) : ''; ?>">
                                </div>
                                <div>
                                    <label for="map_description">Description:</label>
                                    <textarea id="map_description" name="map_description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Optional description"><?php echo $existingMap ? htmlspecialchars($existingMap['description']) : ''; ?></textarea>
                                </div>
                                <div>
                                    <label for="map_category">Category:</label>
                                    <select id="map_category" name="map_category" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value="">Select a category (optional)</option>
                                        <?php
                                        try {
                                            $categories = getCategoriesForDropdown();
                                            foreach ($categories as $category) {
                                                $selected = ($existingMap && $existingMap['category_id'] == $category['id']) ? 'selected' : '';
                                                echo '<option value="' . $category['id'] . '" ' . $selected . '>' . htmlspecialchars($category['name']) . '</option>';
                                            }
                                        } catch (Exception $e) {
                                            // Categories table might not exist yet
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-section">
                        <div class="accordion-header">
                            <h3>WMS Layers</h3>
                            <span class="accordion-icon">▼</span>
                        </div>
                        <div class="accordion-content">
                            <div class="accordion-content-inner">
                                <?php if (empty($availableLayers)): ?>
                                    <p style="color: #666;">No layers available from GeoServer. Please check your connection.</p>
                                <?php else: ?>
                                    <div class="wms-layers-container">
                                        <?php foreach ($availableLayers as $layer): ?>
                                            <label>
                                                <input type="checkbox" name="layers[]" value="<?php echo htmlspecialchars($layer['id']); ?>" 
                                                    <?php 
                                                    if ($existingMap) {
                                                        echo in_array($layer['id'], $existingLayers) ? 'checked' : '';
                                                    } else {
                                                        echo ($layer['id'] === 'topp:states' || $layer['id'] === 'tiger:poi') ? 'checked' : ''; 
                                                    }
                                                    ?>>
                                                <?php echo htmlspecialchars($layer['title']); ?> (<?php echo htmlspecialchars($layer['workspace']); ?>)
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-section">
                        <div class="accordion-header">
                            <h3>Basemap Options</h3>
                            <span class="accordion-icon">▼</span>
                        </div>
                        <div class="accordion-content">
                            <div class="accordion-content-inner">
                                <label>
                                    <input type="checkbox" name="basemaps[]" value="osm" <?php echo (empty($existingBasemaps) || in_array('osm', $existingBasemaps)) ? 'checked' : ''; ?>> OpenStreetMap
                                </label>
                                <label>
                                    <input type="checkbox" name="basemaps[]" value="carto-light" <?php echo in_array('carto-light', $existingBasemaps) ? 'checked' : ''; ?>> Carto Light
                                </label>
                                <label>
                                    <input type="checkbox" name="basemaps[]" value="carto-dark" <?php echo in_array('carto-dark', $existingBasemaps) ? 'checked' : ''; ?>> Carto Dark
                                </label>
                                <label>
                                    <input type="checkbox" name="basemaps[]" value="carto-voyager" <?php echo in_array('carto-voyager', $existingBasemaps) ? 'checked' : ''; ?>> Carto Voyager
                                </label>
                                <label>
                                    <input type="checkbox" name="basemaps[]" value="esri-satellite" <?php echo in_array('esri-satellite', $existingBasemaps) ? 'checked' : ''; ?>> ESRI Satellite
                                </label>
                                <label>
                                    <input type="checkbox" name="basemaps[]" value="esri-topo" <?php echo in_array('esri-topo', $existingBasemaps) ? 'checked' : ''; ?>> ESRI Topo
                                </label>                              
                            </div>
                        </div>
                    </div>

                    <div class="accordion-section">
                        <div class="accordion-header">
                            <h3>Map Features</h3>
                            <span class="accordion-icon">▼</span>
                        </div>
                        <div class="accordion-content">
                            <div class="accordion-content-inner">
                                <label>
                                    <input type="checkbox" name="features[]" value="popup" <?php echo (empty($existingFeatures) || in_array('popup', $existingFeatures)) ? 'checked' : ''; ?>> Enable Click Popups
                                </label>
                                <label>
                                    <input type="checkbox" name="features[]" value="zoom_buttons" <?php echo (empty($existingFeatures) || in_array('zoom_buttons', $existingFeatures)) ? 'checked' : ''; ?>> Include Zoom Buttons
                                </label>
                                <label>
                                    <input type="checkbox" name="features[]" value="opacity_controls" <?php echo (empty($existingFeatures) || in_array('opacity_controls', $existingFeatures)) ? 'checked' : ''; ?>> Include Opacity Controls
                                </label>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Parse existing filters if editing
                    $existingFilters = [];
                    if ($existingMap && isset($existingMap['filters'])) {
                        $existingFilters = json_decode($existingMap['filters'], true) ?: [];
                    }
                    ?>
                    <div class="accordion-section">
                        <div class="accordion-header">
                            <h3>Layer Filters</h3>
                            <span class="accordion-icon">▼</span>
                        </div>
                        <div class="accordion-content">
                            <div class="accordion-content-inner">
                                <p style="color: #666; margin-bottom: 10px;">Set filters for selected WMS layers to show only specific features.</p>
                                <?php 
                                // Only show filters for layers that are selected for this map
                                $selectedLayers = !empty($existingLayers) ? $existingLayers : [];
                                if (!empty($selectedLayers)): ?>
                                    <?php
                                    // Create lookup map
                                    $layerLookup = [];
                                    foreach ($availableLayers as $layer) {
                                        $layerLookup[$layer['id']] = $layer;
                                    }
                                    ?>
                                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #eee; border-radius: 4px; padding: 10px;">
                                        <?php foreach ($selectedLayers as $layerId): 
                                            if (!isset($layerLookup[$layerId])) continue;
                                            $layer = $layerLookup[$layerId];
                                            $hasFilter = isset($existingFilters[$layerId]);
                                            // Support both old single filter and new array format
                                            $conditions = $hasFilter ? 
                                                (is_array($existingFilters[$layerId]) && isset($existingFilters[$layerId][0]) ? 
                                                    $existingFilters[$layerId] : 
                                                    [$existingFilters[$layerId]]) : 
                                                [['attribute' => '', 'operator' => '=', 'value' => '', 'logic' => 'AND']];
                                        ?>
                                        <div style="margin-bottom: 20px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                <label style="font-weight: 600;">
                                                    <?php echo htmlspecialchars($layer['title']); ?> (<?php echo htmlspecialchars($layer['workspace']); ?>)
                                                </label>
                                                <button type="button" onclick="addFilterCondition('<?php echo str_replace(':', '_', htmlspecialchars($layerId)); ?>')" style="background: #0d6efd; color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px;">
                                                    + Add Condition
                                                </button>
                                            </div>
                                            <div class="filter-conditions-<?php echo str_replace(':', '_', htmlspecialchars($layerId)); ?>">
                                                <?php foreach ($conditions as $idx => $condition): 
                                                    $isFirst = $idx === 0;
                                                ?>
                                                <div style="margin-bottom: 10px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 4px; background: white;">
                                                    <?php if (!$isFirst): ?>
                                                    <div style="margin-bottom: 6px;">
                                                        <select name="layer_filters[<?php echo htmlspecialchars($layerId); ?>][<?php echo $idx; ?>][logic]" style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 11px;">
                                                            <option value="AND" <?php echo (!isset($condition['logic']) || $condition['logic'] === 'AND') ? 'selected' : ''; ?>>AND</option>
                                                            <option value="OR" <?php echo (isset($condition['logic']) && $condition['logic'] === 'OR') ? 'selected' : ''; ?>>OR</option>
                                                        </select>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px; margin-bottom: 8px;">
                                                        <input type="text" name="layer_filters[<?php echo htmlspecialchars($layerId); ?>][<?php echo $idx; ?>][attribute]" 
                                                               placeholder="Attribute name" 
                                                               value="<?php echo htmlspecialchars($condition['attribute'] ?? ''); ?>"
                                                               style="padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                                        <select name="layer_filters[<?php echo htmlspecialchars($layerId); ?>][<?php echo $idx; ?>][operator]" 
                                                                style="padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                                            <option value="=" <?php echo (!isset($condition['operator']) || $condition['operator'] === '=') ? 'selected' : ''; ?>>equals (=)</option>
                                                            <option value=">" <?php echo (isset($condition['operator']) && $condition['operator'] === '>') ? 'selected' : ''; ?>>greater (&gt;)</option>
                                                            <option value="<" <?php echo (isset($condition['operator']) && $condition['operator'] === '<') ? 'selected' : ''; ?>>less (&lt;)</option>
                                                            <option value=">=" <?php echo (isset($condition['operator']) && $condition['operator'] === '>=') ? 'selected' : ''; ?>>&gt;=</option>
                                                            <option value="<=" <?php echo (isset($condition['operator']) && $condition['operator'] === '<=') ? 'selected' : ''; ?>>&lt;=</option>
                                                            <option value="!=" <?php echo (isset($condition['operator']) && $condition['operator'] === '!=') ? 'selected' : ''; ?>>not equals (!=)</option>
                                                            <option value="LIKE" <?php echo (isset($condition['operator']) && $condition['operator'] === 'LIKE') ? 'selected' : ''; ?>>contains (LIKE)</option>
                                                        </select>
                                                    </div>
                                                    <div style="display: flex; gap: 8px;">
                                                        <input type="text" name="layer_filters[<?php echo htmlspecialchars($layerId); ?>][<?php echo $idx; ?>][value]" 
                                                               placeholder="Filter value" 
                                                               value="<?php echo htmlspecialchars($condition['value'] ?? ''); ?>"
                                                               style="flex: 1; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                                        <button type="button" onclick="this.parentElement.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 11px;">
                                                            Remove
                                                        </button>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="color: #999; font-size: 12px;">Select layers in "WMS Layers" section to configure filters.</p>
                                <?php endif; ?>
                                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                                    <i class="bi bi-info-circle"></i> Leave fields empty to show all features for that layer.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-section">
                        <div class="accordion-header">
                            <h3>Initial Map Extent</h3>
                            <span class="accordion-icon">▼</span>
                        </div>
                        <div class="accordion-content">
                            <div class="accordion-content-inner">
                                <p style="color: #666; margin-bottom: 10px;">Set the initial map center and zoom level. Leave empty to use the first layer's extent.</p>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div>
                                        <label for="center_lon">Longitude:</label>
                                        <input type="number" id="center_lon" name="center_lon" step="0.000001" placeholder="e.g., -95.7129" 
                                            value="<?php echo ($existingExtent && isset($existingExtent['center_lon'])) ? $existingExtent['center_lon'] : ''; ?>">
                                    </div>
                                    <div>
                                        <label for="center_lat">Latitude:</label>
                                        <input type="number" id="center_lat" name="center_lat" step="0.000001" placeholder="e.g., 37.0902"
                                            value="<?php echo ($existingExtent && isset($existingExtent['center_lat'])) ? $existingExtent['center_lat'] : ''; ?>">
                                    </div>
                                    <div>
                                        <label for="zoom_level">Zoom Level:</label>
                                        <input type="number" id="zoom_level" name="zoom_level" min="0" max="20" step="0.1" placeholder="e.g., 4"
                                            value="<?php echo ($existingExtent && isset($existingExtent['zoom_level'])) ? $existingExtent['zoom_level'] : ''; ?>">
                                    </div>
                                </div>
                                <button type="button" id="capture-position" style="margin-top: 10px; background-color: #2196F3;">Capture Current Map Position</button>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="form-buttons">
                    <button type="submit">Generate Map</button>
                    <button type="button" id="save-button" class="hidden" onclick="saveMapToDatabase()" style="background-color: #2196F3;"><?php echo $mapId > 0 ? 'Update Map' : 'Save to Database'; ?></button>
                    
                </div>
            </form>
        </div>
        <div class="preview-container">
            <h3>Map Preview</h3>
            <iframe id="map-preview" srcdoc="<html><body style='margin:0;padding:0;'><div style='width:100%;height:100%;display:flex;align-items:center;justify-content:center;'>Select options and click Generate Map to preview</div></body></html>"></iframe>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// End output buffering
ob_end_flush();
?>
