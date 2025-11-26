# Render Quick Start Guide

Quick reference for deploying to Render with Supabase.

## Prerequisites Checklist

- [ ] Render account created
- [ ] Supabase project created
- [ ] Supabase database password saved
- [ ] Supabase API keys saved (anon key + service role key)
- [ ] Supabase storage bucket `laravel-storage` created
- [ ] Git repository ready

## Deployment Steps

### 1. Connect Repository to Render

1. Go to [Render Dashboard](https://dashboard.render.com)
2. Click **"New +"** → **"Blueprint"**
3. Connect your Git repository
4. Render will detect `render.yaml` and create the web service

### 2. Configure Environment Variables

Go to your web service → **Environment** tab and add:

#### Required Variables

```
APP_URL=https://amazinginventory-web.onrender.com
```

#### Supabase Database
```
DB_HOST=db.your-project-ref.supabase.co
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.your-project-ref
DB_PASSWORD=your_supabase_password
```

#### Supabase API
```
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_ANON_KEY=your_anon_key
SUPABASE_SERVICE_ROLE_KEY=your_service_role_key
SUPABASE_PROJECT_REF=your-project-ref
```

#### Supabase Storage
```
SUPABASE_STORAGE_ACCESS_KEY=your_service_role_key
SUPABASE_STORAGE_SECRET_KEY=your_service_role_key
SUPABASE_STORAGE_ENDPOINT=https://your-project-ref.supabase.co/storage/v1/s3
SUPABASE_STORAGE_URL=https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage
```

#### Filament Admin
```
FILAMENT_ADMIN_EMAIL=admin@example.com
FILAMENT_ADMIN_PASSWORD=your_secure_password
```

### 3. Deploy

- Render will automatically deploy after connecting the repository
- Monitor build logs in the Render dashboard
- First deployment may take 5-10 minutes

### 4. Post-Deployment

1. **Verify deployment** - Visit your Render URL
2. **Check migrations** - They run automatically during build
3. **Seed database** (first time only):
   ```bash
   # Using Render Shell
   php artisan db:seed --force
   ```
4. **Access admin panel** - Go to `/admin` and login with Filament credentials

## Where to Find Supabase Values

### Database Connection
- **Location:** Supabase Dashboard → Project Settings → Database
- **Host:** `db.your-project-ref.supabase.co`
- **Port:** `6543` (pooler) or `5432` (direct)
- **Database:** `postgres`
- **User:** `postgres.your-project-ref`
- **Password:** The one you set when creating the project

### API Keys
- **Location:** Supabase Dashboard → Project Settings → API
- **Project URL:** `https://your-project-ref.supabase.co`
- **anon key:** Public anonymous key
- **service_role key:** Secret key (keep secure!)

### Storage
- **Location:** Supabase Dashboard → Storage
- **Bucket:** Create `laravel-storage` bucket (make it public)
- **Endpoint:** `https://your-project-ref.supabase.co/storage/v1/s3`
- **Public URL:** `https://your-project-ref.supabase.co/storage/v1/object/public/laravel-storage`

## Troubleshooting

### Build Fails
- Check build logs for specific errors
- Verify all environment variables are set
- Ensure `render-build.sh` is in repository

### Database Connection Error
- Verify Supabase project is active
- Check `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD` are correct
- Ensure `DB_SSLMODE=require` is set
- Try port `5432` if `6543` doesn't work

### Assets Not Loading
- Verify `APP_URL` matches your Render URL exactly
- Check build logs for Vite build errors
- Clear cache: `php artisan cache:clear`

### Storage Not Working
- Verify `laravel-storage` bucket exists in Supabase
- Check all `SUPABASE_STORAGE_*` variables are set
- Ensure bucket is public (if files need public access)

## Support

- Full documentation: See `RENDER_DEPLOYMENT.md`
- Render Docs: https://render.com/docs
- Supabase Docs: https://supabase.com/docs

