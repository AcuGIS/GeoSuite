<?php
// Optional auth (match your app):
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Database.php';
// Use GeoServer config
require_once 'incl/Config.php';

requireAuth();

// --- Inputs ---
$layerRaw = $_GET['layer'] ?? '';
$pkcolRaw = $_GET['pkcol'] ?? '';
$pkRaw    = $_GET['pk'] ?? null;
// when present and truthy, returns all rows (no LIMIT)
$allRaw   = $_GET['all'] ?? null;
$allRows  = filter_var($allRaw, FILTER_VALIDATE_BOOLEAN) || $allRaw === '1';

if (!$layerRaw) {
  http_response_code(400);
  die('Invalid or missing layer name.');
}

$config = getGeoServerConfig();

/**
 * Fetch features from GeoServer via WFS
 */
function fetchFeaturesFromGeoServer($layerName, $pkcol = null, $pkval = null, $maxFeatures = null) {
  global $config;
  
  $baseUrl = $config['geoserver_url'];
  
  // Build WFS GetFeature request
  $params = [
    'service' => 'WFS',
    'version' => '1.1.0',
    'request' => 'GetFeature',
    'typeName' => $layerName,
    'outputFormat' => 'application/json',
    'srsName' => 'EPSG:4326'
  ];
  
  // Add CQL filter if primary key is specified
  if ($pkcol && $pkval !== null && $pkval !== '') {
    $params['CQL_FILTER'] = "$pkcol='$pkval'";
  }
  
  // Add max features limit
  if ($maxFeatures !== null) {
    $params['maxFeatures'] = $maxFeatures;
  }
  
  $url = $baseUrl . '/wfs?' . http_build_query($params);
  
  // Create context with authentication
  $context = stream_context_create([
    'http' => [
      'header' => "Authorization: Basic " . base64_encode($config['geoserver_username'] . ":" . $config['geoserver_password']),
      'ignore_errors' => true
    ]
  ]);
  
  $response = @file_get_contents($url, false, $context);
  
  if ($response === false) {
    throw new Exception("Failed to fetch features from GeoServer");
  }
  
  $data = json_decode($response, true);
  
  if (!isset($data['features'])) {
    throw new Exception("Invalid response from GeoServer WFS");
  }
  
  return $data['features'];
}

// --- Fetch data from GeoServer ---
try {
  $maxFeatures = $allRows ? null : 100;
  
  // Apply filter only if not showing all rows
  $filterPkcol = ($pkcol && !$allRows) ? $pkcol : null;
  $filterPkval = ($pkRaw !== null && $pkRaw !== '' && !$allRows) ? $pkRaw : null;
  
  $features = fetchFeaturesFromGeoServer($layerRaw, $filterPkcol, $filterPkval, $maxFeatures);
  
  // Extract properties into rows array
  $rows = [];
  foreach ($features as $feature) {
    if (isset($feature['properties'])) {
      $rows[] = $feature['properties'];
    }
  }
  
  // Collect columns
  $cols = [];
  if (!empty($rows)) {
    $cols = array_keys($rows[0]);
  }
  
} catch (Throwable $e) {
  http_response_code(500);
  die('Failed to fetch data from GeoServer: ' . htmlspecialchars($e->getMessage()));
}

// --- CSV export ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  try {
    // Fetch fresh data for CSV export
    $csvMaxFeatures = $allRows ? null : 100;
    $csvFilterPkcol = ($pkcol && !$allRows) ? $pkcol : null;
    $csvFilterPkval = ($pkRaw !== null && $pkRaw !== '' && !$allRows) ? $pkRaw : null;
    
    $csvFeatures = fetchFeaturesFromGeoServer($layerRaw, $csvFilterPkcol, $csvFilterPkval, $csvMaxFeatures);
    
    $csvRows = [];
    foreach ($csvFeatures as $feature) {
      if (isset($feature['properties'])) {
        $csvRows[] = $feature['properties'];
      }
    }
    
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $layerRaw);
    $filename .= $allRows ? "_all" : "_view";
    $filename .= "_" . date('Ymd_His') . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $out = fopen('php://output', 'w');
    
    if (!empty($csvRows)) {
      // Emit header row
      fputcsv($out, array_keys($csvRows[0]));
      
      // Emit data rows
      foreach ($csvRows as $row) {
        fputcsv($out, array_map(function($v){
          if (is_bool($v)) return $v ? 'true' : 'false';
          if (is_scalar($v) || $v === null) return $v;
          return json_encode($v);
        }, array_values($row)));
      }
    }
    
    fclose($out);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    die('CSV export failed: ' . htmlspecialchars($e->getMessage()));
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Layer Table – <?php echo htmlspecialchars($layerRaw); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 0; margin: 0; }
    .meta { color:#6b7280; font-size: .9rem; }
    .table-wrap { overflow:auto; max-height: 60vh; }
    .badge-soft { background:#eef2ff; color:#3730a3; padding: 4px 8px; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
    <h4 class="mb-0">Layer: <span class="badge badge-soft"><?php echo htmlspecialchars($layerRaw); ?></span></h4>
    <div class="d-flex align-items-center gap-2">
      <?php
        // Build base query string
        $qsBase = http_build_query(['layer' => $layerRaw]);
        $qsFiltered = http_build_query(array_filter([
          'layer' => $layerRaw,
          'pkcol' => $pkcol ?: null,
          'pk'    => ($pkRaw !== null && $pkRaw !== '') ? $pkRaw : null
        ]));
        $qsAll = http_build_query(['layer' => $layerRaw, 'all' => 1]);
      ?>
      <?php if ($pkcol && $pkRaw !== null && $pkRaw !== ''): ?>
        <a class="btn btn-sm btn-outline-primary" href="?<?php echo $qsAll; ?>">View All Rows</a>
      <?php endif; ?>
      <!-- Export current view (respects filter unless all=1 present) -->
      <a class="btn btn-sm btn-outline-success"
         href="?<?php echo $qsFiltered ?: $qsBase; ?>&export=csv">Export CSV (this view)</a>
      <!-- Export ALL rows (ignores pk filter) -->
      <a class="btn btn-sm btn-success"
         href="?<?php echo $qsAll; ?>&export=csv">Export CSV (all rows)</a>
    </div>
  </div>
  <p class="meta mt-2 mb-3">
    <?php if ($pkcol && $pkRaw !== null && $pkRaw !== ''): ?>
      <?php if ($allRows): ?>
        Showing <strong>all rows</strong> (up to GeoServer limit)
      <?php else: ?>
        Filtered by <strong><?php echo htmlspecialchars($pkcol); ?></strong> = <strong><?php echo htmlspecialchars((string)$pkRaw); ?></strong>
      <?php endif; ?>
    <?php else: ?>
      <?php if ($allRows): ?>
        Showing <strong>all rows</strong> (up to GeoServer limit)
      <?php else: ?>
        Showing up to <strong>100</strong> rows
      <?php endif; ?>
    <?php endif; ?>
    <br>
    <small class="text-muted">Displaying <?php echo count($rows); ?> record(s) from GeoServer layer: <?php echo htmlspecialchars($layerRaw); ?></small>
  </p>

  <div class="table-wrap">
    <table class="table table-striped table-sm align-middle">
      <thead>
        <tr>
          <?php foreach ($cols as $c): ?>
            <th scope="col"><?php echo htmlspecialchars($c); ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="<?php echo max(1, count($cols)); ?>" class="text-muted">No rows.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <?php foreach ($cols as $c): ?>
                <td><?php
                  $v = $r[$c] ?? '';
                  if (is_bool($v)) { echo $v ? 'true' : 'false'; }
                  else { echo htmlspecialchars((string)$v); }
                ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
