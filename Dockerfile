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

# ...pero php.ini-production trae variables_order = "GPCS", SIN la E de Environment,
# y sin la E las variables del contenedor no llegan a $_ENV (a getenv() si, pero el
# codigo lee $_ENV). El defecto de PHP cuando no hay php.ini es "EGPCS", que es lo que
# aplica en produccion ahora mismo, y de ahi salen las ZOHO_* que inyecta el compose
# del VPS: sin esto, activar el php.ini las dejaria a null y el sync con el CRM se
# caeria entero en el primer despliegue.
#
# Las de config/.env no dependen de esto (Dotenv las mete en $_ENV el mismo), solo las
# que vienen por el entorno del contenedor.
RUN printf 'variables_order = "EGPCS"\n' > "$PHP_INI_DIR/conf.d/zz-esc-variables-order.ini"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# El entrypoint aplica las migraciones pendientes antes de levantar Apache, para que
# un despliegue no dependa de que alguien se acuerde de hacerlo a mano.
RUN chmod +x /var/www/html/docker/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
