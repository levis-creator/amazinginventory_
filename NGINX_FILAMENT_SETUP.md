# Nginx Configuration for Filament Assets

## Overview

If you're experiencing issues with Filament assets being blocked (404 errors for `/vendor/filament/...` files), you need to ensure your nginx configuration allows these paths.

## Current Setup

**Your Docker setup:**
- Uses PHP's built-in server (`php artisan serve`)
- **No nginx in Docker** - nginx is likely configured by your hosting provider (Render.com, etc.)

## Solution

### Option 1: Render.com (Recommended)

Render.com uses nginx as a reverse proxy. If Filament assets are blocked:

1. **Check if Render allows custom nginx configs:**
   - Render.com typically handles nginx automatically
   - If assets are blocked, contact Render support or check their documentation

2. **Verify Filament assets are published:**
   ```bash
   # In Render Shell or locally
   php artisan filament:assets
   ```
   
   This should create files in:
   - `public/vendor/filament/`
   - `public/filament/`

3. **Check if assets exist:**
   ```bash
   ls -la public/vendor/filament/
   ls -la public/filament/
   ```

### Option 2: Custom Nginx Configuration (If Supported)

If your hosting provider allows custom nginx configuration:

1. **Use the provided `nginx.conf` file:**
   - The file includes critical rules for Filament assets:
   ```nginx
   location ~ ^/(vendor|filament)/ {
       try_files $uri $uri/ /index.php?$query_string;
       expires 1y;
       add_header Cache-Control "public, immutable";
   }
   ```

2. **For Docker with nginx:**
   - If you want to switch from PHP's built-in server to nginx + PHP-FPM:
   - Update your `Dockerfile` to include nginx
   - Copy `nginx.conf` to the container
   - Configure PHP-FPM

### Option 3: Docker Compose with Nginx (Alternative Setup)

If you want to use nginx in Docker instead of PHP's built-in server:

1. **Create `docker-compose.yml`:**
```yaml
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
      - ./public:/var/www/html/public:ro
    depends_on:
      - php

  php:
    build: .
    volumes:
      - .:/var/www/html
    environment:
      - APP_ENV=production
```

2. **Update `Dockerfile` to use PHP-FPM:**
```dockerfile
FROM php:8.3-fpm
# ... rest of your Dockerfile
```

## Verification

### Check if Filament assets are accessible:

1. **Visit these URLs (replace with your domain):**
   - `https://your-app.com/vendor/filament/filament.css`
   - `https://your-app.com/filament/assets/app.css`

2. **If you get 404:**
   - Assets may not be published: Run `php artisan filament:assets`
   - Nginx may be blocking: Check nginx configuration
   - Permissions issue: Check file permissions

### Debug Steps:

1. **Check if assets exist:**
   ```bash
   ls -la public/vendor/filament/
   ```

2. **Check nginx logs:**
   ```bash
   tail -f /var/log/nginx/error.log
   ```

3. **Test nginx configuration:**
   ```bash
   nginx -t
   ```

## For Render.com Specifically

Render.com typically:
- ✅ Automatically allows all files in `public/` directory
- ✅ Uses nginx as reverse proxy
- ✅ Should not block Filament assets if they're in `public/`

**If assets are still blocked on Render:**

1. **Verify assets are published:**
   ```bash
   # In Render Shell
   php artisan filament:assets
   ```

2. **Check file permissions:**
   ```bash
   chmod -R 755 public/vendor/
   chmod -R 755 public/filament/
   ```

3. **Clear cache:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   ```

4. **Contact Render Support:**
   - They may need to whitelist `/vendor/` paths
   - Or adjust their nginx configuration

## Key Points

✅ **Filament assets should be in `public/` directory** (not `vendor/` in root)
✅ **Your current Docker setup uses PHP's built-in server** (no nginx needed in Docker)
✅ **Render.com handles nginx automatically** (usually no configuration needed)
✅ **If blocked, it's likely a hosting provider configuration issue**

## Quick Fix

If you're on Render.com and assets are blocked:

1. Ensure `php artisan filament:assets` runs during build (already in your `Dockerfile`)
2. Check Render logs for 404 errors
3. Verify `APP_URL` is set correctly
4. Contact Render support if issue persists




