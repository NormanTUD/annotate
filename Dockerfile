FROM php:apache

# Enable the Apache rewrite module
RUN a2enmod rewrite

# Set the port for Apache to listen on
ENV APACHE_PORT 8080
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Install dependencies including sudo and Docker prerequisites
RUN DEBIAN_FRONTEND=noninteractive apt-get update && \
	apt-get remove --purge man-db && \
	apt-get install -y apt-utils libssl-dev iproute2 iputils-ping \
	build-essential curl libgl1 libglib2.0-0 git python3 python3-pip python3-dev \
	python3 python3-pip zip libjpeg-dev libpng-dev libfreetype6-dev \
	mariadb-client sudo curl gnupg lsb-release && \
	rm -rf /var/lib/apt/lists/* && \
	apt-get clean && apt-get autoclean && apt-get autoremove && rm -rf /var/lib/apt/lists/*

# Install Docker CLI
#RUN curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg && \
#    echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list && \
#    apt-get update && \
#    apt-get install -y docker-ce-cli && \
#    rm -rf /var/lib/apt/lists/*

# Allow www-data to run sudo without password
RUN echo "www-data ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/www-data

# PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN docker-php-ext-configure gd --with-jpeg && docker-php-ext-install gd
RUN docker-php-ext-configure exif && docker-php-ext-install exif

RUN pip3 install --break-system-packages --ignore-installed imagehash

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

RUN groupadd -f docker
RUN usermod -aG docker www-data

ARG INSTANCE_NAME
RUN echo "${INSTANCE_NAME}_mariadb" > /etc/dbhost

RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed jax
RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed tensorflowjs
RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed ultralytics
RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed onnx
RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed onnx2tf sng4onnx
RUN python3 -m pip install --no-cache-dir --progress-bar=off --break-system-packages --ignore-installed onnxslim onnxruntime ai-edge-litert
#RUN python3 -m pip install --no-cache-dir --break-system-packages --ignore-installed 'sng4onnx>=1.0.1' 'onnx_graphsurgeon>=0.3.26' 'ai-edge-litert>=1.2.0' 'onnx>=1.12.0,<=1.19.1' 'onnx2tf>=1.26.3' 'onnxslim>=0.1.71' 'onnxruntime'

RUN sed -i 's|from jax.experimental.jax2tf import shape_poly|from jax._src.export import shape_poly|' /usr/local/lib/python3.11/site-packages/tensorflowjs/converters/jax_conversion.py || true

COPY . $APACHE_DOCUMENT_ROOT/

# Start Apache
CMD ["apache2-foreground"]
