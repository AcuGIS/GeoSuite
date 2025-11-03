# Group Management Guide

## Overview

Your GIS application now supports creating custom user groups with individual permissions! This allows you to organize users into teams, departments, or roles, each with their own access levels to maps, dashboards, documents, and HTML pages.

## What's New

### ✨ Custom Group Creation
- Create as many groups as you need (Sales, Engineering, Management, Viewers, etc.)
- Each group has its own unique permissions
- Users inherit permissions from their assigned group

### 🔧 Group Management Features
- **Create** new groups with custom names and descriptions
- **Edit** group names and descriptions
- **Delete** empty groups (must reassign users first)
- View user count for each group
- Set different permissions for each group

## How to Use

### 1. Create a New Group

1. Go to **Manage Users** page
2. In the **Groups** section (right side), click **"Create Group"**
3. Enter:
   - **Group Name**: e.g., "Sales Team", "Viewers", "Engineering"
   - **Description**: What this group is for
4. Click **"Create Group"**

**Example Groups:**
- **Sales Team** - "Can view and edit sales-related maps and dashboards"
- **Engineering** - "Full access to technical documentation and dashboards"
- **Viewers** - "Read-only access to selected content"
- **Regional Managers** - "Access to region-specific maps and reports"

### 2. Edit a Group

1. In the Groups section, click the **pencil icon** next to any group
2. Update the name or description
3. Click **"Save Changes"**

**Note**: You can edit the Admin group, but it will always have full access to everything.

### 3. Delete a Group

1. Groups can only be deleted if they have **0 users**
2. First, reassign all users from that group to another group
3. Click the **trash icon** next to the group
4. Confirm deletion

**Warning**: Deleting a group removes all its permissions permanently.

### 4. Assign Users to Groups

1. When creating a user, select their group from the dropdown
2. To change a user's group:
   - Click **edit** on the user
   - Select a different group
   - Save changes
3. Users immediately inherit their new group's permissions

### 5. Set Permissions for a Group

1. In the Groups section, click **"Manage Permissions"** on any group
2. A modal opens with tabs for:
   - **Maps** - All your saved maps
   - **Dashboards** - All your dashboards
   - **Documents** - All uploaded documents
   - **HTML Pages** - All HTML pages
3. For each item, click **"Edit"** to set permissions:
   - ☑️ **Can View** - Group can see and open this item
   - ☑️ **Can Edit** - Group can modify this item  
   - ☑️ **Can Delete** - Group can delete this item
4. Click **"Save Permissions"**
5. Click **"Close"** when done

## Permission System Explained

### How Permissions Work

```
User Login → Check User's Group → Load Group Permissions → Filter Content
```

### Permission Levels

| Permission | What Users Can Do |
|-----------|------------------|
| **No Permission** | Cannot see the item at all |
| **View Only** | Can see and open, but not modify |
| **View + Edit** | Can see, open, and modify |
| **View + Edit + Delete** | Full control over the item |

**Important**: Users MUST have "View" permission to see an item. Edit and Delete require View to be checked.

### Admin Group Special Rules

- **Admin group** ALWAYS has full access to everything
- You don't need to set permissions for Admin group
- Admins can see and manage all content automatically
- Admins can access user management

### Regular Groups

- Start with NO access to any content
- Must explicitly grant permissions per item
- Each group can have different permissions
- Users inherit ALL permissions from their group

## Example Scenarios

### Scenario 1: Sales Team with Regional Access

**Goal**: Sales team should only see sales-related maps

1. Create group: "Sales Team"
2. Assign sales users to this group
3. Click "Manage Permissions" on Sales Team
4. Go to Maps tab
5. For "Sales Territory Map": Check View, Edit
6. For "Revenue Dashboard": Check View only
7. Save permissions

**Result**: Sales team sees only those 2 items, can edit the map but not the dashboard.

### Scenario 2: Read-Only Viewers

**Goal**: External viewers can see specific content but not edit anything

1. Create group: "External Viewers"
2. Assign viewer accounts to this group
3. Click "Manage Permissions"
4. For selected items: Check ONLY "View" permission
5. Leave Edit and Delete unchecked

**Result**: Viewers can open and view content but cannot modify anything.

### Scenario 3: Department-Based Access

**Goal**: Different departments see different content

1. Create groups:
   - "Engineering" - Technical docs and dashboards
   - "Marketing" - Marketing materials and reports
   - "Finance" - Financial dashboards
2. Assign users to appropriate groups
3. Set different permissions for each group:
   - Engineering: Technical items
   - Marketing: Marketing items
   - Finance: Financial items

**Result**: Each department only sees their relevant content.

### Scenario 4: Hierarchical Access

**Goal**: Managers see more than regular staff

1. Create groups:
   - "Staff" - Basic access
   - "Managers" - Extended access
   - "Executives" - Wide access
2. Set increasing permissions:
   - Staff: View only on basic maps
   - Managers: View + Edit on department maps
   - Executives: View on all non-sensitive content

**Result**: Natural hierarchy based on role.

## Best Practices

### Organizing Groups

✅ **DO:**
- Create groups based on job roles or departments
- Use clear, descriptive names ("Sales Team" not "Group1")
- Write helpful descriptions
- Review permissions regularly
- Start with restrictive permissions, add as needed

❌ **DON'T:**
- Create too many groups (becomes hard to manage)
- Give everyone Admin access
- Leave descriptions blank
- Set permissions and forget about them

### Managing Permissions

✅ **DO:**
- Grant minimum necessary permissions (principle of least privilege)
- Document why each group has certain access
- Review permissions when content changes
- Test with a non-admin account
- Inform users of their access level

❌ **DON'T:**
- Give Edit/Delete to everyone
- Set all permissions to all groups
- Forget to set View permission
- Assume users know what they can access

### User Assignment

✅ **DO:**
- Assign users to the most appropriate group
- Move users between groups as their role changes
- Keep Admin group small
- Regularly audit group membership

❌ **DON'T:**
- Make everyone an Admin
- Create a new group for each user
- Leave users in wrong groups

## Troubleshooting

### Users Can't See Content

**Problem**: User logs in but sees no content

**Solutions**:
1. Check their group assignment
2. Verify group has permissions set
3. Ensure "View" permission is checked
4. Check if content exists (admins should see it)

### Can't Delete Group

**Problem**: "Cannot delete group" error

**Solution**: Group has users assigned. First:
1. Go to Users list
2. Find users in that group
3. Edit each user and change their group
4. Then delete the empty group

### Permissions Not Working

**Problem**: Set permissions but user still can't access

**Solutions**:
1. User must logout and login again
2. Check you set permissions for correct group
3. Verify user is in the right group
4. Ensure View permission is checked (required for access)

### Too Many Groups

**Problem**: Hard to manage many groups

**Solution**: Consolidate groups:
1. Identify groups with similar permissions
2. Merge them into one group
3. Reassign users
4. Delete unused groups

## Quick Reference

### Group Management Locations

| Task | Where | Button/Link |
|------|-------|-------------|
| Create Group | Users page → Groups section | "Create Group" |
| Edit Group | Users page → Groups section | Pencil icon |
| Delete Group | Users page → Groups section | Trash icon |
| Manage Permissions | Users page → Groups section | "Manage Permissions" |
| Assign User to Group | Users page → Edit User | Group dropdown |

### Permission Quick Guide

```
                    NO ACCESS
                       ↓
                  Can View (see it)
                       ↓
              Can View + Edit (modify it)
                       ↓
        Can View + Edit + Delete (full control)
```

### Group Types

| Group | Access Level | Use Case |
|-------|-------------|----------|
| **Admin** | Everything | System administrators |
| **Custom Groups** | As configured | Teams, departments, roles |

## Migration from Old System

If you were using the system before group management:

1. **Old "User" group still exists** - All existing non-admin users are in it
2. **Existing permissions preserved** - User group permissions unchanged
3. **Create new groups** for better organization
4. **Gradually migrate users** to new groups
5. **Set appropriate permissions** for new groups
6. **Test thoroughly** before moving all users

## Security Notes

🔒 **Important Security Practices**:

1. **Admin Access**: Only give Admin to trusted users
2. **Regular Audits**: Review group membership quarterly
3. **Permission Review**: Check permissions when adding new content
4. **Deactivate Users**: Use "Active" checkbox instead of deleting immediately
5. **Document Changes**: Keep notes on why groups have certain access

## Summary

With custom group management, you now have:

✅ Unlimited custom groups  
✅ Per-group permissions on every item  
✅ Flexible user assignment  
✅ Easy permission management UI  
✅ Complete control over who sees what  

Your GIS application is now ready for team-based access control with as simple or complex a structure as you need!

## Need Help?

- Review this guide
- Check AUTHENTICATION_SETUP.md for technical details
- Test with non-admin accounts to verify permissions
- Start simple and add complexity as needed

Happy group management! 🎉

