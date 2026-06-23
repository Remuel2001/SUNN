FROM php:8.2-apache

RUN rm -f /etc/apache2/mods-enabled/mpm_event.load && rm -f /etc/apache2/mods-enabled/mpm_event.conf

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV BASE_URL=

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

EXPOSE 80
