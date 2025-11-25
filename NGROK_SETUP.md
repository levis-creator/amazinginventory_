# Running Laravel with ngrok

This guide helps you expose your local Laravel application to the internet using ngrok, perfect for testing or when Azure billing is an issue.

## Prerequisites

1. **Install ngrok**: Download from [ngrok.com](https://ngrok.com/download) or use package manager:
   ```bash
   # Windows (using Chocolatey)
   choco install ngrok
   
   # macOS (using Homebrew)
   brew install ngrok
   
   # Or download from https://ngrok.com/download
   ```

2. **Sign up for ngrok** (free account works):
   - Go to [ngrok.com](https://ngrok.com)
   - Sign up for free account
   - Get your authtoken from dashboard

3. **Authenticate ngrok**:
   ```bash
   ngrok config add-authtoken YOUR_AUTH_TOKEN
   ```

## Quick Start

### Option 1: Using Laravel Herd (Windows/Mac)

If you're using **Laravel Herd** (your app runs at `amazinginventory.test`):

1. **Make sure your Herd app is running** - Visit `http://amazinginventory.test` to verify

2. **Start ngrok** (tunnels to port 80, which Herd uses):
   ```bash
   # Windows
   ngrok http 80
   
   # Or use the helper script
   scripts\ngrok-herd.bat
   ```

3. **Get your ngrok URL**:
   - Open http://localhost:4040 in your browser
   - Copy the HTTPS URL (e.g., `https://xxxx-xx-xx-xx-xx.ngrok.io`)

4. **Update APP_URL** in your `.env`:
   ```env
   APP_URL=https://your-ngrok-url.ngrok.io
   ```

5. **Clear config cache**:
   ```bash
   php artisan config:clear
   ```

6. **Start Vite dev server** (in another terminal):
   ```bash
   npm run dev
   ```

### Option 2: Using php artisan serve

1. **Start Laravel server**:
   ```bash
   php artisan serve
   ```
   (Runs on http://localhost:8000)

2. **Start Vite dev server** (in another terminal):
   ```bash
   npm run dev
   ```

3. **Start ngrok** (in another terminal):
   ```bash
   ngrok http 8000
   ```

4. **Update APP_URL** in your `.env`:
   ```env
   APP_URL=https://your-ngrok-url.ngrok.io
   ```

5. **Clear config cache**:
   ```bash
   php artisan config:clear
   ```

## Using the Helper Scripts

### Start with ngrok (All-in-one)

```bash
composer run ngrok
```

This script:
- Starts Laravel on port 8000
- Starts Vite dev server
- Starts ngrok tunnel
- Automatically updates APP_URL
- Shows you the public URL

### Start Development (Local only)

```bash
composer run dev
```

Runs Laravel + Vite + Queue worker locally (no ngrok).

## Configuration

### Update .env for ngrok

When using ngrok, you need to update your `.env` file:

```env
APP_URL=https://your-ngrok-url.ngrok.io
APP_ENV=local
APP_DEBUG=true
```

**Important**: The ngrok URL changes each time you restart ngrok (unless you have a paid plan with static domain).

### For Supabase

Your Supabase database and storage will work fine with ngrok. Just make sure:

1. **Database**: Supabase allows connections from anywhere, so ngrok URL works fine
2. **Storage**: Supabase Storage works with any origin
3. **CORS**: If you have CORS issues, configure in Supabase Dashboard

## Troubleshooting

### Issue: Assets (CSS/JS) Not Loading

**Solution**: Make sure Vite dev server is running:
```bash
npm run dev
```

Vite needs to be running in development mode for hot-reloading.

### Issue: ngrok URL Changes Every Time

**Free ngrok**: URLs change on restart. Options:
1. Use the URL shown each time
2. Upgrade to paid plan for static domain
3. Use ngrok config file to set subdomain (paid feature)

### Issue: CORS Errors

If you get CORS errors with Supabase:

1. Check Supabase Dashboard > Settings > API
2. Add your ngrok URL to allowed origins (if needed)
3. Supabase usually allows all origins by default

### Issue: APP_URL Not Updating

```bash
# Clear config cache
php artisan config:clear

# Verify APP_URL
php artisan tinker
>>> config('app.url')
```

### Issue: "ngrok: command not found"

Make sure ngrok is installed and in your PATH:
```bash
# Check if ngrok is installed
ngrok version

# If not found, add to PATH or use full path
/path/to/ngrok http 8000
```

## ngrok Features

### View Requests (Web Interface)

When ngrok is running, open:
```
http://localhost:4040
```

This shows:
- All incoming requests
- Request/response details
- Replay requests
- Inspect headers

### Static Domain (Paid Feature)

If you upgrade ngrok, you can use a static domain:
```bash
ngrok http 8000 --domain=your-static-domain.ngrok.io
```

## Security Considerations

⚠️ **Important**: When using ngrok:

1. **Don't expose sensitive data** - ngrok URLs are public
2. **Use HTTPS** - ngrok provides HTTPS automatically
3. **Don't commit ngrok URLs** - they change frequently
4. **Use for testing only** - not for production
5. **Monitor requests** - check ngrok web interface regularly

## Stopping Services

To stop all services:

1. Press `Ctrl+C` in each terminal
2. Or use the helper script which handles cleanup

## Alternative: LocalTunnel (Free Alternative)

If you prefer a free alternative to ngrok:

```bash
# Install localtunnel
npm install -g localtunnel

# Start Laravel
php artisan serve

# In another terminal, create tunnel
lt --port 8000
```

## Next Steps

Once ngrok is running:

1. ✅ Test your API endpoints
2. ✅ Test file uploads (Supabase Storage)
3. ✅ Test database connections
4. ✅ Share URL with team for testing
5. ✅ Test webhooks (if applicable)

## Production Note

Remember: ngrok is for **development/testing only**. For production:
- Use Azure App Service (when billing is resolved)
- Or use other hosting providers
- Or deploy to VPS/server

---

**Need help?** Check the troubleshooting section or see the helper scripts in `composer.json`.

