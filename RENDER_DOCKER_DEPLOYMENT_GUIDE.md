# Complete Render Docker Deployment Guide

Step-by-step guide to deploy your Laravel application to Render using Docker.

## Prerequisites Checklist

Before starting, ensure you have:

- [ ] **Supabase Account** - Sign up at [supabase.com](https://supabase.com)
- [ ] **Supabase Project Created** - With database and storage configured
- [ ] **Render Account** - Sign up at [render.com](https://render.com)
- [ ] **Git Repository** - Your code pushed to GitHub, GitLab, or Bitbucket
- [ ] **Supabase Credentials** - Database password, API keys saved

---

## Step 1: Set Up Supabase (If Not Done)

### 1.1 Create Supabase Project

1. Go to [Supabase Dashboard](https://app.supabase.com)
2. Click **"New Project"**
3. Fill in:
   - **Name:** Amazing Inventory (or your choice)
   - **Database Password:** Create a strong password ⚠️ **SAVE THIS!**
   - **Region:** Choose closest to your users
4. Click **"Create new project"**
5. Wait 2-3 minutes for provisioning

### 1.2 Get Database Connection Details

1. Go to **Project Settings** → **Database**
2. Find **Connection string** section
3. Note these values:
   - **Host:** `db.your-project-ref.supabase.co`
   - **Port:** `6543` (for pooler - recommended) or `5432` (direct)
   - **Database:** `postgres`
   - **User:** `postgres.your-project-ref`
   - **Password:** The one you created

### 1.3 Get API Keys

1. Go to **Project Settings** → **API**
2. Copy these values:
   - **Project URL:** `https://your-project-ref.supabase.co`
   - **anon key:** (Public key)
   - **service_role key:** (Secret key - keep secure!)
   - **Project Reference:** `your-project-ref` (from URL)

### 1.4 Create Storage Bucket

1. Go to **Storage** in Supabase Dashboard
2. Click **"New bucket"**
3. Configure:
   - **Name:** `laravel-storage`
   - **Public bucket:** ✅ **Enable** (check this!)
   - **File size limit:** 10MB (or your preference)
4. Click **"Create bucket"**

### 1.5 Configure Storage Policies (Optional)

1. Go to **Storage** → **Policies**
2. Select `laravel-storage` bucket
3. Create policies for appropriate access (public read, authenticated write)

---

## Step 2: Prepare Your Code

### 2.1 Verify Files Are Ready

Make sure these files are in your repository:

- ✅ `Dockerfile` (created)
- ✅ `render.yaml` (created)
- ✅ `.dockerignore` (created)
- ✅ `composer.json`
- ✅ `package.json`

### 2.2 Commit and Push to Git

```bash
# Check what files need to be committed
git status

# Add new files
git add Dockerfile render.yaml .dockerignore

# Commit
git commit -m "Add Render Docker deployment configuration"

# Push to your repository
git push origin main
# or
git push origin master
```

---

## Step 3: Deploy to Render

### Option A: Using Blueprint (Recommended - Easiest)

#### 3.1 Connect Repository

1. Go to [Render Dashboard](https://dashboard.render.com)
2. Click **"New +"** button (top right)
3. Select **"Blueprint"**
4. Connect your Git provider (GitHub, GitLab, or Bitbucket)
5. Select your repository: `amazinginventory` (or your repo name)
6. Click **"Apply"**

#### 3.2 Review Blueprint

Render will detect `render.yaml` and show:
- **Service:** `amazinginventory-web`
- **Type:** Web Service
- **Runtime:** Docker

Click **"Apply"** to create the service.

#### 3.3 Configure Environment Variables

After the service is created:

1. Go to your web service: **amazinginventory-web**
2. Click on **"Environment"** tab
3. Add these **required** environment variables:

**Application:**
```
APP_URL=https://amazinginventory-web.onrender.com
```
*(Replace with your actual Render URL after first deploy)*

**Supabase Database:**
```
DB_HOST=db.your-project-ref.supabase.co
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.your-project-ref
DB_PASSWORD=your_supabase_database_password
DB_SSLMODE=require
```

**Supabase API:**
```
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_ANON_KEY=your_anon_key_here
SUPABASE_SERVICE_ROLE_KEY=your_service_role_key_here
SUPABASE_PROJECT_REF=your-project-ref
```

**Supabase Storage:**
```
SUPABASE_STORAGE_ACCESS_KEY=your_service_role_key_here
SUPABASE_STORAGE_SECRET_KEY=your_service_role_key_here
SUPABASE_STORAGE_ENDPOINT=https://your-project-ref.supabase.co/storage/v1/s3
SUPABASE_STORAGE_URL=https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage
SUPABASE_STORAGE_BUCKET=laravel-storage
SUPABASE_STORAGE_REGION=us-east-1
```

**Filament Admin:**
```
FILAMENT_ADMIN_EMAIL=admin@example.com
FILAMENT_ADMIN_PASSWORD=your_secure_password_here
FILAMENT_ADMIN_NAME=Admin User
```

4. Click **"Save Changes"**

#### 3.4 First Deployment

1. Go to **"Events"** tab to watch the build
2. Render will automatically start building
3. First build takes 5-10 minutes
4. Watch the logs for any errors

### Option B: Manual Setup (Alternative)

#### 3.1 Create Web Service

1. Go to [Render Dashboard](https://dashboard.render.com)
2. Click **"New +"** → **"Web Service"**
3. Connect your Git repository
4. Select your repository

#### 3.2 Configure Service

Fill in the form:

- **Name:** `amazinginventory-web`
- **Region:** Choose closest to your users
- **Branch:** `main` or `master`
- **Root Directory:** Leave empty
- **Runtime:** Select **"Docker"** ⚠️ (Not PHP!)
- **Dockerfile Path:** `./Dockerfile` (or leave empty if in root)
- **Docker Context:** `.` (root directory)
- **Docker Command:** `php artisan serve --host=0.0.0.0 --port=$PORT`
- **Plan:** Starter (or higher)

#### 3.3 Add Environment Variables

Same as Option A, Step 3.3 above.

#### 3.4 Create Service

1. Click **"Create Web Service"**
2. Render will start building immediately

---

## Step 4: Monitor First Deployment

### 4.1 Watch Build Logs

1. Go to your service → **"Events"** or **"Logs"** tab
2. Watch for:
   - ✅ Docker image building
   - ✅ Composer installing dependencies
   - ✅ NPM installing packages
   - ✅ Assets building
   - ✅ Service starting

### 4.2 Common First-Time Issues

**If build fails:**
- Check logs for specific error
- Verify all environment variables are set
- Ensure Supabase project is active
- Check Dockerfile syntax

**If service won't start:**
- Verify `APP_KEY` is set (auto-generated by Render)
- Check database connection variables
- Ensure `APP_URL` matches your Render URL

---

## Step 5: Post-Deployment Setup

### 5.1 Get Your Render URL

1. After successful deployment, go to your service
2. Your URL will be: `https://amazinginventory-web.onrender.com`
3. **Update `APP_URL` environment variable** to match this exact URL

### 5.2 Run Database Migrations

Migrations run automatically during build, but verify:

1. Go to your service → **"Shell"** tab
2. Run:
```bash
php artisan migrate:status
```

If migrations are pending:
```bash
php artisan migrate --force
```

### 5.3 Seed Database (First Time Only)

1. In Render Shell, run:
```bash
php artisan db:seed --force
```

This creates:
- Default permissions and roles
- Admin user (from `FILAMENT_ADMIN_EMAIL` and `FILAMENT_ADMIN_PASSWORD`)

### 5.4 Verify Application

1. Visit your Render URL: `https://amazinginventory-web.onrender.com`
2. Check if the homepage loads
3. Access admin panel: `https://amazinginventory-web.onrender.com/admin`
4. Login with Filament credentials from environment variables

---

## Step 6: Verify Everything Works

### 6.1 Test Checklist

- [ ] Homepage loads correctly
- [ ] Admin panel accessible at `/admin`
- [ ] Can login with Filament credentials
- [ ] Database connection works (check any data pages)
- [ ] File uploads work (if applicable)
- [ ] Assets (CSS/JS) load correctly
- [ ] No errors in Render logs

### 6.2 Check Logs

1. Go to service → **"Logs"** tab
2. Look for any errors or warnings
3. Common issues:
   - Database connection errors → Check Supabase credentials
   - Asset loading issues → Verify `APP_URL` is correct
   - Storage errors → Check Supabase Storage configuration

---

## Troubleshooting

### Build Fails

**Error: "Cannot find Dockerfile"**
- Ensure `Dockerfile` is in repository root
- Check it's committed and pushed

**Error: "Composer install failed"**
- Check `composer.json` is valid
- Verify all dependencies are available

**Error: "npm build failed"**
- Check `package.json` is valid
- Verify Node.js version in Dockerfile (20.x)

### Service Won't Start

**Error: "Port already in use"**
- Dockerfile should use `$PORT` variable
- Start command should be: `php artisan serve --host=0.0.0.0 --port=$PORT`

**Error: "APP_KEY not set"**
- Should auto-generate, but can manually set:
  - Generate: `php artisan key:generate --show`
  - Add to environment variables

### Database Connection Issues

**Error: "Connection refused"**
- Verify Supabase project is active
- Check `DB_HOST` is correct format: `db.your-project-ref.supabase.co`
- Verify `DB_USERNAME` includes project ref: `postgres.your-project-ref`
- Check `DB_PASSWORD` is correct
- Ensure `DB_SSLMODE=require` is set

**Error: "SSL connection required"**
- Add `DB_SSLMODE=require` to environment variables

### Assets Not Loading

**CSS/JS files return 404**
- Verify `APP_URL` matches your Render URL exactly
- Check build completed successfully (look for `public/build/manifest.json`)
- Clear cache: In Shell, run `php artisan cache:clear`

### Storage Not Working

**File uploads fail**
- Verify `laravel-storage` bucket exists in Supabase
- Check bucket is public (if files need public access)
- Verify all `SUPABASE_STORAGE_*` variables are set
- Check storage policies in Supabase Dashboard

---

## Environment Variables Quick Reference

Copy-paste ready list (replace placeholders):

```
APP_URL=https://amazinginventory-web.onrender.com
DB_HOST=db.your-project-ref.supabase.co
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.your-project-ref
DB_PASSWORD=your_password
DB_SSLMODE=require
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_ANON_KEY=your_anon_key
SUPABASE_SERVICE_ROLE_KEY=your_service_role_key
SUPABASE_PROJECT_REF=your-project-ref
SUPABASE_STORAGE_ACCESS_KEY=your_service_role_key
SUPABASE_STORAGE_SECRET_KEY=your_service_role_key
SUPABASE_STORAGE_ENDPOINT=https://your-project-ref.supabase.co/storage/v1/s3
SUPABASE_STORAGE_URL=https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage
SUPABASE_STORAGE_BUCKET=laravel-storage
SUPABASE_STORAGE_REGION=us-east-1
FILAMENT_ADMIN_EMAIL=admin@example.com
FILAMENT_ADMIN_PASSWORD=your_secure_password
FILAMENT_ADMIN_NAME=Admin User
```

---

## Next Steps After Deployment

1. **Set up custom domain** (optional)
   - Go to service → **Settings** → **Custom Domains**
   - Add your domain
   - Update DNS records
   - Update `APP_URL` environment variable

2. **Enable auto-deploy** (already enabled by default)
   - Every push to your branch triggers a new deployment

3. **Set up monitoring**
   - Render provides basic metrics
   - Consider adding error tracking (Sentry, etc.)

4. **Backup strategy**
   - Supabase provides automatic backups
   - Consider additional backup solutions if needed

---

## Support Resources

- **Render Documentation:** https://render.com/docs
- **Laravel Documentation:** https://laravel.com/docs
- **Supabase Documentation:** https://supabase.com/docs
- **Filament Documentation:** https://filamentphp.com/docs

---

## Quick Commands Reference

**Render Shell Commands:**
```bash
# Check migration status
php artisan migrate:status

# Run migrations
php artisan migrate --force

# Seed database
php artisan db:seed --force

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Generate app key (if needed)
php artisan key:generate --show
```

Good luck with your deployment! 🚀

