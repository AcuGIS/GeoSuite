<?php
// Start output buffering to prevent header issues
ob_start();

// Include required files
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Database.php';

// Require authentication
requireAuth();

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
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="map_export.pdf"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Get map parameters
    $centerLon = isset($_GET['center_lon']) ? floatval($_GET['center_lon']) : -95;
    $centerLat = isset($_GET['center_lat']) ? floatval($_GET['center_lat']) : 37;
    $zoom = isset($_GET['zoom']) ? intval($_GET['zoom']) : 4;
    $width = isset($_GET['width']) ? intval($_GET['width']) : 1200;
    $height = isset($_GET['height']) ? intval($_GET['height']) : 800;
    
    // Parse map configuration
    $mapConfig = json_decode($map['config'], true);
    $layers = $mapConfig['layers'] ?? [];
    $basemaps = $mapConfig['basemaps'] ?? ['osm'];
    
    // Create a simple HTML page for PDF generation
    $html = generateMapForPDF($mapId, $basemaps, $layers, $centerLon, $centerLat, $zoom, $width, $height);
    
    // Use a headless browser approach or create a simple image-based PDF
    // For now, let's create a simple PDF with map information
    createSimplePDF($map, $centerLon, $centerLat, $zoom, $layers);
    
} catch (Exception $e) {
    ob_end_clean();
    die('Error generating PDF: ' . htmlspecialchars($e->getMessage()));
}

function generateMapForPDF($mapId, $basemaps, $layers, $centerLon, $centerLat, $zoom, $width, $height) {
    $config = getGeoServerConfig();
    $proxyUrl = 'geoserver_proxy.php?map_id='$mapId;
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <script src="https://cdn.jsdelivr.net/npm/ol@latest/dist/ol.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@latest/ol.css" />
        <style>
            body { margin: 0; padding: 0; }
            #map { width: <?php echo $width; ?>px; height: <?php echo $height; ?>px; }
        </style>
    </head>
    <body>
        <div id="map"></div>
        <script>
            var map = new ol.Map({
                target: 'map',
                layers: [
                    <?php foreach ($basemaps as $basemap): ?>
                    <?php if ($basemap === 'osm'): ?>
                    new ol.layer.Tile({
                        source: new ol.source.OSM()
                    }),
                    <?php elseif ($basemap === 'carto-light'): ?>
                    new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://{a-c}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png",
                            attributions: "© OpenStreetMap, © CARTO"
                        })
                    }),
                    <?php elseif ($basemap === 'carto-dark'): ?>
                    new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://{a-c}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png",
                            attributions: "© OpenStreetMap, © CARTO"
                        })
                    }),
                    <?php elseif ($basemap === 'carto-voyager'): ?>
                    new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://{a-c}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png",
                            attributions: "© OpenStreetMap, © CARTO"
                        })
                    }),
                    <?php elseif ($basemap === 'esri-satellite'): ?>
                    new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
                            attributions: "© Esri"
                        })
                    }),
                    <?php elseif ($basemap === 'esri-topo'): ?>
                    new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}",
                            attributions: "© Esri"
                        })
                    }),
                    <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php foreach ($layers as $layer): ?>
                    new ol.layer.Image({
                        source: new ol.source.ImageWMS({
                            url: "<?php echo $proxyUrl; ?>",
                            params: { 
                                "LAYERS": "<?php echo $layer; ?>", 
                                "TILED": true,
                                "FORMAT": "image/png",
                                "TRANSPARENT": true,
                                "SRS": "EPSG:3857",
                                "VERSION": "1.1.1"
                            }
                        })
                    }),
                    <?php endforeach; ?>
                ],
                view: new ol.View({
                    center: ol.proj.fromLonLat([<?php echo $centerLon; ?>, <?php echo $centerLat; ?>]),
                    zoom: <?php echo $zoom; ?>
                })
            });
            
            // Wait for map to load then trigger PDF generation
            map.once('rendercomplete', function() {
                // Signal that map is ready
                document.body.setAttribute('data-ready', 'true');
            });
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function createSimplePDF($map, $centerLon, $centerLat, $zoom, $layers) {
    // For now, create a simple text-based PDF with map information
    // This is a fallback solution that works without CORS issues
    
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('GeoLite');
    $pdf->SetAuthor('GeoLite System');
    $pdf->SetTitle('Map Export: ' . $map['title']);
    $pdf->SetSubject('Map Export');
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Map Export: ' . $map['title'], 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Ln(5);
    
    // Map information
    $pdf->Cell(0, 8, 'Map Information:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Center: ' . $centerLon . ', ' . $centerLat, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Zoom Level: ' . $zoom, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Export Date: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
    
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Layers:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    foreach ($layers as $layer) {
        $layerName = explode(':', $layer)[1] ?? $layer;
        $pdf->Cell(0, 6, '• ' . $layerName, 0, 1, 'L');
    }
    
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Note: This is a text-based export. For visual map export,', 0, 1, 'C');
    $pdf->Cell(0, 6, 'please use the browser\'s print function or take a screenshot.', 0, 1, 'C');
    
    // Output PDF
    $pdf->Output('map_export.pdf', 'D');
}

// Check if TCPDF is available, if not, create a simple HTML response
if (!class_exists('TCPDF')) {
    // Fallback: create a simple HTML page that can be printed
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Map Export - <?php echo htmlspecialchars($map['title']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .info { margin-bottom: 20px; }
            .layers { margin-top: 20px; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo htmlspecialchars($map['title']); ?></h1>
            <p>Map Export - <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <div class="info">
            <h3>Map Information</h3>
            <p><strong>Center:</strong> <?php echo $centerLon; ?>, <?php echo $centerLat; ?></p>
            <p><strong>Zoom Level:</strong> <?php echo $zoom; ?></p>
            <p><strong>Export Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <div class="layers">
            <h3>Layers</h3>
            <ul>
                <?php foreach ($layers as $layer): ?>
                <?php $layerName = explode(':', $layer)[1] ?? $layer; ?>
                <li><?php echo htmlspecialchars($layerName); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="no-print">
            <p><em>Note: This is a text-based export. Use your browser's print function (Ctrl+P) to create a PDF.</em></p>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    // Set headers for HTML response
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
}
?>
