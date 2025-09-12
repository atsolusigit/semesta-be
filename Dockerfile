FROM dunglas/frankenphp:latest

ENV SERVER_NAME="examfortis.my.id"

WORKDIR /app

COPY . /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install helper
RUN curl -sSL https://install-php-extensions.github.io/install-php-extensions.sh -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions

# Install PHP extensions
RUN install-php-extensions gd zip pdo_mysql pcntl

RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev --optimize-autoloader

RUN chmod -R 777 storage bootstrap/cache

COPY frankenphp.yaml /etc/frankenphp/config.frankenphp.yaml

EXPOSE 80 443

