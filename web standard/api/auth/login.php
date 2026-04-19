<?php
/**
 * User Login API
 * POST /api/auth/login.php
 * 
 * Required fields: username, password
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
    // Get input data
    $data = getJsonInput();
    
    // Validate required fields
    $required = ['username', 'password'];
    $missing = validateRequired($data, $required);
    
    if (!empty($missing)) {
        sendError(ERROR_MISSING_FIELDS, HTTP_BAD_REQUEST, ['missing' => $missing]);
    }
    
    $username = trim($data['username']);
    $password = $data['password'];
    
    $pdo = getDbConnection();
    
    // Find user by username or email
    $stmt = $pdo->prepare("
        SELECT id, username, email, password_hash, first_name, last_name, role
        FROM users
        WHERE username = ? OR email = ?
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    // Verify user and password
    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        sendError(ERROR_INVALID_CREDENTIALS, HTTP_UNAUTHORIZED);
    }
    
    // Create session token
    $token = createSessionToken($pdo, (int) $user['id']);
    
    // Send success response
    sendSuccess(SUCCESS_LOGIN, [
        'user' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role']
        ],
        'token' => $token
    ]);
    
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        sendError('Database error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
    }
    sendError(ERROR_SERVER, HTTP_INTERNAL_ERROR);
} catch (Exception $e) {
    if (DEBUG_MODE) {
        sendError('Error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
    }
    sendError(ERROR_SERVER, HTTP_INTERNAL_ERROR);
}
