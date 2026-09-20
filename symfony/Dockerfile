#syntax=docker/dockerfile:1.4

FROM dunglas/frankenphp:1-php8.3 AS frankenphp_upstream

# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

VOLUME /app/var/

# persistent / runtime deps
# hadolint ignore=DL3008

RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
	file \
	gettext \
	git \
	fish \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
		pdo \
		pdo_pgsql \
		pgsql \
		gd \
	;

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1 DATABASE_URL=$DATABASE_URL

COPY --link docker/frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/
COPY --link --chmod=755 docker/frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link ./docker/caddy/Caddyfile.base /etc/caddy/Caddyfile.base
COPY --link ./docker/caddy/worker.Caddyfile /etc/caddy/worker.Caddyfile
# COPY --link frankenphp/Caddyfile /etc/caddy/Caddyfile

# CRONS ARE SET HERE
COPY --link docker/crons/crontabs /etc/crontabs/root
COPY --link docker/crons/daily /etc/periodic/daily
RUN chmod -R +x /etc/periodic/

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]

# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev XDEBUG_MODE=$XDEBUG_MODE XDEBUG_CONFIG=$XDEBUG_CONFIG

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
    install-php-extensions \
        xdebug \
    ;

COPY --link docker/frankenphp/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch" ]

FROM vue:latest AS vue_prod
FROM docs:latest AS docs_prod

# Prod FrankenPHP image
FROM frankenphp_base AS frankenphp_prod

ARG APP_ENV
ENV APP_ENV=$APP_ENV SENTRY_DSN=$SENTRY_DSN
ENV FRANKENPHP_CONFIG="import worker.Caddyfile"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link docker/frankenphp/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/

# prevent the reinstallation of vendors at every changes in the source code
COPY --link composer.* symfony.* ./

# if $APP_ENV == "prod" then install prod dependencies
RUN if [ "$APP_ENV" = "prod" ]; then \
    set -eux; \
    composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
  fi

# if $APP_ENV == "dev" then install dev dependencies
RUN if [ "$APP_ENV" = "dev" ]; then \
    set -eux; \
    composer install --no-cache --prefer-dist --no-autoloader --no-scripts --no-progress; \
  fi


# copy sources
COPY --link . ./
COPY --from=vue_prod --link /app/dist /var/www/vue
COPY --from=docs_prod --link /app/.vitepress/dist /var/www/docs
# RUN rm -Rf docker/frankenphp/
# RUN rm -Rf frankenphp/

RUN if [ "$APP_ENV" = "prod" ]; then \
    set -eux; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; sync; \
  fi

RUN if [ "$APP_ENV" = "dev" ]; then \
    set -eux; \
    mkdir -p var/cache var/log; \
    composer dump-autoload; \
    composer dump-env dev; \
    composer run-script post-install-cmd; \
    chmod +x bin/console; sync; \
  fi