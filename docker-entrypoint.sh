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
    
    # Check if FILAMENT_ADMIN_EMAIL is set
    if [ -z "$FILAMENT_ADMIN_EMAIL" ]; then
        echo "⚠️  WARNING: FILAMENT_ADMIN_EMAIL is not set!"
        echo "   Admin user will be created with default email: admin@example.com"
    else
        echo "✅ FILAMENT_ADMIN_EMAIL is set to: $FILAMENT_ADMIN_EMAIL"
    fi
    
    # Run the seeder
    php artisan db:seed --force --no-interaction || echo "⚠️  Seeding failed or already completed"
    
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
    echo "📁 Filament assets: $(find public/filament -type f | wc -l) files"
else
    echo "⚠️  WARNING: public/filament directory not found! Filament assets may not be published."
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
echo "🌟 Starting PHP development server..."
# Render provides PORT environment variable, default to 80 if not set
PORT=${PORT:-80}
echo "🌐 Binding to port $PORT..."
# Ensure we're in the correct directory and serve from public directory
cd /var/www/html
exec php artisan serve --host=0.0.0.0 --port=$PORT

