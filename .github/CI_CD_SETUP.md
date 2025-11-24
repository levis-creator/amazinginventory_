# CI/CD Setup Guide

This guide explains how to set up Continuous Integration and Continuous Deployment (CI/CD) for your application.

## Overview

The repository includes three GitHub Actions workflows:

1. **`.github/workflows/azure-deploy.yml`** - Automated deployment to Azure App Service
2. **`.github/workflows/tests.yml`** - Automated testing on push/PR
3. **`.github/workflows/lint.yml`** - Code quality checks (Pint)

## Required GitHub Secrets

### For Azure Deployment

You need to configure the following secrets in your GitHub repository:

#### 1. AZURE_CREDENTIALS

This is a service principal JSON that allows GitHub Actions to deploy to Azure.

**How to create:**

```bash
# Login to Azure
az login

# Create service principal (replace with your subscription ID)
az ad sp create-for-rbac \
  --name "github-actions-amazinginventory" \
  --role contributor \
  --scopes /subscriptions/YOUR_SUBSCRIPTION_ID/resourceGroups/AmazingInventory \
  --sdk-auth
```

This will output JSON like:
```json
{
  "clientId": "...",
  "clientSecret": "...",
  "subscriptionId": "...",
  "tenantId": "...",
  ...
}
```

**Add to GitHub:**
1. Go to your GitHub repository
2. Settings > Secrets and variables > Actions
3. Click "New repository secret"
4. Name: `AZURE_CREDENTIALS`
5. Value: Paste the entire JSON output
6. Click "Add secret"

### For Testing Workflow

#### 2. FLUX_USERNAME (if using Flux UI)
- Your Flux UI username
- Settings > Secrets > New secret
- Name: `FLUX_USERNAME`

#### 3. FLUX_LICENSE_KEY (if using Flux UI)
- Your Flux UI license key
- Settings > Secrets > New secret
- Name: `FLUX_LICENSE_KEY`

## Workflow Details

### Azure Deployment Workflow

**Triggers:**
- Push to `main` or `master` branch
- Manual trigger via `workflow_dispatch`

**Steps:**
1. Checks out code
2. Sets up PHP 8.2 with required extensions (including PostgreSQL)
3. Sets up Node.js 20.x
4. Caches and installs Composer dependencies
5. Installs NPM dependencies
6. Builds frontend assets
7. Logs into Azure using credentials
8. Deploys to Azure App Service
9. Database migrations are handled by `deploy.sh` script

**Environment Variables:**
- `AZURE_WEBAPP_NAME`: Your Azure App Service name (default: `amazinginventory-app`)
- `AZURE_RESOURCE_GROUP`: Your Azure resource group (default: `amazinginventory-rg`)
- `PHP_VERSION`: PHP version (default: `8.2`)
- `NODE_VERSION`: Node.js version (default: `20.x`)

### Testing Workflow

**Triggers:**
- Push to `develop` or `main` branch
- Pull requests to `develop` or `main`

**Steps:**
1. Sets up PHP 8.4 with Xdebug
2. Sets up Node.js 22
3. Installs dependencies
4. Copies `.env.example` to `.env`
5. Generates application key
6. Builds assets
7. Runs Pest tests

### Linting Workflow

**Triggers:**
- Push to `develop` or `main` branch
- Pull requests to `develop` or `main`

**Steps:**
1. Sets up PHP 8.4
2. Installs dependencies
3. Runs Laravel Pint for code formatting

## Setting Up Secrets in GitHub

### Method 1: Via GitHub Web Interface

1. Go to your repository on GitHub
2. Click **Settings** > **Secrets and variables** > **Actions**
3. Click **New repository secret**
4. Enter the name and value
5. Click **Add secret**

### Method 2: Via GitHub CLI

```bash
# Install GitHub CLI if not installed
# https://cli.github.com/

# Login
gh auth login

# Add secret
gh secret set AZURE_CREDENTIALS --body "$(cat azure-credentials.json)"
```

## Customizing Workflows

### Change Deployment Branch

Edit `.github/workflows/azure-deploy.yml`:

```yaml
on:
  push:
    branches:
      - production  # Change this
```

### Change App Service Name

Edit `.github/workflows/azure-deploy.yml`:

```yaml
env:
  AZURE_WEBAPP_NAME: your-app-name  # Change this
  AZURE_RESOURCE_GROUP: your-resource-group  # Change this
```

### Add Pre-deployment Steps

You can add steps before deployment:

```yaml
- name: Run tests before deployment
  run: |
    composer install
    php artisan test
```

### Skip Deployment on Certain Conditions

```yaml
- name: Deploy to Azure Web App
  if: "!contains(github.event.head_commit.message, '[skip deploy]')"
  uses: azure/webapps-deploy@v2
  ...
```

## Troubleshooting

### Deployment Fails with "Authentication Failed"

- Verify `AZURE_CREDENTIALS` secret is set correctly
- Check that the service principal has the correct permissions
- Ensure the resource group exists

### Migrations Fail

- Migrations run automatically via `deploy.sh`
- If you need to run them separately, uncomment the migration step in the workflow
- Ensure database credentials are set in Azure App Settings

### Build Fails

- Check that all required PHP extensions are listed
- Verify Node.js version is compatible
- Check that `composer.json` and `package.json` are valid

### Tests Fail

- Ensure `.env.example` has all required variables
- Check that test database is configured (if needed)
- Verify all dependencies are installed

## Best Practices

1. **Never commit secrets**: Always use GitHub Secrets
2. **Use environments**: Create separate environments for staging/production
3. **Test workflows**: Test on feature branches before merging
4. **Monitor deployments**: Check Azure Portal after each deployment
5. **Review logs**: Check GitHub Actions logs for any issues

## Environment Protection

For production deployments, consider:

1. **Required reviewers**: Require approval before deployment
2. **Environment secrets**: Use environment-specific secrets
3. **Deployment branches**: Restrict which branches can deploy
4. **Status checks**: Require tests to pass before deployment

To set up environment protection:

1. Go to Settings > Environments
2. Create new environment (e.g., "Production")
3. Add required reviewers
4. Update workflow to use environment:

```yaml
jobs:
  deploy:
    environment: Production
    ...
```

## Additional Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Azure Login Action](https://github.com/Azure/login)
- [Azure Web Apps Deploy Action](https://github.com/Azure/webapps-deploy)
- [Laravel Deployment Guide](https://laravel.com/docs/deployment)

