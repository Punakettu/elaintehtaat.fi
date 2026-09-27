FROM php:8.5-fpm-alpine

# Install PHP extensions required/recommended by Drupal 11.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    apcu \
    bcmath \
    gd \
    intl \
    opcache \
    pdo_mysql \
    redis \
    xdebug \
    zip

RUN apk add --no-cache git unzip mysql-client patch

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

ENV PATH="/var/www/html/vendor/bin:${PATH}"

# Switches Xdebug on when XDEBUG_ENABLE=true, then hands off to the stock entrypoint.
COPY --chmod=0755 entrypoint.sh /usr/local/bin/xdebug-entrypoint
ENTRYPOINT ["xdebug-entrypoint"]
CMD ["php-fpm"]
