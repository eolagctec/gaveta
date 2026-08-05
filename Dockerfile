# Dockerfile for Gaveta
FROM php:8.1-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    git \
    unzip \
    libssl-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip gd \
    && pecl install redis && docker-php-ext-enable redis \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy application files
COPY . /var/www/html/

# Ensure uploads dir exists and is writable
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80
CMD ["apache2-foreground"]
