FROM php:8.2-apache

RUN a2enmod rewrite headers

# Enable .htaccess support
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN apt-get update && apt-get install -y unzip git && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
