<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use OpenApi\Attributes as OA;

#[
    OA\Info(
        version: '1.0.0',
        title: 'Amazing Inventory API',
        description: 'API documentation for Amazing Inventory management system'
    ),
    OA\Server(
        url: '/api/v1',
        description: 'API Server'
    ),
    OA\SecurityScheme(
        securityScheme: 'sanctum',
        type: 'http',
        scheme: 'bearer',
        bearerFormat: 'JWT',
        description: 'Enter token in format (Bearer <token>)'
    ),
    OA\Schema(
        schema: 'Category',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Electronics'),
            new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Electronic devices and accessories'),
            new OA\Property(property: 'is_active', type: 'boolean', example: true),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'Supplier',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'ABC Company'),
            new OA\Property(property: 'contact', type: 'string', nullable: true, example: '+1234567890'),
            new OA\Property(property: 'email', type: 'string', nullable: true, format: 'email', example: 'supplier@example.com'),
            new OA\Property(property: 'address', type: 'string', nullable: true, example: '123 Main St, City, Country'),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'Product',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Laptop Computer'),
            new OA\Property(property: 'sku', type: 'string', example: 'LAP-001'),
            new OA\Property(property: 'category_id', type: 'integer', example: 1),
            new OA\Property(property: 'category', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'Electronics'),
            ]),
            new OA\Property(property: 'cost_price', type: 'number', format: 'float', example: 800.00),
            new OA\Property(property: 'selling_price', type: 'number', format: 'float', example: 1200.00),
            new OA\Property(property: 'stock', type: 'integer', example: 10),
            new OA\Property(property: 'is_active', type: 'boolean', example: true),
            new OA\Property(property: 'photos', type: 'array', items: new OA\Items(type: 'string', format: 'uri'), nullable: true, example: ['https://example.com/storage/products/photo1.jpg']),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'User',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
            new OA\Property(property: 'email_verified_at', type: 'string', nullable: true, format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), nullable: true, example: ['user']),
            new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), nullable: true, example: ['view users']),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'Role',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'admin'),
            new OA\Property(property: 'guard_name', type: 'string', example: 'web'),
            new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), nullable: true, example: ['view users', 'create users']),
            new OA\Property(property: 'permissions_count', type: 'integer', nullable: true, example: 5),
            new OA\Property(property: 'users_count', type: 'integer', nullable: true, example: 10),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'Permission',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'view users'),
            new OA\Property(property: 'guard_name', type: 'string', example: 'web'),
            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), nullable: true, example: ['admin', 'manager']),
            new OA\Property(property: 'roles_count', type: 'integer', nullable: true, example: 2),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'StockMovement',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'product_id', type: 'integer', example: 1),
            new OA\Property(property: 'product', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'Laptop Computer'),
                new OA\Property(property: 'sku', type: 'string', example: 'AG000001'),
            ]),
            new OA\Property(property: 'type', type: 'string', enum: ['in', 'out'], example: 'in'),
            new OA\Property(property: 'quantity', type: 'integer', example: 10),
            new OA\Property(property: 'reason', type: 'string', enum: ['purchase', 'sale', 'adjustment'], example: 'purchase'),
            new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Missing items found during inventory check'),
            new OA\Property(property: 'created_by', type: 'integer', example: 1),
            new OA\Property(property: 'creator', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
            ]),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'Purchase',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'supplier_id', type: 'integer', example: 1),
            new OA\Property(property: 'supplier', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'ABC Company'),
                new OA\Property(property: 'contact', type: 'string', nullable: true, example: '+1234567890'),
                new OA\Property(property: 'email', type: 'string', nullable: true, format: 'email', example: 'supplier@example.com'),
            ]),
            new OA\Property(property: 'total_amount', type: 'number', format: 'float', example: 500.00),
            new OA\Property(property: 'created_by', type: 'integer', example: 1),
            new OA\Property(property: 'creator', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
            ]),
            new OA\Property(
                property: 'items',
                type: 'array',
                items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'product_id', type: 'integer', example: 1),
                        new OA\Property(property: 'product', type: 'object', nullable: true, properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string', example: 'Laptop Computer'),
                            new OA\Property(property: 'sku', type: 'string', example: 'AG000001'),
                        ]),
                        new OA\Property(property: 'quantity', type: 'integer', example: 10),
                        new OA\Property(property: 'cost_price', type: 'number', format: 'float', example: 50.00),
                        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 500.00),
                    ]
                ),
                nullable: true
            ),
            new OA\Property(property: 'items_count', type: 'integer', nullable: true, example: 2),
            new OA\Property(
                property: 'expenses',
                type: 'array',
                items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'expense_category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'expense_category', type: 'object', nullable: true, properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string', example: 'Bale Purchase'),
                        ]),
                        new OA\Property(property: 'amount', type: 'string', example: '500.00'),
                        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Auto-created expense for Purchase #1'),
                        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-11-24'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
                    ]
                ),
                nullable: true
            ),
            new OA\Property(property: 'expenses_count', type: 'integer', nullable: true, example: 1),
            new OA\Property(property: 'expenses_total', type: 'string', nullable: true, example: '500.00'),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'Sale',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'customer_name', type: 'string', example: 'John Doe'),
            new OA\Property(property: 'total_amount', type: 'number', format: 'float', example: 1200.00),
            new OA\Property(property: 'created_by', type: 'integer', example: 1),
            new OA\Property(property: 'creator', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
            ]),
            new OA\Property(
                property: 'items',
                type: 'array',
                items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'product_id', type: 'integer', example: 1),
                        new OA\Property(property: 'product', type: 'object', nullable: true, properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string', example: 'Laptop Computer'),
                            new OA\Property(property: 'sku', type: 'string', example: 'AG000001'),
                        ]),
                        new OA\Property(property: 'quantity', type: 'integer', example: 2),
                        new OA\Property(property: 'selling_price', type: 'number', format: 'float', example: 1200.00),
                        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 2400.00),
                    ]
                ),
                nullable: true
            ),
            new OA\Property(property: 'items_count', type: 'integer', nullable: true, example: 2),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'ExpenseCategory',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Transport'),
            new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Transportation expenses'),
            new OA\Property(property: 'is_active', type: 'boolean', example: true),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'Expense',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'expense_category_id', type: 'integer', example: 1),
            new OA\Property(property: 'expense_category', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'Transport'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Transportation expenses'),
            ]),
            new OA\Property(property: 'amount', type: 'string', example: '300.00'),
            new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Delivered items to shop'),
            new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-11-24'),
            new OA\Property(property: 'created_by', type: 'integer', example: 1),
            new OA\Property(property: 'creator', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
            ]),
            new OA\Property(property: 'purchase_id', type: 'integer', nullable: true, example: 1),
            new OA\Property(property: 'purchase', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'total_amount', type: 'number', format: 'float', example: 500.00),
            ]),
            new OA\Property(property: 'stock_movement_id', type: 'integer', nullable: true, example: 1),
            new OA\Property(property: 'stock_movement', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'type', type: 'string', enum: ['in', 'out'], example: 'in'),
                new OA\Property(property: 'quantity', type: 'integer', example: 10),
            ]),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00+00:00'),
        ]
    ),
    OA\Schema(
        schema: 'Error',
        type: 'object',
        properties: [
            new OA\Property(property: 'success', type: 'boolean', example: false),
            new OA\Property(property: 'message', type: 'string', example: 'Error message'),
            new OA\Property(property: 'errors', type: 'object', nullable: true),
        ]
    ),
    OA\Schema(
        schema: 'Success',
        type: 'object',
        properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'message', type: 'string', nullable: true),
            new OA\Property(property: 'data', type: 'object', nullable: true),
        ]
    ),
]
class Controller extends BaseController
{
    //
}

