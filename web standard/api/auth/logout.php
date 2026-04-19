<?php
/**
 * User Logout API
 * POST /api/auth/logout.php
 * 
 * Requires: Authorization header with Bearer token
 */

define('ELIXR_CMS', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/auth.php';

// Handle CORS
handleCors();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError(ERROR_METHOD_NOT_ALLOWED, HTTP_METHOD_NOT_ALLOWED);
}

try {
    $token = getBearerToken();
    
    if (!$token) {
        sendError(ERROR_UNAUTHORIZED, HTTP_UNAUTHORIZED);
    }
    
    $pdo = getDbConnection();
    
    // Delete session token
    deleteSessionToken($pdo, $token);
    
    sendSuccess(SUCCESS_LOGOUT);
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        sendError('Error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
    }
    sendError(ERROR_SERVER, HTTP_INTERNAL_ERROR);
}
