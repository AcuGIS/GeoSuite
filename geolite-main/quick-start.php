<?php
// Start output buffering to prevent header issues
ob_start();

// Include required files (no authentication required - public page)
require_once 'incl/const.php';
require_once 'incl/Auth.php';

// Flush output buffer
ob_end_flush();

// Set header variables for the include
$headerTitle = 'Quick Start';
$headerIcon = 'lightning-charge';
$headerSubtitle = 'Get started with GeoLite - Maps, Dashboards, Documents & HTML';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Start - GeoLite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Material Design Tokens */
        :root {
            --md-surface: #ffffff;
            --md-surface-variant: #f3f4f6;
            --md-outline: #d0d5dd;
            --md-on-surface: #1f2937;
            --md-on-surface-secondary: #6b7280;
            --md-primary: #3b82f6;
            --md-primary-container: #e0edff;
            --md-on-primary: #ffffff;
            --md-radius-lg: 16px;
            --md-radius-md: 10px;
            --elev-card: 0 8px 24px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.06);
        }

        body {
            background: var(--md-surface-variant);
            min-height: 100vh;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .guide-section {
            background: var(--md-surface);
            border-radius: var(--md-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--elev-card);
            border: 1px solid var(--md-outline);
        }

        .guide-section h2 {
            color: var(--md-on-surface);
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
        }

        .guide-section h2 i {
            color: var(--md-primary);
            font-size: 1.75rem;
        }

        .guide-section h3 {
            color: var(--md-on-surface);
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .guide-section p {
            color: var(--md-on-surface-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .guide-section ul, .guide-section ol {
            color: var(--md-on-surface-secondary);
            line-height: 1.8;
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .guide-section li {
            margin-bottom: 0.5rem;
        }

        .guide-section code {
            background: var(--md-surface-variant);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-size: 0.9em;
            color: var(--md-primary);
        }

        .info-box {
            background: var(--md-primary-container);
            border-left: 4px solid var(--md-primary);
            padding: 1rem;
            margin: 1rem 0;
            border-radius: var(--md-radius-md);
        }

        .info-box p {
            margin: 0;
            color: var(--md-on-surface);
        }

        .info-box strong {
            color: var(--md-primary);
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            background: var(--md-primary);
            color: white;
            border-radius: 50%;
            font-weight: 600;
            margin-right: 0.75rem;
            font-size: 0.9rem;
        }

        .permission-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        .permission-table th,
        .permission-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--md-outline);
        }

        .permission-table th {
            background: var(--md-surface-variant);
            font-weight: 600;
            color: var(--md-on-surface);
        }

        .permission-table td {
            color: var(--md-on-surface-secondary);
        }

        .badge-admin {
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-user {
            background: #6c757d;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'incl/header.php'; ?>

    <div class="container">
        <div class="guide-section">
            <h2><i class="bi bi-lightning-charge"></i> Welcome to GeoLite</h2>
            <p>This Quick Start guide will help you get started with creating Maps, Dashboards, Documents, and HTML pages in GeoLite, as well as managing users and permissions.</p>
            
            <div class="info-box">
                <p><strong>Note:</strong> Most creation and management features require Admin access. Regular users can view and interact with content that has been shared with them.</p>
            </div>
        </div>

        <!-- Creating Maps -->
        <div class="guide-section">
            <h2><i class="bi bi-map"></i> Creating Maps</h2>
            <p>Maps allow you to display geographic data from your GeoServer with customizable basemaps and interactive features.</p>
            
            <h3>Step-by-Step Guide</h3>
            <ol>
                <li>
                    <strong>Access Map Builder</strong><br>
                    Navigate to the Map Builder by clicking <strong>"New Resource" → "Map"</strong> in the header (Admin only), or go directly to <code>map_builder.php</code>.
                </li>
                <li>
                    <strong>Select Basemaps</strong><br>
                    Choose one or more basemaps:
                    <ul>
                        <li>OpenStreetMap (default open-source map)</li>
                        <li>Carto Light (light theme)</li>
                        <li>Carto Dark (dark theme)</li>
                    </ul>
                </li>
                <li>
                    <strong>Choose WMS Layers</strong><br>
                    Select WMS (Web Map Service) layers from your GeoServer. These layers will be displayed on top of your basemap.
                </li>
                <li>
                    <strong>Enable Features</strong><br>
                    Configure optional map features:
                    <ul>
                        <li><strong>Popups:</strong> Show information when clicking on map features</li>
                        <li><strong>Zoom Buttons:</strong> Add zoom in/out controls</li>
                        <li><strong>Opacity Controls:</strong> Allow users to adjust layer transparency</li>
                    </ul>
                </li>
                <li>
                    <strong>Set Initial Extent</strong><br>
                    Optionally set the initial map view (center point and zoom level), or capture your current position.
                </li>
                <li>
                    <strong>Enter Map Details</strong><br>
                    Provide a <strong>Map Title</strong> (required) and an optional description to help identify your map.
                </li>
                <li>
                    <strong>Generate and Save</strong><br>
                    Click <strong>"Generate Map"</strong> to preview your map, then click <strong>"Save to Database"</strong> to save it.
                </li>
            </ol>

            <div class="info-box">
                <p><strong>Tip:</strong> After saving, your map will appear on the home page and can be viewed by users with appropriate permissions.</p>
            </div>
        </div>

        <!-- Creating Dashboards -->
        <div class="guide-section">
            <h2><i class="bi bi-speedometer2"></i> Creating Dashboards</h2>
            <p>Dashboards combine multiple widgets (maps, charts, tables, counters, and text) into a single interactive view for data visualization.</p>
            
            <h3>Step-by-Step Guide</h3>
            <ol>
                <li>
                    <strong>Access Dashboard Builder</strong><br>
                    Click <strong>"New Resource" → "Dashboard"</strong> in the header (Admin only), or navigate to <code>dashboard_builder.php</code>.
                </li>
                <li>
                    <strong>Add Widgets</strong><br>
                    Drag widgets from the sidebar onto the canvas:
                    <ul>
                        <li><strong>Map:</strong> Interactive map with GeoServer layers</li>
                        <li><strong>Chart:</strong> Data visualization (bar, line, pie charts)</li>
                        <li><strong>Table:</strong> Display tabular data</li>
                        <li><strong>Counter:</strong> Show counts, sums, or averages</li>
                        <li><strong>Text:</strong> Add descriptions and annotations</li>
                    </ul>
                </li>
                <li>
                    <strong>Configure Widgets</strong><br>
                    Click the gear icon (⚙️) on any widget to configure its settings, data source, and appearance.
                </li>
                <li>
                    <strong>Reposition Widgets</strong><br>
                    Drag widgets by their header to reposition them on the canvas.
                </li>
                <li>
                    <strong>Resize Widgets</strong><br>
                    Drag the bottom-right corner of any widget to resize it.
                </li>
                <li>
                    <strong>Save Dashboard</strong><br>
                    Click <strong>"Save Dashboard"</strong> and enter a title and description. Your dashboard will be saved and accessible from the Dashboard Library.
                </li>
            </ol>

            <div class="info-box">
                <p><strong>Tip:</strong> Dashboards can be exported as PDF for reports and presentations using the "Export PDF" button.</p>
            </div>
        </div>

        <!-- Creating HTML Pages -->
        <div class="guide-section">
            <h2><i class="bi bi-code-square"></i> Creating HTML Pages</h2>
            <p>HTML Pages allow you to create custom web pages with full HTML, CSS, and JavaScript support.</p>
            
            <h3>Step-by-Step Guide</h3>
            <ol>
                <li>
                    <strong>Access HTML Pages</strong><br>
                    Click <strong>"New Resource" → "HTML Page"</strong> in the header (Admin only), or navigate to <code>html_pages.php</code>.
                </li>
                <li>
                    <strong>Create New Page</strong><br>
                    Click the <strong>"Create New HTML Page"</strong> button.
                </li>
                <li>
                    <strong>Enter Page Details</strong><br>
                    Provide a title and description for your HTML page.
                </li>
                <li>
                    <strong>Write HTML Content</strong><br>
                    Enter your HTML, CSS, and JavaScript code in the editor. You have full control over the page structure and styling.
                </li>
                <li>
                    <strong>Save and Preview</strong><br>
                    Click <strong>"Save"</strong> to save your HTML page. You can then view it from the main library.
                </li>
            </ol>

            <div class="info-box">
                <p><strong>Tip:</strong> HTML pages can embed maps, charts, and other GeoLite resources using iframes or JavaScript APIs.</p>
            </div>
        </div>

        <!-- Creating Documents -->
        <div class="guide-section">
            <h2><i class="bi bi-file-earmark-text"></i> Creating Documents</h2>
            <p>Documents allow you to upload and share PDFs, Word documents, images, and other file types.</p>
            
            <h3>Step-by-Step Guide</h3>
            <ol>
                <li>
                    <strong>Access Documents</strong><br>
                    Click <strong>"New Resource" → "Document"</strong> in the header (Admin only), or navigate to <code>documents.php</code>.
                </li>
                <li>
                    <strong>Upload Document</strong><br>
                    Click the <strong>"Upload New Document"</strong> button.
                </li>
                <li>
                    <strong>Select File</strong><br>
                    Choose a file from your computer. Supported formats include PDF, Word documents, images, and more.
                </li>
                <li>
                    <strong>Enter Document Details</strong><br>
                    Provide a title and description for your document. This helps users identify the document in the library.
                </li>
                <li>
                    <strong>Save</strong><br>
                    Click <strong>"Upload"</strong> to save your document. It will appear in the library and can be viewed or downloaded by users with appropriate permissions.
                </li>
            </ol>

            <div class="info-box">
                <p><strong>Tip:</strong> Documents are stored securely and can be accessed by users based on their permission settings.</p>
            </div>
        </div>

        <!-- Managing Users -->
        <div class="guide-section">
            <h2><i class="bi bi-people"></i> Managing Users</h2>
            <p>User management allows administrators to create, edit, and manage user accounts in the system.</p>
            
            <h3>Accessing User Management</h3>
            <p>Only administrators can access user management. Navigate to it by:</p>
            <ol>
                <li>Logging in as an administrator</li>
                <li>Clicking <strong>"Administration" → "Manage Users"</strong> in the header</li>
                <li>Or navigating directly to <code>users.php</code></li>
            </ol>

            <h3>Creating a New User</h3>
            <ol>
                <li>Click the <strong>"Create User"</strong> button</li>
                <li>Fill in the required information:
                    <ul>
                        <li><strong>Username:</strong> Unique identifier for login</li>
                        <li><strong>Password:</strong> User's password (stored securely)</li>
                        <li><strong>Full Name:</strong> Display name</li>
                        <li><strong>Email:</strong> User's email address</li>
                        <li><strong>Group:</strong> Select "Admin" or "User"</li>
                        <li><strong>Active:</strong> Check to enable the account</li>
                    </ul>
                </li>
                <li>Click <strong>"Create User"</strong> to save</li>
            </ol>

            <h3>Editing a User</h3>
            <ol>
                <li>Click the <strong>pencil icon</strong> (✏️) on any user row</li>
                <li>Update the user details as needed</li>
                <li><strong>To change password:</strong> Enter a new password, or leave blank to keep the current password</li>
                <li>Click <strong>"Save Changes"</strong></li>
            </ol>

            <h3>Deleting a User</h3>
            <ol>
                <li>Click the <strong>trash icon</strong> (🗑️) on any user row</li>
                <li>Confirm the deletion</li>
                <li><strong>Note:</strong> You cannot delete your own account</li>
            </ol>

            <h3>User Roles</h3>
            <table class="permission-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Access</th>
                        <th>Permissions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge-admin">Admin</span></td>
                        <td>Everything</td>
                        <td>Full access to all content and user management</td>
                    </tr>
                    <tr>
                        <td><span class="badge-user">User</span></td>
                        <td>Restricted</td>
                        <td>Only sees items explicitly granted by admins</td>
                    </tr>
                </tbody>
            </table>

            <div class="info-box">
                <p><strong>Important:</strong> Admin group has automatic full access - no need to set permissions. User group starts with NO access and must be granted permissions per item.</p>
            </div>
        </div>

        <!-- Managing Permissions -->
        <div class="guide-section">
            <h2><i class="bi bi-shield-check"></i> Managing Permissions</h2>
            <p>Permissions control what content users can view, edit, or delete. This is essential for securing your GeoLite installation.</p>
            
            <h3>Accessing Permission Management</h3>
            <ol>
                <li>Navigate to <strong>"Administration" → "Manage Users"</strong></li>
                <li>Click the <strong>"Manage Permissions"</strong> button or tab</li>
            </ol>

            <h3>Granting Permissions</h3>
            <p>To grant access to content for the User group:</p>
            <ol>
                <li>In the Permissions section, select the appropriate tab:
                    <ul>
                        <li><strong>Maps:</strong> Manage map permissions</li>
                        <li><strong>Dashboards:</strong> Manage dashboard permissions</li>
                        <li><strong>Documents:</strong> Manage document permissions</li>
                        <li><strong>HTML Pages:</strong> Manage HTML page permissions</li>
                    </ul>
                </li>
                <li>Find the item you want to share and click <strong>"Edit"</strong></li>
                <li>Check the appropriate permission boxes:
                    <ul>
                        <li>☑️ <strong>View:</strong> User can see the item in the library and open it</li>
                        <li>☑️ <strong>Edit:</strong> User can modify the item</li>
                        <li>☑️ <strong>Delete:</strong> User can delete the item</li>
                    </ul>
                </li>
                <li>Click <strong>"Save Permissions"</strong> to apply</li>
            </ol>

            <h3>Permission Levels</h3>
            <table class="permission-table">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>What It Does</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>View</strong></td>
                        <td>User sees the item in library and can open it</td>
                    </tr>
                    <tr>
                        <td><strong>Edit</strong></td>
                        <td>User can modify the item</td>
                    </tr>
                    <tr>
                        <td><strong>Delete</strong></td>
                        <td>User can delete the item</td>
                    </tr>
                </tbody>
            </table>

            <div class="info-box">
                <p><strong>Important Notes:</strong></p>
                <ul style="margin-top: 0.5rem;">
                    <li>Users need <strong>View</strong> permission to see an item</li>
                    <li><strong>Edit</strong> and <strong>Delete</strong> require <strong>View</strong> permission</li>
                    <li><strong>Admin</strong> group has automatic full access - no need to set permissions</li>
                    <li><strong>User</strong> group starts with NO access - must grant permissions per item</li>
                </ul>
            </div>
        </div>

        <!-- Getting Started -->
        <div class="guide-section">
            <h2><i class="bi bi-rocket-takeoff"></i> Getting Started</h2>
            <p>Ready to begin? Follow these steps to get started:</p>
            <ol>
                <li>
                    <strong>Login</strong><br>
                    If you haven't already, <a href="login.php">login to GeoLite</a> using your admin credentials.
                </li>
                <li>
                    <strong>Create Your First Resource</strong><br>
                    Start by creating a Map, Dashboard, Document, or HTML Page using the guides above.
                </li>
                <li>
                    <strong>Manage Users</strong><br>
                    Create additional user accounts and set appropriate permissions for them.
                </li>
                <li>
                    <strong>Share Content</strong><br>
                    Use the permission management system to share your content with specific users or groups.
                </li>
            </ol>

            <div class="info-box">
                <p><strong>Default Admin Credentials:</strong> Username: <code>admin</code>, Password: <code>geolite</code><br>
                <strong>⚠️ IMPORTANT:</strong> Change the default password immediately for security!</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

