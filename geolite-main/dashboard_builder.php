<?php
// Start output buffering to prevent header issues
ob_start();

// Include required files
require_once 'incl/const.php';
require_once 'incl/Database.php';
require_once 'incl/Auth.php';
require_once 'incl/Config.php';

// Require authentication
requireAuth();

// Get dashboard ID if editing
$dashboardId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$dashboard = null;
$dashboardConfig = null;

if ($dashboardId > 0) {
    // Editing existing dashboard - check permission
    try {
        $dashboard = getDashboardById($dashboardId);
        if ($dashboard) {
            if (!canEdit('dashboard', $dashboardId)) {
                // User doesn't have permission to edit this dashboard
                ob_end_clean();
                header('Location: index.php?error=access_denied');
                exit;
            }
            $dashboardConfig = json_decode($dashboard['config'], true);
        }
    } catch (Exception $e) {
        $error = "Failed to load dashboard.";
    }
} else {
    // Creating new dashboard - only admins can create
    if (!isAdmin()) {
        ob_end_clean();
        header('Location: index.php?error=access_denied');
        exit;
    }
}

// Get available layers from GeoServer
$availableLayers = getAvailableLayers();
$geoServerConfig = getGeoServerConfig();

// Handle save request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : 'Untitled Dashboard';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
        $config = isset($_POST['config']) ? json_decode($_POST['config'], true) : [];
        
        try {
            if ($dashboardId > 0) {
                // Update existing dashboard
                updateDashboard($dashboardId, $title, $description, $config, $categoryId);
                ob_end_clean();
                header('Location: index.php?saved=dashboard');
                exit;
            } else {
                // Create new dashboard
                $newId = saveDashboard($title, $description, $config, $categoryId);
                ob_end_clean();
                header('Location: index.php?saved=dashboard');
                exit;
            }
        } catch (Exception $e) {
            error_log("Error saving dashboard: " . $e->getMessage());
            $error = "Failed to save dashboard. Please check database configuration.";
        }
    }
}

// Flush output buffer
ob_end_flush();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $dashboardId > 0 ? 'Edit' : 'Create'; ?> Dashboard - GeoLite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f6f7fb;
            --panel: #fff;
            --muted: #6b7280;
            --text: #1f2937;
            --accent: #667eea;
            --shadow: 0 10px 24px rgba(0,0,0,.08);
            --radius: 14px;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
        }
        .topbar {
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            background: #fff;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .btn {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 8px 12px;
            background: #fff;
            cursor: pointer;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .wrap {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 16px;
            padding: 16px;
            height: calc(100vh - 200px);
            transition: grid-template-columns 0.3s ease;
        }
        .wrap.sidebar-collapsed {
            grid-template-columns: 0px 1fr;
        }
        .sidebar {
            background: #fff;
            border-radius: 14px;
            box-shadow: var(--shadow);
            padding: 16px;
            height: 100%;
            overflow: auto;
            position: relative;
            transition: all 0.3s ease;
        }
        .wrap.sidebar-collapsed .sidebar {
            margin-left: -320px;
            opacity: 0;
            pointer-events: none;
        }
        .sidebar-toggle {
            position: absolute;
            left: 16px;
            top: 80px;
            z-index: 100;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 8px 12px;
            cursor: pointer;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        .sidebar-toggle:hover {
            background: #f9fafb;
            border-color: var(--accent);
        }
        .sidebar-collapsed .sidebar-toggle {
            left: 16px;
        }
        .picker {
            padding: 12px;
            border: 1px dashed #e5e7eb;
            border-radius: 12px;
            background: #fafafa;
            cursor: pointer;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .picker:hover {
            background: #f3f4f6;
            border-color: var(--accent);
        }
        .canvas {
            position: relative;
            height: 100%;
            border-radius: 12px;
            background: white;
            box-shadow: var(--shadow);
            overflow: auto;
        }
        .item {
            position: absolute;
            background: #fff;
            border-radius: 0px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            overflow: hidden;
            border-top: 2px solid #d97706;
        }
        .item[data-kind="map"] {
            overflow: visible;
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 8px;
            border-bottom: 1px solid #eef0f4;
            cursor: move;
            background: #f9fafb;
        }
        .title {
            font-weight: 600;
            padding: 2px 4px;
            border-radius: 4px;
            transition: background 0.2s;
            font-size: 12px;
            border: 1px solid transparent;
            outline: none;
        }
        .title:hover {
            background: #f3f4f6;
        }
        .title:focus {
            background: #fff;
            border-color: var(--accent);
        }
        .tools {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .tbtn {
            border: none;
            background: #f3f4f6;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #6b7280;
            transition: all 0.2s;
        }
        .tbtn:hover {
            background: #e5e7eb;
            color: #374151;
        }
        .tbtn.maximize {
            position: relative;
        }
        .tbtn.maximize::before {
            content: '⛶';
            font-size: 16px;
        }
        .tbtn.maximize.maximized::before {
            content: '⛷';
            font-size: 16px;
        }
        .item.maximized {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1000;
            border-radius: 0;
        }
        .item.maximized .card-header {
            background: #fff;
            border-bottom: 1px solid #eef0f4;
        }
        .body {
            height: calc(100% - 40px);
            overflow: auto;
        }
        .item[data-kind="map"] .body {
            overflow: visible;
        }
        .item[data-kind="map"] .pad { padding: 0; height: 100%; }
        
        .item[data-kind="text"] .pad {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .item[data-kind="text"] .pad > div {
            flex: 1;
            width: 100%;
        }
        
        /* Ensure Quill-generated content displays properly */
        .item[data-kind="text"] .pad > div p,
        .item[data-kind="text"] .pad > div ul,
        .item[data-kind="text"] .pad > div ol {
            margin: 0;
            padding: 0;
            width: 100%;
        }
        
        .item[data-kind="text"] .pad > div ul,
        .item[data-kind="text"] .pad > div ol {
            padding-left: 20px;
        }

        .pad {
            padding: 12px;
        }
        .resize {
            position: absolute;
            right: 6px;
            bottom: 6px;
            width: 16px;
            height: 16px;
            cursor: nwse-resize;
            background: linear-gradient(135deg,transparent 50%,#cbd5e1 50%),
                        linear-gradient(45deg,transparent 50%,#cbd5e1 50%);
            background-size: 8px 8px;
            background-repeat: no-repeat;
            background-position: left bottom, right top;
            border-radius: 4px;
            z-index: 40000;
        }
        .leaflet-container {
            height: 100%;
            width: 100%;
        }
        .help {
            font-size: 12px;
            color: #6b7280;
        }
        /* Modal styles */
        .backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.35);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .modal-custom {
            width: min(820px,calc(100% - 24px));
            background: #fff;
            border-radius: 14px;
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
            z-index: 10000;
        }
        .mh {
            padding: 12px 16px;
            border-bottom: 1px solid #eef0f4;
            font-weight: 600;
        }
        .mb {
            padding: 14px 16px;
            max-height: 70vh;
            overflow: auto;
        }
        .mf {
            padding: 12px 16px;
            border-top: 1px solid #eef0f4;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .row-custom {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        .row-custom input, .row-custom select, .row-custom textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font: inherit;
        }
        #dbg {
            display: none;
            margin: 8px 16px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #ffeeba;
            border-left: 4px solid #ffecb5;
            background: #fff3cd;
            color: #856404;
            font: 12px/1.4 system-ui;
        }
        
        /* Feature popup styles */
        .feature-popup, .multi-feature-popup {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
            min-width: 200px;
            max-width: 300px;
        }
        
        .popup-header {
            background: var(--accent);
            color: white;
            padding: 8px 12px;
            margin: -10px -10px 10px -10px;
            border-radius: 8px 8px 0 0;
        }
        
        .popup-header h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
        }
        
        .popup-content {
            padding: 0;
        }
        
        .popup-row {
            padding: 4px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 12px;
        }
        
        .popup-row:last-child {
            border-bottom: none;
        }
        
        .popup-row strong {
            color: #333;
        }
        
        .popup-footer {
            margin-top: 10px;
            text-align: center;
        }
        
        .popup-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }
        
        .popup-btn:hover {
            background: #5a67d8;
        }
        
        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .feature-table-modal {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 80%;
            max-height: 80%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            background: var(--accent);
            color: white;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
        
        .modal-content {
            padding: 16px;
            overflow: auto;
            flex: 1;
        }
        
        .feature-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .feature-table th {
            background: #f8f9fa;
            padding: 8px;
            text-align: left;
            border: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .feature-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }
        
        .feature-table tr:nth-child(even) {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php 
    $headerTitle = 'Dashboard Builder';
    $headerSubtitle = $dashboardId > 0 ? 'Edit Dashboard' : 'Create Dashboard';
    $headerIcon = 'speedometer2';
    include 'incl/header.php'; 
    ?>
    
    <div class="topbar" style="background: white; padding: 10px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <div style="display: flex; gap: 10px; justify-content: center; align-items: center;">
            <button class="btn" id="testBtn">Test Widgets</button>
            <button class="btn" id="clearBtn">Clear</button>
            <button class="btn" id="loadBtn" style="display: none;">Load</button>
            <button class="btn btn-primary" id="saveBtn">Save Dashboard</button>
        </div>
    </div>
    
    <div id="dbg"></div>

    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-layout-sidebar"></i>
    </button>

    <div class="wrap" id="mainWrap">
        <aside class="sidebar">
            <h6 style="margin-top: 0;">Dashboard Widgets</h6>
            <div class="picker" data-kind="map">
                <i class="bi bi-geo-alt"></i> <strong>Map</strong>
                <div class="help">Add an interactive map</div>
            </div>
            <div class="picker" data-kind="chart">
                <i class="bi bi-bar-chart"></i> <strong>Chart</strong>
                <div class="help">Add a chart (bar, line, pie)</div>
            </div>
            <div class="picker" data-kind="table">
                <i class="bi bi-table"></i> <strong>Table</strong>
                <div class="help">Add a data table</div>
            </div>
            <div class="picker" data-kind="counter">
                <i class="bi bi-123"></i> <strong>Counter</strong>
                <div class="help">Count, Sum, or Average</div>
            </div>
            <div class="picker" data-kind="text">
                <i class="bi bi-type"></i> <strong>HTML</strong>
                <div class="help">Add formatted HTML content</div>
            </div>
            <hr style="margin: 16px 0; border: none; border-top: 1px solid #eef0f4">
            <div class="help">
                Drag widgets onto the canvas to build your dashboard. 
                Click to configure, drag to reposition, and resize from the corner.
            </div>
        </aside>
        <main class="canvas" id="canvas"></main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
    <!-- Quill Editor -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        // Configuration from PHP
        const DASHBOARD_EDITOR = true;
        const DASHBOARD_ID = <?php echo $dashboardId; ?>;
        const AVAILABLE_LAYERS = <?php echo json_encode($availableLayers); ?>;
        const INITIAL_CONFIG = <?php echo $dashboardConfig ? json_encode($dashboardConfig) : 'null'; ?>;
        const canvas = document.getElementById('canvas');
        
        // Debug information
        console.log('Dashboard Builder loaded');
        console.log('Available layers:', AVAILABLE_LAYERS);
        console.log('Initial config:', INITIAL_CONFIG);

        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mainWrap = document.getElementById('mainWrap');
        let sidebarCollapsed = false;

        sidebarToggle.addEventListener('click', () => {
            sidebarCollapsed = !sidebarCollapsed;
            mainWrap.classList.toggle('sidebar-collapsed', sidebarCollapsed);
            
            // Update button icon
            const icon = sidebarToggle.querySelector('i');
            if (sidebarCollapsed) {
                icon.className = 'bi bi-layout-sidebar-inset';
            } else {
                icon.className = 'bi bi-layout-sidebar';
            }
            
            // Trigger resize for any maps to adjust to new canvas size
            setTimeout(() => {
                items.forEach(item => {
                    if (item.kind === 'map') {
                        const mapDiv = document.getElementById('map-' + item.id);
                        if (mapDiv && mapDiv._leaflet_map) {
                            mapDiv._leaflet_map.invalidateSize();
                        }
                    }
                });
            }, 350); // Wait for transition to complete
        });

        // Function to build CQL filter for data fetching
        function buildCqlFilterForData(filters, layerId) {
            if (!filters || !filters[layerId]) return '';
            
            const conditions = filters[layerId];
            const filterParts = [];
            
            conditions.forEach((filter, idx) => {
                if (!filter.attribute || !filter.value) return;
                
                const attribute = filter.attribute;
                const operator = filter.operator || '=';
                const value = filter.value;
                
                let condition = '';
                switch (operator) {
                    case '=':
                        condition = attribute + ' = \'' + value.replace(/'/g, "''") + '\'';
                        break;
                    case '!=':
                        condition = attribute + ' != \'' + value.replace(/'/g, "''") + '\'';
                        break;
                    case '>':
                    case '<':
                    case '>=':
                    case '<=':
                        condition = attribute + ' ' + operator + ' ' + value;
                        break;
                    case 'LIKE':
                        condition = attribute + ' LIKE \'%' + value.replace(/'/g, "''") + '%\'';
                        break;
                    default:
                        condition = attribute + ' = \'' + value.replace(/'/g, "''") + '\'';
                }
                
                if (idx > 0 && filter.logic) {
                    filterParts.push(filter.logic + ' ' + condition);
                } else {
                    filterParts.push(condition);
                }
            });
            
            return filterParts.join(' ');
        }

        // Function to fetch data from GeoServer WFS via proxy
        async function fetchLayerData(layerName, limit = 100) {
            try {
                const proxyUrl = 'geoserver_proxy.php?dash_id=' + DASHBOARD_ID;
                let wfsUrl = `${proxyUrl}&service=WFS&version=1.0.0&request=GetFeature&typeName=${layerName}&outputFormat=application/json&maxFeatures=${limit}`;
                
                // Build filter map from all map widgets
                const layerFilters = {};
                items.forEach(item => {
                    if (item.kind === 'map' && item.config.filters) {
                        Object.keys(item.config.filters).forEach(layerId => {
                            layerFilters[layerId] = item.config.filters[layerId];
                        });
                    }
                });
                
                // Apply CQL filter if configured for this layer in any map widget
                if (layerFilters[layerName]) {
                    const cqlFilter = buildCqlFilterForData(layerFilters, layerName);
                    if (cqlFilter) {
                        wfsUrl += `&CQL_FILTER=${encodeURIComponent(cqlFilter)}`;
                        console.log('Applying filter when fetching data for', layerName + ':', cqlFilter);
                    }
                }
                
                console.log('Fetching data from:', wfsUrl);
                
                const response = await fetch(wfsUrl);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Layer data fetched:', data);
                return data;
            } catch (error) {
                console.error('Error fetching layer data:', error);
                return null;
            }
        }

        // Function to format numbers with comma separators
        function formatNumber(value) {
            if (typeof value === 'string') {
                // If it's a string (like "Error"), return as is
                return value;
            }
            if (value == null || isNaN(value)) {
                return '0';
            }
            // Use toLocaleString to add comma separators
            return Number(value).toLocaleString();
        }

        // Filter index tracking
        let filterIndexes = {};
        
        // Function to generate layer filters HTML for the map widget
        function generateLayerFiltersHTML(item) {
            const selectedLayers = item.config.layers || [];
            const filters = item.config.filters || {};
            
            if (selectedLayers.length === 0) {
                return '<p style="color: #999; text-align: center; padding: 20px;">Select layers above to configure filters.</p>';
            }
            
            let html = '';
            selectedLayers.forEach(layerId => {
                const layer = AVAILABLE_LAYERS.find(l => l.id === layerId);
                if (!layer) return;
                
                const layerFilters = filters[layerId] || [{'attribute': '', 'operator': '=', 'value': '', 'logic': 'AND'}];
                
                html += `
                    <div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="font-weight: 600; font-size: 12px;">
                                ${layer.title} (${layer.workspace})
                            </label>
                            <button type="button" onclick="addFilterCondition('${layerId.replace(/:/g, '_')}')" 
                                    style="background: #0d6efd; color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px;">
                                + Add Condition
                            </button>
                        </div>
                        <div class="filter-conditions-${layerId.replace(/:/g, '_')}">
                            ${layerFilters.map((condition, idx) => {
                                const isFirst = idx === 0;
                                return `
                                    <div style="margin-bottom: 10px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 4px; background: white;">
                                        ${!isFirst ? `
                                        <div style="margin-bottom: 6px;">
                                            <select name="layer_filters[${layerId}][${idx}][logic]" 
                                                    style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 11px;">
                                                <option value="AND" ${(!condition.logic || condition.logic === 'AND') ? 'selected' : ''}>AND</option>
                                                <option value="OR" ${(condition.logic === 'OR') ? 'selected' : ''}>OR</option>
                                            </select>
                                        </div>
                                        ` : ''}
                                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px; margin-bottom: 8px;">
                                            <input type="text" name="layer_filters[${layerId}][${idx}][attribute]" 
                                                   placeholder="Attribute name" 
                                                   value="${(condition.attribute || '').replace(/"/g, '&quot;')}" 
                                                   style="padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                            <select name="layer_filters[${layerId}][${idx}][operator]" 
                                                    style="padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                                <option value="=" ${(!condition.operator || condition.operator === '=') ? 'selected' : ''}>equals (=)</option>
                                                <option value=">" ${(condition.operator === '>') ? 'selected' : ''}>greater (&gt;)</option>
                                                <option value="<" ${(condition.operator === '<') ? 'selected' : ''}>less (&lt;)</option>
                                                <option value=">=" ${(condition.operator === '>=') ? 'selected' : ''}>&gt;=</option>
                                                <option value="<=" ${(condition.operator === '<=') ? 'selected' : ''}>&lt;=</option>
                                                <option value="!=" ${(condition.operator === '!=') ? 'selected' : ''}>not equals (!=)</option>
                                                <option value="LIKE" ${(condition.operator === 'LIKE') ? 'selected' : ''}>contains (LIKE)</option>
                                            </select>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <input type="text" name="layer_filters[${layerId}][${idx}][value]" 
                                                   placeholder="Filter value" 
                                                   value="${(condition.value || '').replace(/"/g, '&quot;')}" 
                                                   style="flex: 1; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                            <button type="button" onclick="this.parentElement.parentElement.remove()" 
                                                    style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 11px;">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
            });
            
            return html;
        }
        
        // Function to add a filter condition to a layer
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

        // Function to handle map clicks and show popups
        async function handleMapClick(e, map, layers) {
            if (!layers || layers.length === 0) {
                console.log('No layers configured for popups');
                return;
            }
            
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;
            
            try {
                // Query each layer for features at the clicked point
                const allFeatures = [];
                
                for (const layerId of layers) {
                    try {
                        const features = await queryFeaturesAtPoint(layerId, lat, lng);
                        if (features && features.length > 0) {
                            allFeatures.push(...features.map(f => ({
                                ...f,
                                layerName: layerId
                            })));
                        }
                    } catch (error) {
                        console.warn(`Error querying layer ${layerId}:`, error);
                    }
                }
                
                if (allFeatures.length > 0) {
                    showFeaturePopup(allFeatures, lat, lng, map);
                } else {
                    // Show a simple popup indicating no features found
                    L.popup()
                        .setLatLng([lat, lng])
                        .setContent('<div style="padding: 10px; text-align: center; color: #666;">No features found at this location</div>')
                        .openOn(map);
                }
                
            } catch (error) {
                console.error('Error handling map click:', error);
            }
        }
        
        // Function to query features at a specific point using WFS
        async function queryFeaturesAtPoint(layerId, lat, lng) {
            try {
                const proxyUrl = 'geoserver_proxy.php?dash_id=' + DASHBOARD_ID;
                const bbox = `${lng-0.001},${lat-0.001},${lng+0.001},${lat+0.001}`;
                const wfsUrl = `${proxyUrl}&service=WFS&version=1.0.0&request=GetFeature&typeName=${layerId}&outputFormat=application/json&bbox=${bbox}&srsName=EPSG:4326`;
                
                const response = await fetch(wfsUrl);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                return data.features || [];
            } catch (error) {
                console.error('Error querying features at point:', error);
                return [];
            }
        }
        
        // Function to show feature popup
        function showFeaturePopup(features, lat, lng, map) {
            if (features.length === 1) {
                // Single feature - show simple popup
                const feature = features[0];
                const popupContent = generatePopupContent(feature);
                
                L.popup()
                    .setLatLng([lat, lng])
                    .setContent(popupContent)
                    .openOn(map);
            } else {
                // Multiple features - show navigation popup
                const popupContent = generateMultiFeaturePopupContent(features, lat, lng);
                
                L.popup()
                    .setLatLng([lat, lng])
                    .setContent(popupContent)
                    .openOn(map);
            }
        }
        
        // Function to generate popup content for a single feature
        function generatePopupContent(feature) {
            const props = feature.properties || {};
            const layerName = feature.layerName || 'Unknown Layer';
            
            let html = `<div class="feature-popup">`;
            html += `<div class="popup-header">`;
            html += `<h4>${layerName}</h4>`;
            html += `</div>`;
            html += `<div class="popup-content">`;
            
            // Show first 5 properties
            const propKeys = Object.keys(props).slice(0, 5);
            propKeys.forEach(key => {
                const value = props[key];
                const displayValue = typeof value === 'string' && value.length > 50 
                    ? value.substring(0, 50) + '...' 
                    : value;
                html += `<div class="popup-row">`;
                html += `<strong>${key}:</strong> ${displayValue}`;
                html += `</div>`;
            });
            
            if (Object.keys(props).length > 5) {
                html += `<div class="popup-row">`;
                html += `<em>... and ${Object.keys(props).length - 5} more properties</em>`;
                html += `</div>`;
            }
            
            html += `</div>`;
            html += `<div class="popup-footer">`;
            html += `<button class="popup-btn" onclick="showFeatureTable(${JSON.stringify(feature).replace(/"/g, '&quot;')})">View Details</button>`;
            html += `</div>`;
            html += `</div>`;
            
            return html;
        }
        
        // Function to generate popup content for multiple features
        function generateMultiFeaturePopupContent(features, lat, lng) {
            const layerCounts = {};
            features.forEach(f => {
                const layerName = f.layerName || 'Unknown';
                layerCounts[layerName] = (layerCounts[layerName] || 0) + 1;
            });
            
            let html = `<div class="multi-feature-popup">`;
            html += `<div class="popup-header">`;
            html += `<h4>Multiple Features Found</h4>`;
            html += `</div>`;
            html += `<div class="popup-content">`;
            
            Object.entries(layerCounts).forEach(([layerName, count]) => {
                html += `<div class="popup-row">`;
                html += `<strong>${layerName}:</strong> ${count} feature${count > 1 ? 's' : ''}`;
                html += `</div>`;
            });
            
            html += `</div>`;
            html += `<div class="popup-footer">`;
            html += `<button class="popup-btn" onclick="showAllFeaturesTable(${JSON.stringify(features).replace(/"/g, '&quot;')})">View All Details</button>`;
            html += `</div>`;
            html += `</div>`;
            
            return html;
        }
        
        // Function to show feature details in a modal table
        function showFeatureTable(feature) {
            const props = feature.properties || {};
            const layerName = feature.layerName || 'Unknown Layer';
            
            let tableHTML = `
                <div class="feature-table-modal">
                    <div class="modal-header">
                        <h3>Feature Details - ${layerName}</h3>
                        <button class="modal-close" onclick="closeFeatureTable()">&times;</button>
                    </div>
                    <div class="modal-content">
                        <table class="feature-table">
                            <thead>
                                <tr>
                                    <th>Property</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            Object.entries(props).forEach(([key, value]) => {
                tableHTML += `
                    <tr>
                        <td><strong>${key}</strong></td>
                        <td>${value}</td>
                    </tr>
                `;
            });
            
            tableHTML += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            // Create modal overlay
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = tableHTML;
            document.body.appendChild(modal);
        }
        
        // Function to show all features in a modal table
        function showAllFeaturesTable(features) {
            let tableHTML = `
                <div class="feature-table-modal">
                    <div class="modal-header">
                        <h3>All Features (${features.length})</h3>
                        <button class="modal-close" onclick="closeFeatureTable()">&times;</button>
                    </div>
                    <div class="modal-content">
                        <table class="feature-table">
                            <thead>
                                <tr>
                                    <th>Layer</th>
                                    <th>Property</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            features.forEach(feature => {
                const props = feature.properties || {};
                const layerName = feature.layerName || 'Unknown';
                
                Object.entries(props).forEach(([key, value]) => {
                    tableHTML += `
                        <tr>
                            <td>${layerName}</td>
                            <td><strong>${key}</strong></td>
                            <td>${value}</td>
                        </tr>
                    `;
                });
            });
            
            tableHTML += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            // Create modal overlay
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = tableHTML;
            document.body.appendChild(modal);
        }
        
        // Function to close feature table modal
        function closeFeatureTable() {
            const modal = document.querySelector('.modal-overlay');
            if (modal) {
                modal.remove();
            }
        }

        // Function to get color palette based on scheme
        function getColorPalette(scheme) {
            const palettes = {
                default: ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b', '#e377c2', '#7f7f7f', '#bcbd22', '#17becf'],
                viridis: ['#440154', '#31688e', '#35b779', '#fde724', '#440154', '#31688e', '#35b779', '#fde724', '#440154', '#31688e'],
                warm: ['#d62728', '#ff7f0e', '#ffbb78', '#ff9896', '#c49c94', '#f7b6d2', '#d62728', '#ff7f0e', '#ffbb78', '#ff9896'],
                cool: ['#1f77b4', '#aec7e8', '#17becf', '#9edae5', '#7fcdbb', '#1f77b4', '#aec7e8', '#17becf', '#9edae5', '#7fcdbb'],
                earth: ['#8c564b', '#c49c94', '#bcbd22', '#dbdb8d', '#9467bd', '#8c564b', '#c49c94', '#bcbd22', '#dbdb8d', '#9467bd']
            };
            return palettes[scheme] || palettes.default;
        }

        // Function to process layer data for charts
        
function processDataForChart(data, xField, yField, aggregation = 'count') {
    if (!data || !data.features) return null;

    const groups = new Map();
    data.features.forEach(feature => {
        const xValue = feature.properties?.[xField] ?? 'Unknown';
        const yRaw = feature.properties?.[yField];
        const yNum = parseFloat(yRaw);
        const isNum = !isNaN(yNum);

        if (!groups.has(xValue)) {
            groups.set(xValue, { count: 0, sum: 0, min: isNum ? yNum : Infinity, max: isNum ? yNum : -Infinity });
        }
        const g = groups.get(xValue);
        g.count += 1;
        if (isNum) {
            g.sum += yNum;
            if (yNum < g.min) g.min = yNum;
            if (yNum > g.max) g.max = yNum;
        }
    });

    const x = [];
    const y = [];
    groups.forEach((g, key) => {
        x.push(key);
        let val = 0;
        switch (aggregation) {
            case 'sum': val = g.sum; break;
            case 'avg': val = g.count ? (g.sum / g.count) : 0; break;
            case 'min': val = (g.min === Infinity) ? 0 : g.min; break;
            case 'max': val = (g.max === -Infinity) ? 0 : g.max; break;
            case 'count':
            default: val = g.count; break;
        }
        y.push(val);
    });

    return { x, y };
}

        // Function to get feature count
        function getFeatureCount(data) {
            return data && data.features ? data.features.length : 0;
        }

        // Function to get bounding box of a geometry
        function getGeometryBounds(geometry) {
            let minLng = Infinity, minLat = Infinity;
            let maxLng = -Infinity, maxLat = -Infinity;
            
            function processCoordinate(coord) {
                if (typeof coord[0] === 'number' && typeof coord[1] === 'number') {
                    minLng = Math.min(minLng, coord[0]);
                    maxLng = Math.max(maxLng, coord[0]);
                    minLat = Math.min(minLat, coord[1]);
                    maxLat = Math.max(maxLat, coord[1]);
                } else if (Array.isArray(coord)) {
                    coord.forEach(processCoordinate);
                }
            }
            
            processCoordinate(geometry.coordinates);
            
            return {
                minLng, minLat, maxLng, maxLat
            };
        }
        
        // Function to check if two bounding boxes intersect
        function boundsIntersect(bounds1, bounds2) {
            return !(bounds1.maxLng < bounds2.minLng || 
                     bounds1.minLng > bounds2.maxLng || 
                     bounds1.maxLat < bounds2.minLat || 
                     bounds1.minLat > bounds2.maxLat);
        }
        
        // Function to filter features by map bounds
        function filterFeaturesByBounds(features, mapBounds) {
            if (!mapBounds || !features) return features;
            
            const viewBounds = {
                minLng: mapBounds._southWest.lng,
                minLat: mapBounds._southWest.lat,
                maxLng: mapBounds._northEast.lng,
                maxLat: mapBounds._northEast.lat
            };
            
            return features.filter(feature => {
                if (!feature.geometry || !feature.geometry.coordinates) return false;
                
                try {
                    const featureBounds = getGeometryBounds(feature.geometry);
                    return boundsIntersect(featureBounds, viewBounds);
                } catch (error) {
                    console.warn('Error filtering feature:', error);
                    return true; // Include feature if we can't determine bounds
                }
            });
        }

        // Function to update widgets based on map bounds
        function updateWidgetsForMapBounds(mapBounds) {
            console.log('Updating widgets for map bounds:', mapBounds);
            
            // Find all widgets that have layer configurations
            items.forEach(item => {
                if (item.config.layer && (item.kind === 'chart' || item.kind === 'table' || item.kind === 'counter')) {
                    updateWidgetForBounds(item, mapBounds);
                }
            });
        }

        // Function to update a single widget based on map bounds
        async function updateWidgetForBounds(item, mapBounds) {
            try {
                const data = await fetchLayerData(item.config.layer, 1000);
                if (!data || !data.features) return;
                
                // Filter features by current map bounds
                const filteredFeatures = filterFeaturesByBounds(data.features, mapBounds);
                console.log(`Filtered ${filteredFeatures.length} features for ${item.kind} widget`);
                
                // Update the widget with filtered data
                updateWidgetContent(item, filteredFeatures);
                
            } catch (error) {
                console.error('Error updating widget for bounds:', error);
            }
        }

        // Function to update widget content with filtered data
        function updateWidgetContent(item, features) {
            const itemDiv = document.querySelector(`[data-id="${item.id}"]`);
            if (!itemDiv) return;
            
            const container = itemDiv.querySelector('.pad');
            if (!container) return;
            
            switch (item.kind) {
                case 'chart':
                    updateChartContent(item, features, container);
                    break;
                case 'table':
                    updateTableContent(item, features, container);
                    break;
                case 'counter':
                    updateCounterContent(item, features, container);
                    break;
            }
        }

        // Function to update chart content with filtered features
        function updateChartContent(item, features, container) {
            // Always ensure chart div exists
            let chartDiv = container.querySelector('[id^="chart-"]');
            if (!chartDiv) {
                // Recreate the chart div if it was removed
                container.innerHTML = '';
                chartDiv = document.createElement('div');
                chartDiv.id = 'chart-' + item.id;
                chartDiv.style.width = '100%';
                chartDiv.style.height = '100%';
                chartDiv.style.minHeight = '200px';
                container.appendChild(chartDiv);
            }
            
            if (!features || features.length === 0) {
                chartDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No features in current view</div>';
                return;
            }
            
            try {
                const properties = features[0].properties;
                const propNames = Object.keys(properties).filter(prop => 
                    typeof properties[prop] === 'string' || typeof properties[prop] === 'number'
                );
                
                const xField = item.config.xField || propNames[0] || 'id';
                const yField = item.config.yField || propNames[1] || propNames[0] || 'id';
                
                const chartData = processDataForChart({features: features}, xField, yField, item.config.aggregation || 'count');
                
                if (chartData && chartData.x.length > 0) {
                    // Clear any "no data" message
                    chartDiv.innerHTML = '';
                    
                    const colorPalette = getColorPalette(item.config.colorScheme || 'default');
                    const chartType = item.config.type || 'bar';
                    
                    // Handle pie charts differently - they use 'labels' and 'values' instead of 'x' and 'y'
                    let plotData;
                    if (chartType === 'pie') {
                        plotData = [{
                            labels: chartData.x.slice(0, 10),
                            values: chartData.y.slice(0, 10),
                            type: 'pie',
                            marker: {
                                colors: colorPalette
                            }
                        }];
                    } else {
                        const primaryLabel = item.config.label || `Data from ${item.config.layer}`;
                        plotData = [{
                            x: chartData.x.slice(0, 10),
                            y: chartData.y.slice(0, 10),
                            type: chartType,
                            name: primaryLabel,
                            marker: {
                                color: colorPalette
                            },
                            fill: (chartType === 'area') ? 'tozeroy' : undefined
                        }];
                        
                        // Add second series as a line if enabled and chart type supports it
                        if (item.config.enableSecondSeries && (chartType === 'bar' || chartType === 'area')) {
                            // Get second series data
                            let secondFeatures = features;
                            if (item.config.secondLayer && item.config.secondLayer !== item.config.layer) {
                                // Need to fetch data from different layer - we'll handle this in the initial load
                                secondFeatures = features; // Will be handled in initial render
                            }
                            
                            const secondYField = item.config.secondYField;
                            if (secondYField && secondFeatures && secondFeatures.length > 0) {
                                const secondChartData = processDataForChart(
                                    {features: secondFeatures}, 
                                    xField, 
                                    secondYField, 
                                    item.config.aggregation || 'count'
                                );
                                
                                if (secondChartData && secondChartData.y.length > 0) {
                                    // Match x values to primary series for proper alignment
                                    const alignedY = chartData.x.map(xVal => {
                                        const idx = secondChartData.x.indexOf(xVal);
                                        return idx >= 0 ? secondChartData.y[idx] : null;
                                    });
                                    
                                    const secondSeriesLabel = item.config.secondLabel || `Second Series`;
                                    plotData.push({
                                        x: chartData.x.slice(0, 10),
                                        y: alignedY.slice(0, 10),
                                        type: 'scatter',
                                        mode: 'lines+markers',
                                        name: secondSeriesLabel,
                                        line: {
                                            color: '#ff7f0e',
                                            width: 2
                                        },
                                        marker: {
                                            color: '#ff7f0e',
                                            size: 6
                                        },
                                        yaxis: 'y2'
                                    });
                                }
                            }
                        }
                    }
                    
                    const showGrid = item.config.showGrid !== false;
                    const layout = {
                        margin: {t: 20, r: 20, b: 40, l: 40},
                        font: {size: 12},
                        paper_bgcolor: 'rgba(0,0,0,0)',
                        plot_bgcolor: 'rgba(0,0,0,0)',
                        hovermode: 'closest',
                        showlegend: true
                    };
                    
                    // Only add axis config for non-pie charts
                    if (chartType !== 'pie') {
                        layout.xaxis = {
                            showgrid: showGrid,
                            gridcolor: '#e5e5e5'
                        };
                        layout.yaxis = {
                            showgrid: showGrid,
                            gridcolor: '#e5e5e5'
                        };
                        
                        // Add second y-axis if second series is enabled
                        if (item.config.enableSecondSeries && plotData.length > 1) {
                            layout.yaxis2 = {
                                title: item.config.secondLabel || 'Second Series',
                                overlaying: 'y',
                                side: 'right',
                                showgrid: false
                            };
                        }
                    }
                    
                    const config = {
                        responsive: true,
                        displayModeBar: true,
                        displaylogo: false,
                        modeBarButtonsToAdd: ['hoverclosest', 'hovercompare'],
                        modeBarButtonsToRemove: [],
                        toImageButtonOptions: {
                            format: 'png',
                            filename: 'chart_export',
                            height: 800,
                            width: 1200,
                            scale: 2
                        }
                    };
                    
                    Plotly.newPlot(chartDiv.id, plotData, layout, config);
                } else {
                    chartDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No data available for chart</div>';
                }
            } catch (error) {
                console.error('Error updating chart:', error);
            }
        }

        // Function to update table content with filtered features
        function updateTableContent(item, features, container) {
            if (!features || features.length === 0) {
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No features in current view</div>';
                return;
            }
            
            try {
                const properties = features[0].properties;
                const propNames = Object.keys(properties);
                
                let tableHTML = `
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                        <thead>
                            <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                <th style="padding: 6px; text-align: left; border: 1px solid #dee2e6;">Features in view: ${features.length}</th>
                            </tr>
                            <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                `;
                
                // Use configured columns if available, otherwise use first 5 columns
                const displayProps = item.config.columns && item.config.columns.length > 0 
                    ? item.config.columns.filter(col => propNames.includes(col))
                    : propNames.slice(0, 5);
                displayProps.forEach(prop => {
                    tableHTML += `<th style="padding: 6px; text-align: left; border: 1px solid #dee2e6;">${prop}</th>`;
                });
                
                tableHTML += `
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                features.forEach((feature, index) => {
                    const bgColor = index % 2 === 0 ? '' : 'background-color: #f8f9fa;';
                    tableHTML += `<tr style="border-bottom: 1px solid #dee2e6; ${bgColor}">`;
                    
                    displayProps.forEach(prop => {
                        const value = feature.properties[prop] || '';
                        const displayValue = typeof value === 'string' && value.length > 20 
                            ? value.substring(0, 20) + '...' 
                            : value;
                        tableHTML += `<td style="padding: 6px; border: 1px solid #dee2e6;">${displayValue}</td>`;
                    });
                    
                    tableHTML += '</tr>';
                });
                
                tableHTML += `
                        </tbody>
                    </table>
                `;
                
                container.innerHTML = tableHTML;
            } catch (error) {
                console.error('Error updating table:', error);
            }
        }

        // Function to update counter content with filtered features
        function updateCounterContent(item, features, container) {
            // Find the counter div inside the pad container
            const counter = container.querySelector('div');
            if (!counter) {
                container.innerHTML = '<div style="color: #999; text-align: center;">No features in current view</div>';
                return;
            }
            
            const valueDiv = counter.querySelector('div:nth-child(1)');
            const labelDiv = counter.querySelector('div:nth-child(2)');
            const descDiv = counter.querySelector('div:nth-child(3)');
            
            if (!features || features.length === 0) {
                if (valueDiv && labelDiv && descDiv) {
                    valueDiv.textContent = '0';
                    labelDiv.textContent = 'No features';
                    descDiv.textContent = '';
                } else {
                    container.innerHTML = '<div style="color: #999; text-align: center;">No features in current view</div>';
                }
                return;
            }
            
            try {
                let value = 0;
                const count = features.length;
                
                if (item.config.operation === 'count') {
                    value = count;
                } else if (item.config.operation === 'sum' && item.config.field) {
                    value = features.reduce((sum, feature) => {
                        const val = parseFloat(feature.properties[item.config.field]) || 0;
                        return sum + val;
                    }, 0);
                } else if (item.config.operation === 'avg' && item.config.field) {
                    const sum = features.reduce((sum, feature) => {
                        const val = parseFloat(feature.properties[item.config.field]) || 0;
                        return sum + val;
                    }, 0);
                    value = count > 0 ? (sum / count).toFixed(1) : 0;
                } else if (item.config.operation === 'min' && item.config.field) {
                    const values = features.map(feature => parseFloat(feature.properties[item.config.field]) || 0);
                    value = values.length > 0 ? Math.min(...values) : 0;
                } else if (item.config.operation === 'max' && item.config.field) {
                    const values = features.map(feature => parseFloat(feature.properties[item.config.field]) || 0);
                    value = values.length > 0 ? Math.max(...values) : 0;
                } else {
                    value = count;
                }
                
                const label = item.config.operation === 'count' ? 'Features' : 
                             item.config.operation === 'sum' ? `Sum of ${item.config.field}` :
                             item.config.operation === 'avg' ? `Avg of ${item.config.field}` :
                             item.config.operation === 'min' ? `Min of ${item.config.field}` :
                             item.config.operation === 'max' ? `Max of ${item.config.field}` : 'Count';
                
                if (valueDiv && labelDiv && descDiv) {
                    valueDiv.textContent = formatNumber(value);
                    labelDiv.textContent = label;
                    descDiv.textContent = `in current view (${count} features)`;
                } else {
                    container.innerHTML = `
                        <div style="font-size: 48px; font-weight: bold; color: var(--accent); text-align: center;">${formatNumber(value)}</div>
                        <div style="color: #999; margin-top: 10px; text-align: center;">${label}</div>
                        <div style="color: #666; font-size: 12px; margin-top: 5px; text-align: center;">in current view (${count} features)</div>
                    `;
                }
            } catch (error) {
                console.error('Error updating counter:', error);
            }
        }

        // Dashboard state
        let items = [];
        let nextId = 1;

        // Initialize dashboard with existing config if editing
        if (INITIAL_CONFIG && INITIAL_CONFIG.items) {
            items = INITIAL_CONFIG.items;
            nextId = Math.max(...items.map(i => i.id), 0) + 1;
            items.forEach(item => createItemElement(item));
        }

        // Add widget pickers
        document.querySelectorAll('.picker').forEach(picker => {
            picker.addEventListener('click', () => {
                const kind = picker.dataset.kind;
                addItem(kind);
            });
        });

        function addItem(kind) {
            const item = {
                id: nextId++,
                kind: kind,
                x: 50,
                y: 50,
                w: kind === 'map' ? 600 : 400,
                h: kind === 'map' ? 500 : 300,
                title: getDefaultTitle(kind),
                config: getDefaultConfig(kind)
            };
            items.push(item);
            createItemElement(item);
        }

        function getDefaultTitle(kind) {
            const titles = {
                map: 'Map View',
                chart: 'Chart',
                table: 'Data Table',
                counter: 'Counter',
                text: 'Text Widget'
            };
            return titles[kind] || 'Widget';
        }

        function getDefaultConfig(kind) {
            const configs = {
                map: {
                    layers: [],
                    center: [0, 0],
                    zoom: 2
                },
                chart: {
                    type: 'bar',
                    layer: '',
                    xField: '',
                    yField: '',
                    aggregation: 'count'
                },
                table: {
                    layer: '',
                    columns: [],
                    limit: 100
                },
                counter: {
                    layer: '',
                    field: '',
                    operation: 'count'
                },
                text: {
                    content: 'Enter your text here...'
                }
            };
            return configs[kind] || {};
        }

        function createItemElement(item) {
            const div = document.createElement('div');
            div.className = 'item';
            div.dataset.id = item.id;
            div.dataset.kind = item.kind;
            div.style.left = item.x + 'px';
            div.style.top = item.y + 'px';
            div.style.width = item.w + 'px';
            div.style.height = item.h + 'px';

            const header = document.createElement('div');
            header.className = 'card-header';
            
            const titleSpan = document.createElement('span');
            titleSpan.className = 'title';
            titleSpan.contentEditable = 'true';
            titleSpan.textContent = item.title;
            titleSpan.addEventListener('blur', () => {
                item.title = titleSpan.textContent;
            });
            
            const tools = document.createElement('div');
            tools.className = 'tools';
            
            const configBtn = document.createElement('button');
            configBtn.className = 'tbtn';
            configBtn.innerHTML = '⚙';
            configBtn.title = 'Configure';
            configBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                openConfigModal(item);
            });
            
            const maxBtn = document.createElement('button');
            maxBtn.className = 'tbtn maximize';
            maxBtn.title = 'Maximize';
            maxBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleMaximize(div);
            });
            
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'tbtn';
            deleteBtn.innerHTML = '×';
            deleteBtn.title = 'Delete';
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (confirm('Delete this widget?')) {
                    items = items.filter(i => i.id !== item.id);
                    div.remove();
                }
            });
            
            tools.appendChild(configBtn);
            tools.appendChild(maxBtn);
            tools.appendChild(deleteBtn);
            header.appendChild(titleSpan);
            header.appendChild(tools);
            
            const body = document.createElement('div');
            body.className = 'body';
            
            const pad = document.createElement('div');
            pad.className = 'pad';
            
            // Render widget content
            renderWidgetContent(item, pad);
            
            body.appendChild(pad);
            
            const resize = document.createElement('div');
            resize.className = 'resize';
            
            div.appendChild(header);
            div.appendChild(body);
            div.appendChild(resize);
            
            canvas.appendChild(div);
            
            // Make draggable
            makeDraggable(div, header, item);
            makeResizable(div, resize, item);
        }

        function renderWidgetContent(item, container) {
            container.innerHTML = '';
            
            switch (item.kind) {
                case 'map':
                    const mapDiv = document.createElement('div');
                    mapDiv.id = 'map-' + item.id;
                    mapDiv.style.width = '100%';
                    mapDiv.style.height = '100%';
                    mapDiv.style.minHeight = '200px';
                    container.appendChild(mapDiv);
                    
                                // Ensure the wrapper fills the item
            container.style.height = '100%';
            container.style.padding = '0';
// Initialize map after DOM is ready
                    setTimeout(() => {
                        try {
                            if (typeof L === 'undefined') {
                                throw new Error('Leaflet not loaded');
                            }
                            
                            // Check if the element exists in DOM
                            const mapElement = document.getElementById('map-' + item.id);
                            if (!mapElement) {
                                throw new Error('Map container not found in DOM');
                            }
                            
                            // Ensure map container is fluid and fills the widget
mapElement.style.position = 'relative';
mapElement.style.width = '100%';
mapElement.style.height = '100%';
mapElement.style.minHeight = '200px';
mapElement.style.maxWidth = 'none';
mapElement.style.maxHeight = 'none';
mapElement.style.overflow = 'hidden';
const map = L.map('map-' + item.id, {
                                center: item.config.center || [0, 0],
                                zoom: item.config.zoom || 2,
                                zoomControl: true,
                                preferCanvas: false
                            });
                            
                            // Restore saved extent if available
const savedBounds = item.config && item.config.bounds;
const savedCenter = item.config && item.config.center;
const savedZoom = item.config && item.config.zoom;
// Force map to invalidate size multiple times
                            setTimeout(() => {
                                map.invalidateSize();
                                // Also trigger resize event
                                window.dispatchEvent(new Event('resize'));
                            }, 100);
                            
                            setTimeout(() => {
                                map.invalidateSize();
                            }, 300);
                            
                            setTimeout(() => {
                                map.invalidateSize();
                            }, 500);
                            
                            // --- Base maps ---
const baseMaps = {
    'OpenStreetMap': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }),
    'Carto Light': L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CartoDB',
        subdomains: 'abcd'
    }),
    'Carto Dark': L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CartoDB',
        subdomains: 'abcd'
    }),
    'Carto Voyager': L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png', {
        attribution: '&copy; CartoDB',
        subdomains: 'abcd'
    }),
    'Esri Satellite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri'
    }),
    'Esri Topo': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri'
    })
};
// Use selected basemap or default to OpenStreetMap
const selectedBasemap = item.config && item.config.basemap ? item.config.basemap : 'OpenStreetMap';
if (baseMaps[selectedBasemap]) {
    baseMaps[selectedBasemap].addTo(map);
}

// --- Helper function to build CQL filter ---
function buildCqlFilter(filters, layerId) {
    if (!filters || !filters[layerId]) return '';
    
    const conditions = filters[layerId];
    const filterParts = [];
    
    conditions.forEach((filter, idx) => {
        if (!filter.attribute || !filter.value) return;
        
        const attribute = filter.attribute;
        const operator = filter.operator || '=';
        const value = filter.value;
        
        let condition = '';
        switch (operator) {
            case '=':
                condition = attribute + ' = \'' + value.replace(/'/g, "''") + '\'';
                break;
            case '!=':
                condition = attribute + ' != \'' + value.replace(/'/g, "''") + '\'';
                break;
            case '>':
            case '<':
            case '>=':
            case '<=':
                condition = attribute + ' ' + operator + ' ' + value;
                break;
            case 'LIKE':
                condition = attribute + ' LIKE \'%' + value.replace(/'/g, "''") + '%\'';
                break;
            default:
                condition = attribute + ' = \'' + value.replace(/'/g, "''") + '\'';
        }
        
        if (idx > 0 && filter.logic) {
            filterParts.push(filter.logic + ' ' + condition);
        } else {
            filterParts.push(condition);
        }
    });
    
    return filterParts.join(' ');
}

// --- Overlays (WMS layers) ---
const overlays = {};
if (item.config.layers && item.config.layers.length > 0) {
    item.config.layers.forEach(layerId => {
        try {
            // Use proxy to avoid CORS issues with authentication
            const proxyUrl = 'geoserver_proxy.php?dash_id=' + DASHBOARD_ID;
            
            // Build WMS options
            const wmsOptions = {
                layers: layerId,
                format: 'image/png',
                transparent: true,
                version: '1.1.1'
            };
            
            // Apply CQL filter if configured
            const cqlFilter = buildCqlFilter(item.config.filters, layerId);
            if (cqlFilter) {
                wmsOptions.cql_filter = cqlFilter;
                console.log('Applying filter to layer', layerId + ':', cqlFilter);
            }
            
            const wmsLayer = L.tileLayer.wms(proxyUrl, wmsOptions);
            overlays[layerId] = wmsLayer;
            wmsLayer.addTo(map);
        } catch (error) {
            console.warn('Failed to add WMS layer:', layerId, error);
        }
    });
}

// --- Layer selector control (only show overlays if there are any) ---
if (Object.keys(overlays).length > 0) {
    L.control.layers(null, overlays, { collapsed: false, position: 'topright' }).addTo(map);
}


                            
                            // Apply saved map view (bounds preferred over center/zoom)
if (savedBounds && savedBounds.length === 2) {
    try {
        const b = L.latLngBounds(savedBounds[0], savedBounds[1]);
        map.fitBounds(b, {animate: false});
    } catch (e) { console.warn('Invalid saved bounds', savedBounds, e); }
} else if (savedCenter && typeof savedCenter[0] === 'number' && typeof savedCenter[1] === 'number' && typeof savedZoom === 'number') {
    map.setView(savedCenter, savedZoom, {animate: false});
}
// Store map instance
                            mapDiv._leaflet_map = map;
                            
                            // Add resize observer to detect container size changes
                            const resizeObserver = new ResizeObserver(() => {
mapElement.style.width = '100%';
mapElement.style.height = '100%';
setTimeout(() => map.invalidateSize(), 100);
});resizeObserver.observe(mapElement.parentElement);
                            
                            // Add event listeners to update widgets when map moves
                            let updateTimeout;
                            map.on('moveend zoomend', function() {
                                // Debounce updates to avoid too many API calls
                                clearTimeout(updateTimeout);
                                updateTimeout = setTimeout(() => {
                                    const bounds = map.getBounds();
                                    updateWidgetsForMapBounds(bounds);
                                }, 500);
                            });
                            
                            // Add click event handler for popups
                            map.on('click', function(e) {
                                handleMapClick(e, map, item.config.layers || []);
                            });
                            
                            // Force initial resize after everything is loaded
                            setTimeout(() => {
                                map.invalidateSize();
                                // Also try to trigger a redraw
                                map._resetView(map.getCenter(), map.getZoom(), true);
                            }, 1000);
                            
                            console.log('Map initialized successfully for item', item.id);
                        } catch (error) {
                            console.error('Failed to initialize map:', error);
                            mapDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Failed to load map: ' + error.message + '</div>';
                        }
                    }, 100);
                    break;
                    
                case 'chart':
                    // Create a placeholder chart
                    const chartDiv = document.createElement('div');
                    chartDiv.id = 'chart-' + item.id;
                    chartDiv.style.width = '100%';
                    chartDiv.style.height = '100%';
                    chartDiv.style.minHeight = '200px';
                    container.appendChild(chartDiv);
                    
                    // Initialize chart after DOM is ready
                    setTimeout(() => {
                        try {
                            if (typeof Plotly === 'undefined') {
                                throw new Error('Plotly not loaded');
                            }
                            
                            // Check if the element exists in DOM
                            const chartElement = document.getElementById('chart-' + item.id);
                            if (!chartElement) {
                                throw new Error('Chart container not found in DOM');
                            }
                            
                            // Try to fetch real data if layer is configured
                            if (item.config.layer) {
                                chartDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Loading chart data...</div>';
                                
                                fetchLayerData(item.config.layer, 100).then(data => {
                                    // Clear loading text
                                    chartDiv.innerHTML = '';
                                    if (data && data.features && data.features.length > 0) {
                                        // Get available properties from first feature
                                        const properties = data.features[0].properties;
                                        const propNames = Object.keys(properties).filter(prop => 
                                            typeof properties[prop] === 'string' || typeof properties[prop] === 'number'
                                        );
                                        
                                        // Use first two properties as x and y if not configured
                                        const xField = item.config.xField || propNames[0] || 'id';
                                        const yField = item.config.yField || propNames[1] || propNames[0] || 'id';
                                        
                                        const chartData = processDataForChart(data, xField, yField, item.config.aggregation || 'count');
                                        
                                        if (chartData && chartData.x.length > 0) {
                                            const colorPalette = getColorPalette(item.config.colorScheme || 'default');
                                            const chartType = item.config.type || 'bar';
                                            
                                            // Handle pie charts differently - they use 'labels' and 'values' instead of 'x' and 'y'
                                            let plotData;
                                            if (chartType === 'pie') {
                                                plotData = [{
                                                    labels: chartData.x.slice(0, 10),
                                                    values: chartData.y.slice(0, 10),
                                                    type: 'pie',
                                                    marker: {
                                                        colors: colorPalette
                                                    }
                                                }];
                                            } else {
                                                const primaryLabel = item.config.label || `Data from ${item.config.layer}`;
                                                plotData = [{
                                                    x: chartData.x.slice(0, 10),
                                                    y: chartData.y.slice(0, 10),
                                                    type: chartType,
                                                    name: primaryLabel,
                                                    marker: {
                                                        color: colorPalette
                                                    },
                                                    fill: (chartType === 'area') ? 'tozeroy' : undefined
                                                }];
                                                
                                                // Add second series as a line if enabled and chart type supports it
                                                if (item.config.enableSecondSeries && (chartType === 'bar' || chartType === 'area')) {
                                                    const secondYField = item.config.secondYField;
                                                    if (secondYField) {
                                                        const secondChartData = processDataForChart(
                                                            data, 
                                                            xField, 
                                                            secondYField, 
                                                            item.config.aggregation || 'count'
                                                        );
                                                        
                                                        if (secondChartData && secondChartData.y.length > 0) {
                                                            // Match x values to primary series for proper alignment
                                                            const alignedY = chartData.x.map(xVal => {
                                                                const idx = secondChartData.x.indexOf(xVal);
                                                                return idx >= 0 ? secondChartData.y[idx] : null;
                                                            });
                                                            
                                                            const secondSeriesLabel = item.config.secondLabel || `Second Series`;
                                                            plotData.push({
                                                                x: chartData.x.slice(0, 10),
                                                                y: alignedY.slice(0, 10),
                                                                type: 'scatter',
                                                                mode: 'lines+markers',
                                                                name: secondSeriesLabel,
                                                                line: {
                                                                    color: '#ff7f0e',
                                                                    width: 2
                                                                },
                                                                marker: {
                                                                    color: '#ff7f0e',
                                                                    size: 6
                                                                },
                                                                yaxis: 'y2'
                                                            });
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            const showGrid = item.config.showGrid !== false;
                                            const layout = {
                                                margin: {t: 20, r: 20, b: 40, l: 40},
                                                font: {size: 12},
                                                paper_bgcolor: 'rgba(0,0,0,0)',
                                                plot_bgcolor: 'rgba(0,0,0,0)',
                                                hovermode: 'closest',
                                                showlegend: true
                                            };
                                            
                                            // Only add axis config for non-pie charts
                                            if (chartType !== 'pie') {
                                                layout.xaxis = {
                                                    showgrid: showGrid,
                                                    gridcolor: '#e5e5e5'
                                                };
                                                layout.yaxis = {
                                                    showgrid: showGrid,
                                                    gridcolor: '#e5e5e5'
                                                };
                                                
                                                // Add second y-axis if second series is enabled
                                                if (item.config.enableSecondSeries && plotData.length > 1) {
                                                    layout.yaxis2 = {
                                                        title: item.config.secondLabel || 'Second Series',
                                                        overlaying: 'y',
                                                        side: 'right',
                                                        showgrid: false
                                                    };
                                                }
                                            }
                                            
                                            const config = {
                                                responsive: true,
                                                displayModeBar: true,
                                                displaylogo: false,
                                                modeBarButtonsToAdd: ['hoverclosest', 'hovercompare'],
                                                modeBarButtonsToRemove: [],
                                                toImageButtonOptions: {
                                                    format: 'png',
                                                    filename: 'chart_export',
                                                    height: 800,
                                                    width: 1200,
                                                    scale: 2
                                                }
                                            };
                                            
                                            Plotly.newPlot(chartDiv.id, plotData, layout, config);
                                        } else {
                                            chartDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No data available for chart</div>';
                                        }
                                    } else {
                                        chartDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No features found in layer</div>';
                                    }
                                }).catch(error => {
                                    console.error('Error loading chart data:', error);
                                    chartDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Error loading data</div>';
                                });
                            } else {
                                // Show sample data if no layer configured
                                const chartType = item.config.type || 'bar';
                                let chartData;
                                
                                // Handle pie charts differently
                                if (chartType === 'pie') {
                                    chartData = [{
                                        labels: ['North', 'South', 'East', 'West'],
                                        values: [20, 14, 23, 25],
                                        type: 'pie',
                                        marker: {
                                            colors: ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728']
                                        }
                                    }];
                                } else {
                                    chartData = [{
                                        x: ['North', 'South', 'East', 'West'],
                                        y: [20, 14, 23, 25],
                                        type: chartType,
                                        name: 'Sample Data',
                                        marker: {
                                            color: ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728']
                                        }
                                    }];
                                }
                                
                                const layout = {
                                    margin: {t: 20, r: 20, b: 40, l: 40},
                                    font: {size: 12},
                                    paper_bgcolor: 'rgba(0,0,0,0)',
                                    plot_bgcolor: 'rgba(0,0,0,0)',
                                    hovermode: 'closest',
                                    showlegend: true
                                };
                                
                                const config = {
                                    responsive: true,
                                    displayModeBar: true,
                                    displaylogo: false,
                                    modeBarButtonsToAdd: ['hoverclosest', 'hovercompare'],
                                    modeBarButtonsToRemove: [],
                                    toImageButtonOptions: {
                                        format: 'png',
                                        filename: 'chart_export',
                                        height: 800,
                                        width: 1200,
                                        scale: 2
                                    }
                                };
                                
                                Plotly.newPlot(chartDiv.id, chartData, layout, config);
                            }
                            
                            console.log('Chart initialized successfully for item', item.id);
                        } catch (error) {
                            console.error('Failed to create chart:', error);
                            chartDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999; border: 2px dashed #ddd; border-radius: 8px; margin: 20px;">Chart Preview<br><small>Error: ' + error.message + '</small></div>';
                        }
                    }, 100);
                    break;
                    
                case 'table':
                    // Create table container
                    const tableDiv = document.createElement('div');
                    tableDiv.style.width = '100%';
                    tableDiv.style.height = '100%';
                    tableDiv.style.overflow = 'auto';
                    tableDiv.style.padding = '10px';
                    container.appendChild(tableDiv);
                    
                    // Try to fetch real data if layer is configured
                    if (item.config.layer) {
                        tableDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Loading table data...</div>';
                        
                        fetchLayerData(item.config.layer, item.config.limit || 50).then(data => {
                            // Clear loading text
                            tableDiv.innerHTML = '';
                            if (data && data.features && data.features.length > 0) {
                                // Get properties from first feature
                                const properties = data.features[0].properties;
                                const propNames = Object.keys(properties);
                                
                                // Use configured columns if available, otherwise use first 5 columns
                                const displayProps = item.config.columns && item.config.columns.length > 0 
                                    ? item.config.columns.filter(col => propNames.includes(col))
                                    : propNames.slice(0, 5);
                                
                                // Create table header
                                let tableHTML = `
                                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                        <thead>
                                            <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                `;
                                
                                // Add headers for properties
                                displayProps.forEach(prop => {
                                    tableHTML += `<th style="padding: 6px; text-align: left; border: 1px solid #dee2e6;">${prop}</th>`;
                                });
                                
                                tableHTML += `
                                            </tr>
                                        </thead>
                                        <tbody>
                                `;
                                
                                // Add data rows
                                data.features.forEach((feature, index) => {
                                    const bgColor = index % 2 === 0 ? '' : 'background-color: #f8f9fa;';
                                    tableHTML += `<tr style="border-bottom: 1px solid #dee2e6; ${bgColor}">`;
                                    
                                    displayProps.forEach(prop => {
                                        const value = feature.properties[prop] || '';
                                        const displayValue = typeof value === 'string' && value.length > 20 
                                            ? value.substring(0, 20) + '...' 
                                            : value;
                                        tableHTML += `<td style="padding: 6px; border: 1px solid #dee2e6;">${displayValue}</td>`;
                                    });
                                    
                                    tableHTML += '</tr>';
                                });
                                
                                tableHTML += `
                                        </tbody>
                                    </table>
                                `;
                                
                                tableDiv.innerHTML = tableHTML;
                            } else {
                                tableDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No features found in layer</div>';
                            }
                        }).catch(error => {
                            console.error('Error loading table data:', error);
                            tableDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Error loading data</div>';
                        });
                    } else {
                        // Show sample data if no layer configured
                        const table = document.createElement('table');
                        table.style.width = '100%';
                        table.style.borderCollapse = 'collapse';
                        table.style.fontSize = '14px';
                        table.innerHTML = `
                            <thead>
                                <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 8px; text-align: left; border: 1px solid #dee2e6;">ID</th>
                                    <th style="padding: 8px; text-align: left; border: 1px solid #dee2e6;">Name</th>
                                    <th style="padding: 8px; text-align: left; border: 1px solid #dee2e6;">Value</th>
                                    <th style="padding: 8px; text-align: left; border: 1px solid #dee2e6;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid #dee2e6;">
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">1</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">Sample Location 1</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">100</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6; color: green;">Active</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #dee2e6; background-color: #f8f9fa;">
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">2</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">Sample Location 2</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">200</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6; color: orange;">Pending</td>
                                </tr>
                            </tbody>
                        `;
                        
                        tableDiv.appendChild(table);
                    }
                    
                    console.log('Table initialized successfully for item', item.id);
                    break;
                    
                case 'counter':
                    const counter = document.createElement('div');
                    counter.style.textAlign = 'center';
                    counter.style.width = '100%';
                    counter.style.height = '100%';
                    counter.style.display = 'flex';
                    counter.style.flexDirection = 'column';
                    counter.style.alignItems = 'center';
                    counter.style.justifyContent = 'center';
                    counter.style.padding = '40px';
                    counter.style.boxSizing = 'border-box';
                    counter.style.overflow = 'hidden';
                    container.appendChild(counter);
                    
                    // Create stable DOM structure that won't cause layout shifts
                    const valueDiv = document.createElement('div');
                    valueDiv.style.fontSize = '48px';
                    valueDiv.style.fontWeight = 'bold';
                    valueDiv.style.color = 'var(--accent)';
                    valueDiv.style.textAlign = 'center';
                    
                    const labelDiv = document.createElement('div');
                    labelDiv.style.color = '#999';
                    labelDiv.style.marginTop = '10px';
                    labelDiv.style.textAlign = 'center';
                    
                    const descDiv = document.createElement('div');
                    descDiv.style.color = '#666';
                    descDiv.style.fontSize = '12px';
                    descDiv.style.marginTop = '5px';
                    descDiv.style.textAlign = 'center';
                    
                    counter.appendChild(valueDiv);
                    counter.appendChild(labelDiv);
                    counter.appendChild(descDiv);
                    
                    // Try to fetch real data if layer is configured
                    if (item.config.layer) {
                        valueDiv.textContent = '...';
                        
                        fetchLayerData(item.config.layer, 1000).then(data => {
                            if (data && data.features) {
                                let value = 0;
                                const count = data.features.length;
                                
                                if (item.config.operation === 'count') {
                                    value = count;
                                } else if (item.config.operation === 'sum' && item.config.field) {
                                    value = data.features.reduce((sum, feature) => {
                                        const val = parseFloat(feature.properties[item.config.field]) || 0;
                                        return sum + val;
                                    }, 0);
                                } else if (item.config.operation === 'avg' && item.config.field) {
                                    const sum = data.features.reduce((sum, feature) => {
                                        const val = parseFloat(feature.properties[item.config.field]) || 0;
                                        return sum + val;
                                    }, 0);
                                    value = count > 0 ? (sum / count).toFixed(1) : 0;
                                } else if (item.config.operation === 'min' && item.config.field) {
                                    const values = data.features.map(feature => parseFloat(feature.properties[item.config.field]) || 0);
                                    value = values.length > 0 ? Math.min(...values) : 0;
                                } else if (item.config.operation === 'max' && item.config.field) {
                                    const values = data.features.map(feature => parseFloat(feature.properties[item.config.field]) || 0);
                                    value = values.length > 0 ? Math.max(...values) : 0;
                                } else {
                                    value = count;
                                }
                                
                                const label = item.config.operation === 'count' ? 'Features' : 
                                             item.config.operation === 'sum' ? `Sum of ${item.config.field}` :
                                             item.config.operation === 'avg' ? `Avg of ${item.config.field}` :
                                             item.config.operation === 'min' ? `Min of ${item.config.field}` :
                                             item.config.operation === 'max' ? `Max of ${item.config.field}` : 'Count';
                                
                                valueDiv.textContent = formatNumber(value);
                                labelDiv.textContent = label;
                                descDiv.textContent = `from ${item.config.layer}`;
                            } else {
                                valueDiv.textContent = '0';
                                labelDiv.textContent = 'No data';
                                descDiv.textContent = '';
                            }
                        }).catch(error => {
                            console.error('Error loading counter data:', error);
                            valueDiv.textContent = 'Error';
                            labelDiv.textContent = 'Error loading data';
                            descDiv.textContent = '';
                        });
                    } else {
                        // Show sample data if no layer configured
                        valueDiv.textContent = '123';
                        labelDiv.textContent = 'Sample Count';
                        descDiv.textContent = '';
                    }
                    break;
                    
                case 'text':
                    const textPreview = document.createElement('div');
                    textPreview.style.minHeight = '100px';
                    textPreview.style.width = '100%';
                    textPreview.style.padding = '10px';
                    textPreview.style.overflow = 'auto';
                    textPreview.style.boxSizing = 'border-box';
                    // Render HTML content (saved from Quill)
                    textPreview.innerHTML = item.config.content || '<p>Enter your HTML content here...</p>';
                    container.appendChild(textPreview);
                    break;
            }
        }

        function openConfigModal(item) {
            const backdrop = document.createElement('div');
            backdrop.className = 'backdrop';
            
            const modal = document.createElement('div');
            modal.className = 'modal-custom';
            
            let modalContent = '';
            
            switch (item.kind) {
                case 'map':
                    modalContent = `
                        <div class="mh">Configure Map</div>
                        <div class="mb">
                            <div class="row-custom">
                                <label>Title:</label>
                                <input type="text" id="widgetTitle" value="${(item.title || 'Map View').replace(/"/g, '&quot;')}" style="flex: 1;">
                            </div>
                            <div class="row-custom">
                                <label>Basemap:</label>
                                <select id="mapBasemap" style="flex: 1;">
                                    <option value="OpenStreetMap" ${(!item.config.basemap || item.config.basemap === 'OpenStreetMap') ? 'selected' : ''}>OpenStreetMap</option>
                                    <option value="Carto Light" ${item.config.basemap === 'Carto Light' ? 'selected' : ''}>Carto Light</option>
                                    <option value="Carto Dark" ${item.config.basemap === 'Carto Dark' ? 'selected' : ''}>Carto Dark</option>
                                    <option value="Carto Voyager" ${item.config.basemap === 'Carto Voyager' ? 'selected' : ''}>Carto Voyager</option>
                                    <option value="Esri Satellite" ${item.config.basemap === 'Esri Satellite' ? 'selected' : ''}>Esri Satellite</option>
                                    <option value="Esri Topo" ${item.config.basemap === 'Esri Topo' ? 'selected' : ''}>Esri Topo</option>
                                </select>
                            </div>
                            <div class="row-custom">
                                <label>Select Layers:</label>
                                <div>
                                    <select id="mapLayers" multiple style="height: 150px;">
                                        ${AVAILABLE_LAYERS.map(layer => 
                                            `<option value="${layer.id}" ${(item.config.layers || []).includes(layer.id) ? 'selected' : ''}>${layer.title} (${layer.workspace})</option>`
                                        ).join('')}
                                    </select>
                                </div>
                            </div>
                            <div id="mapFiltersContainer" style="margin-top: 20px;">
                                <div style="background: #f9f9f9; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                        <strong style="font-size: 14px;">Layer Filters</strong>
                                    </div>
                                    <p style="color: #666; margin: 0 0 10px 0; font-size: 12px;">Set filters for selected WMS layers to show only specific features.</p>
                                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #eee; border-radius: 4px; padding: 10px; background: white;">
                                        ${generateLayerFiltersHTML(item)}
                                    </div>
                                    <p style="font-size: 11px; color: #999; margin-top: 8px;">
                                        <i class="bi bi-info-circle"></i> Leave fields empty to show all features for that layer.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mf">
                            <button class="btn" onclick="this.closest('.backdrop').remove()">Cancel</button>
                            <button class="btn btn-primary" id="saveConfig">Save</button>
                        </div>
                    `;
                    break;
                    
                case 'chart':
                    modalContent = `
                        <div class="mh">Configure Chart</div>
                        <div class="mb">
                            <div class="row-custom">
                                <label>Title:</label>
                                <input type="text" id="widgetTitle" value="${(item.title || 'Chart').replace(/"/g, '&quot;')}" style="flex: 1;">
                            </div>
                            <div class="row-custom">
                                <label>Chart Type:</label>
                                <select id="chartType">
                                    <option value="bar" ${item.config.type === 'bar' ? 'selected' : ''}>Bar Chart</option>
                                    <option value="line" ${item.config.type === 'line' ? 'selected' : ''}>Line Chart</option>
                                    <option value="scatter" ${item.config.type === 'scatter' ? 'selected' : ''}>Scatter Plot</option>
                                    <option value="pie" ${item.config.type === 'pie' ? 'selected' : ''}>Pie Chart</option>
                                    <option value="area" ${item.config.type === 'area' ? 'selected' : ''}>Area Chart</option>
                                    <option value="histogram" ${item.config.type === 'histogram' ? 'selected' : ''}>Histogram</option>
                                    <option value="box" ${item.config.type === 'box' ? 'selected' : ''}>Box Plot</option>
                                </select>
                            </div>
                            <div class="row-custom">
                                <label>Show Grid:</label>
                                <select id="chartShowGrid">
                                    <option value="true" ${(item.config.showGrid !== false) ? 'selected' : ''}>Yes</option>
                                    <option value="false" ${(item.config.showGrid === false) ? 'selected' : ''}>No</option>
                                </select>
                            </div>
                            <div class="row-custom">
                                <label>Chart Color Scheme:</label>
                                <select id="chartColorScheme">
                                    <option value="default" ${(!item.config.colorScheme || item.config.colorScheme === 'default') ? 'selected' : ''}>Default</option>
                                    <option value="viridis" ${item.config.colorScheme === 'viridis' ? 'selected' : ''}>Viridis</option>
                                    <option value="warm" ${item.config.colorScheme === 'warm' ? 'selected' : ''}>Warm</option>
                                    <option value="cool" ${item.config.colorScheme === 'cool' ? 'selected' : ''}>Cool</option>
                                    <option value="earth" ${item.config.colorScheme === 'earth' ? 'selected' : ''}>Earth</option>
                                </select>
                            </div>
                            <div class="row-custom">
                                <label>Data Layer:</label>
                                <select id="chartLayer">
                                    <option value="">Select a layer...</option>
                                    ${AVAILABLE_LAYERS.map(layer => 
                                        `<option value="${layer.id}" ${item.config.layer === layer.id ? 'selected' : ''}>${layer.title}</option>`
                                    ).join('')}
                                </select>
                            </div>
<div class="row-custom">
    <label>Series Label:</label>
    <input type="text" id="chartLabel" value="${item.config.label || ''}" placeholder="Optional label for series">
</div>
<div class="row-custom">
    <label>Aggregation:</label>
    <select id="chartAgg">
        <option value="count" ${item.config.aggregation === 'count' ? 'selected' : ''}>Count</option>
        <option value="sum" ${item.config.aggregation === 'sum' ? 'selected' : ''}>Sum</option>
        <option value="avg" ${item.config.aggregation === 'avg' ? 'selected' : ''}>Average</option>
        <option value="min" ${item.config.aggregation === 'min' ? 'selected' : ''}>Min</option>
        <option value="max" ${item.config.aggregation === 'max' ? 'selected' : ''}>Max</option>
    </select>
</div>
<div class="row-custom" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #eef0f4;">
    <label style="font-weight: 600;">Second Series (Line - for Bar/Area charts):</label>
</div>
<div class="row-custom">
    <label style="font-size: 12px; color: #666;">Enable Second Series:</label>
    <select id="chartEnableSecondSeries">
        <option value="false" ${!item.config.enableSecondSeries ? 'selected' : ''}>No</option>
        <option value="true" ${item.config.enableSecondSeries ? 'selected' : ''}>Yes</option>
    </select>
</div>
<div class="row-custom" id="secondSeriesConfig" style="${item.config.enableSecondSeries ? 'display: flex;' : 'display: none;'}">
    <label style="font-size: 12px; color: #666;">Second Y Field:</label>
    <select id="chartSecondYField">
        <option value="">-- choose layer first --</option>
    </select>
</div>
<div class="row-custom" id="secondSeriesLayerConfig" style="${item.config.enableSecondSeries ? 'display: flex;' : 'display: none;'}">
    <label style="font-size: 12px; color: #666;">Second Series Layer:</label>
    <select id="chartSecondLayer">
        <option value="">Same as primary</option>
        ${AVAILABLE_LAYERS.map(layer => 
            `<option value="${layer.id}" ${item.config.secondLayer === layer.id ? 'selected' : ''}>${layer.title}</option>`
        ).join('')}
    </select>
</div>
<div class="row-custom" id="secondSeriesLabelConfig" style="${item.config.enableSecondSeries ? 'display: flex;' : 'display: none;'}">
    <label style="font-size: 12px; color: #666;">Second Series Label:</label>
    <input type="text" id="chartSecondLabel" value="${item.config.secondLabel || ''}" placeholder="Optional label">
</div>

                        </div>
                        <div class="mf">
                            <button class="btn" onclick="this.closest('.backdrop').remove()">Cancel</button>
                            <button class="btn btn-primary" id="saveConfig">Save</button>
                        </div>
                    `;
                    break;
                    
                case 'table':
                    modalContent = `
                        <div class="mh">Configure Table</div>
                        <div class="mb">
                            <div class="row-custom">
                                <label>Title:</label>
                                <input type="text" id="widgetTitle" value="${(item.title || 'Data Table').replace(/"/g, '&quot;')}" style="flex: 1;">
                            </div>
                            <div class="row-custom">
                                <label>Data Layer:</label>
                                <select id="tableLayer">
                                    <option value="">Select a layer...</option>
                                    ${AVAILABLE_LAYERS.map(layer => 
                                        `<option value="${layer.id}" ${item.config.layer === layer.id ? 'selected' : ''}>${layer.title}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div class="row-custom">
                                <label>Columns:</label>
                                <div style="flex: 1;">
                                    <div id="columnSelection" style="border: 1px solid #ddd; border-radius: 4px; padding: 8px; min-height: 100px; max-height: 200px; overflow-y: auto; background: #f9f9f9;">
                                        <div style="color: #999; text-align: center; padding: 20px;">Select a layer first</div>
                                    </div>
                                    <div style="margin-top: 4px; font-size: 11px; color: #666;">
                                        Select columns and drag to reorder
                                    </div>
                                </div>
                            </div>
                            <div class="row-custom">
                                <label>Row Limit:</label>
                                <input type="number" id="tableLimit" value="${item.config.limit || 100}" min="1" max="1000">
                            </div>
                        </div>
                        <div class="mf">
                            <button class="btn" onclick="this.closest('.backdrop').remove()">Cancel</button>
                            <button class="btn btn-primary" id="saveConfig">Save</button>
                        </div>
                    `;
                    break;
                    
                case 'counter':
                    modalContent = `
                        <div class="mh">Configure Counter</div>
                        <div class="mb">
                            <div class="row-custom">
                                <label>Title:</label>
                                <input type="text" id="widgetTitle" value="${(item.title || 'Counter').replace(/"/g, '&quot;')}" style="flex: 1;">
                            </div>
                            <div class="row-custom">
                                <label>Data Layer:</label>
                                <select id="counterLayer">
                                    <option value="">Select a layer...</option>
                                    ${AVAILABLE_LAYERS.map(layer => 
                                        `<option value="${layer.id}" ${item.config.layer === layer.id ? 'selected' : ''}>${layer.title}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div class="row-custom">
                                <label>Operation:</label>
                                <select id="counterOp">
                                    <option value="count" ${item.config.operation === 'count' ? 'selected' : ''}>Count</option>
                                    <option value="sum" ${item.config.operation === 'sum' ? 'selected' : ''}>Sum</option>
                                    <option value="avg" ${item.config.operation === 'avg' ? 'selected' : ''}>Average</option>
                                    <option value="min" ${item.config.operation === 'min' ? 'selected' : ''}>Minimum</option>
                                    <option value="max" ${item.config.operation === 'max' ? 'selected' : ''}>Maximum</option>
                                </select>
                            </div>
                            <div class="row-custom">
                                <label>Field:</label>
                                <select id="counterField">
                                    <option value="">Select a field...</option>
                                </select>
                            </div>
                        </div>
                        <div class="mf">
                            <button class="btn" onclick="this.closest('.backdrop').remove()">Cancel</button>
                            <button class="btn btn-primary" id="saveConfig">Save</button>
                        </div>
                    `;
                    break;
                    
                case 'text':
                    modalContent = `
                        <div class="mh">Configure HTML Widget</div>
                        <div class="mb">
                            <div class="row-custom">
                                <label>Title:</label>
                                <input type="text" id="widgetTitle" value="${(item.title || 'HTML Content').replace(/"/g, '&quot;')}" style="flex: 1;">
                            </div>
                            <div class="row-custom" style="flex-direction: column; align-items: stretch;">
                                <label style="margin-bottom: 8px;">HTML Content:</label>
                                <div id="quillEditorContainer" style="height: 300px; background: #fff;"></div>
                            </div>
                        </div>
                        <div class="mf">
                            <button class="btn" onclick="this.closest('.backdrop').remove()">Cancel</button>
                            <button class="btn btn-primary" id="saveConfig">Save</button>
                        </div>
                    `;
                    break;
            }
            
            modal.innerHTML = modalContent;
            backdrop.appendChild(modal);
            document.body.appendChild(backdrop);

// --- Initialize Quill editor for Text widget ---
(function quillEditorInitializer() {
    if (item.kind !== 'text') return;
    const quillContainer = document.getElementById('quillEditorContainer');
    if (!quillContainer) return;
    
    // Initialize Quill editor
    const quill = new Quill('#quillEditorContainer', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'align': [] }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });
    
    // Load existing content if available
    if (item.config.content) {
        quill.root.innerHTML = item.config.content;
    }
    
    // Store quill instance for save handler
    quillContainer.quillInstance = quill;
})();

// --- Inject field selection for Counter modal ---
(function counterFieldDynamicEnhancer() {
    if (item.kind !== 'counter') return;
    const layerSel = document.getElementById('counterLayer');
    const fieldSel = document.getElementById('counterField');
    if (!layerSel || !fieldSel) return; // not a counter modal

    async function populateFields(layerId) {
        if (!layerId) {
            fieldSel.innerHTML = '<option value="">-- choose layer first --</option>';
            return;
        }
        try {
            const data = await fetchLayerData(layerId, 1);
            if (!data || !data.features || data.features.length === 0) {
                fieldSel.innerHTML = '<option value="">(no features)</option>';
                return;
            }
            const props = data.features[0].properties || {};
            const keys = Object.keys(props);
            const numeric = keys.filter(k => typeof props[k] === 'number' || (!isNaN(parseFloat(props[k])) && props[k] !== null && props[k] !== ''));

            function makeOpts(arr, selected) {
                if (!arr.length) return '<option value="">(no numeric fields)</option>';
                return arr.map(k => `<option value="${k}" ${selected===k?'selected':''}>${k}</option>`).join('');
            }
            const selField = (item.config && item.config.field) || '';
            fieldSel.innerHTML = makeOpts(numeric.length ? numeric : keys, selField);
        } catch (e) {
            console.warn('Failed to populate fields for layer', layerId, e);
            fieldSel.innerHTML = '<option value="">(error)</option>';
        }
    }

    layerSel.addEventListener('change', e => populateFields(e.target.value));
    if (layerSel.value) populateFields(layerSel.value);
})();

// --- Inject X/Y field selection for Chart modal if missing ---
(function chartXYDynamicEnhancer() {
    if (item.kind !== 'chart') return;
    const layerSel = document.getElementById('chartLayer');
    if (!layerSel) return; // not a chart modal

    // If X/Y selects are already present, don't add duplicates
    let xSel = document.getElementById('chartXField');
    let ySel = document.getElementById('chartYField');

    // Helper to insert a row into the modal body
    function insertRow(label, selectId, afterEl) {
        const row = document.createElement('div');
        row.className = 'row-custom';
        row.innerHTML = `<label>${label}:</label><select id="${selectId}"><option value="">-- choose layer first --</option></select>`;
        afterEl.parentNode.insertBefore(row, afterEl.nextSibling);
        return row.querySelector('select');
    }

    // Find the layer row to insert after
    const rows = modal.querySelectorAll('.row-custom');
    let layerRow = null;
    rows.forEach(r => {
        const lab = r.querySelector('label');
        if (lab && /Layer/i.test(lab.textContent)) layerRow = r;
    });
    if (!layerRow) return;

    if (!xSel) xSel = insertRow('X Field (category)', 'chartXField', layerRow);
    if (!ySel) ySel = insertRow('Y Field (numeric)', 'chartYField', xSel.closest('.row-custom'));

    async function populateFields(layerId) {
        if (!layerId) {
            xSel.innerHTML = '<option value="">-- choose layer first --</option>';
            ySel.innerHTML = '<option value="">-- choose layer first --</option>';
            return;
        }
        try {
            const data = await fetchLayerData(layerId, 1);
            if (!data || !data.features || data.features.length === 0) {
                xSel.innerHTML = '<option value="">(no features)</option>';
                ySel.innerHTML = '<option value="">(no features)</option>';
                return;
            }
            const props = data.features[0].properties || {};
            const keys = Object.keys(props);
            const numeric = keys.filter(k => typeof props[k] === 'number' || (!isNaN(parseFloat(props[k])) && props[k] !== null && props[k] !== ''));
            const cats = keys.filter(k => typeof props[k] === 'string' || !numeric.includes(k));

            function makeOpts(arr, selected) {
                if (!arr.length) return '<option value="">(no fields)</option>';
                return arr.map(k => `<option value="${k}" ${selected===k?'selected':''}>${k}</option>`).join('');
            }
            const selX = (item.config && item.config.xField) || '';
            const selY = (item.config && item.config.yField) || '';
            xSel.innerHTML = makeOpts(cats.length ? cats : keys, selX);
            ySel.innerHTML = makeOpts(numeric.length ? numeric : keys, selY);
        } catch (e) {
            console.warn('Failed to populate fields for layer', layerId, e);
            xSel.innerHTML = '<option value="">(error)</option>';
            ySel.innerHTML = '<option value="">(error)</option>';
        }
    }

    layerSel.addEventListener('change', e => populateFields(e.target.value));
    if (layerSel.value) populateFields(layerSel.value);
})();

// --- Extend Save handler to capture X/Y if present ---
(function chartXYSaveHook() {
    if (item.kind !== 'chart') return;
    const saveBtn = document.getElementById('saveConfig');
    if (!saveBtn) return;
    const handler = function () {
        const xSel = document.getElementById('chartXField');
        const ySel = document.getElementById('chartYField');
        if (xSel && ySel) {
            item.config = item.config || {};
            item.config.xField = xSel.value || '';
            item.config.yField = ySel.value || '';
        }
    };
    // Attach before any existing listeners fire (capture phase false is fine)
    saveBtn.addEventListener('click', handler, { once: true });
})();


// If this is the Chart modal, wire up dynamic field loading
(function () {
    const layerSel = document.getElementById('chartLayer');
    const xSel = document.getElementById('chartXField');
    const ySel = document.getElementById('chartYField');
    if (!layerSel || !xSel || !ySel) return;
    async function populateFields(layerId) {
        if (!layerId) {
            xSel.innerHTML = '<option value="">-- choose layer first --</option>';
            ySel.innerHTML = '<option value="">-- choose layer first --</option>';
            return;
        }
        try {
            const data = await fetchLayerData(layerId, 1);
            if (!data || !data.features || data.features.length === 0) {
                xSel.innerHTML = '<option value="">(no features)</option>';
                ySel.innerHTML = '<option value="">(no features)</option>';
                return;
            }
            const props = data.features[0].properties || {};
            const keys = Object.keys(props);
            const numeric = keys.filter(k => typeof props[k] === 'number' || (!isNaN(parseFloat(props[k])) && props[k] !== null && props[k] !== ''));
            const cats = keys.filter(k => typeof props[k] === 'string' || !numeric.includes(k));
            function makeOpts(arr, selected) {
                if (!arr.length) return '<option value="">(no fields)</option>';
                return arr.map(k => `<option value="${k}" ${selected===k?'selected':''}>${k}</option>`).join('');
            }
            xSel.innerHTML = makeOpts(cats.length ? cats : keys, item.config.xField || '');
            ySel.innerHTML = makeOpts(numeric.length ? numeric : keys, item.config.yField || '');
        } catch (e) {
            console.warn('Failed to load properties for layer', layerId, e);
            xSel.innerHTML = '<option value="">(error)</option>';
            ySel.innerHTML = '<option value="">(error)</option>';
        }
    }
    layerSel.addEventListener('change', e => populateFields(e.target.value));
    if (layerSel.value) populateFields(layerSel.value);
})();

// Second series configuration handler
(function secondSeriesConfigHandler() {
    const enableSecondSeries = document.getElementById('chartEnableSecondSeries');
    const secondSeriesConfig = document.getElementById('secondSeriesConfig');
    const secondSeriesLayerConfig = document.getElementById('secondSeriesLayerConfig');
    const secondSeriesLabelConfig = document.getElementById('secondSeriesLabelConfig');
    const secondYField = document.getElementById('chartSecondYField');
    const secondLayer = document.getElementById('chartSecondLayer');
    const primaryLayer = document.getElementById('chartLayer');
    
    if (!enableSecondSeries) return;
    
    // Show/hide second series config based on enable toggle
    function toggleSecondSeriesVisibility() {
        const isEnabled = enableSecondSeries.value === 'true';
        secondSeriesConfig.style.display = isEnabled ? 'flex' : 'none';
        secondSeriesLayerConfig.style.display = isEnabled ? 'flex' : 'none';
        secondSeriesLabelConfig.style.display = isEnabled ? 'flex' : 'none';
        
        if (isEnabled && secondYField) {
            // Populate second Y field when enabled
            const layerToUse = secondLayer && secondLayer.value ? secondLayer.value : (primaryLayer ? primaryLayer.value : '');
            if (layerToUse) {
                populateSecondYField(layerToUse);
            }
        }
    }
    
    async function populateSecondYField(layerId) {
        if (!layerId || !secondYField) return;
        try {
            const data = await fetchLayerData(layerId, 1);
            if (!data || !data.features || data.features.length === 0) {
                secondYField.innerHTML = '<option value="">(no features)</option>';
                return;
            }
            const props = data.features[0].properties || {};
            const keys = Object.keys(props);
            const numeric = keys.filter(k => typeof props[k] === 'number' || (!isNaN(parseFloat(props[k])) && props[k] !== null && props[k] !== ''));
            const cats = keys.filter(k => typeof props[k] === 'string' || !numeric.includes(k));
            function makeOpts(arr, selected) {
                if (!arr.length) return '<option value="">(no fields)</option>';
                return arr.map(k => `<option value="${k}" ${selected===k?'selected':''}>${k}</option>`).join('');
            }
            const selY = (item.config && item.config.secondYField) || '';
            secondYField.innerHTML = makeOpts(numeric.length ? numeric : keys, selY);
        } catch (e) {
            console.warn('Failed to populate second Y field for layer', layerId, e);
            secondYField.innerHTML = '<option value="">(error)</option>';
        }
    }
    
    enableSecondSeries.addEventListener('change', toggleSecondSeriesVisibility);
    if (primaryLayer) {
        primaryLayer.addEventListener('change', () => {
            if (enableSecondSeries.value === 'true' && (!secondLayer || !secondLayer.value)) {
                populateSecondYField(primaryLayer.value);
            }
        });
    }
    if (secondLayer) {
        secondLayer.addEventListener('change', () => {
            if (enableSecondSeries.value === 'true') {
                populateSecondYField(secondLayer.value || (primaryLayer ? primaryLayer.value : ''));
            }
        });
    }
    
    // Initialize visibility
    toggleSecondSeriesVisibility();
})();

// If this is the Table modal, wire up column selection
(function () {
    const layerSel = document.getElementById('tableLayer');
    const columnDiv = document.getElementById('columnSelection');
    if (!layerSel || !columnDiv) return;
    
    let draggedElement = null;
    
    async function populateColumns(layerId) {
        if (!layerId) {
            columnDiv.innerHTML = '<div style="color: #999; text-align: center; padding: 20px;">Select a layer first</div>';
            return;
        }
        try {
            const data = await fetchLayerData(layerId, 1);
            if (!data || !data.features || data.features.length === 0) {
                columnDiv.innerHTML = '<div style="color: #999; text-align: center; padding: 20px;">No features found</div>';
                return;
            }
            const props = data.features[0].properties || {};
            const allColumns = Object.keys(props);
            
            // Get previously selected columns or use all columns
            const selectedColumns = item.config.columns && item.config.columns.length > 0 
                ? item.config.columns.filter(col => allColumns.includes(col))
                : allColumns;
            
            columnDiv.innerHTML = '';
            
            allColumns.forEach(col => {
                const isSelected = selectedColumns.includes(col);
                const colDiv = document.createElement('div');
                colDiv.className = 'column-item';
                colDiv.draggable = true;
                colDiv.dataset.column = col;
                colDiv.style.cssText = 'display: flex; align-items: center; padding: 6px 8px; margin: 4px 0; background: white; border: 1px solid #ddd; border-radius: 4px; cursor: move; user-select: none;';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = isSelected;
                checkbox.style.marginRight = '8px';
                checkbox.addEventListener('change', () => {
                    colDiv.classList.toggle('selected', checkbox.checked);
                });
                
                const label = document.createElement('span');
                label.textContent = col;
                label.style.flex = '1';
                
                const handle = document.createElement('span');
                handle.innerHTML = '☰';
                handle.style.cssText = 'color: #999; margin-left: 8px; cursor: grab;';
                
                colDiv.appendChild(checkbox);
                colDiv.appendChild(label);
                colDiv.appendChild(handle);
                
                if (isSelected) {
                    colDiv.classList.add('selected');
                }
                
                // Drag and drop handlers
                colDiv.addEventListener('dragstart', (e) => {
                    draggedElement = colDiv;
                    colDiv.style.opacity = '0.5';
                });
                
                colDiv.addEventListener('dragend', (e) => {
                    colDiv.style.opacity = '1';
                    draggedElement = null;
                });
                
                colDiv.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    const afterElement = getDragAfterElement(columnDiv, e.clientY);
                    if (afterElement == null) {
                        columnDiv.appendChild(draggedElement);
                    } else {
                        columnDiv.insertBefore(draggedElement, afterElement);
                    }
                });
                
                columnDiv.appendChild(colDiv);
            });
            
            // Sort to put selected columns in their saved order
            if (selectedColumns.length > 0) {
                selectedColumns.forEach(col => {
                    const colEl = columnDiv.querySelector(`[data-column="${col}"]`);
                    if (colEl) {
                        columnDiv.appendChild(colEl);
                    }
                });
            }
            
        } catch (e) {
            console.warn('Failed to load columns for layer', layerId, e);
            columnDiv.innerHTML = '<div style="color: #999; text-align: center; padding: 20px;">Error loading columns</div>';
        }
    }
    
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.column-item:not([style*="opacity: 0.5"])')]
            .filter(el => el !== draggedElement);
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
    
    layerSel.addEventListener('change', e => populateColumns(e.target.value));
    if (layerSel.value) populateColumns(layerSel.value);
})();


            
            // Handle save
            const saveBtn = modal.querySelector('#saveConfig');
            if (saveBtn) {
                saveBtn.addEventListener('click', () => {
                    // Update title for all widget types
                    const titleInput = document.getElementById('widgetTitle');
                    if (titleInput) {
                        item.title = titleInput.value;
                        // Update the title in the DOM
                        const itemDiv = document.querySelector(`[data-id="${item.id}"]`);
                        if (itemDiv) {
                            const titleSpan = itemDiv.querySelector('.title');
                            if (titleSpan) {
                                titleSpan.textContent = item.title;
                            }
                        }
                    }
                    
                    switch (item.kind) {
                        case 'map':
                            const selectedBasemap = document.getElementById('mapBasemap').value;
                            item.config.basemap = selectedBasemap;
                            const selectedLayers = Array.from(document.getElementById('mapLayers').selectedOptions).map(opt => opt.value);
                            item.config.layers = selectedLayers;
                            
                            // Capture filters from the modal
                            const filtersForm = document.querySelector('#mapFiltersContainer');
                            if (filtersForm) {
                                const layerFilters = {};
                                selectedLayers.forEach(layerId => {
                                    const sanitizedLayerId = layerId.replace(/:/g, '_');
                                    const conditionDivs = filtersForm.querySelectorAll('.filter-conditions-' + sanitizedLayerId + ' > div');
                                    const conditions = [];
                                    
                                    conditionDivs.forEach((conditionDiv, idx) => {
                                        const attributeInput = conditionDiv.querySelector('input[name*="[attribute]"]');
                                        const operatorSelect = conditionDiv.querySelector('select[name*="[operator]"]');
                                        const valueInput = conditionDiv.querySelector('input[name*="[value]"]');
                                        const logicSelect = conditionDiv.querySelector('select[name*="[logic]"]');
                                        
                                        if (attributeInput && operatorSelect && valueInput) {
                                            const attribute = attributeInput.value.trim();
                                            const value = valueInput.value.trim();
                                            
                                            if (attribute && value) {
                                                conditions.push({
                                                    attribute: attribute,
                                                    operator: operatorSelect.value,
                                                    value: value,
                                                    logic: logicSelect ? logicSelect.value : 'AND'
                                                });
                                            }
                                        }
                                    });
                                    
                                    if (conditions.length > 0) {
                                        layerFilters[layerId] = conditions;
                                    }
                                });
                                
                                item.config.filters = layerFilters;
                            }
                            break;
                        case 'chart':
                            item.config.type = document.getElementById('chartType').value;
                            item.config.layer = document.getElementById('chartLayer').value;
                            item.config.showGrid = document.getElementById('chartShowGrid').value === 'true';
                            item.config.colorScheme = document.getElementById('chartColorScheme').value;
                            if (document.getElementById('chartLabel')) {
                                item.config.label = document.getElementById('chartLabel').value || '';
                            }
                            if (document.getElementById('chartXField')) {
                                item.config.xField = document.getElementById('chartXField').value || '';
                            }
                            if (document.getElementById('chartYField')) {
                                item.config.yField = document.getElementById('chartYField').value || '';
                            }
                            if (document.getElementById('chartAgg')) {
                                item.config.aggregation = document.getElementById('chartAgg').value || 'count';
                            }
                            // Second series configuration
                            if (document.getElementById('chartEnableSecondSeries')) {
                                item.config.enableSecondSeries = document.getElementById('chartEnableSecondSeries').value === 'true';
                            }
                            if (document.getElementById('chartSecondYField')) {
                                item.config.secondYField = document.getElementById('chartSecondYField').value || '';
                            }
                            if (document.getElementById('chartSecondLayer')) {
                                item.config.secondLayer = document.getElementById('chartSecondLayer').value || '';
                            }
                            if (document.getElementById('chartSecondLabel')) {
                                item.config.secondLabel = document.getElementById('chartSecondLabel').value || '';
                            }
                            break;
                        case 'table':
                            item.config.layer = document.getElementById('tableLayer').value;
                            item.config.limit = parseInt(document.getElementById('tableLimit').value);
                            // Capture selected columns in order
                            const columnDiv = document.getElementById('columnSelection');
                            if (columnDiv) {
                                const columnItems = columnDiv.querySelectorAll('.column-item');
                                item.config.columns = Array.from(columnItems)
                                    .filter(el => el.querySelector('input[type="checkbox"]').checked)
                                    .map(el => el.dataset.column);
                            }
                            break;
                        case 'counter':
                            item.config.layer = document.getElementById('counterLayer').value;
                            item.config.operation = document.getElementById('counterOp').value;
                            item.config.field = document.getElementById('counterField').value;
                            break;
                        case 'text':
                            // Get HTML content from Quill editor
                            const quillContainer = document.getElementById('quillEditorContainer');
                            if (quillContainer && quillContainer.quillInstance) {
                                item.config.content = quillContainer.quillInstance.root.innerHTML;
                            }
                            break;
                    }
                    
                    // Re-render the widget
                    const itemDiv = document.querySelector(`[data-id="${item.id}"]`);
                    if (itemDiv) {
                        const pad = itemDiv.querySelector('.pad');
                        renderWidgetContent(item, pad);
                        
                        // If it's a map, invalidate size after a short delay
                        if (item.kind === 'map') {
                            setTimeout(() => {
                                const mapDiv = pad.querySelector('[id^="map-"]');
                                if (mapDiv && mapDiv._leaflet_map) {
                                    mapDiv._leaflet_map.invalidateSize();
                                }
                            }, 100);
                        }
                    }
                    
                    backdrop.remove();
                });
            }
            
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    backdrop.remove();
                }
            });
        }

        function toggleMaximize(div) {
            div.classList.toggle('maximized');
        }

        function makeDraggable(element, handle, item) {
            let isDragging = false;
            let startX, startY, startLeft, startTop;
            
            handle.addEventListener('mousedown', (e) => {
                if (e.target.classList.contains('title') || e.target.closest('.tools')) return;
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                startLeft = element.offsetLeft;
                startTop = element.offsetTop;
                e.preventDefault();
            });
            
            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                item.x = Math.max(0, startLeft + dx);
                item.y = Math.max(0, startTop + dy);
                element.style.left = item.x + 'px';
                element.style.top = item.y + 'px';
            });
            
            document.addEventListener('mouseup', () => {
                isDragging = false;
            });
        }

        function makeResizable(element, handle, item) {
            let isResizing = false;
            let startX, startY, startW, startH;
            
            handle.addEventListener('mousedown', (e) => {
                isResizing = true;
                startX = e.clientX;
                startY = e.clientY;
                startW = element.offsetWidth;
                startH = element.offsetHeight;
                e.preventDefault();
                e.stopPropagation();
            });
            
            document.addEventListener('mousemove', (e) => {
                if (!isResizing) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                item.w = Math.max(200, startW + dx);
                item.h = Math.max(150, startH + dy);
                element.style.width = item.w + 'px';
                element.style.height = item.h + 'px';
            });
            
            document.addEventListener('mouseup', () => {
                isResizing = false;
            });
        }

        // Test button
        document.getElementById('testBtn').addEventListener('click', () => {
            // Add one of each widget type for testing
            addItem('map');
            addItem('chart');
            addItem('table');
            addItem('counter');
            addItem('text');
        });

        // Clear button
        document.getElementById('clearBtn').addEventListener('click', () => {
            if (confirm('Clear all widgets?')) {
                items = [];
                canvas.innerHTML = '';
            }
        });

        // Save button
        document.getElementById('saveBtn').addEventListener('click', () => {
            // Create a modal for dashboard details
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                background: rgba(0,0,0,0.5); z-index: 10000; display: flex; 
                align-items: center; justify-content: center;
            `;
            
            modal.innerHTML = `
                <div style="background: white; padding: 20px; border-radius: 8px; width: 400px; max-width: 90vw;">
                    <h3 style="margin-top: 0;">Save Dashboard</h3>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Title:</label>
                        <input type="text" id="dashboardTitle" value="${<?php echo $dashboard ? '"' . htmlspecialchars($dashboard['title']) . '"' : '"Untitled Dashboard"'; ?>}" 
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Description:</label>
                        <textarea id="dashboardDescription" rows="3" 
                                  style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">${<?php echo $dashboard ? '"' . htmlspecialchars($dashboard['description']) . '"' : '""'; ?>}</textarea>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Category:</label>
                        <select id="dashboardCategory" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Select a category (optional)</option>
                            <?php
                            try {
                                $categories = getCategoriesForDropdown();
                                foreach ($categories as $category) {
                                    $selected = ($dashboard && $dashboard['category_id'] == $category['id']) ? 'selected' : '';
                                    echo '<option value="' . $category['id'] . '" ' . $selected . '>' . htmlspecialchars($category['name']) . '</option>';
                                }
                            } catch (Exception $e) {
                                // Categories table might not exist yet
                            }
                            ?>
                        </select>
                    </div>
                    <div style="text-align: right;">
                        <button id="cancelSave" style="margin-right: 10px; padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px;">Cancel</button>
                        <button id="confirmSave" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px;">Save</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Handle cancel
            document.getElementById('cancelSave').addEventListener('click', () => {
                document.body.removeChild(modal);
            });
            
            // Handle save
            document.getElementById('confirmSave').addEventListener('click', () => {
                const title = document.getElementById('dashboardTitle').value.trim();
                if (!title) {
                    alert('Please enter a dashboard title.');
                    return;
                }
                
                const description = document.getElementById('dashboardDescription').value.trim();
                const categoryId = document.getElementById('dashboardCategory').value;
                
                // Capture live map extents before saving
                try {
                    items = items.map(it => {
                        if (it.kind === 'map') {
                            const mapEl = document.getElementById('map-' + it.id);
                            const mapInst = mapEl && mapEl._leaflet_map;
                            if (mapInst) {
                                const c = mapInst.getCenter();
                                const z = mapInst.getZoom();
                                const b = mapInst.getBounds();
                                it.config = it.config || {};
                                it.config.center = [c.lat, c.lng];
                                it.config.zoom = z;
                                it.config.bounds = [[b.getSouthWest().lat, b.getSouthWest().lng],[b.getNorthEast().lat, b.getNorthEast().lng]];
                            }
                        }
                        return it;
                    });
                } catch (e) {
                    console.warn('Could not capture map state before save:', e);
                }

                const config = {
                    items: items
                };
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="title" value="${title}">
                    <input type="hidden" name="description" value="${description}">
                    <input type="hidden" name="category_id" value="${categoryId}">
                    <input type="hidden" name="config" value='${JSON.stringify(config)}'>
                `;
                document.body.appendChild(form);
                form.submit();
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
