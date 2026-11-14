# PHP 8.4 + Apache, con PDO MySQL, mod_rewrite, mod_expires y mod_headers (caché del navegador), Composer y envío de correo a Mailpit
FROM php:8.4-apache

# git y unzip: los necesita Composer para descargar paquetes
# msmtp: hace que la función mail() de PHP entregue los correos a Mailpit (bandeja de prueba)
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip msmtp \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql \
 && a2enmod rewrite expires headers \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html/public>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/cooki.conf \
 && a2enconf cooki \
 && printf 'sendmail_path = "/usr/bin/msmtp -t --host=mailpit --port=1025 --from=no-responder@cooki.test"\n' > /usr/local/etc/php/conf.d/correo.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
