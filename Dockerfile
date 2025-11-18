FROM php:apache

# Enable the Apache rewrite module
RUN a2enmod rewrite

# Set the port for Apache to listen on
ENV APACHE_PORT 8080
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Install dependencies including sudo and Docker prerequisites
RUN DEBIAN_FRONTEND=noninteractive apt-get update && \
    apt-get install -y apt-utils libssl-dev iproute2 iputils-ping \
    python3 python3-pip zip libjpeg-dev libpng-dev libfreetype6-dev \
    mariadb-client sudo curl gnupg lsb-release && \
    rm -rf /var/lib/apt/lists/*

# Install Docker CLI
RUN curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg && \
    echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list && \
    apt-get update && \
    apt-get install -y docker-ce-cli && \
    rm -rf /var/lib/apt/lists/*

# Allow www-data to run sudo without password
RUN echo "www-data ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/www-data

# PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN docker-php-ext-configure gd --with-jpeg && docker-php-ext-install gd
RUN docker-php-ext-configure exif && docker-php-ext-install exif

RUN pip3 install --break-system-packages imagehash

EXPOSE $APACHE_PORT

RUN chmod 777 -R /tmp && chmod o+t -R /tmp

# Copy environment file
COPY .env /var/www/html/.env

# Adjust Apache config & extract DB credentials
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    grep "DB_PASSWORD" .env | sed -e 's#.*=##' >> /etc/dbpw && \
    grep "DB_USER" .env | sed -e 's#.*=##' >> /etc/dbuser && \
    grep "DB_HOST" .env | sed -e 's#.*=##' >> /etc/dbhost && \
    grep "DB_PORT" .env | sed -e 's#.*=##' >> /etc/dbport

RUN rm .env

# Copy PHP files
COPY . $APACHE_DOCUMENT_ROOT/

RUN groupadd -f docker
RUN usermod -aG docker www-data

ARG INSTANCE_NAME
RUN echo "${INSTANCE_NAME}_mariadb" > /etc/dbhost

# Start Apache
CMD ["apache2-foreground"]
