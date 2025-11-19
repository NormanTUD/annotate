FROM php:apache

# Enable the Apache rewrite module
RUN a2enmod rewrite

# Set the port for Apache to listen on
ENV APACHE_PORT 8080
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Install dependencies including sudo and Docker prerequisites
RUN DEBIAN_FRONTEND=noninteractive apt-get update && \
	apt-get remove --purge man-db && \
	apt-get install -y apt-utils libssl-dev iproute2 iputils-ping uuid-runtime \
	build-essential curl libgl1 libglib2.0-0 git python3 python3-pip python3-dev \
	python3 python3-pip zip libjpeg-dev libpng-dev libfreetype6-dev \
	mariadb-client sudo curl gnupg lsb-release && \
	rm -rf /var/lib/apt/lists/* && \
	apt-get clean && apt-get autoclean && apt-get autoremove && rm -rf /var/lib/apt/lists/*

RUN echo "www-data ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/www-data

# PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN docker-php-ext-configure gd --with-jpeg && docker-php-ext-install gd
RUN docker-php-ext-configure exif && docker-php-ext-install exif

RUN pip3 install --break-system-packages --ignore-installed imagehash

RUN groupadd -f docker
RUN usermod -aG docker www-data

ARG INSTANCE_NAME
RUN echo "${INSTANCE_NAME}_mariadb" > /etc/dbhost

RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed jax
RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed tensorflowjs
RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed ultralytics
RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed onnx2tf sng4onnx onnx_graphsurgeon onnx
RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed onnxslim onnxruntime ai-edge-litert || true

RUN sed -i 's|from jax.experimental.jax2tf import shape_poly|from jax._src.export import shape_poly|' /usr/local/lib/python3.11/site-packages/tensorflowjs/converters/jax_conversion.py || true

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

COPY . $APACHE_DOCUMENT_ROOT/

# Start Apache
CMD ["apache2-foreground"]
