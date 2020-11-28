##################################################################################################################
# Dependency Stage
##################################################################################################################
FROM composer:latest AS vendor

WORKDIR /app/

COPY composer.json composer.lock /app/

COPY . /app/

RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative \
    --no-ansi \
    --no-dev

##################################################################################################################
# Base Stage
##################################################################################################################
FROM php:7.4-cli-alpine as base_image

RUN apk --no-cache update \
    && apk --no-cache add gmp-dev python3 py3-pip \
    && docker-php-ext-install -j$(nproc) gmp bcmath

COPY . /app/
COPY --from=vendor /app/vendor/ /app/vendor/

WORKDIR /app/resources/xpub_derive

RUN pip3 install --no-cache -r requirements.txt

WORKDIR /app/

##################################################################################################################
# Test Stage
##################################################################################################################
FROM base_image as test

WORKDIR /app/

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --from=vendor /usr/bin/composer /usr/bin/composer

# run the test script(s) from composer, this validates the application before allowing the build to succeed
RUN composer install --no-interaction --no-plugins --no-scripts --prefer-dist --no-ansi --ignore-platform-reqs
RUN composer run-script test

##################################################################################################################
# Production Stage
##################################################################################################################
FROM base_image as production_build

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

# compile the container for performance reasons
RUN /app/bin/bitcoin-dca >/dev/null

ENTRYPOINT ["docker-entrypoint"]

##################################################################################################################
# Development Stage
##################################################################################################################
FROM base_image AS development

# Do development things here
