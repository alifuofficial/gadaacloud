#!/bin/sh
set -e

# Run migrations
if [ -n "$DB_HOST" ]; then
    echo "Running migrations..."
    php artisan migrate --force
fi

# Cache configuration, routes, and views for optimal performance
echo "Caching configuration and routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Apache in foreground
echo "Starting Apache..."
exec apache2-foreground
