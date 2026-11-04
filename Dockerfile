# Single-container image: Apache + PHP 8.3 serving public/, SQLite on a volume.
# Optional build secret "extra_ca": a PEM bundle to trust when building behind a
# TLS-inspecting proxy (docker build --secret id=extra_ca,src=proxy-ca.pem ...).

############################ 1. Frontend assets ############################
FROM node:22-alpine AS assets
WORKDIR /app
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
COPY package.json package-lock.json ./
RUN --mount=type=secret,id=extra_ca,required=false \
    if [ -f /run/secrets/extra_ca ]; then export NODE_EXTRA_CA_CERTS=/run/secrets/extra_ca; fi; \
    npm ci --no-audit --no-fund
COPY tsconfig.json vite.config.ts ./
COPY resources ./resources
RUN npm run build

############################ 2. PHP dependencies ###########################
# Same base as the runtime stage, so only two base images are pulled in total.
FROM php:8.3-apache AS vendor
WORKDIR /app
RUN --mount=type=secret,id=extra_ca,required=false \
    if [ -f /run/secrets/extra_ca ]; then cat /run/secrets/extra_ca >> /etc/ssl/certs/ca-certificates.crt; fi; \
    sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY composer.json composer.lock ./
RUN --mount=type=secret,id=extra_ca,required=false \
    if [ -f /run/secrets/extra_ca ]; then cat /run/secrets/extra_ca >> /etc/ssl/certs/ca-certificates.crt; fi; \
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-scripts --no-autoloader --no-interaction \
        --ignore-platform-req=ext-gd \
    # Packages installed from source carry their git history; it is not needed at runtime.
    && find vendor -name .git -type d -prune -exec rm -rf {} +
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-dev --optimize --no-scripts

############################ 3. Runtime ####################################
FROM php:8.3-apache AS runtime
RUN --mount=type=secret,id=extra_ca,required=false \
    if [ -f /run/secrets/extra_ca ]; then cat /run/secrets/extra_ca >> /etc/ssl/certs/ca-certificates.crt; fi; \
    sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd opcache \
    && apt-get purge -y --auto-remove libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && apt-get install -y --no-install-recommends libpng16-16 libjpeg62-turbo libfreetype6 \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/apache-security.conf /etc/apache2/conf-enabled/zz-hardening.conf
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && rm -f .env bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && mkdir -p /data storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /data

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=log

VOLUME ["/data"]
EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
