FROM dunglas/frankenphp:latest

ENV SERVER_NAME="examfortis.my.id"

WORKDIR /app

COPY . /app

# Switch Debian repo dari trixie -> bookworm
RUN sed -i 's/trixie/bookworm/g' /etc/apt/sources.list

# Install dependencies
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

# Install PHP extensions helper
RUN curl -sSL https://install-php-extensions.github.io/install-php-extensions.sh -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pcntl zip pdo_mysql gd

RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev --optimize-autoloader

RUN chmod -R 777 storage bootstrap/cache || true

COPY frankenphp.yaml /etc/frankenphp/config.frankenphp.yaml

EXPOSE 80 443

