FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev && docker-php-ext-install pdo_mysql mbstring && apt-get purge -y --auto-remove libonig-dev && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

EXPOSE 80
