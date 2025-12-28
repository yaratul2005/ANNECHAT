# Deployment Notes - Media & Status Features

## New Features Added

### 1. Real-Time Status Indicators
- **Green dot**: User is online
- **Red dot**: User is offline  
- **Yellow dot**: User is away
- Status updates automatically every 10 seconds
- Smooth color transitions when status changes

### 2. Media Upload Support
- **Images**: JPEG, PNG, GIF, WebP
- **Videos**: MP4, WebM, OGG
- **Documents**: PDF, DOC, DOCX, TXT
- Maximum file size: 5MB
- Files stored in `/uploads/messages/`

## Database Migration Required

If you already have the messages table, run this migration:

```sql
ALTER TABLE `messages` 
ADD COLUMN `attachment_type` ENUM('image', 'video', 'file', 'none') DEFAULT 'none' AFTER `message_text`,
ADD COLUMN `attachment_url` VARCHAR(255) DEFAULT NULL AFTER `attachment_type`,
ADD COLUMN `attachment_name` VARCHAR(255) DEFAULT NULL AFTER `attachment_url`,
ADD COLUMN `attachment_size` INT UNSIGNED DEFAULT NULL AFTER `attachment_name`;
```

Or use the migration file: `database/migration_add_attachments.sql`

## File Permissions

Ensure uploads directory is writable:
```bash
chmod 777 public_html/uploads/messages
```

## Security

- File uploads validated by MIME type
- File size limited to 5MB
- Only allowed file types accepted
- Script execution blocked in uploads directory via .htaccess

