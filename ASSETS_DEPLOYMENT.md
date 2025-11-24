# Assets & Tailwind CSS Deployment Guide

This guide ensures your Tailwind CSS and frontend assets work correctly after deployment to Azure.

## How Assets Work

Your application uses **Vite** with **Tailwind CSS** for asset compilation:

1. **Development**: Assets are served by Vite dev server
2. **Production**: Assets are compiled to `public/build/` directory
3. **Laravel**: Uses `@vite()` directive to load compiled assets

## Critical Configuration

### 1. APP_URL Must Be Set Correctly

The `APP_URL` environment variable is **critical** for asset URLs to work correctly.

**In Azure App Settings, ensure:**
```env
APP_URL=https://your-actual-app-name.azurewebsites.net
```

**Why it matters:**
- Vite uses `APP_URL` to generate correct asset URLs
- Wrong `APP_URL` = assets load from wrong domain = 404 errors
- Must match your actual Azure App Service URL exactly

### 2. Build Process

Assets are built automatically during deployment via:

1. **GitHub Actions** (if using CI/CD):
   - Runs `npm run build` before deployment
   - Verifies `public/build/manifest.json` exists

2. **Azure Deployment Script** (`deploy.sh`):
   - Installs npm dependencies
   - Runs `npm run build`
   - Verifies build output

3. **Azure Oryx Build** (`.deployment`):
   - Runs `npm ci && npm run build` before deployment
   - Ensures assets are built even if deploy.sh fails

## Verification Steps

### After Deployment, Check:

1. **Verify Build Directory Exists:**
   ```bash
   az webapp ssh --resource-group AmazingInventory --name amazinginventory-app
   cd /home/site/wwwroot
   ls -la public/build/
   ```

   You should see:
   - `manifest.json` (required!)
   - `assets/` directory with CSS and JS files

2. **Check APP_URL:**
   ```bash
   az webapp config appsettings list \
     --resource-group AmazingInventory \
     --name amazinginventory-app \
     --query "[?name=='APP_URL'].value" -o tsv
   ```

   Should match: `https://your-app-name.azurewebsites.net`

3. **Test in Browser:**
   - Open your app in browser
   - Open Developer Tools (F12)
   - Check Console for 404 errors on CSS/JS files
   - Check Network tab - assets should load from `/build/assets/`

## Common Issues & Solutions

### Issue 1: Tailwind CSS Not Loading (No Styles)

**Symptoms:**
- Page loads but has no styling
- All elements appear unstyled

**Causes & Solutions:**

1. **Build didn't run:**
   ```bash
   # SSH into app and manually build
   cd /home/site/wwwroot
   npm ci
   npm run build
   ```

2. **APP_URL incorrect:**
   - Check Azure App Settings
   - Update `APP_URL` to match your actual domain
   - Clear config cache: `php artisan config:clear`

3. **Manifest file missing:**
   ```bash
   # Check if manifest exists
   ls -la public/build/manifest.json
   
   # If missing, rebuild
   npm run build
   ```

### Issue 2: 404 Errors on Assets

**Symptoms:**
- Browser console shows 404 for CSS/JS files
- Assets trying to load from wrong URL

**Solutions:**

1. **Verify APP_URL:**
   ```bash
   # Get current APP_URL
   az webapp config appsettings list \
     --resource-group AmazingInventory \
     --name amazinginventory-app \
     --query "[?name=='APP_URL']"
   
   # Update if wrong
   az webapp config appsettings set \
     --resource-group AmazingInventory \
     --name amazinginventory-app \
     --settings APP_URL="https://your-correct-url.azurewebsites.net"
   ```

2. **Clear caches:**
   ```bash
   php artisan config:clear
   php artisan view:clear
   php artisan cache:clear
   ```

3. **Rebuild assets:**
   ```bash
   npm run build
   ```

### Issue 3: Assets Load But Styles Don't Apply

**Symptoms:**
- CSS files load (200 OK)
- But Tailwind classes don't work

**Solutions:**

1. **Check Tailwind config:**
   - Verify `@tailwindcss/vite` is in `vite.config.js`
   - Check `resources/css/app.css` has `@import 'tailwindcss';`

2. **Rebuild with clean cache:**
   ```bash
   rm -rf node_modules/.vite
   npm run build
   ```

3. **Verify content paths in Tailwind:**
   - Tailwind scans files in `resources/views` and `vendor/`
   - Ensure your Blade files are in correct locations

### Issue 4: Build Fails During Deployment

**Symptoms:**
- Deployment fails with npm/build errors

**Solutions:**

1. **Check Node.js version:**
   - Azure uses Node 18+ by default
   - Your `package.json` should be compatible

2. **Check npm dependencies:**
   ```bash
   # Test build locally first
   npm ci
   npm run build
   ```

3. **Check build logs:**
   - Azure Portal > App Service > Deployment Center > Logs
   - Look for npm/build errors

## Manual Asset Build (If Needed)

If assets aren't building automatically:

```bash
# SSH into Azure App Service
az webapp ssh --resource-group AmazingInventory --name amazinginventory-app

# Navigate to app directory
cd /home/site/wwwroot

# Install dependencies (if needed)
npm ci

# Build assets
npm run build

# Verify build
ls -la public/build/

# Clear Laravel caches
php artisan config:clear
php artisan view:clear
```

## Best Practices

1. **Always set APP_URL correctly** - This is the #1 cause of asset issues
2. **Test build locally** before deploying
3. **Monitor deployment logs** for build errors
4. **Keep Node.js version consistent** between local and Azure
5. **Commit `package-lock.json`** to ensure consistent dependencies

## Environment Variables Checklist

Ensure these are set in Azure App Settings:

- ✅ `APP_URL` - Must match your Azure App Service URL exactly
- ✅ `APP_ENV=production`
- ✅ `APP_DEBUG=false` (for production)

## Quick Verification Script

Add this to your deployment or run manually:

```bash
#!/bin/bash
echo "Checking asset deployment..."

# Check manifest exists
if [ -f "public/build/manifest.json" ]; then
    echo "✅ manifest.json exists"
else
    echo "❌ manifest.json missing - assets won't work!"
    exit 1
fi

# Check assets directory
if [ -d "public/build/assets" ]; then
    echo "✅ assets directory exists"
    ls -la public/build/assets/ | head -5
else
    echo "❌ assets directory missing!"
    exit 1
fi

# Check APP_URL
APP_URL=$(php artisan tinker --execute="echo config('app.url');")
echo "APP_URL is set to: $APP_URL"

echo "✅ Asset check complete!"
```

## Additional Resources

- [Laravel Vite Documentation](https://laravel.com/docs/vite)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Vite Build Documentation](https://vitejs.dev/guide/build.html)

---

**Remember**: The most common issue is incorrect `APP_URL`. Always verify it matches your actual Azure App Service URL!

