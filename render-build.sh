#!/bin/bash

# Render Build Script for Laravel Application
# This script runs during the build phase on Render

set -e

echo "🚀 Starting Laravel build process on Render..."

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install NPM dependencies
echo "📦 Installing NPM dependencies..."
npm ci

# Build frontend assets (Vite)
echo "🎨 Building frontend assets..."
npm run build

# Verify build output exists
if [ ! -f "public/build/manifest.json" ]; then
    echo "⚠️  WARNING: Build manifest not found! Assets may not work correctly."
fi

# Create storage directories
echo "📁 Creating storage directories..."
mkdir -p storage/app/public
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set proper permissions
echo "🔐 Setting storage permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Create symbolic link for storage
echo "🔗 Creating storage symbolic link..."
php artisan storage:link || true

# Generate application key if not set
echo "🔑 Generating application key..."
php artisan key:generate --force || true

# Run database migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force --no-interaction || echo "⚠️  Migration failed or already up to date"

# Run database seeders (only on first deployment)
# Uncomment the next line if you want to seed on every deployment
# php artisan db:seed --force --no-interaction || true

# Clear and cache configuration
echo "⚡ Optimizing application..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan event:cache || true

# Clear application cache
php artisan cache:clear || true

echo "✅ Build completed successfully!"

