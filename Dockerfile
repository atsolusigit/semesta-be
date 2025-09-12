FROM dunglas/frankenphp:latest

ENV SERVER_NAME="examfortis.my.id"

WORKDIR /app

COPY . /app

# install dependencies & helper
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# install install-php-extensions helper
RUN curl -sSL https://install-php-extensions.github.io/install-php-extensions.sh | bash

# install extensions
RUN install-php-extensions pcntl zip pdo_mysql gd

RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev --optimize-autoloader

RUN chmod -R 777 storage bootstrap/cache

COPY frankenphp.yaml /etc/frankenphp/config.frankenphp.yaml

EXPOSE 80 443

