FROM php:8.2-cli

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    libssl-dev \
    pkg-config \
    unzip \
    zip \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-install zip
RUN pecl install mongodb-1.19.0 && docker-php-ext-enable mongodb

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar archivos
COPY . /var/www/html/
WORKDIR /var/www/html

# Instalar dependencias PHP
RUN composer install --no-interaction --optimize-autoloader --ignore-platform-req=ext-mongodb

EXPOSE 80

# Usar el servidor built-in de PHP — sin nginx, sin fpm
CMD php -S 0.0.0.0:${PORT:-80} -t /var/www/html
