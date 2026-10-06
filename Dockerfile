# Image de production de Binux Shop : FrankenPHP (Caddy + PHP 8.3), sans outil de développement.
FROM dunglas/frankenphp:1-php8.3-bookworm

RUN install-php-extensions pdo_pgsql intl opcache zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR

WORKDIR /app

# Dépendances d'abord : cette couche n'est reconstruite que si composer.lock change.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-progress --no-interaction --prefer-dist --no-autoloader

COPY . .
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-binux-shop.ini

RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && rm -f /usr/bin/composer \
    && mkdir -p var public/images/products public/images/categories /data /config \
    && chown -R www-data:www-data var public/images /data /config \
    # Le serveur écoute sur un port non privilégié : il n'a besoin d'aucune capacité système.
    && setcap -r /usr/local/bin/frankenphp

# Le serveur ne tourne pas en root.
USER www-data

EXPOSE 8080

# Le cache de l'application est préparé au démarrage, quand les variables d'environnement sont connues.
CMD ["sh", "-c", "php bin/console cache:warmup --no-debug && exec frankenphp run --config /app/Caddyfile --adapter caddyfile"]
