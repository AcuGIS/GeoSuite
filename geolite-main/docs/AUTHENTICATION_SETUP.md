# Authentication System Setup Guide

This guide will help you set up the new user and group authentication system for your GeoLite GIS application.

## Overview

The authentication system includes:
- **Users**: Individual user accounts with login credentials
- **Groups**: Admin and User groups with different permission levels
- **Permissions**: Granular control over which content each group can access
- **Admin Interface**: User management page for creating users and setting permissions

### 1. Test Login

1. Navigate to your application (e.g., `http://yourserver/login.php`)
2. Login with: **username: admin**, **password: geolite**
3. You should see the main library page with a "Manage Users" button

## How It Works

### Groups

**Admin Group**
- Full access to all content (maps, dashboards, documents, HTML pages)
- Can create, view, edit, and delete any item
- Can manage users and permissions
- Automatically has access to everything without explicit permission grants

**User Group**
- Restricted access - no content visible by default
- Admins must explicitly grant permissions to specific items
- Permissions are set per-item basis
- Can only see items they have view permission for

### User Management (Admin Only)

As an admin, you can:

1. **Access User Management**: Click "Manage Users" button on the library page

2. **Create New Users**:
   - Click "Create User" button
   - Enter username, password, full name, and email
   - Select a group (Admin or User)
   - Set active status

3. **Edit Users**:
   - Click edit (pencil) icon on any user
   - Update user details
   - Change password (leave blank to keep current)
   - Change group membership

4. **Delete Users**:
   - Click delete (trash) icon on any user
   - Confirm deletion
   - Note: You cannot delete your own account

5. **Manage Permissions for User Group**:
   - Click "Manage Permissions" button in the Groups card
   - Navigate through tabs: Maps, Dashboards, Documents, HTML Pages
   - For each item, click "Edit" to set permissions:
     - **View**: User can see the item in the library
     - **Edit**: User can modify the item
     - **Delete**: User can delete the item
   - Save permissions

### How Permissions Work

- **Admin Group**: Always has full access, no explicit permissions needed
- **User Group**: Requires explicit permission grants per item
- Permissions are checked when:
  - Displaying items in the library (index.php)
  - Accessing view, edit, or delete actions
  - Creating new content (users can always create if logged in)

### Content Access for Users

When a regular user logs in:
- They only see items they have "view" permission for
- Edit and delete buttons only appear if they have those specific permissions
- If no permissions are granted, they see an empty library

## Security Notes

1. **Change Default Admin Password**: 
   - Login as admin
   - Go to Manage Users
   - Edit the admin user
   - Set a strong password

2. **User Passwords**: 
   - All passwords are hashed using PHP's `password_hash()` with bcrypt
   - Passwords are never stored in plain text

3. **Session Management**:
   - Sessions are used for authentication
   - Logout properly destroys sessions
   - Inactive sessions should be managed at the server level

## Example Usage Scenarios

### Scenario 1: Public View Access
Give User group view-only access to specific maps:
1. Go to Manage Users → Manage Permissions
2. Click Maps tab
3. For each map you want to share, click "Edit"
4. Check "Can View" only
5. Save permissions

### Scenario 2: Content Editors
Allow User group to edit specific dashboards:
1. Go to Manage Users → Manage Permissions
2. Click Dashboards tab
3. For each dashboard, click "Edit"
4. Check "Can View" and "Can Edit"
5. Save permissions

### Scenario 3: Creating New Users
Add a new user account:
1. Go to Manage Users
2. Click "Create User"
3. Enter details:
   - Username: `john.doe`
   - Password: `SecurePass123`
   - Full Name: `John Doe`
   - Email: `john.doe@example.com`
   - Group: `User`
4. Create user
5. Set permissions for items they should access

## Troubleshooting

### Cannot login with admin/admin
- Verify the migration ran successfully
- Check if the `users` table exists and has data:
  ```sql
  SELECT * FROM users WHERE username = 'admin';
  ```
- The default password hash should be: `$2y$10$6ZfN54B27A8JNIxWz8ZGq.8stjo4o61tzI3wHjRNRFr/osxrr.Sle`

### Users see no content
- Verify they're in the User group
- Check if permissions are set for items they should see
- Admins should see all content automatically

### Permission changes not taking effect
- Refresh the page after changing permissions
- Check if the user is logged in with the correct account
- Verify the permission was saved in the database:
  ```sql
  SELECT * FROM permissions WHERE group_id = [user_group_id];
  ```

## Database Schema

### users table
- `id`: Primary key
- `username`: Unique username
- `password_hash`: Hashed password
- `full_name`: User's full name
- `email`: Email address
- `group_id`: Foreign key to groups table
- `is_active`: Boolean active status
- `last_login`: Last login timestamp
- `created_at`, `updated_at`: Timestamps

### groups table
- `id`: Primary key
- `name`: Group name (Admin, User)
- `description`: Group description

### permissions table
- `id`: Primary key
- `group_id`: Foreign key to groups
- `item_type`: Type (map, dashboard, document, html_page)
- `item_id`: ID of specific item
- `can_view`, `can_edit`, `can_delete`: Boolean permissions

## API Reference

### Authentication Functions (incl/Auth.php)

- `isLoggedIn()`: Check if user is authenticated
- `authenticate($username, $password)`: Login user
- `logout()`: Logout current user
- `requireAuth()`: Require authentication or redirect
- `requireAdmin()`: Require admin privileges or redirect
- `isAdmin()`: Check if current user is admin
- `canView($itemType, $itemId)`: Check view permission
- `canEdit($itemType, $itemId)`: Check edit permission
- `canDelete($itemType, $itemId)`: Check delete permission
- `getCurrentUserId()`: Get current user ID
- `getCurrentUsername()`: Get current username
- `getCurrentUserGroupId()`: Get current user's group ID
- `getCurrentUserGroupName()`: Get current user's group name

### Database Functions (incl/Database.php)

User Management:
- `getUserByUsername($username)`
- `getUserById($id)`
- `getAllUsers()`
- `createUser($username, $password, $fullName, $email, $groupId, $isActive)`
- `updateUser($id, $username, $fullName, $email, $groupId, $isActive, $password)`
- `deleteUser($id)`

Permission Management:
- `checkPermission($groupId, $itemType, $itemId, $permissionType)`
- `getGroupPermissions($groupId)`
- `getItemPermissions($itemType, $itemId)`
- `setPermission($groupId, $itemType, $itemId, $canView, $canEdit, $canDelete)`
- `removePermission($groupId, $itemType, $itemId)`

## Future Enhancements

Potential improvements you could add:
- Password reset functionality
- Email verification
- Additional groups with custom permissions
- Permission inheritance
- Activity logging
- Session timeout settings
- Two-factor authentication
- Role-based creation permissions
- Bulk permission management

## Support

For issues or questions:
1. Check this documentation
2. Review the troubleshooting section
3. Check database logs and PHP error logs
4. Verify all migration scripts ran successfully
