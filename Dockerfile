FROM php:8.1-cli

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && \
    apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j$(nproc) gd

RUN apt-get update && \
    apt-get install -y libzip-dev && \
    docker-php-ext-install -j$(nproc) zip

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --prefer-dist --no-scripts

COPY . .

RUN composer install --no-dev --optimize-autoloader --prefer-dist

CMD [ "php", "/app/bin/console", "expand" ]
