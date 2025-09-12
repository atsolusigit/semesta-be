FROM dunglas/frankenphp:1.9.1-builder-php8.3.25-bookworm AS app

ENV SERVER_NAME="examfortis.my.id"

WORKDIR /app

COPY . /app

# Install system dependencies (buat gd, zip, dsb.)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    unzip \
    curl \
    git \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo_mysql pcntl

RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev --optimize-autoloader

RUN chmod -R 777 storage bootstrap/cache || true

COPY frankenphp.yaml /etc/frankenphp/config.frankenphp.yaml

EXPOSE 80 443

