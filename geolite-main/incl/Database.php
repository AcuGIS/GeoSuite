<?php
/**
 * Get database connection
 * @return PDO Database connection
 */
function getDbConnection() {    
    try {
        $pdo = new PDO('pgsql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Convert PostgreSQL booleans to PHP booleans
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new Exception("Database connection failed");
    }
}

/**
 * Save a map to the database
 * @param string $title Map title
 * @param string $description Map description
 * @param string $html_content The generated HTML content
 * @param array $basemaps Selected basemaps
 * @param array $layers Selected layers
 * @param array $features Selected features
 * @param array $initialExtent Initial extent settings
 * @param int|null $categoryId Category ID (optional)
 * @return int The ID of the saved map
 */
function saveMap($title, $description, $html_content, $basemaps, $layers, $features, $initialExtent, $categoryId = null, $filters = null) {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO maps (title, description, html_content, basemaps, layers, features, initial_extent, filters, category_id, created_at, updated_at) 
            VALUES (:title, :description, :html_content, :basemaps, :layers, :features, :initial_extent, :filters, :category_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'html_content' => $html_content,
        'basemaps' => json_encode($basemaps),
        'layers' => json_encode($layers),
        'features' => json_encode($features),
        'initial_extent' => json_encode($initialExtent),
        'filters' => json_encode($filters ?? []),
        'category_id' => $categoryId
    ]);
    
    $result = $stmt->fetch();
    return $result['id'];
}

/**
 * Get all maps from the database
 * @return array Array of maps with category information
 */
function getAllMaps() {
    $pdo = getDbConnection();
    
    $sql = "SELECT m.id, m.title, m.description, m.thumbnail, m.created_at, m.updated_at, 
                   c.id as category_id, c.name as category_name, c.color as category_color, c.icon as category_icon
            FROM maps m 
            LEFT JOIN categories c ON m.category_id = c.id 
            ORDER BY m.created_at DESC";
    $stmt = $pdo->query($sql);
    
    return $stmt->fetchAll();
}

/**
 * Get a single map by ID
 * @param int $id Map ID
 * @return array|null Map data or null if not found
 */
function getMapById($id) {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM maps WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    return $stmt->fetch();
}

/**
 * Delete a map by ID
 * @param int $id Map ID
 * @return bool Success status
 */
function deleteMap($id) {
    $pdo = getDbConnection();
    
    $sql = "DELETE FROM maps WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute(['id' => $id]);
}

/**
 * Update a map
 * @param int $id Map ID
 * @param string $title Map title
 * @param string $description Map description
 * @param string $html_content The generated HTML content
 * @param array $basemaps Selected basemaps
 * @param array $layers Selected layers
 * @param array $features Selected features
 * @param array $initialExtent Initial extent settings
 * @param int|null $categoryId Category ID (optional)
 * @return bool Success status
 */
function updateMap($id, $title, $description, $html_content, $basemaps, $layers, $features, $initialExtent, $categoryId = null, $filters = null) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE maps SET 
            title = :title, 
            description = :description, 
            html_content = :html_content, 
            basemaps = :basemaps, 
            layers = :layers, 
            features = :features, 
            initial_extent = :initial_extent,
            filters = :filters,
            category_id = :category_id,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'title' => $title,
        'description' => $description,
        'html_content' => $html_content,
        'basemaps' => json_encode($basemaps),
        'layers' => json_encode($layers),
        'features' => json_encode($features),
        'initial_extent' => json_encode($initialExtent),
        'filters' => json_encode($filters ?? []),
        'category_id' => $categoryId
    ]);
}

/* ==================== DASHBOARD FUNCTIONS ==================== */

/**
 * Save a dashboard to the database
 * @param string $title Dashboard title
 * @param string $description Dashboard description
 * @param array $config Dashboard configuration (widgets, layout, etc.)
 * @param int|null $categoryId Category ID (optional)
 * @return int The ID of the saved dashboard
 */
function saveDashboard($title, $description, $config, $categoryId = null) {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO dashboards (title, description, config, category_id, created_at, updated_at) 
            VALUES (:title, :description, :config, :category_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'config' => json_encode($config),
        'category_id' => $categoryId
    ]);
    
    $result = $stmt->fetch();
    return $result['id'];
}

/**
 * Get all dashboards from the database
 * @return array Array of dashboards with category information
 */
function getAllDashboards() {
    $pdo = getDbConnection();
    
    $sql = "SELECT d.id, d.title, d.description, d.thumbnail, d.created_at, d.updated_at,
                   c.id as category_id, c.name as category_name, c.color as category_color, c.icon as category_icon
            FROM dashboards d 
            LEFT JOIN categories c ON d.category_id = c.id 
            ORDER BY d.created_at DESC";
    $stmt = $pdo->query($sql);
    
    return $stmt->fetchAll();
}

/**
 * Get a single dashboard by ID
 * @param int $id Dashboard ID
 * @return array|null Dashboard data or null if not found
 */
function getDashboardById($id) {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM dashboards WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    return $stmt->fetch();
}

/**
 * Delete a dashboard by ID
 * @param int $id Dashboard ID
 * @return bool Success status
 */
function deleteDashboard($id) {
    $pdo = getDbConnection();
    
    $sql = "DELETE FROM dashboards WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute(['id' => $id]);
}

/**
 * Update a dashboard
 * @param int $id Dashboard ID
 * @param string $title Dashboard title
 * @param string $description Dashboard description
 * @param array $config Dashboard configuration
 * @param int|null $categoryId Category ID (optional)
 * @return bool Success status
 */
function updateDashboard($id, $title, $description, $config, $categoryId = null) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE dashboards SET 
            title = :title, 
            description = :description, 
            config = :config,
            category_id = :category_id,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'title' => $title,
        'description' => $description,
        'config' => json_encode($config),
        'category_id' => $categoryId
    ]);
}

/* ==================== DOCUMENTS FUNCTIONS ==================== */

/**
 * Save a document to the database
 * @param string $title Document title
 * @param string $description Document description
 * @param string $filename Unique filename
 * @param string $originalFilename Original filename
 * @param string $filePath Path to the uploaded file
 * @param int $fileSize File size in bytes
 * @param string $mimeType File MIME type
 * @param int|null $categoryId Category ID (optional)
 * @return int The ID of the saved document
 */
function saveDocument($title, $description, $filename, $originalFilename, $filePath, $fileSize, $mimeType, $categoryId = null) {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO documents (title, description, filename, original_filename, file_size, mime_type, category_id, created_at, updated_at) 
            VALUES (:title, :description, :filename, :original_filename, :file_size, :mime_type, :category_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'filename' => $filename,
        'original_filename' => $originalFilename,
        'file_size' => $fileSize,
        'mime_type' => $mimeType,
        'category_id' => $categoryId
    ]);
    
    $result = $stmt->fetch();
    return $result['id'];
}

/**
 * Get all documents from the database
 * @return array Array of documents with category information
 */
function getAllDocuments() {
    try {
        $pdo = getDbConnection();
        
        $sql = "SELECT d.id, d.title, d.description, d.thumbnail, d.original_filename, d.file_size, d.mime_type, d.created_at, d.updated_at,
                       c.id as category_id, c.name as category_name, c.color as category_color, c.icon as category_icon
                FROM documents d 
                LEFT JOIN categories c ON d.category_id = c.id 
                ORDER BY d.created_at DESC";
        $stmt = $pdo->query($sql);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table might not exist yet
        return [];
    }
}

/**
 * Get a single document by ID
 * @param int $id Document ID
 * @return array|null Document data or null if not found
 */
function getDocumentById($id) {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM documents WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    return $stmt->fetch();
}

/**
 * Delete a document by ID
 * @param int $id Document ID
 * @return bool Success status
 */
function deleteDocument($id) {
    $pdo = getDbConnection();
    
    $sql = "DELETE FROM documents WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute(['id' => $id]);
}

/**
 * Update a document
 * @param int $id Document ID
 * @param string $title Document title
 * @param string $description Document description
 * @param int|null $categoryId Category ID (optional)
 * @return bool Success status
 */
function updateDocument($id, $title, $description, $categoryId = null) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE documents SET 
            title = :title, 
            description = :description,
            category_id = :category_id,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'title' => $title,
        'description' => $description,
        'category_id' => $categoryId
    ]);
}

/* ==================== HTML PAGES FUNCTIONS ==================== */

/**
 * Save an HTML page to the database
 * @param string $title Page title
 * @param string $description Page description
 * @param string $htmlContent HTML content
 * @param int|null $categoryId Category ID (optional)
 * @return int The ID of the saved HTML page
 */
function saveHtmlPage($title, $description, $htmlContent, $categoryId = null) {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO html_pages (title, description, html_content, category_id, created_at, updated_at) 
            VALUES (:title, :description, :html_content, :category_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'html_content' => $htmlContent,
        'category_id' => $categoryId
    ]);
    
    $result = $stmt->fetch();
    return $result['id'];
}

/**
 * Get all HTML pages from the database
 * @return array Array of HTML pages with category information
 */
function getAllHtmlPages() {
    try {
        $pdo = getDbConnection();
        
        $sql = "SELECT h.id, h.title, h.description, h.thumbnail, h.created_at, h.updated_at,
                       c.id as category_id, c.name as category_name, c.color as category_color, c.icon as category_icon
                FROM html_pages h 
                LEFT JOIN categories c ON h.category_id = c.id 
                ORDER BY h.created_at DESC";
        $stmt = $pdo->query($sql);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table might not exist yet
        return [];
    }
}

/**
 * Get a single HTML page by ID
 * @param int $id HTML page ID
 * @return array|null HTML page data or null if not found
 */
function getHtmlPageById($id) {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM html_pages WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    return $stmt->fetch();
}

/**
 * Delete an HTML page by ID
 * @param int $id HTML page ID
 * @return bool Success status
 */
function deleteHtmlPage($id) {
    $pdo = getDbConnection();
    
    $sql = "DELETE FROM html_pages WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute(['id' => $id]);
}

/**
 * Update an HTML page
 * @param int $id HTML page ID
 * @param string $title Page title
 * @param string $description Page description
 * @param string $htmlContent HTML content
 * @param int|null $categoryId Category ID (optional)
 * @return bool Success status
 */
function updateHtmlPage($id, $title, $description, $htmlContent, $categoryId = null) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE html_pages SET 
            title = :title, 
            description = :description, 
            html_content = :html_content,
            category_id = :category_id,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'title' => $title,
        'description' => $description,
        'html_content' => $htmlContent,
        'category_id' => $categoryId
    ]);
}

/* ==================== THUMBNAIL FUNCTIONS ==================== */

/**
 * Update thumbnail for a map
 * @param int $id Map ID
 * @param string $thumbnailPath Path to thumbnail image
 * @return bool Success status
 */
function updateMapThumbnail($id, $thumbnailPath) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE maps SET thumbnail = :thumbnail, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'thumbnail' => $thumbnailPath
    ]);
}

/**
 * Update thumbnail for a dashboard
 * @param int $id Dashboard ID
 * @param string $thumbnailPath Path to thumbnail image
 * @return bool Success status
 */
function updateDashboardThumbnail($id, $thumbnailPath) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE dashboards SET thumbnail = :thumbnail, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'thumbnail' => $thumbnailPath
    ]);
}

/**
 * Update thumbnail for a document
 * @param int $id Document ID
 * @param string $thumbnailPath Path to thumbnail image
 * @return bool Success status
 */
function updateDocumentThumbnail($id, $thumbnailPath) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE documents SET thumbnail = :thumbnail, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'thumbnail' => $thumbnailPath
    ]);
}

/**
 * Update thumbnail for an HTML page
 * @param int $id HTML page ID
 * @param string $thumbnailPath Path to thumbnail image
 * @return bool Success status
 */
function updateHtmlPageThumbnail($id, $thumbnailPath) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE html_pages SET thumbnail = :thumbnail, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'thumbnail' => $thumbnailPath
    ]);
}

/* ==================== USER MANAGEMENT FUNCTIONS ==================== */

/**
 * Get user by username
 * @param string $username Username
 * @return array|null User data with group info or null if not found
 */
function getUserByUsername($username) {
    $pdo = getDbConnection();
    
    $sql = "SELECT u.*, g.name as group_name 
            FROM users u 
            LEFT JOIN groups g ON u.group_id = g.id 
            WHERE u.username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['username' => $username]);
    
    return $stmt->fetch();
}

/**
 * Get user by ID
 * @param int $id User ID
 * @return array|null User data with group info or null if not found
 */
function getUserById($id) {
    $pdo = getDbConnection();
    
    $sql = "SELECT u.*, g.name as group_name 
            FROM users u 
            LEFT JOIN groups g ON u.group_id = g.id 
            WHERE u.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    return $stmt->fetch();
}

/**
 * Get all users
 * @return array Array of users with group info
 */
function getAllUsers() {
    $pdo = getDbConnection();
    
    $sql = "SELECT u.*, g.name as group_name 
            FROM users u 
            LEFT JOIN groups g ON u.group_id = g.id 
            ORDER BY u.username";
    $stmt = $pdo->query($sql);
    
    return $stmt->fetchAll();
}

/**
 * Create a new user
 * @param string $username Username
 * @param string $password Plain text password (will be hashed)
 * @param string $fullName Full name
 * @param string $email Email address
 * @param int $groupId Group ID
 * @param bool $isActive Whether user is active
 * @return int New user ID
 */
function createUser($username, $password, $fullName, $email, $groupId, $isActive = true) {
    $pdo = getDbConnection();
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, password_hash, full_name, email, group_id, is_active, created_at, updated_at) 
            VALUES (:username, :password_hash, :full_name, :email, :group_id, :is_active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'password_hash' => $passwordHash,
        'full_name' => $fullName,
        'email' => $email,
        'group_id' => $groupId,
        'is_active' => $isActive
    ]);
    
    $result = $stmt->fetch();
    return $result['id'];
}

/**
 * Update a user
 * @param int $id User ID
 * @param string $username Username
 * @param string $fullName Full name
 * @param string $email Email address
 * @param int $groupId Group ID
 * @param bool $isActive Whether user is active
 * @param string|null $password New password (optional, only if changing)
 * @return bool Success status
 */
function updateUser($id, $username, $fullName, $email, $groupId, $isActive, $password = null) {
    $pdo = getDbConnection();
    
    if ($password !== null && $password !== '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET 
                username = :username, 
                password_hash = :password_hash,
                full_name = :full_name, 
                email = :email, 
                group_id = :group_id, 
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        return $pdo->prepare($sql)->execute([
            'id' => $id,
            'username' => $username,
            'password_hash' => $passwordHash,
            'full_name' => $fullName,
            'email' => $email,
            'group_id' => $groupId,
            'is_active' => $isActive
        ]);
    } else {
        $sql = "UPDATE users SET 
                username = :username, 
                full_name = :full_name, 
                email = :email, 
                group_id = :group_id, 
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        return $pdo->prepare($sql)->execute([
            'id' => $id,
            'username' => $username,
            'full_name' => $fullName,
            'email' => $email,
            'group_id' => $groupId,
            'is_active' => $isActive
        ]);
    }
}

/**
 * Delete a user
 * @param int $id User ID
 * @return bool Success status
 */
function deleteUser($id) {
    $pdo = getDbConnection();
    
    $sql = "DELETE FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute(['id' => $id]);
}

/**
 * Update user's last login time
 * @param int $userId User ID
 * @return bool Success status
 */
function updateUserLastLogin($userId) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute(['id' => $userId]);
}

/* ==================== GROUP MANAGEMENT FUNCTIONS ==================== */

/**
 * Get all groups
 * @return array Array of groups
 */
function getAllGroups() {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM groups ORDER BY name";
    $stmt = $pdo->query($sql);
    
    return $stmt->fetchAll();
}

/**
 * Get group by ID
 * @param int $id Group ID
 * @return array|null Group data or null if not found
 */
function getGroupById($id) {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM groups WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    return $stmt->fetch();
}

/**
 * Create a new group
 * @param string $name Group name
 * @param string $description Group description
 * @return int New group ID
 */
function createGroup($name, $description) {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO groups (name, description, created_at) 
            VALUES (:name, :description, CURRENT_TIMESTAMP)
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'name' => $name,
        'description' => $description
    ]);
    
    $result = $stmt->fetch();
    return $result['id'];
}

/**
 * Update a group
 * @param int $id Group ID
 * @param string $name Group name
 * @param string $description Group description
 * @return bool Success status
 */
function updateGroup($id, $name, $description) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE groups SET 
            name = :name, 
            description = :description
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'name' => $name,
        'description' => $description
    ]);
}

/**
 * Delete a group
 * @param int $id Group ID
 * @return bool Success status
 */
function deleteGroup($id) {
    $pdo = getDbConnection();
    
    // Note: This will fail if users are assigned to this group due to foreign key constraint
    // That's intentional - you should reassign users first
    $sql = "DELETE FROM groups WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute(['id' => $id]);
}

/**
 * Get count of users in a group
 * @param int $groupId Group ID
 * @return int Number of users in group
 */
function getGroupUserCount($groupId) {
    $pdo = getDbConnection();
    
    $sql = "SELECT COUNT(*) as count FROM users WHERE group_id = :group_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['group_id' => $groupId]);
    
    $result = $stmt->fetch();
    return $result['count'];
}

/* ==================== CATEGORIES FUNCTIONS ==================== */

/**
 * Get categories for dropdown selection
 * @return array Array of categories formatted for dropdowns
 */
function getCategoriesForDropdown() {
    $pdo = getDbConnection();
    
    $sql = "SELECT id, name FROM categories ORDER BY name";
    $stmt = $pdo->query($sql);
    
    return $stmt->fetchAll();
}

/**
 * Get all categories
 * @return array Array of categories
 */
function getAllCategories() {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM categories ORDER BY name";
    $stmt = $pdo->query($sql);
    
    return $stmt->fetchAll();
}

/**
 * Get a single category by ID
 * @param int $id Category ID
 * @return array|null Category data or null if not found
 */
function getCategoryById($id) {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM categories WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    
    return $stmt->fetch();
}

/**
 * Create a new category
 * @param string $name Category name
 * @param string $description Category description
 * @param string $color Hex color code
 * @param string $icon Bootstrap icon class
 * @return int New category ID
 */
function createCategory($name, $description, $color = '#667eea', $icon = 'bi-tag') {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO categories (name, description, color, icon, created_at, updated_at) 
            VALUES (:name, :description, :color, :icon, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'name' => $name,
        'description' => $description,
        'color' => $color,
        'icon' => $icon
    ]);
    
    $result = $stmt->fetch();
    return $result['id'];
}

/**
 * Update a category
 * @param int $id Category ID
 * @param string $name Category name
 * @param string $description Category description
 * @param string $color Hex color code
 * @param string $icon Bootstrap icon class
 * @return bool Success status
 */
function updateCategory($id, $name, $description, $color, $icon) {
    $pdo = getDbConnection();
    
    $sql = "UPDATE categories SET 
            name = :name, 
            description = :description, 
            color = :color,
            icon = :icon,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'color' => $color,
        'icon' => $icon
    ]);
}

/**
 * Delete a category
 * @param int $id Category ID
 * @return bool Success status
 */
function deleteCategory($id) {
    $pdo = getDbConnection();
    
    $sql = "DELETE FROM categories WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute(['id' => $id]);
}

/* ==================== PERMISSION MANAGEMENT FUNCTIONS ==================== */

/**
 * Check if a group has a specific permission on an item
 * @param int $groupId Group ID
 * @param string $itemType Type of item (map, dashboard, document, html_page)
 * @param int $itemId ID of the item
 * @param string $permissionType Permission type (can_view, can_edit, can_delete)
 * @return bool True if group has permission
 */
function checkPermission($groupId, $itemType, $itemId, $permissionType) {
    $pdo = getDbConnection();
    
    // Check for specific item permission first
    $sql = "SELECT $permissionType FROM permissions 
            WHERE group_id = :group_id 
            AND item_type = :item_type 
            AND (item_id = :item_id OR item_id IS NULL)
            ORDER BY item_id DESC NULLS LAST
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'group_id' => $groupId,
        'item_type' => $itemType,
        'item_id' => $itemId
    ]);
    
    $result = $stmt->fetch();
    
    return $result && $result[$permissionType] === true;
}

/**
 * Get all permissions for a group
 * @param int $groupId Group ID
 * @return array Array of permissions
 */
function getGroupPermissions($groupId) {
    $pdo = getDbConnection();
    
    $sql = "SELECT * FROM permissions WHERE group_id = :group_id ORDER BY item_type, item_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['group_id' => $groupId]);
    
    return $stmt->fetchAll();
}

/**
 * Get permissions for a specific item
 * @param string $itemType Type of item
 * @param int $itemId ID of the item
 * @return array Array of group permissions for this item
 */
function getItemPermissions($itemType, $itemId) {
    $pdo = getDbConnection();
    
    $sql = "SELECT p.*, g.name as group_name 
            FROM permissions p
            LEFT JOIN groups g ON p.group_id = g.id
            WHERE p.item_type = :item_type 
            AND p.item_id = :item_id
            ORDER BY g.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'item_type' => $itemType,
        'item_id' => $itemId
    ]);
    
    return $stmt->fetchAll();
}

/**
 * Set permission for a group on an item
 * @param int $groupId Group ID
 * @param string $itemType Type of item
 * @param int|null $itemId ID of the item (null for all items of this type)
 * @param bool $canView Can view permission
 * @param bool $canEdit Can edit permission
 * @param bool $canDelete Can delete permission
 * @return bool Success status
 */
function setPermission($groupId, $itemType, $itemId, $canView, $canEdit, $canDelete) {
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO permissions (group_id, item_type, item_id, can_view, can_edit, can_delete, created_at)
            VALUES (:group_id, :item_type, :item_id, :can_view, :can_edit, :can_delete, CURRENT_TIMESTAMP)
            ON CONFLICT (group_id, item_type, item_id)
            DO UPDATE SET 
                can_view = :can_view, 
                can_edit = :can_edit, 
                can_delete = :can_delete";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'group_id' => $groupId,
        'item_type' => $itemType,
        'item_id' => $itemId,
        'can_view' => $canView,
        'can_edit' => $canEdit,
        'can_delete' => $canDelete
    ]);
}

/**
 * Remove permission for a group on an item
 * @param int $groupId Group ID
 * @param string $itemType Type of item
 * @param int|null $itemId ID of the item (null for all items of this type)
 * @return bool Success status
 */
function removePermission($groupId, $itemType, $itemId) {
    $pdo = getDbConnection();
    
    if ($itemId === null) {
        $sql = "DELETE FROM permissions WHERE group_id = :group_id AND item_type = :item_type AND item_id IS NULL";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            'group_id' => $groupId,
            'item_type' => $itemType
        ]);
    } else {
        $sql = "DELETE FROM permissions WHERE group_id = :group_id AND item_type = :item_type AND item_id = :item_id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            'group_id' => $groupId,
            'item_type' => $itemType,
            'item_id' => $itemId
        ]);
    }
}
