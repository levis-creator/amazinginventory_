# Azure Deployment Checklist (with Supabase)

Use this checklist to ensure your application is ready for Azure App Service deployment with Supabase for database and storage.

## Pre-Deployment Checklist

### Code Preparation
- [ ] All code is committed to version control
- [ ] `.env` file is NOT committed (already in `.gitignore`)
- [ ] All dependencies are listed in `composer.json` and `package.json`
- [ ] Frontend assets are built or build process is configured
- [ ] Database migrations are up to date
- [ ] Application key is generated (will be set in Azure)

### Azure Resources (Hosting)
- [ ] Azure subscription is active
- [ ] Resource group is created
- [ ] App Service Plan is created
- [ ] Web App is created with PHP 8.2 runtime

### Supabase Resources (Database & Storage)
- [ ] Supabase account is created
- [ ] Supabase project is created
- [ ] Database password is set and saved securely
- [ ] Storage bucket `laravel-storage` is created
- [ ] Storage bucket is set to public
- [ ] Storage policies are configured

### Configuration
- [ ] Environment variables are configured in Azure App Settings:
- [ ] `APP_NAME`
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` (generated)
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` (Azure app URL) - **CRITICAL: Must match your actual Azure App Service URL exactly for assets to work**
  - [ ] Supabase database connection settings:
    - [ ] `DB_CONNECTION=pgsql`
    - [ ] `DB_HOST` (Supabase database host)
    - [ ] `DB_PORT` (6543 for pooler or 5432 for direct)
    - [ ] `DB_DATABASE=postgres`
    - [ ] `DB_USERNAME=postgres`
    - [ ] `DB_PASSWORD` (Supabase database password)
    - [ ] `DB_SSLMODE=require`
  - [ ] Supabase Storage settings:
    - [ ] `FILESYSTEM_DISK=supabase`
    - [ ] `SUPABASE_URL`
    - [ ] `SUPABASE_ANON_KEY`
    - [ ] `SUPABASE_SERVICE_ROLE_KEY`
    - [ ] `SUPABASE_PROJECT_REF`
    - [ ] `SUPABASE_STORAGE_BUCKET`
    - [ ] `SUPABASE_STORAGE_ENDPOINT`
    - [ ] `SUPABASE_STORAGE_URL`
  - [ ] Mail configuration (if using)
  - [ ] Redis settings (if using)

### Security
- [ ] Strong Supabase database password is set
- [ ] Supabase Service Role Key is secured (has admin access)
- [ ] `APP_DEBUG=false` in production
- [ ] Sensitive credentials are in Azure App Settings, not code
- [ ] HTTPS is enabled
- [ ] Supabase Row Level Security (RLS) is considered for database tables
- [ ] Supabase Storage policies are configured appropriately
- [ ] Connection pooling is used (port 6543) for better security

### Files and Scripts
- [ ] `.deployment` file exists
- [ ] `deploy.sh` script exists and is executable
- [ ] `web.config` exists (for IIS)
- [ ] `startup.sh` exists (optional)
- [ ] Supabase Storage configuration is correct (no symbolic link needed)

## Deployment Steps

### Initial Deployment
1. [ ] Push code to Azure remote
2. [ ] Verify deployment succeeds
3. [ ] Check application logs for errors
4. [ ] Run database migrations
5. [ ] Configure Supabase Storage policies (if not done earlier)
6. [ ] Run database seeders (if needed)
7. [ ] Test application endpoints
8. [ ] Verify file uploads work with Supabase Storage

### Post-Deployment Verification
- [ ] Application loads at Azure URL
- [ ] API endpoints respond correctly
- [ ] Database connections work
- [ ] File storage works (uploads/downloads)
- [ ] Authentication works
- [ ] Admin panel is accessible (if applicable)
- [ ] Logs are being generated
- [ ] Error pages display correctly

## Monitoring Setup

- [ ] Application Insights is enabled
- [ ] Log Analytics is configured
- [ ] Alerts are set up for:
  - [ ] High error rate
  - [ ] High response time
  - [ ] Low availability
- [ ] Supabase backup is configured (automatic in Supabase)
- [ ] Azure App Service backup is configured (optional)
- [ ] Monitoring dashboard is set up

## Performance Optimization

- [ ] Caching is configured (Redis or database)
- [ ] Configuration is cached (`config:cache`)
- [ ] Routes are cached (`route:cache`)
- [ ] Views are cached (`view:cache`)
- [ ] Assets are optimized
- [ ] Database indexes are created
- [ ] CDN is configured (optional)

## Documentation

- [ ] Deployment documentation is reviewed
- [ ] Team members have access to Azure resources
- [ ] Credentials are stored securely
- [ ] Rollback procedure is documented
- [ ] Support contacts are documented

## Testing

- [ ] All API endpoints are tested
- [ ] File upload functionality is tested
- [ ] Database operations are tested
- [ ] Authentication flows are tested
- [ ] Error handling is tested
- [ ] Performance is acceptable

## Go-Live Checklist

- [ ] All pre-deployment items are checked
- [ ] All deployment steps are completed
- [ ] All post-deployment verifications pass
- [ ] Monitoring is active
- [ ] Backup is configured
- [ ] Team is notified
- [ ] Support is ready
- [ ] Rollback plan is ready

## Post Go-Live

- [ ] Monitor application for 24-48 hours
- [ ] Review logs for any issues
- [ ] Check performance metrics
- [ ] Verify backups are running
- [ ] Document any issues and resolutions
- [ ] Update documentation as needed

## Rollback Procedure

If issues occur:

1. [ ] Identify the problem
2. [ ] Check application logs
3. [ ] Review recent changes
4. [ ] If needed, rollback to previous deployment:
   ```bash
   az webapp deployment slot swap \
     --resource-group AmazingInventory \
     --name amazinginventory-app \
     --slot staging \
     --target-slot production
   ```
5. [ ] Or redeploy previous version from Git
6. [ ] Document the issue
7. [ ] Fix and redeploy

## Emergency Contacts

- Azure Support: [Azure Portal Support](https://portal.azure.com/#blade/Microsoft_Azure_Support/HelpAndSupportBlade)
- Application Team: [Your contact info]
- Database Admin: [Your contact info]
- DevOps Team: [Your contact info]

## Notes

Add any deployment-specific notes here:

- 
- 
- 

---

**Last Updated**: [Date]
**Deployed By**: [Name]
**Version**: [Version Number]

