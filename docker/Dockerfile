FROM php:8.2-apache

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install additional PHP extensions if needed
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite auth_digest headers

# Set working directory
WORKDIR /var/www/html

# Copy Apache configuration
COPY docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/next-app \
    && chown -R www-data:www-data /var/www/next-app \
    && chmod -R 775 /var/www/next-app \
    && mkdir -p /var/www/.npm \
    && chown -R www-data:www-data /var/www/.npm \
    && chmod -R 775 /var/www/.npm

EXPOSE 80

CMD ["apache2-foreground"]
