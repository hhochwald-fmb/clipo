FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads /var/www/html/storage/backups /var/www/html/storage/tmp \
    && chown -R www-data:www-data /var/www/html \
    && chmod 775 /var/www/html/uploads /var/www/html/storage
