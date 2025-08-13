<?php
require_once 'config.php';
require_once 'auth_functions.php';

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
        handleGetUsers($pdo);
        break;
    case 'PUT':
        handleUpdateUser($pdo);
        break;
    default:
        sendErrorResponse('Method not allowed', 405);
}

function handleGetUsers($pdo) {
    try {
        // Check if user is admin
        requireAdmin();
        
        // Get all users with their details
        $sql = "SELECT id, name, email, is_admin, is_active, created_at, last_login, 
                       failed_login_attempts, locked_until
                FROM users 
                ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        // Convert types and format data
        foreach ($users as &$user) {
            $user['id'] = (int)$user['id'];
            $user['is_admin'] = (bool)$user['is_admin'];
            $user['is_active'] = (bool)$user['is_active'];
            $user['failed_login_attempts'] = (int)$user['failed_login_attempts'];
            
            // Format dates
            if ($user['created_at']) {
                $user['created_at'] = date('Y-m-d', strtotime($user['created_at']));
            }
            if ($user['last_login']) {
                $user['last_login'] = date('Y-m-d H:i', strtotime($user['last_login']));
            }
            if ($user['locked_until']) {
                $user['locked_until'] = date('Y-m-d H:i', strtotime($user['locked_until']));
            }
        }
        
        sendSuccessResponse($users);
        
    } catch (PDOException $e) {
        error_log("Get users error: " . $e->getMessage());
        sendErrorResponse('Failed to retrieve users', 500);
    }
}

function handleUpdateUser($pdo) {
    try {
        // Check if user is admin
        requireAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            sendErrorResponse('User ID is required');
        }
        
        $userId = (int)$input['id'];
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendErrorResponse('User not found', 404);
        }
        
        // Update user fields if provided
        $updates = [];
        $params = [];
        
        if (isset($input['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = (bool)$input['is_active'];
        }
        
        if (isset($input['is_admin'])) {
            $updates[] = "is_admin = ?";
            $params[] = (bool)$input['is_admin'];
        }
        
        if (isset($input['name']) && !empty($input['name'])) {
            $updates[] = "name = ?";
            $params[] = sanitizeInput($input['name']);
        }
        
        if (empty($updates)) {
            sendErrorResponse('No valid fields to update');
        }
        
        // Add user ID to params
        $params[] = $userId;
        
        // Update user
        $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Get updated user
        $stmt = $pdo->prepare("SELECT id, name, email, is_admin, is_active, created_at, last_login 
                               FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch();
        
        // Convert types
        $updatedUser['id'] = (int)$updatedUser['id'];
        $updatedUser['is_admin'] = (bool)$updatedUser['is_admin'];
        $updatedUser['is_active'] = (bool)$updatedUser['is_active'];
        
        // Format dates
        if ($updatedUser['created_at']) {
            $updatedUser['created_at'] = date('Y-m-d', strtotime($updatedUser['created_at']));
        }
        if ($updatedUser['last_login']) {
            $updatedUser['last_login'] = date('Y-m-d H:i', strtotime($updatedUser['last_login']));
        }
        
        sendSuccessResponse($updatedUser, 'User updated successfully');
        
    } catch (PDOException $e) {
        error_log("Update user error: " . $e->getMessage());
        sendErrorResponse('Failed to update user', 500);
    }
}
?>
