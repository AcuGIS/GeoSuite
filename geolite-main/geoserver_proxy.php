<?php
/**
 * GeoServer Proxy
 * 
 * This proxy forwards WMS requests to GeoServer with authentication,
 * avoiding CORS issues when custom headers are needed.
 */

// Include required files
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Database.php';
require_once 'incl/Config.php';

// Require authentication - only authenticated users can use the proxy
//requireAuth();

// Get dashboard ID
$id = -1;
$id_type = '';
if(isset($_GET['map_id'])){
    $id = intval($_GET['map_id']);
    $id_type = 'map';
}else if(isset($_GET['dash_id'])){
    $id_type = 'dashboard';
    $id = intval($_GET['dash_id']);
}

if ($id === -1) {
    http_response_code(404);
    die('Bad request! Map ID is required.');
}else if(($id === 0) && !isLoggedIn()){ // map builder uses id=0 for new maps
    header('Location: index.php?error=access_denied');
    exit;
}

// Check view permission
if (!canView($id_type, $id)) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Get GeoServer configuration
$config = getGeoServerConfig();

// Get the request URI and query string
$requestUri = $_SERVER['REQUEST_URI'];
$queryString = $_SERVER['QUERY_STRING'];

// Parse the query string to remove proxy-specific parameters
parse_str($queryString, $params);

// Reconstruct the GeoServer URL
$geoserverPath = '/wms'; // Default to WMS
if (isset($params['service'])) {
    $service = strtolower($params['service']);
    if ($service === 'wfs') {
        $geoserverPath = '/wfs';
    } elseif ($service === 'wcs') {
        $geoserverPath = '/wcs';
    }
}

$geoserverUrl = $config['geoserver_url'] . $geoserverPath . '?' . $queryString;

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $geoserverUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

// Add authentication
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $config['geoserver_username'] . ':' . $config['geoserver_password']);

// Forward request method
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
} elseif ($method !== 'GET') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
}

// Get headers to forward (exclude some)
$headersToForward = [];
foreach (getallheaders() as $name => $value) {
    $lowerName = strtolower($name);
    // Skip headers that shouldn't be forwarded
    if (!in_array($lowerName, ['host', 'connection', 'authorization', 'cookie', 'referer', 'origin'])) {
        $headersToForward[] = "$name: $value";
    }
}

// Add Accept header if not present
if (!isset($headersToForward['Accept'])) {
    $headersToForward[] = 'Accept: */*';
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headersToForward);

// Get response headers
$responseHeaders = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
    $len = strlen($header);
    $header = explode(':', $header, 2);
    if (count($header) < 2) {
        return $len;
    }
    
    $name = strtolower(trim($header[0]));
    $value = trim($header[1]);
    
    // Skip headers that shouldn't be forwarded to client
    if (!in_array($name, ['transfer-encoding', 'connection'])) {
        $responseHeaders[$name] = $value;
    }
    
    return $len;
});

// Execute request
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Proxy error: ' . curl_error($ch),
        'url' => $geoserverUrl
    ]);
    curl_close($ch);
    exit;
}

// Get response info
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);

// Set response code
http_response_code($httpCode);

// Forward response headers
foreach ($responseHeaders as $name => $value) {
    header("$name: $value");
}

// Ensure Content-Type is set
if (!isset($responseHeaders['content-type']) && $contentType) {
    header("Content-Type: $contentType");
}

// Add CORS headers for same-origin requests (optional but helpful)
header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// Output response
echo $response;
