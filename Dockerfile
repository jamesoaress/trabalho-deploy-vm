FROM php:8.2-apache

ARG APP_REVISION=local
ENV APP_REVISION=${APP_REVISION}

RUN mkdir -p /var/lib/php/sessions \
    && chown www-data:www-data /var/lib/php/sessions \
    && chmod 700 /var/lib/php/sessions \
    && printf '<Directory /var/www/html>\n    Options -Indexes\n</Directory>\n' > /etc/apache2/conf-available/no-indexes.conf \
    && a2enconf no-indexes

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY index.php crud-filmes.php health.php /var/www/html/

EXPOSE 80
