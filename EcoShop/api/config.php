<?php
// PostgreSQL Database Configuration for EcoShop

// Security settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

// Session security settings - Fixed for proper cookie handling
ini_set('session.cookie_httponly', 1);
ini_set("session.cookie_secure", 1); // Set to 1 for HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax'); // Changed from 'Strict' to 'Lax' for better compatibility
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cookie_lifetime', 3600); // 1 hour cookie lifetime
ini_set('session.use_cookies', 1); // Ensure cookies are used
ini_set('session.use_only_cookies', 1); // Only use cookies for session ID

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_NAME', 'EcoShop_schema');
define('DB_USER', 'postgres');
define('DB_PASS', 'admin');
define('DB_PORT', '5432');

// PDO options for PostgreSQL with security enhancements
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_TIMEOUT => 30
];

/**
 * Get database connection with connection pooling
 * @return PDO|null Database connection or null on failure
 */
function getDatabase() {
    static $pdo = null;
    global $pdo_options;
    
    if ($pdo === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";options='--client_encoding=UTF8'";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

/**
 * Initialize database with schema and sample data
 */
function initializeDatabase() {
    try {
        $pdo = getDatabase();
        if (!$pdo) {
            throw new Exception("Could not connect to database");
        }
        
        $schemaFile = __DIR__ . '/../database/ecommerce_postgresql_schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: " . $schemaFile);
        }
        
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
        
        return true;
    } catch (Exception $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if database tables exist
 */
function databaseExists() {
    try {
        $pdo = getDatabase();
        if (!$pdo) {
            return false;
        }
        
        $stmt = $pdo->query("SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = 'users'
        )");
        $result = $stmt->fetch();
        return $result['exists'] === true || $result['exists'] === 't';
    } catch (PDOException $e) {
        error_log("Database check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Set CORS headers for API responses
 */
function setCorsHeaders() {
    // Allow specific origins in production
    $allowedOrigins = ['http://localhost:8000', 'http://127.0.0.1:8000', 'http://localhost', 'http://localhost:3000'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowedOrigins) || empty($origin)) {
        header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
    header("Access-Control-Allow-Credentials: true");
    header("Content-Type: application/json; charset=UTF-8");
    
    // Security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Send JSON success response
 */
function sendSuccessResponse($data = null, $message = 'Success', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Send JSON error response
 */
function sendErrorResponse($message = 'An error occurred', $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data' => null,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Validate required fields with enhanced validation
 */
function validateRequired($required, $data) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || 
            (is_string($data[$field]) && trim($data[$field]) === '') ||
            (is_array($data[$field]) && empty($data[$field]))) {
            return "Field '$field' is required and cannot be empty";
        }
    }
    return null;
}

/**
 * Enhanced input sanitization
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email format with additional checks
 */
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Additional checks
    if (strlen($email) > 254) {
        return false;
    }
    
    // Check for common disposable email domains (basic list)
    $disposableDomains = ['10minutemail.com', 'tempmail.org', 'guerrillamail.com'];
    $domain = substr(strrchr($email, "@"), 1);
    
    if (in_array(strtolower($domain), $disposableDomains)) {
        return false;
    }
    
    return true;
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return empty($errors) ? null : implode('. ', $errors);
}

/**
 * Rate limiting implementation
 */
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
    $cacheFile = __DIR__ . '/../cache/rate_limit_' . md5($identifier) . '.json';
    
    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }
    
    $now = time();
    $attempts = [];
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        $attempts = $data['attempts'] ?? [];
    }
    
    // Remove old attempts outside time window
    $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });
    
    if (count($attempts) >= $maxAttempts) {
        return false;
    }
    
    // Add current attempt
    $attempts[] = $now;
    file_put_contents($cacheFile, json_encode(['attempts' => $attempts]));
    
    return true;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = []) {
    $logFile = __DIR__ . '/../logs/security.log';
    
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'event' => $event,
        'details' => $details
    ];
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

// Auto-initialize database if it doesn't exist
if (!databaseExists()) {
    initializeDatabase();
}
?>

