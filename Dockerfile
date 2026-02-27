# ══════════════════════════════════════════════════════════════════════════════
#  GAM — Global Asset Management System
#  Multi-stage Docker build: PHP 8.3-FPM + Nginx + CLI Processing Tools
#  Per REQ-01 (Containerised Environment) and REQ-02 (Server-Side Tools)
# ══════════════════════════════════════════════════════════════════════════════

# ── Stage 1: Composer dependencies ───────────────────────────────────────────
FROM composer:2 AS composer-deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-req=ext-pcntl

# ── Stage 2: Node build (Vite) ──────────────────────────────────────────────
FROM node:20-alpine AS node-build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Stage 3: App image ──────────────────────────────────────────────────────
FROM php:8.4-fpm AS app

LABEL maintainer="GAM Team"
LABEL description="GAM Application — Laravel 12 + PHP 8.4-FPM + Nginx + CLI Tools"

ARG DEBIAN_FRONTEND=noninteractive

# ── Install system packages + REQ-02 CLI tools ──────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Core utilities
    curl \
    unzip \
    zip \
    git \
    supervisor \
    cron \
    # Nginx web server (REQ-01)
    nginx \
    # Ghostscript 10 — EPS/PDF → PNG (REQ-02)
    ghostscript \
    # ImageMagick 7 — PSD/TIFF/raster processing (REQ-02)
    imagemagick \
    libmagickwand-dev \
    # Inkscape — AI/SVG → PNG (REQ-02)
    inkscape \
    # Poppler-utils — PDF → PNG via pdftoppm (REQ-02)
    poppler-utils \
    # FFmpeg — Video thumbnail extraction (REQ-02)
    ffmpeg \
    # LibreOffice headless — DOCX/XLSX → PDF → PNG (REQ-02)
    libreoffice-core \
    libreoffice-common \
    libreoffice-writer \
    libreoffice-calc \
    # PHP extension dependencies
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libsqlite3-dev \
    # Redis extension dependency
    && pecl install redis \
    && docker-php-ext-enable redis \
    # Clean up
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# ── Install PHP extensions ───────────────────────────────────────────────────
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        pdo_sqlite \
        zip \
        intl \
        mbstring \
        xml \
        curl \
        bcmath \
        opcache \
        pcntl

# ── ImageMagick policy: allow PDF/PS processing ─────────────────────────────
RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
        sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' /etc/ImageMagick-6/policy.xml; \
        sed -i 's/rights="none" pattern="PS"/rights="read|write" pattern="PS"/' /etc/ImageMagick-6/policy.xml; \
        sed -i 's/rights="none" pattern="EPS"/rights="read|write" pattern="EPS"/' /etc/ImageMagick-6/policy.xml; \
    fi

# ── PHP configuration ───────────────────────────────────────────────────────
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# ── Nginx configuration ─────────────────────────────────────────────────────
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# ── Supervisor configuration (runs PHP-FPM + Nginx + cron) ──────────────────
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Working directory ────────────────────────────────────────────────────────
WORKDIR /var/www/html

# ── Copy Composer autoload from stage 1 + app code ──────────────────────────
COPY --from=composer-deps /app/vendor ./vendor
COPY . .

# ── Copy built frontend assets from stage 2 ─────────────────────────────────
COPY --from=node-build /app/public/build ./public/build

# ── Run Composer autoload dump + optimisations ───────────────────────────────
RUN touch .env \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer dump-autoload --optimize --no-dev \
    && composer clear-cache

# ── Create required directories ─────────────────────────────────────────────
RUN mkdir -p \
        storage/app/staging \
        storage/app/assets \
        storage/app/public/previews \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# ── Entrypoint ──────────────────────────────────────────────────────────────
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
