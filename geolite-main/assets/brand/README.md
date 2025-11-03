# Brand Assets Directory

This directory stores uploaded brand assets for the GeoLite application.

## Contents

This directory will contain:
- **Logo images**: Uploaded via the Settings page
- **Hero images**: Background images for the login page

## File Naming Convention

Files are automatically named with the following pattern:
```
{type}_{date}_{time}_{random}.{ext}

Examples:
- logo_20251020_095430_a3f2b9c1.png
- hero_20251020_095445_d7e8f2a4.jpg
```

## Supported Formats

- PNG (.png)
- JPEG (.jpg, .jpeg)
- WebP (.webp)
- SVG (.svg)

## Permissions

This directory must be writable by the web server to allow image uploads.

### Linux/Unix
```bash
chmod 775 assets/brand
chown www-data:www-data assets/brand  # Adjust user/group as needed
```

### Windows
The directory should have write permissions for the IIS/Apache user account.

## Security

- Files are validated for proper image format before upload
- Filenames are generated with random components to prevent guessing
- Only image files are allowed (validated by MIME type)

## Management

Images are managed through the Admin Settings page:
- **Upload**: Use the file input fields for Logo or Hero Image
- **Remove**: Check the "Remove current" checkbox and save
- **View**: Click the "View" link next to the upload field

Old images are automatically deleted when replaced or when removed via the checkbox.

