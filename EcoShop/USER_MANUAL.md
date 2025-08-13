# EcoShop User Manual

## Table of Contents

1. [Getting Started](#getting-started)
2. [Customer Guide](#customer-guide)
3. [Administrator Guide](#administrator-guide)
4. [Troubleshooting](#troubleshooting)
5. [Frequently Asked Questions](#frequently-asked-questions)

## Getting Started

### System Requirements

To use EcoShop, you need:
- A modern web browser (Chrome, Firefox, Safari, Edge)
- Internet connection
- JavaScript enabled in your browser

### Accessing the Application

1. Open your web browser
2. Navigate to the EcoShop URL provided by your administrator
3. The homepage will load showing featured products and navigation options

## Customer Guide

### Creating an Account

1. Click the "Login" button in the top navigation
2. Click "Register" or "Create Account" 
3. Fill in the registration form:
   - **Full Name**: Enter your complete name
   - **Email Address**: Use a valid email address (this will be your username)
   - **Password**: Create a strong password (minimum 6 characters)
4. Click "Register" to create your account
5. You will be automatically logged in after successful registration

### Logging In

1. Click the "Login" button in the top navigation
2. Enter your email address and password
3. Click "Login" to access your account
4. Once logged in, you'll see your name in the top navigation

### Browsing Products

#### Viewing All Products
- The homepage displays featured products automatically
- Scroll down to see more products
- Each product shows:
  - Product image
  - Name and description
  - Price (with discounts if applicable)
  - Customer rating
  - Stock status

#### Searching for Products
1. Use the search bar in the top navigation
2. Type keywords related to the product you're looking for
3. Press Enter or click the search button
4. Results will display matching products

#### Filtering Products
1. Use the "Category" dropdown to filter by product category:
   - Electronics
   - Accessories
   - Gaming
   - All Categories (to remove filter)
2. Use the "Sort by" dropdown to organize results:
   - Name (alphabetical)
   - Price: Low to High
   - Price: High to Low
   - Rating (highest first)

### Shopping Cart Management

#### Adding Items to Cart
1. Find the product you want to purchase
2. Click the "Add to Cart" button
3. The item will be added to your cart
4. The cart icon will update to show the number of items

#### Viewing Your Cart
1. Click the cart icon in the top navigation
2. A cart sidebar or page will open showing:
   - All items in your cart
   - Quantity of each item
   - Individual and total prices
   - Discount information if applicable

#### Updating Cart Items
1. In the cart view, find the item you want to modify
2. Use the quantity controls (+ and - buttons) to adjust quantity
3. Changes are saved automatically
4. The total price updates in real-time

#### Removing Items from Cart
1. In the cart view, find the item you want to remove
2. Click the "Remove" or trash icon button
3. The item will be immediately removed from your cart
4. The total price will update automatically

### Checkout Process

#### Reviewing Your Order
1. Click "Checkout" or "Proceed to Checkout" from your cart
2. Review all items in your order:
   - Product names and quantities
   - Individual prices
   - Subtotal and total amount
   - Applied discounts

#### Entering Shipping Information
1. Fill in the shipping address form:
   - **Full Name**: Recipient's complete name
   - **Address Line 1**: Street address
   - **Address Line 2**: Apartment, suite, or unit number (optional)
   - **City**: City name
   - **State/Province**: State or province
   - **ZIP/Postal Code**: Postal code
   - **Country**: Select your country

#### Payment Information
1. Enter your payment details:
   - **Card Number**: Credit or debit card number
   - **Expiry Date**: MM/YY format
   - **CVV**: Security code on back of card
   - **Cardholder Name**: Name as it appears on the card

#### Completing Your Order
1. Review all information for accuracy
2. Read and accept the terms and conditions
3. Click "Place Order" to complete your purchase
4. You will receive an order confirmation with:
   - Order number
   - Estimated delivery date
   - Order summary

### Order History and Tracking

#### Viewing Your Orders
1. Log in to your account
2. Navigate to "My Orders" or "Order History"
3. You'll see a list of all your orders with:
   - Order number
   - Order date
   - Total amount
   - Current status

#### Order Status Meanings
- **Pending**: Order received, awaiting processing
- **Processing**: Order is being prepared for shipment
- **Shipped**: Order has been dispatched
- **Delivered**: Order has been delivered
- **Cancelled**: Order has been cancelled

#### Tracking Your Order
1. Find your order in the order history
2. Click on the order number for details
3. View tracking information if available
4. Contact customer service for additional tracking details

## Administrator Guide

### Accessing the Admin Panel

1. Navigate to `/admin` on your EcoShop installation
2. Enter your administrator credentials:
   - **Email**: Your admin email address
   - **Password**: Your admin password
3. Click "Login" to access the admin dashboard

### Admin Dashboard

The dashboard provides an overview of your store:
- **Total Products**: Number of products in your catalog
- **Total Orders**: Number of orders received
- **Total Users**: Number of registered customers
- **Total Revenue**: Sum of all completed orders

### Product Management

#### Adding New Products
1. Navigate to the "Products" section
2. Click "Add Product" button
3. Fill in the product information:
   - **Name**: Product title
   - **Price**: Product price in dollars
   - **Category**: Product category
   - **Description**: Detailed product description
   - **Image URL**: Path to product image
   - **Discount**: Percentage discount (0-100)
   - **In Stock**: Check if product is available
4. Click "Save Product" to add the product

#### Editing Existing Products
1. In the Products section, find the product to edit
2. Click the "Edit" button for that product
3. Modify the product information as needed
4. Click "Save Product" to update the product

#### Deleting Products
1. In the Products section, find the product to delete
2. Click the "Delete" button for that product
3. Confirm the deletion when prompted
4. The product will be permanently removed

### Order Management

#### Viewing Orders
1. Navigate to the "Orders" section
2. View all customer orders with:
   - Order ID
   - Customer name and email
   - Order total
   - Current status
   - Order date

#### Updating Order Status
1. Find the order you want to update
2. Use the status dropdown to select new status:
   - Pending
   - Processing
   - Shipped
   - Delivered
   - Cancelled
3. The status updates automatically when changed

#### Order Details
1. Click on an order ID to view full details
2. See all items in the order
3. View customer information
4. Check shipping address

### User Management

#### Viewing Registered Users
1. Navigate to the "Users" section
2. View all registered customers with:
   - User ID
   - Name and email
   - Account type (Admin/User)
   - Registration date

#### Managing User Accounts
1. Find the user account to manage
2. Click "Edit" to modify user information
3. Change user privileges if needed
4. Save changes to update the account

## Troubleshooting

### Common Customer Issues

#### Cannot Add Items to Cart
**Problem**: "Add to Cart" button doesn't work
**Solutions**:
1. Ensure JavaScript is enabled in your browser
2. Check if the product is in stock
3. Try refreshing the page
4. Clear your browser cache and cookies

#### Login Problems
**Problem**: Cannot log in to account
**Solutions**:
1. Verify your email address is correct
2. Check if Caps Lock is on when entering password
3. Try resetting your password
4. Contact customer support if issues persist

#### Checkout Issues
**Problem**: Cannot complete purchase
**Solutions**:
1. Verify all required fields are filled
2. Check that your payment information is correct
3. Ensure your cart is not empty
4. Try using a different browser

#### Page Loading Problems
**Problem**: Pages load slowly or not at all
**Solutions**:
1. Check your internet connection
2. Try refreshing the page
3. Clear browser cache and cookies
4. Try accessing from a different device

### Common Admin Issues

#### Cannot Access Admin Panel
**Problem**: Admin login fails
**Solutions**:
1. Verify admin credentials are correct
2. Check if admin account exists in database
3. Ensure you're accessing the correct URL (/admin)
4. Contact system administrator

#### Products Not Displaying
**Problem**: Products don't appear on the website
**Solutions**:
1. Check if products are marked as "In Stock"
2. Verify product information is complete
3. Check image URLs are correct
4. Refresh the main website

#### Order Status Not Updating
**Problem**: Order status changes don't save
**Solutions**:
1. Ensure you have admin privileges
2. Check database connection
3. Try refreshing the admin panel
4. Contact technical support

## Frequently Asked Questions

### General Questions

**Q: Is my personal information secure?**
A: Yes, EcoShop uses industry-standard security measures including password hashing and secure session management to protect your data.

**Q: Can I change my account information?**
A: Yes, you can update your account information by logging in and accessing your account settings.

**Q: How do I reset my password?**
A: Use the "Forgot Password" link on the login page to reset your password via email.

### Shopping Questions

**Q: Can I modify my order after placing it?**
A: Orders can only be modified before they enter "Processing" status. Contact customer service for assistance.

**Q: What payment methods are accepted?**
A: EcoShop accepts major credit and debit cards. Additional payment methods may be available depending on your location.

**Q: How long does shipping take?**
A: Shipping times vary by location and product. Estimated delivery dates are provided during checkout.

**Q: Can I cancel my order?**
A: Orders can be cancelled before they are shipped. Contact customer service to cancel an order.

### Technical Questions

**Q: Which browsers are supported?**
A: EcoShop works with all modern browsers including Chrome, Firefox, Safari, and Edge.

**Q: Do I need to create an account to shop?**
A: While you can browse products without an account, you need to register to make purchases and track orders.

**Q: Is the website mobile-friendly?**
A: Yes, EcoShop is fully responsive and works on all devices including smartphones and tablets.

### Admin Questions

**Q: How do I add product images?**
A: Upload images to the images folder and enter the relative path (e.g., "images/product.jpg") in the product form.

**Q: Can I export order data?**
A: Order data can be accessed through the admin panel. Contact technical support for export options.

**Q: How do I backup my data?**
A: Regular database backups should be performed by your system administrator.

## Contact Information

For additional support:
- **Technical Issues**: Contact your system administrator
- **Account Problems**: Use the contact form on the website
- **Order Questions**: Check your order status in your account

---

This manual covers the essential functions of EcoShop. For advanced features or custom configurations, consult with your system administrator or technical support team.

