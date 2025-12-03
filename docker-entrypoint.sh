#!/bin/bash
set -e

echo "🚀 Starting Laravel application..."

# Get database connection type (default to sqlite if not set)
DB_CONNECTION=${DB_CONNECTION:-sqlite}
SYSTEM_DB_CONNECTION=${SYSTEM_DB_CONNECTION:-sqlite}

# Handle SQLite database file creation for application database
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
    echo "✅ Application database will use SQLite: $DB_PATH"
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

# Handle SQLite database file creation for system database
if [ "$SYSTEM_DB_CONNECTION" = "sqlite" ]; then
    echo "📁 Setting up System SQLite database..."
    SYSTEM_DB_PATH=${SYSTEM_DB_DATABASE:-/var/www/html/database/system.sqlite}
    
    # Create database directory if it doesn't exist
    SYSTEM_DB_DIR=$(dirname "$SYSTEM_DB_PATH")
    mkdir -p "$SYSTEM_DB_DIR"
    
    # Create database file if it doesn't exist
    if [ ! -f "$SYSTEM_DB_PATH" ]; then
        echo "📝 Creating System SQLite database file at $SYSTEM_DB_PATH..."
        touch "$SYSTEM_DB_PATH"
        chmod 664 "$SYSTEM_DB_PATH"
        chown www-data:www-data "$SYSTEM_DB_PATH"
        echo "✅ System SQLite database file created!"
    else
        echo "✅ System SQLite database file already exists!"
    fi
    
    # Ensure database directory is writable
    chmod -R 775 "$SYSTEM_DB_DIR"
    chown -R www-data:www-data "$SYSTEM_DB_DIR"
    echo "✅ System database will use SQLite: $SYSTEM_DB_PATH"
fi

# Run migrations and seed for all databases
# The migrate:all command handles both system and application database migrations
# The seeder uses firstOrCreate, so it's safe to run multiple times

# Check database connections before running migrations
echo "🔍 Checking database connections before running migrations..."

# Check application database connection
echo "📊 Checking application database connection..."
APP_DB_CONNECTED=false
if php artisan db:show > /dev/null 2>&1; then
    APP_DB_CONNECTED=true
    echo "✅ Application database connection successful (via db:show)"
elif php artisan migrate:status > /dev/null 2>&1; then
    APP_DB_CONNECTED=true
    echo "✅ Application database connection successful (via migrate:status)"
else
    echo "❌ Application database connection failed!"
    echo "⚠️  Please check your database configuration:"
    echo "   - DB_CONNECTION: ${DB_CONNECTION:-not set}"
    echo "   - DB_HOST: ${DB_HOST:-not set}"
    echo "   - DB_DATABASE: ${DB_DATABASE:-not set}"
    echo "   - DB_USERNAME: ${DB_USERNAME:-not set}"
fi

# Check system database connection (if not SQLite)
if [ "$SYSTEM_DB_CONNECTION" != "sqlite" ]; then
    echo "📊 Checking system database connection..."
    SYSTEM_DB_CONNECTED=false
    if php artisan migrate:status --database=system > /dev/null 2>&1; then
        SYSTEM_DB_CONNECTED=true
        echo "✅ System database connection successful"
    else
        echo "❌ System database connection failed!"
        echo "⚠️  Please check your system database configuration:"
        echo "   - SYSTEM_DB_CONNECTION: ${SYSTEM_DB_CONNECTION:-not set}"
        echo "   - SYSTEM_DB_HOST: ${SYSTEM_DB_HOST:-not set}"
    fi
else
    SYSTEM_DB_CONNECTED=true
    echo "✅ System database is SQLite (connection check skipped)"
fi

# Verify connections are successful before proceeding
if [ "$APP_DB_CONNECTED" = "false" ]; then
    echo ""
    echo "❌ ERROR: Application database connection failed!"
    echo "   Cannot proceed with migrations without a valid database connection."
    echo "   Please fix your database configuration and try again."
    exit 1
fi

if [ "$SYSTEM_DB_CONNECTED" = "false" ]; then
    echo ""
    echo "❌ ERROR: System database connection failed!"
    echo "   Cannot proceed with migrations without a valid system database connection."
    echo "   Please fix your system database configuration and try again."
    exit 1
fi

echo ""
echo "✅ All database connections verified successfully!"

# IMPORTANT: migrate:all runs system database migrations FIRST, before application migrations
# This ensures the database_configurations table exists before the app tries to use it
echo ""
echo "🗄️  Running migrations for all databases (system + application)..."
if php artisan migrate:all --force; then
    echo "✅ All database migrations completed successfully"
else
    echo "⚠️  Database migrations failed or already up to date"
    # Continue anyway - migrations might already be run
fi

if [ "${SEED_DATABASE:-true}" = "true" ]; then
    echo "🗄️  Seeding databases..."
    
    # Check if FILAMENT_ADMIN_EMAIL is set
    if [ -z "$FILAMENT_ADMIN_EMAIL" ]; then
        echo "⚠️  WARNING: FILAMENT_ADMIN_EMAIL is not set!"
        echo "   Admin user will be created with default email: admin@example.com"
    else
        echo "✅ FILAMENT_ADMIN_EMAIL is set to: $FILAMENT_ADMIN_EMAIL"
    fi
    
    # Run seeders
    php artisan db:seed --force || echo "⚠️  Seeding failed or already completed"
    
    # Clear permission cache to ensure roles are fresh (critical for shared DB)
    echo "🔄 Clearing permission cache (important for shared database)..."
    php artisan permission:cache-reset || true
    
    # Verify admin user was created/updated and has admin role
    # Since local and production share the same DB, we need to ensure existing users get the role
    echo "🔍 Verifying admin user setup..."
    echo "   Note: Since local and production share the same database,"
    echo "   make sure FILAMENT_ADMIN_EMAIL matches the email you use to login."
    php artisan tinker --execute="
        \$email = env('FILAMENT_ADMIN_EMAIL', 'admin@example.com');
        \$user = \App\Models\User::where('email', \$email)->first();
        
        if (\$user) {
            // Refresh user to clear any cached relationships
            \$user->load('roles');
            \$hasAdminRole = \$user->hasRole('admin');
            \$roles = \$user->roles->pluck('name')->join(', ') ?: 'none';
            
            echo 'Found user: ' . \$user->name . ' (' . \$user->email . ')' . PHP_EOL;
            echo 'User ID: ' . \$user->id . PHP_EOL;
            echo 'Has admin role: ' . (\$hasAdminRole ? 'YES ✅' : 'NO ❌') . PHP_EOL;
            echo 'Current roles: ' . \$roles . PHP_EOL;
            
            if (!\$hasAdminRole) {
                echo '⚠️  WARNING: User does not have admin role! Fixing now...' . PHP_EOL;
                
                // Ensure admin role exists
                \$adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->where('guard_name', 'web')->first();
                if (!\$adminRole) {
                    echo '❌ ERROR: Admin role does not exist in database!' . PHP_EOL;
                    echo 'Please run: php artisan db:seed --class=PermissionRoleSeeder' . PHP_EOL;
                } else {
                    // Assign admin role (syncRoles removes other roles, which is what we want)
                    \$user->syncRoles(['admin']);
                    
                    // Clear cache and refresh
                    app()['cache']->forget('spatie.permission.cache');
                    \$user->refresh();
                    \$user->load('roles');
                    
                    // Verify it worked
                    if (\$user->hasRole('admin')) {
                        echo '✅ Admin role assigned successfully!' . PHP_EOL;
                    } else {
                        echo '❌ Failed to assign admin role. Please check database manually.' . PHP_EOL;
                    }
                }
            } else {
                echo '✅ User already has admin role - no action needed.' . PHP_EOL;
            }
        } else {
            echo '⚠️  User not found with email: ' . \$email . PHP_EOL;
            echo 'The seeder should create this user. Checking all users...' . PHP_EOL;
            \$allUsers = \App\Models\User::all();
            echo 'Total users in database: ' . \$allUsers->count() . PHP_EOL;
            foreach (\$allUsers as \$u) {
                echo '  - ' . \$u->email . ' (ID: ' . \$u->id . ')' . PHP_EOL;
            }
        }
    " || echo "⚠️  Could not verify admin user (tinker failed)"
else
    echo "🗄️  Running migrations for all databases (seeding skipped)..."
    php artisan migrate:all --force || echo "⚠️  Migration failed or already up to date"
    echo "⏭️  Database seeding skipped (SEED_DATABASE=false)"
fi

# Clear all caches first to ensure fresh configuration
# This is especially important after code changes like implementing FilamentUser interface
echo "🧹 Clearing all caches (important after FilamentUser interface implementation)..."
php artisan optimize:clear || true
# Also clear permission cache separately (Spatie Permission)
php artisan permission:cache-reset || php artisan cache:clear || true

# Verify APP_URL is set (critical for asset URLs)
if [ -z "$APP_URL" ]; then
    echo "⚠️  WARNING: APP_URL is not set! This may cause asset loading issues."
    echo "   Please set APP_URL in your Railway environment variables."
    echo "   Railway will provide RAILWAY_PUBLIC_DOMAIN if you enable public networking."
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
# This MUST run after migrations and before optimization
echo "📦 Publishing Filament assets..."
if php artisan filament:assets; then
    echo "✅ Filament assets published successfully"
else
    echo "⚠️  First attempt failed, retrying Filament assets publish..."
    # Clear config cache and try again (sometimes needed if config changed)
    php artisan config:clear || true
    if php artisan filament:assets; then
        echo "✅ Filament assets published on retry"
    else
        echo "❌ ERROR: Filament assets publish failed after retry!"
        echo "   This may cause Filament login page to not work properly."
        echo "   Trying alternative: php artisan vendor:publish --tag=filament-assets"
        php artisan vendor:publish --tag=filament-assets --force || true
    fi
fi

# Ensure public directory and assets are accessible
echo "🔐 Setting proper permissions for public directory..."
chmod -R 755 public/ || true
chown -R www-data:www-data public/ || true

# Verify public/index.php exists and is accessible
if [ ! -f "public/index.php" ]; then
    echo "⚠️  WARNING: public/index.php not found!"
else
    chmod 644 public/index.php || true
    echo "✅ public/index.php is accessible"
fi

# Ensure Filament assets directory is accessible
if [ -d "public/filament" ]; then
    echo "✅ Filament assets directory found, setting permissions..."
    chmod -R 755 public/filament/ || true
    chown -R www-data:www-data public/filament/ || true
    # List Filament assets for debugging
    FILAMENT_FILES=$(find public/filament -type f 2>/dev/null | wc -l)
    echo "📁 Filament assets: $FILAMENT_FILES files"
    
    # Also check vendor/filament directory (some versions use this)
    if [ -d "public/vendor/filament" ]; then
        VENDOR_FILES=$(find public/vendor/filament -type f 2>/dev/null | wc -l)
        echo "📁 Filament vendor assets: $VENDOR_FILES files"
        chmod -R 755 public/vendor/filament/ || true
        chown -R www-data:www-data public/vendor/filament/ || true
    fi
else
    echo "⚠️  WARNING: public/filament directory not found!"
    echo "   Attempting to publish Filament assets again..."
    php artisan config:clear || true
    php artisan filament:assets || php artisan vendor:publish --tag=filament-assets --force || true
    
    # Check again after retry
    if [ -d "public/filament" ]; then
        echo "✅ Filament assets published on retry"
        chmod -R 755 public/filament/ || true
        chown -R www-data:www-data public/filament/ || true
    else
        echo "❌ ERROR: Filament assets still not found after retry!"
        echo "   Filament login page may not work. Check Railway logs for errors."
        echo "   You may need to run manually: php artisan filament:assets"
    fi
fi

# Ensure build assets are accessible
if [ -d "public/build" ]; then
    echo "✅ Build assets directory found, setting permissions..."
    chmod -R 755 public/build/ || true
    chown -R www-data:www-data public/build/ || true
else
    echo "⚠️  WARNING: public/build directory not found!"
fi

# Optimize Filament for production
# This caches Filament components and Blade Icons for better performance
echo "🎨 Optimizing Filament panel..."
php artisan filament:optimize || echo "⚠️  Filament optimization failed, continuing anyway..."

# Start the application
echo "🌟 Application is ready!"
echo "🌐 Starting application via supervisor (PHP-FPM + Nginx)..."

# Ensure PHP-FPM socket directory exists (for fallback)
mkdir -p /var/run/php || true

# Execute the startup script if it exists, otherwise start supervisor directly
if [ -f "/usr/local/bin/start-railway.sh" ]; then
    exec /usr/local/bin/start-railway.sh
else
    # Fallback: start supervisor directly
    PORT=${PORT:-80}
    echo "⚠️  Startup script not found, starting supervisor directly on port $PORT"
    sed -i "s/listen 80;/listen $PORT;/" /etc/nginx/sites-available/default || true
    exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi

