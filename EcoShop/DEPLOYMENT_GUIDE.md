# EcoShop Deployment Guide

## Overview

This guide provides step-by-step instructions for deploying the EcoShop e-commerce application in production environments. The application requires PostgreSQL, PHP, and a web server.

## System Requirements

### Minimum Requirements
- **Operating System**: Linux (Ubuntu 20.04+ recommended), Windows Server, or macOS
- **PHP**: Version 8.1 or higher
- **PostgreSQL**: Version 14 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: 2GB RAM minimum, 4GB recommended
- **Storage**: 10GB available disk space
- **Network**: Internet connection for initial setup

### PHP Extensions Required
- pdo
- pdo_pgsql
- session
- json
- mbstring
- curl

## Production Deployment

### Step 1: Server Preparation

#### Ubuntu/Debian Systems
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y postgresql postgresql-contrib php php-fpm php-pgsql php-mbstring php-json php-curl nginx

# Start and enable services
sudo systemctl start postgresql nginx php8.1-fpm
sudo systemctl enable postgresql nginx php8.1-fpm
```

#### CentOS/RHEL Systems
```bash
# Update system packages
sudo yum update -y

# Install required packages
sudo yum install -y postgresql-server postgresql-contrib php php-fpm php-pgsql php-mbstring php-json php-curl nginx

# Initialize PostgreSQL
sudo postgresql-setup initdb

# Start and enable services
sudo systemctl start postgresql nginx php-fpm
sudo systemctl enable postgresql nginx php-fpm
```

### Step 2: Database Setup

#### Create Database and User
```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE ecoshop_db;
CREATE USER ecoshop_user WITH PASSWORD 'your_secure_password_here';
GRANT ALL PRIVILEGES ON DATABASE ecoshop_db TO ecoshop_user;
\q
```

#### Import Database Schema
```bash
# Navigate to application directory
cd /path/to/ecommerce-postgresql

# Import schema
PGPASSWORD=your_secure_password_here psql -h localhost -U ecoshop_user -d ecoshop_db -f database/ecommerce_postgresql_schema.sql
```

### Step 3: Application Deployment

#### Copy Application Files
```bash
# Create web directory
sudo mkdir -p /var/www/ecoshop

# Copy application files
sudo cp -r /path/to/ecommerce-postgresql/* /var/www/ecoshop/

# Set proper ownership
sudo chown -R www-data:www-data /var/www/ecoshop

# Set proper permissions
sudo chmod -R 755 /var/www/ecoshop
sudo chmod -R 644 /var/www/ecoshop/api/*.php
```

#### Configure Database Connection
```bash
# Edit configuration file
sudo nano /var/www/ecoshop/api/config.php

# Update database credentials:
# $host = 'localhost';
# $dbname = 'ecoshop_db';
# $username = 'ecoshop_user';
# $password = 'your_secure_password_here';
```

### Step 4: Web Server Configuration

#### Nginx Configuration
Create `/etc/nginx/sites-available/ecoshop`:
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/ecoshop;
    index index.html index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;

    # Main location
    location / {
        try_files $uri $uri/ =404;
    }

    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # API routes
    location /api/ {
        try_files $uri $uri/ =404;
    }

    # Admin panel
    location /admin/ {
        try_files $uri $uri/ =404;
    }

    # Static files caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security - deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /(database|config)/ {
        deny all;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/ecoshop /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### Apache Configuration
Create `/etc/apache2/sites-available/ecoshop.conf`:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/ecoshop

    <Directory /var/www/ecoshop>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set X-Content-Type-Options "nosniff"

    # PHP configuration
    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>

    # Deny access to sensitive directories
    <Directory /var/www/ecoshop/database>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/ecoshop_error.log
    CustomLog ${APACHE_LOG_DIR}/ecoshop_access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite ecoshop
sudo a2enmod rewrite headers
sudo systemctl reload apache2
```

### Step 5: SSL/HTTPS Setup (Recommended)

#### Using Let's Encrypt (Certbot)
```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Test automatic renewal
sudo certbot renew --dry-run
```

### Step 6: Security Hardening

#### PostgreSQL Security
```bash
# Edit PostgreSQL configuration
sudo nano /etc/postgresql/14/main/postgresql.conf

# Restrict connections
listen_addresses = 'localhost'

# Edit access control
sudo nano /etc/postgresql/14/main/pg_hba.conf

# Restart PostgreSQL
sudo systemctl restart postgresql
```

#### PHP Security
```bash
# Edit PHP configuration
sudo nano /etc/php/8.1/fpm/php.ini

# Recommended settings:
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

#### Firewall Configuration
```bash
# Enable UFW firewall
sudo ufw enable

# Allow necessary ports
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS

# Deny all other incoming connections
sudo ufw default deny incoming
sudo ufw default allow outgoing
```

### Step 7: Monitoring and Logging

#### Log File Locations
- **Nginx**: `/var/log/nginx/access.log`, `/var/log/nginx/error.log`
- **Apache**: `/var/log/apache2/access.log`, `/var/log/apache2/error.log`
- **PHP**: `/var/log/php_errors.log`
- **PostgreSQL**: `/var/log/postgresql/postgresql-14-main.log`

#### Log Rotation Setup
```bash
# Create logrotate configuration
sudo nano /etc/logrotate.d/ecoshop

# Add configuration:
/var/log/nginx/*log /var/log/apache2/*log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data adm
    postrotate
        systemctl reload nginx
        systemctl reload apache2
    endscript
}
```

## Development Environment Setup

### Using Docker (Recommended for Development)

#### Create docker-compose.yml
```yaml
version: '3.8'

services:
  web:
    image: php:8.1-apache
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_NAME=ecoshop_db
      - DB_USER=ecoshop_user
      - DB_PASS=ecoshop123

  db:
    image: postgres:14
    environment:
      POSTGRES_DB: ecoshop_db
      POSTGRES_USER: ecoshop_user
      POSTGRES_PASSWORD: ecoshop123
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./database/ecommerce_postgresql_schema.sql:/docker-entrypoint-initdb.d/init.sql
    ports:
      - "5432:5432"

volumes:
  postgres_data:
```

#### Start Development Environment
```bash
# Start containers
docker-compose up -d

# Access application at http://localhost:8000
```

### Local Development Setup

#### Using PHP Built-in Server
```bash
# Navigate to application directory
cd /path/to/ecommerce-postgresql

# Start PHP development server
php -S localhost:8000

# Access application at http://localhost:8000
```

## Backup and Recovery

### Database Backup
```bash
# Create backup
pg_dump -h localhost -U ecoshop_user -d ecoshop_db > ecoshop_backup_$(date +%Y%m%d_%H%M%S).sql

# Automated backup script
#!/bin/bash
BACKUP_DIR="/var/backups/ecoshop"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

pg_dump -h localhost -U ecoshop_user -d ecoshop_db > $BACKUP_DIR/ecoshop_$DATE.sql
gzip $BACKUP_DIR/ecoshop_$DATE.sql

# Keep only last 30 days of backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete
```

### Database Restore
```bash
# Restore from backup
gunzip -c ecoshop_backup_20250806_120000.sql.gz | psql -h localhost -U ecoshop_user -d ecoshop_db
```

### File System Backup
```bash
# Backup application files
tar -czf ecoshop_files_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/ecoshop

# Backup images directory
rsync -av /var/www/ecoshop/images/ /backup/ecoshop/images/
```

## Performance Optimization

### PHP Optimization
```bash
# Install and configure OPcache
sudo apt install php8.1-opcache

# Edit PHP configuration
sudo nano /etc/php/8.1/fpm/php.ini

# Add OPcache settings:
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### PostgreSQL Optimization
```bash
# Edit PostgreSQL configuration
sudo nano /etc/postgresql/14/main/postgresql.conf

# Recommended settings for production:
shared_buffers = 256MB
effective_cache_size = 1GB
maintenance_work_mem = 64MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
```

### Web Server Optimization

#### Nginx Optimization
```nginx
# Add to nginx.conf
worker_processes auto;
worker_connections 1024;

# Enable gzip compression
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

# Enable caching
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

## Troubleshooting

### Common Issues

#### Database Connection Errors
```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Check connection
psql -h localhost -U ecoshop_user -d ecoshop_db

# Check logs
sudo tail -f /var/log/postgresql/postgresql-14-main.log
```

#### PHP Errors
```bash
# Check PHP-FPM status
sudo systemctl status php8.1-fpm

# Check PHP error logs
sudo tail -f /var/log/php_errors.log

# Test PHP configuration
php -m | grep pdo
```

#### Web Server Issues
```bash
# Check Nginx status
sudo systemctl status nginx
sudo nginx -t

# Check Apache status
sudo systemctl status apache2
sudo apache2ctl configtest

# Check error logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/apache2/error.log
```

### Performance Issues
```bash
# Monitor system resources
htop
iostat -x 1
free -h

# Check database performance
sudo -u postgres psql -d ecoshop_db -c "SELECT * FROM pg_stat_activity;"

# Analyze slow queries
sudo -u postgres psql -d ecoshop_db -c "SELECT query, mean_time, calls FROM pg_stat_statements ORDER BY mean_time DESC LIMIT 10;"
```

## Maintenance

### Regular Maintenance Tasks

#### Daily
- Monitor error logs
- Check disk space usage
- Verify backup completion

#### Weekly
- Update system packages
- Review security logs
- Analyze performance metrics

#### Monthly
- Update application dependencies
- Review and rotate logs
- Test backup restoration
- Security audit

### Update Procedures

#### Application Updates
```bash
# Backup current version
cp -r /var/www/ecoshop /var/www/ecoshop_backup_$(date +%Y%m%d)

# Deploy new version
# ... copy new files ...

# Update database schema if needed
PGPASSWORD=password psql -h localhost -U ecoshop_user -d ecoshop_db -f updates/schema_update.sql

# Clear cache and restart services
sudo systemctl restart php8.1-fpm nginx
```

#### System Updates
```bash
# Update packages
sudo apt update && sudo apt upgrade -y

# Restart services if needed
sudo systemctl restart postgresql nginx php8.1-fpm
```

## Support and Documentation

### Log Analysis
- Monitor application logs regularly
- Set up log aggregation for multiple servers
- Use tools like ELK stack for advanced log analysis

### Monitoring Tools
- **System**: htop, iostat, netstat
- **Database**: pg_stat_activity, pg_stat_statements
- **Web**: nginx/apache status modules
- **Application**: Custom health check endpoints

### Documentation
- Keep deployment documentation updated
- Document any custom configurations
- Maintain change logs for updates

---

This deployment guide provides comprehensive instructions for setting up EcoShop in production environments. For specific deployment scenarios or advanced configurations, consult with your system administrator or DevOps team.

