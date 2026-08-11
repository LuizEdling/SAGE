FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
        exif \
        pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-sage.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
# Angular fala direto com este servidor embutido do Laravel — sem nginx/php-fpm
# na frente (não é mandatado pelo TCC; artisan serve é suficiente pra Fase 1).
# --no-reload: sem ele o Laravel ignora PHP_CLI_SERVER_WORKERS e sobe um único
# servidor. Só deixa de reiniciar sozinho quando o .env muda; o código PHP
# continua sendo relido a cada requisição.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
