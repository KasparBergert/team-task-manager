FROM serversideup/php:8.5-fpm
WORKDIR /var/www/html

COPY . .
RUN test -f vendor/autoload.php && test -f vendor/bin/pest || \
    { echo 'Missing vendor dependencies. Run composer install with development packages before building.' >&2; exit 1; }

USER root
RUN touch .env.testing
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
