# Render deploys Laravel via Docker (no native PHP buildpack) — this mirrors
# Render's own official example (render-examples/php-laravel-docker), which
# uses this same base image bundling Nginx + PHP-FPM in one container.
FROM richarvey/nginx-php-fpm:3.1.6

COPY . .

ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1

# Installed at build time (not container start) — faster boots, and no need
# for Composer/network access once the container is actually running.
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

CMD ["/start.sh"]
