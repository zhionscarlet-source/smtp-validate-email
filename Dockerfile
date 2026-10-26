FROM composer:2 AS composer
FROM php:8.2-apache

COPY --from=composer /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
    && composer dump-autoload --no-dev --optimize \
    && a2enmod rewrite

# Render's default web-service port is 10000.
RUN sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && printf '%s\n' \
       '<VirtualHost *:10000>' \
       '    DocumentRoot /var/www/html/public' \
       '    <Directory /var/www/html/public>' \
       '        Options FollowSymLinks' \
       '        AllowOverride All' \
       '        Require all granted' \
       '    </Directory>' \
       '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
       '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
       '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

ENV PORT=10000
EXPOSE 10000
