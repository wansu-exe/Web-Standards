<?php
/**
 * User Registration API
 * POST /api/auth/register.php
 * 
 * Required fields: username, email, password, first_name, last_name
 * Optional fields: role (default: student), faculty_code (required if role=faculty)
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
    $required = ['username', 'email', 'password', 'first_name', 'last_name'];
    $missing = validateRequired($data, $required);
    
    if (!empty($missing)) {
        sendError(ERROR_MISSING_FIELDS, HTTP_BAD_REQUEST, ['missing' => $missing]);
    }
    
    // Sanitize inputs
    $username = trim($data['username']);
    $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
    $password = $data['password'];
    $firstName = trim($data['first_name']);
    $lastName = trim($data['last_name']);
    $role = isset($data['role']) && $data['role'] === 'faculty' ? 'faculty' : 'student';
    $facultyCode = $data['faculty_code'] ?? '';
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email format', HTTP_BAD_REQUEST);
    }
    
    // Validate password length
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        sendError('Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters', HTTP_BAD_REQUEST);
    }
    
    // Validate username length
    if (strlen($username) < 3 || strlen($username) > 50) {
        sendError('Username must be between 3 and 50 characters', HTTP_BAD_REQUEST);
    }
    
    // Validate faculty registration code
    if ($role === 'faculty') {
        if (empty($facultyCode) || $facultyCode !== FACULTY_REGISTRATION_CODE) {
            sendError(ERROR_INVALID_FACULTY_CODE, HTTP_BAD_REQUEST);
        }
    }
    
    $pdo = getDbConnection();
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        sendError(ERROR_USER_EXISTS, HTTP_CONFLICT);
    }
    
    // Hash password
    $passwordHash = hashPassword($password);
    
    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, first_name, last_name, role)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName, $role]);
    
    $userId = (int) $pdo->lastInsertId();
    
    // Create session token
    $token = createSessionToken($pdo, $userId);
    
    // Send success response
    sendSuccess(SUCCESS_REGISTERED, [
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role
        ],
        'token' => $token
    ], HTTP_CREATED);
    
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
