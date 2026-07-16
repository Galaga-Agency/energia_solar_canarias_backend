FROM php:8.2-apache

RUN a2enmod rewrite headers

# Enable .htaccess support
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN apt-get update && apt-get install -y unzip git libpng-dev libjpeg-dev libfreetype6-dev && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql gd

# La imagen base no activa ningun php.ini, asi que aplican los defaults de PHP y
# display_errors queda a On: los warnings se imprimen ANTES del JSON y rompen la
# respuesta de la API. Usamos php.ini-production (display_errors=Off, log_errors=On)
# para que el cuerpo sea JSON valido. Los errores siguen visibles en:
#   docker compose logs app
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
