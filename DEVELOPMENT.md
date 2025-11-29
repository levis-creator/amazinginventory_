# Development Guide

This guide covers coding standards, best practices, and development workflows for the Amazing Inventory Management System.

## Table of Contents

- [Coding Standards](#coding-styles)
- [Development Workflow](#development-workflow)
- [Best Practices](#best-practices)
- [Testing Guidelines](#testing-guidelines)
- [Git Workflow](#git-workflow)
- [Code Review Guidelines](#code-review-guidelines)

## Coding Standards

### PHP Code Style

This project uses **Laravel Pint** for code style enforcement, which follows **PSR-12** coding standards.

#### Run Code Style Fixer

```bash
# Fix all files
./vendor/bin/pint

# Check without fixing
./vendor/bin/pint --test
```

#### Key Style Rules

1. **Indentation**: 4 spaces (no tabs)
2. **Line Length**: 120 characters max
3. **Naming**:
   - Classes: `PascalCase`
   - Methods: `camelCase`
   - Variables: `camelCase`
   - Constants: `UPPER_SNAKE_CASE`
   - Database: `snake_case`

4. **Type Declarations**: Always use type hints
   ```php
   public function store(Request $request): JsonResponse
   {
       // ...
   }
   ```

5. **Return Types**: Always declare return types
   ```php
   public function getTotal(): float
   {
       return $this->total;
   }
   ```

### JavaScript/TypeScript

- Follow ESLint rules
- Use modern ES6+ syntax
- Prefer `const` over `let`
- Avoid `var`

### Blade Templates

- Use 4-space indentation
- Keep templates simple
- Extract complex logic to components
- Use Blade components when possible

## Development Workflow

### Setting Up Development Environment

1. **Clone Repository**
   ```bash
   git clone <repository-url>
   cd amazinginventory
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment Setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Setup**
   ```bash
   touch database/database.sqlite
   php artisan migrate --seed
   ```

5. **Start Development Server**
   ```bash
   # Terminal 1: Laravel
   php artisan serve
   
   # Terminal 2: Vite
   npm run dev
   ```

### Development Commands

```bash
# Run all development tools
composer dev

# Run tests
php artisan test

# Fix code style
./vendor/bin/pint

# Clear caches
php artisan optimize:clear
```

## Best Practices

### SOLID Principles

#### 1. Single Responsibility Principle (SRP)

Each class should have one reason to change.

**Good:**
```php
class ProductService
{
    public function calculateProfit(Product $product): float
    {
        return $product->selling_price - $product->cost_price;
    }
}
```

**Bad:**
```php
class ProductService
{
    public function calculateProfit() { }
    public function sendEmail() { }
    public function generateReport() { }
}
```

#### 2. Open/Closed Principle (OCP)

Open for extension, closed for modification.

**Good:**
```php
interface PaymentMethod
{
    public function process(float $amount): bool;
}

class CreditCardPayment implements PaymentMethod { }
class PayPalPayment implements PaymentMethod { }
```

#### 3. Dependency Inversion Principle (DIP)

Depend on abstractions, not concretions.

**Good:**
```php
class OrderService
{
    public function __construct(
        private PaymentMethod $paymentMethod
    ) {}
}
```

### DRY (Don't Repeat Yourself)

#### Extract Common Logic

**Bad:**
```php
// Repeated in multiple controllers
$products = Product::where('is_active', true)
    ->orderBy('name')
    ->get();
```

**Good:**
```php
// In Product model
public function scopeActive($query)
{
    return $query->where('is_active', true);
}

// Usage
$products = Product::active()->orderBy('name')->get();
```

#### Use Traits for Shared Behavior

```php
trait HasStockManagement
{
    public function updateStock(int $quantity): void
    {
        // Shared stock update logic
    }
}
```

### KISS (Keep It Simple, Stupid)

- Write simple, readable code
- Avoid over-engineering
- Use Laravel's built-in features
- Don't create abstractions prematurely

**Good:**
```php
$product->increment('stock', $quantity);
```

**Bad:**
```php
$stockManager = new StockManagerFactory()
    ->create($product)
    ->increment($quantity)
    ->execute();
```

### Model Best Practices

#### Use Model Events Wisely

```php
protected static function boot(): void
{
    parent::boot();
    
    static::creating(function (Product $product) {
        if (empty($product->sku)) {
            $product->sku = static::generateSku();
        }
    });
}
```

#### Use Accessors and Mutators

```php
// Accessor
public function getFullNameAttribute(): string
{
    return "{$this->first_name} {$this->last_name}";
}

// Mutator
public function setEmailAttribute(string $value): void
{
    $this->attributes['email'] = strtolower($value);
}
```

#### Use Scopes for Reusable Queries

```php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}

public function scopeLowStock($query, int $threshold = 10)
{
    return $query->where('stock', '<', $threshold);
}
```

### Controller Best Practices

#### Keep Controllers Thin

**Good:**
```php
public function store(StoreProductRequest $request): JsonResponse
{
    $product = Product::create($request->validated());
    
    return response()->json([
        'success' => true,
        'data' => new ProductResource($product),
    ], 201);
}
```

**Bad:**
```php
public function store(Request $request)
{
    // Validation logic
    // Business logic
    // Database operations
    // Email sending
    // Report generation
    // ...
}
```

#### Use Form Requests for Validation

```php
// app/Http/Requests/StoreProductRequest.php
class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

### API Best Practices

#### Consistent Response Format

```php
// Success
return response()->json([
    'success' => true,
    'message' => 'Product created successfully',
    'data' => new ProductResource($product),
], 201);

// Error
return response()->json([
    'success' => false,
    'message' => 'Validation failed',
    'errors' => $validator->errors(),
], 422);
```

#### Use API Resources

```php
// app/Http/Resources/ProductResource.php
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->selling_price,
            'stock' => $this->stock,
        ];
    }
}
```

#### Proper HTTP Status Codes

- `200` - Success (GET, PUT, PATCH)
- `201` - Created (POST)
- `204` - No Content (DELETE)
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Server Error

### Database Best Practices

#### Use Migrations

Always use migrations for schema changes:

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('sku')->unique();
    $table->timestamps();
});
```

#### Use Transactions

```php
DB::transaction(function () {
    $purchase = Purchase::create($data);
    foreach ($items as $item) {
        PurchaseItem::create($item);
        Product::find($item['product_id'])->increment('stock', $item['quantity']);
    }
});
```

#### Use Eager Loading

**Bad:**
```php
$sales = Sale::all();
foreach ($sales as $sale) {
    echo $sale->items->count(); // N+1 problem
}
```

**Good:**
```php
$sales = Sale::with('items')->get();
foreach ($sales as $sale) {
    echo $sale->items->count();
}
```

## Testing Guidelines

### Writing Tests

Use **Pest** for testing:

```php
// tests/Feature/ProductTest.php
test('can create a product', function () {
    $response = $this->postJson('/api/v1/products', [
        'name' => 'Test Product',
        'price' => 100.00,
    ]);
    
    $response->assertStatus(201)
        ->assertJson(['success' => true]);
});
```

### Test Structure

```
tests/
├── Feature/          # Feature tests (HTTP, API)
├── Unit/            # Unit tests (models, services)
└── TestCase.php     # Base test case
```

### Running Tests

```bash
# All tests
php artisan test

# Specific test
php artisan test --filter ProductTest

# With coverage
php artisan test --coverage
```

### Test Best Practices

1. **Arrange-Act-Assert** pattern
2. **Test one thing per test**
3. **Use descriptive test names**
4. **Mock external dependencies**
5. **Test edge cases**

## Git Workflow

### Branch Naming

- `feature/feature-name` - New features
- `bugfix/bug-description` - Bug fixes
- `hotfix/urgent-fix` - Urgent production fixes
- `refactor/refactor-name` - Code refactoring

### Commit Messages

Follow conventional commits:

```
feat: add product search functionality
fix: resolve stock calculation bug
docs: update API documentation
refactor: simplify product service
test: add product creation tests
chore: update dependencies
```

### Pull Request Process

1. Create feature branch
2. Make changes and commit
3. Push to remote
4. Create Pull Request
5. Address review comments
6. Merge after approval

## Code Review Guidelines

### What to Look For

1. **Functionality**: Does it work as intended?
2. **Code Quality**: Is it readable and maintainable?
3. **Performance**: Are there any performance issues?
4. **Security**: Any security vulnerabilities?
5. **Tests**: Are there adequate tests?
6. **Documentation**: Is it documented?

### Review Checklist

- [ ] Code follows style guidelines
- [ ] No hardcoded values
- [ ] Proper error handling
- [ ] Tests are included
- [ ] Documentation is updated
- [ ] No security issues
- [ ] Performance is acceptable

## Common Pitfalls to Avoid

### 1. N+1 Query Problem

**Bad:**
```php
$products = Product::all();
foreach ($products as $product) {
    echo $product->category->name; // N+1
}
```

**Good:**
```php
$products = Product::with('category')->get();
foreach ($products as $product) {
    echo $product->category->name;
}
```

### 2. Mass Assignment Vulnerabilities

**Bad:**
```php
Product::create($request->all());
```

**Good:**
```php
Product::create($request->only(['name', 'price', 'stock']));
// Or use $fillable in model
```

### 3. Missing Validation

**Bad:**
```php
public function store(Request $request)
{
    Product::create($request->all());
}
```

**Good:**
```php
public function store(StoreProductRequest $request)
{
    Product::create($request->validated());
}
```

### 4. Not Using Transactions

**Bad:**
```php
$purchase = Purchase::create($data);
foreach ($items as $item) {
    PurchaseItem::create($item);
    Product::find($item['product_id'])->increment('stock');
}
```

**Good:**
```php
DB::transaction(function () use ($data, $items) {
    $purchase = Purchase::create($data);
    foreach ($items as $item) {
        PurchaseItem::create($item);
        Product::find($item['product_id'])->increment('stock');
    }
});
```

## Performance Tips

1. **Use Indexes**: Add indexes for frequently queried columns
2. **Eager Load**: Use `with()` to prevent N+1 queries
3. **Paginate**: Always paginate large datasets
4. **Cache**: Cache expensive operations
5. **Optimize Queries**: Use `select()` to limit columns

## Documentation

### Code Comments

- Use PHPDoc for classes and methods
- Explain "why", not "what"
- Keep comments up to date

```php
/**
 * Calculate the profit margin for a product.
 *
 * @param Product $product
 * @return float The profit margin as a percentage
 */
public function calculateProfitMargin(Product $product): float
{
    // ...
}
```

### README Updates

- Update README when adding features
- Document breaking changes
- Keep installation steps current

---

Follow these guidelines to maintain code quality and consistency across the project.

