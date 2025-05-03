FROM dunglas/frankenphp

WORKDIR /app

COPY composer.json composer.lock ./


RUN composer install --no-dev --optimize-autoloader

# Copy semua project
COPY . .

# Permissions
RUN chmod -R 777 storage bootstrap/cache

# FrankenPHP listen di port 80
EXPOSE 80

# Command bawaan FrankenPHP
CMD ["frankenphp", "-c", "frankenphp.yaml", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=80"]
