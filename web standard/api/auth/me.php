<?php
/**
 * Get Current User API
 * GET /api/auth/me.php
 * 
 * Requires: Authorization header with Bearer token
 */

define('ELIXR_CMS', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/auth.php';

// Handle CORS
handleCors();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError(ERROR_METHOD_NOT_ALLOWED, HTTP_METHOD_NOT_ALLOWED);
}

try {
    $pdo = getDbConnection();
    $user = requireAuth($pdo);
    
    sendSuccess('User retrieved', ['user' => $user]);
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        sendError('Error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
    }
    sendError(ERROR_SERVER, HTTP_INTERNAL_ERROR);
}
