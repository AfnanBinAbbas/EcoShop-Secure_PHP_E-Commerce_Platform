<?php
require_once 'config.php';
require_once 'cookie_manager.php';

// Start secure session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters before starting session
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true for HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDatabase();

if (!$pdo) {
    logSecurityEvent('database_connection_failed');
    sendErrorResponse('Service temporarily unavailable', 503);
}

// Get client IP for rate limiting
$clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            logSecurityEvent('invalid_json_input', ['ip' => $clientIP]);
            sendErrorResponse('Invalid request format');
        }
        
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'login':
                    handleLogin($pdo, $input, $clientIP);
                    break;
                case 'register':
                    handleRegister($pdo, $input, $clientIP);
                    break;
                default:
                    logSecurityEvent('invalid_action', ['action' => $input['action'], 'ip' => $clientIP]);
                    sendErrorResponse('Invalid action');
            }
        } else {
            // Default to login for backward compatibility
            handleLogin($pdo, $input, $clientIP);
        }
        break;
        
    case 'GET':
        handleGetCurrentUser();
        break;
        
    case 'DELETE':
        handleLogout($clientIP);
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

/**
 * Handle user login with security measures
 */
function handleLogin($pdo, $input, $clientIP) {
    try {
        // Rate limiting
        if (!checkRateLimit('login_' . $clientIP, 5, 900)) { // 5 attempts per 15 minutes
            logSecurityEvent('login_rate_limit_exceeded', ['ip' => $clientIP]);
            sendErrorResponse('Too many login attempts. Please try again later.', 429);
        }
        
        // Validate required fields
        $requiredFields = ['email', 'password'];
        $error = validateRequired($requiredFields, $input);
        if ($error) {
            logSecurityEvent('login_missing_fields', ['ip' => $clientIP, 'error' => $error]);
            sendErrorResponse($error);
        }
        
        $email = sanitizeInput($input['email']);
        $password = $input['password'];
        
        // Validate email format
        if (!validateEmail($email)) {
            logSecurityEvent('login_invalid_email', ['email' => $email, 'ip' => $clientIP]);
            sendErrorResponse('Invalid email format');
        }
        
        // Additional input validation
        if (strlen($password) > 128) {
            logSecurityEvent('login_password_too_long', ['email' => $email, 'ip' => $clientIP]);
            sendErrorResponse('Invalid credentials', 401);
        }
        
        // Get user from database with account status check
        $stmt = $pdo->prepare("
            SELECT id, email, name, password_hash, is_admin, created_at, 
                   failed_login_attempts, locked_until, last_login
            FROM users 
            WHERE email = ? AND is_active = true
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            logSecurityEvent('login_user_not_found', ['email' => $email, 'ip' => $clientIP]);
            // Use same error message to prevent user enumeration
            sendErrorResponse('Invalid email or password', 401);
        }
        
        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            logSecurityEvent('login_account_locked', ['email' => $email, 'ip' => $clientIP]);
            sendErrorResponse('Account temporarily locked due to multiple failed attempts', 423);
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            // Increment failed login attempts
            $failedAttempts = ($user['failed_login_attempts'] ?? 0) + 1;
            $lockUntil = null;
            
            // Lock account after 5 failed attempts for 30 minutes
            if ($failedAttempts >= 5) {
                $lockUntil = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
            }
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET failed_login_attempts = ?, locked_until = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$failedAttempts, $lockUntil, $user['id']]);
            
            logSecurityEvent('login_failed_password', [
                'email' => $email, 
                'ip' => $clientIP, 
                'attempts' => $failedAttempts
            ]);
            
            sendErrorResponse('Invalid email or password', 401);
        }
        
        // Reset failed login attempts on successful login
        $stmt = $pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, locked_until = NULL, 
                last_login = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Create secure session
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'is_admin' => (bool)$user['is_admin'],
            'login_time' => time(),
            'ip' => $clientIP
        ];
        
        // Generate CSRF token
        generateCSRFToken();
        
        // Save session cookie to cookies.txt
        saveSessionCookie(session_id());
        
        // Return user data (without sensitive information)
        $userData = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'is_admin' => (bool)$user['is_admin'],
            'created_at' => $user['created_at'],
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        logSecurityEvent('login_successful', ['email' => $email, 'ip' => $clientIP]);
        sendSuccessResponse($userData, 'Login successful');
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        logSecurityEvent('login_database_error', ['ip' => $clientIP]);
        sendErrorResponse('Login failed. Please try again.', 500);
    }
}

/**
 * Handle user registration with security measures
 */
function handleRegister($pdo, $input, $clientIP) {
    try {
        // Rate limiting for registration
        if (!checkRateLimit('register_' . $clientIP, 3, 3600)) { // 3 attempts per hour
            logSecurityEvent('register_rate_limit_exceeded', ['ip' => $clientIP]);
            sendErrorResponse('Too many registration attempts. Please try again later.', 429);
        }
        
        // Validate required fields
        $requiredFields = ['email', 'name', 'password'];
        $error = validateRequired($requiredFields, $input);
        if ($error) {
            logSecurityEvent('register_missing_fields', ['ip' => $clientIP, 'error' => $error]);
            sendErrorResponse($error);
        }
        
        $email = sanitizeInput($input['email']);
        $name = sanitizeInput($input['name']);
        $password = $input['password'];
        
        // Validate email
        if (!validateEmail($email)) {
            logSecurityEvent('register_invalid_email', ['email' => $email, 'ip' => $clientIP]);
            sendErrorResponse('Invalid email format');
        }
        
        // Validate name
        if (strlen($name) < 2 || strlen($name) > 100) {
            logSecurityEvent('register_invalid_name', ['email' => $email, 'ip' => $clientIP]);
            sendErrorResponse('Name must be between 2 and 100 characters');
        }
        
        // Enhanced name validation
        if (!preg_match('/^[a-zA-Z\s\'-]+$/', $name)) {
            logSecurityEvent('register_invalid_name_format', ['email' => $email, 'ip' => $clientIP]);
            sendErrorResponse('Name contains invalid characters');
        }
        
        // Validate password strength
        $passwordError = validatePassword($password);
        if ($passwordError) {
            logSecurityEvent('register_weak_password', ['email' => $email, 'ip' => $clientIP]);
            sendErrorResponse($passwordError);
        }
        
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            logSecurityEvent('register_email_exists', ['email' => $email, 'ip' => $clientIP]);
            sendErrorResponse('Email already registered', 409);
        }
        
        // Hash password with strong settings
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 4096, // 4 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Insert new user
            $stmt = $pdo->prepare("
                INSERT INTO users (email, name, password_hash, is_admin, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, FALSE, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) 
                RETURNING id, created_at
            ");
            $stmt->execute([$email, $name, $passwordHash]);
            $result = $stmt->fetch();
            $userId = $result['id'];
            $createdAt = $result['created_at'];
            
            // Commit transaction
            $pdo->commit();
            
            // Regenerate session ID
            session_regenerate_id(true);
            
            // Create session for new user
            $_SESSION['user'] = [
                'id' => (int)$userId,
                'email' => $email,
                'name' => $name,
                'is_admin' => false,
                'login_time' => time(),
                'ip' => $clientIP
            ];
            
            // Generate CSRF token
            generateCSRFToken();
            
            // Save session cookie to cookies.txt
            saveSessionCookie(session_id());
            
            // Return user data
            $userData = [
                'id' => (int)$userId,
                'email' => $email,
                'name' => $name,
                'is_admin' => false,
                'created_at' => $createdAt,
                'csrf_token' => $_SESSION['csrf_token']
            ];
            
            logSecurityEvent('register_successful', ['email' => $email, 'ip' => $clientIP]);
            sendSuccessResponse($userData, 'Registration successful', 201);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        logSecurityEvent('register_database_error', ['ip' => $clientIP]);
        sendErrorResponse('Registration failed. Please try again.', 500);
    }
}

/**
 * Get current user information
 */
function handleGetCurrentUser() {
    try {
        if (!isset($_SESSION['user'])) {
            sendErrorResponse('Not authenticated', 401);
        }
        
        // Validate session integrity
        $currentIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (isset($_SESSION['user']['ip']) && $_SESSION['user']['ip'] !== $currentIP) {
            logSecurityEvent('session_ip_mismatch', [
                'session_ip' => $_SESSION['user']['ip'],
                'current_ip' => $currentIP
            ]);
            session_destroy();
            sendErrorResponse('Session invalid', 401);
        }
        
        // Check session timeout (1 hour)
        if (isset($_SESSION['user']['login_time']) && 
            (time() - $_SESSION['user']['login_time']) > 3600) {
            logSecurityEvent('session_timeout', ['user_id' => $_SESSION['user']['id']]);
            session_destroy();
            sendErrorResponse('Session expired', 401);
        }
        
        $userData = $_SESSION['user'];
        unset($userData['ip']); // Don't send IP to client
        $userData['csrf_token'] = generateCSRFToken();
        
        sendSuccessResponse($userData, 'User data retrieved');
        
    } catch (Exception $e) {
        error_log("Get current user error: " . $e->getMessage());
        sendErrorResponse('Failed to get user data', 500);
    }
}

/**
 * Handle user logout
 */
function handleLogout($clientIP) {
    try {
        if (isset($_SESSION['user'])) {
            logSecurityEvent('logout_successful', [
                'user_id' => $_SESSION['user']['id'],
                'ip' => $clientIP
            ]);
        }
        
        // Destroy session completely
        $_SESSION = [];
        
        // Clear session cookie from cookies.txt
        clearSessionCookie();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        sendSuccessResponse(null, 'Logout successful');
        
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
        sendErrorResponse('Logout failed', 500);
    }
}

/**
 * Authentication helper functions
 */
function isAuthenticated() {
    return isset($_SESSION['user']) && 
           isset($_SESSION['user']['login_time']) &&
           (time() - $_SESSION['user']['login_time']) < 3600;
}

function isAdmin() {
    return isAuthenticated() && $_SESSION['user']['is_admin'];
}

function getCurrentUserId() {
    return isAuthenticated() ? $_SESSION['user']['id'] : null;
}

function requireAuth() {
    if (!isAuthenticated()) {
        sendErrorResponse('Authentication required', 401);
    }
}

function requireAdmin() {
    if (!isAuthenticated()) {
        sendErrorResponse('Authentication required', 401);
    }
    if (!isAdmin()) {
        sendErrorResponse('Admin privileges required', 403);
    }
}
?>

