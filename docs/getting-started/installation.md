# Installation Guide

## System Requirements

- **PHP** >= 7.4 (8.0+ recommended)
- **MySQL** or MariaDB
- **Composer** - Dependency management
- **Apache** or **Nginx** with URL rewriting

## Installation Steps

### 1. Install Composer Dependencies

```bash
composer install
```

This will install all required packages:

- PHPMailer - Email sending
- Monolog - Logging
- Carbon - Date/time manipulation
- Guzzle - HTTP client
- Flysystem - File storage
- Symfony components
- And more...

### 2. Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

### 3. Configure Your Environment

Edit `.env` and update these settings:

```env
APP_NAME="My Application"
APP_ENV=development
APP_DEBUG=true
APP_KEY=your_generated_key_here
APP_URL=http://localhost

DB_HOST=localhost
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
```

### 4. Create Database

Create a MySQL/MariaDB database:

```sql
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Set Permissions

```bash
chmod -R 775 storage/
chmod -R 775 public_html/
```

### 7. Start Development Server

```bash
php artisan serve
```

Visit `http://localhost:8000` in your browser!

## Web Server Configuration

### Apache

The framework includes an `.htaccess` file in the `public_html/` directory for Apache users.

Make sure `mod_rewrite` is enabled:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Nginx

Add this to your Nginx configuration:

```nginx
server {
    listen 80;
    server_name example.com;
    root /path/to/framework/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Verify Installation

Test your installation:

```bash
# List all Artisan commands
php artisan list

# Check routes
php artisan route:list

# View migration status
php artisan migrate:status
```

## Next Steps

- [Configuration Guide](configuration.md)
- [Directory Structure](directory-structure.md)
- [Build Your First Application](first-application.md)

## Troubleshooting

### Permission Errors

If you encounter permission errors:

```bash
sudo chown -R www-data:www-data storage/
sudo chown -R www-data:www-data public_html/
```

### Database Connection Failed

- Verify database credentials in `.env`
- Ensure MySQL service is running
- Check if database exists

### Composer Install Fails

Update Composer:

```bash
composer self-update
composer install
```

### Missing PHP Extensions

Install required extensions:

```bash
# Ubuntu/Debian
sudo apt-get install php-mbstring php-xml php-mysql php-curl

# CentOS/RHEL
sudo yum install php-mbstring php-xml php-mysqlnd php-curl
```
