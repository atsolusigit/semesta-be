FROM dunglas/frankenphp:1.1.3-php8.2

ENV SERVER_NAME=localhost

WORKDIR /app

# Copy semua source code ke dalam container
COPY . /app

# Install dependency dasar
RUN apt-get update && apt-get install -y git zip unzip curl openssl && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP yang dibutuhkan Laravel
RUN install-php-extensions \
    pcntl \
    zip \
    pdo \
    pdo_mysql \
    mbstring \
    tokenizer \
    xml \
    ctype

# Copy Composer dari image resmi Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set permission storage & cache
RUN chmod -R 777 storage bootstrap/cache

# Copy konfigurasi Caddy/FrankenPHP
COPY Caddyfile /etc/frankenphp/Caddyfile

# Expose port HTTP dan HTTPS
EXPOSE 80 443
