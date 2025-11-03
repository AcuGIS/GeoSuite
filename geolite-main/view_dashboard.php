<?php

// Include required files
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Config.php';
require_once 'incl/Database.php';

// Require authentication
//requireAuth();

// Get dashboard ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    http_response_code(404);
    die('Bad request! Dashboard ID is required.');
}

// Check view permission
if (!canView('dashboard', $id)) {
    header('Location: index.php?error=access_denied');
    exit;
}

try {
    $dashboard = getDashboardById($id);
    if (!$dashboard) {
        http_response_code(404);
        die('Dashboard not found!');
    }
} catch (Exception $e) {
    http_response_code(500);
    die('Error loading dashboard.');
}

$dashboardConfig = json_decode($dashboard['config'], true);
$geoServerConfig = getGeoServerConfig();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dashboard['title']); ?> - GeoLite Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        :root {
            --bg: #f6f7fb;
            --panel: #fff;
            --muted: #6b7280;
            --text: #1f2937;
            --accent: #2563eb;
            --shadow: 0 10px 24px rgba(0,0,0,.08);
            --radius: 14px;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
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
            background: #696969;
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
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #333;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .btn:hover {
            background: #f3f4f6;
        }
        .btn-primary:hover {
            background: #1d4ed8;
            color: #fff;
        }
        .wrap {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            padding: 16px;
        }
        .canvas {
            position: relative;
            min-height: calc(100vh - 88px);
            border-radius: 12px;
        }
        .item {
            position: absolute;
            background: #fff;
            border-radius: 0px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            overflow: hidden;
            border-top: 5px solid #d97706;
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
        }
        .title {
            font-weight: 900;
            padding: 2px 4px;
            border-radius: 4px;
            font-size: 14px;
            color: #6495ed;
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

        .pad {
            padding: 12px;
        }
        .leaflet-container {
            height: 100%;
            width: 100%;
        }
        .leaflet-popup-content {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
            line-height: 1.4;
        }
        .leaflet-popup-content-wrapper {
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .leaflet-popup-tip {
            background: #fff;
            border: 1px solid #e5e7eb;
        }
        
        /* Magnifying glass styles */
        .magnifying-glass {
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 16px;
            color: #6b7280;
        }
        
        .magnifying-glass:hover {
            color: #2563eb;
            transform: scale(1.1);
        }
        
        .feature-highlight-marker {
            z-index: 1000;
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
            background: #1d4ed8;
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


/* allow the toolbar to live outside the plot box */
.js-plotly-plot { position: relative; overflow: visible !important; }

/* move it up/left, keep it above the header, keep it visible */
.js-plotly-plot .modebar,
.js-plotly-plot .modebar-container {
  position: absolute !important;
  left: 40px !important;
  top: 10px !important;                     /* anchor at the top */
  transform: translateY(-26px) !important;/* lift into the header area */
  z-index: 99999 !important;
  opacity: 1 !important;                 /* <-- stop hover fade-out */
  pointer-events: auto;
}

/* if your header is covering it, keep header below the toolbar */
.card-header { position: relative; z-index: 1; }


/* Ensure the Plotly container can show the toolbar cleanly */
.js-plotly-plot,
.js-plotly-plot .plotly {
  position: relative;
  overflow: visible !important;
}

/* Move toolbar to top-right, aligned just below header line */
.js-plotly-plot .modebar,
.js-plotly-plot .modebar-container {
  position: absolute !important;
  top: -18px !important;     /* lifts into header space */
  right: 10px !important;    /* keep near chart edge */
  left: auto !important;
  transform: none !important;
  z-index: 1000 !important;
  opacity: 1 !important;     /* prevent fade-out */
  pointer-events: auto;
}

/* Make sure card body doesn't clip it */
.card-body {
  position: relative;
  overflow: visible !important;
}

/* Keep header flat, not blocking it */
.card-header {
  position: relative;
  z-index: 1;
  overflow: visible !important;
}

.body {
    height: calc(100% - 40px);
    overflow: auto;
}



    </style>
</head>
<body>
    <div class="topbar">
        <div>
            <strong style="color: white;"><?php echo htmlspecialchars($dashboard['title']); ?></strong>
            <?php if (!empty($dashboard['description'])): ?>
                <br><span style="font-size: 12px; color: #e5e7eb;"><?php echo htmlspecialchars($dashboard['description']); ?></span>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="index.php" class="btn">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="dashboard_builder.php?id=<?php echo $id; ?>" class="btn">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <button class="btn btn-primary" id="exportPdfBtn" title="Export Dashboard to PDF">
                <i class="bi bi-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>
    
    <div class="wrap">
        <main class="canvas" id="canvas"></main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        // Configuration from PHP
        const DASHBOARD_EDITOR = false;
        const DASHBOARD_ID = <?php echo $id; ?>;
        const DASHBOARD_CONFIG = <?php echo json_encode($dashboardConfig); ?>;
        const canvas = document.getElementById('canvas');

        // Build filter map from all map widgets
        const layerFilters = {};
        if (DASHBOARD_CONFIG && DASHBOARD_CONFIG.items) {
            DASHBOARD_CONFIG.items.forEach(item => {
                if (item.kind === 'map' && item.config.filters) {
                    Object.keys(item.config.filters).forEach(layerId => {
                        layerFilters[layerId] = item.config.filters[layerId];
                    });
                }
            });
        }

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
                
                // Apply CQL filter if configured for this layer in any map widget
                if (layerFilters[layerName]) {
                    const cqlFilter = buildCqlFilterForData(layerFilters, layerName);
                    if (cqlFilter) {
                        wfsUrl += `&CQL_FILTER=${encodeURIComponent(cqlFilter)}`;
                        console.log('Applying filter when fetching data for', layerName + ':', cqlFilter);
                    }
                }
                
                const response = await fetch(wfsUrl);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
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
            // Find all widgets that have layer configurations
            if (DASHBOARD_CONFIG && DASHBOARD_CONFIG.items) {
                DASHBOARD_CONFIG.items.forEach(item => {
                    if (item.config.layer && (item.kind === 'chart' || item.kind === 'table' || item.kind === 'counter')) {
                        updateWidgetForBounds(item, mapBounds);
                    }
                });
            }
        }

        // Function to update a single widget based on map bounds
        async function updateWidgetForBounds(item, mapBounds) {
            try {
                const data = await fetchLayerData(item.config.layer, 1000);
                if (!data || !data.features) return;
                
                // Filter features by current map bounds
                const filteredFeatures = filterFeaturesByBounds(data.features, mapBounds);
                
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
                `;
                
                // Add magnifying glass column header
                tableHTML += `<th style="padding: 6px; text-align: center; border: 1px solid #dee2e6; width: 40px;"><span class="magnifying-glass">🔍</span></th>`;
                
                // Use configured columns if available, otherwise use first 5 columns
                const displayProps = item.config.columns && item.config.columns.length > 0 
                    ? item.config.columns.filter(col => propNames.includes(col))
                    : propNames.slice(0, 5);
                displayProps.forEach(prop => {
                    tableHTML += `<th style="padding: 6px; text-align: left; border: 1px solid #dee2e6;">${prop}</th>`;
                });
                
                tableHTML += `
                            </tr>
                            <tr style="background-color: #e9ecef; border-bottom: 1px solid #dee2e6;">
                                <td colspan="${displayProps.length + 1}" style="padding: 4px 6px; text-align: left; border: 1px solid #dee2e6; font-size: 11px; color: #6c757d;">Features in view: ${features.length}</td>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                features.forEach((feature, index) => {
                    const bgColor = index % 2 === 0 ? '' : 'background-color: #f8f9fa;';
                    tableHTML += `<tr style="border-bottom: 1px solid #dee2e6; ${bgColor}">`;
                    
                    // Add magnifying glass cell with click handler
                    tableHTML += `<td style="padding: 6px; border: 1px solid #dee2e6; text-align: center;" onclick="zoomToFeature(${JSON.stringify(feature).replace(/"/g, '&quot;')}, '${item.config.layer}')" title="Zoom to feature on map"><span class="magnifying-glass">🔍</span></td>`;
                    
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
                             // item.config.operation === 'sum' ? `Sum of ${item.config.field}` :
                             item.config.operation === 'avg' ? `Avg of ${item.config.field}` :
                             item.config.operation === 'min' ? `Min of ${item.config.field}` :
                             item.config.operation === 'max' ? `Max of ${item.config.field}` : '';
                
                if (valueDiv && labelDiv && descDiv) {
                    valueDiv.textContent = formatNumber(value);
                    labelDiv.textContent = label;
                    // descDiv.textContent = `in current view (${count} features)`;
                } else {
                    container.innerHTML = `
                        <div style="font-size: 48px; font-weight: bold; color: var(--accent); text-align: center;">${formatNumber(value)}</div>
                        <div style="color: #999; margin-top: 10px; text-align: center;">${label}</div>
                        <!-- <div style="color: #666; font-size: 12px; margin-top: 5px; text-align: center;">in current view (${count} features)</div> -->
                    `;
                }
            } catch (error) {
                console.error('Error updating counter:', error);
            }
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

        // Function to zoom to a specific feature on the map
        function zoomToFeature(feature, layerName) {
            try {
                // Find all map widgets in the dashboard
                const mapWidgets = DASHBOARD_CONFIG.items.filter(item => item.kind === 'map');
                
                if (mapWidgets.length === 0) {
                    console.warn('No map widgets found in dashboard');
                    return;
                }
                
                // Use the first map widget (you could enhance this to find the specific map)
                const mapWidget = mapWidgets[0];
                const mapElement = document.getElementById('map-' + mapWidget.id);
                
                if (!mapElement || !mapElement._leaflet_map) {
                    console.warn('Map element not found or not initialized');
                    return;
                }
                
                const map = mapElement._leaflet_map;
                
                // Check if the feature has geometry
                if (!feature.geometry || !feature.geometry.coordinates) {
                    console.warn('Feature has no geometry to zoom to');
                    return;
                }
                
                // Handle different geometry types
                let bounds;
                switch (feature.geometry.type) {
                    case 'Point':
                        // For points, zoom to the point with a reasonable zoom level
                        const coords = feature.geometry.coordinates;
                        map.setView([coords[1], coords[0]], Math.max(map.getZoom(), 15), {
                            animate: true,
                            duration: 1.0
                        });
                        break;
                        
                    case 'Polygon':
                    case 'MultiPolygon':
                    case 'LineString':
                    case 'MultiLineString':
                        // For complex geometries, fit bounds
                        try {
                            const geoJsonLayer = L.geoJSON(feature);
                            bounds = geoJsonLayer.getBounds();
                            map.fitBounds(bounds, {
                                animate: true,
                                duration: 1.0,
                                padding: [20, 20]
                            });
                        } catch (error) {
                            console.error('Error creating bounds for feature:', error);
                            // Fallback to point zoom if bounds creation fails
                            const coords = feature.geometry.coordinates;
                            if (Array.isArray(coords) && coords.length >= 2) {
                                map.setView([coords[1], coords[0]], Math.max(map.getZoom(), 15), {
                                    animate: true,
                                    duration: 1.0
                                });
                            }
                        }
                        break;
                        
                    default:
                        console.warn('Unsupported geometry type:', feature.geometry.type);
                        return;
                }
                
                // Add a temporary marker to highlight the feature
                let marker;
                if (feature.geometry.type === 'Point') {
                    const coords = feature.geometry.coordinates;
                    marker = L.marker([coords[1], coords[0]], {
                        icon: L.divIcon({
                            className: 'feature-highlight-marker',
                            html: '<div style="background-color: #ff0000; border: 2px solid #fff; border-radius: 50%; width: 20px; height: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                            iconSize: [20, 20],
                            iconAnchor: [10, 10]
                        })
                    }).addTo(map);
                } else {
                    // For non-point geometries, add a temporary highlight layer
                    const highlightLayer = L.geoJSON(feature, {
                        style: {
                            color: '#ff0000',
                            weight: 3,
                            opacity: 0.8,
                            fillOpacity: 0.2
                        }
                    }).addTo(map);
                    marker = highlightLayer; // Use the layer as our "marker"
                }
                
                // Remove the highlight after 3 seconds
                setTimeout(() => {
                    if (marker) {
                        map.removeLayer(marker);
                    }
                }, 3000);
                
            } catch (error) {
                console.error('Error zooming to feature:', error);
            }
        }

        // Load and render dashboard
        if (DASHBOARD_CONFIG && DASHBOARD_CONFIG.items) {
            DASHBOARD_CONFIG.items.forEach(item => {
                createItemElement(item);
            });
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
            titleSpan.textContent = item.title;
            
            const tools = document.createElement('div');
            tools.className = 'tools';
            
            const maxBtn = document.createElement('button');
            maxBtn.className = 'tbtn maximize';
            maxBtn.title = 'Maximize';
            maxBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleMaximize(div);
            });
            
            tools.appendChild(maxBtn);
            header.appendChild(titleSpan);
            header.appendChild(tools);
            
            const body = document.createElement('div');
            body.className = 'body';
            
            const pad = document.createElement('div');
            pad.className = 'pad';
            
            // Render widget content
            renderWidgetContent(item, pad);
            
            body.appendChild(pad);
            div.appendChild(header);
            div.appendChild(body);
            canvas.appendChild(div);
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
                        } catch (error) {
                            console.error('Failed to initialize map:', error);
                            mapDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Failed to load map: ' + error.message + '</div>';
                        }
                    }, 100);
                    break;
                    
                case 'chart':
                    // Create a chart
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
                                
                                // Add magnifying glass column header
                                tableHTML += `<th style="padding: 6px; text-align: center; border: 1px solid #dee2e6; width: 40px;"><span class="magnifying-glass">🔍</span></th>`;
                                
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
                                    
                                    // Add magnifying glass cell with click handler
                                    tableHTML += `<td style="padding: 6px; border: 1px solid #dee2e6; text-align: center;" onclick="zoomToFeature(${JSON.stringify(feature).replace(/"/g, '&quot;')}, '${item.config.layer}')" title="Zoom to feature on map"><span class="magnifying-glass">🔍</span></td>`;
                                    
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
                    valueDiv.style.fontSize = '32px';
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
                                             // item.config.operation === 'sum' ? `Sum of ${item.config.field}` :
                                             item.config.operation === 'avg' ? `Avg of ${item.config.field}` :
                                             item.config.operation === 'min' ? `Min of ${item.config.field}` :
                                             item.config.operation === 'max' ? `Max of ${item.config.field}` : '';
                                
                                valueDiv.textContent = formatNumber(value);
                                labelDiv.textContent = label;
                                // descDiv.textContent = `from ${item.config.layer}`;
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
                    const textContent = document.createElement('div');
                    textContent.style.minHeight = '100px';
                    textContent.style.width = '100%';
                    textContent.style.padding = '10px';
                    textContent.style.overflow = 'auto';
                    textContent.style.boxSizing = 'border-box';
                    // Render HTML content (from Quill)
                    textContent.innerHTML = item.config.content || '<p>Text content</p>';
                    container.appendChild(textContent);
                    break;
            }
        }

        function toggleMaximize(div) {
            div.classList.toggle('maximized');
            const maxBtn = div.querySelector('.maximize');
            if (maxBtn) {
                maxBtn.classList.toggle('maximized');
            }
            
            // If there's a map, invalidate its size after a short delay
            setTimeout(() => {
                const mapDiv = div.querySelector('.body .pad > div');
                if (mapDiv && mapDiv._leaflet_map) {
                    mapDiv._leaflet_map.invalidateSize();
                }
            }, 100);
        }

        // PDF Export Function
        function exportToPDF() {
            const exportBtn = document.getElementById('exportPdfBtn');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';
            exportBtn.disabled = true;
            
            exportBtn.style.display = 'none';
            
            setTimeout(() => {
                const dashboardArea = document.querySelector('.wrap');
                
                const opt = {
                    margin: [0, 0, 0, 0],
                    filename: `dashboard-<?php echo preg_replace('/[^a-zA-Z0-9-_]/', '', $dashboard['title']); ?>-${new Date().toISOString().split('T')[0]}.pdf`,
                    image: { type: 'jpeg', quality: 0.95 },
                    html2canvas: { 
                        scale: 0.8,
                        useCORS: true,
                        letterRendering: true,
                        allowTaint: true,
                        backgroundColor: '#f6f7fb',
                        scrollX: 0,
                        scrollY: 0,
                        width: dashboardArea.scrollWidth,
                        height: dashboardArea.scrollHeight
                    },
                    jsPDF: { 
                        unit: 'mm', 
                        format: 'a4', 
                        orientation: 'landscape' 
                    }
                };
                
                html2pdf().set(opt).from(dashboardArea).save().then(() => {
                    exportBtn.style.display = 'inline-flex';
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;
                }).catch((error) => {
                    console.error('PDF generation failed:', error);
                    alert('Failed to generate PDF. Please try again.');
                    exportBtn.style.display = 'inline-flex';
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;
                });
            }, 500);
        }

        document.getElementById('exportPdfBtn').addEventListener('click', exportToPDF);
    </script>
</body>
</html>
