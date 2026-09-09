FROM php:8.2-apache

# Install PostgreSQL PDO extension
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite (not strictly required but handy)
RUN a2enmod rewrite headers

COPY src/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
