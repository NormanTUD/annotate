FROM php:apache

# Enable the Apache rewrite module
RUN a2enmod rewrite

# Set the port for Apache to listen on
ENV APACHE_PORT 8080
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Install necessary dependencies
RUN apt-get update
RUN apt-get install -y libssl-dev iproute2 iputils-ping
RUN docker-php-ext-install mysqli
RUN rm -rf /var/lib/apt/lists/*

# Copy the PHP files to the container
COPY . $APACHE_DOCUMENT_ROOT/
COPY .env /var/www/html/.env

# Configure Apache
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN cat .env | grep "DB_PASSWORD" | sed -e 's#.*=##' >> /etc/dbpw
RUN cat .env | grep "DB_USER" | sed -e 's#.*=##' >> /etc/dbuser
RUN cat .env | grep "DB_HOST" | sed -e 's#.*=##' >> /etc/dbhost
RUN cat .env | grep "DB_PORT" | sed -e 's#.*=##' >> /etc/dbport

RUN rm .env

# Expose the Apache port
EXPOSE $APACHE_PORT

RUN chmod 777 -R /tmp && chmod o+t -R /tmp

# Start Apache server
CMD ["apache2-foreground"]
