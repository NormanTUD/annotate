FROM python:3.11-slim

ENV DEBIAN_FRONTEND=noninteractive
ENV APACHE_PORT=8080
ENV APACHE_DOCUMENT_ROOT=/var/www/html

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        build-essential libjpeg-dev libpng-dev libfreetype6-dev apache2 php libapache2-mod-php \
    && python3 -m pip install --no-cache-dir --break-system-packages jax tensorflowjs onnx2tf sng4onnx onnx_graphsurgeon onnx onnxslim onnxruntime ai-edge-litert tf_keras ultralytics imagehash \
    && apt-get purge -y build-essential libjpeg-dev libpng-dev libfreetype6-dev \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache rewrite module aktivieren
RUN a2enmod rewrite

# Userrechte & sudo konfigurieren
RUN mkdir -p /etc/sudoers.d
RUN echo "www-data ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/www-data

# PHP Extensions konfigurieren
#RUN docker-php-ext-configure gd --with-jpeg && docker-php-ext-install gd mysqli pdo pdo_mysql exif

# TensorflowJS Fix
RUN sed -i 's|from jax.experimental.jax2tf import shape_poly|from jax._src.export import shape_poly|' \
    $(python3 -m site --user-site)/tensorflowjs/converters/jax_conversion.py || true

RUN apt-get update && apt install -y --no-install-recommends curl php-mysql libglib2.0-0 uuid-runtime libgl1 libglvnd0 php-mysql php-gd && apt-get autoremove -y && apt-get clean && apt-get autoclean && rm -rf /var/lib/apt/lists/*

RUN PHP_INI=$(find /etc/php -name php.ini | grep apache2) && \
    sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 100M/' "$PHP_INI" && \
    sed -i 's/^post_max_size = .*/post_max_size = 100M/' "$PHP_INI"

# Apache Konfiguration anpassen
RUN sed -i "s|DocumentRoot /var/www/html|DocumentRoot ${APACHE_DOCUMENT_ROOT}|" /etc/apache2/sites-available/000-default.conf
RUN sed -i "s|<Directory /var/www/html>|<Directory ${APACHE_DOCUMENT_ROOT}>|" /etc/apache2/apache2.conf
RUN sed -i "s/80/${APACHE_PORT}/" /etc/apache2/ports.conf
RUN sed -i "s/*:80/*:${APACHE_PORT}/" /etc/apache2/sites-available/000-default.conf

RUN ln -s /usr/local/bin/python3.11 /usr/local/bin/python3

RUN rm /var/www/html/index.html
# Berechtigungen für /tmp
RUN chmod 777 -R /tmp && chmod o+t -R /tmp

# Environment-Datei und Projekt kopieren
COPY .env $APACHE_DOCUMENT_ROOT/.env
COPY . $APACHE_DOCUMENT_ROOT/

# DB Credentials extrahieren
ARG INSTANCE_NAME
RUN echo "${INSTANCE_NAME}_mariadb" > /etc/dbhost && \
    grep "DB_PASSWORD" $APACHE_DOCUMENT_ROOT/.env | sed -e 's#.*=##' >> /etc/dbpw && \
    grep "DB_USER" $APACHE_DOCUMENT_ROOT/.env | sed -e 's#.*=##' >> /etc/dbuser && \
    grep "DB_PORT" $APACHE_DOCUMENT_ROOT/.env | sed -e 's#.*=##' >> /etc/dbport && \
    rm $APACHE_DOCUMENT_ROOT/.env

EXPOSE $APACHE_PORT

CMD ["/usr/sbin/apachectl", "-D", "FOREGROUND"]
