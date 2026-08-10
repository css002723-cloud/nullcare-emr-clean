# Step 1: Build the React Frontend
FROM node:20 AS frontend-builder
WORKDIR /app
COPY frontend/package*.json ./
RUN npm install
COPY frontend/ . 
RUN npm run build

# Step 2: Set up the Runtime Environment
FROM php:8.3-apache
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev zip unzip git libpq-dev nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql \
    && a2enmod rewrite

# Setup Apache Document Root to Laravel's public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure Apache to pass API requests to Laravel and forward everything else to the React frontend
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    \n\
    # Proxy API requests directly to Laravel\n\
    ProxyPassMatch ^/api/(.*)$ http://localhost:8000/api/$1\n\
    ProxyPassMatch ^/deploy-database-secure-xyz$ http://localhost:8000/deploy-database-secure-xyz\n\
    \n\
    # Serve React Static Files for everything else\n\
    Alias / /var/www/html/frontend-dist/\n\
    <Directory /var/www/html/frontend-dist/>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
        FallbackResource /index.html\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf
RUN a2enmod proxy proxy_http

WORKDIR /var/www/html
COPY . .

# Copy compiled React build assets to a dedicated static directory
COPY --from=frontend-builder /app/dist ./frontend-dist

# Install Composer dependencies (use official installer)
# Increase memory limit for composer in case of large installs
ENV COMPOSER_MEMORY_LIMIT=-1
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php

RUN composer install --no-dev --no-interaction --optimize-autoloader
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80 and start both servers side-by-side
EXPOSE 80
CMD php artisan serve --host=0.0.0.0 --port=8000 & apache2-foreground
