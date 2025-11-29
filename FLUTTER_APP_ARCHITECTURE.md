# Flutter App Architecture Documentation

## Overview

The Amazing Inventory Flutter mobile app follows a **feature-based architecture** pattern, ensuring maintainability, scalability, and separation of concerns. This document describes the app structure, navigation, and design patterns.

## Architecture Principles

### SOLID Principles
- **Single Responsibility**: Each feature is self-contained
- **Open/Closed**: Features can be extended without modification
- **Liskov Substitution**: Features follow consistent interfaces
- **Interface Segregation**: Specific, focused widgets
- **Dependency Inversion**: Features depend on abstractions (NavigationService)

### DRY (Don't Repeat Yourself)
- Shared widgets in `lib/shared/widgets/`
- Reusable components (AppSearchBar, etc.)
- Centralized theme and constants

### KISS (Keep It Simple, Stupid)
- Clear, readable code
- Consistent patterns across features
- Simple navigation structure

## Project Structure

```
lib/
├── core/                           # Core application infrastructure
│   ├── constants/
│   │   └── app_constants.dart      # Navigation indices, app-wide constants
│   ├── routes/
│   │   └── app_router.dart         # Centralized screen routing
│   ├── services/
│   │   └── navigation_service.dart # Tab navigation service (singleton)
│   └── theme/
│       ├── app_colors.dart         # Color system
│       └── app_theme.dart          # Theme configuration
│
├── features/                       # Feature-based modules
│   ├── dashboard/                  # Home/Dashboard feature
│   │   ├── screens/
│   │   │   └── dashboard_screen.dart
│   │   └── widgets/
│   │       ├── metric_card.dart
│   │       └── stock_flow_chart.dart
│   │
│   ├── inventory/                  # Products/Inventory feature
│   │   ├── models/
│   │   │   └── product_model.dart
│   │   ├── screens/
│   │   │   ├── inventory_screen.dart
│   │   │   ├── add_product_screen.dart
│   │   │   └── product_details_screen.dart
│   │   └── widgets/
│   │       ├── product_card.dart
│   │       ├── search_bar.dart
│   │       ├── filter_chips.dart
│   │       └── ...
│   │
│   ├── notifications/              # Notifications feature
│   │   └── screens/
│   │       └── notifications_screen.dart
│   │
│   ├── modules/                    # Modules navigation hub
│   │   ├── models/
│   │   │   └── module_item.dart
│   │   ├── screens/
│   │   │   └── modules_list_screen.dart
│   │   └── widgets/
│   │       └── module_list_item.dart
│   │
│   ├── sales/                      # Sales feature
│   │   └── screens/
│   │       └── sales_list_screen.dart
│   │
│   ├── purchases/                  # Purchases feature
│   │   └── screens/
│   │       └── purchases_list_screen.dart
│   │
│   ├── capital/                    # Capital Investments feature
│   │   └── screens/
│   │       └── capital_list_screen.dart
│   │
│   ├── expenses/                   # Expenses feature
│   │   └── screens/
│   │       └── expenses_list_screen.dart
│   │
│   ├── expense_categories/         # Expense Categories feature
│   │   └── screens/
│   │       └── expense_categories_list_screen.dart
│   │
│   ├── categories/                 # Categories feature
│   │   └── screens/
│   │       └── categories_list_screen.dart
│   │
│   ├── suppliers/                  # Suppliers feature
│   │   └── screens/
│   │       └── suppliers_list_screen.dart
│   │
│   └── stock_movements/            # Stock Movements feature
│       └── screens/
│           └── stock_movements_list_screen.dart
│
└── shared/                         # Shared utilities and widgets
    ├── utils/
    │   └── greeting_util.dart
    └── widgets/
        ├── search_bar.dart         # Reusable AppSearchBar widget
        └── bottom_nav_bar.dart
```

## Navigation Structure

### Bottom Navigation Bar

The app uses a bottom navigation bar with 4 main tabs:

1. **Home** (Index 0)
   - Dashboard screen with metrics and charts
   - Profile section
   - Notification bell (navigates to Notifications tab)

2. **Inventory** (Index 1)
   - Product list screen
   - Search and filter functionality
   - Add product capability

3. **Notifications** (Index 2)
   - Notifications list screen
   - Empty state when no notifications
   - Mark all as read functionality

4. **More** (Index 3)
   - Modules list screen
   - Access to all 9 inventory modules
   - Search functionality

### Center Floating Action Button

A floating action button in the center provides quick actions:
- Add Product
- New Sale
- New Purchase

### Navigation Service

Features use `NavigationService` for decoupled tab navigation:

```dart
// Navigate to notifications
NavigationService.instance.navigateToNotifications();

// Navigate to any tab by index
NavigationService.instance.navigateToTab(AppConstants.notificationsIndex);
```

**Benefits:**
- Features don't depend on main app state
- Easy to test
- Centralized navigation logic

## UI Consistency

### Standard Module Screen Structure

All module screens follow this consistent pattern:

```
┌─────────────────────────────────┐
│  Top Bar (White Background)      │
│  [Title - 24px Bold] [Add Btn]  │
├─────────────────────────────────┤
│  Search Bar                     │
│  [Search Icon] [Hint Text]       │
├─────────────────────────────────┤
│                                 │
│  List View                      │
│  - Module items                 │
│  - Or Empty State               │
│                                 │
└─────────────────────────────────┘
```

### Components

1. **Top Bar**
   - White background (`AppColors.cardBackground`)
   - Title: 24px, bold, `AppColors.textPrimary`
   - Optional "Add [Module]" button (purple, `AppColors.metricPurple`)
   - Padding: `20px horizontal, 16px vertical`

2. **Search Bar** (`AppSearchBar`)
   - Height: 48px
   - White background with border
   - Search icon prefix
   - Rounded corners (12px)
   - Module-specific hint text

3. **List View**
   - Padding: `20px horizontal` or `16px all`
   - Scrollable list
   - Consistent item spacing

4. **Empty State**
   - Large icon (64px, `AppColors.gray400`)
   - Title: 18px, bold, `AppColors.textSecondary`
   - Subtitle: 14px, `AppColors.textTertiary`
   - Centered vertically and horizontally

## Feature Modules

### 1. Dashboard Feature
**Location**: `lib/features/dashboard/`

**Screens:**
- `dashboard_screen.dart` - Main dashboard with metrics

**Widgets:**
- `metric_card.dart` - KPI metric cards
- `stock_flow_chart.dart` - Stock flow visualization

**Features:**
- Real-time metrics display
- Charts and visualizations
- Profile section
- Notification bell with badge

### 2. Inventory Feature
**Location**: `lib/features/inventory/`

**Screens:**
- `inventory_screen.dart` - Product list
- `add_product_screen.dart` - Add new product
- `product_details_screen.dart` - Product details

**Models:**
- `product_model.dart` - Product data model

**Widgets:**
- `product_card.dart` - Product list item
- `search_bar.dart` - Inventory-specific search
- `filter_chips.dart` - Stock status filters

### 3. Notifications Feature
**Location**: `lib/features/notifications/`

**Screens:**
- `notifications_screen.dart` - Notifications list

**Features:**
- Notification list (ready for API)
- Empty state
- Mark all as read

### 4. Modules Feature
**Location**: `lib/features/modules/`

**Screens:**
- `modules_list_screen.dart` - Modules navigation hub

**Models:**
- `module_item.dart` - Module metadata model

**Widgets:**
- `module_list_item.dart` - Module card widget

**Features:**
- List of 9 inventory modules
- Search functionality
- Color-coded module cards
- Navigation to module screens

### 5-12. Module Features
Each module (Sales, Purchases, Capital, Expenses, etc.) follows the same structure:
- `screens/[module]_list_screen.dart` - List screen with consistent UI
- Ready for models, widgets, and API integration

## Shared Components

### AppSearchBar
**Location**: `lib/shared/widgets/search_bar.dart`

Reusable search bar widget used across all module screens:
- Consistent styling
- Search icon
- Customizable hint text
- Controller support

### NavigationService
**Location**: `lib/core/services/navigation_service.dart`

Singleton service for tab navigation:
- Decouples features from main app
- Provides navigation methods
- Callback-based tab switching

## Design Patterns

### 1. Feature-Based Architecture
Each feature is self-contained with:
- Models (data structures)
- Screens (UI pages)
- Widgets (reusable UI components)

### 2. Service Pattern
- `NavigationService` - Centralized navigation
- Future: API services, state management

### 3. Repository Pattern (Future)
- API repositories for data access
- Separation of data layer from UI

### 4. State Management (Future)
- Ready for Provider, Riverpod, or Bloc
- Current: Local state with setState

## Color System

All colors are centralized in `AppColors`:

```dart
// Backgrounds
scaffoldBackground, cardBackground

// Text
textPrimary, textSecondary, textTertiary

// Metrics
metricPurple, metricGreen, metricRed, metricYellow

// Borders
borderLight, gray300

// And more...
```

## Constants

Navigation indices in `AppConstants`:
- `homeIndex = 0`
- `inventoryIndex = 1`
- `notificationsIndex = 2`
- `moreIndex = 3`

## Best Practices

### 1. Feature Independence
- Features should not depend on other features
- Use shared components for common functionality
- Use NavigationService for cross-feature navigation

### 2. Consistent Styling
- Always use `AppColors` for colors
- Use `AppTheme` for text styles
- Follow the standard screen structure

### 3. Code Organization
- Keep models simple (data classes)
- Extract complex widgets
- Use meaningful names

### 4. API Integration (Future)
- Create API service per feature
- Use models for data parsing
- Handle loading and error states

## Future Enhancements

1. **API Integration**
   - API services for each feature
   - Data models matching backend
   - Error handling and retry logic

2. **State Management**
   - Implement Provider/Riverpod/Bloc
   - Global state management
   - Caching strategies

3. **Offline Support**
   - Local database (SQLite/Hive)
   - Sync mechanism
   - Offline-first approach

4. **Additional Features**
   - Detail screens for each module
   - Create/Edit forms
   - Image uploads
   - Charts and analytics

## Testing

### Unit Tests
- Test models and utilities
- Test business logic
- Test navigation service

### Widget Tests
- Test individual widgets
- Test screen layouts
- Test user interactions

### Integration Tests
- Test navigation flows
- Test API integration
- Test end-to-end scenarios

## Code Style

- Follow Flutter/Dart style guide
- Use meaningful variable names
- Add documentation comments
- Keep functions focused and small
- Use const constructors where possible

## Dependencies

Current key dependencies:
- `feather_icons` - Icon library
- `flutter` - Framework
- Future: `http`, `provider`, `flutter_secure_storage`, etc.

---

**Last Updated**: 2025-01-XX
**Version**: 1.0.0

