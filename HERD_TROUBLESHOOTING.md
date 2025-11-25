# Laravel Herd Troubleshooting Guide

## Issue: "404 Site not found" in Herd

If you're seeing a 404 error when accessing `amazinginventory.test`, follow these steps:

## Quick Fixes

### 1. Restart Herd

1. Open **Laravel Herd** application
2. Click **"Restart"** or **"Stop"** then **"Start"**
3. Wait a few seconds for Herd to restart
4. Try accessing `http://amazinginventory.test` again

### 2. Verify Site Detection

1. Open **Laravel Herd** application
2. Go to **"Sites"** tab
3. Look for `amazinginventory` in the list
4. If it's not there, Herd might not be detecting it

### 3. Check Directory Structure

Your project should be in:
```
C:\Users\User\Herd\amazinginventory\
```

And should have:
- ✅ `public/index.php` (Laravel entry point)
- ✅ `artisan` (Laravel CLI)
- ✅ `composer.json`
- ✅ `.env` file

### 4. Manually Link Site (if needed)

If Herd isn't detecting your site automatically:

1. Open **Laravel Herd** application
2. Click **"Sites"** tab
3. Click **"Link"** or **"Add Site"**
4. Navigate to: `C:\Users\User\Herd\amazinginventory`
5. The site should appear as `amazinginventory.test`

### 5. Check PHP Version

1. Open **Laravel Herd** application
2. Go to **"PHP"** tab
3. Make sure you have PHP 8.2 or higher selected
4. Your project requires PHP ^8.2 (from composer.json)

### 6. Verify .env Configuration

Make sure your `.env` has:
```env
APP_URL=http://amazinginventory.test
APP_ENV=local
APP_DEBUG=true
```

### 7. Clear Laravel Caches

Run these commands in your project directory:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 8. Check Storage Permissions

Make sure storage directories are writable:

```bash
# Windows (if needed)
icacls storage /grant Users:F /T
icacls bootstrap\cache /grant Users:F /T
```

### 9. Verify Database

Your `.env` shows SQLite database. Make sure it exists:

```bash
# Check if database file exists
dir database\database.sqlite

# If it doesn't exist, create it and run migrations
php artisan migrate
```

### 10. Test Direct Access

Try accessing the site directly via IP:

1. Find your local IP or use `127.0.0.1`
2. But first, check if `php artisan serve` works:
   ```bash
   php artisan serve
   ```
3. Then visit `http://localhost:8000`
4. If this works, the issue is with Herd configuration

## Alternative: Use php artisan serve

If Herd continues to have issues, you can use Laravel's built-in server:

```bash
# Start Laravel server
php artisan serve

# Then access at: http://localhost:8000
```

Then use ngrok with port 8000:
```bash
ngrok http 8000
```

## Still Not Working?

1. **Check Herd Logs**:
   - Open Herd application
   - Check for any error messages
   - Look at the "Logs" section

2. **Reinstall Herd** (last resort):
   - Uninstall Laravel Herd
   - Reinstall from https://herd.laravel.com
   - Restart your computer
   - Re-add your site

3. **Check Windows Hosts File**:
   - Open `C:\Windows\System32\drivers\etc\hosts` as Administrator
   - Should have: `127.0.0.1 amazinginventory.test`
   - Herd usually manages this automatically

## Common Issues

### Issue: "Site not found" but site exists in Herd
**Solution**: Restart Herd and clear browser cache

### Issue: PHP errors instead of 404
**Solution**: Check `storage/logs/laravel.log` for errors

### Issue: Blank page
**Solution**: 
- Check `APP_DEBUG=true` in `.env`
- Check `storage/logs/laravel.log`
- Verify `vendor` directory exists (run `composer install`)

### Issue: Assets not loading
**Solution**: 
- Run `npm install`
- Run `npm run dev` (for development)
- Or `npm run build` (for production assets)

---

**Need more help?** Check Laravel Herd documentation: https://herd.laravel.com/docs

