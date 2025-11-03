<?php
// Start output buffering to prevent header issues
ob_start();

// Include required files
require_once 'incl/const.php';
require_once 'incl/Auth.php';
require_once 'incl/Database.php';
require_once 'incl/Config.php';

// Require admin authentication
requireAdmin();

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $groupId = intval($_POST['group_id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($username) || empty($password)) {
            $error = "Username and password are required.";
        } else {
            try {
                createUser($username, $password, $fullName, $email, $groupId, $isActive);
                $success = "User created successfully!";
            } catch (Exception $e) {
                $error = "Failed to create user: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_user') {
        $userId = intval($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $groupId = intval($_POST['group_id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        
        if (empty($username)) {
            $error = "Username is required.";
        } else {
            try {
                updateUser($userId, $username, $fullName, $email, $groupId, $isActive, $password);
                $success = "User updated successfully!";
            } catch (Exception $e) {
                $error = "Failed to update user: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_user') {
        $userId = intval($_POST['user_id'] ?? 0);
        
        // Prevent deleting self
        if ($userId === getCurrentUserId()) {
            $error = "You cannot delete your own account.";
        } else {
            try {
                deleteUser($userId);
                $success = "User deleted successfully!";
            } catch (Exception $e) {
                $error = "Failed to delete user: " . $e->getMessage();
            }
        }
    } elseif ($action === 'set_permission') {
        $groupId = intval($_POST['group_id'] ?? 0);
        $itemType = $_POST['item_type'] ?? '';
        $itemId = $_POST['item_id'] ?? null;
        if ($itemId !== null && $itemId !== '') {
            $itemId = intval($itemId);
        } else {
            $itemId = null;
        }
        $canView = isset($_POST['can_view']) ? 1 : 0;
        $canEdit = isset($_POST['can_edit']) ? 1 : 0;
        $canDelete = isset($_POST['can_delete']) ? 1 : 0;
        
        try {
            setPermission($groupId, $itemType, $itemId, $canView, $canEdit, $canDelete);
            $success = "Permission set successfully!";
        } catch (Exception $e) {
            $error = "Failed to set permission: " . $e->getMessage();
        }
    } elseif ($action === 'remove_permission') {
        $groupId = intval($_POST['group_id'] ?? 0);
        $itemType = $_POST['item_type'] ?? '';
        $itemId = $_POST['item_id'] ?? null;
        if ($itemId !== null && $itemId !== '') {
            $itemId = intval($itemId);
        } else {
            $itemId = null;
        }
        
        try {
            removePermission($groupId, $itemType, $itemId);
            $success = "Permission removed successfully!";
        } catch (Exception $e) {
            $error = "Failed to remove permission: " . $e->getMessage();
        }
    } elseif ($action === 'create_group') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = "Group name is required.";
        } else {
            try {
                createGroup($name, $description);
                $success = "Group created successfully!";
            } catch (Exception $e) {
                $error = "Failed to create group: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_group') {
        $groupId = intval($_POST['group_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = "Group name is required.";
        } else {
            try {
                updateGroup($groupId, $name, $description);
                $success = "Group updated successfully!";
            } catch (Exception $e) {
                $error = "Failed to update group: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_group') {
        $groupId = intval($_POST['group_id'] ?? 0);
        
        // Check if group has users
        $userCount = getGroupUserCount($groupId);
        if ($userCount > 0) {
            $error = "Cannot delete group with {$userCount} user(s). Please reassign users first.";
        } else {
            try {
                deleteGroup($groupId);
                $success = "Group deleted successfully!";
            } catch (Exception $e) {
                $error = "Failed to delete group: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_geoserver') {
        $url      = trim($_POST['geoserver_url'] ?? '');
        $username = trim($_POST['geoserver_username'] ?? '');
        $password = $_POST['geoserver_password'] ?? '';
        
        if (empty($url) || empty($username) || empty($password)) {
            $error = "Username/password and URL are required.";
        } else {
            try {
                saveGeoServerConfig($url, $username, $password);
                $success = "Geoserver updated successfully!";
            } catch (Exception $e) {
                $error = "Failed to update geoserver: " . $e->getMessage();
            }
        }
    }
}

// Get all users
try {
    $users = getAllUsers();
} catch (Exception $e) {
    $error = "Failed to load users.";
    $users = [];
}

// Get all groups
try {
    $groups = getAllGroups();
} catch (Exception $e) {
    $error = "Failed to load groups.";
    $groups = [];
}

// Get permissions for all groups
$allGroupPermissions = [];
foreach ($groups as $group) {
    try {
        $allGroupPermissions[$group['id']] = getGroupPermissions($group['id']);
    } catch (Exception $e) {
        $allGroupPermissions[$group['id']] = [];
    }
}

// Also get user counts for each group
$groupUserCounts = [];
foreach ($groups as $group) {
    try {
        $groupUserCounts[$group['id']] = getGroupUserCount($group['id']);
    } catch (Exception $e) {
        $groupUserCounts[$group['id']] = 0;
    }
}

// Get selected group for permission management (from GET parameter)
$selectedGroupId = isset($_GET['manage_permissions']) ? intval($_GET['manage_permissions']) : null;
$selectedGroup = null;
$selectedGroupPermissions = [];

if ($selectedGroupId) {
    $selectedGroup = getGroupById($selectedGroupId);
    if ($selectedGroup) {
        $selectedGroupPermissions = $allGroupPermissions[$selectedGroupId] ?? [];
    }
}

// Get all content items for permission management
try {
    $allMaps = getAllMaps();
    $allDashboards = getAllDashboards();
    $allDocuments = getAllDocuments();
    $allHtmlPages = getAllHtmlPages();
} catch (Exception $e) {
    $allMaps = [];
    $allDashboards = [];
    $allDocuments = [];
    $allHtmlPages = [];
}

$geoserver_config = getGeoServerConfig();

// Flush output buffer
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - GeoLite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f3f4f6;
            min-height: 100vh;
            padding: 0;
        }
        .card {
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .table {
            margin-bottom: 0;
        }
        .badge {
            padding: 5px 10px;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.875rem;
        }
        .permission-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .permission-badge {
            display: inline-block;
            margin: 2px;
        }
    </style>
</head>
<body>
    <!-- Set header variables for the include -->
    <?php 
    $headerTitle = 'User Management';
    $headerSubtitle = 'Manage users, groups, and permissions';
    $headerIcon = 'people';
    include 'incl/header.php'; 
    ?>

    <div class="container-fluid" style="max-width: 98%; padding: 20px; margin: 0 auto;">

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Users List -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0"><i class="bi bi-person-lines-fill"></i> Users</h3>
                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                <i class="bi bi-plus-circle"></i> Create User
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Group</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            <?php if ($user['id'] === getCurrentUserId()): ?>
                                                <span class="badge bg-info">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['group_name'] === 'Admin' ? 'danger' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars($user['group_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['last_login']): ?>
                                                <?php echo date('M j, Y g:i A', strtotime($user['last_login'])); ?>
                                            <?php else: ?>
                                                <em class="text-muted">Never</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($user['id'] !== getCurrentUserId()): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Groups Management -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0"><i class="bi bi-people-fill"></i> Groups</h3>
                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                                <i class="bi bi-plus-circle"></i> Create Group
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php foreach ($groups as $group): ?>
                        <div class="mb-3 p-2" style="background: #f8f9fa; border-radius: 8px;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <span class="badge bg-<?php echo $group['name'] === 'Admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo htmlspecialchars($group['name']); ?>
                                        </span>
                                        <small class="text-muted">(<?php echo $groupUserCounts[$group['id']]; ?> users)</small>
                                    </h5>
                                    <p class="text-muted mb-2 small"><?php echo htmlspecialchars($group['description']); ?></p>
                                    <?php if ($group['name'] === 'Admin'): ?>
                                        <small class="text-success d-block">
                                            <i class="bi bi-check-circle"></i> Full access to all content
                                        </small>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-primary mt-1" onclick="openPermissionsModal(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">
                                            <i class="bi bi-shield-lock"></i> Manage Permissions
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group btn-group-sm ms-2">
                                    <button class="btn btn-outline-secondary" onclick="editGroup(<?php echo htmlspecialchars(json_encode($group)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($groupUserCounts[$group['id']] == 0 && $group['name'] !== 'Admin'): ?>
                                    <button class="btn btn-outline-danger" onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Geoserver Info -->
                <div class="card">
<div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0"><i class="bi bi-layers"></i> GeoServer</h3>                            
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>URL</th>
                                        <th>Username</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($geoserver_config['geoserver_url']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($geoserver_config['geoserver_username']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editGeoserver(<?php echo htmlspecialchars(json_encode($geoserver_config)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus"></i> Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create_username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="create_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="create_password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="create_full_name" name="full_name">
                        </div>
                        <div class="mb-3">
                            <label for="create_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="create_email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="create_group_id" class="form-label">Group *</label>
                            <select class="form-select" id="create_group_id" name="group_id" required>
                                <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>" <?php echo $group['name'] === 'User' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="create_is_active" name="is_active" checked>
                            <label class="form-check-label" for="create_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="edit_password" name="password" placeholder="Leave blank to keep current password">
                        </div>
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name">
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="edit_group_id" class="form-label">Group *</label>
                            <select class="form-select" id="edit_group_id" name="group_id" required>
                                <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>">
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger"></i> Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete user "<strong id="delete_username"></strong>"?</p>
                        <p class="text-danger mb-0">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_group">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create New Group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create_group_name" class="form-label">Group Name *</label>
                            <input type="text" class="form-control" id="create_group_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_group_description" class="form-label">Description</label>
                            <textarea class="form-control" id="create_group_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Group Modal -->
    <div class="modal fade" id="editGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_group">
                    <input type="hidden" name="group_id" id="edit_group_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_group_name" class="form-label">Group Name *</label>
                            <input type="text" class="form-control" id="edit_group_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_group_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_group_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Group Modal -->
    <div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="group_id" id="delete_group_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger"></i> Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the group "<strong id="delete_group_name"></strong>"?</p>
                        <p class="text-danger mb-0">This action cannot be undone. All permissions for this group will be removed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Permissions Modal -->
    <?php if ($selectedGroup): ?>
    <div class="modal fade show" id="managePermissionsModal" tabindex="-1" aria-hidden="false" style="display: block;" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-lock"></i> Manage Permissions for <?php echo htmlspecialchars($selectedGroup['name']); ?></h5>
                    <a href="users.php" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="current_group_id" value="<?php echo $selectedGroupId; ?>">
                    <p class="text-muted">Set permissions for this group on specific items. By default, groups have no access to any content.</p>
                    
                    <ul class="nav nav-tabs" id="permissionTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="maps-tab" data-bs-toggle="tab" data-bs-target="#maps" type="button" role="tab">
                                <i class="bi bi-map"></i> Maps (<?php echo count($allMaps); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="dashboards-tab" data-bs-toggle="tab" data-bs-target="#dashboards" type="button" role="tab">
                                <i class="bi bi-speedometer2"></i> Dashboards (<?php echo count($allDashboards); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                                <i class="bi bi-file-earmark"></i> Documents (<?php echo count($allDocuments); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="htmlpages-tab" data-bs-toggle="tab" data-bs-target="#htmlpages" type="button" role="tab">
                                <i class="bi bi-file-code"></i> HTML Pages (<?php echo count($allHtmlPages); ?>)
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="permissionTabContent">
                        <!-- Maps Tab -->
                        <div class="tab-pane fade show active" id="maps" role="tabpanel">
                            <?php if (empty($allMaps)): ?>
                                <p class="text-muted">No maps available.</p>
                            <?php else: ?>
                                <?php foreach ($allMaps as $map): ?>
                                    <?php
                                    $hasPermission = false;
                                    $currentPerms = ['can_view' => false, 'can_edit' => false, 'can_delete' => false];
                                    foreach ($selectedGroupPermissions as $perm) {
                                        if ($perm['item_type'] === 'map' && $perm['item_id'] == $map['id']) {
                                            $hasPermission = true;
                                            $currentPerms = $perm;
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="permission-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($map['title']); ?></strong>
                                                <?php if ($hasPermission): ?>
                                                    <div class="mt-1">
                                                        <?php if ($currentPerms['can_view']): ?>
                                                            <span class="badge bg-success permission-badge">View</span>
                                                        <?php endif; ?>
                                                        <?php if ($currentPerms['can_edit']): ?>
                                                            <span class="badge bg-warning permission-badge">Edit</span>
                                                        <?php endif; ?>
                                                        <?php if ($currentPerms['can_delete']): ?>
                                                            <span class="badge bg-danger permission-badge">Delete</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-secondary">No Access</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="setItemPermission('map', <?php echo $map['id']; ?>, '<?php echo htmlspecialchars(addslashes($map['title'])); ?>', <?php echo htmlspecialchars(json_encode($currentPerms)); ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Dashboards Tab -->
                        <div class="tab-pane fade" id="dashboards" role="tabpanel">
                            <?php if (empty($allDashboards)): ?>
                                <p class="text-muted">No dashboards available.</p>
                            <?php else: ?>
                                <?php foreach ($allDashboards as $dashboard): ?>
                                    <?php
                                    $hasPermission = false;
                                    $currentPerms = ['can_view' => false, 'can_edit' => false, 'can_delete' => false];
                                    foreach ($selectedGroupPermissions as $perm) {
                                        if ($perm['item_type'] === 'dashboard' && $perm['item_id'] == $dashboard['id']) {
                                            $hasPermission = true;
                                            $currentPerms = $perm;
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="permission-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($dashboard['title']); ?></strong>
                                                <?php if ($hasPermission): ?>
                                                    <div class="mt-1">
                                                        <?php if ($currentPerms['can_view']): ?>
                                                            <span class="badge bg-success permission-badge">View</span>
                                                        <?php endif; ?>
                                                        <?php if ($currentPerms['can_edit']): ?>
                                                            <span class="badge bg-warning permission-badge">Edit</span>
                                                        <?php endif; ?>
                                                        <?php if ($currentPerms['can_delete']): ?>
                                                            <span class="badge bg-danger permission-badge">Delete</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-secondary">No Access</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="setItemPermission('dashboard', <?php echo $dashboard['id']; ?>, '<?php echo htmlspecialchars(addslashes($dashboard['title'])); ?>', <?php echo htmlspecialchars(json_encode($currentPerms)); ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documents" role="tabpanel">
                            <?php if (empty($allDocuments)): ?>
                                <p class="text-muted">No documents available.</p>
                            <?php else: ?>
                                <?php foreach ($allDocuments as $doc): ?>
                                    <?php
                                    $hasPermission = false;
                                    $currentPerms = ['can_view' => false, 'can_edit' => false, 'can_delete' => false];
                                    foreach ($selectedGroupPermissions as $perm) {
                                        if ($perm['item_type'] === 'document' && $perm['item_id'] == $doc['id']) {
                                            $hasPermission = true;
                                            $currentPerms = $perm;
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="permission-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($doc['title']); ?></strong>
                                                <?php if ($hasPermission): ?>
                                                    <div class="mt-1">
                                                        <?php if ($currentPerms['can_view']): ?>
                                                            <span class="badge bg-success permission-badge">View</span>
                                                        <?php endif; ?>
                                                        <?php if ($currentPerms['can_edit']): ?>
                                                            <span class="badge bg-warning permission-badge">Edit</span>
                                                        <?php endif; ?>
                                                        <?php if ($currentPerms['can_delete']): ?>
                                                            <span class="badge bg-danger permission-badge">Delete</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-secondary">No Access</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="setItemPermission('document', <?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['title'])); ?>', <?php echo htmlspecialchars(json_encode($currentPerms)); ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- HTML Pages Tab -->
                        <div class="tab-pane fade" id="htmlpages" role="tabpanel">
                            <?php if (empty($allHtmlPages)): ?>
                                <p class="text-muted">No HTML pages available.</p>
                            <?php else: ?>
                                <?php foreach ($allHtmlPages as $page): ?>
                                    <?php
                                    $hasPermission = false;
                                    $currentPerms = ['can_view' => false, 'can_edit' => false, 'can_delete' => false];
                                    foreach ($selectedGroupPermissions as $perm) {
                                        if ($perm['item_type'] === 'html_page' && $perm['item_id'] == $page['id']) {
                                            $hasPermission = true;
                                            $currentPerms = $perm;
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="permission-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($page['title']); ?></strong>
                                                <?php if ($hasPermission): ?>
                                                    <div class="mt-1">
                                                        <?php if ($currentPerms['can_view']): ?>
                                                            <span class="badge bg-success permission-badge">View</span>
                                                        <?php endif; ?>
                                                        <?php if ($currentPerms['can_edit']): ?>
                                                            <span class="badge bg-warning permission-badge">Edit</span>
                                                        <?php endif; ?>
                                                        <?php if ($currentPerms['can_delete']): ?>
                                                            <span class="badge bg-danger permission-badge">Delete</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-secondary">No Access</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="setItemPermission('html_page', <?php echo $page['id']; ?>, '<?php echo htmlspecialchars(addslashes($page['title'])); ?>', <?php echo htmlspecialchars(json_encode($currentPerms)); ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <!-- Edit Permission Modal -->
    <div class="modal fade" id="editPermissionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="set_permission">
                    <input type="hidden" name="group_id" id="perm_group_id">
                    <input type="hidden" name="item_type" id="perm_item_type">
                    <input type="hidden" name="item_id" id="perm_item_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-shield-check"></i> Set Permissions</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Set permissions for: <strong id="perm_item_title"></strong></p>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="perm_can_view" name="can_view">
                            <label class="form-check-label" for="perm_can_view">
                                <i class="bi bi-eye"></i> Can View
                            </label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="perm_can_edit" name="can_edit">
                            <label class="form-check-label" for="perm_can_edit">
                                <i class="bi bi-pencil"></i> Can Edit
                            </label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="perm_can_delete" name="can_delete">
                            <label class="form-check-label" for="perm_can_delete">
                                <i class="bi bi-trash"></i> Can Delete
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Permissions
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Geoserver Modal -->
    <div class="modal fade" id="editGeoserverModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_geoserver">
                    
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Geoserver</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_geoserver_url" class="form-label">URL</label>
                            <input type="text" class="form-control" id="edit_geoserver_url" name="geoserver_url" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_geoserver_username" name="geoserver_username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="edit_geoserver_password" name="geoserver_password" placeholder="Leave blank to keep current password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_group_id').value = user.group_id;
            document.getElementById('edit_is_active').checked = user.is_active;
            document.getElementById('edit_password').value = '';
            
            var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }

        function deleteUser(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            
            var modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            modal.show();
        }

        function setItemPermission(itemType, itemId, itemTitle, currentPerms) {
            const groupId = document.getElementById('current_group_id').value;
            document.getElementById('perm_group_id').value = groupId;
            document.getElementById('perm_item_type').value = itemType;
            document.getElementById('perm_item_id').value = itemId;
            document.getElementById('perm_item_title').textContent = itemTitle;
            document.getElementById('perm_can_view').checked = currentPerms.can_view || false;
            document.getElementById('perm_can_edit').checked = currentPerms.can_edit || false;
            document.getElementById('perm_can_delete').checked = currentPerms.can_delete || false;
            
            var modal = new bootstrap.Modal(document.getElementById('editPermissionModal'));
            modal.show();
        }
        
        function openPermissionsModal(groupId, groupName) {
            // Reload the page with selected group to show permissions modal
            window.location.href = 'users.php?manage_permissions=' + groupId;
        }
        
        function editGroup(group) {
            document.getElementById('edit_group_id').value = group.id;
            document.getElementById('edit_group_name').value = group.name;
            document.getElementById('edit_group_description').value = group.description;
            
            var modal = new bootstrap.Modal(document.getElementById('editGroupModal'));
            modal.show();
        }
        
        function deleteGroup(groupId, groupName) {
            document.getElementById('delete_group_id').value = groupId;
            document.getElementById('delete_group_name').textContent = groupName;
            
            var modal = new bootstrap.Modal(document.getElementById('deleteGroupModal'));
            modal.show();
        }
        
        function editGeoserver(config) {
            document.getElementById('edit_geoserver_url').value = config.geoserver_url;
            document.getElementById('edit_geoserver_username').value = config.geoserver_username;
            document.getElementById('edit_geoserver_password').value = config.geoserver_password;
            
            var modal = new bootstrap.Modal(document.getElementById('editGeoserverModal'));
            modal.show();
        }
        
        // Store all data in JavaScript for dynamic permission management
        const allGroupPermissions = <?php echo json_encode($allGroupPermissions); ?>;
        const allMaps = <?php echo json_encode($allMaps); ?>;
        const allDashboards = <?php echo json_encode($allDashboards); ?>;
        const allDocuments = <?php echo json_encode($allDocuments); ?>;
        const allHtmlPages = <?php echo json_encode($allHtmlPages); ?>;
    </script>
</body>
</html>
