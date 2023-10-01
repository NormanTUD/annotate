FROM php:apache

# Enable the Apache rewrite module
RUN a2enmod rewrite

# Set the port for Apache to listen on
ENV APACHE_PORT 8080
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Install necessary dependencies
RUN apt-get update && apt-get install -y \
    libssl-dev iproute2 \
    && rm -rf /var/lib/apt/lists/*

# Install the MongoDB extension using pecl
RUN pecl install mongodb && \
    docker-php-ext-enable mongodb

# Copy the PHP files to the container
COPY . $APACHE_DOCUMENT_ROOT/

COPY .env /var/www/html/.env
RUN chmod +x /var/www/html/.env

# Configure Apache
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Add the "extension=mongodb.so" directive to the PHP configuration
RUN echo "extension=mongodb.so" >> /usr/local/etc/php/php.ini

# Debugging step - Check the content of php.ini again
RUN cat /usr/local/etc/php/php.ini

# Expose the Apache port
EXPOSE $APACHE_PORT

# Start Apache server
CMD ["apache2-foreground"]
