#!/bin/bash
cat > /etc/apache2/sites-available/000-default.conf << 'APACHE_CONFIG'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.html index.php
    </Directory>

    Alias /backend /var/www/html/backend
    
    <Directory /var/www/html/backend>
        Options FollowSymLinks ExecCGI
        AllowOverride All
        Require all granted
        AddHandler application/x-httpd-php .php
        DirectoryIndex index.php
    </Directory>

    <IfModule mod_headers.c>
        Header always set Access-Control-Allow-Origin "*"
        Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
        Header always set Access-Control-Allow-Headers "Content-Type, Authorization"
    </IfModule>
    
    <IfModule mod_mime.c>
        AddType audio/mpeg mp3
        AddType audio/ogg ogg
        AddType audio/wav wav
    </IfModule>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
APACHE_CONFIG

ln -sf /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-enabled/000-default.conf
apache2ctl configtest
apache2ctl graceful



