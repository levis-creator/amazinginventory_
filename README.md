# Amazing Inventory Management System

A comprehensive inventory management system built with Laravel and Filament, designed to help businesses manage products, sales, purchases, expenses, and financial tracking efficiently.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Project Structure](#project-structure)
- [Deployment](#deployment)
- [Contributing](#contributing)
- [License](#license)

## 🎯 Overview

Amazing Inventory is a full-featured inventory management system that provides:

- **Product Management**: Track products with categories, SKUs, pricing, and stock levels
- **Sales & Purchases**: Manage sales transactions and purchase orders with automatic stock updates
- **Financial Tracking**: Monitor cash flow, expenses, revenue, and capital investments
- **Stock Management**: Automatic stock movement tracking with detailed history
- **Multi-User Support**: Role-based access control with permissions
- **RESTful API**: Complete API for mobile app integration
- **Admin Dashboard**: Beautiful Filament-based admin panel with analytics widgets

## ✨ Features

### Core Features

- **Product Management**
  - Product catalog with categories
  - Auto-generated SKUs (AG000001 format)
  - Cost and selling price tracking
  - Stock level monitoring
  - Product photos support
  - Active/inactive status

- **Inventory Operations**
  - Purchase orders with multiple items
  - Sales transactions with customer tracking
  - Automatic stock updates
  - Stock movement history
  - Low stock alerts

- **Financial Management**
  - Expense tracking with categories
  - Capital investment tracking
  - Cash flow analysis
  - Revenue and expense trends
  - Financial dashboard widgets

- **User Management**
  - Role-based access control (RBAC)
  - Permission system
  - User authentication (Laravel Fortify)
  - Two-factor authentication support
  - API token authentication (Sanctum)

- **Dashboard & Analytics**
  - Real-time statistics
  - Cash flow trends
  - Revenue vs expenses charts
  - Top selling products
  - Low stock alerts
  - Recent transactions

- **API Integration**
  - RESTful API (v1)
  - Laravel Sanctum authentication
  - Mobile app ready
  - Swagger/OpenAPI documentation
  - Rate limiting

## 🛠 Technology Stack

### Backend
- **PHP**: 8.2+
- **Laravel**: 12.x
- **Filament**: 4.x (Admin Panel)
- **Livewire**: 3.x (Reactive Components)
- **Laravel Sanctum**: API Authentication
- **Laravel Fortify**: Authentication Services
- **Spatie Permission**: Role & Permission Management
- **L5-Swagger**: API Documentation

### Frontend
- **Tailwind CSS**: 4.x
- **Vite**: Build Tool
- **Alpine.js**: Lightweight JavaScript Framework
- **Chart.js**: Data Visualization

### Database
- **SQLite**: Default (Development)
- **PostgreSQL**: Production (Supabase)
- **MySQL/MariaDB**: Supported

### Development Tools
- **Pest**: Testing Framework
- **Laravel Pint**: Code Style Fixer
- **Laravel Pail**: Log Viewer

## 📦 Requirements

- PHP 8.2 or higher
- Composer 2.x
- Node.js 20.x and npm
- SQLite (for development) or PostgreSQL/MySQL (for production)
- Web server (Apache/Nginx) or PHP built-in server

### PHP Extensions
- BCMath
- Ctype
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PDO
- Tokenizer
- XML
- SQLite3 (for SQLite)
- pdo_pgsql (for PostgreSQL)

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/amazinginventory.git
cd amazinginventory
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 3. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure Database

Edit `.env` file:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Or for PostgreSQL/MySQL:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=amazinginventory
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 5. Create Database

For SQLite:
```bash
touch database/database.sqlite
```

For PostgreSQL/MySQL:
```bash
# Create database manually or use migrations
php artisan migrate
```

### 6. Run Migrations and Seeders

```bash
php artisan migrate --seed
```

This will:
- Create all database tables
- Seed default roles and permissions
- Create initial admin user (check seeders for credentials)

### 7. Build Frontend Assets

```bash
# Development
npm run dev

# Production
npm run build
```

### 8. Start Development Server

```bash
php artisan serve
```

Visit `http://localhost:8000` in your browser.

### 9. Access Admin Panel

Navigate to `/admin` and login with the default admin credentials (check seeders).

## ⚙️ Configuration

### Environment Variables

Key environment variables in `.env`:

```env
APP_NAME="Amazing Inventory"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Mail (optional)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025

# CORS (for API)
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173
```

### Filament Configuration

Filament admin panel is automatically configured. Customize in:
- `app/Providers/Filament/AdminPanelProvider.php`

### API Configuration

API routes are versioned under `/api/v1/`. Configure in:
- `routes/api.php`
- `config/sanctum.php` (for token expiration)

## 📖 Usage

### Admin Panel

1. **Login**: Navigate to `/admin` and login
2. **Dashboard**: View analytics and statistics
3. **Products**: Manage product catalog
4. **Sales**: Create and view sales
5. **Purchases**: Manage purchase orders
6. **Expenses**: Track business expenses
7. **Reports**: View financial reports

### API Usage

See [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) for complete API reference.

**Quick Start:**

```bash
# Register
curl -X POST http://localhost:8000/api/v1/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com","password":"password123","password_confirmation":"password123"}'

# Login
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123"}'

# Get Products (with token)
curl -X GET http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 📚 API Documentation

Complete API documentation is available in [API_DOCUMENTATION.md](./API_DOCUMENTATION.md).

Interactive API documentation (Swagger) is available at:
- `/api/documentation` (when L5-Swagger is configured)

## 📁 Project Structure

```
amazinginventory/
├── app/
│   ├── Filament/              # Filament admin panel resources
│   │   ├── Resources/         # CRUD resources
│   │   ├── Widgets/           # Dashboard widgets
│   │   └── Pages/             # Custom pages
│   ├── Http/
│   │   ├── Controllers/       # API controllers
│   │   ├── Middleware/        # Custom middleware
│   │   └── Resources/         # API resources
│   ├── Models/                # Eloquent models
│   ├── Services/              # Business logic services
│   └── Providers/             # Service providers
├── config/                    # Configuration files
├── database/
│   ├── migrations/            # Database migrations
│   └── seeders/               # Database seeders
├── resources/
│   ├── views/                 # Blade templates
│   ├── css/                   # Stylesheets
│   └── js/                    # JavaScript
├── routes/
│   ├── api.php                # API routes
│   └── web.php                # Web routes
└── tests/                     # Test files
```

## 🚢 Deployment

### Production Deployment

See deployment guides:
- [Azure Deployment](./AZURE_DEPLOYMENT.md)
- [Render Deployment](./RENDER_DEPLOYMENT.md)
- [Deployment Checklist](./DEPLOYMENT_CHECKLIST.md)

### Quick Production Setup

1. Set environment to production:
```env
APP_ENV=production
APP_DEBUG=false
```

2. Optimize application:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

3. Build assets:
```bash
npm run build
```

4. Run migrations:
```bash
php artisan migrate --force
```

## 🧪 Testing

Run tests with Pest:

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter ProductTest

# With coverage
php artisan test --coverage
```

## 📝 Additional Documentation

- [Architecture Documentation](./ARCHITECTURE.md) - System design and architecture
- [Development Guide](./DEVELOPMENT.md) - Coding standards and best practices
- [User Guide](./USER_GUIDE.md) - End-user documentation
- [Flutter Integration](./FLUTTER_INTEGRATION.md) - Mobile app integration
- [CI/CD Setup](./CI_CD_READINESS.md) - Continuous integration setup

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Code Style

This project uses Laravel Pint for code style:

```bash
./vendor/bin/pint
```

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🆘 Support

For issues and questions:
- Open an issue on GitHub
- Check existing documentation
- Review [Troubleshooting Guide](./HERD_TROUBLESHOOTING.md)

## 🙏 Acknowledgments

- [Laravel](https://laravel.com) - The PHP Framework
- [Filament](https://filamentphp.com) - Admin Panel Builder
- [Spatie](https://spatie.be) - Permission Package
- All contributors and users

---

**Made with ❤️ for efficient inventory management**

