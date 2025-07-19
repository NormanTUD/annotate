FROM php:apache

# Enable the Apache rewrite module
RUN a2enmod rewrite

# Set the port for Apache to listen on
ENV APACHE_PORT 8080
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Install necessary dependencies
RUN DEBIAN_FRONTEND=noninteractive apt-get update
RUN DEBIAN_FRONTEND=noninteractive apt-get install -y apt-utils libssl-dev iproute2 iputils-ping python3 python3-pip zip libjpeg-dev libpng-dev libfreetype6-dev

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN docker-php-ext-configure gd --with-jpeg && \
    docker-php-ext-install gd
RUN docker-php-ext-configure exif && \
	docker-php-ext-install exif

RUN pip3 install --break-system-packages imagehash
RUN rm -rf /var/lib/apt/lists/*

RUN apt install -y mariadb-client

EXPOSE $APACHE_PORT

RUN chmod 777 -R /tmp && chmod o+t -R /tmp

# Copy the PHP files to the container
COPY .env /var/www/html/.env

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    grep "DB_PASSWORD" .env | sed -e 's#.*=##' >> /etc/dbpw && \
    grep "DB_USER" .env | sed -e 's#.*=##' >> /etc/dbuser && \
    grep "DB_HOST" .env | sed -e 's#.*=##' >> /etc/dbhost && \
    grep "DB_PORT" .env | sed -e 's#.*=##' >> /etc/dbport

RUN rm .env

COPY . $APACHE_DOCUMENT_ROOT/

# Start Apache server
CMD ["apache2-foreground"]
