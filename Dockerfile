FROM python:3.11-slim

ENV DEBIAN_FRONTEND=noninteractive
ENV APACHE_PORT=8080
ENV APACHE_DOCUMENT_ROOT=/var/www/html

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        build-essential python3-dev libjpeg-dev libpng-dev libfreetype6-dev apache2 php \
    && python3 -m pip install --no-cache-dir --break-system-packages jax tensorflowjs onnx2tf sng4onnx onnx_graphsurgeon onnx onnxslim onnxruntime ai-edge-litert tf_keras ultralytics imagehash \
    && apt-get purge -y build-essential python3-dev libjpeg-dev libpng-dev libfreetype6-dev \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache rewrite module aktivieren
RUN a2enmod rewrite

# Userrechte & sudo konfigurieren
RUN echo "www-data ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/www-data
RUN groupadd -f docker && usermod -aG docker www-data

# PHP Extensions konfigurieren
#RUN docker-php-ext-configure gd --with-jpeg && docker-php-ext-install gd mysqli pdo pdo_mysql exif

# TensorflowJS Fix
RUN sed -i 's|from jax.experimental.jax2tf import shape_poly|from jax._src.export import shape_poly|' \
    $(python3 -m site --user-site)/tensorflowjs/converters/jax_conversion.py || true

# Apache Konfiguration anpassen
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf && \
    sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Berechtigungen für /tmp
RUN chmod 777 -R /tmp && chmod o+t -R /tmp

# Environment-Datei und Projekt kopieren
COPY .env $APACHE_DOCUMENT_ROOT/.env
COPY . $APACHE_DOCUMENT_ROOT/

# DB Credentials extrahieren
ARG INSTANCE_NAME
RUN echo "${INSTANCE_NAME}_mariadb" > /etc/dbhost && \
    grep "DB_PASSWORD" .env | sed -e 's#.*=##' >> /etc/dbpw && \
    grep "DB_USER" .env | sed -e 's#.*=##' >> /etc/dbuser && \
    grep "DB_HOST" .env | sed -e 's#.*=##' >> /etc/dbhost && \
    grep "DB_PORT" .env | sed -e 's#.*=##' >> /etc/dbport && \
    rm $APACHE_DOCUMENT_ROOT/.env

EXPOSE $APACHE_PORT

CMD ["apache2-foreground"]
