# Authentication System - Quick Reference

## Quick Start

### 1. Login
- **URL**: `login.php`
- **Default Admin**: 
  - Username: `admin`
  - Password: `geolite`
- **⚠️ IMPORTANT**: Change the default password immediately!

### 2. Access User Management
- Login as admin → Click **"Manage Users"** button on library page

## User Roles

| Role | Access | Permissions |
|------|--------|-------------|
| **Admin** | Everything | Full access to all content and user management |
| **User** | Restricted | Only sees items explicitly granted by admins |

## Common Tasks

### Create a New User
1. Manage Users → **Create User**
2. Fill in: Username, Password, Full Name, Email
3. Select Group: Admin or User
4. Click **Create User**

### Grant Access to Content (for User group)
1. Manage Users → **Manage Permissions**
2. Select tab: Maps / Dashboards / Documents / HTML Pages
3. Click **Edit** on any item
4. Check permissions:
   - ☑️ **View**: User can see it
   - ☑️ **Edit**: User can modify it
   - ☑️ **Delete**: User can delete it
5. Click **Save Permissions**

### Edit a User
1. Manage Users → Click **pencil icon** on user row
2. Update details
3. Leave password blank to keep current password
4. Click **Save Changes**

### Change Password
**For yourself**: Manage Users → Edit your own user → Enter new password

**For others (admin only)**: Manage Users → Edit user → Enter new password

### Deactivate a User
1. Manage Users → Edit user
2. Uncheck **Active** checkbox
3. Save

## Permission Levels

| Permission | What It Does |
|-----------|--------------|
| **View** | User sees the item in library and can open it |
| **Edit** | User can modify the item |
| **Delete** | User can delete the item |

**Note**: Users need View permission to see an item. Edit and Delete require View.

## Important Notes

✅ **Admin group** has automatic full access - no need to set permissions

✅ **User group** starts with NO access - must grant permissions per item

✅ **Cannot delete yourself** - prevents accidental lockout

✅ **Passwords are hashed** - stored securely in database

✅ **Sessions expire on logout** - always logout when done

## File Locations

| File | Purpose |
|------|---------|
| `login.php` | Login page |
| `users.php` | User management (admin only) |
| `index.php` | Main library (filtered by permissions) |
| `incl/Auth.php` | Authentication functions |
| `incl/Database.php` | Database functions including user/permission management |

## Database Tables

| Table | Contents |
|-------|----------|
| `users` | User accounts and credentials |
| `groups` | Admin and User groups |
| `permissions` | Item-level access control |

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Can't login with admin/admin | Verify migration ran: `SELECT * FROM users;` |
| Users see nothing | Grant permissions via Manage Permissions |
| Changes not showing | Logout and login again |
| Forgot admin password | Reset via database or create new admin user |

## Security Checklist

- [ ] Run the migration
- [ ] Login as admin
- [ ] Change default admin password
- [ ] Create additional user accounts
- [ ] Set appropriate permissions for User group
- [ ] Test with a User account
- [ ] Document passwords securely

## SQL Commands (if needed)

### Reset admin password to "admin"
```sql
UPDATE users 
SET password_hash = '$2y$10$6ZfN54B27A8JNIxWz8ZGq.8stjo4o61tzI3wHjRNRFr/osxrr.Sle' 
WHERE username = 'admin';
```

### Check all users
```sql
SELECT u.username, u.full_name, u.is_active, g.name as group_name 
FROM users u 
LEFT JOIN groups g ON u.group_id = g.id;
```

### View permissions for User group
```sql
SELECT p.item_type, p.item_id, p.can_view, p.can_edit, p.can_delete
FROM permissions p
JOIN groups g ON p.group_id = g.id
WHERE g.name = 'User';
```

### Remove all permissions for User group
```sql
DELETE FROM permissions 
WHERE group_id = (SELECT id FROM groups WHERE name = 'User');
```

## Getting Help

1. Check `AUTHENTICATION_SETUP.md` for detailed documentation
2. Review error messages in PHP error log
3. Check database with SQL queries above
4. Verify all files were updated correctly
