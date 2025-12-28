# New Features Added

## 1. Real-Time Status Indicators (Green/Red Dots)

### Features:
- **Green dot**: User is online
- **Red dot**: User is offline
- **Yellow dot**: User is away
- Status indicators always visible on user avatars
- Real-time updates every 10 seconds
- Smooth color transitions when status changes
- Works in both Online and Inbox tabs

### Implementation:
- Status indicators update automatically via `updateStatusIndicators()` function
- Uses efficient batch updates (fetches once, updates all indicators)
- Smooth CSS transitions for color changes
- Status stored in `data-status` attribute for tracking

## 2. Media Upload Support

### Supported File Types:
- **Images**: JPEG, PNG, GIF, WebP
- **Videos**: MP4, WebM, OGG, QuickTime
- **Documents**: PDF, DOC, DOCX, TXT
- Maximum file size: 5MB

### Features:
- File attachment button in message input
- Image preview before sending
- Video playback in chat
- File download links for documents
- Full-size image modal viewer
- File size display
- Remove file option before sending

### Implementation:
- New API endpoint: `/api/upload.php`
- Database fields added: `attachment_type`, `attachment_url`, `attachment_name`, `attachment_size`
- Files stored in `/uploads/messages/`
- Secure file validation and MIME type checking

## Database Migration

If you have an existing database, run:

```sql
ALTER TABLE `messages` 
ADD COLUMN `attachment_type` ENUM('image', 'video', 'file', 'none') DEFAULT 'none' AFTER `message_text`,
ADD COLUMN `attachment_url` VARCHAR(255) DEFAULT NULL AFTER `attachment_type`,
ADD COLUMN `attachment_name` VARCHAR(255) DEFAULT NULL AFTER `attachment_url`,
ADD COLUMN `attachment_size` INT UNSIGNED DEFAULT NULL AFTER `attachment_name`;
```

Or use: `database/migration_add_attachments.sql`

## File Permissions

```bash
chmod 777 public_html/uploads/messages
```

## Usage

### Sending Media:
1. Click the paperclip icon in the message input
2. Select an image, video, or document
3. Preview will appear (for images)
4. Optionally add text message
5. Click Send

### Viewing Media:
- **Images**: Click to view full-size in modal
- **Videos**: Play directly in chat
- **Files**: Click to download

## Status Indicator Behavior

- Updates automatically every 10 seconds
- Changes color smoothly when user comes online/goes offline
- Always visible (no hiding for offline users)
- Works across all user lists (Online tab, Inbox tab)

