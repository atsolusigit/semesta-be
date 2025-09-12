FROM dunglas/frankenphp:latest

ENV SERVER_NAME="examfortis.my.id"

WORKDIR /app

COPY . /app

RUN docker-php-ext-install pcntl pdo_mysql zip gd

RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev --optimize-autoloader

RUN chmod -R 777 storage bootstrap/cache

COPY frankenphp.yaml /etc/frankenphp/config.frankenphp.yaml

EXPOSE 80 443

