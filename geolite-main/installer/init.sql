INSERT INTO public.groups
    (name, description, created_at)
VALUES
    ('Public', 'Group for public read-only access to resources', '2025-10-19 19:52:49.764279'),
    ('Admin', 'Administrators with full access to all features and content', '2025-10-19 19:52:49.764279'),
    ('User', 'Standard users with restricted access', '2025-10-19 19:52:49.764279');

-- Give Admin group full permissions to everything (NULL item_id means all items)
INSERT INTO public.permissions
    (group_id, item_type, item_id, can_view, can_edit, can_delete)
VALUES
    (2, 'map',          NULL, TRUE, TRUE, TRUE),
    (2, 'dashboard',    NULL, TRUE, TRUE, TRUE),
    (2, 'document',     NULL, TRUE, TRUE, TRUE),
    (2, 'html_page',    NULL, TRUE, TRUE, TRUE)
ON CONFLICT (group_id, item_type, item_id) DO NOTHING;
    
INSERT INTO public.users
    (username, password_hash, full_name, email, group_id, is_active, created_at, updated_at, last_login)
VALUES
    ('admin', 'ADMIN_APP_PASS', 'Administrator', 'admin@example.com', 2, true, '2025-10-19 19:52:49.764279', '2025-10-20 06:44:00.662218', '2025-10-20 06:44:00.662218'),
    ('jane.doe', 'ADMIN_APP_PASS', 'Jane Doe', 'jane@doe.com', 3, true, '2025-10-19 20:06:14.145062', '2025-10-19 20:17:27.274041', '2025-10-19 20:17:27.274041');

INSERT INTO public.geoserver_config
    (geoserver_url, geoserver_username, geoserver_password)
VALUES
    ('http://localhost:8080/geoserver', 'admin', 'geoserver');

-- Insert common mapping categories
INSERT INTO public.categories (name, description, color, icon) VALUES
    ('Administrative', 'Administrative boundaries, districts, and jurisdictional areas', '#e74c3c', 'bi-building'),
    ('Transportation', 'Roads, highways, railways, airports, and transportation infrastructure', '#3498db', 'bi-car-front'),
    ('Water Features', 'Rivers, lakes, streams, wetlands, and water bodies', '#2980b9', 'bi-droplet'),
    ('Land Use', 'Land cover, zoning, agricultural areas, and land use classifications', '#27ae60', 'bi-tree'),
    ('Elevation', 'Topography, elevation contours, and terrain features', '#8e44ad', 'bi-mountain'),
    ('Population', 'Demographics, census data, and population distribution', '#f39c12', 'bi-people'),
    ('Environment', 'Environmental features, protected areas, and ecological zones', '#16a085', 'bi-flower1'),
    ('Utilities', 'Power lines, pipelines, telecommunications, and utility infrastructure', '#d35400', 'bi-lightning'),
    ('Emergency Services', 'Fire stations, police stations, hospitals, and emergency facilities', '#c0392b', 'bi-hospital'),
    ('Recreation', 'Parks, recreational facilities, and leisure areas', '#2ecc71', 'bi-tree-fill'),
    ('Economic', 'Business districts, commercial areas, and economic zones', '#f1c40f', 'bi-shop'),
    ('Cultural', 'Historical sites, monuments, museums, and cultural landmarks', '#9b59b6', 'bi-building'),
    ('Weather', 'Weather stations, climate data, and meteorological information', '#1abc9c', 'bi-cloud-sun'),
    ('Infrastructure', 'Bridges, dams, ports, and major infrastructure projects', '#34495e', 'bi-gear'),
    ('Boundaries', 'International borders, state lines, and territorial boundaries', '#95a5a6', 'bi-diagram-2');
