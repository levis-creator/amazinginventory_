# Azure Quick Start Guide (with Supabase)

This is a quick reference guide for deploying to Azure App Service with Supabase for database and storage. For detailed instructions, see [AZURE_DEPLOYMENT.md](./AZURE_DEPLOYMENT.md).

## Quick Deployment Steps

### 1. Prerequisites Check
```bash
# Verify Azure CLI is installed
az --version

# Login to Azure
az login
```

### 2. Set Up Supabase Project

1. Go to [Supabase Dashboard](https://app.supabase.com)
2. Create a new project:
   - Name: `amazing-inventory`
   - Database Password: Create a strong password (save it!)
   - Region: Choose closest to your Azure region
3. Wait for project creation (2-3 minutes)
4. Go to **Storage** > Create bucket named `laravel-storage` (make it public)

### 3. Create Azure Resources (One-time setup)

```bash
# Set variables
RESOURCE_GROUP="AmazingInventory"
APP_NAME="amazinginventory-app"
SUPABASE_PROJECT_REF="your-project-ref"  # From Supabase URL

# Note: If resource group already exists, skip this step
# az group create --name $RESOURCE_GROUP --location eastus

# Create app service plan
az appservice plan create \
  --name amazinginventory-plan \
  --resource-group AmazingInventory \
  --sku B1 \
  --is-linux

# Create web app
az webapp create \
  --resource-group AmazingInventory \
  --plan amazinginventory-plan \
  --name $APP_NAME \
  --runtime "PHP:8.2"
```

### 4. Get Supabase Credentials

From Supabase Dashboard > **Project Settings > API**:
- **Project URL**: `https://$SUPABASE_PROJECT_REF.supabase.co`
- **anon key**: Copy the `anon` `public` key
- **service_role key**: Copy the `service_role` `secret` key

From Supabase Dashboard > **Project Settings > Database**:
- **Host**: `db.$SUPABASE_PROJECT_REF.supabase.co`
- **Password**: The password you set during project creation

### 5. Configure Environment Variables

```bash
# Generate APP_KEY locally first
php artisan key:generate --show
# Copy the generated key

# Set app settings (replace placeholders with your actual values)
az webapp config appsettings set \
  --resource-group AmazingInventory \
  --name $APP_NAME \
  --settings \
    APP_NAME="Amazing Inventory" \
    APP_ENV=production \
    APP_KEY="base64:YOUR_GENERATED_KEY_HERE" \
    APP_DEBUG=false \
    APP_URL="https://$APP_NAME.azurewebsites.net" \
    DB_CONNECTION=pgsql \
    DB_HOST="db.$SUPABASE_PROJECT_REF.supabase.co" \
    DB_PORT=6543 \
    DB_DATABASE="postgres" \
    DB_USERNAME="postgres.$SUPABASE_PROJECT_REF" \
    DB_PASSWORD="YOUR_SUPABASE_DB_PASSWORD" \
    DB_SSLMODE="require" \
    FILESYSTEM_DISK=supabase \
    SUPABASE_URL="https://$SUPABASE_PROJECT_REF.supabase.co" \
    SUPABASE_ANON_KEY="YOUR_SUPABASE_ANON_KEY" \
    SUPABASE_SERVICE_ROLE_KEY="YOUR_SUPABASE_SERVICE_ROLE_KEY" \
    SUPABASE_PROJECT_REF="$SUPABASE_PROJECT_REF" \
    SUPABASE_STORAGE_ACCESS_KEY="YOUR_SUPABASE_SERVICE_ROLE_KEY" \
    SUPABASE_STORAGE_SECRET_KEY="YOUR_SUPABASE_SERVICE_ROLE_KEY" \
    SUPABASE_STORAGE_BUCKET="laravel-storage" \
    SUPABASE_STORAGE_ENDPOINT="https://$SUPABASE_PROJECT_REF.supabase.co/storage/v1/s3" \
    SUPABASE_STORAGE_URL="https://$SUPABASE_PROJECT_REF.supabase.co/storage/v1/object/public/laravel-storage" \
    SUPABASE_STORAGE_REGION="us-east-1"
```

### 6. Configure Supabase Storage Policies

In Supabase Dashboard > **Storage** > **Policies**:
1. Select `laravel-storage` bucket
2. Create policy for public read access:
   ```sql
   CREATE POLICY "Public Access" ON storage.objects
   FOR SELECT USING (bucket_id = 'laravel-storage');
   ```

### 7. Deploy Application

```bash
# Initialize git (if not already done)
git init
git add .
git commit -m "Initial commit"

# Add Azure remote
git remote add azure https://$APP_NAME.scm.azurewebsites.net/$APP_NAME.git

# Deploy
git push azure main
```

### 8. Run Migrations

```bash
az webapp ssh \
  --resource-group AmazingInventory \
  --name $APP_NAME

# In SSH session:
cd /home/site/wwwroot
php artisan migrate --force
# Note: No need to run storage:link with Supabase Storage
```

## Common Commands

### View Logs
```bash
az webapp log tail \
  --resource-group AmazingInventory \
  --name $APP_NAME
```

### SSH into App
```bash
az webapp ssh \
  --resource-group AmazingInventory \
  --name $APP_NAME
```

### Restart App
```bash
az webapp restart \
  --resource-group AmazingInventory \
  --name $APP_NAME
```

### Update Environment Variables
```bash
az webapp config appsettings set \
  --resource-group AmazingInventory \
  --name $APP_NAME \
  --settings KEY="value"
```

### View App Settings
```bash
az webapp config appsettings list \
  --resource-group AmazingInventory \
  --name $APP_NAME
```

## Environment Variables Checklist

Before deployment, ensure these are set in Azure App Settings:

- [ ] `APP_NAME`
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` (generated)
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` (your Azure app URL)
- [ ] `DB_CONNECTION=pgsql`
- [ ] `DB_HOST` (Supabase database host)
- [ ] `DB_PORT=6543` (or 5432 for direct connection)
- [ ] `DB_DATABASE=postgres`
- [ ] `DB_USERNAME=postgres`
- [ ] `DB_PASSWORD` (Supabase database password)
- [ ] `DB_SSLMODE=require`
- [ ] `FILESYSTEM_DISK=supabase`
- [ ] `SUPABASE_URL`
- [ ] `SUPABASE_ANON_KEY`
- [ ] `SUPABASE_SERVICE_ROLE_KEY`
- [ ] `SUPABASE_PROJECT_REF`
- [ ] `SUPABASE_STORAGE_ACCESS_KEY`
- [ ] `SUPABASE_STORAGE_SECRET_KEY`
- [ ] `SUPABASE_STORAGE_BUCKET=laravel-storage`
- [ ] `SUPABASE_STORAGE_ENDPOINT`
- [ ] `SUPABASE_STORAGE_URL`
- [ ] `SUPABASE_STORAGE_REGION`

## Troubleshooting Quick Fixes

### App not loading
1. Check logs: `az webapp log tail`
2. Verify environment variables
3. Check database connectivity

### Database connection failed
1. Verify Supabase credentials in App Settings
2. Check Supabase project is active
3. Try connection pooler (port 6543) vs direct (port 5432)
4. Ensure `DB_SSLMODE=require` is set

### Storage not working
1. Verify Supabase Storage credentials (ANON_KEY and SERVICE_ROLE_KEY)
2. Check storage bucket exists and is public in Supabase
3. Ensure `FILESYSTEM_DISK=supabase` is set
4. Verify storage policies are configured

### Assets not loading
1. Ensure `npm run build` ran during deployment
2. Check `public/build` directory exists
3. Verify `APP_URL` is correct

## Next Steps

- Set up custom domain
- Configure SSL certificate
- Set up monitoring and alerts
- Configure backups
- Set up CI/CD pipeline

For detailed information, see [AZURE_DEPLOYMENT.md](./AZURE_DEPLOYMENT.md).

