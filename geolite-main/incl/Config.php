<?php
/**
 * Get available layers from GeoServer
 * @return array Array of available layers with their names and workspaces
 */
function getAvailableLayers() {
    $config = getGeoServerConfig();
    
    $url = $config['geoserver_url'] . '/rest/layers.json';
    $context = stream_context_create([
        'http' => [
            'header' => "Authorization: Basic " . base64_encode($config['geoserver_username'] . ":" . $config['geoserver_password'])
        ]
    ]);
    
    try {
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception("Failed to fetch layers from GeoServer");
        }
        
        $data = json_decode($response, true);
        $layers = [];
        
        if (isset($data['layers']['layer'])) {
            foreach ($data['layers']['layer'] as $layer) {
                if (isset($layer['name'])) {
                    // Split the layer name into workspace and layer name
                    $parts = explode(':', $layer['name']);
                    if (count($parts) === 2) {
                        $layers[] = [
                            'id' => $layer['name'],
                            'workspace' => $parts[0],
                            'name' => $parts[1],
                            'title' => isset($layer['title']) ? $layer['title'] : $parts[1]
                        ];
                    }
                }
            }
        }
        
        return $layers;
    } catch (Exception $e) {
        error_log("Error fetching GeoServer layers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get GeoServer configuration
 * @return array The GeoServer configuration array
 */
function getGeoServerConfig() {
    $pdo = getDbConnection();
    $config = $pdo->query("SELECT * FROM public.geoserver_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    return $config;
}

function saveGeoServerConfig($url, $username, $password) {
    $pdo = getDbConnection();

    $config = getGeoServerConfig();
    
    $password = empty($password) ? $config['geoserver_password'] : $password;
    
    $stmt = $pdo->prepare("UPDATE public.geoserver_config SET geoserver_url = ?, geoserver_username = ?, geoserver_password = ? WHERE id=1");
    $stmt->execute([$url, $username,  $password ]);
}
