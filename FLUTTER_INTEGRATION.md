# Flutter Mobile App Integration Guide

## CORS - You Won't Have Issues! ✅

**Important**: CORS (Cross-Origin Resource Sharing) is a **browser security feature**. Mobile apps (Flutter iOS/Android) **do NOT have CORS restrictions**. You can make direct API calls from your Flutter mobile app without any CORS configuration.

The CORS configuration in this Laravel backend is only needed if you're building:
- Flutter Web applications
- Web-based admin panels
- Testing APIs in a browser

## API Configuration

### Base URL

Your API base URL structure:
```
Development: http://amazinginventory.test/api/v1
Production:  https://yourdomain.com/api/v1
```

### Authentication

The API uses **Laravel Sanctum** with Bearer token authentication, which is perfect for mobile apps.

## Flutter Implementation Guide

### 1. API Service Setup

Create an API service class in your Flutter app:

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class ApiService {
  static const String baseUrl = 'http://amazinginventory.test/api/v1';
  String? _token;

  // Store token after login
  void setToken(String token) {
    _token = token;
  }

  // Get headers with authentication
  Map<String, String> get headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    
    if (_token != null) {
      headers['Authorization'] = 'Bearer $_token';
    }
    
    return headers;
  }

  // Login
  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/login'),
      headers: headers,
      body: jsonEncode({
        'email': email,
        'password': password,
      }),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      _token = data['access_token'];
      return data;
    } else {
      throw Exception('Login failed: ${response.body}');
    }
  }

  // Register
  Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/register'),
      headers: headers,
      body: jsonEncode({
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
      }),
    );

    if (response.statusCode == 201) {
      final data = jsonDecode(response.body);
      _token = data['access_token'];
      return data;
    } else {
      throw Exception('Registration failed: ${response.body}');
    }
  }

  // Get authenticated user
  Future<Map<String, dynamic>> getUser() async {
    final response = await http.get(
      Uri.parse('$baseUrl/user'),
      headers: headers,
    );

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Failed to get user: ${response.body}');
    }
  }

  // Logout
  Future<void> logout() async {
    final response = await http.post(
      Uri.parse('$baseUrl/logout'),
      headers: headers,
    );

    if (response.statusCode == 200) {
      _token = null;
    } else {
      throw Exception('Logout failed: ${response.body}');
    }
  }

  // Generic GET request
  Future<Map<String, dynamic>> get(String endpoint, {Map<String, String>? queryParams}) async {
    var uri = Uri.parse('$baseUrl/$endpoint');
    if (queryParams != null) {
      uri = uri.replace(queryParameters: queryParams);
    }

    final response = await http.get(uri, headers: headers);

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      throw Exception('GET request failed: ${response.body}');
    }
  }

  // Generic POST request
  Future<Map<String, dynamic>> post(String endpoint, Map<String, dynamic> data) async {
    final response = await http.post(
      Uri.parse('$baseUrl/$endpoint'),
      headers: headers,
      body: jsonEncode(data),
    );

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return jsonDecode(response.body);
    } else {
      throw Exception('POST request failed: ${response.body}');
    }
  }

  // Generic PUT request
  Future<Map<String, dynamic>> put(String endpoint, Map<String, dynamic> data) async {
    final response = await http.put(
      Uri.parse('$baseUrl/$endpoint'),
      headers: headers,
      body: jsonEncode(data),
    );

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return jsonDecode(response.body);
    } else {
      throw Exception('PUT request failed: ${response.body}');
    }
  }

  // Generic DELETE request
  Future<void> delete(String endpoint) async {
    final response = await http.delete(
      Uri.parse('$baseUrl/$endpoint'),
      headers: headers,
    );

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception('DELETE request failed: ${response.body}');
    }
  }
}
```

### 2. Token Storage

Use secure storage for tokens (recommended: `flutter_secure_storage`):

```dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TokenStorage {
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'auth_token';

  static Future<void> saveToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
  }

  static Future<String?> getToken() async {
    return await _storage.read(key: _tokenKey);
  }

  static Future<void> deleteToken() async {
    await _storage.delete(key: _tokenKey);
  }
}
```

### 3. Example Usage

```dart
// Initialize API service
final apiService = ApiService();

// Login
try {
  final response = await apiService.login('user@example.com', 'password123');
  final token = response['access_token'];
  await TokenStorage.saveToken(token);
  apiService.setToken(token);
  
  print('Login successful!');
  print('User: ${response['user']}');
} catch (e) {
  print('Login error: $e');
}

// Get products
try {
  final response = await apiService.get('products', queryParams: {
    'per_page': '20',
    'is_active': 'true',
  });
  print('Products: ${response['data']}');
} catch (e) {
  print('Error fetching products: $e');
}

// Create a product
try {
  final response = await apiService.post('products', {
    'name': 'New Product',
    'sku': 'PROD-001',
    'category_id': 1,
    'cost_price': 100.00,
    'selling_price': 150.00,
    'stock': 10,
    'is_active': true,
  });
  print('Product created: ${response['data']}');
} catch (e) {
  print('Error creating product: $e');
}
```

## Available API Endpoints

### Authentication
- `POST /api/v1/register` - Register new user
- `POST /api/v1/login` - Login
- `POST /api/v1/logout` - Logout (requires auth)
- `GET /api/v1/user` - Get authenticated user (requires auth)
- `GET /api/v1/user/permissions` - Get user permissions (requires auth)
- `POST /api/v1/user/check-permission` - Check specific permission (requires auth)

### Resources (All require authentication)
- `GET /api/v1/categories` - List categories
- `POST /api/v1/categories` - Create category
- `GET /api/v1/categories/{id}` - Get category
- `PUT /api/v1/categories/{id}` - Update category
- `DELETE /api/v1/categories/{id}` - Delete category

- `GET /api/v1/products` - List products
- `POST /api/v1/products` - Create product
- `GET /api/v1/products/{id}` - Get product
- `PUT /api/v1/products/{id}` - Update product
- `DELETE /api/v1/products/{id}` - Delete product

- `GET /api/v1/suppliers` - List suppliers
- `POST /api/v1/suppliers` - Create supplier
- `GET /api/v1/suppliers/{id}` - Get supplier
- `PUT /api/v1/suppliers/{id}` - Update supplier
- `DELETE /api/v1/suppliers/{id}` - Delete supplier

- `GET /api/v1/stock-movements` - List stock movements
- `POST /api/v1/stock-movements` - Create stock movement
- `GET /api/v1/stock-movements/{id}` - Get stock movement
- `PUT /api/v1/stock-movements/{id}` - Update stock movement
- `DELETE /api/v1/stock-movements/{id}` - Delete stock movement

- `GET /api/v1/purchases` - List purchases
- `POST /api/v1/purchases` - Create purchase
- `GET /api/v1/purchases/{id}` - Get purchase
- `PUT /api/v1/purchases/{id}` - Update purchase
- `DELETE /api/v1/purchases/{id}` - Delete purchase

- `GET /api/v1/sales` - List sales
- `POST /api/v1/sales` - Create sale
- `GET /api/v1/sales/{id}` - Get sale
- `PUT /api/v1/sales/{id}` - Update sale
- `DELETE /api/v1/sales/{id}` - Delete sale

- `GET /api/v1/expense-categories` - List expense categories
- `POST /api/v1/expense-categories` - Create expense category
- `GET /api/v1/expense-categories/{id}` - Get expense category
- `PUT /api/v1/expense-categories/{id}` - Update expense category
- `DELETE /api/v1/expense-categories/{id}` - Delete expense category

- `GET /api/v1/expenses` - List expenses
- `POST /api/v1/expenses` - Create expense
- `GET /api/v1/expenses/{id}` - Get expense
- `PUT /api/v1/expenses/{id}` - Update expense
- `DELETE /api/v1/expenses/{id}` - Delete expense

### Admin-Only Endpoints
- `GET /api/v1/users` - List users (admin only)
- `GET /api/v1/roles` - List roles (admin only)
- `GET /api/v1/permissions` - List permissions (admin only)

## Error Handling

The API returns consistent error responses:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

Handle errors in Flutter:

```dart
try {
  final response = await apiService.post('products', productData);
} on http.ClientException catch (e) {
  // Network error
  print('Network error: $e');
} catch (e) {
  // Parse error response
  final errorData = jsonDecode(e.toString());
  if (errorData['errors'] != null) {
    // Validation errors
    errorData['errors'].forEach((key, value) {
      print('$key: ${value.join(', ')}');
    });
  } else {
    print('Error: ${errorData['message']}');
  }
}
```

## Rate Limiting

- **Public routes** (register, login): 60 requests per minute
- **Protected routes**: 120 requests per minute

If you hit rate limits, you'll receive a `429 Too Many Requests` response. Implement retry logic with exponential backoff.

## Security Best Practices

1. **Always use HTTPS in production** - Never send tokens over HTTP
2. **Store tokens securely** - Use `flutter_secure_storage` or similar
3. **Handle token expiration** - Implement token refresh if needed
4. **Validate SSL certificates** - Don't disable certificate validation in production
5. **Implement proper error handling** - Don't expose sensitive information in error messages

## Testing

For local development, you can test against:
- Local Laravel server: `http://127.0.0.1:8000/api/v1`
- Laravel Herd: `http://amazinginventory.test/api/v1`

Make sure your Flutter app can reach the API endpoint. For Android emulator, use `10.0.2.2` instead of `localhost`:
- `http://10.0.2.2:8000/api/v1`

For iOS simulator, `localhost` works fine.

## Production Checklist

- [ ] Update base URL to production domain
- [ ] Ensure API uses HTTPS
- [ ] Implement proper token storage
- [ ] Add error handling and retry logic
- [ ] Test all API endpoints
- [ ] Implement offline caching if needed
- [ ] Add request/response logging for debugging
- [ ] Handle network connectivity issues gracefully


