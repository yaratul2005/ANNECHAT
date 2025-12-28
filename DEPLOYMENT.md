# Deployment Guide - Anne Chat

This guide will help you deploy Anne Chat to a production server.

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.3+)
- Apache web server with mod_rewrite enabled
- Composer (for dependency management)
- SSL certificate (recommended for production)

## Step 1: Server Setup

### 1.1 Upload Files

Upload all files to your web server's public directory (usually `public_html`, `www`, or `htdocs`).

```
your-server/
├── public_html/          (or www/ or htdocs/)
│   ├── api/
│   ├── css/
│   ├── js/
│   ├── uploads/
│   ├── index.php
│   ├── dashboard.php
│   ├── profile.php
│   └── ...
├── src/
├── database/
├── vendor/
├── .env
└── composer.json
```

### 1.2 Set Permissions

Set proper file permissions:

```bash
# Make uploads directory writable
chmod -R 755 public_html/uploads/
chmod -R 755 public_html/uploads/messages/
chmod -R 755 public_html/uploads/profile/
chmod -R 755 public_html/uploads/posts/

# Set ownership (adjust user:group as needed)
chown -R www-data:www-data public_html/uploads/
```

## Step 2: Database Setup

### 2.1 Create Database

Create a MySQL database and user:

```sql
CREATE DATABASE anne_chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'anne_chat_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON anne_chat.* TO 'anne_chat_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2.2 Run Migrations

Run all SQL migration files in order from the `database/` directory:

1. `migration_initial.sql` (if exists)
2. `migration_add_is_banned.sql`
3. `migration_add_profile_fields.sql`
4. `migration_add_posts.sql`
5. `migration_add_comments.sql` (if exists)
6. `migration_add_reactions.sql` (if exists)

You can run them via phpMyAdmin, MySQL command line, or your preferred database tool.

## Step 3: Configuration

### 3.1 Environment Variables

Create a `.env` file in the project root (copy from `.env.example` if available):

```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=anne_chat
DB_USER=anne_chat_user
DB_PASSWORD=your_secure_password

# Application
APP_NAME=Anne Chat
APP_URL=https://yourdomain.com
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_KEY=your_random_32_character_key_here

# SMTP (for email verification)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=noreply@yourdomain.com
SMTP_FROM_NAME=Anne Chat

# Security
BCRYPT_COST=12
SESSION_TIMEOUT=1800

# File Upload
MAX_FILE_SIZE=5242880
ALLOWED_IMAGE_TYPES=jpg,jpeg,png,gif,webp
```

**Important Security Notes:**
- Set `APP_DEBUG=false` in production
- Generate a secure `APP_KEY` (32+ random characters)
- Use strong database passwords
- Never commit `.env` to version control

### 3.2 Apache Configuration

Ensure `.htaccess` is working. The file should be in `public_html/.htaccess`.

If mod_rewrite is not enabled, enable it:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 3.3 PHP Configuration

Update `php.ini` settings (or use `.htaccess`):

```ini
upload_max_filesize = 5M
post_max_size = 5M
max_execution_time = 30
memory_limit = 128M
```

## Step 4: Install Dependencies

If using Composer dependencies:

```bash
cd /path/to/your/project
composer install --no-dev --optimize-autoloader
```

## Step 5: Security Hardening

### 5.1 Protect Sensitive Files

Ensure `.htaccess` blocks access to:
- `.env`
- `composer.json` / `composer.lock`
- `bootstrap.php`
- `.git/` directory

### 5.2 File Permissions

```bash
# Restrict access to sensitive files
chmod 600 .env
chmod 644 public_html/.htaccess
```

### 5.3 SSL Certificate

Install an SSL certificate (Let's Encrypt is free):

```bash
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

## Step 6: Admin Access

Admin panel is accessible at:
- **URL**: `https://yourdomain.com/admin`
- **Login**: Use admin credentials created during setup

**Note**: Admin panel is hidden from regular users and only accessible via the `/admin` path.

## Step 7: Testing

### 7.1 Test Basic Functionality

1. Visit `https://yourdomain.com`
2. Register a new account
3. Login
4. Send a message
5. Upload a profile picture
6. Create a post
7. Test guest login

### 7.2 Test Admin Panel

1. Visit `https://yourdomain.com/admin`
2. Login with admin credentials
3. Test user management
4. Test settings

## Step 8: Performance Optimization

### 8.1 Enable Caching

Add to `.htaccess`:

```apache
# Browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### 8.2 Enable Compression

Already included in `.htaccess`:

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
```

## Step 9: Monitoring & Maintenance

### 9.1 Error Logging

Check PHP error logs:
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/php/error.log
```

### 9.2 Database Backups

Set up regular database backups:

```bash
# Daily backup script
mysqldump -u anne_chat_user -p anne_chat > backup_$(date +%Y%m%d).sql
```

### 9.3 Update Procedure

1. Backup database
2. Backup files
3. Upload new files
4. Run any new migrations
5. Clear cache (if applicable)
6. Test functionality

## Troubleshooting

### Issue: 500 Internal Server Error

- Check PHP error logs
- Verify `.htaccess` syntax
- Check file permissions
- Verify database connection in `.env`

### Issue: Images not uploading

- Check `uploads/` directory permissions (must be writable)
- Verify `MAX_FILE_SIZE` in `.env` and `php.ini`
- Check PHP `upload_max_filesize` and `post_max_size`

### Issue: Admin panel not accessible

- Verify `.htaccess` rewrite rules
- Check that `admin-login.php` exists
- Verify admin user exists in database

### Issue: Database connection failed

- Verify database credentials in `.env`
- Check database server is running
- Verify user has proper permissions
- Check firewall rules

## Support

For issues or questions:
1. Check error logs
2. Verify all configuration steps
3. Review this deployment guide
4. Check database migrations are complete

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong database password
- [ ] SSL certificate installed
- [ ] `.env` file protected (not web-accessible)
- [ ] File upload directory permissions set correctly
- [ ] Admin panel accessible only via `/admin`
- [ ] Regular database backups configured
- [ ] Error logging enabled
- [ ] Security headers configured in `.htaccess`

---

**Congratulations!** Your Anne Chat application should now be deployed and ready for use.

