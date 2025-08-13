<?php
require_once 'config.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDatabase();

if (!$pdo) {
    sendErrorResponse('Database connection failed', 500);
}

switch ($method) {
    case 'GET':
        handleGetProducts($pdo);
        break;
    case 'POST':
        // Start session only for admin operations
        session_start();
        require_once 'auth.php';
        handleCreateProduct($pdo);
        break;
    case 'PUT':
        // Start session only for admin operations
        session_start();
        require_once 'auth.php';
        handleUpdateProduct($pdo);
        break;
    case 'DELETE':
        // Start session only for admin operations
        session_start();
        require_once 'auth.php';
        handleDeleteProduct($pdo);
        break;
    default:
        sendErrorResponse('Method not allowed', 405);
}

function handleGetProducts($pdo) {
    try {
        $category = $_GET['category'] ?? '';
        $search = $_GET['search'] ?? '';
        $sort = $_GET['sort'] ?? 'name';
        $order = $_GET['order'] ?? 'ASC';
        
        // Build the query
        $sql = "SELECT * FROM products WHERE 1=1";
        $params = [];
        
        if (!empty($category) && $category !== 'all') {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        if (!empty($search)) {
            $sql .= " AND (name ILIKE ? OR description ILIKE ? OR category ILIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Validate sort column
        $allowedSorts = ['name', 'price', 'rating', 'created_at'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'name';
        }
        
        // Validate order
        $order = strtoupper($order);
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'ASC';
        }
        
        $sql .= " ORDER BY $sort $order";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Convert boolean and numeric fields for PostgreSQL
        foreach ($products as &$product) {
            $product['id'] = (int)$product['id'];
            $product['price'] = (float)$product['price'];
            $product['rating'] = (float)$product['rating'];
            $product['in_stock'] = $product['in_stock'] === 't' || $product['in_stock'] === true;
            $product['discount'] = (int)$product['discount'];
            $product['inStock'] = $product['in_stock']; // For frontend compatibility
        }
        
        sendSuccessResponse($products);
        
    } catch (PDOException $e) {
        error_log("Get products error: " . $e->getMessage());
        sendErrorResponse('Failed to retrieve products', 500);
    }
}

function handleCreateProduct($pdo) {
    try {
        // Check if user is admin
        requireAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendErrorResponse('Invalid JSON input');
        }
        
        $requiredFields = ['name', 'price', 'image', 'description', 'category'];
        $error = validateRequired($requiredFields, $input);
        if ($error) {
            sendErrorResponse($error);
        }
        
        $name = sanitizeInput($input['name']);
        $price = (float)$input['price'];
        $image = sanitizeInput($input['image']);
        $description = sanitizeInput($input['description']);
        $category = sanitizeInput($input['category']);
        $rating = isset($input['rating']) ? (float)$input['rating'] : 0;
        $in_stock = isset($input['in_stock']) ? (bool)$input['in_stock'] : true;
        $discount = isset($input['discount']) ? (int)$input['discount'] : 0;
        
        if ($price <= 0) {
            sendErrorResponse('Price must be greater than 0');
        }
        
        // PostgreSQL version with RETURNING clause
        $sql = "INSERT INTO products (name, price, image, description, category, rating, in_stock, discount) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $price, $image, $description, $category, $rating, $in_stock, $discount]);
        $result = $stmt->fetch();
        $productId = $result['id'];
        
        // Get the created product
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        // Convert types for PostgreSQL
        $product['id'] = (int)$product['id'];
        $product['price'] = (float)$product['price'];
        $product['rating'] = (float)$product['rating'];
        $product['in_stock'] = $product['in_stock'] === 't' || $product['in_stock'] === true;
        $product['discount'] = (int)$product['discount'];
        $product['inStock'] = $product['in_stock'];
        
        sendSuccessResponse($product, 'Product created successfully', 201);
        
    } catch (PDOException $e) {
        error_log("Create product error: " . $e->getMessage());
        sendErrorResponse('Failed to create product', 500);
    }
}

function handleUpdateProduct($pdo) {
    try {
        // Check if user is admin
        requireAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            sendErrorResponse('Product ID is required');
        }
        
        $productId = (int)$input['id'];
        
        // Check if product exists
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $existingProduct = $stmt->fetch();
        
        if (!$existingProduct) {
            sendErrorResponse('Product not found', 404);
        }
        
        // Build update query dynamically
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['name', 'price', 'image', 'description', 'category', 'rating', 'in_stock', 'discount'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                
                if ($field === 'price' || $field === 'rating') {
                    $params[] = (float)$input[$field];
                } elseif ($field === 'in_stock') {
                    $params[] = (bool)$input[$field];
                } elseif ($field === 'discount') {
                    $params[] = (int)$input[$field];
                } else {
                    $params[] = sanitizeInput($input[$field]);
                }
            }
        }
        
        if (empty($updateFields)) {
            sendErrorResponse('No valid fields to update');
        }
        
        // PostgreSQL automatically updates updated_at via trigger
        $params[] = $productId;
        
        $sql = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Get the updated product
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        // Convert types for PostgreSQL
        $product['id'] = (int)$product['id'];
        $product['price'] = (float)$product['price'];
        $product['rating'] = (float)$product['rating'];
        $product['in_stock'] = $product['in_stock'] === 't' || $product['in_stock'] === true;
        $product['discount'] = (int)$product['discount'];
        $product['inStock'] = $product['in_stock'];
        
        sendSuccessResponse($product, 'Product updated successfully');
        
    } catch (PDOException $e) {
        error_log("Update product error: " . $e->getMessage());
        sendErrorResponse('Failed to update product', 500);
    }
}

function handleDeleteProduct($pdo) {
    try {
        // Check if user is admin
        requireAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            sendErrorResponse('Product ID is required');
        }
        
        $productId = (int)$input['id'];
        
        // Check if product exists
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            sendErrorResponse('Product not found', 404);
        }
        
        // Delete the product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        
        sendSuccessResponse(null, 'Product deleted successfully');
        
    } catch (PDOException $e) {
        error_log("Delete product error: " . $e->getMessage());
        sendErrorResponse('Failed to delete product', 500);
    }
}

/**
 * Get product categories
 * @param PDO $pdo Database connection
 * @return array List of unique categories
 */
function getProductCategories($pdo) {
    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $categories;
    } catch (PDOException $e) {
        error_log("Get categories error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get product by ID
 * @param PDO $pdo Database connection
 * @param int $productId Product ID
 * @return array|null Product data or null if not found
 */
function getProductById($pdo, $productId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Convert types for PostgreSQL
            $product['id'] = (int)$product['id'];
            $product['price'] = (float)$product['price'];
            $product['rating'] = (float)$product['rating'];
            $product['in_stock'] = $product['in_stock'] === 't' || $product['in_stock'] === true;
            $product['discount'] = (int)$product['discount'];
            $product['inStock'] = $product['in_stock'];
        }
        
        return $product;
    } catch (PDOException $e) {
        error_log("Get product by ID error: " . $e->getMessage());
        return null;
    }
}
?>

