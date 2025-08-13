<?php
require_once 'config.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDatabase();

if (!$pdo) {
    sendErrorResponse('Database connection failed', 500);
}

// For simplicity, we'll use session-based cart storage
// In a production environment, you might want to store cart items in the database
session_start();

switch ($method) {
    case 'GET':
        handleGetCart($pdo);
        break;
    case 'POST':
        handleAddToCart($pdo);
        break;
    case 'PUT':
        handleUpdateCart($pdo);
        break;
    case 'DELETE':
        handleRemoveFromCart($pdo);
        break;
    default:
        sendErrorResponse('Method not allowed', 405);
}

function handleGetCart($pdo) {
    try {
        $cart = $_SESSION['cart'] ?? [];
        
        // Get detailed cart information with product details
        $cartWithDetails = getCartWithDetails($pdo);
        
        sendSuccessResponse($cartWithDetails);
        
    } catch (Exception $e) {
        error_log("Get cart error: " . $e->getMessage());
        sendErrorResponse('Failed to retrieve cart', 500);
    }
}

function handleAddToCart($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendErrorResponse('Invalid JSON input');
        }
        
        $requiredFields = ['productId', 'quantity'];
        $error = validateRequired($requiredFields, $input);
        if ($error) {
            sendErrorResponse($error);
        }
        
        $productId = (int)$input['productId'];
        $quantity = (int)$input['quantity'];
        
        if ($quantity <= 0) {
            sendErrorResponse('Quantity must be greater than 0');
        }
        
        // Verify product exists and is in stock (PostgreSQL boolean handling)
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND in_stock = TRUE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            sendErrorResponse('Product not found or out of stock', 404);
        }
        
        // Initialize cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Check if product already in cart
        $existingIndex = -1;
        foreach ($_SESSION['cart'] as $index => $item) {
            if ($item['productId'] == $productId) {
                $existingIndex = $index;
                break;
            }
        }
        
        if ($existingIndex >= 0) {
            // Update existing item
            $_SESSION['cart'][$existingIndex]['quantity'] += $quantity;
        } else {
            // Add new item
            $_SESSION['cart'][] = [
                'productId' => $productId,
                'quantity' => $quantity,
                'addedAt' => date('Y-m-d H:i:s')
            ];
        }
        
        // Get detailed cart information
        $cartWithDetails = getCartWithDetails($pdo);
        
        sendSuccessResponse($cartWithDetails, 'Product added to cart');
        
    } catch (PDOException $e) {
        error_log("Add to cart error: " . $e->getMessage());
        sendErrorResponse('Failed to add product to cart', 500);
    }
}

function handleUpdateCart($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendErrorResponse('Invalid JSON input');
        }
        
        $requiredFields = ['productId', 'quantity'];
        $error = validateRequired($requiredFields, $input);
        if ($error) {
            sendErrorResponse($error);
        }
        
        $productId = (int)$input['productId'];
        $quantity = (int)$input['quantity'];
        
        if ($quantity < 0) {
            sendErrorResponse('Quantity cannot be negative');
        }
        
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Find and update item
        $found = false;
        foreach ($_SESSION['cart'] as $index => $item) {
            if ($item['productId'] == $productId) {
                if ($quantity == 0) {
                    // Remove item if quantity is 0
                    array_splice($_SESSION['cart'], $index, 1);
                } else {
                    // Update quantity
                    $_SESSION['cart'][$index]['quantity'] = $quantity;
                }
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            sendErrorResponse('Product not found in cart', 404);
        }
        
        // Get detailed cart information
        $cartWithDetails = getCartWithDetails($pdo);
        
        sendSuccessResponse($cartWithDetails, 'Cart updated successfully');
        
    } catch (Exception $e) {
        error_log("Update cart error: " . $e->getMessage());
        sendErrorResponse('Failed to update cart', 500);
    }
}

function handleRemoveFromCart($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['productId'])) {
            sendErrorResponse('Product ID is required');
        }
        
        $productId = (int)$input['productId'];
        
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Find and remove item
        $found = false;
        foreach ($_SESSION['cart'] as $index => $item) {
            if ($item['productId'] == $productId) {
                array_splice($_SESSION['cart'], $index, 1);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            sendErrorResponse('Product not found in cart', 404);
        }
        
        // Get detailed cart information
        $cartWithDetails = getCartWithDetails($pdo);
        
        sendSuccessResponse($cartWithDetails, 'Product removed from cart');
        
    } catch (Exception $e) {
        error_log("Remove from cart error: " . $e->getMessage());
        sendErrorResponse('Failed to remove product from cart', 500);
    }
}

// Helper function to get cart with product details
function getCartWithDetails($pdo) {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return [];
    }
    
    $cartWithDetails = [];
    
    foreach ($_SESSION['cart'] as $cartItem) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$cartItem['productId']]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Convert types for PostgreSQL
            $product['id'] = (int)$product['id'];
            $product['price'] = (float)$product['price'];
            $product['rating'] = (float)$product['rating'];
            $product['in_stock'] = $product['in_stock'] === 't' || $product['in_stock'] === true;
            $product['discount'] = (int)$product['discount'];
            $product['inStock'] = $product['in_stock'];
            
            $cartWithDetails[] = [
                'product' => $product,
                'quantity' => $cartItem['quantity'],
                'addedAt' => $cartItem['addedAt']
            ];
        }
    }
    
    return $cartWithDetails;
}

/**
 * Clear cart (useful after checkout)
 */
function clearCart() {
    $_SESSION['cart'] = [];
}

/**
 * Get cart total
 * @param PDO $pdo Database connection
 * @return float Total cart value
 */
function getCartTotal($pdo) {
    $cartWithDetails = getCartWithDetails($pdo);
    $total = 0;
    
    foreach ($cartWithDetails as $item) {
        $price = $item['product']['price'];
        $discount = $item['product']['discount'];
        $quantity = $item['quantity'];
        
        // Apply discount if available
        if ($discount > 0) {
            $price = $price * (1 - $discount / 100);
        }
        
        $total += $price * $quantity;
    }
    
    return round($total, 2);
}

/**
 * Get cart item count
 * @return int Total number of items in cart
 */
function getCartItemCount() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
    
    return $count;
}

/**
 * Validate cart items (check if products still exist and are in stock)
 * @param PDO $pdo Database connection
 * @return array Array of validation errors
 */
function validateCart($pdo) {
    $errors = [];
    
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return $errors;
    }
    
    foreach ($_SESSION['cart'] as $index => $item) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$item['productId']]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $errors[] = "Product with ID {$item['productId']} no longer exists";
            // Remove invalid item from cart
            array_splice($_SESSION['cart'], $index, 1);
        } elseif (!($product['in_stock'] === 't' || $product['in_stock'] === true)) {
            $errors[] = "Product '{$product['name']}' is no longer in stock";
            // Remove out of stock item from cart
            array_splice($_SESSION['cart'], $index, 1);
        }
    }
    
    return $errors;
}
?>

