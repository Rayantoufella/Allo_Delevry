FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip libpng-dev libonig-dev libxml2-dev libzip-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Initialisation du backend (composer, .env, clé, migrations, seed, storage:link).
COPY entrypoint.sh /usr/local/bin/allo-entrypoint
RUN chmod +x /usr/local/bin/allo-entrypoint

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

CMD ["php-fpm"]
