# Quick Fix: Assets Not Working

If your Tailwind CSS or JavaScript assets aren't loading after deployment, follow these steps:

## Step 1: Verify APP_URL (Most Common Issue)

```bash
# Check current APP_URL
az webapp config appsettings list \
  --resource-group AmazingInventory \
  --name amazinginventory-app \
  --query "[?name=='APP_URL'].value" -o tsv

# Update if incorrect (replace with your actual URL)
az webapp config appsettings set \
  --resource-group AmazingInventory \
  --name amazinginventory-app \
  --settings APP_URL="https://your-actual-app-name.azurewebsites.net"
```

## Step 2: Rebuild Assets

```bash
# SSH into app
az webapp ssh --resource-group AmazingInventory --name amazinginventory-app

# In SSH session:
cd /home/site/wwwroot
npm ci
npm run build

# Verify build
ls -la public/build/manifest.json

# Clear caches
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

## Step 3: Verify in Browser

1. Open your app: `https://your-app-name.azurewebsites.net`
2. Press F12 (Developer Tools)
3. Check Console tab for errors
4. Check Network tab - look for CSS/JS files loading from `/build/assets/`

## Still Not Working?

See [ASSETS_DEPLOYMENT.md](./ASSETS_DEPLOYMENT.md) for detailed troubleshooting.

