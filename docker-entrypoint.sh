#!/bin/bash
set -e

echo "🚀 Starting Laravel application..."

# Get database connection type
DB_CONNECTION=${DB_CONNECTION:-sqlite}

# Handle SQLite database file creation
if [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "📁 Setting up SQLite database..."
    DB_PATH=${DB_DATABASE:-/var/www/html/database/database.sqlite}
    
    # Create database directory if it doesn't exist
    DB_DIR=$(dirname "$DB_PATH")
    mkdir -p "$DB_DIR"
    
    # Create database file if it doesn't exist
    if [ ! -f "$DB_PATH" ]; then
        echo "📝 Creating SQLite database file at $DB_PATH..."
        touch "$DB_PATH"
        chmod 664 "$DB_PATH"
        chown www-data:www-data "$DB_PATH"
        echo "✅ SQLite database file created!"
    else
        echo "✅ SQLite database file already exists!"
    fi
    
    # Ensure database directory is writable
    chmod -R 775 "$DB_DIR"
    chown -R www-data:www-data "$DB_DIR"
else
    # For other database types, wait for connection
    echo "⏳ Waiting for database connection..."
    max_attempts=30
    attempt=0
    until php artisan migrate:status > /dev/null 2>&1 || [ $attempt -ge $max_attempts ]; do
        attempt=$((attempt + 1))
        echo "Database is unavailable - sleeping (attempt $attempt/$max_attempts)"
        sleep 2
    done

    if [ $attempt -ge $max_attempts ]; then
        echo "⚠️  Warning: Could not connect to database after $max_attempts attempts"
        echo "Continuing anyway - migrations will be attempted..."
    else
        echo "✅ Database is ready!"
    fi
fi

# Run database migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force --no-interaction || echo "⚠️  Migration failed or already up to date"

# Seed database (creates admin user, roles, and permissions)
# The seeder uses firstOrCreate, so it's safe to run multiple times
if [ "${SEED_DATABASE:-true}" = "true" ]; then
    echo "🌱 Seeding database with admin user, roles, and permissions..."
    php artisan db:seed --force --no-interaction || echo "⚠️  Seeding failed or already completed"
else
    echo "⏭️  Database seeding skipped (SEED_DATABASE=false)"
fi

# Clear all caches first to ensure fresh configuration
echo "🧹 Clearing caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

# Verify APP_URL is set (critical for asset URLs)
if [ -z "$APP_URL" ]; then
    echo "⚠️  WARNING: APP_URL is not set! This may cause asset loading issues."
    echo "   Please set APP_URL in your Render environment variables."
else
    echo "✅ APP_URL is set to: $APP_URL"
fi

# Verify assets are built
if [ ! -f "public/build/manifest.json" ]; then
    echo "⚠️  WARNING: Build manifest not found! Rebuilding assets..."
    npm run build || echo "⚠️  Asset build failed, continuing anyway..."
else
    echo "✅ Asset manifest found at public/build/manifest.json"
    # Verify assets directory exists
    if [ -d "public/build/assets" ]; then
        ASSET_COUNT=$(find public/build/assets -type f | wc -l)
        echo "✅ Found $ASSET_COUNT asset files in public/build/assets/"
    else
        echo "⚠️  WARNING: public/build/assets directory not found!"
    fi
fi

# Cache configuration with current environment variables (including APP_URL)
echo "⚡ Caching configuration with current environment..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan event:cache || true

# Optimize Laravel for production
echo "🚀 Optimizing Laravel application..."
php artisan optimize || true

# Publish Filament assets (ensures CSS/JS are available)
echo "📦 Publishing Filament assets..."
php artisan filament:assets || echo "⚠️  Filament assets publish failed, continuing anyway..."

# Optimize Filament for production
# This caches Filament components and Blade Icons for better performance
echo "🎨 Optimizing Filament panel..."
php artisan filament:optimize || echo "⚠️  Filament optimization failed, continuing anyway..."

# Start the application
echo "🌟 Starting PHP development server..."
# Render provides PORT environment variable, default to 80 if not set
PORT=${PORT:-80}
echo "🌐 Binding to port $PORT..."
exec php artisan serve --host=0.0.0.0 --port=$PORT

