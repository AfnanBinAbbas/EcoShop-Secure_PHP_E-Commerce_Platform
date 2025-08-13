<?php
/**
 * Session Restore API
 * Restores user session from cookies.txt or order_cookies.txt
 */

require_once 'config.php';
require_once 'cookie_manager.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDatabase();

if (!$pdo) {
    sendErrorResponse('Database connection failed', 500);
}

switch ($method) {
    case 'POST':
        handleRestoreSession($pdo);
        break;
    case 'GET':
        handleCheckSession();
        break;
    default:
        sendErrorResponse('Method not allowed', 405);
}

/**
 * Restore session from cookie files
 */
function handleRestoreSession($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $cookieType = $input['type'] ?? 'session'; // 'session' or 'order'
        
        $cookieManager = getCookieManager();
        
        // Get session ID from appropriate cookie file
        if ($cookieType === 'order') {
            $sessionId = $cookieManager->getOrderCookie();
        } else {
            $sessionId = $cookieManager->getSessionCookie();
        }
        
        if (!$sessionId) {
            sendErrorResponse('No valid session cookie found', 404);
        }
        
        // Start session with the retrieved session ID
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
        }
        
        session_id($sessionId);
        session_start();
        
        // Check if session has valid user data
        if (!isset($_SESSION['user'])) {
            sendErrorResponse('Session expired or invalid', 401);
        }
        
        // Validate session integrity
        $currentIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (isset($_SESSION['user']['ip']) && $_SESSION['user']['ip'] !== $currentIP) {
            logSecurityEvent('session_restore_ip_mismatch', [
                'session_ip' => $_SESSION['user']['ip'],
                'current_ip' => $currentIP,
                'cookie_type' => $cookieType
            ]);
            session_destroy();
            sendErrorResponse('Session invalid due to IP mismatch', 401);
        }
        
        // Check session timeout (1 hour)
        if (isset($_SESSION['user']['login_time']) && 
            (time() - $_SESSION['user']['login_time']) > 3600) {
            logSecurityEvent('session_restore_timeout', [
                'user_id' => $_SESSION['user']['id'],
                'cookie_type' => $cookieType
            ]);
            session_destroy();
            sendErrorResponse('Session expired', 401);
        }
        
        // Verify user still exists in database
        $stmt = $pdo->prepare("SELECT id, email, name, is_admin, is_active FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['is_active']) {
            logSecurityEvent('session_restore_user_invalid', [
                'user_id' => $_SESSION['user']['id'],
                'cookie_type' => $cookieType
            ]);
            session_destroy();
            sendErrorResponse('User account not found or inactive', 401);
        }
        
        // Update session data with current user info
        $_SESSION['user']['email'] = $user['email'];
        $_SESSION['user']['name'] = $user['name'];
        $_SESSION['user']['is_admin'] = (bool)$user['is_admin'];
        
        // Generate new CSRF token
        generateCSRFToken();
        
        // Update cookie files with current session
        if ($cookieType === 'order') {
            $cookieManager->saveOrderCookie(session_id());
        } else {
            $cookieManager->saveSessionCookie(session_id());
        }
        
        $userData = $_SESSION['user'];
        unset($userData['ip']); // Don't send IP to client
        $userData['csrf_token'] = $_SESSION['csrf_token'];
        
        logSecurityEvent('session_restored_successfully', [
            'user_id' => $_SESSION['user']['id'],
            'cookie_type' => $cookieType
        ]);
        
        sendSuccessResponse($userData, 'Session restored successfully');
        
    } catch (Exception $e) {
        error_log("Session restore error: " . $e->getMessage());
        sendErrorResponse('Failed to restore session', 500);
    }
}

/**
 * Check current session status
 */
function handleCheckSession() {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user'])) {
            sendErrorResponse('No active session', 401);
        }
        
        // Validate session integrity
        $currentIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (isset($_SESSION['user']['ip']) && $_SESSION['user']['ip'] !== $currentIP) {
            session_destroy();
            sendErrorResponse('Session invalid', 401);
        }
        
        // Check session timeout
        if (isset($_SESSION['user']['login_time']) && 
            (time() - $_SESSION['user']['login_time']) > 3600) {
            session_destroy();
            sendErrorResponse('Session expired', 401);
        }
        
        $userData = $_SESSION['user'];
        unset($userData['ip']);
        $userData['csrf_token'] = generateCSRFToken();
        
        sendSuccessResponse($userData, 'Session is active');
        
    } catch (Exception $e) {
        error_log("Check session error: " . $e->getMessage());
        sendErrorResponse('Failed to check session', 500);
    }
}

/**
 * Clean expired sessions and cookies
 */
function cleanExpiredSessions() {
    try {
        $cookieManager = getCookieManager();
        $cookieManager->cleanExpiredCookies();
        
        // Clean up session files (if using file-based sessions)
        if (ini_get('session.save_handler') === 'files') {
            $sessionPath = session_save_path();
            if ($sessionPath && is_dir($sessionPath)) {
                $files = glob($sessionPath . '/sess_*');
                $maxLifetime = ini_get('session.gc_maxlifetime');
                
                foreach ($files as $file) {
                    if (filemtime($file) + $maxLifetime < time()) {
                        unlink($file);
                    }
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Clean expired sessions error: " . $e->getMessage());
        return false;
    }
}

// Auto-clean expired sessions and cookies
cleanExpiredSessions();
?>

