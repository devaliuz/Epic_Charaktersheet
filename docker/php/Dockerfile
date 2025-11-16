FROM php:8.2-apache

# Systemabhängigkeiten installieren
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP-Extensions (PostgreSQL + übliche)
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Apache Module
RUN a2enmod rewrite headers mime

WORKDIR /var/www/html

# Apache vhost config wird per Volume gemountet (siehe docker-compose)

# Composer (optional)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

EXPOSE 80
CMD ["apache2-foreground"]


