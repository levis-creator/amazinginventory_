# Architecture Documentation

This document describes the system architecture, design patterns, and technical decisions for the Amazing Inventory Management System.

## Table of Contents

- [System Overview](#system-overview)
- [Architecture Patterns](#architecture-patterns)
- [Database Design](#database-design)
- [Application Layers](#application-layers)
- [API Design](#api-design)
- [Security Architecture](#security-architecture)
- [File Structure](#file-structure)

## System Overview

The Amazing Inventory system follows a **layered architecture** pattern with clear separation of concerns:

```
┌─────────────────────────────────────┐
│      Presentation Layer              │
│  (Filament Admin Panel / API)       │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│      Application Layer               │
│  (Controllers / Resources)          │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│      Domain Layer                    │
│  (Models / Business Logic)           │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│      Data Access Layer               │
│  (Eloquent ORM / Database)           │
└─────────────────────────────────────┘
```

## Architecture Patterns

### 1. MVC (Model-View-Controller)

The application follows Laravel's MVC pattern:

- **Models**: Located in `app/Models/`, represent database entities
- **Views**: Blade templates in `resources/views/` and Filament resources
- **Controllers**: API controllers in `app/Http/Controllers/Api/`

### 2. Repository Pattern (Implicit)

Laravel's Eloquent ORM acts as a repository pattern, abstracting database operations.

### 3. Service Layer Pattern

Business logic is encapsulated in service classes:

- `app/Services/AuditLogService.php` - Audit logging
- `app/Services/CorsService.php` - CORS management
- `app/Services/DatabaseConfigurationService.php` - Database config
- `app/Services/EnvironmentVariableService.php` - Environment management

### 4. Resource Pattern

API responses are formatted using Laravel API Resources:

- `app/Http/Resources/` - Transform models for API responses

## Database Design

### Entity Relationship Diagram

```
Users ──┬── Sales (created_by)
        ├── Purchases (created_by)
        ├── Expenses (created_by)
        └── CapitalInvestments (created_by)

Categories ── Products
Suppliers ── Purchases

Products ──┬── PurchaseItems
           ├── SaleItems
           └── StockMovements

Purchases ──┬── PurchaseItems
            └── Expenses

Sales ── SaleItems

ExpenseCategories ── Expenses

Roles ──┬── Users (many-to-many)
        └── Permissions (many-to-many)
```

### Key Tables

#### Core Inventory Tables

- **products**: Product catalog with SKU, pricing, stock
- **categories**: Product categorization
- **suppliers**: Supplier information
- **stock_movements**: Complete stock history

#### Transaction Tables

- **purchases**: Purchase orders
- **purchase_items**: Items in each purchase
- **sales**: Sales transactions
- **sale_items**: Items in each sale

#### Financial Tables

- **expenses**: Business expenses
- **expense_categories**: Expense categorization
- **capital_investments**: Capital investment tracking

#### System Tables

- **users**: User accounts
- **roles**: User roles
- **permissions**: System permissions
- **model_has_roles**: Role assignments
- **role_has_permissions**: Permission assignments

### Database Conventions

- **Naming**: snake_case for tables and columns
- **Timestamps**: All tables include `created_at` and `updated_at`
- **Soft Deletes**: Not used (hard deletes with audit trail)
- **Foreign Keys**: Properly defined with cascade rules
- **Indexes**: Added for performance (see `add_dashboard_performance_indexes.php`)

## Application Layers

### 1. Presentation Layer

#### Filament Admin Panel

- **Location**: `app/Filament/Resources/`
- **Purpose**: Web-based admin interface
- **Features**:
  - CRUD operations
  - Dashboard widgets
  - Form validation
  - Relationship management

#### API Layer

- **Location**: `app/Http/Controllers/Api/`
- **Purpose**: RESTful API for mobile/web clients
- **Versioning**: `/api/v1/`
- **Authentication**: Laravel Sanctum tokens

### 2. Application Layer

#### Controllers

API controllers handle HTTP requests:

```php
app/Http/Controllers/Api/
├── AuthController.php          # Authentication
├── ProductController.php        # Product CRUD
├── SaleController.php           # Sales management
├── PurchaseController.php      # Purchase management
└── ...
```

#### Middleware

- `EnsureUserIsAdmin`: Admin-only route protection
- `auth:sanctum`: API authentication
- `throttle`: Rate limiting

#### Resources

Transform models for API responses:

```php
app/Http/Resources/
├── ProductResource.php
├── SaleResource.php
└── ...
```

### 3. Domain Layer

#### Models

Eloquent models with business logic:

```php
app/Models/
├── Product.php              # Auto SKU generation, stock management
├── Sale.php                 # Stock reversal on delete
├── Purchase.php             # Expense creation
├── StockMovement.php        # Automatic tracking
└── ...
```

#### Model Events

Models use Eloquent events for automatic operations:

- **Product**: Auto-generate SKU, track stock changes
- **Sale**: Decrease stock, create stock movements
- **Purchase**: Increase stock, create expenses
- **StockMovement**: Automatic creation on stock changes

### 4. Data Access Layer

- **ORM**: Eloquent ORM
- **Migrations**: Version-controlled schema
- **Seeders**: Initial data population

## API Design

### RESTful Principles

- **Resources**: Nouns (products, sales, purchases)
- **HTTP Methods**: GET, POST, PUT, DELETE
- **Status Codes**: Standard HTTP status codes
- **Versioning**: URL-based (`/api/v1/`)

### API Structure

```
/api/v1/
├── auth/
│   ├── POST   /register
│   ├── POST   /login
│   ├── POST   /logout
│   └── GET    /user
├── products/
│   ├── GET    /products
│   ├── GET    /products/{id}
│   ├── POST   /products
│   ├── PUT    /products/{id}
│   └── DELETE /products/{id}
└── ...
```

### Response Format

**Success Response:**
```json
{
  "success": true,
  "message": "Resource retrieved successfully",
  "resource": { ... }
}
```

**Collection Response:**
```json
{
  "data": [ ... ],
  "links": { ... },
  "meta": { ... }
}
```

**Error Response:**
```json
{
  "message": "Validation error",
  "errors": { ... }
}
```

### Rate Limiting

- **Public routes**: 60 requests/minute
- **Protected routes**: 120 requests/minute

## Security Architecture

### Authentication

1. **Web**: Laravel Fortify (session-based)
2. **API**: Laravel Sanctum (token-based)

### Authorization

- **RBAC**: Role-Based Access Control (Spatie Permission)
- **Permissions**: Granular permission system
- **Middleware**: Route-level protection

### Security Features

- **CSRF Protection**: Enabled for web routes
- **XSS Protection**: Blade templating escapes
- **SQL Injection**: Eloquent ORM protection
- **Password Hashing**: Bcrypt
- **Token Expiration**: Configurable in Sanctum
- **CORS**: Configurable origins

## File Structure

### Key Directories

```
app/
├── Filament/              # Admin panel
│   ├── Resources/         # CRUD resources
│   ├── Widgets/           # Dashboard widgets
│   └── Pages/             # Custom pages
├── Http/
│   ├── Controllers/       # Request handlers
│   ├── Middleware/        # Request middleware
│   └── Resources/         # API transformers
├── Models/                # Domain models
├── Services/              # Business logic
└── Providers/             # Service providers

config/                    # Configuration files
database/
├── migrations/           # Schema changes
└── seeders/              # Data seeding

routes/
├── api.php               # API routes
└── web.php               # Web routes
```

### Naming Conventions

- **Controllers**: PascalCase, singular (e.g., `ProductController`)
- **Models**: PascalCase, singular (e.g., `Product`)
- **Resources**: PascalCase, singular + "Resource" (e.g., `ProductResource`)
- **Migrations**: snake_case, descriptive (e.g., `create_products_table`)
- **Routes**: kebab-case (e.g., `/api/v1/products`)

## Design Principles

### SOLID Principles

1. **Single Responsibility**: Each class has one reason to change
2. **Open/Closed**: Open for extension, closed for modification
3. **Liskov Substitution**: Subtypes must be substitutable
4. **Interface Segregation**: Many specific interfaces
5. **Dependency Inversion**: Depend on abstractions

### DRY (Don't Repeat Yourself)

- Reusable components
- Shared traits
- Service classes for common logic

### KISS (Keep It Simple, Stupid)

- Simple, readable code
- Avoid over-engineering
- Clear naming conventions

## Data Flow

### Creating a Sale

```
1. API Request → SaleController::store()
2. Validation → FormRequest
3. Business Logic → Sale Model
4. Stock Update → Product Model (decrement)
5. Stock Movement → Automatic creation
6. Response → SaleResource
```

### Stock Management Flow

```
Purchase/Sale → Model Event → Stock Update → StockMovement Created
```

## Performance Considerations

### Database Indexing

- Foreign keys indexed
- Dashboard queries optimized
- See `add_dashboard_performance_indexes.php`

### Caching Strategy

- Config caching in production
- Route caching
- View caching

### Query Optimization

- Eager loading relationships
- Select specific columns
- Pagination for large datasets

## Extension Points

### Adding New Features

1. **New Resource**: Create Filament resource
2. **New API Endpoint**: Add controller and route
3. **New Model**: Create migration and model
4. **New Widget**: Create widget class

### Customization

- **Themes**: Filament theme customization
- **Policies**: Authorization policies
- **Events**: Custom event listeners
- **Jobs**: Background processing

## Technology Decisions

### Why Laravel?

- Rapid development
- Rich ecosystem
- Excellent documentation
- Active community

### Why Filament?

- Fast admin panel development
- Beautiful UI out of the box
- Extensible
- Modern stack (Livewire)

### Why Sanctum?

- Lightweight
- Token-based auth
- Mobile-friendly
- Built-in Laravel

### Why Spatie Permission?

- Battle-tested
- Flexible
- Well-documented
- Active maintenance

---

This architecture provides a solid foundation for scalability, maintainability, and extensibility.

