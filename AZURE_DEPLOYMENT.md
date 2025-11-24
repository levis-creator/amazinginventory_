# Azure Deployment Guide (with Supabase)

This guide will help you deploy the Amazing Inventory Laravel application to Azure App Service, using Supabase for database and storage.

## Prerequisites

1. **Azure Account**: An active Azure subscription (for hosting only)
2. **Supabase Account**: A Supabase account at [https://supabase.com](https://supabase.com)
3. **Azure CLI**: Install from [https://aka.ms/installazurecliwindows](https://aka.ms/installazurecliwindows)
4. **Git**: For version control and deployment
5. **Composer**: PHP dependency manager
6. **Node.js & NPM**: For building frontend assets

## Step 1: Create Azure Resources

### 1.1 Create Resource Group

```bash
az group create --name AmazingInventory --location eastus
```

### 1.2 Create App Service Plan

```bash
az appservice plan create \
  --name amazinginventory-plan \
  --resource-group AmazingInventory \
  --sku B1 \
  --is-linux
```

### 1.3 Create Web App

```bash
az webapp create \
  --resource-group AmazingInventory \
  --plan amazinginventory-plan \
  --name amazinginventory-app \
  --runtime "PHP:8.2"
```

### 1.4 Set Up Supabase Project

**Note**: For detailed Supabase setup instructions, see [SUPABASE_SETUP.md](./SUPABASE_SETUP.md)

1. Go to [Supabase Dashboard](https://app.supabase.com)
2. Click "New Project"
3. Fill in project details:
   - **Name**: amazing-inventory (or your preferred name)
   - **Database Password**: Create a strong password (save this!)
   - **Region**: Choose closest to your Azure App Service region
4. Wait for project to be created (takes 2-3 minutes)

### 1.5 Get Supabase Credentials

After project creation, go to **Project Settings > API**:

1. **Project URL**: `https://your-project-ref.supabase.co`
2. **anon/public key**: Copy the `anon` `public` key
3. **service_role key**: Copy the `service_role` `secret` key (keep this secure!)

Go to **Project Settings > Database**:

1. **Host**: `db.your-project-ref.supabase.co`
2. **Database name**: `postgres`
3. **Port**: `5432`
4. **User**: `postgres`
5. **Password**: The password you set during project creation

### 1.6 Create Supabase Storage Bucket

1. In Supabase Dashboard, go to **Storage**
2. Click **New bucket**
3. Name: `laravel-storage`
4. **Public bucket**: Enable this (for public file access)
5. Click **Create bucket**

### 1.7 Create Redis Cache (Optional, for caching)

```bash
az redis create \
  --resource-group AmazingInventory \
  --name amazinginventory-redis \
  --location eastus \
  --sku Basic \
  --vm-size c0
```

## Step 2: Configure Application Settings

### 2.1 Generate Application Key

```bash
# Run locally to generate APP_KEY
php artisan key:generate --show
```

Copy the generated key (starts with `base64:`).

### 2.2 Configure App Service Environment Variables

Replace `your-project-ref` with your actual Supabase project reference (found in your Supabase URL).

```bash
# Set environment variables
az webapp config appsettings set \
  --resource-group AmazingInventory \
  --name amazinginventory-app \
  --settings \
    APP_NAME="Amazing Inventory" \
    APP_ENV=production \
    APP_KEY="base64:YOUR_GENERATED_KEY_HERE" \
    APP_DEBUG=false \
    APP_URL="https://amazinginventory-app.azurewebsites.net" \
    DB_CONNECTION=pgsql \
    DB_HOST="db.your-project-ref.supabase.co" \
    DB_PORT=5432 \
    DB_DATABASE="postgres" \
    DB_USERNAME="postgres" \
    DB_PASSWORD="YOUR_SUPABASE_DB_PASSWORD" \
    DB_SSLMODE="require" \
    FILESYSTEM_DISK=supabase \
    SUPABASE_URL="https://your-project-ref.supabase.co" \
    SUPABASE_ANON_KEY="YOUR_SUPABASE_ANON_KEY" \
    SUPABASE_SERVICE_ROLE_KEY="YOUR_SUPABASE_SERVICE_ROLE_KEY" \
    SUPABASE_PROJECT_REF="your-project-ref" \
    SUPABASE_STORAGE_ACCESS_KEY="YOUR_SUPABASE_SERVICE_ROLE_KEY" \
    SUPABASE_STORAGE_SECRET_KEY="YOUR_SUPABASE_SERVICE_ROLE_KEY" \
    SUPABASE_STORAGE_BUCKET="laravel-storage" \
    SUPABASE_STORAGE_ENDPOINT="https://your-project-ref.supabase.co/storage/v1/s3" \
    SUPABASE_STORAGE_URL="https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage" \
    SUPABASE_STORAGE_REGION="us-east-1" \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    LOG_CHANNEL=stack \
    LOG_LEVEL=error
```

**Important**: 
- Replace `YOUR_GENERATED_KEY_HERE` with the key from step 2.1
- Replace `your-project-ref` with your actual Supabase project reference
- Replace `YOUR_SUPABASE_DB_PASSWORD` with your Supabase database password
- Replace `YOUR_SUPABASE_ANON_KEY` with your Supabase anon key
- Replace `YOUR_SUPABASE_SERVICE_ROLE_KEY` with your Supabase service role key

## Step 3: Configure Supabase Database Connection

### 3.1 Configure Database Connection Pooling (Recommended)

Supabase uses connection pooling. For production, use the **Transaction** pooler:

- **Host**: `db.your-project-ref.supabase.co`
- **Port**: `6543` (for transaction pooler) or `5432` (for direct connection)
- **Database**: `postgres`
- **User**: `postgres.your-project-ref` (for pooler) or `postgres` (for direct)

Update your database configuration:

```bash
az webapp config appsettings set \
  --resource-group AmazingInventory \
  --name amazinginventory-app \
  --settings \
    DB_HOST="db.your-project-ref.supabase.co" \
    DB_PORT=6543
```

### 3.2 Configure Network Access (if needed)

By default, Supabase allows connections from anywhere. If you need to restrict access:

1. Go to Supabase Dashboard > **Project Settings > Database**
2. Under **Connection string**, configure IP restrictions if needed
3. For Azure App Service, you may need to allow Azure's IP ranges

## Step 4: Configure Deployment

### 4.1 Enable Local Git Deployment

```bash
az webapp deployment source config-local-git \
  --resource-group AmazingInventory \
  --name amazinginventory-app
```

### 4.2 Get Deployment URL

```bash
az webapp deployment list-publishing-profiles \
  --resource-group AmazingInventory \
  --name amazinginventory-app \
  --xml
```

### 4.3 Configure PHP Settings

```bash
az webapp config set \
  --resource-group AmazingInventory \
  --name amazinginventory-app \
  --php-version "8.2" \
  --always-on true
```

### 4.4 Set Startup Command

```bash
az webapp config set \
  --resource-group AmazingInventory \
  --name amazinginventory-app \
  --startup-file "deploy.sh"
```

## Step 5: Deploy Application

### 5.1 Initialize Git Repository (if not already done)

```bash
git init
git add .
git commit -m "Initial commit for Azure deployment"
```

### 5.2 Add Azure Remote

```bash
git remote add azure https://amazinginventory-app.scm.azurewebsites.net/amazinginventory-app.git
```

### 5.3 Deploy

```bash
git push azure main
```

## Step 6: Run Database Migrations

### 6.1 Using Azure Cloud Shell or SSH

```bash
az webapp ssh \
  --resource-group AmazingInventory \
  --name amazinginventory-app
```

Then run:

```bash
cd /home/site/wwwroot
php artisan migrate --force
php artisan db:seed
```

### 6.2 Or Configure in Deployment Script

The `deploy.sh` script includes automatic migrations. Ensure your database is accessible.

## Step 7: Configure Supabase Storage Policies

Since we're using Supabase Storage, you need to set up storage policies:

1. Go to Supabase Dashboard > **Storage** > **Policies**
2. Select the `laravel-storage` bucket
3. Create policies for:
   - **SELECT (Read)**: Allow public read access
   - **INSERT (Upload)**: Allow authenticated users or service role
   - **UPDATE**: Allow authenticated users or service role
   - **DELETE**: Allow authenticated users or service role

Example policy for public read access:
```sql
CREATE POLICY "Public Access" ON storage.objects
FOR SELECT USING (bucket_id = 'laravel-storage');
```

**Note**: With Supabase Storage, you don't need to run `php artisan storage:link` as files are served directly from Supabase.

## Step 8: Verify Deployment

1. Visit your app: `https://amazinginventory-app.azurewebsites.net`
2. Check logs: Azure Portal > App Service > Log stream
3. Verify API endpoints: `https://amazinginventory-app.azurewebsites.net/api/v1/products`

## Step 9: Configure Custom Domain (Optional)

```bash
az webapp config hostname add \
  --webapp-name amazinginventory-app \
  --resource-group AmazingInventory \
  --hostname www.yourdomain.com
```

## Step 10: Set Up SSL Certificate

Azure App Service provides free SSL certificates for custom domains through Azure Portal.

## Troubleshooting

### Common Issues

1. **500 Internal Server Error**
   - Check application logs: Azure Portal > App Service > Log stream
   - Verify environment variables are set correctly
   - Ensure database connection is working

2. **Database Connection Failed**
   - Verify Supabase database credentials in App Settings
   - Check Supabase project is active and running
   - Ensure `DB_SSLMODE=require` is set for secure connection
   - Try using connection pooler (port 6543) instead of direct connection (port 5432)
   - Check Supabase Dashboard for connection issues

3. **Storage Not Working**
   - Verify Supabase Storage credentials (ANON_KEY and SERVICE_ROLE_KEY)
   - Check storage bucket exists and is public in Supabase Dashboard
   - Ensure `FILESYSTEM_DISK=supabase` is set
   - Verify `SUPABASE_STORAGE_ENDPOINT` and `SUPABASE_STORAGE_URL` are correct
   - Check Supabase Storage policies allow read/write access

4. **Assets Not Loading**
   - Run `npm run build` locally and commit built assets
   - Or ensure build process runs during deployment
   - Check `public/build` directory exists

5. **Permission Errors**
   - Ensure storage directories have correct permissions
   - Check `deploy.sh` sets proper permissions

### View Logs

```bash
# Application logs
az webapp log tail \
  --resource-group AmazingInventory \
  --name amazinginventory-app

# Or via Azure Portal
# App Service > Monitoring > Log stream
```

### SSH into App Service

```bash
az webapp ssh \
  --resource-group AmazingInventory \
  --name amazinginventory-app
```

## Continuous Deployment

### Option 1: GitHub Actions

Create `.github/workflows/azure-deploy.yml`:

```yaml
name: Deploy to Azure

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Azure Login
        uses: azure/login@v1
        with:
          creds: ${{ secrets.AZURE_CREDENTIALS }}
      
      - name: Deploy to Azure Web App
        uses: azure/webapps-deploy@v2
        with:
          app-name: 'amazinginventory-app'
          package: '.'
```

### Option 2: Azure DevOps

Set up a pipeline in Azure DevOps connected to your repository.

## Security Best Practices

1. **Never commit `.env` file** - Use Azure App Settings
2. **Protect Supabase Service Role Key** - This key has admin access, keep it secure
3. **Use Supabase RLS (Row Level Security)** - Enable RLS policies on your database tables
4. **Enable HTTPS only** in App Service configuration
5. **Use Supabase Connection Pooling** - Reduces connection overhead and improves security
6. **Configure Storage Policies** - Set appropriate read/write policies in Supabase
7. **Enable application insights** for monitoring
8. **Set up Supabase backups** - Configure automatic backups in Supabase Dashboard
9. **Use environment-specific keys** - Different keys for development and production

## Cost Optimization

1. **Use appropriate SKU** - Start with B1, scale as needed
2. **Enable auto-shutdown** for development environments
3. **Supabase Free Tier** - Includes 500MB database and 1GB storage (great for development)
4. **Monitor Supabase usage** - Check usage in Supabase Dashboard
5. **Optimize database queries** - Use indexes and efficient queries
6. **Use Supabase connection pooling** - Reduces database load
7. **Monitor Azure App Service usage** - Use Azure Cost Management

## Monitoring

1. **Application Insights**: Enable in Azure Portal
2. **Log Analytics**: Configure log retention
3. **Alerts**: Set up alerts for errors and performance
4. **Metrics**: Monitor CPU, memory, and response times

## Support

For issues specific to:
- **Azure Services**: [Azure Support](https://azure.microsoft.com/support/)
- **Supabase**: [Supabase Documentation](https://supabase.com/docs) and [Discord Community](https://discord.supabase.com)
- **Laravel**: [Laravel Documentation](https://laravel.com/docs)
- **Application Issues**: Check application logs and error tracking

## Additional Resources

- [Azure App Service Documentation](https://docs.microsoft.com/azure/app-service/)
- [Laravel Deployment Guide](https://laravel.com/docs/deployment)
- [Supabase Documentation](https://supabase.com/docs)
- [Supabase Storage Guide](https://supabase.com/docs/guides/storage)
- [Supabase Database Guide](https://supabase.com/docs/guides/database)
- [Supabase Connection Pooling](https://supabase.com/docs/guides/database/connecting-to-postgres#connection-pooler)

