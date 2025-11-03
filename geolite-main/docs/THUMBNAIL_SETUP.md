# Thumbnail Feature Setup Guide

## Overview
The thumbnail feature allows users to upload and display thumbnail images for maps, dashboards, documents, and HTML pages in the GeoLite application.

## Features

### Thumbnail Upload
- Click the "Thumbnail" button on any card in the index page
- Select an image file (JPG, PNG, GIF, or WebP)
- Preview the image before uploading
- Upload the thumbnail (max 5MB)
- The page will automatically reload to show the new thumbnail

### Thumbnail Display
- Thumbnails appear below the card header
- If no thumbnail is set, a placeholder icon is displayed
- Thumbnails are displayed at 150px height with cover fit

### Supported Formats
- JPEG/JPG
- PNG
- GIF
- WebP

### Size Limits
- Maximum file size: 5MB
- Recommended dimensions: 400x300 pixels or similar aspect ratio

## Files Modified/Created

### New Files:
1. `add_thumbnails_migration.sql` - Database migration script
2. `upload_thumbnail.php` - Thumbnail upload handler
3. `THUMBNAIL_SETUP.md` - This setup guide

### Modified Files:
1. `incl/Database.php` - Added thumbnail support to database functions
2. `index.php` - Added thumbnail display and upload UI

## Troubleshooting

### Upload Fails
- Check that `uploads/thumbnails` directory exists
- Verify write permissions on the directory
- Check PHP upload settings in `php.ini`:
  - `upload_max_filesize = 5M`
  - `post_max_size = 8M`
  - `file_uploads = On`

### Thumbnails Not Displaying
- Clear browser cache
- Check file path in database
- Verify image file exists in `uploads/thumbnails/`

### Database Errors
- Ensure migration was run successfully
- Check that thumbnail columns exist in tables
- Verify database connection settings in `incl/Database.php`

## Security Notes

- File type validation is performed on the server side
- File sizes are limited to 5MB
- Only image files (JPEG, PNG, GIF, WebP) are accepted
- Files are renamed with unique IDs to prevent conflicts
- Authentication is required to upload thumbnails

## Future Enhancements

Potential improvements for future versions:
- Image cropping/resizing on upload
- Multiple thumbnails per item
- Thumbnail generation from map screenshots
- Drag-and-drop upload interface
- Delete thumbnail functionality
