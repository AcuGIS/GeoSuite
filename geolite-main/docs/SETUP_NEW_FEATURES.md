# Setup Instructions for Documents and HTML Pages Features

## Overview
Two new features have been added to your GeoLite application:
1. **Documents** - Upload and manage documents (PDF, DOCX, XLSX, TXT, etc.)
2. **HTML Pages** - Create custom HTML pages using a WYSIWYG editor

This will create two new tables:
- `documents` - Stores document metadata and file information
- `html_pages` - Stores custom HTML page content

**Important:** Make sure your web server has write permissions to the `uploads` directory.

## New Files Created

### PHP Files
- **documents.php** - Document management interface
- **view_document.php** - Document viewer/downloader
- **html_pages.php** - HTML page editor with WYSIWYG
- **view_html_page.php** - HTML page viewer

### Database Files
- **add_documents_html_migration.sql** - Database migration script

### Updated Files
- **incl/Database.php** - Added database functions for documents and HTML pages
- **index.php** - Added sections to display documents and HTML pages

## Features

### Documents Page
- Upload documents (PDF, DOCX, XLSX, TXT, and more)
- Add title and description for each document
- Download documents
- Edit document metadata (title and description)
- Delete documents (removes both database entry and file)
- View document information (filename, size, upload date)
- File type icons for visual identification

### HTML Pages
- Create custom HTML pages using TinyMCE WYSIWYG editor
- Rich text formatting options
- Preview HTML before saving
- Edit existing HTML pages
- View HTML pages in a new tab
- Full HTML/CSS support

### Index Page Updates
- New navigation buttons for Documents and HTML Pages
- Display documents section with download links
- Display HTML pages section with view links
- Integrated with existing Maps and Dashboards sections

## Security Considerations

1. **File Uploads**: The document upload feature generates unique filenames to prevent conflicts and potential security issues.

2. **Authentication**: All pages require authentication using the existing authentication system.

3. **HTML Content**: The HTML pages feature allows users to create custom HTML. Since this is for authenticated users, XSS protection is handled at the authentication level.

4. **File Permissions**: Ensure the `uploads` directory has appropriate permissions (755 recommended).

## Usage

### Uploading Documents
1. Navigate to **Documents** from the main page
2. Fill in the title and description
3. Select a file to upload
4. Click "Upload Document"
5. The document will appear in the Documents section on the main page

### Creating HTML Pages
1. Navigate to **HTML Pages** from the main page
2. Enter a title and optional description
3. Use the WYSIWYG editor to create your HTML content
4. Click "Preview" to see how it will look
5. Click "Create Page" to save
6. The page will appear in the HTML Pages section on the main page

## Technical Details

### Supported File Types for Documents
The system supports all file types, but provides special icons for:
- PDF files
- Word documents (DOC, DOCX)
- Excel spreadsheets (XLS, XLSX)
- Text files (TXT)

### WYSIWYG Editor
The HTML pages feature uses TinyMCE 6, which provides:
- Rich text formatting
- Tables, lists, and media
- Source code editing
- Full HTML/CSS support
- No API key required (using free CDN version)

### Database Schema

**documents table:**
- id (SERIAL PRIMARY KEY)
- title (VARCHAR 255)
- description (TEXT)
- filename (VARCHAR 255) - Unique generated filename
- original_filename (VARCHAR 255) - Original upload filename
- file_size (INTEGER) - Size in bytes
- mime_type (VARCHAR 100) - File MIME type
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

**html_pages table:**
- id (SERIAL PRIMARY KEY)
- title (VARCHAR 255)
- description (TEXT)
- html_content (TEXT)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

## Troubleshooting

### "Failed to connect to database"
- Ensure the migration script has been run
- Check database credentials in `incl/Database.php`

### "Failed to upload file"
- Check that the `uploads` directory exists
- Verify write permissions on the `uploads` directory
- Check PHP upload settings (upload_max_filesize, post_max_size)

### "Permission denied" when uploading
- Run: `chmod 755 uploads` (or `chmod 775` if needed)
- Ensure the web server user owns or has write access to the directory

## Next Steps

You can now:
1. Upload important documents to share with your team
2. Create custom HTML pages for reports, dashboards, or documentation
3. Access all content from the main library page
4. Edit or delete content as needed

All features maintain the same beautiful UI design and user experience as your existing Maps and Dashboards features!
