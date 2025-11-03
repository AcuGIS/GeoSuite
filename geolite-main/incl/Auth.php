<?php
// Authentication helper functions

/**
 * Start session if not already started
 */
function ensureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    ensureSession();
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && isset($_SESSION['user_id']);
}

/**
 * Authenticate user with username and password
 * @param string $username Username
 * @param string $password Password
 * @return bool True if authentication successful
 */
function authenticate($username, $password) {
    try {
        $user = getUserByUsername($username);
        
        // PostgreSQL returns booleans as 't'/'f' strings, convert to boolean
        $isActive = ($user['is_active'] === true || $user['is_active'] === 't' || $user['is_active'] === 1 || $user['is_active'] === '1');
        
        if ($user && $isActive && password_verify($password, $user['password_hash'])) {
            ensureSession();
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['group_id'] = $user['group_id'];
            $_SESSION['group_name'] = $user['group_name'];
            $_SESSION['login_time'] = time();
            
            // Update last login time
            updateUserLastLogin($user['id']);
            
            return true;
        }
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Logout the current user
 */
function logout() {
    ensureSession();
    $_SESSION = array();
    
    // Destroy session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Require authentication - redirect to login if not authenticated
 * @param string $loginPage Path to login page (default: login.php)
 */
function requireAuth($loginPage = 'login.php') {
    if (!isLoggedIn()) {
        header('Location: ' . $loginPage);
        exit;
    }
}

/**
 * Get current username
 * @return string|null Username if logged in, null otherwise
 */
function getCurrentUsername() {
    ensureSession();
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

/**
 * Get current fullname
 * @return string|null Username if logged in, null otherwise
 */
function getCurrentFullname() {
    ensureSession();
    return isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null;
}


/**
 * Get current user ID
 * @return int|null User ID if logged in, null otherwise
 */
function getCurrentUserId() {
    ensureSession();
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current user's group ID
 * @return int|null Group ID if logged in, null otherwise
 */
function getCurrentUserGroupId() {
    ensureSession();
    return isset($_SESSION['group_id']) ? $_SESSION['group_id'] : null;
}

/**
 * Get current user's group name
 * @return string|null Group name if logged in, null otherwise
 */
function getCurrentUserGroupName() {
    ensureSession();
    return isset($_SESSION['group_name']) ? $_SESSION['group_name'] : null;
}

/**
 * Check if current user is admin
 * @return bool True if user is in Admin group
 */
function isAdmin() {
    ensureSession();
    return isset($_SESSION['group_name']) && $_SESSION['group_name'] === 'Admin';
}

/**
 * Require admin privileges - redirect or show error
 * @param string $redirectPage Page to redirect to if not admin (default: index.php)
 */
function requireAdmin($redirectPage = 'index.php') {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    
    if (!isAdmin()) {
        header('Location: ' . $redirectPage . '?error=access_denied');
        exit;
    }
}

/**
 * Check if user has permission to view an item
 * @param string $itemType Type of item (map, dashboard, document, html_page)
 * @param int $itemId ID of the item
 * @return bool True if user has permission
 */
function canView($itemType, $itemId) {
    if (!isLoggedIn()) {
        return checkPermission(1, $itemType, $itemId, 'can_view');
    }
    
    // Admins can view everything
    if (isAdmin()) {
        return true;
    }
    
    $groupId = getCurrentUserGroupId();
    if (!$groupId) {
        return false;
    }
    
    try {
        return  checkPermission($groupId, $itemType, $itemId, 'can_view') ||
                checkPermission(1, $itemType, $itemId, 'can_view');
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has permission to edit an item
 * @param string $itemType Type of item (map, dashboard, document, html_page)
 * @param int $itemId ID of the item
 * @return bool True if user has permission
 */
function canEdit($itemType, $itemId) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Admins can edit everything
    if (isAdmin()) {
        return true;
    }
    
    $groupId = getCurrentUserGroupId();
    if (!$groupId) {
        return false;
    }
    
    try {
        return checkPermission($groupId, $itemType, $itemId, 'can_edit');
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has permission to delete an item
 * @param string $itemType Type of item (map, dashboard, document, html_page)
 * @param int $itemId ID of the item
 * @return bool True if user has permission
 */
function canDelete($itemType, $itemId) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Admins can delete everything
    if (isAdmin()) {
        return true;
    }
    
    $groupId = getCurrentUserGroupId();
    if (!$groupId) {
        return false;
    }
    
    try {
        return checkPermission($groupId, $itemType, $itemId, 'can_delete');
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all items that the current user has permission to view
 * @param string $itemType Type of item
 * @param array $allItems Array of all items
 * @return array Filtered array of items user can view
 */
function filterItemsByPermission($itemType, $allItems) {
    // Admins can see everything
    if (isAdmin()) {
        return $allItems;
    }
    
    // Filter items based on permissions
    return array_filter($allItems, function($item) use ($itemType) {
        return canView($itemType, $item['id']);
    });
}
