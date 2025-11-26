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
# Render provides PORT environment variable, default to 80 if not set
PORT=${PORT:-80}
echo "🌐 Binding to port $PORT..."
exec php artisan serve --host=0.0.0.0 --port=$PORT

