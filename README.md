# Anne - Real-Time Chat Web Application

A modern, secure, real-time chat application built with PHP 8.0+, MySQL 8.0, and vanilla JavaScript. Designed for shared hosting environments.

![ANNE CHAT](https://yaratul.com/wp-content/uploads/2025/12/Gemini_Generated_Image_t3cjqet3cjqet3cj.png)

## Features

- **Multi-tier Authentication**: Guest users, registered users (verified/unverified), and admin users
- **Real-time Messaging**: Long-polling mechanism for instant message delivery
- **Email Verification**: Secure email verification workflow for registered users
- **Admin CMS**: Complete content management system for site settings, SEO, and user management
- **Responsive Design**: Mobile-first design that works on all devices
- **Security First**: OWASP Top 10 compliance, prepared statements, CSRF protection

## Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache or Nginx web server
- mod_rewrite (for Apache)
- Composer (for dependency management)

## Installation

1. **Upload Files**
   - Upload all files to your web server's public directory (public_html, www, or htdocs)

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Run Installation Script**
   - Navigate to `install.php` in your browser
   - Follow the installation wizard to:
     - Create database and tables
     - Configure database connection
     - Set up application settings
     - Configure SMTP for email

4. **Set Permissions**
   ```bash
   chmod 755 public_html
   chmod 644 public_html/*.php
   chmod 777 public_html/uploads
   chmod 600 .env
   ```

5. **Delete Installation File**
   - Remove or rename `install.php` after installation

## Configuration

Edit the `.env` file to configure:

- Database connection settings
- Application name and URL
- SMTP email settings
- Security parameters

## Default Admin Account

- **Username**: admin
- **Password**: admin123

**⚠️ IMPORTANT**: Change the admin password immediately after installation!

## Directory Structure

```
/
├── public_html/          # Public web files
│   ├── api/             # API endpoints
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript files
│   ├── uploads/          # User uploads
│   └── *.php             # Public pages
├── src/                  # Application source code
│   ├── config/           # Configuration classes
│   ├── models/            # Data models
│   ├── services/          # Business logic
│   ├── middleware/        # Request middleware
│   └── utils/             # Utility functions
├── database/             # Database schema
├── vendor/               # Composer dependencies
├── .env                  # Environment configuration
├── composer.json         # PHP dependencies
└── README.md             # This file
```

## Usage

### Guest Users
- Login with username and age
- View messages in read-only mode
- Session expires after 24 hours

### Registered Users
- Register with email and password
- Verify email address
- Full messaging capabilities after verification
- Profile management

### Admin Users
- Access admin dashboard
- Manage site settings
- View activity logs
- Manage users and messages

## API Endpoints

- `POST /api/register.php` - User registration
- `POST /api/login.php` - User login
- `POST /api/guest-login.php` - Guest login
- `POST /api/logout.php` - Logout
- `POST /api/messages.php` - Send message
- `GET /api/messages.php?action=poll` - Poll for new messages
- `GET /api/users.php?action=online` - Get online users
- `GET /api/settings.php` - Get settings (admin)
- `POST /api/settings.php` - Update settings (admin)

## Security Features

- Password hashing with bcrypt
- Prepared statements (SQL injection prevention)
- XSS protection (HTML escaping)
- CSRF token support
- Session security (HttpOnly, Secure cookies)
- Input validation and sanitization
- Rate limiting ready

## Browser Support

- Chrome (last 2 versions)
- Firefox (last 2 versions)
- Safari (last 2 versions)
- Edge (last 2 versions)

## License

This project is provided as-is for educational and commercial use.

## Support

For issues and questions, please refer to the documentation files:
- `Anne_Complete_System_Architecture.md`
- `Anne_Developer_Guidelines.md`

## Version

1.0.0 - Initial Release

