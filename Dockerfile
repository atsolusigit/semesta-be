FROM dunglas/frankenphp:latest

WORKDIR /app

COPY . /app

RUN install-php-extensions \
    pcntl \
    zip

RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev --optimize-autoloader

RUN chmod -R 777 storage bootstrap/cache

COPY frankenphp.yaml /etc/frankenphp/config.frankenphp.yaml

EXPOSE 80 443
