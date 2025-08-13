# EcoShop - PostgreSQL E-commerce Web Application

## Overview

EcoShop is a comprehensive e-commerce web application built with PostgreSQL, HTML, CSS, JavaScript, jQuery, AJAX, and PHP. The application provides a complete online shopping experience with user functionality for browsing products, managing shopping carts, and completing purchases, along with a robust admin panel for product and order management.

## Features

### User Features
- **User Registration and Login**: Secure user authentication system
- **Product Browsing**: Browse products with filtering and search capabilities
- **Shopping Cart**: Add, update, and remove items from cart with AJAX
- **Checkout Process**: Complete purchase with shipping information
- **Order History**: View past orders and order status

### Admin Features
- **Admin Dashboard**: Overview of store statistics and metrics
- **Product Management**: Add, edit, and delete products
- **Order Management**: View and update order status
- **User Management**: View registered users

## Technology Stack

- **Database**: PostgreSQL 14+
- **Backend**: PHP 8.1+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Libraries**: jQuery 3.6+, Font Awesome 6.0+
- **Server**: PHP Built-in Development Server (for development)

## Installation and Setup

### Prerequisites

- PostgreSQL 14 or higher
- PHP 8.1 or higher with PDO PostgreSQL extension
- Web server (Apache/Nginx) or PHP built-in server for development

### Database Setup

1. Create a PostgreSQL database and user:
```sql
CREATE DATABASE ecoshop_db;
CREATE USER ecoshop_user WITH PASSWORD 'ecoshop123';
GRANT ALL PRIVILEGES ON DATABASE ecoshop_db TO ecoshop_user;
```

2. Import the database schema:
```bash
PGPASSWORD=ecoshop123 psql -h localhost -U ecoshop_user -d ecoshop_db -f database/ecommerce_postgresql_schema.sql
```

### Application Setup

1. Clone or extract the application files to your web directory
2. Update database configuration in `api/config.php` if needed
3. Ensure proper file permissions for the web server
4. Start the development server:
```bash
php -S localhost:8000
```

### Default Admin Account

- **Email**: admin@ecoshop.com
- **Password**: admin123

## Project Structure

```
ecommerce-postgresql/
├── api/                    # Backend API endpoints
│   ├── auth.php           # Authentication API
│   ├── auth_functions.php # Authentication helper functions
│   ├── cart.php           # Shopping cart API
│   ├── config.php         # Database configuration
│   ├── orders.php         # Orders management API
│   └── products.php       # Products management API
├── admin/                 # Admin panel
│   ├── admin.css         # Admin panel styles
│   ├── admin.js          # Admin panel JavaScript
│   └── index.html        # Admin panel interface
├── css/                   # Frontend stylesheets
│   └── style.css         # Main application styles
├── database/              # Database schema and setup
│   └── ecommerce_postgresql_schema.sql
├── images/                # Product images and assets
├── js/                    # Frontend JavaScript files
│   └── script.js         # Main application JavaScript
├── index.html            # Main application interface
└── README.md             # This documentation file
```

## API Endpoints

### Authentication API (`/api/auth.php`)

#### POST - Login
```json
{
  "action": "login",
  "email": "user@example.com",
  "password": "password123"
}
```

#### POST - Register
```json
{
  "action": "register",
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123"
}
```

#### GET - Get Current User
Returns current authenticated user information.

#### DELETE - Logout
Logs out the current user.

### Products API (`/api/products.php`)

#### GET - Get All Products
```
GET /api/products.php
GET /api/products.php?category=Electronics
GET /api/products.php?search=headphones
```

#### POST - Create Product (Admin Only)
```json
{
  "name": "Product Name",
  "price": 99.99,
  "category": "Electronics",
  "description": "Product description",
  "image": "images/product.jpg",
  "discount": 10,
  "in_stock": true
}
```

#### PUT - Update Product (Admin Only)
```json
{
  "id": 1,
  "name": "Updated Product Name",
  "price": 89.99,
  "category": "Electronics",
  "description": "Updated description",
  "image": "images/updated-product.jpg",
  "discount": 15,
  "in_stock": true
}
```

#### DELETE - Delete Product (Admin Only)
```json
{
  "id": 1
}
```

### Cart API (`/api/cart.php`)

#### GET - Get Cart Items
Returns all items in the current user's cart.

#### POST - Add to Cart
```json
{
  "productId": 1,
  "quantity": 2
}
```

#### PUT - Update Cart Item
```json
{
  "productId": 1,
  "quantity": 3
}
```

#### DELETE - Remove from Cart
```json
{
  "productId": 1
}
```

### Orders API (`/api/orders.php`)

#### GET - Get Orders
Returns orders for the current user (or all orders for admin).

#### POST - Create Order
```json
{
  "items": [
    {
      "productId": 1,
      "quantity": 2
    }
  ],
  "shipping_address": "123 Main St, City, State 12345"
}
```

#### PUT - Update Order Status (Admin Only)
```json
{
  "id": 1,
  "status": "shipped"
}
```

#### DELETE - Delete Order (Admin Only)
```json
{
  "id": 1
}
```

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Products Table
```sql
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(500),
    description TEXT,
    category VARCHAR(100),
    rating DECIMAL(3,2) DEFAULT 0,
    in_stock BOOLEAN DEFAULT TRUE,
    discount INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Orders Table
```sql
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    total DECIMAL(10,2) NOT NULL,
    shipping_address TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Order Items Table
```sql
CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    quantity INTEGER NOT NULL,
    price DECIMAL(10,2) NOT NULL
);
```

## Usage Guide

### For Customers

1. **Browse Products**: Visit the main page to view available products
2. **Search and Filter**: Use the search bar and category filters to find specific products
3. **Add to Cart**: Click "Add to Cart" on any product to add it to your shopping cart
4. **View Cart**: Click the cart icon to view and manage cart items
5. **Checkout**: Proceed to checkout to complete your purchase
6. **Account Management**: Register for an account to track orders and save preferences

### For Administrators

1. **Access Admin Panel**: Navigate to `/admin` and login with admin credentials
2. **Dashboard**: View store statistics and overview
3. **Manage Products**: Add, edit, or delete products from the inventory
4. **Process Orders**: View and update order status as they progress
5. **User Management**: View registered users and their information

## Security Features

- **Password Hashing**: All passwords are securely hashed using PHP's password_hash()
- **SQL Injection Protection**: All database queries use prepared statements
- **Session Management**: Secure session handling for user authentication
- **Admin Authorization**: Admin-only endpoints are protected with proper authorization checks
- **CORS Headers**: Proper CORS configuration for API security

## Development Notes

### PostgreSQL Adaptations

This application has been specifically adapted for PostgreSQL from a MySQL version, including:

- **Boolean Handling**: Proper handling of PostgreSQL boolean types
- **Auto-increment Fields**: Using SERIAL instead of AUTO_INCREMENT
- **RETURNING Clause**: Utilizing PostgreSQL's RETURNING clause for insert operations
- **Data Type Conversions**: Proper type casting for PostgreSQL compatibility

### AJAX Implementation

The application uses jQuery AJAX for:
- Dynamic product loading and filtering
- Shopping cart operations without page refresh
- Real-time form validation
- Admin panel operations

### Responsive Design

The application is fully responsive and works on:
- Desktop computers
- Tablets
- Mobile devices

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check PostgreSQL service is running
   - Verify database credentials in `api/config.php`
   - Ensure database and user exist

2. **Products Not Loading**
   - Check PHP error logs
   - Verify database schema is properly imported
   - Ensure sample data is inserted

3. **Admin Panel Not Accessible**
   - Verify admin user exists in database
   - Check admin credentials
   - Ensure proper file permissions

### Error Logs

Check the following for error information:
- PHP error logs
- PostgreSQL logs
- Browser developer console
- Network tab for API request failures

## Contributing

To contribute to this project:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

Refer to [LICENSE](https://github.com/AfnanBinAbbas/EcoShop-Secure_PHP_E-Commerce_Platform/blob/main/LICENSE)

## Support

For support and questions:
- Check the troubleshooting section
- Review the API documentation
- Examine the code comments for implementation details

---

**Author**: Afnan Bin Abbas  
**Version**: 1.4.0  
**Last Updated**: August 6, 2025

