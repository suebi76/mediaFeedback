FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install required PHP extensions for SQLite and Media Processing
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy Custom Apache Config (Optional, but good for DocumentRoot overriding if needed later)
# COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Set Working Directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Permissions
# Make the data directory writable. Wait, if it's mounted as a volume in docker-compose,
# permissions will be inherited from the host. But making the local copy writable is a good fallback.
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html/data && \
    chmod -R 775 /var/www/html/data

EXPOSE 80
