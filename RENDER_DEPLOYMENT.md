# Render Deployment Guide

This guide will help you deploy the Amazing Inventory Laravel application to Render using **Supabase** for database and storage.

## Prerequisites

- A Render account (sign up at [render.com](https://render.com))
- A Supabase account with a project created (sign up at [supabase.com](https://supabase.com))
- Git repository with your code (GitHub, GitLab, or Bitbucket)
- Basic understanding of Laravel and environment variables

## Supabase Setup

Before deploying to Render, you need to set up Supabase:

### 1. Create Supabase Project

1. Go to [Supabase Dashboard](https://app.supabase.com)
2. Click "New Project"
3. Fill in:
   - **Name:** Amazing Inventory (or your preferred name)
   - **Database Password:** Create a strong password (save this!)
   - **Region:** Choose closest to your users
4. Click "Create new project"
5. Wait for project to be provisioned (2-3 minutes)

### 2. Get Supabase Connection Details

1. Go to **Project Settings** → **Database**
2. Note the connection details:
   - **Host:** `db.your-project-ref.supabase.co`
   - **Port:** `6543` (for connection pooler) or `5432` (for direct connection)
   - **Database:** `postgres`
   - **User:** `postgres.your-project-ref`
   - **Password:** The password you set during project creation

### 3. Get Supabase API Keys

1. Go to **Project Settings** → **API**
2. Note:
   - **Project URL:** `https://your-project-ref.supabase.co`
   - **anon key:** Public anonymous key
   - **service_role key:** Secret service role key (keep this secure!)

### 4. Set Up Supabase Storage

1. Go to **Storage** in Supabase Dashboard
2. Click **New bucket**
3. Configure:
   - **Name:** `laravel-storage`
   - **Public bucket:** ✅ Enable (for public file access)
   - **File size limit:** Set appropriate limit (e.g., 10MB)
4. Click **Create bucket**

### 5. Configure Storage Policies (Optional but Recommended)

1. Go to **Storage** → **Policies**
2. Select `laravel-storage` bucket
3. Create policies for appropriate access control

## Quick Start

### Option 1: Using Render Blueprint (Recommended)

1. **Connect your repository to Render:**
   - Go to [Render Dashboard](https://dashboard.render.com)
   - Click "New +" → "Blueprint"
   - Connect your Git repository
   - Render will automatically detect `render.yaml` and create the services

2. **Configure Environment Variables:**
   After the services are created, go to your web service and add these **required** environment variables:

   **Application:**
   - `APP_URL` - Your Render web service URL (e.g., `https://amazinginventory-web.onrender.com`)

   **Supabase Database:**
   - `DB_HOST` - `db.your-project-ref.supabase.co`
   - `DB_PORT` - `6543` (pooler) or `5432` (direct)
   - `DB_DATABASE` - `postgres`
   - `DB_USERNAME` - `postgres.your-project-ref`
   - `DB_PASSWORD` - Your Supabase database password

   **Supabase API:**
   - `SUPABASE_URL` - `https://your-project-ref.supabase.co`
   - `SUPABASE_ANON_KEY` - Your Supabase anon key
   - `SUPABASE_SERVICE_ROLE_KEY` - Your Supabase service role key
   - `SUPABASE_PROJECT_REF` - Your Supabase project reference

   **Supabase Storage:**
   - `SUPABASE_STORAGE_ACCESS_KEY` - Your Supabase service role key
   - `SUPABASE_STORAGE_SECRET_KEY` - Your Supabase service role key
   - `SUPABASE_STORAGE_ENDPOINT` - `https://your-project-ref.supabase.co/storage/v1/s3`
   - `SUPABASE_STORAGE_URL` - `https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage`

   **Filament Admin:**
   - `FILAMENT_ADMIN_EMAIL` - Admin email (e.g., `admin@example.com`)
   - `FILAMENT_ADMIN_PASSWORD` - Strong password for admin user

3. **Deploy:**
   - Render will automatically deploy when you connect the repository
   - Monitor the build logs in the Render dashboard
   - Once deployed, your app will be available at the provided URL

### Option 2: Manual Setup

1. **Create Web Service:**
   - Go to Render Dashboard → "New +" → "Web Service"
   - Connect your Git repository
   - Configure:
     - **Name:** `amazinginventory-web`
     - **Runtime:** **Docker** (PHP is not available in the dropdown, so use Docker)
     - **Dockerfile Path:** `./Dockerfile` (or leave empty if Dockerfile is in root)
     - **Docker Context:** `.` (root directory)
     - **Docker Command:** `php artisan serve --host=0.0.0.0 --port=$PORT`
     - **Plan:** Starter (or higher)
   
   **Important:** The Dockerfile will automatically:
   - Install PHP 8.2 with required extensions
   - Install Composer and Node.js
   - Build your application assets
   - Set up Laravel storage

2. **Configure Environment Variables:**
   Add the following environment variables in the Render dashboard:

   **Application Settings:**
   ```
   APP_NAME=Laravel
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-service-name.onrender.com
   LOG_CHANNEL=stack
   LOG_LEVEL=error
   ```

   **Supabase Database Settings:**
   ```
   DB_CONNECTION=pgsql
   DB_HOST=db.your-project-ref.supabase.co
   DB_PORT=6543
   DB_DATABASE=postgres
   DB_USERNAME=postgres.your-project-ref
   DB_PASSWORD=your_supabase_database_password
   DB_SSLMODE=require
   ```

   **Supabase API Configuration:**
   ```
   SUPABASE_URL=https://your-project-ref.supabase.co
   SUPABASE_ANON_KEY=your_supabase_anon_key
   SUPABASE_SERVICE_ROLE_KEY=your_supabase_service_role_key
   SUPABASE_PROJECT_REF=your-project-ref
   ```

   **Supabase Storage Configuration:**
   ```
   FILESYSTEM_DISK=supabase
   SUPABASE_STORAGE_ACCESS_KEY=your_supabase_service_role_key
   SUPABASE_STORAGE_SECRET_KEY=your_supabase_service_role_key
   SUPABASE_STORAGE_BUCKET=laravel-storage
   SUPABASE_STORAGE_ENDPOINT=https://your-project-ref.supabase.co/storage/v1/s3
   SUPABASE_STORAGE_URL=https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage
   SUPABASE_STORAGE_REGION=us-east-1
   ```

   **Session & Cache:**
   ```
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   ```

   **Filament Admin:**
   ```
   FILAMENT_ADMIN_EMAIL=admin@example.com
   FILAMENT_ADMIN_PASSWORD=your-secure-password
   FILAMENT_ADMIN_NAME=Admin User
   ```

   **Other Settings:**
   ```
   BROADCAST_CONNECTION=log
   MAIL_MAILER=log
   L5_SWAGGER_GENERATE_ALWAYS=false
   ```

3. **Generate APP_KEY:**
   - `APP_KEY` will be auto-generated by Render (configured in `render.yaml`)
   - Or manually add it: Generate with `php artisan key:generate --show` and add to environment variables

5. **Deploy:**
   - Click "Create Web Service"
   - Render will build and deploy your application
   - Monitor build logs for any issues

## Post-Deployment Steps

### 1. Run Database Migrations

**Migrations run automatically during deployment** via `docker-entrypoint.sh`, which:
- Creates system database file (if using SQLite)
- Runs system database migrations first
- Runs application database migrations
- Seeds the database (if `SEED_DATABASE=true`)

#### For Free Tier (No Shell Access)

If you're on Render's free tier and don't have shell access, you can run migrations via web routes:

**Option 1: Check Migration Status**
```http
GET https://your-app.onrender.com/admin/migrations/status
Authorization: Bearer {your_admin_token}
```

**Option 2: Run System Migrations**
```http
POST https://your-app.onrender.com/admin/migrations/system
Authorization: Bearer {your_admin_token}
```

**Option 3: Run All Migrations**
```http
POST https://your-app.onrender.com/admin/migrations/all
Authorization: Bearer {your_admin_token}
```

**Note:** These routes require admin authentication. You must be logged in as an admin user.

#### For Paid Tier (With Shell Access)

If you have shell access, you can verify migrations:

```bash
# Using Render Shell
php artisan migrate:status
php artisan migrate --force
php artisan migrate --database=system --path=database/migrations/system --force
```

### 2. Seed Database (First Time Only)

Seeding runs automatically during deployment if `SEED_DATABASE=true` (default).

If you need to seed manually:

**Free Tier (Web Route):**
- Seeding happens automatically during deployment
- Or trigger via redeploy

**Paid Tier (Shell):**
```bash
# Using Render Shell
php artisan db:seed --force
```

This will create:
- Default permissions and roles
- Admin user (from `FILAMENT_ADMIN_EMAIL` and `FILAMENT_ADMIN_PASSWORD`)

### 3. Verify Application

1. Visit your Render URL
2. Access Filament admin panel at `/admin`
3. Login with credentials from environment variables
4. Verify all features are working
5. Check `/admin/database-configurations` to ensure system migrations ran successfully

## Environment Variables Reference

### Required Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_URL` | Your application URL | `https://amazinginventory-web.onrender.com` |
| `APP_KEY` | Laravel encryption key | Auto-generated by Render |
| `DB_HOST` | Supabase database host | `db.your-project-ref.supabase.co` |
| `DB_PORT` | Supabase database port | `6543` (pooler) or `5432` (direct) |
| `DB_DATABASE` | Database name | `postgres` |
| `DB_USERNAME` | Database user | `postgres.your-project-ref` |
| `DB_PASSWORD` | Supabase database password | Your Supabase password |
| `SUPABASE_URL` | Supabase project URL | `https://your-project-ref.supabase.co` |
| `SUPABASE_ANON_KEY` | Supabase anonymous key | From Supabase dashboard |
| `SUPABASE_SERVICE_ROLE_KEY` | Supabase service role key | From Supabase dashboard |
| `SUPABASE_PROJECT_REF` | Supabase project reference | `your-project-ref` |
| `SUPABASE_STORAGE_ACCESS_KEY` | Storage access key | Same as service role key |
| `SUPABASE_STORAGE_SECRET_KEY` | Storage secret key | Same as service role key |
| `SUPABASE_STORAGE_ENDPOINT` | Storage S3 endpoint | `https://your-project-ref.supabase.co/storage/v1/s3` |
| `SUPABASE_STORAGE_URL` | Storage public URL | `https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage` |
| `FILAMENT_ADMIN_EMAIL` | Admin email | `admin@example.com` |
| `FILAMENT_ADMIN_PASSWORD` | Admin password | Strong password |

### Optional Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_DEBUG` | Enable debug mode | `false` |
| `LOG_LEVEL` | Logging level | `error` |
| `SESSION_DRIVER` | Session storage | `database` |
| `CACHE_STORE` | Cache driver | `database` |
| `FILESYSTEM_DISK` | File storage | `supabase` |
| `SUPABASE_STORAGE_BUCKET` | Storage bucket name | `laravel-storage` |
| `SUPABASE_STORAGE_REGION` | Storage region | `us-east-1` |
| `DB_SSLMODE` | Database SSL mode | `require` |

## File Storage

This application uses **Supabase Storage** for file storage, which provides:

- **Persistent storage** - Files are stored in Supabase, not on Render's ephemeral filesystem
- **S3-compatible API** - Uses Laravel's S3 driver
- **Public file access** - Files can be accessed via public URLs
- **Scalable** - No storage limits on Render's filesystem

### Supabase Storage Configuration

The storage is already configured in `config/filesystems.php` as the `supabase` disk. Ensure:

1. **Bucket exists** - Create `laravel-storage` bucket in Supabase Dashboard
2. **Bucket is public** - Enable public access for the bucket
3. **Environment variables are set** - All `SUPABASE_STORAGE_*` variables are configured
4. **Storage policies** - Configure appropriate policies in Supabase Dashboard

## Background Jobs (Optional)

If you need to process background jobs:

1. Create a new Background Worker service
2. Set start command: `php artisan queue:work --tries=3`
3. Ensure `QUEUE_CONNECTION=database` is set

## SSL/HTTPS

Render provides free SSL certificates automatically. Your app will be available over HTTPS by default.

## Custom Domain

1. Go to your web service settings
2. Click "Custom Domains"
3. Add your domain
4. Update DNS records as instructed
5. Update `APP_URL` environment variable

## Monitoring & Logs

- **Build Logs:** Available during deployment
- **Runtime Logs:** Available in the Render dashboard
- **Metrics:** CPU, Memory, and Request metrics available

## Troubleshooting

### Build Fails

1. Check build logs for specific errors
2. Verify all dependencies in `composer.json` and `package.json`
3. Ensure `render-build.sh` is executable (chmod +x)

### Application Not Starting

1. Check runtime logs
2. Verify `APP_KEY` is set
3. Verify database connection
4. Check `APP_URL` matches your Render URL

### Database Connection Issues

1. Verify Supabase project is active and running
2. Check environment variables are correctly set (especially `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`)
3. Ensure database credentials match Supabase Dashboard
4. Verify `DB_SSLMODE=require` is set (Supabase requires SSL)
5. Check if using correct port (6543 for pooler, 5432 for direct connection)
6. Test connection from Supabase Dashboard → Database → Connection string

### Assets Not Loading

1. Verify `npm run build` completed successfully
2. Check `public/build/manifest.json` exists
3. Ensure `APP_URL` is set correctly and matches your Render URL exactly
4. Clear cache: `php artisan cache:clear`
5. Verify Vite assets are built: Check `public/build/` directory exists

### Storage/File Upload Issues

1. Verify Supabase Storage bucket `laravel-storage` exists
2. Check all `SUPABASE_STORAGE_*` environment variables are set
3. Verify bucket is public (if files need public access)
4. Check storage policies in Supabase Dashboard
5. Test file upload from Supabase Dashboard → Storage

### 500 Errors

1. Check application logs in Render dashboard
2. Enable `APP_DEBUG=true` temporarily (for debugging only)
3. Check Laravel logs: `storage/logs/laravel.log`
4. Verify all required environment variables are set

## Security Best Practices

1. **Never commit `.env` file** - Use Render environment variables
2. **Set `APP_DEBUG=false`** in production
3. **Use strong passwords** for admin accounts
4. **Enable HTTPS** (automatic on Render)
5. **Regular updates** - Keep dependencies updated
6. **Database backups** - Render provides automatic backups for paid plans

## Scaling

- **Starter Plan:** Suitable for development and small applications
- **Standard Plan:** Better performance and more resources
- **Pro Plan:** Production-grade with auto-scaling

## Support

- Render Documentation: https://render.com/docs
- Laravel Documentation: https://laravel.com/docs
- Filament Documentation: https://filamentphp.com/docs

## Additional Resources

- [Render PHP Guide](https://render.com/docs/php)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [Filament Installation](https://filamentphp.com/docs/installation)

