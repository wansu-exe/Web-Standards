<?php
/**
 * API Response Helper Functions
 * Elixr University Classroom Management System
 */

// Prevent direct access
if (!defined('ELIXR_CMS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// HTTP Status Code Constants
const HTTP_OK = 200;
const HTTP_CREATED = 201;
const HTTP_BAD_REQUEST = 400;
const HTTP_UNAUTHORIZED = 401;
const HTTP_FORBIDDEN = 403;
const HTTP_NOT_FOUND = 404;
const HTTP_METHOD_NOT_ALLOWED = 405;
const HTTP_CONFLICT = 409;
const HTTP_INTERNAL_ERROR = 500;

// Error Message Constants
const ERROR_INVALID_REQUEST = 'Invalid request';
const ERROR_MISSING_FIELDS = 'Missing required fields';
const ERROR_UNAUTHORIZED = 'Unauthorized access';
const ERROR_FORBIDDEN = 'Access forbidden';
const ERROR_NOT_FOUND = 'Resource not found';
const ERROR_METHOD_NOT_ALLOWED = 'Method not allowed';
const ERROR_USER_EXISTS = 'Username or email already exists';
const ERROR_INVALID_CREDENTIALS = 'Invalid username or password';
const ERROR_INVALID_FACULTY_CODE = 'Invalid faculty registration code';
const ERROR_CLASS_NOT_FOUND = 'Class not found';
const ERROR_ALREADY_ENROLLED = 'Already enrolled in this class';
const ERROR_INVALID_JOIN_CODE = 'Invalid join code';
const ERROR_SERVER = 'Internal server error';

// Success Message Constants
const SUCCESS_REGISTERED = 'Registration successful';
const SUCCESS_LOGIN = 'Login successful';
const SUCCESS_LOGOUT = 'Logout successful';
const SUCCESS_CLASS_CREATED = 'Class created successfully';
const SUCCESS_ENROLLED = 'Successfully enrolled in class';
const SUCCESS_ANNOUNCEMENT_CREATED = 'Announcement created successfully';
const SUCCESS_CLASSWORK_CREATED = 'Classwork created successfully';
const SUCCESS_SUBMISSION_SAVED = 'Submission saved successfully';

/**
 * Send JSON response
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 */
function sendResponse(array $data, int $statusCode = HTTP_OK): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send success response
 * @param string $message Success message
 * @param array $data Additional data
 * @param int $statusCode HTTP status code
 */
function sendSuccess(string $message, array $data = [], int $statusCode = HTTP_OK): void {
    $response = [
        'success' => true,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response['data'] = $data;
    }
    
    sendResponse($response, $statusCode);
}

/**
 * Send error response
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 * @param array $errors Additional error details
 */
function sendError(string $message, int $statusCode = HTTP_BAD_REQUEST, array $errors = []): void {
    $response = [
        'success' => false,
        'message' => $message
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    sendResponse($response, $statusCode);
}

/**
 * Handle CORS preflight request
 */
function handleCors(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
        exit;
    }
}

/**
 * Get JSON input from request body
 * @return array
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

/**
 * Validate required fields
 * @param array $data Input data
 * @param array $required Required field names
 * @return array Missing fields
 */
function validateRequired(array $data, array $required): array {
    $missing = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}
