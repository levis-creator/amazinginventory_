#!/bin/bash
set -e

echo "🚀 Starting Laravel application..."

# Wait for database to be ready (optional, but recommended)
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

# Run database migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force --no-interaction || echo "⚠️  Migration failed or already up to date"

# Clear and cache configuration (only if not already cached)
if [ ! -f "bootstrap/cache/config.php" ]; then
    echo "⚡ Caching configuration..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
fi

# Start the application
echo "🌟 Starting PHP development server..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-80}

