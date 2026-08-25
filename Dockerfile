# ---- Stage 1: Composer dependencies ----
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---- Stage 2: Frontend build (Vite) ----
FROM node:20-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ---- Stage 3: Runtime image ----
FROM php:8.3-fpm-alpine

# System deps + PHP extensions needed by your composer.json
# - pdo_mysql: DB
# - gd, zip: maatwebsite/excel
# - bcmath, exif, pcntl: common Laravel needs
RUN apk add --no-cache \
        nginx \
        supervisor \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        bash \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

WORKDIR /var/www/html

# App code + vendor from build stage
COPY --from=vendor /app /var/www/html

# Compiled Vite assets (manifest.json + hashed js/css)
COPY --from=frontend /app/public/build /var/www/html/public/build

# Nginx + Supervisor config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Permissions Laravel needs for storage/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]# ---- Stage 1: Composer dependencies ----
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---- Stage 2: Frontend build (Vite) ----
FROM node:20-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ---- Stage 3: Runtime image ----
FROM php:8.3-fpm-alpine

# System deps + PHP extensions needed by your composer.json
# - pdo_mysql: DB
# - gd, zip: maatwebsite/excel
# - bcmath, exif, pcntl: common Laravel needs
RUN apk add --no-cache \
        nginx \
        supervisor \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        bash \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

WORKDIR /var/www/html

# App code + vendor from build stage
COPY --from=vendor /app /var/www/html

# Compiled Vite assets (manifest.json + hashed js/css)
COPY --from=frontend /app/public/build /var/www/html/public/build

# Nginx + Supervisor config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Permissions Laravel needs for storage/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]