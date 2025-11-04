# GeoServer Secured Layers Fix

## Problem
Private/secured GeoServer layers were not displaying in the application, even though admin credentials were configured in `incl/Config.php`.

## Root Cause
The application had a **split authentication problem**:

1. **Server-side (PHP)**: The PHP code successfully authenticated with GeoServer when fetching the layer list via the REST API (lines 17-21 in `incl/Config.php`). This is why secured layers appeared in the layer selection dropdown.

2. **Client-side (JavaScript)**: The map rendering happens in the browser using JavaScript (OpenLayers for maps, Leaflet for dashboards). These JavaScript libraries make direct HTTP requests to GeoServer to fetch map tiles and images. **These requests did not include authentication credentials**, so GeoServer blocked access to secured layers.

## Solution
Created a **PHP proxy** (`geoserver_proxy.php`) that handles authentication server-side, avoiding CORS issues and keeping credentials secure.

### Files Created/Modified

#### 1. `geoserver_proxy.php` (NEW)
A PHP proxy endpoint that:
- Requires user authentication (only authenticated users can access)
- Receives WMS/WFS/WCS requests from the browser (same-origin, no CORS)
- Adds GeoServer credentials from `incl/Config.php`
- Forwards the request to GeoServer using cURL with authentication
- Returns the response to the browser

#### 2. `incl/map_template.php` (OpenLayers-based maps)
- **WMS Layer Requests**: Changed to use `geoserver_proxy.php` instead of direct GeoServer URL
- **GetCapabilities Requests**: Updated to use proxy for layer extent queries
- **GetFeatureInfo Requests**: Updated to use proxy for popup feature info

#### 3. `view_dashboard.php` (Leaflet-based dashboards - view mode)
- **WMS Layer Requests**: Changed to use `geoserver_proxy.php` instead of direct GeoServer URL

#### 4. `dashboard_builder.php` (Leaflet-based dashboards - builder mode)
- **WMS Layer Requests**: Changed to use `geoserver_proxy.php` instead of direct GeoServer URL

## Technical Details

### Proxy-Based Architecture
The proxy approach solves both authentication and CORS issues:

1. **Browser → Proxy**: Same-origin request (no CORS issues)
   ```javascript
   // OpenLayers example
   url: window.location.origin + "/geoserver_proxy.php"
   
   // Leaflet example
   const wmsLayer = L.tileLayer.wms(proxyUrl, {...});
   ```

2. **Proxy → GeoServer**: Server-to-server request with authentication
   ```php
   curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
   curl_setopt($ch, CURLOPT_USERPWD, 
       $config['geoserver_username'] . ':' . $config['geoserver_password']);
   ```

3. **Proxy → Browser**: Returns GeoServer response with proper headers

### Why This Works
- **No CORS Issues**: Browser makes same-origin requests to the proxy
- **Secure**: Credentials never exposed to the browser
- **Simple**: No complex custom tile loaders or header manipulation
- **Standard**: Uses standard Leaflet/OpenLayers APIs without extensions

## Security Benefits
The proxy approach is **more secure** than the original direct authentication attempt:

1. **Credentials Stay Server-Side**: GeoServer credentials are never exposed to the browser
2. **Application Authentication**: Only users authenticated with your application can use the proxy
3. **Single Point of Control**: All GeoServer access goes through the proxy, making it easy to add logging, rate limiting, or additional security measures

**Additional Recommendations**:
1. Consider using a dedicated read-only GeoServer account instead of the admin account
2. Add rate limiting to the proxy if needed
3. Add request logging for audit purposes

## Testing
To verify the fix works:
1. Configure a secured/private layer in GeoServer
2. Ensure the credentials in `incl/Config.php` have permission to access that layer
3. Create a new map or dashboard with the secured layer
4. The layer should now display correctly in the map viewer

## Configuration
No configuration changes needed. The proxy uses the existing credentials from `incl/Config.php`:
```php
$config = [
    'geoserver_url' => 'https://novella.webgis1.com/geoserver',
    'geoserver_username' => 'admin',
    'geoserver_password' => 'geoserver'
];
```

The proxy file (`geoserver_proxy.php`) should be placed in the application root directory (same level as `index.php`).

