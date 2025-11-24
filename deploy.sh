#!/bin/bash

# Azure App Service Deployment Script for Laravel
# This script runs during deployment on Azure App Service

set -e

echo "Starting Laravel deployment on Azure..."

# Navigate to the application directory
cd /home/site/wwwroot

# Install Composer dependencies (if not already installed)
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# Install NPM dependencies and build assets
if [ ! -d "node_modules" ]; then
    echo "Installing NPM dependencies..."
    npm ci --production=false
fi

# Ensure public/build directory exists
echo "Creating public/build directory..."
mkdir -p public/build

# Build frontend assets (this generates Tailwind CSS and JS)
echo "Building frontend assets (Tailwind CSS, JS)..."
npm run build

# Verify build output exists
if [ ! -f "public/build/manifest.json" ]; then
    echo "ERROR: Build manifest not found! Assets may not work correctly."
    exit 1
fi

echo "Frontend assets built successfully!"

# Create storage directories if they don't exist
echo "Creating storage directories..."
mkdir -p storage/app/public
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set proper permissions
echo "Setting storage permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Create symbolic link for storage
echo "Creating storage symbolic link..."
php artisan storage:link || true

# Run database migrations (only if not in maintenance mode)
echo "Running database migrations..."
php artisan migrate --force --no-interaction || echo "Migration failed or already up to date"

# Clear and cache configuration
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

# Clear application cache
php artisan cache:clear || true

echo "Deployment completed successfully!"

