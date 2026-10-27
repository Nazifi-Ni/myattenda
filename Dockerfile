FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application source code
COPY . /var/www/html/

# Adjust Apache DocumentRoot and permissions
RUN chown -R www-data:www-data /var/www/html

# Support dynamic PORT environment variable (Render, Koyeb, Railway, Cloud Run)
CMD sh -c "sed -i 's/80/'\"\${PORT:-80}\"'/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf && apache2-foreground"
