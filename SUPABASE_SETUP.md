# Supabase Setup Guide

This guide will help you set up Supabase for database and storage with your Laravel application deployed on Azure.

## Prerequisites

1. **Supabase Account**: Sign up at [supabase.com](https://supabase.com)
2. **Supabase Project**: Create a new project in your Supabase dashboard

## Step 1: Create Supabase Project

1. Go to [app.supabase.com](https://app.supabase.com)
2. Click "New Project"
3. Fill in:
   - **Name**: Your project name (e.g., "amazing-inventory")
   - **Database Password**: Choose a strong password (save this!)
   - **Region**: Choose closest to your Azure App Service region
   - **Pricing Plan**: Select appropriate plan
4. Click "Create new project"
5. Wait for project to be provisioned (2-3 minutes)

## Step 2: Get Database Connection Details

1. In Supabase Dashboard, go to **Settings** > **Database**
2. Find the **Connection string** section
3. You'll need:
   - **Host**: `db.your-project-ref.supabase.co`
   - **Port**: `5432` (direct) or `6543` (connection pooler)
   - **Database**: `postgres`
   - **User**: `postgres` (direct) or `postgres.your-project-ref` (pooler)
   - **Password**: The password you set during project creation
   - **SSL Mode**: `require` (Supabase requires SSL)

### Connection Pooling (Recommended for Production)

Supabase provides connection pooling to handle many concurrent connections:

- **Transaction Pooler**: Port `6543`, User `postgres.your-project-ref`
- **Session Pooler**: Port `5432`, User `postgres.your-project-ref`

For Laravel, use the **Transaction Pooler** for better performance.

## Step 3: Configure Supabase Storage

### 3.1 Create Storage Bucket

1. Go to **Storage** in Supabase Dashboard
2. Click **New bucket**
3. Configure:
   - **Name**: `laravel-storage` (or your preferred name)
   - **Public bucket**: ✅ Enable (for public file access)
   - **File size limit**: Set appropriate limit (e.g., 10MB)
   - **Allowed MIME types**: Leave empty for all types, or specify image types
4. Click **Create bucket**

### 3.2 Configure Storage Policies (Optional but Recommended)

For public buckets, you may want to set up policies:

1. Go to **Storage** > **Policies**
2. Create policies for your bucket:
   - **SELECT**: Allow public read access
   - **INSERT**: Allow authenticated users (or service role)
   - **UPDATE**: Allow authenticated users (or service role)
   - **DELETE**: Allow authenticated users (or service role)

### 3.3 Get Storage S3-Compatible Credentials

Supabase Storage supports S3-compatible API. To use it:

1. Go to **Settings** > **API**
2. Note your:
   - **Project URL**: `https://your-project-ref.supabase.co`
   - **Service Role Key**: (keep this secret!)
   - **Anon Key**: (for public access)

**Note**: Supabase Storage S3 API uses the service role key for authentication. The endpoint format is:
- **Endpoint**: `https://your-project-ref.supabase.co/storage/v1/s3`
- **Access Key**: Your service role key (or configure S3 credentials if available)
- **Secret Key**: Your service role key (or configure S3 credentials if available)

**Alternative**: If Supabase provides dedicated S3 credentials in your project settings, use those instead.

## Step 4: Configure Laravel Environment Variables

Add these to your Azure App Service Configuration or `.env` file:

```env
# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=db.your-project-ref.supabase.co
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.your-project-ref
DB_PASSWORD=your_database_password
DB_SSLMODE=require

# Supabase API Configuration
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_ANON_KEY=your_anon_key
SUPABASE_SERVICE_ROLE_KEY=your_service_role_key
SUPABASE_PROJECT_REF=your-project-ref

# Supabase Storage Configuration
FILESYSTEM_DISK=supabase
SUPABASE_STORAGE_ACCESS_KEY=your_service_role_key
SUPABASE_STORAGE_SECRET_KEY=your_service_role_key
SUPABASE_STORAGE_BUCKET=laravel-storage
SUPABASE_STORAGE_ENDPOINT=https://your-project-ref.supabase.co/storage/v1/s3
SUPABASE_STORAGE_URL=https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage
SUPABASE_STORAGE_REGION=us-east-1
```

## Step 5: Set Up Database in Azure App Service

### 5.1 Configure Environment Variables

```bash
az webapp config appsettings set \
  --resource-group AmazingInventory \
  --name amazinginventory-app \
  --settings \
    DB_CONNECTION=pgsql \
    DB_HOST="db.your-project-ref.supabase.co" \
    DB_PORT=6543 \
    DB_DATABASE="postgres" \
    DB_USERNAME="postgres.your-project-ref" \
    DB_PASSWORD="your_database_password" \
    DB_SSLMODE="require" \
    FILESYSTEM_DISK=supabase \
    SUPABASE_URL="https://your-project-ref.supabase.co" \
    SUPABASE_ANON_KEY="your_anon_key" \
    SUPABASE_SERVICE_ROLE_KEY="your_service_role_key" \
    SUPABASE_PROJECT_REF="your-project-ref" \
    SUPABASE_STORAGE_ACCESS_KEY="your_service_role_key" \
    SUPABASE_STORAGE_SECRET_KEY="your_service_role_key" \
    SUPABASE_STORAGE_BUCKET="laravel-storage" \
    SUPABASE_STORAGE_ENDPOINT="https://your-project-ref.supabase.co/storage/v1/s3" \
    SUPABASE_STORAGE_URL="https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage" \
    SUPABASE_STORAGE_REGION="us-east-1"
```

## Step 6: Run Database Migrations

After deployment, run migrations:

```bash
az webapp ssh \
  --resource-group AmazingInventory \
  --name amazinginventory-app

# In SSH session:
cd /home/site/wwwroot
php artisan migrate --force
```

Or run locally pointing to Supabase:

```bash
php artisan migrate --force
```

## Step 7: Verify Storage Configuration

Test file upload:

1. Upload a file through your application
2. Check Supabase Dashboard > Storage > `laravel-storage` bucket
3. Verify file appears and is accessible

## Step 8: Configure Database Connection Pooling

For production, Supabase recommends using connection pooling:

### Option 1: Transaction Pooler (Recommended)
- **Port**: `6543`
- **User**: `postgres.your-project-ref`
- **Max connections**: Higher limit, better for web apps

### Option 2: Session Pooler
- **Port**: `5432`
- **User**: `postgres.your-project-ref`
- **Max connections**: Lower limit, for admin tools

### Option 3: Direct Connection (Not Recommended for Production)
- **Port**: `5432`
- **User**: `postgres`
- **Max connections**: Very limited

## Troubleshooting

### Database Connection Issues

**Error: "Connection refused"**
- Verify firewall rules in Supabase (should allow all IPs for Azure)
- Check host and port are correct
- Ensure SSL mode is set to `require`

**Error: "Too many connections"**
- Switch to connection pooler (port 6543)
- Update username to `postgres.your-project-ref`

**Error: "SSL connection required"**
- Set `DB_SSLMODE=require` in environment variables

### Storage Issues

**Error: "Access Denied"**
- Verify bucket is public or policies are set correctly
- Check service role key is correct
- Ensure bucket name matches configuration

**Error: "Endpoint not found"**
- Verify `SUPABASE_STORAGE_ENDPOINT` is correct
- Check project reference in URL

**Files not accessible publicly**
- Ensure bucket is set to public
- Check `SUPABASE_STORAGE_URL` is correct
- Verify file permissions in Supabase dashboard

### Performance Optimization

1. **Use Connection Pooling**: Always use transaction pooler in production
2. **Enable CDN**: Supabase Storage can be served via CDN
3. **Optimize Queries**: Use indexes and proper query optimization
4. **Monitor Usage**: Check Supabase dashboard for usage metrics

## Security Best Practices

1. **Never commit credentials**: Store all keys in Azure App Settings
2. **Use Service Role Key carefully**: Only use in server-side code
3. **Set up RLS (Row Level Security)**: If using Supabase Auth
4. **Use Anon Key for public access**: Use service role key only when necessary
5. **Enable SSL**: Always use `DB_SSLMODE=require`
6. **Set storage policies**: Configure appropriate access policies

## Additional Resources

- [Supabase Documentation](https://supabase.com/docs)
- [Supabase Storage Guide](https://supabase.com/docs/guides/storage)
- [PostgreSQL Connection Pooling](https://supabase.com/docs/guides/database/connecting-to-postgres#connection-pooler)
- [Supabase S3 API](https://supabase.com/docs/guides/storage/s3-upload)

## Support

- **Supabase Support**: [supabase.com/support](https://supabase.com/support)
- **Supabase Discord**: [discord.supabase.com](https://discord.supabase.com)
- **Supabase GitHub**: [github.com/supabase/supabase](https://github.com/supabase/supabase)

