FROM dunglas/frankenphp

WORKDIR /app

COPY composer.json composer.lock ./

RUN install-php-extensions \
    pcntl \
    zip
    
RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev --optimize-autoloader

COPY . .

RUN chmod -R 777 storage bootstrap/cache

EXPOSE 80

CMD ["frankenphp", "-c", "frankenphp.yaml", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=80"]
