# Step 1: Build the React Frontend from the frontend subfolder
FROM node:20 AS frontend-builder
WORKDIR /app
# Copy package files specifically from the frontend directory
COPY frontend/package*.json ./
RUN npm install
# Copy the rest of the frontend files
COPY frontend/ . 
RUN npm run build

# Step 2: Build the Laravel Production Runtime
FROM php:8.3-apache
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev zip unzip git libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql \
    && a2enmod rewrite

# Setup Apache Document Root to Laravel's public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .

# Copy the compiled assets from Vite's default output (dist) into Laravel's public folder
COPY --from=frontend-builder /app/dist ./public/dist

# Install Composer dependencies
RUN curl -sS https://getcomposer.org | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# Set permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
