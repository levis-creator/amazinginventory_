#!/bin/bash

# Azure App Service Startup Script
# This script runs when the container starts

cd /home/site/wwwroot

# Wait for database to be ready (optional)
# You can add a health check here if needed

# Run migrations if needed (be careful in production)
# php artisan migrate --force || true

# Clear and cache configuration
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Start PHP-FPM (handled by Azure, but you can add custom commands here)
exec "$@"

