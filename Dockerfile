# Stage 1: Build React/Vite assets
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Serve PHP & Laravel (PHP 8.4)
FROM php:8.4-cli-alpine

# Install required system dependencies & PHP extensions for Laravel
RUN apk add --no-cache \
    oniguruma-dev \
    libxml2-dev \
    libpng-dev \
    libzip-dev \
    zip unzip git \
    && docker-php-ext-install pdo pdo_mysql mbstring xml bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy Laravel code & built React assets from Stage 1
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Install PHP production dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Set storage and cache permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 10000

# Cache config, run database migrations, and launch app on $PORT
CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]