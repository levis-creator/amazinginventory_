# Railway Deployment Guide

This guide will help you deploy the Amazing Inventory Laravel application to Railway.

## Prerequisites

- A Railway account (sign up at [railway.app](https://railway.app))
- GitHub repository with your code (or use Railway's GitHub integration)
- Basic understanding of environment variables

## Quick Start

### 1. Create a New Project on Railway

1. Go to [railway.app](https://railway.app) and sign in
2. Click "New Project"
3. Select "Deploy from GitHub repo" (recommended) or "Empty Project"
4. If using GitHub, select your repository

### 2. Configure the Service

Railway will automatically detect the `Dockerfile` and configure the service. The deployment uses:

- **Multi-stage Docker build** for optimized image size
- **PHP 8.3 FPM** with **Nginx** for production performance
- **Supervisor** to manage PHP-FPM and Nginx processes
- **Automatic migrations** on startup via entrypoint script

### 3. Set Environment Variables

Configure the following environment variables in Railway's dashboard:

#### Required Variables

```bash
APP_NAME="Amazing Inventory"
APP_ENV=production
APP_KEY=                    # Generate with: php artisan key:generate --show
APP_DEBUG=false
APP_URL=                    # Will be set automatically if using Railway's public domain
```

#### Database Configuration

**Option 1: Use Railway PostgreSQL (Recommended)**

Railway can automatically provision a PostgreSQL database. After creating it:

1. Add the PostgreSQL service to your project
2. Railway will automatically set these variables:
   - `DATABASE_URL`
   - `PGHOST`
   - `PGPORT`
   - `PGUSER`
   - `PGPASSWORD`
   - `PGDATABASE`

3. Set in your Laravel service:
   ```bash
   DB_CONNECTION=pgsql
   DB_HOST=${{Postgres.PGHOST}}
   DB_PORT=${{Postgres.PGPORT}}
   DB_DATABASE=${{Postgres.PGDATABASE}}
   DB_USERNAME=${{Postgres.PGUSER}}
   DB_PASSWORD=${{Postgres.PGPASSWORD}}
   ```

**Option 2: Use SQLite (Development/Testing)**

```bash
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite
```

**Option 3: External Database**

```bash
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

#### System Database Configuration

If using a separate system database:

```bash
SYSTEM_DB_CONNECTION=pgsql
SYSTEM_DB_HOST=${{Postgres.PGHOST}}
SYSTEM_DB_PORT=${{Postgres.PGPORT}}
SYSTEM_DB_DATABASE=system_db
SYSTEM_DB_USERNAME=${{Postgres.PGUSER}}
SYSTEM_DB_PASSWORD=${{Postgres.PGPASSWORD}}
```

Or for SQLite:

```bash
SYSTEM_DB_CONNECTION=sqlite
SYSTEM_DB_DATABASE=/var/www/html/database/system.sqlite
```

#### Filament Admin Configuration

```bash
FILAMENT_ADMIN_EMAIL=admin@example.com
FILAMENT_ADMIN_PASSWORD=your-secure-password
FILAMENT_ADMIN_NAME="Admin User"
```

#### Optional Variables

```bash
SEED_DATABASE=true                    # Set to false to skip seeding on startup
LOG_CHANNEL=stack
LOG_LEVEL=info
QUEUE_CONNECTION=database             # Or 'redis' if using Redis
SESSION_DRIVER=database               # Or 'redis' if using Redis
CACHE_DRIVER=database                 # Or 'redis' if using Redis
```

### 4. Enable Public Networking

1. In your service settings, go to "Networking"
2. Click "Generate Domain" to get a public URL
3. Railway will automatically set `RAILWAY_PUBLIC_DOMAIN`
4. Set `APP_URL` to match your Railway domain:
   ```bash
   APP_URL=https://your-app-name.up.railway.app
   ```

### 5. Deploy

Railway will automatically:
1. Build the Docker image using the `Dockerfile`
2. Run the entrypoint script which:
   - Sets up databases (SQLite files if using SQLite)
   - Runs migrations
   - Seeds the database (if `SEED_DATABASE=true`)
   - Clears and caches configuration
   - Optimizes Laravel for production
3. Start PHP-FPM and Nginx via Supervisor

### 6. Verify Deployment

1. Check the deployment logs in Railway dashboard
2. Visit your public URL: `https://your-app-name.up.railway.app`
3. Check health endpoint: `https://your-app-name.up.railway.app/api/health`
4. Access Filament admin: `https://your-app-name.up.railway.app/admin`

## Architecture

### Docker Multi-Stage Build

The `Dockerfile` uses a two-stage build:

1. **Builder Stage**: Installs dependencies, builds assets, runs Laravel setup
2. **Production Stage**: Minimal runtime image with PHP-FPM, Nginx, and Supervisor

### Process Management

Supervisor manages:
- **PHP-FPM**: Handles PHP requests
- **Nginx**: Serves static files and proxies PHP requests

### Port Configuration

Railway provides the `PORT` environment variable. The startup script (`start-railway.sh`) automatically configures Nginx to listen on this port.

## Health Checks

The application includes a health check endpoint at `/api/health` that returns:

```json
{
  "status": "ok",
  "timestamp": "2025-01-27T12:00:00Z",
  "service": "Amazing Inventory API"
}
```

Railway uses this for health monitoring.

## Database Migrations

Migrations run automatically on startup via `docker-entrypoint.sh`:

1. System database migrations (if using separate system DB)
2. Application database migrations
3. Database seeding (if `SEED_DATABASE=true`)

## Troubleshooting

### Build Fails

- Check that all required files are in the repository
- Verify `composer.json` and `package.json` are valid
- Check build logs for specific errors

### Application Won't Start

- Check environment variables are set correctly
- Verify database connection settings
- Check application logs: `railway logs`

### Database Connection Issues

- Verify database service is running
- Check database credentials in environment variables
- For PostgreSQL, ensure the database service is linked to your app service

### Assets Not Loading

- Verify `APP_URL` is set correctly
- Check that `npm run build` completed successfully
- Ensure `public/build/manifest.json` exists

### 502 Bad Gateway

- Check PHP-FPM is running: `railway logs`
- Verify Nginx configuration
- Check file permissions on storage directories

### Admin User Not Created

- Verify `FILAMENT_ADMIN_EMAIL` is set
- Check seeder logs in deployment output
- Manually create admin user if needed:
  ```bash
  railway run php artisan tinker
  # Then create user manually
  ```

## Environment Variables Reference

### Application

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_NAME` | Application name | `Amazing Inventory` |
| `APP_ENV` | Environment | `production` |
| `APP_KEY` | Encryption key | Generated by `php artisan key:generate` |
| `APP_DEBUG` | Debug mode | `false` |
| `APP_URL` | Application URL | `https://your-app.up.railway.app` |

### Database

| Variable | Description | Example |
|----------|-------------|---------|
| `DB_CONNECTION` | Database driver | `pgsql` or `sqlite` |
| `DB_HOST` | Database host | `${{Postgres.PGHOST}}` |
| `DB_PORT` | Database port | `${{Postgres.PGPORT}}` |
| `DB_DATABASE` | Database name | `${{Postgres.PGDATABASE}}` |
| `DB_USERNAME` | Database user | `${{Postgres.PGUSER}}` |
| `DB_PASSWORD` | Database password | `${{Postgres.PGPASSWORD}}` |

### Filament

| Variable | Description | Example |
|----------|-------------|---------|
| `FILAMENT_ADMIN_EMAIL` | Admin email | `admin@example.com` |
| `FILAMENT_ADMIN_PASSWORD` | Admin password | `SecurePassword123!` |
| `FILAMENT_ADMIN_NAME` | Admin name | `Admin User` |

### Deployment

| Variable | Description | Default |
|----------|-------------|---------|
| `SEED_DATABASE` | Run seeders on startup | `true` |
| `PORT` | Server port (set by Railway) | `80` |

## Custom Domain

To use a custom domain:

1. Go to your service settings → Networking
2. Click "Add Custom Domain"
3. Follow Railway's instructions to configure DNS
4. Update `APP_URL` to match your custom domain

## Scaling

Railway supports horizontal scaling:

1. Go to service settings
2. Adjust the number of instances
3. Railway will automatically load balance between instances

**Note**: Ensure your database and session storage support multiple instances (use PostgreSQL/Redis, not SQLite).

## Monitoring

Railway provides:

- **Deployment logs**: View build and runtime logs
- **Metrics**: CPU, memory, and network usage
- **Health checks**: Automatic health monitoring

## Cost Optimization

- Use Railway's free tier for development
- Monitor resource usage in Railway dashboard
- Optimize Docker image size (already optimized with multi-stage build)
- Use Railway's PostgreSQL for better performance than SQLite

## Support

- Railway Documentation: [docs.railway.app](https://docs.railway.app)
- Railway Discord: [discord.gg/railway](https://discord.gg/railway)
- Application Issues: Check deployment logs and application logs

## Additional Resources

- [Railway Quick Start](https://docs.railway.app/develop/cli)
- [Railway Environment Variables](https://docs.railway.app/develop/variables)
- [Laravel Deployment](https://laravel.com/docs/deployment)

