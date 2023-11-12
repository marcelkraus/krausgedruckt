FROM php:8.2-fpm-alpine

WORKDIR /app

RUN apk add --no-cache bash git ca-certificates wget curl

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY files/*.ini "$PHP_INI_DIR/conf.d/"

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    @composer \
    intl \
    pdo_mysql \
    redis \
    zip \
    xdebug

RUN apk add nodejs npm
