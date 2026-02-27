#!/bin/bash
# ═══════════════════════════════════════════════════════════════
#  GAM Deploy Helper — flush OPCache + re-cache after docker cp
#  Run inside the container after copying updated files:
#    docker exec gam-app bash /var/www/html/deploy-refresh.sh
# ═══════════════════════════════════════════════════════════════

set -e
echo "🔄 GAM Deploy Refresh"

# 1. Clear Laravel caches
echo "  → Clearing Laravel caches..."
php /var/www/html/artisan optimize:clear --quiet 2>/dev/null || true

# 2. Re-cache config, routes, views
echo "  → Re-caching config/routes/views..."
php /var/www/html/artisan optimize --quiet 2>/dev/null || true

# 3. Flush OPCache via PHP-FPM (CLI has separate OPCache)
echo "  → Flushing PHP-FPM OPCache..."
echo '<?php opcache_reset(); echo "ok";' > /var/www/html/public/_opcache_reset.php
curl -s http://127.0.0.1/_opcache_reset.php > /dev/null 2>&1 || true
rm -f /var/www/html/public/_opcache_reset.php

echo "✅ Done — pages will be fast on next request"
