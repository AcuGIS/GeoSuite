<?php

require_once 'incl/Config.php';

function generateZoomButtonJavaScript($layerId, $layer, $geoserverUrl, $proxyUrl) {
    ob_start();
    ?>
    var zoom<?php echo $layerId; ?>Btn = document.getElementById("zoom-<?php echo $layerId; ?>");
    if (zoom<?php echo $layerId; ?>Btn) {
        zoom<?php echo $layerId; ?>Btn.onclick = async function() {
            var layerName = <?php echo $layerId; ?>Layer.get("name");
            var proxyUrl = "<?php echo $proxyUrl; ?>";
            var url = proxyUrl + "?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetCapabilities&LAYERS=" + layerName;
            
            try {
                const response = await fetch(url);
                const text = await response.text();
                const parser = new DOMParser();
                const xmlDoc = parser.parseFromString(text, "text/xml");
                const layerInfo = xmlDoc.getElementsByTagName("Layer");
                
                for (let i = 0; i < layerInfo.length; i++) {
                    const name = layerInfo[i].getElementsByTagName("Name")[0];
                    if (name && name.textContent === layerName) {
                        const bboxes = layerInfo[i].getElementsByTagName("BoundingBox");
                        let bbox = null;
                        let sourceSRS = null;

                        // First try to find EPSG:4326 bbox
                        for (let j = 0; j < bboxes.length; j++) {
                            if (bboxes[j].getAttribute("SRS") === "EPSG:4326") {
                                bbox = bboxes[j];
                                sourceSRS = "EPSG:4326";
                                break;
                            }
                        }

                        // If no EPSG:4326 found, use the first available bbox
                        if (!bbox && bboxes.length > 0) {
                            bbox = bboxes[0];
                            sourceSRS = bbox.getAttribute("SRS");
                            
                            // Ensure the projection is registered
                            try {
                                await ensureProjectionRegistered(sourceSRS);
                            } catch (error) {
                                console.error("Error registering projection:", error);
                                return;
                            }
                        }

                        if (bbox) {
                            const extent = [
                                parseFloat(bbox.getAttribute("minx")),
                                parseFloat(bbox.getAttribute("miny")),
                                parseFloat(bbox.getAttribute("maxx")),
                                parseFloat(bbox.getAttribute("maxy"))
                            ];

                            // Add padding to the extent
                            const width = extent[2] - extent[0];
                            const height = extent[3] - extent[1];
                            extent[0] -= width * 0.1;
                            extent[1] -= height * 0.1;
                            extent[2] += width * 0.1;
                            extent[3] += height * 0.1;

                            try {
                                let transformedExtent;
                                if (sourceSRS === "EPSG:4326") {
                                    transformedExtent = ol.proj.transformExtent(extent, "EPSG:4326", "EPSG:3857");
                                } else {
                                    const wgs84Extent = ol.proj.transformExtent(extent, sourceSRS, "EPSG:4326");
                                    transformedExtent = ol.proj.transformExtent(wgs84Extent, "EPSG:4326", "EPSG:3857");
                                }
                                
                                if (isNaN(transformedExtent[0]) || isNaN(transformedExtent[1]) || 
                                    isNaN(transformedExtent[2]) || isNaN(transformedExtent[3])) {
                                    throw new Error("Invalid transformed extent");
                                }

                                map.getView().fit(transformedExtent, {
                                    padding: [50, 50, 50, 50],
                                    duration: 1000,
                                    maxZoom: 15
                                });
                            } catch (error) {
                                console.error("Error transforming extent:", error);
                                const defaultExtent = [-20037508.34, -20037508.34, 20037508.34, 20037508.34];
                                map.getView().fit(defaultExtent, {
                                    padding: [50, 50, 50, 50],
                                    duration: 1000,
                                    maxZoom: 15
                                });
                            }
                        } else {
                            console.error("No bounding box found for layer");
                        }
                        break;
                    }
                }
            } catch (error) {
                console.error("Error fetching layer extent:", error);
            }
        };
    }
    <?php
    return ob_get_clean();
}

function generateMapTemplate($mapId, $basemaps, $layers, $features, $initialExtent = null, $mapMetadata = null, $filters = null) {
    $config = getGeoServerConfig();
    
    // Calculate proxy URL - use relative path to ensure it works in iframes
    $proxyUrl = 'geoserver_proxy.php?map_id='.$mapId;
    $firstBasemap = true;
    
    // Start output buffering for the entire template
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Custom Map</title>
        <script src="https://cdn.jsdelivr.net/npm/proj4@2.8.0/dist/proj4.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/ol@latest/dist/ol.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@latest/ol.css" />
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap Icons (for eye and 3-dot icons) -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            html, body { margin: 0; padding: 0; height: 100%; }
            #map { width: 100vw; height: 100vh; }
            .offcanvas-start { width: 320px !important; }
            .layer-eye { cursor: pointer; margin-right: 8px; }
            .layer-row { display: flex; align-items: center; justify-content: space-between; }
            .dropdown-toggle::after { display: none; }
            
            /* Card Styles for Sidebar */
            .map-info-card, .basemap-card {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 16px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                margin-bottom: 16px;
            }
            
            .map-info-card h6 {
                font-size: 1.1rem;
                font-weight: 600;
                color: #212529;
                margin: 0;
            }
            
            .map-info-card p {
                font-size: 0.875rem;
                color: #6c757d;
                margin: 0;
                line-height: 1.4;
            }
            
            .basemap-card label {
                font-size: 0.875rem;
                font-weight: 500;
                color: #212529;
            }
            
            .basemap-card .form-select {
                border: 1px solid #ced4da;
                border-radius: 6px;
                font-size: 0.875rem;
                padding: 8px 12px;
            }
            
            .basemap-card .form-select:focus {
                border-color: #86b7fe;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            }
            .ol-popup {
                position: absolute;
                background-color: white;
                border: 1px solid #e5e7eb;
                max-width: 450px;
                min-width: 380px;
                max-height: 500px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-radius: 8px;
                z-index: 1001;
                font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
                overflow: hidden;
            }
            
            /* Popup Header Navigation */
            .popup-header {
                background-color: #f8f9fa;
                padding: 8px 12px;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 13px;
                color: #6b7280;
            }
            
            .popup-nav {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .popup-nav-btn {
                background: none;
                border: none;
                color: #6b7280;
                cursor: pointer;
                padding: 2px;
                border-radius: 3px;
                font-size: 12px;
            }
            
            .popup-nav-btn:hover {
                background-color: #e5e7eb;
                color: #374151;
            }
            
            .popup-close {
                background: none;
                border: none;
                color: #6b7280;
                cursor: pointer;
                padding: 2px;
                border-radius: 3px;
                font-size: 14px;
                font-weight: bold;
            }
            
            .popup-close:hover {
                background-color: #e5e7eb;
                color: #374151;
            }
            
            /* Popup Content */
            .popup-content {
                padding: 12px;
                max-height: 420px;
                overflow-y: auto;
            }
            
            .popup-layer {
                margin-bottom: 12px;
                font-size: 14px;
            }
            
            .popup-layer-label {
                color: #6b7280;
                font-weight: normal;
            }
            
            .popup-layer-name {
                font-weight: bold;
                color: #111827;
            }
            
            /* Action Buttons */
            .popup-actions {
                display: flex;
                gap: 12px;
                margin-bottom: 12px;
            }
            
            .popup-action-btn {
                display: flex;
                align-items: center;
                gap: 6px;
                background: none;
                border: none;
                color: #374151;
                cursor: pointer;
                padding: 6px 8px;
                border-radius: 4px;
                font-size: 13px;
                transition: background-color 0.2s;
            }
            
            .popup-action-btn:hover {
                background-color: #f3f4f6;
            }
            
            .popup-action-btn i {
                font-size: 14px;
            }
            
            /* Feature Attributes */
            .popup-attribute {
                margin-bottom: 8px;
                font-size: 13px;
                line-height: 1.4;
            }
            
            .popup-attribute.highlighted {
                background-color: #f8f9fa;
                padding: 6px 8px;
                border-radius: 4px;
                margin: 6px -8px;
            }
            
            .popup-attribute-label {
                font-weight: bold;
                color: #111827;
                margin-right: 4px;
            }
            
            .popup-attribute-value {
                color: #374151;
            }
            
            /* Hidden Properties Indicator */
            .popup-hidden-props {
                margin-top: 12px;
                padding-top: 8px;
                border-top: 1px solid #e5e7eb;
                font-size: 12px;
                color: #6b7280;
                text-align: center;
            }
            
            /* Custom Scrollbar */
            .popup-content::-webkit-scrollbar {
                width: 6px;
            }
            
            .popup-content::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 3px;
            }
            
            .popup-content::-webkit-scrollbar-thumb {
                background: #c1c1c1;
                border-radius: 3px;
            }
            
            .popup-content::-webkit-scrollbar-thumb:hover {
                background: #a8a8a8;
            }
            /* Remove Bootstrap offcanvas backdrop (gray overlay) */
            .offcanvas-backdrop { display: none !important; }
            /* Move OpenLayers zoom control to right side */
            .ol-zoom {
                right: 10px;
                left: auto;
                top: 10px;
            }
            /* Position for custom reset extent control */
            .ol-reset-extent {
                top: 70px;
                right: 10px;
                left: auto;
            }
            .ol-reset-extent button {
                background: rgba(255,255,255,0.85);
                border: 1px solid #e5e7eb;
                border-radius: 4px;
                color: #111827;
                width: 28px;
                height: 28px;
                line-height: 26px;
                font-size: 16px;
                padding: 0;
                cursor: pointer;
            }
            .ol-reset-extent button:hover { background: #ffffff; }
            /* Sidebar handle (thumb) styling */
            #sidebar-handle {
                position: fixed;
                top: 50%;
                left: 320px;
                transform: translateY(-50%);
                width: 24px;
                height: 64px;
                background: #f8f9fa;
                border-radius: 0 8px 8px 0;
                box-shadow: 1px 0 4px rgba(0,0,0,0.08);
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 1200;
                transition: left 0.3s;
                border: 1px solid #dee2e6;
                border-left: none;
            }
            #sidebar-handle.closed {
                left: 0;
                border-radius: 0 8px 8px 0;
            }
            #sidebar-handle i {
                font-size: 1.5rem;
                color: #888;
            }
            /* Hide handle when sidebar is open */
            .offcanvas.show ~ #sidebar-handle { display: none; }
        </style>
        <!-- Bootstrap 5 JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Common projection definitions
            const commonProjections = {
                "EPSG:26713": "+proj=utm +zone=13 +datum=NAD27 +units=m +no_defs",
                "EPSG:26714": "+proj=utm +zone=14 +datum=NAD27 +units=m +no_defs",
                "EPSG:26715": "+proj=utm +zone=15 +datum=NAD27 +units=m +no_defs",
                "EPSG:26716": "+proj=utm +zone=16 +datum=NAD27 +units=m +no_defs",
                "EPSG:26717": "+proj=utm +zone=17 +datum=NAD27 +units=m +no_defs",
                "EPSG:26718": "+proj=utm +zone=18 +datum=NAD27 +units=m +no_defs",
                "EPSG:26719": "+proj=utm +zone=19 +datum=NAD27 +units=m +no_defs",
                "EPSG:26720": "+proj=utm +zone=20 +datum=NAD27 +units=m +no_defs",
                "EPSG:26721": "+proj=utm +zone=21 +datum=NAD27 +units=m +no_defs",
                "EPSG:26722": "+proj=utm +zone=22 +datum=NAD27 +units=m +no_defs",
                "EPSG:32613": "+proj=utm +zone=13 +datum=WGS84 +units=m +no_defs",
                "EPSG:32614": "+proj=utm +zone=14 +datum=WGS84 +units=m +no_defs",
                "EPSG:32615": "+proj=utm +zone=15 +datum=WGS84 +units=m +no_defs",
                "EPSG:32616": "+proj=utm +zone=16 +datum=WGS84 +units=m +no_defs",
                "EPSG:32617": "+proj=utm +zone=17 +datum=WGS84 +units=m +no_defs",
                "EPSG:32618": "+proj=utm +zone=18 +datum=WGS84 +units=m +no_defs",
                "EPSG:32619": "+proj=utm +zone=19 +datum=WGS84 +units=m +no_defs",
                "EPSG:32620": "+proj=utm +zone=20 +datum=WGS84 +units=m +no_defs",
                "EPSG:32621": "+proj=utm +zone=21 +datum=WGS84 +units=m +no_defs",
                "EPSG:32622": "+proj=utm +zone=22 +datum=WGS84 +units=m +no_defs"
            };

            // Function to ensure a projection is registered
            async function ensureProjectionRegistered(srs) {
                // If projection is already registered, return it
                if (ol.proj.get(srs)) {
                    return ol.proj.get(srs);
                }

                // Check if we have a definition in our common projections
                if (commonProjections[srs]) {
                    proj4.defs(srs, commonProjections[srs]);
                    ol.proj.proj4.register(proj4);
                    return ol.proj.get(srs);
                }

                // If not found in common projections, try to fetch from GeoServer
                try {
                    // Use direct GeoServer REST API for projection definitions (REST API typically has different CORS settings)
                    const response = await fetch("<?php echo $config['geoserver_url']; ?>/rest/srs/" + srs);
                    if (!response.ok) {
                        throw new Error("Failed to fetch projection definition");
                    }
                    const data = await response.json();
                    if (data.wkt) {
                        proj4.defs(srs, data.wkt);
                        ol.proj.proj4.register(proj4);
                        return ol.proj.get(srs);
                    }
                } catch (error) {
                    console.error("Error fetching projection definition:", error);
                }

                throw new Error("Could not register projection: " + srs);
            }

            // Register common projections on page load
            Object.entries(commonProjections).forEach(([srs, def]) => {
                proj4.defs(srs, def);
            });
            ol.proj.proj4.register(proj4);
        </script>
    </head>
    <body>
        <div id="map"></div>
        <div id="popup" class="ol-popup" style="display:none;"></div>
        <!-- Controls Container (top right, vertical stack) -->
        <div id="map-controls-container" style="position: fixed; top: 80px; right: 20px; z-index: 2000; width: 340px; display: flex; flex-direction: column; gap: 10px;">
        </div>
        <!-- Sidebar handle (thumb) for opening/closing sidebar -->
        <div id="sidebar-handle" class="closed" style="display:none" title="Open sidebar"><i class="bi bi-chevron-double-right"></i></div>
        
        <!-- Table View Modal -->
        <div class="modal fade" id="tableModal" tabindex="-1" aria-labelledby="tableModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="tableModalLabel">Layer Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div id="tableViewContent">
                  <div class="text-center">
                    <div class="spinner-border" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Bootstrap Offcanvas Sidebar (must be direct child of body) -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel" style="z-index: 1100;">
          <div class="offcanvas-header">
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body p-3">
            <!-- Map Information Card -->
            <?php if ($mapMetadata): ?>
            <div class="map-info-card mb-3">
              <div class="d-flex align-items-start mb-2">
                <i class="bi bi-geo-alt-fill me-2 text-dark" style="font-size: 1.2rem;"></i>
                <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($mapMetadata['title']); ?></h6>
              </div>
              <p class="text-muted mb-1 small">Description: <?php echo htmlspecialchars($mapMetadata['description'] ?: 'No description provided'); ?></p>
              <p class="text-muted mb-0 small">Created: <?php echo date('n/j/Y', strtotime($mapMetadata['created_at'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Basemap Switcher Card -->
            <?php if (count($basemaps) > 1): ?>
            <div class="basemap-card mb-3">
              <div class="d-flex align-items-center mb-2">
                <i class="bi bi-globe me-2 text-dark"></i>
                <label for="basemap" class="form-label mb-0 text-dark">Basemap</label>
              </div>
              <select id="basemap" class="form-select form-select-sm">
                <?php foreach ($basemaps as $basemap): 
                  $name = str_replace(
                    ['osm', 'carto-light', 'carto-dark', 'carto-voyager', 'esri-satellite', 'esri-topo'],
                    ['OpenStreetMap', 'Carto Light', 'Carto Dark', 'Carto Voyager', 'ESRI Satellite', 'ESRI Topo'],
                    $basemap
                  );
                ?>
                <option value="<?php echo $basemap; ?>"><?php echo $name; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <!-- Layer List -->
            <div class="list-group list-group-flush">
              <?php foreach ($layers as $layer): 
                $layerId = str_replace([':', '.'], '_', $layer);
                $layerName = explode(':', $layer)[1] ?? $layer;
              ?>
              <div class="list-group-item layer-row" id="row-<?php echo $layerId; ?>">
                <span class="layer-eye" id="<?php echo $layerId; ?>-eye" data-layer="<?php echo $layerId; ?>">
                  <i class="bi bi-eye-fill" id="<?php echo $layerId; ?>-eye-icon"></i>
                </span>
                <span class="flex-grow-1"> <?php echo htmlspecialchars($layerName); ?> </span>
                <div class="dropdown">
                  <button class="btn btn-link p-0" type="button" id="dropdownMenu-<?php echo $layerId; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="dropdownMenu-<?php echo $layerId; ?>" style="min-width:220px;">
                    <li>
                      <label for="<?php echo $layerId; ?>-opacity" class="form-label mb-1"><i class="bi bi-circle-half"></i> Opacity</label>
                      <input type="range" class="form-range" id="<?php echo $layerId; ?>-opacity" min="0" max="1" step="0.01" value="1">
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <button class="dropdown-item" id="zoom-<?php echo $layerId; ?>"><i class="bi bi-search"></i> Zoom to layer</button>
                    </li>
                  </ul>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <script>
            var map, view, layers = [];
            var wmsLayers = [];
            function initMap() {
                try {
                    // Initialize map view with custom or default center and zoom
                    var initialCenter = <?php 
                        if ($initialExtent && $initialExtent['center_lon'] !== null && $initialExtent['center_lat'] !== null) {
                            echo json_encode([$initialExtent['center_lon'], $initialExtent['center_lat']]);
                        } else {
                            echo '[-95, 37]'; // Default center
                        }
                    ?>;
                    var initialZoom = <?php 
                        if ($initialExtent && $initialExtent['zoom_level'] !== null) {
                            echo $initialExtent['zoom_level'];
                        } else {
                            echo '4'; // Default zoom
                        }
                    ?>;

                    view = new ol.View({
                        center: ol.proj.fromLonLat(initialCenter),
                        zoom: initialZoom
                    });

                    // Default extents/state for reset control
                    var defaultCenter3857 = ol.proj.fromLonLat(initialCenter);
                    var defaultZoomLevel = initialZoom;
                    var defaultExtent3857 = null; // will be set from layer extent if available

                    // Add basemap layers
                    var firstBasemap = true;
                    var basemapLayers = [];
                    <?php foreach ($basemaps as $basemap): ?>
                    <?php if ($basemap === 'osm'): ?>
                    var osmLayer = new ol.layer.Tile({ 
                        source: new ol.source.OSM(),
                        visible: <?php echo $firstBasemap ? 'true' : 'false'; ?>,
                        name: "osm",
                        zIndex: 0
                    });
                    layers.push(osmLayer);
                    basemapLayers.push(osmLayer);
                    <?php elseif ($basemap === 'carto-light'): ?>
                    var cartoLightLayer = new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://{a-c}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png",
                            attributions: "© OpenStreetMap, © CARTO"
                        }),
                        visible: <?php echo $firstBasemap ? 'true' : 'false'; ?>,
                        name: "carto-light",
                        zIndex: 0
                    });
                    layers.push(cartoLightLayer);
                    basemapLayers.push(cartoLightLayer);
                    <?php elseif ($basemap === 'carto-dark'): ?>
                    var cartoDarkLayer = new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://{a-c}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png",
                            attributions: "© OpenStreetMap, © CARTO"
                        }),
                        visible: <?php echo $firstBasemap ? 'true' : 'false'; ?>,
                        name: "carto-dark",
                        zIndex: 0
                    });
                    layers.push(cartoDarkLayer);
                    basemapLayers.push(cartoDarkLayer);
                    <?php elseif ($basemap === 'carto-voyager'): ?>
                    var cartoVoyagerLayer = new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://{a-c}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png",
                            attributions: "© OpenStreetMap, © CARTO"
                        }),
                        visible: <?php echo $firstBasemap ? 'true' : 'false'; ?>,
                        name: "carto-voyager",
                        zIndex: 0
                    });
                    layers.push(cartoVoyagerLayer);
                    basemapLayers.push(cartoVoyagerLayer);
                    <?php elseif ($basemap === 'esri-satellite'): ?>
                    var esriSatelliteLayer = new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
                            attributions: "© Esri"
                        }),
                        visible: <?php echo $firstBasemap ? 'true' : 'false'; ?>,
                        name: "esri-satellite",
                        zIndex: 0
                    });
                    layers.push(esriSatelliteLayer);
                    basemapLayers.push(esriSatelliteLayer);
                    <?php elseif ($basemap === 'esri-topo'): ?>
                    var esriTopoLayer = new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}",
                            attributions: "© Esri"
                        }),
                        visible: <?php echo $firstBasemap ? 'true' : 'false'; ?>,
                        name: "esri-topo",
                        zIndex: 0
                    });
                    layers.push(esriTopoLayer);
                    basemapLayers.push(esriTopoLayer);
                    <?php endif; ?>
                    <?php $firstBasemap = false; ?>
                    <?php endforeach; ?>

                    // Add WMS layers
                    <?php foreach ($layers as $layer): 
                        $layerId = str_replace([':', '.'], '_', $layer);
                        
                        // Build CQL filter for this layer (supporting multiple conditions)
                        $cqlFilter = '';
                        if ($filters && isset($filters[$layer])) {
                            $conditions = $filters[$layer];
                            
                            // Support both old single filter and new array format
                            if (!is_array($conditions) || !isset($conditions[0])) {
                                $conditions = [$conditions];
                            }
                            
                            $filterParts = [];
                            foreach ($conditions as $idx => $filter) {
                                if (empty($filter['attribute']) || empty($filter['value'])) {
                                    continue;
                                }
                                
                                $attribute = $filter['attribute'];
                                $operator = $filter['operator'];
                                $value = $filter['value'];
                                
                                // Build CQL condition
                                $condition = '';
                                switch ($operator) {
                                    case '=':
                                    case '>':
                                    case '<':
                                    case '>=':
                                    case '<=':
                                    case '!=':
                                        $condition = "$attribute $operator '$value'";
                                        break;
                                    case 'LIKE':
                                        $condition = "$attribute LIKE '%$value%'";
                                        break;
                                }
                                
                                // Add logic operator for subsequent conditions
                                if ($idx > 0 && isset($filter['logic'])) {
                                    $logic = $filter['logic'];
                                    $filterParts[] = $logic;
                                }
                                $filterParts[] = $condition;
                            }
                            
                            if (!empty($filterParts)) {
                                $cqlFilter = implode(' ', $filterParts);
                            }
                        }
                    ?>
                    var <?php echo $layerId; ?>Layer = new ol.layer.Image({
                        source: new ol.source.ImageWMS({
                            url: "<?php echo $proxyUrl; ?>",
                            params: { 
                                "LAYERS": "<?php echo $layer; ?>", 
                                "TILED": true,
                                "FORMAT": "image/png",
                                "TRANSPARENT": true,
                                "SRS": "EPSG:3857",
                                "VERSION": "1.1.1"<?php echo $cqlFilter ? ",\n                                \"CQL_FILTER\": \"" . addslashes($cqlFilter) . "\"" : ""; ?>
                            },
                            ratio: 1,
                            serverType: "geoserver",
                            projection: "EPSG:3857"
                        }),
                        opacity: 1,
                        zIndex: 1,
                        name: "<?php echo $layer; ?>"
                    });
                    wmsLayers.push(<?php echo $layerId; ?>Layer);
                    layers.push(<?php echo $layerId; ?>Layer);
                    <?php endforeach; ?>

                    // Create map
                    map = new ol.Map({
                        target: "map",
                        layers: layers,
                        view: view
                    });

                    // Add custom "Zoom to Extent" control
                    (function(){
                        var button = document.createElement('button');
                        button.innerHTML = '⌂';
                        button.title = 'Zoom to extent';
                        button.onclick = function() {
                            if (defaultExtent3857 && Array.isArray(defaultExtent3857)) {
                                map.getView().fit(defaultExtent3857, { padding: [50,50,50,50], duration: 700, maxZoom: 15 });
                            } else if (defaultCenter3857 && typeof defaultZoomLevel !== 'undefined') {
                                map.getView().animate({ center: defaultCenter3857, zoom: defaultZoomLevel, duration: 600 });
                            } else {
                                var world = [-20037508.34, -20037508.34, 20037508.34, 20037508.34];
                                map.getView().fit(world, { padding: [50,50,50,50], duration: 700, maxZoom: 5 });
                            }
                        };
                        var element = document.createElement('div');
                        element.className = 'ol-reset-extent ol-control';
                        element.appendChild(button);
                        var ResetControl = new ol.control.Control({ element: element });
                        map.addControl(ResetControl);
                    })();


                    // Set initial extent based on the first visible WMS layer only if no custom extent is set
                    function setInitialExtent() {
                        <?php if ($initialExtent && $initialExtent['center_lon'] !== null && $initialExtent['center_lat'] !== null): ?>
                        // Custom extent is set, no need to fetch layer extent
                        <?php else: ?>
                        // No custom extent, use first visible layer's extent
                        var firstVisibleLayer = wmsLayers.find(function(layer) {
                            return layer.getVisible();
                        });

                        if (firstVisibleLayer) {
                            var layerName = firstVisibleLayer.get("name");
                            var proxyUrl = "<?php echo $proxyUrl; ?>";
                            var url = proxyUrl + "?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetCapabilities&LAYERS=" + layerName;
                            
                            fetch(url)
                                .then(function(response) { return response.text(); })
                                .then(function(text) {
                                    var parser = new DOMParser();
                                    var xmlDoc = parser.parseFromString(text, "text/xml");
                                    var layerInfo = xmlDoc.getElementsByTagName("Layer");
                                    
                                    for (var i = 0; i < layerInfo.length; i++) {
                                        var name = layerInfo[i].getElementsByTagName("Name")[0];
                                        if (name && name.textContent === layerName) {
                                            var bbox = layerInfo[i].getElementsByTagName("BoundingBox")[0];
                                            if (bbox) {
                                                var extent = [
                                                    parseFloat(bbox.getAttribute("minx")),
                                                    parseFloat(bbox.getAttribute("miny")),
                                                    parseFloat(bbox.getAttribute("maxx")),
                                                    parseFloat(bbox.getAttribute("maxy"))
                                                ];
                                                var width = extent[2] - extent[0];
                                                var height = extent[3] - extent[1];
                                                extent[0] -= width * 0.1;
                                                extent[1] -= height * 0.1;
                                                extent[2] += width * 0.1;
                                                extent[3] += height * 0.1;
                                                
                                                var transformedExtent = ol.proj.transformExtent(extent, "EPSG:4326", "EPSG:3857");
                                                defaultExtent3857 = transformedExtent; // save for reset button
                                                map.getView().fit(transformedExtent, {
                                                    padding: [50, 50, 50, 50],
                                                    duration: 1000,
                                                    maxZoom: 15
                                                });
                                            }
                                            break;
                                        }
                                    }
                                })
                                .catch(function(error) {
                                    console.error("Error setting initial extent:", error);
                                });
                        }
                        <?php endif; ?>
                    }

                    // Set initial extent after a short delay to ensure layers are loaded
                    <?php if (!$initialExtent || $initialExtent['center_lon'] === null || $initialExtent['center_lat'] === null): ?>
                    setTimeout(setInitialExtent, 500);
                    <?php endif; ?>

                    // Ensure proper initial visibility of basemaps
                    var firstBasemapFound = false;
                    layers.forEach(function(layer) {
                        if (layer instanceof ol.layer.Tile) {
                            if (!firstBasemapFound) {
                                layer.setVisible(true);
                                firstBasemapFound = true;
                            } else {
                                layer.setVisible(false);
                            }
                        }
                    });

                    // Add basemap switching functionality
                    var basemapSelect = document.getElementById("basemap");
                    if (basemapSelect) {
                        basemapSelect.onchange = function(e) {
                            var selectedBasemap = e.target.value;
                            
                            // Hide all basemap layers first
                            layers.forEach(function(layer) {
                                if (layer instanceof ol.layer.Tile) {
                                    layer.setVisible(false);
                                }
                            });
                            
                            // Show the selected basemap
                            layers.forEach(function(layer) {
                                if (layer instanceof ol.layer.Tile && layer.get("name") === selectedBasemap) {
                                    layer.setVisible(true);
                                }
                            });
                        };
                    }

                    // Layer visibility toggle (eye icon)
                    <?php foreach ($layers as $layer): 
                        $layerId = str_replace([':', '.'], '_', $layer);
                    ?>
                    var eyeIcon<?php echo $layerId; ?> = document.getElementById("<?php echo $layerId; ?>-eye");
                    var eyeIconElem<?php echo $layerId; ?> = document.getElementById("<?php echo $layerId; ?>-eye-icon");
                    if (eyeIcon<?php echo $layerId; ?>) {
                      eyeIcon<?php echo $layerId; ?>.onclick = function() {
                        var lyr = <?php echo $layerId; ?>Layer;
                        var vis = lyr.getVisible();
                        lyr.setVisible(!vis);
                        if (lyr.getVisible()) {
                          eyeIconElem<?php echo $layerId; ?>.classList.remove('bi-eye-slash-fill');
                          eyeIconElem<?php echo $layerId; ?>.classList.add('bi-eye-fill');
                        } else {
                          eyeIconElem<?php echo $layerId; ?>.classList.remove('bi-eye-fill');
                          eyeIconElem<?php echo $layerId; ?>.classList.add('bi-eye-slash-fill');
                        }
                      };
                    }
                    <?php endforeach; ?>

                    // Opacity controls
                    <?php foreach ($layers as $layer): 
                        $layerId = str_replace([':', '.'], '_', $layer);
                    ?>
                    var opacitySlider<?php echo $layerId; ?> = document.getElementById("<?php echo $layerId; ?>-opacity");
                    if (opacitySlider<?php echo $layerId; ?>) {
                      opacitySlider<?php echo $layerId; ?>.oninput = function(e) {
                        <?php echo $layerId; ?>Layer.setOpacity(parseFloat(e.target.value));
                      };
                    }
                    <?php endforeach; ?>

                    // Zoom to layer buttons
                    <?php foreach ($layers as $layer): 
                        $layerId = str_replace([':', '.'], '_', $layer);
                        echo generateZoomButtonJavaScript($layerId, $layer, $config['geoserver_url'], $proxyUrl);
                    endforeach; ?>

                    // Add popup functionality (always include)
                    <?php echo generatePopupJavaScript($layers, $config, $proxyUrl); ?>

                } catch (error) {
                    console.error("Error initializing map:", error);
                }
            }

            // Initialize map when DOM is ready
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", function() {
                    initMap();
                });
            } else {
                initMap();
            }

            // Sidebar handle open/close logic
            document.addEventListener('DOMContentLoaded', function() {
                var sidebar = document.getElementById('sidebar');
                var handle = document.getElementById('sidebar-handle');
                var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(sidebar);
                // Open sidebar on page load
                offcanvas.show();
                // Open sidebar when handle is clicked
                handle.addEventListener('click', function() {
                    offcanvas.show();
                });
                // Hide handle when sidebar is open, show when closed
                sidebar.addEventListener('show.bs.offcanvas', function() {
                    handle.style.display = 'none';
                });
                sidebar.addEventListener('hidden.bs.offcanvas', function() {
                    handle.style.display = 'flex';
                });
            });
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function generatePopupJavaScript($layers, $config, $proxyUrl) {
    return '
            var popup = document.getElementById("popup");
            if (popup) {
                var overlay = new ol.Overlay({
                    element: popup,
                    positioning: "bottom-center",
                    stopEvent: true,
                    offset: [0, -15]
                });
                map.addOverlay(overlay);

                map.on("singleclick", function(evt) {
                    popup.style.display = "none";
                    overlay.setPosition(undefined);

                    var viewResolution = view.getResolution();
                    var coordinate = evt.coordinate;
                    var urlLayers = [];
                    
                    // Get visible WMS layers
                    layers.forEach(function(layer) {
                        if (layer instanceof ol.layer.Image && layer.getVisible()) {
                            urlLayers.push(layer.get("name"));
                        }
                    });

                    if (urlLayers.length === 0) return;

                    var proxyUrl = "' . $proxyUrl . '";
                    var url = proxyUrl + "?SERVICE=WMS&VERSION=1.1.1&REQUEST=GetFeatureInfo" +
                        "&FORMAT=image/png&TRANSPARENT=true" +
                        "&QUERY_LAYERS=" + urlLayers.join(",") +
                        "&LAYERS=" + urlLayers.join(",") +
                        "&INFO_FORMAT=application/json" +
                        "&FEATURE_COUNT=5" +
                        "&SRS=EPSG:3857" +
                        "&WIDTH=" + map.getSize()[0] +
                        "&HEIGHT=" + map.getSize()[1] +
                        "&BBOX=" + map.getView().calculateExtent(map.getSize()).join(",") +
                        "&X=" + Math.floor(evt.pixel[0]) + "&Y=" + Math.floor(evt.pixel[1]);

                    fetch(url)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.features && data.features.length > 0) {
                                var currentFeatureIndex = 0;
                                var totalFeatures = data.features.length;
                                
                                function generatePopupHTML(featureIndex) {
                                    var feature = data.features[featureIndex];
                                    var props = feature.properties;
                                    // Derive correct layer name from feature.id
                                    var layerName = "Unknown Layer";
                                    if (feature && feature.id) {
                                        // GeoServer usually returns "workspace:layername.123"
                                        var idStr = String(feature.id);
                                        if (idStr.indexOf(".") > -1) idStr = idStr.split(".")[0];
                                        if (idStr.indexOf(":") > -1) idStr = idStr.split(":").pop();
                                        layerName = idStr;
                                    } else if (props && (props._layer || props.layer || props.layer_name)) {
                                        layerName = props._layer || props.layer || props.layer_name;
                                    } else if (urlLayers.length > 0) {
                                        layerName = urlLayers[0];
                                    }
                                    
                                    var html = "<div class=\"popup-header\">";
                                    html += "<div class=\"popup-nav\">";
                                    html += "<button class=\"popup-nav-btn\" onclick=\"navigateFeature(" + (featureIndex - 1) + ")\" " + (featureIndex === 0 ? "disabled" : "") + ">&lt;</button>";
                                    html += "<span>" + (featureIndex + 1) + " of " + totalFeatures + "</span>";
                                    html += "<button class=\"popup-nav-btn\" onclick=\"navigateFeature(" + (featureIndex + 1) + ")\" " + (featureIndex === totalFeatures - 1 ? "disabled" : "") + ">&gt;</button>";
                                    html += "</div>";
                                    html += "<button class=\"popup-close\" onclick=\"closePopup()\">&times;</button>";
                                    html += "</div>";
                                    
                                    html += "<div class=\"popup-content\">";
                                    
                                    // Layer information
                                    html += "<div class=\"popup-layer\">";
                                    html += "<span class=\"popup-layer-label\">Layer:</span> ";
                                    html += "<span class=\"popup-layer-name\">" + layerName + "</span>";
                                    html += "</div>";
                                    
                                    // Action buttons
                                    html += "<div class=\"popup-actions\">";
                                    html += "<button class=\"popup-action-btn\" onclick=\"showTable()\">";
                                    html += "<i class=\"bi bi-table\"></i> Table";
                                    html += "</button>";
                                    html += "<button class=\"popup-action-btn\" onclick=\"zoomToFeature()\">";
                                    html += "<i class=\"bi bi-zoom-in\"></i> Zoom to";
                                    html += "</button>";
                                    html += "</div>";
                                    
                                    // Feature attributes - show all properties
                                    var propKeys = Object.keys(props);
                                    
                                    propKeys.forEach(function(key, index) {
                                        // Highlight important attributes like Name, Address, etc.
                                        var importantKeys = ["name", "address", "title", "description", "type", "category"];
                                        var isHighlighted = importantKeys.some(function(importantKey) {
                                            return key.toLowerCase().includes(importantKey);
                                        });
                                        
                                        html += "<div class=\"popup-attribute" + (isHighlighted ? " highlighted" : "") + "\">";
                                        html += "<span class=\"popup-attribute-label\">" + key + ":</span> ";
                                        html += "<span class=\"popup-attribute-value\">" + (props[key] || "N/A") + "</span>";
                                        html += "</div>";
                                    });
                                    
                                    html += "</div>";
                                    
                                    return html;
                                }
                                
                                // Generate initial popup
                                popup.innerHTML = generatePopupHTML(currentFeatureIndex);
                                popup.style.display = "block";
                                overlay.setPosition(coordinate);
                                
                                // Add navigation functions to window scope
                                window.navigateFeature = function(newIndex) {
                                    if (newIndex >= 0 && newIndex < totalFeatures) {
                                        currentFeatureIndex = newIndex;
                                        popup.innerHTML = generatePopupHTML(currentFeatureIndex);
                                    }
                                };
                                
                                window.closePopup = function() {
                                    popup.style.display = "none";
                                    overlay.setPosition(undefined);
                                };
                                
                                window.showTable = function() {
                                    var f = data.features[currentFeatureIndex] || {};
                                    var props = (f && f.properties) ? f.properties : {};
                                    // ---- Resolve correct layer name from this feature ----
                                    var layerName = "Unknown";
                                    if (f && f.id) {
                                        var idStr = String(f.id);
                                        if (idStr.indexOf(".") > -1) idStr = idStr.split(".")[0];     // strip ".123"
                                        if (idStr.indexOf(":") > -1) idStr = idStr.split(":").pop();  // strip "workspace:"
                                        layerName = idStr || layerName;
                                    } else if (props && (props._layer || props.layer || props.layer_name)) {
                                        layerName = props._layer || props.layer || props.layer_name;
                                    } else if (urlLayers && urlLayers.length > 0) {
                                        layerName = urlLayers[0];
                                    }

                                    // ---- Try to detect a primary key candidate on the clicked feature ----
                                    var pkCandidates = ["id","fid","gid","objectid","feature_id","pk","ogc_fid"];
                                    var pkcol = null;
                                    for (var i = 0; i < pkCandidates.length; i++) {
                                        var k = pkCandidates[i];
                                        if (Object.prototype.hasOwnProperty.call(props, k)) { pkcol = k; break; }
                                    }
                                    // Fallback: first property that ends with "_id"
                                    if (!pkcol) {
                                        Object.keys(props || {}).some(function(k){
                                            if (/_id$/i.test(k)) { pkcol = k; return true; }
                                            return false;
                                        });
                                    }
                                    var pkval = pkcol ? props[pkcol] : null;
                                    var q = "layer=" + encodeURIComponent(layerName);
                                    if (pkcol && pkval !== undefined && pkval !== null) {
                                        q += "&pkcol=" + encodeURIComponent(pkcol) + "&pk=" + encodeURIComponent(pkval);
                                    }
                                    var tableUrl = "view_table.php?" + q;
                                    
                                    // Open modal and load content
                                    var modal = new bootstrap.Modal(document.getElementById(\'tableModal\'));
                                    var modalContent = document.getElementById(\'tableViewContent\');
                                    // Update modal title if present
                                    try {
                                        var titleEl = document.querySelector(\'#tableModal .modal-title, #tableModalLabel\');
                                        if (titleEl) { titleEl.textContent = \'Layer Table — \' + layerName; }
                                    } catch(e) {}
                                    
                                    // Show loading spinner
                                    modalContent.innerHTML = \'<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>\';
                                    modal.show();
                                    
                                    // Load table content via fetch
                                    fetch(tableUrl)
                                        .then(function(response) { return response.text(); })
                                        .then(function(html) {
                                            // Extract just the body content from the response
                                            var parser = new DOMParser();
                                            var doc = parser.parseFromString(html, \'text/html\');
                                            var bodyContent = doc.body.innerHTML;
                                            modalContent.innerHTML = bodyContent;
                                        })
                                        .catch(function(error) {
                                            modalContent.innerHTML = \'<div class="alert alert-danger">Error loading table: \' + error.message + \'</div>\';
                                        });
                                };
                                
                                window.zoomToFeature = function() {
                                    var f = data.features[currentFeatureIndex];
                                    if (!f || !f.geometry) {
                                        console.warn("No geometry found for feature.", f);
                                        return;
                                    }
                                    try {
                                        // Use ol/format/GeoJSON to convert to ol.Feature
                                        var geojsonFormat = new ol.format.GeoJSON();
                                        var olFeature = geojsonFormat.readFeature(f, {
                                            dataProjection: "EPSG:4326", // Default from GeoServer JSON
                                            featureProjection: map.getView().getProjection()
                                        });

                                        var geom = olFeature.getGeometry();
                                        if (!geom) {
                                            console.warn("No geometry parsed from feature.", f);
                                            return;
                                        }

                                        var extent = geom.getExtent();

                                        // Validate extent; handle cases where geometry extent is empty/invalid
                                        var invalid = (
                                            !extent || extent.length !== 4 ||
                                            !isFinite(extent[0]) || !isFinite(extent[1]) ||
                                            !isFinite(extent[2]) || !isFinite(extent[3]) ||
                                            extent[0] === Infinity || extent[2] === -Infinity
                                        );

                                        if (invalid) {
                                            // Try to derive an extent from the raw GeoJSON point or the click coordinate
                                            var buffer = 5000;
                                            if (f && f.geometry && f.geometry.type === "Point" && Array.isArray(f.geometry.coordinates)) {
                                                var p = ol.proj.transform(f.geometry.coordinates, "EPSG:4326", map.getView().getProjection());
                                                extent = [p[0] - buffer, p[1] - buffer, p[0] + buffer, p[1] + buffer];
                                            } else if (typeof coordinate !== "undefined" && coordinate && coordinate.length === 2) {
                                                extent = [coordinate[0] - buffer, coordinate[1] - buffer, coordinate[0] + buffer, coordinate[1] + buffer];
                                            } else {
                                                console.warn("Empty extent and no fallback available for zoom.", f);
                                                return;
                                            }
                                        }

                                        // For Points, pad extent a bit so it is not zoomed too far in
                                        var isPoint = geom.getType() === "Point";
                                        if (isPoint) {
                                            var buffer = 5000; // meters; adjust for your data context
                                            extent = [
                                                extent[0] - buffer,
                                                extent[1] - buffer,
                                                extent[2] + buffer,
                                                extent[3] + buffer
                                            ];
                                        }

                                        map.getView().fit(extent, {
                                            duration: 1000,
                                            padding: [50, 50, 50, 50],
                                            maxZoom: 15
                                        });
                                    } catch (err) {
                                        console.error("Could not zoom to feature:", err, f);
                                    }
                                };
                            }
                        })
                        .catch(function(error) {
                            console.error("Error fetching feature info:", error);
                        });
                });
            }';
}
?>
