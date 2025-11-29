# API Documentation

## Base URL
```
http://amazinginventory.test/api/v1
```

**Note:** All API endpoints are versioned under `/api/v1/`. The base URL for all requests should include the `/v1` prefix.

## Authentication

The API uses Laravel Sanctum for authentication. You need to include the Bearer token in the Authorization header for protected routes.

### Register
```http
POST /api/v1/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "roles": [2]
}
```

**Request Body:**
- `name` (required) - User's full name
- `email` (required) - User's email address (must be unique)
- `password` (required) - Password (minimum 8 characters)
- `password_confirmation` (required) - Password confirmation (must match password)
- `roles` (optional) - Array of role IDs to assign. If not provided, defaults to 'user' role

**Response:**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2025-11-23T04:35:00+00:00",
    "roles": ["user"],
    "permissions": ["view users"],
    "created_at": "2025-11-23T04:35:00+00:00",
    "updated_at": "2025-11-23T04:35:00+00:00"
  },
  "access_token": "1|token...",
  "token_type": "Bearer"
}
```

**Notes:**
- User is automatically assigned the 'user' role if no roles are specified
- Email is automatically verified upon registration
- Access token is returned immediately, allowing instant login
- Password must be at least 8 characters long

### Login
```http
POST /api/v1/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "message": "Login successful",
  "user": { ... },
  "access_token": "1|token...",
  "token_type": "Bearer"
}
```

### Logout
```http
POST /api/v1/logout
Authorization: Bearer {token}
```

### Get Authenticated User
```http
GET /api/v1/user
Authorization: Bearer {token}
```

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2025-11-23T04:35:00+00:00",
    "roles": ["admin"],
    "permissions": ["view users", "create users", "update users", "delete users"],
    "created_at": "2025-11-23T04:35:00+00:00",
    "updated_at": "2025-11-23T04:35:00+00:00"
  }
}
```

### Get User Permissions (Mobile-Friendly)
```http
GET /api/user/permissions
Authorization: Bearer {token}
```

**Response:**
```json
{
  "permissions": [
    "view users",
    "create users",
    "update users",
    "delete users",
    "view roles",
    "create roles"
  ]
}
```

### Check User Permission
```http
POST /api/user/check-permission
Authorization: Bearer {token}
Content-Type: application/json

{
  "permission": "create users"
}
```

**Response:**
```json
{
  "permission": "create users",
  "has_permission": true
}
```

## Users API

### List Users
```http
GET /api/v1/users?search=john&role=admin&verified=true&per_page=15&sort_by=created_at&sort_order=desc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by name or email
- `role` - Filter by role name
- `verified` - Filter by email verification status
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: created_at)
- `sort_order` - Sort order: asc or desc (default: desc)

### Get User
```http
GET /api/v1/users/{id}
Authorization: Bearer {token}
```

### Create User
```http
POST /api/v1/users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "roles": [1, 2]
}
```

### Update User
```http
PUT /api/v1/users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Smith",
  "email": "jane.smith@example.com",
  "password": "newpassword123",
  "roles": [1]
}
```

### Delete User
```http
DELETE /api/v1/users/{id}
Authorization: Bearer {token}
```

## Roles API

### List Roles
```http
GET /api/v1/roles?search=admin&guard=web&per_page=15&sort_by=name&sort_order=asc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by role name
- `guard` - Filter by guard name
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: name)
- `sort_order` - Sort order: asc or desc (default: asc)

### Get Role
```http
GET /api/v1/roles/{id}
Authorization: Bearer {token}
```

### Create Role (Admin Only)
```http
POST /api/v1/roles
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "editor",
  "guard_name": "web",
  "permissions": [1, 2, 3]
}
```

### Update Role (Admin Only)
```http
PUT /api/v1/roles/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "editor",
  "permissions": [1, 2, 3, 4]
}
```

### Delete Role (Admin Only)
```http
DELETE /api/v1/roles/{id}
Authorization: Bearer {token}
```

### List All Roles (Mobile-Friendly)
```http
GET /api/v1/roles/list/all
Authorization: Bearer {token}
```

**Response:**
```json
{
  "roles": [
    {
      "id": 1,
      "name": "admin",
      "guard_name": "web"
    },
    {
      "id": 2,
      "name": "user",
      "guard_name": "web"
    }
  ]
}
```

> **Note:** This endpoint returns a simplified list without pagination, perfect for mobile dropdowns and selection lists.

## Permissions API

> **⚠️ Admin Only:** All permission management endpoints require the `admin` role. Mobile apps should use the permission checking endpoints instead.

### List Permissions (Admin Only)
```http
GET /api/v1/permissions?search=view&guard=web&role=admin&per_page=15&sort_by=name&sort_order=asc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by permission name
- `guard` - Filter by guard name
- `role` - Filter by role name
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: name)
- `sort_order` - Sort order: asc or desc (default: asc)

### Get Permission (Admin Only)
```http
GET /api/v1/permissions/{id}
Authorization: Bearer {token}
```

### Create Permission (Admin Only)
```http
POST /api/v1/permissions
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "edit posts",
  "guard_name": "web",
  "roles": [1]
}
```

### Update Permission (Admin Only)
```http
PUT /api/v1/permissions/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "edit posts",
  "roles": [1, 2]
}
```

### Delete Permission (Admin Only)
```http
DELETE /api/v1/permissions/{id}
Authorization: Bearer {token}
```

### List All Permissions (Admin Only)
```http
GET /api/v1/permissions/list/all
Authorization: Bearer {token}
```

**Response:**
```json
{
  "permissions": [
    {
      "id": 1,
      "name": "view users",
      "guard_name": "web"
    },
    {
      "id": 2,
      "name": "create users",
      "guard_name": "web"
    }
  ]
}
```

> **Note:** Permission management is admin-only. Mobile apps should use `/api/v1/user/permissions` and `/api/v1/user/check-permission` instead.

## Mobile App Considerations

### Recommended Endpoints for Mobile Apps

**For Authentication & Authorization:**
- `GET /api/v1/user` - Get current user with roles and permissions
- `GET /api/v1/user/permissions` - Get just the permissions list (lighter payload)
- `POST /api/v1/user/check-permission` - Check if user has a specific permission

**For Role Management (Read-Only):**
- `GET /api/v1/roles` - List roles (with pagination)
- `GET /api/v1/roles/{id}` - Get specific role
- `GET /api/v1/roles/list/all` - Get all roles (for dropdowns, no pagination)

**Note:** Permission management endpoints are **admin-only** and not needed for mobile apps. Mobile apps only need to:
- Check user permissions (for authorization)
- View available roles (if needed for user assignment)

**Why These Endpoints?**
- `/api/v1/user/permissions` - Returns only permissions array (smaller payload)
- `/api/v1/user/check-permission` - Quick permission check without loading full user
- `/api/v1/roles/list/all` - Simplified list for mobile UI components (dropdowns)

### Permission Checking in Mobile App

Instead of checking permissions on every request, you can:
1. Load user permissions once after login: `GET /api/v1/user/permissions`
2. Store them locally in the mobile app
3. Use `POST /api/v1/user/check-permission` for server-side validation when needed

### What Mobile App Doesn't Need

The following endpoints are **admin-only** and restricted:
- ❌ `POST /api/v1/permissions` - Create permission
- ❌ `PUT /api/v1/permissions/{id}` - Update permission
- ❌ `DELETE /api/v1/permissions/{id}` - Delete permission
- ❌ `POST /api/v1/roles` - Create role (admin only)
- ❌ `PUT /api/v1/roles/{id}` - Update role (admin only)
- ❌ `DELETE /api/v1/roles/{id}` - Delete role (admin only)

These are only available to users with the `admin` role and are intended for the web admin panel.

## Rate Limiting

- **Public routes** (register, login): 60 requests per minute
- **Protected routes**: 120 requests per minute

## Error Responses

All errors follow a consistent format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

**HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Server Error

## Categories API

### List Categories
```http
GET /api/v1/categories?search=electronics&is_active=true&per_page=15&sort_by=name&sort_order=asc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by name or description
- `is_active` - Filter by active status (true/false)
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: name)
- `sort_order` - Sort order: asc or desc (default: asc)

### Get Category
```http
GET /api/v1/categories/{id}
Authorization: Bearer {token}
```

### Create Category
```http
POST /api/v1/categories
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Electronics",
  "description": "Electronic devices and accessories",
  "is_active": true
}
```

### Update Category
```http
PUT /api/v1/categories/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Electronics",
  "description": "Updated description",
  "is_active": true
}
```

### Delete Category
```http
DELETE /api/v1/categories/{id}
Authorization: Bearer {token}
```

## Suppliers API

### List Suppliers
```http
GET /api/v1/suppliers?search=abc&per_page=15&sort_by=name&sort_order=asc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by name, contact, email, or address
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: name)
- `sort_order` - Sort order: asc or desc (default: asc)

### Get Supplier
```http
GET /api/v1/suppliers/{id}
Authorization: Bearer {token}
```

### Create Supplier
```http
POST /api/v1/suppliers
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "ABC Company",
  "contact": "+1234567890",
  "email": "supplier@example.com",
  "address": "123 Main St, City, Country"
}
```

### Update Supplier
```http
PUT /api/v1/suppliers/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "ABC Company",
  "contact": "+1234567890",
  "email": "supplier@example.com",
  "address": "123 Main St, City, Country"
}
```

### Delete Supplier
```http
DELETE /api/v1/suppliers/{id}
Authorization: Bearer {token}
```

## Products API

### List Products
```http
GET /api/v1/products?search=laptop&category_id=1&is_active=true&per_page=15&sort_by=name&sort_order=asc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by name or SKU
- `category_id` - Filter by category ID
- `is_active` - Filter by active status (true/false)
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: name)
- `sort_order` - Sort order: asc or desc (default: asc)

### Get Product
```http
GET /api/v1/products/{id}
Authorization: Bearer {token}
```

### Create Product
```http
POST /api/v1/products
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Laptop Computer",
  "sku": "AG000001",
  "category_id": 1,
  "cost_price": 800.00,
  "selling_price": 1200.00,
  "stock": 10,
  "is_active": true
}
```

**Note:** SKU is optional. If not provided, it will be auto-generated starting from AG000001.

### Update Product
```http
PUT /api/v1/products/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Laptop Computer",
  "cost_price": 850.00,
  "selling_price": 1250.00,
  "stock": 15
}
```

### Delete Product
```http
DELETE /api/v1/products/{id}
Authorization: Bearer {token}
```

## Stock Movements API

### List Stock Movements
```http
GET /api/v1/stock-movements?product_id=1&type=in&reason=purchase&per_page=15&sort_by=created_at&sort_order=desc
Authorization: Bearer {token}
```

**Query Parameters:**
- `product_id` - Filter by product ID
- `type` - Filter by type: 'in' or 'out'
- `reason` - Filter by reason: 'purchase', 'sale', or 'adjustment'
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: created_at)
- `sort_order` - Sort order: asc or desc (default: desc)

### Get Stock Movement
```http
GET /api/v1/stock-movements/{id}
Authorization: Bearer {token}
```

### Create Stock Movement
```http
POST /api/v1/stock-movements
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_id": 1,
  "type": "in",
  "quantity": 10,
  "reason": "adjustment",
  "notes": "Stock adjustment"
}
```

### Update Stock Movement
```http
PUT /api/v1/stock-movements/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "quantity": 15,
  "notes": "Updated notes"
}
```

### Delete Stock Movement
```http
DELETE /api/v1/stock-movements/{id}
Authorization: Bearer {token}
```

## Purchases API

### List Purchases
```http
GET /api/v1/purchases?search=supplier&supplier_id=1&per_page=15&sort_by=created_at&sort_order=desc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by supplier name
- `supplier_id` - Filter by supplier ID
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: created_at)
- `sort_order` - Sort order: asc or desc (default: desc)

### Get Purchase
```http
GET /api/v1/purchases/{id}
Authorization: Bearer {token}
```

**Response includes:**
- Purchase details
- Supplier information
- Items with products
- **Expenses** (linked expenses)
- Expenses count and total

### Create Purchase
```http
POST /api/v1/purchases
Authorization: Bearer {token}
Content-Type: application/json

{
  "supplier_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 10,
      "cost_price": 50.00
    },
    {
      "product_id": 2,
      "quantity": 5,
      "cost_price": 30.00
    }
  ],
  "expenses": [
    {
      "expense_category_id": 2,
      "amount": 300.00,
      "notes": "Transport cost",
      "date": "2025-11-24"
    }
  ]
}
```

**Request Body:**
- `supplier_id` (required) - Supplier ID
- `items` (required) - Array of purchase items
  - `product_id` (required) - Product ID
  - `quantity` (required) - Quantity (min: 1)
  - `cost_price` (required) - Cost price per unit (min: 0)
- `expenses` (optional) - Array of additional expenses related to this purchase
  - `expense_category_id` (required) - Expense category ID
  - `amount` (required) - Expense amount (min: 0)
  - `notes` (optional) - Notes about the expense
  - `date` (required) - Date of the expense (YYYY-MM-DD format)

**Notes:**
- Stock is automatically increased for each product in items
- Stock movements are automatically created
- A "Bale Purchase" expense is automatically created for the purchase total amount
- Any additional expenses you provide are also created and linked to the purchase

### Update Purchase
```http
PUT /api/v1/purchases/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "supplier_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 15,
      "cost_price": 55.00
    }
  ]
}
```

**Note:** Updating a purchase will reverse old stock changes and apply new ones. The linked expense amount is automatically updated.

### Delete Purchase
```http
DELETE /api/v1/purchases/{id}
Authorization: Bearer {token}
```

**Note:** Deleting a purchase will reverse stock changes and create adjustment stock movements.

## Sales API

### List Sales
```http
GET /api/v1/sales?search=customer&per_page=15&sort_by=created_at&sort_order=desc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by customer name
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: created_at)
- `sort_order` - Sort order: asc or desc (default: desc)

### Get Sale
```http
GET /api/v1/sales/{id}
Authorization: Bearer {token}
```

### Create Sale
```http
POST /api/v1/sales
Authorization: Bearer {token}
Content-Type: application/json

{
  "customer_name": "John Doe",
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "selling_price": 1200.00
    }
  ]
}
```

**Request Body:**
- `customer_name` (required) - Customer name
- `items` (required) - Array of sale items
  - `product_id` (required) - Product ID
  - `quantity` (required) - Quantity (min: 1)
  - `selling_price` (required) - Selling price per unit (min: 0)

**Notes:**
- Stock is automatically decreased for each product
- Stock movements are automatically created

### Update Sale
```http
PUT /api/v1/sales/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "customer_name": "Jane Doe",
  "items": [
    {
      "product_id": 1,
      "quantity": 3,
      "selling_price": 1200.00
    }
  ]
}
```

### Delete Sale
```http
DELETE /api/v1/sales/{id}
Authorization: Bearer {token}
```

**Note:** Deleting a sale will reverse stock changes.

## Expense Categories API

### List Expense Categories
```http
GET /api/v1/expense-categories?search=transport&is_active=true&per_page=15&sort_by=name&sort_order=asc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by name or description
- `is_active` - Filter by active status (true/false)
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: name)
- `sort_order` - Sort order: asc or desc (default: asc)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Transport",
      "description": "Transportation expenses",
      "is_active": true,
      "created_at": "2025-11-24T00:00:00+00:00",
      "updated_at": "2025-11-24T00:00:00+00:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### Get Expense Category
```http
GET /api/v1/expense-categories/{id}
Authorization: Bearer {token}
```

### Create Expense Category
```http
POST /api/v1/expense-categories
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Transport",
  "description": "Transportation expenses",
  "is_active": true
}
```

**Request Body:**
- `name` (required) - Category name (must be unique)
- `description` (optional) - Category description
- `is_active` (optional) - Active status (default: true)

**Common Expense Categories:**
- Transport
- Rent
- Bale Purchase (auto-created)
- Repairs
- Marketing
- Electricity
- Cleaning
- Salary
- Packaging
- Licenses

### Update Expense Category
```http
PUT /api/v1/expense-categories/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Transport",
  "description": "Updated description",
  "is_active": true
}
```

### Delete Expense Category
```http
DELETE /api/v1/expense-categories/{id}
Authorization: Bearer {token}
```

## Expenses API

### List Expenses
```http
GET /api/v1/expenses?search=transport&expense_category_id=1&date_from=2025-11-01&date_to=2025-11-30&per_page=15&sort_by=date&sort_order=desc
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` - Search by notes
- `expense_category_id` - Filter by expense category ID
- `date_from` - Filter expenses from date (YYYY-MM-DD)
- `date_to` - Filter expenses to date (YYYY-MM-DD)
- `per_page` - Number of items per page (default: 15)
- `sort_by` - Field to sort by (default: date)
- `sort_order` - Sort order: asc or desc (default: desc)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "expense_category_id": 1,
      "expense_category": {
        "id": 1,
        "name": "Transport",
        "description": "Transportation expenses"
      },
      "amount": "300.00",
      "notes": "Delivered items to shop",
      "date": "2025-11-24",
      "created_by": 1,
      "creator": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "purchase_id": 1,
      "purchase": {
        "id": 1,
        "total_amount": 500.00
      },
      "stock_movement_id": null,
      "stock_movement": null,
      "created_at": "2025-11-24T00:00:00+00:00",
      "updated_at": "2025-11-24T00:00:00+00:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### Get Expense
```http
GET /api/v1/expenses/{id}
Authorization: Bearer {token}
```

### Create Expense
```http
POST /api/v1/expenses
Authorization: Bearer {token}
Content-Type: application/json

{
  "expense_category_id": 1,
  "amount": 300.00,
  "notes": "Delivered items to shop",
  "date": "2025-11-24",
  "purchase_id": 1,
  "stock_movement_id": null
}
```

**Request Body:**
- `expense_category_id` (required) - Expense category ID
- `amount` (required) - Expense amount (min: 0)
- `notes` (optional) - Notes about the expense
- `date` (required) - Date of the expense (YYYY-MM-DD format)
- `purchase_id` (optional) - Link to a purchase
- `stock_movement_id` (optional) - Link to a stock movement

**Notes:**
- Expenses can be standalone (operating expenses) or linked to purchases/stock movements
- Linked expenses help track costs related to specific inventory transactions

### Update Expense
```http
PUT /api/v1/expenses/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 350.00,
  "notes": "Updated transport cost"
}
```

### Delete Expense
```http
DELETE /api/v1/expenses/{id}
Authorization: Bearer {token}
```

## Expenses and Purchases Integration

### How Expenses Work with Purchases

When you create a purchase, the system automatically:

1. **Creates a "Bale Purchase" expense** - This expense is automatically created for the purchase total amount and linked to the purchase.

2. **Allows additional expenses** - You can add additional expenses when creating a purchase (e.g., transport, handling, packaging).

**Example: Creating a Purchase with Expenses**

```http
POST /api/v1/purchases
Authorization: Bearer {token}
Content-Type: application/json

{
  "supplier_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 10,
      "cost_price": 50.00
    }
  ],
  "expenses": [
    {
      "expense_category_id": 2,
      "amount": 300.00,
      "notes": "Transport cost for delivery",
      "date": "2025-11-24"
    },
    {
      "expense_category_id": 3,
      "amount": 100.00,
      "notes": "Packaging materials",
      "date": "2025-11-24"
    }
  ]
}
```

**What Happens:**
1. Purchase is created with total amount: $500.00 (10 × $50.00)
2. Stock is increased for the product
3. Stock movements are created
4. **Auto-created expense:** "Bale Purchase" for $500.00 (linked to purchase)
5. **Manual expenses:** Transport ($300.00) and Packaging ($100.00) are created and linked to purchase

**Total Expenses for this Purchase:**
- Bale Purchase: $500.00 (auto-created)
- Transport: $300.00 (manual)
- Packaging: $100.00 (manual)
- **Total: $900.00**

### Viewing Expenses in a Purchase

When you fetch a purchase, expenses are included:

```http
GET /api/v1/purchases/{id}
Authorization: Bearer {token}
```

**Response includes:**
```json
{
  "purchase": {
    "id": 1,
    "supplier_id": 1,
    "total_amount": 500.00,
    "items": [ ... ],
    "expenses": [
      {
        "id": 1,
        "expense_category_id": 1,
        "expense_category": {
          "id": 1,
          "name": "Bale Purchase"
        },
        "amount": "500.00",
        "notes": "Auto-created expense for Purchase #1",
        "date": "2025-11-24"
      },
      {
        "id": 2,
        "expense_category_id": 2,
        "expense_category": {
          "id": 2,
          "name": "Transport"
        },
        "amount": "300.00",
        "notes": "Transport cost for delivery",
        "date": "2025-11-24"
      }
    ],
    "expenses_count": 2,
    "expenses_total": "800.00"
  }
}
```

### Types of Expenses

**1. Operating Expenses (Manual Input)**
- These do not affect inventory
- Examples: Rent, Transport, Cleaning, Salary, Electricity, Marketing
- Created manually via `/api/v1/expenses` endpoint
- No purchase_id or stock_movement_id

**2. Inventory Expenses (Auto-Linked)**
- These are expenses related to acquiring items
- Examples: Bale Purchase, Transport for new items, Handling
- Automatically created when purchases are made
- Linked to purchase_id or stock_movement_id

## Response Format

All successful responses follow this format:

**Single Resource:**
```json
{
  "success": true,
  "message": "Resource retrieved successfully",
  "resource": { ... }
}
```

**Collection:**
```json
{
  "data": [ ... ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 75
  }
}
```

