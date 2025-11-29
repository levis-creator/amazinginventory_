# User Guide

This guide helps end-users understand how to use the Amazing Inventory Management System effectively.

## Table of Contents

- [Getting Started](#getting-started)
- [Dashboard](#dashboard)
- [Product Management](#product-management)
- [Sales Management](#sales-management)
- [Purchase Management](#purchase-management)
- [Expense Tracking](#expense-tracking)
- [Financial Reports](#financial-reports)
- [User Management](#user-management)
- [Tips & Best Practices](#tips--best-practices)

## Getting Started

### Accessing the System

1. Navigate to your application URL (e.g., `https://yourdomain.com/admin`)
2. Login with your credentials
3. You'll be redirected to the dashboard

### First Steps

1. **Set Up Categories**: Create product categories before adding products
2. **Add Suppliers**: Register your suppliers
3. **Create Products**: Add products to your inventory
4. **Record Initial Stock**: Set initial stock levels for products

## Dashboard

The dashboard provides an overview of your business at a glance.

### Dashboard Widgets

1. **Statistics Cards**
   - Total Products
   - Total Sales
   - Total Revenue
   - Total Expenses
   - Cash Flow

2. **Cash Flow Trend**
   - Visual chart showing cash flow over the last 30 days
   - Tracks cash in (sales + investments) vs cash out (expenses + purchases)

3. **Revenue vs Expenses**
   - Comparison chart of revenue and expenses
   - Helps identify profitability trends

4. **Top Selling Products**
   - Lists products with highest sales
   - Useful for inventory planning

5. **Low Stock Alerts**
   - Products with stock below threshold
   - Helps prevent stockouts

6. **Recent Transactions**
   - Latest sales and purchases
   - Quick access to recent activity

## Product Management

### Creating a Product

1. Navigate to **Products** in the sidebar
2. Click **New Product**
3. Fill in the form:
   - **Name**: Product name (required)
   - **SKU**: Stock Keeping Unit (auto-generated if left empty)
   - **Category**: Select from existing categories
   - **Cost Price**: Purchase cost per unit
   - **Selling Price**: Retail price per unit
   - **Stock**: Initial stock quantity
   - **Photos**: Upload product images (optional)
   - **Active**: Toggle to enable/disable product
4. Click **Create**

### Editing a Product

1. Go to **Products** → Select product
2. Click **Edit**
3. Update fields as needed
4. Click **Save**

**Note**: Changing stock directly will create an automatic stock movement record.

### Managing Product Photos

- Upload multiple photos per product
- Photos are stored and can be deleted individually
- Supported formats: JPG, PNG, GIF

### Product Status

- **Active**: Product is available for sale
- **Inactive**: Product is hidden from sales but retained in system

## Sales Management

### Creating a Sale

1. Navigate to **Sales** → **New Sale**
2. Enter **Customer Name**
3. Add items:
   - Select **Product**
   - Enter **Quantity**
   - Enter **Selling Price** (defaults to product price)
4. Add more items if needed
5. Review **Total Amount**
6. Click **Create**

**What Happens:**
- Stock is automatically decreased
- Stock movement is recorded
- Sale record is created

### Viewing Sales

- **List View**: See all sales with filters
- **View Details**: Click on a sale to see:
  - Customer name
  - Items sold
  - Total amount
  - Date and time
  - Created by

### Editing a Sale

1. Open the sale
2. Click **Edit**
3. Modify items or customer name
4. Click **Save**

**Note**: Stock is automatically adjusted when editing.

### Deleting a Sale

1. Open the sale
2. Click **Delete**
3. Confirm deletion

**Note**: Stock is automatically restored when a sale is deleted.

## Purchase Management

### Creating a Purchase

1. Navigate to **Purchases** → **New Purchase**
2. Select **Supplier**
3. Add items:
   - Select **Product**
   - Enter **Quantity**
   - Enter **Cost Price** per unit
4. Add more items if needed
5. (Optional) Add **Expenses**:
   - Select expense category (e.g., Transport)
   - Enter amount
   - Add notes
6. Review **Total Amount**
7. Click **Create**

**What Happens:**
- Stock is automatically increased for each product
- Stock movements are created
- A "Bale Purchase" expense is automatically created
- Any additional expenses are linked to the purchase

### Purchase Expenses

When creating a purchase, you can add related expenses:

- **Transport**: Delivery costs
- **Packaging**: Packaging materials
- **Handling**: Handling fees
- **Other**: Any other purchase-related costs

These expenses are tracked separately and linked to the purchase for cost analysis.

### Viewing Purchases

- See all purchases with supplier information
- View purchase details including:
  - Supplier details
  - Items purchased
  - Linked expenses
  - Total costs

## Expense Tracking

### Creating an Expense

1. Navigate to **Expenses** → **New Expense**
2. Select **Expense Category**
3. Enter **Amount**
4. Enter **Date**
5. (Optional) Add **Notes**
6. (Optional) Link to **Purchase** or **Stock Movement**
7. Click **Create**

### Expense Categories

Common categories include:

- **Transport**: Transportation costs
- **Rent**: Rental expenses
- **Bale Purchase**: Purchase costs (auto-created)
- **Repairs**: Maintenance costs
- **Marketing**: Marketing expenses
- **Electricity**: Utility bills
- **Cleaning**: Cleaning services
- **Salary**: Employee wages
- **Packaging**: Packaging materials
- **Licenses**: License fees

### Viewing Expenses

- Filter by category, date range, or search notes
- View expense details including:
  - Category
  - Amount
  - Date
  - Linked purchase (if any)
  - Created by

## Financial Reports

### Cash Flow Analysis

The dashboard shows:

- **Cash In**: Sales revenue + Capital investments
- **Cash Out**: Expenses + Purchases
- **Net Cash Flow**: Cash In - Cash Out

### Revenue vs Expenses

Compare revenue from sales against total expenses to track profitability.

### Capital Investments

Track capital investments separately from sales:

1. Navigate to **Capital Investments** → **New Investment**
2. Enter **Amount**
3. Enter **Date**
4. Add **Notes** (optional)
5. Click **Create**

Capital investments are included in cash flow calculations.

## User Management

### Managing Users (Admin Only)

1. Navigate to **Users**
2. **Create User**:
   - Enter name and email
   - Set password
   - Assign roles
3. **Edit User**:
   - Update information
   - Change roles
   - Reset password
4. **Delete User**: Remove user account

### Roles and Permissions

- **Admin**: Full system access
- **User**: Standard user access
- Custom roles can be created with specific permissions

### User Profile

Update your profile:

1. Click your name in the top right
2. Select **Profile**
3. Update information
4. Change password if needed
5. Save changes

## Stock Management

### Stock Movements

Every stock change is automatically recorded:

- **Type**: In (increase) or Out (decrease)
- **Reason**: Purchase, Sale, or Adjustment
- **Quantity**: Amount changed
- **Date**: When it occurred

### Viewing Stock History

1. Go to **Stock Movements**
2. Filter by:
   - Product
   - Type (In/Out)
   - Reason
   - Date range

### Manual Stock Adjustments

To adjust stock manually:

1. Edit the product
2. Change the stock quantity
3. Save

A stock movement will be automatically created with reason "adjustment".

## Tips & Best Practices

### Inventory Management

1. **Regular Stock Checks**: Review low stock alerts regularly
2. **Accurate Pricing**: Keep cost and selling prices updated
3. **Category Organization**: Use categories to organize products
4. **SKU Management**: Let the system auto-generate SKUs for consistency

### Sales Best Practices

1. **Customer Names**: Use consistent naming for better tracking
2. **Price Verification**: Verify selling prices before completing sales
3. **Stock Availability**: Check stock before creating sales
4. **Regular Reviews**: Review sales reports regularly

### Purchase Management

1. **Supplier Information**: Keep supplier details up to date
2. **Cost Tracking**: Record accurate cost prices
3. **Expense Recording**: Don't forget to add related expenses
4. **Purchase History**: Review purchase history for cost analysis

### Financial Management

1. **Regular Expense Entry**: Record expenses promptly
2. **Category Usage**: Use appropriate expense categories
3. **Date Accuracy**: Ensure dates are correct for accurate reporting
4. **Review Reports**: Regularly review cash flow and financial reports

### Data Entry Tips

1. **Consistency**: Use consistent naming conventions
2. **Completeness**: Fill in all relevant fields
3. **Accuracy**: Double-check amounts and quantities
4. **Notes**: Add notes for important transactions

### Security Best Practices

1. **Strong Passwords**: Use strong, unique passwords
2. **Regular Updates**: Keep your system updated
3. **User Access**: Grant appropriate access levels
4. **Logout**: Always logout when finished

## Common Tasks

### Finding a Product

1. Go to **Products**
2. Use the search bar
3. Filter by category if needed

### Checking Stock Levels

1. View **Products** list
2. Check **Stock** column
3. Or view **Low Stock Products** widget on dashboard

### Viewing Sales History

1. Go to **Sales**
2. Use filters to narrow down:
   - Date range
   - Customer name
   - Product

### Generating Financial Summary

1. View **Dashboard** for overview
2. Check **Cash Flow Trend** widget
3. Review **Revenue vs Expenses** chart

## Troubleshooting

### Stock Not Updating

- Check if product is active
- Verify sale/purchase was completed successfully
- Review stock movements for the product

### Can't Create Sale

- Verify product has sufficient stock
- Check if product is active
- Ensure you have proper permissions

### Missing Data in Reports

- Verify dates are correct
- Check if records exist for the period
- Ensure proper categorization

## Getting Help

- Check this user guide
- Contact your system administrator
- Review system documentation
- Check error messages for specific guidance

---

**Remember**: The system automatically handles stock updates, expense tracking, and financial calculations. Focus on accurate data entry for best results!

