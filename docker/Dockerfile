# theme_saec - Entorno Docker para Moodle 4.x (PHP 8.2 + Apache)
# Ubicacion: theme/saec/docker/Dockerfile
FROM php:8.2-apache

# ------------------------------------------------------------------
# Dependencias de sistema requeridas para compilar extensiones PHP
# ------------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libxslt1-dev \
    git \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------------
# Extensiones PHP obligatorias segun requisitos de Moodle 4.x
# ------------------------------------------------------------------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        mysqli \
        zip \
        soap \
        bcmath \
        exif \
        opcache

# ------------------------------------------------------------------
# Apache: habilitar mod_rewrite (requerido por Moodle .htaccess/clean URLs)
# ------------------------------------------------------------------
RUN a2enmod rewrite

# ------------------------------------------------------------------
# Directorio moodledata con permisos correctos para www-data
# ------------------------------------------------------------------
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata \
    && chmod -R 770 /var/www/moodledata

WORKDIR /var/www/html

EXPOSE 80
