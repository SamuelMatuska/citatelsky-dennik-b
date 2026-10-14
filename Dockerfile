# 1) React build -> /app/public (index.html + assets/)
FROM node:22-alpine AS frontend
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# 2) Laravel
FROM php:8.4-cli
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction
COPY . .
COPY --from=frontend /app/public/ ./public/
RUN composer dump-autoload --optimize && php artisan package:discover

EXPOSE 8000
CMD ["sh", "docker/start.sh"]
