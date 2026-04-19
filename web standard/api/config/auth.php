<?php
/**
 * Authentication Helper Functions
 * Elixr University Classroom Management System
 */

// Prevent direct access
if (!defined('ELIXR_CMS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

/**
 * Generate a secure random token
 * @param int $length Token length
 * @return string
 */
function generateToken(int $length = 64): string {
    try {
        return bin2hex(random_bytes($length / 2));
    } catch (Exception $e) {
        // Fallback for older PHP versions
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    }
}

/**
 * Generate a unique join code for classes
 * @return string
 */
function generateJoinCode(): string {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed confusing chars (0, O, 1, I)
    $code = '';
    $length = defined('JOIN_CODE_LENGTH') ? JOIN_CODE_LENGTH : 8;
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    return $code;
}

/**
 * Hash password securely
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 * @param string $password Plain text password
 * @param string $hash Stored hash
 * @return bool
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Create session token for user
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return string Session token
 */
function createSessionToken(PDO $pdo, int $userId): string {
    try {
        // Clean up expired tokens
        $stmt = $pdo->prepare("DELETE FROM session_tokens WHERE user_id = ? OR expires_at < NOW()");
        $stmt->execute([$userId]);
        
        // Generate new token
        $token = generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
        
        $stmt = $pdo->prepare("INSERT INTO session_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $token, $expiresAt]);
        
        return $token;
    } catch (PDOException $e) {
        throw new Exception("Failed to create session token");
    }
}

/**
 * Validate session token and get user
 * @param PDO $pdo Database connection
 * @param string $token Session token
 * @return array|null User data or null if invalid
 */
function validateSessionToken(PDO $pdo, string $token): ?array {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role
            FROM session_tokens st
            JOIN users u ON st.user_id = u.id
            WHERE st.token = ? AND st.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Delete session token (logout)
 * @param PDO $pdo Database connection
 * @param string $token Session token
 * @return bool
 */
function deleteSessionToken(PDO $pdo, string $token): bool {
    try {
        $stmt = $pdo->prepare("DELETE FROM session_tokens WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get authorization token from request headers
 * @return string|null
 */
function getBearerToken(): ?string {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return $matches[1];
    }
    
    // Fallback to query parameter for debugging
    return $_GET['token'] ?? null;
}

/**
 * Require authentication - sends error response if not authenticated
 * @param PDO $pdo Database connection
 * @return array User data
 */
function requireAuth(PDO $pdo): array {
    $token = getBearerToken();
    
    if (!$token) {
        sendError(ERROR_UNAUTHORIZED, HTTP_UNAUTHORIZED);
    }
    
    $user = validateSessionToken($pdo, $token);
    
    if (!$user) {
        sendError(ERROR_UNAUTHORIZED, HTTP_UNAUTHORIZED);
    }
    
    return $user;
}

/**
 * Require faculty role
 * @param array $user User data from requireAuth
 */
function requireFaculty(array $user): void {
    if ($user['role'] !== 'faculty') {
        sendError(ERROR_FORBIDDEN, HTTP_FORBIDDEN);
    }
}

/**
 * Require student role
 * @param array $user User data from requireAuth
 */
function requireStudent(array $user): void {
    if ($user['role'] !== 'student') {
        sendError(ERROR_FORBIDDEN, HTTP_FORBIDDEN);
    }
}
