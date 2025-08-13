<?php
// Authentication helper functions (without routing logic)

/**
 * Check if current user is authenticated
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated() {
    return isset($_SESSION['user']);
}

/**
 * Check if current user is admin
 * @return bool True if admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['user']) && $_SESSION['user']['is_admin'];
}

/**
 * Get current user ID
 * @return int|null User ID or null if not authenticated
 */
function getCurrentUserId() {
    return isset($_SESSION['user']) ? $_SESSION['user']['id'] : null;
}

/**
 * Require authentication for API endpoint
 */
function requireAuth() {
    if (!isAuthenticated()) {
        sendErrorResponse('Authentication required', 401);
    }
}

/**
 * Require admin privileges for API endpoint
 */
function requireAdmin() {
    if (!isAuthenticated()) {
        sendErrorResponse('Authentication required', 401);
    }
    if (!isAdmin()) {
        sendErrorResponse('Admin privileges required', 403);
    }
}
?>

