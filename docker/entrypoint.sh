#!/bin/bash
set -e

echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•"
echo "  GAM â€” Global Asset Management System"
echo "  Starting application..."
echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•"

# â”€â”€ Wait for MySQL if using it â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if [ "$DB_CONNECTION" = "mysql" ]; then
    echo "Waiting for MySQL..."
    until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
        sleep 2
    done
    echo "MySQL is ready."
fi

# -- Install Composer dependencies if vendor/ is missing (bind-mount override) -
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "vendor/ not found (bind-mount override). Running composer install..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# -- Restore frontend build assets if missing (bind-mount override) ------------
if [ ! -f /var/www/html/public/build/manifest.json ] && [ -d /tmp/gam-build-assets ]; then
    echo "public/build/ not found. Restoring from Docker image..."
    mkdir -p /var/www/html/public/build
    cp -R /tmp/gam-build-assets/* /var/www/html/public/build/
fi

# -- Generate APP_KEY if placeholder is still present ---------------------
if grep -q 'GENERATE_ME_WITH' /var/www/html/.env 2>/dev/null; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force
fi

# â”€â”€ Storage symlink â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
php artisan storage:link --force 2>/dev/null || true

# â”€â”€ Run migrations â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
echo "Running migrations..."
php artisan migrate --force

# -- Auto-seed if roles table is empty (first boot) ---------------------------
ROLE_COUNT=$(php -r "try { echo (new PDO('mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')))->query('SELECT COUNT(*) FROM roles')->fetchColumn(); } catch(Exception \$e) { echo '0'; }" 2>/dev/null)
if [ "$ROLE_COUNT" = "0" ]; then
    echo "First boot detected (no roles). Running db:seed..."
    php artisan db:seed --force
fi

# â”€â”€ Cache configuration for production â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# â”€â”€ Create BookStack DB if needed â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if [ "$DB_CONNECTION" = "mysql" ]; then
    php -r "
        try {
            \$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', 'root', '${DB_ROOT_PASSWORD:-rootsecret}');
            \$pdo->exec('CREATE DATABASE IF NOT EXISTS bookstack');
            echo \"BookStack DB ensured.\n\";
        } catch (Exception \$e) {
            echo \"Could not create BookStack DB: \" . \$e->getMessage() . \"\n\";
        }
    " 2>/dev/null || true
fi

# â”€â”€ Set permissions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•"
echo "  GAM ready â€” Listening on port 80"
echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•"

# â”€â”€ Execute the main command (supervisord) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
exec "$@"
