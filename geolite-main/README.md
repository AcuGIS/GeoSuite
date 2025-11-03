# GeoLite Map Builder

A PHP application for generating and managing OpenLayers maps from GeoServer, with PostgreSQL database integration. Now includes a powerful Dashboard Builder for creating interactive data dashboards.

## Features

- **User Authentication**: Secure login system to protect your maps and dashboards
- **Interactive Map Builder**: Create custom maps with multiple basemaps (OSM, Carto Light, Carto Dark)
- **Dashboard Builder**: Create interactive dashboards with multiple widgets
  - Map widgets with GeoServer layer integration
  - Chart widgets (bar, line, pie)
  - Table widgets for data display
  - Counter widgets (count, sum, average)
  - Text widgets for annotations
  - Drag-and-drop interface
  - Resizable and repositionable widgets
  - PDF export functionality
- **GeoServer Integration**: Load and display WMS layers from GeoServer
- **Database Storage**: Save maps and dashboards to PostgreSQL database
- **Map Library**: View all saved maps as cards on the index page
- **Dashboard Library**: View all saved dashboards with easy access
- **Map Features**: 
  - Click popups for feature information
  - Zoom controls
  - Opacity controls for layers
  - Measure tools (distance and area)
  - PDF export
  - Basemap switching
  - Layer visibility toggle

## Requirements

- PHP 7.4 or higher
- PostgreSQL 10 or higher
- PHP PDO PostgreSQL extension (`php-pgsql`)
- Web server (Apache/Nginx)
- GeoServer instance

## Installation

### 1. Setup

First, install PostgreSQL server:

```bash
./installer/postgres.sh
```

Next run the app installer:

```bash
./installer/app-install.sh
```

### 3. Configure GeoServer Connection

Go ot /users.php and enter your Geoserver instance details:

### 4. Web Server Configuration

Make sure your web server has proper permissions to read the application files.

#### Apache
```apache
<Directory /path/to/GeoLite>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

#### Nginx
```nginx
location /geolite {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Usage

### Logging In

1. Navigate to `login.php` or any protected page (will redirect to login)
2. Enter credentials:
   - **Username**: `admin`
   - **Password**: `admin`
3. Click **Sign In**
4. You will be redirected to the index page

**Note**: To change the login credentials, edit `incl/Auth.php` and modify the `AUTH_USERNAME` and `AUTH_PASSWORD` constants.

### Creating a Map

1. Open `map_builder.php` in your web browser
2. Select basemaps (OpenStreetMap, Carto Light, Carto Dark)
3. Choose WMS layers from your GeoServer
4. Enable desired features (popups, zoom buttons, opacity controls)
5. Set initial map extent (optional) or capture current position
6. Enter a **Map Title** (required)
7. Add a description (optional)
8. Click **"Generate Map"** to preview
9. Click **"Save to Database"** to save the map

### Viewing Saved Maps

1. Open `index.php` in your web browser
2. Browse through your saved maps displayed as cards
3. Click **"View"** to open a map in a new tab
4. Click **"Delete"** to remove a map (with confirmation)

### Creating a Dashboard

1. Navigate to `dashboards.php` or click **"Dashboards"** from any page
2. Click **"Create New Dashboard"**
3. Drag widgets from the sidebar onto the canvas:
   - **Map**: Interactive map with GeoServer layers
   - **Chart**: Data visualization (bar, line, pie charts)
   - **Table**: Display tabular data
   - **Counter**: Show counts, sums, or averages
   - **Text**: Add descriptions and annotations
4. Configure each widget by clicking the gear icon (⚙)
5. Reposition widgets by dragging the header
6. Resize widgets by dragging the bottom-right corner
7. Click **"Save Dashboard"** and enter a title and description
8. Your dashboard will be saved and accessible from the Dashboard Library

### Viewing Saved Dashboards

1. Open `dashboards.php` in your web browser
2. Browse through your saved dashboards displayed as cards
3. Click **"View"** to open a dashboard in a new tab
4. Click **"Edit"** to modify an existing dashboard
5. Click **"Delete"** to remove a dashboard (with confirmation)
6. Use **"Export PDF"** to create a PDF snapshot of your dashboard

### Navigation

- From the index page, click **"Create New Map"** to open the map builder
- From the index page, click **"Dashboards"** to view and manage dashboards
- From any page, use the navigation buttons to switch between Maps and Dashboards
- From the map builder, click **"View Saved Maps"** to return to the index

## File Structure

```
GeoLite/
├── login.php              # Login page
├── logout.php             # Logout handler
├── index.php              # Main page with saved maps cards
├── map_builder.php        # Map builder interface
├── map_template.php       # Map HTML template generator
├── view_map.php           # Display individual saved maps
├── dashboards.php         # Dashboard library (list all dashboards)
├── dashboard_builder.php  # Dashboard builder/editor interface
├── view_dashboard.php     # Display individual dashboards
├── incl/
│   ├── Auth.php           # Authentication functions
│   ├── Config.php         # GeoServer configuration
│   └── Database.php       # PostgreSQL & dashboard database functions
└── README.md             # This file
```

## Database Schema

### Maps Table

The `maps` table stores all saved maps:

| Column | Type | Description |
|--------|------|-------------|
| id | SERIAL | Primary key |
| title | VARCHAR(255) | Map title |
| description | TEXT | Map description |
| html_content | TEXT | Generated HTML map |
| basemaps | JSONB | Selected basemaps |
| layers | JSONB | Selected WMS layers |
| features | JSONB | Enabled features |
| initial_extent | JSONB | Initial map view settings |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

### Dashboards Table

The `dashboards` table stores all saved dashboards:

| Column | Type | Description |
|--------|------|-------------|
| id | SERIAL | Primary key |
| title | VARCHAR(255) | Dashboard title |
| description | TEXT | Dashboard description |
| config | JSONB | Dashboard configuration (widgets, layout) |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

## Troubleshooting

### Database Connection Errors

- Verify PostgreSQL is running: `sudo systemctl status postgresql`
- Check database credentials in `incl/Database.php`
- Ensure PHP PDO PostgreSQL extension is installed: `php -m | grep pdo_pgsql`

### GeoServer Connection Issues

- Verify GeoServer URL is accessible
- Check GeoServer credentials in `incl/Config.php`
- Ensure GeoServer CORS is configured to allow your domain

### No Layers Showing

- Verify GeoServer REST API is accessible
- Check GeoServer authentication credentials
- Ensure layers are published in GeoServer

## Security Notes

- **Change the default login credentials** (`admin/admin`) in `incl/Auth.php` for production use
- Change default database passwords in production
- Protect `incl/` directory from direct web access using `.htaccess` or web server configuration
- Use HTTPS in production environments
- Sanitize all user inputs (already implemented in code)
- Keep GeoServer credentials secure
- Sessions are stored using PHP's default session handler
- For enhanced security, consider implementing:
  - Password hashing (currently using static credentials)
  - Database-backed user authentication
  - Session timeout
  - CSRF protection
  - Rate limiting on login attempts

## License

This project is provided as-is for use with GeoServer and OpenLayers mapping applications.
