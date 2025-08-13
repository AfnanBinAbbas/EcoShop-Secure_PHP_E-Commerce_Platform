<?php
require_once 'config.php';
require_once 'auth_functions.php';
require_once 'cookie_manager.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDatabase();

if (!$pdo) {
    sendErrorResponse('Database connection failed', 500);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

switch ($method) {
    case 'GET':
        handleGetOrders($pdo);
        break;
    case 'POST':
        handleCreateOrder($pdo);
        break;
    case 'PUT':
        handleUpdateOrder($pdo);
        break;
    case 'DELETE':
        handleDeleteOrder($pdo);
        break;
    default:
        sendErrorResponse('Method not allowed', 405);
}

function handleGetOrders($pdo) {
    try {
        // Check if user is authenticated
        requireAuth();
        
        $userId = getCurrentUserId();
        $isAdminUser = isAdmin();
        
        if ($isAdminUser) {
            // Admin can see all orders
            $sql = "SELECT o.*, u.name as user_name, u.email as user_email 
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    ORDER BY o.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } else {
            // Regular users can only see their own orders
            $sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
        }
        
        $orders = $stmt->fetchAll();
        
        // Get order items for each order
        foreach ($orders as &$order) {
            $order['id'] = (int)$order['id'];
            $order['user_id'] = (int)$order['user_id'];
            $order['total'] = (float)$order['total'];
            
            // Get order items with product details
            $itemsSql = "SELECT oi.*, p.name as product_name, p.image as product_image 
                         FROM order_items oi 
                         JOIN products p ON oi.product_id = p.id 
                         WHERE oi.order_id = ?";
            $itemsStmt = $pdo->prepare($itemsSql);
            $itemsStmt->execute([$order['id']]);
            $items = $itemsStmt->fetchAll();
            
            // Convert types for items
            foreach ($items as &$item) {
                $item['id'] = (int)$item['id'];
                $item['order_id'] = (int)$item['order_id'];
                $item['product_id'] = (int)$item['product_id'];
                $item['quantity'] = (int)$item['quantity'];
                $item['price'] = (float)$item['price'];
            }
            
            $order['items'] = $items;
        }
        
        sendSuccessResponse($orders);
        
    } catch (PDOException $e) {
        error_log("Get orders error: " . $e->getMessage());
        sendErrorResponse('Failed to retrieve orders', 500);
    }
}

function handleCreateOrder($pdo) {
    try {
        // Check if user is authenticated
        requireAuth();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendErrorResponse('Invalid JSON input');
        }
        
        $requiredFields = ['items', 'shipping_address'];
        $error = validateRequired($requiredFields, $input);
        if ($error) {
            sendErrorResponse($error);
        }
        
        $userId = getCurrentUserId();
        $items = $input['items'];
        $shippingAddress = sanitizeInput($input['shipping_address']);
        
        if (empty($items) || !is_array($items)) {
            sendErrorResponse('Order must contain at least one item');
        }
        
        if (strlen($shippingAddress) < 10) {
            sendErrorResponse('Shipping address must be at least 10 characters long');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            $total = 0;
            $validatedItems = [];
            
            // Validate items and calculate total
            foreach ($items as $item) {
                if (!isset($item['productId']) || !isset($item['quantity'])) {
                    throw new Exception('Invalid item format');
                }
                
                $productId = (int)$item['productId'];
                $quantity = (int)$item['quantity'];
                
                if ($quantity <= 0) {
                    throw new Exception('Item quantity must be greater than 0');
                }
                
                // Get product details and verify availability (PostgreSQL boolean handling)
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND in_stock = TRUE");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    throw new Exception("Product with ID $productId not found or out of stock");
                }
                
                $price = (float)$product['price'];
                
                // Apply discount if available
                if ($product['discount'] > 0) {
                    $price = $price * (1 - $product['discount'] / 100);
                }
                
                $itemTotal = $price * $quantity;
                $total += $itemTotal;
                
                $validatedItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $price
                ];
            }
            
            // Create order (PostgreSQL version with RETURNING clause)
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, shipping_address, status) VALUES (?, ?, ?, 'pending') RETURNING id");
            $stmt->execute([$userId, $total, $shippingAddress]);
            $result = $stmt->fetch();
            $orderId = $result['id'];
            
            // Create order items
            foreach ($validatedItems as $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Clear cart after successful order
            if (isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            // Save order cookie to order_cookies.txt
            saveOrderCookie(session_id());
            
            // Get the created order with details
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            // Get order items
            $itemsSql = "SELECT oi.*, p.name as product_name, p.image as product_image 
                         FROM order_items oi 
                         JOIN products p ON oi.product_id = p.id 
                         WHERE oi.order_id = ?";
            $itemsStmt = $pdo->prepare($itemsSql);
            $itemsStmt->execute([$orderId]);
            $orderItems = $itemsStmt->fetchAll();
            
            // Convert types
            $order['id'] = (int)$order['id'];
            $order['user_id'] = (int)$order['user_id'];
            $order['total'] = (float)$order['total'];
            
            foreach ($orderItems as &$item) {
                $item['id'] = (int)$item['id'];
                $item['order_id'] = (int)$item['order_id'];
                $item['product_id'] = (int)$item['product_id'];
                $item['quantity'] = (int)$item['quantity'];
                $item['price'] = (float)$item['price'];
            }
            
            $order['items'] = $orderItems;
            
            sendSuccessResponse($order, 'Order created successfully', 201);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Create order error: " . $e->getMessage());
        sendErrorResponse('Failed to create order: ' . $e->getMessage(), 500);
    }
}

function handleUpdateOrder($pdo) {
    try {
        // Check if user is admin (only admins can update order status)
        requireAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            sendErrorResponse('Order ID is required');
        }
        
        $orderId = (int)$input['id'];
        
        // Check if order exists
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            sendErrorResponse('Order not found', 404);
        }
        
        // Update status if provided
        if (isset($input['status'])) {
            $status = sanitizeInput($input['status']);
            $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            
            if (!in_array($status, $allowedStatuses)) {
                sendErrorResponse('Invalid order status');
            }
            
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $orderId]);
        }
        
        // Get updated order
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $updatedOrder = $stmt->fetch();
        
        // Convert types
        $updatedOrder['id'] = (int)$updatedOrder['id'];
        $updatedOrder['user_id'] = (int)$updatedOrder['user_id'];
        $updatedOrder['total'] = (float)$updatedOrder['total'];
        
        sendSuccessResponse($updatedOrder, 'Order updated successfully');
        
    } catch (PDOException $e) {
        error_log("Update order error: " . $e->getMessage());
        sendErrorResponse('Failed to update order', 500);
    }
}

function handleDeleteOrder($pdo) {
    try {
        // Check if user is admin
        requireAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            sendErrorResponse('Order ID is required');
        }
        
        $orderId = (int)$input['id'];
        
        // Check if order exists
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            sendErrorResponse('Order not found', 404);
        }
        
        // Delete order (order_items will be deleted automatically due to CASCADE)
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        
        sendSuccessResponse(null, 'Order deleted successfully');
        
    } catch (PDOException $e) {
        error_log("Delete order error: " . $e->getMessage());
        sendErrorResponse('Failed to delete order', 500);
    }
}

/**
 * Create order from current cart
 * @param PDO $pdo Database connection
 * @param string $shippingAddress Shipping address
 * @return array Created order data
 */
function createOrderFromCart($pdo, $shippingAddress) {
    requireAuth();
    
    // Include cart functions
    require_once 'cart.php';
    
    $cartItems = getCartWithDetails($pdo);
    
    if (empty($cartItems)) {
        throw new Exception('Cart is empty');
    }
    
    $orderItems = [];
    foreach ($cartItems as $cartItem) {
        $orderItems[] = [
            'productId' => $cartItem['product']['id'],
            'quantity' => $cartItem['quantity']
        ];
    }
    
    // Create order using the existing handleCreateOrder logic
    $orderData = [
        'items' => $orderItems,
        'shipping_address' => $shippingAddress
    ];
    
    return $orderData;
}

/**
 * Get order statistics for admin dashboard
 * @param PDO $pdo Database connection
 * @return array Order statistics
 */
function getOrderStatistics($pdo) {
    requireAdmin();
    
    try {
        $stats = [];
        
        // Total orders
        $stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM orders");
        $stats['total_orders'] = (int)$stmt->fetchColumn();
        
        // Total revenue
        $stmt = $pdo->query("SELECT SUM(total) as total_revenue FROM orders WHERE status != 'cancelled'");
        $stats['total_revenue'] = (float)$stmt->fetchColumn();
        
        // Orders by status
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
        $statusCounts = $stmt->fetchAll();
        $stats['orders_by_status'] = [];
        foreach ($statusCounts as $status) {
            $stats['orders_by_status'][$status['status']] = (int)$status['count'];
        }
        
        // Recent orders (last 30 days)
        $stmt = $pdo->query("SELECT COUNT(*) as recent_orders FROM orders WHERE created_at >= NOW() - INTERVAL '30 days'");
        $stats['recent_orders'] = (int)$stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Get order statistics error: " . $e->getMessage());
        return [];
    }
}
?>

