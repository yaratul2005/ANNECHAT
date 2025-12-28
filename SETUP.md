# Anne Chat - Quick Setup Guide

## Prerequisites

Before installing Anne Chat, ensure you have:

1. **Web Server**: Apache or Nginx with PHP 8.0+ support
2. **Database**: MySQL 8.0+ or MariaDB 10.3+
3. **PHP Extensions**: 
   - PDO with MySQL driver
   - OpenSSL
   - mbstring
   - cURL
   - GD (for image processing)
   - json
   - filter
   - hash
4. **Composer**: PHP package manager (for dependencies)

## Installation Steps

### Step 1: Upload Files

Upload all project files to your web server's public directory:
- For cPanel: `public_html/`
- For Plesk: `httpdocs/`
- For Apache: `htdocs/` or `www/`

### Step 2: Install PHP Dependencies

Via SSH or terminal:
```bash
cd /path/to/anne
composer install
```

Or if you don't have SSH access, ensure `vendor/` directory is uploaded with all dependencies.

### Step 3: Set File Permissions

```bash
chmod 755 public_html
chmod 644 public_html/*.php
chmod 755 public_html/api
chmod 644 public_html/api/*.php
chmod 755 public_html/uploads
chmod 777 public_html/uploads/profiles
chmod 600 .env (after creation)
```

### Step 4: Create Database

1. Log into your hosting control panel (cPanel/Plesk)
2. Go to MySQL Databases
3. Create a new database (e.g., `anne_chat`)
4. Create a database user and assign all privileges
5. Note down the database credentials

### Step 5: Run Installation Wizard

1. Navigate to `http://yourdomain.com/install.php`
2. Follow the installation steps:
   - **Step 1**: Welcome screen
   - **Step 2**: Enter database credentials
   - **Step 3**: Configure application settings and SMTP
   - **Step 4**: Installation complete

### Step 6: Configure SMTP (Optional but Recommended)

For email verification to work, configure SMTP settings:

**Gmail Example:**
- SMTP Host: `smtp.gmail.com`
- SMTP Port: `587`
- SMTP User: Your Gmail address
- SMTP Password: App-specific password (not your regular password)

**Other Providers:**
- Check your email provider's SMTP settings
- Common ports: 587 (TLS), 465 (SSL)

### Step 7: Secure Installation

After successful installation:
1. **Delete or rename** `install.php` file
2. Verify `.env` file has 600 permissions
3. Test login with default admin account
4. **Change admin password immediately**

## Default Credentials

**Admin Account:**
- Username: `admin`
- Password: `admin123`

⚠️ **CHANGE THIS IMMEDIATELY AFTER INSTALLATION!**

## Post-Installation Checklist

- [ ] Delete `install.php`
- [ ] Change admin password
- [ ] Test user registration
- [ ] Test email verification
- [ ] Test guest login
- [ ] Test messaging functionality
- [ ] Configure SSL/HTTPS
- [ ] Set up regular backups

## Troubleshooting

### Database Connection Error
- Verify database credentials in `.env`
- Check database user has proper permissions
- Ensure database exists

### Email Not Sending
- Verify SMTP credentials
- Check firewall allows SMTP ports
- Test with different email provider
- Check spam folder

### Permission Errors
- Verify file permissions are set correctly
- Check uploads directory is writable (777)
- Ensure .env file is readable (600)

### Session Issues
- Check PHP session directory is writable
- Verify session configuration in php.ini
- Clear browser cookies and try again

## Support

For detailed documentation, refer to:
- `Anne_Complete_System_Architecture.md`
- `Anne_Developer_Guidelines.md`
- `README.md`

