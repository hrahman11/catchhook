# syntax=docker/dockerfile:1

# --- Frontend assets ---------------------------------------------------------
FROM node:24-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json ./

# Reverb connection details are compiled into the frontend bundle.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN npm run build

# --- Application -------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4 AS app

RUN install-php-extensions pdo_pgsql intl pcntl opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies before copying the source so this layer stays cached.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/catchhook-entrypoint

RUN composer dump-autoload --optimize --no-dev \
    && chmod +x /usr/local/bin/catchhook-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache /data /config

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=":8000"

# Run as an unprivileged user; port 8000 needs no extra capabilities.
USER www-data

EXPOSE 8000

ENTRYPOINT ["catchhook-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
