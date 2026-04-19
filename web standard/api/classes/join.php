<?php
/**
 * Join Class API
 * POST /api/classes/join.php
 * 
 * Required fields: join_code
 * Students only
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
    $pdo = getDbConnection();
    $user = requireAuth($pdo);
    requireStudent($user);
    
    $data = getJsonInput();
    
    // Validate required fields
    if (empty($data['join_code'])) {
        sendError('Join code is required', HTTP_BAD_REQUEST);
    }
    
    $joinCode = strtoupper(trim($data['join_code']));
    
    // Find class by join code
    $stmt = $pdo->prepare("
        SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as faculty_name
        FROM classes c
        JOIN users u ON c.faculty_id = u.id
        WHERE c.join_code = ?
    ");
    $stmt->execute([$joinCode]);
    $class = $stmt->fetch();
    
    if (!$class) {
        sendError(ERROR_INVALID_JOIN_CODE, HTTP_NOT_FOUND);
    }
    
    // Check if already enrolled
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND class_id = ?");
    $stmt->execute([$user['id'], $class['id']]);
    
    if ($stmt->fetch()) {
        sendError(ERROR_ALREADY_ENROLLED, HTTP_CONFLICT);
    }
    
    // Enroll student
    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, class_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $class['id']]);
    
    sendSuccess(SUCCESS_ENROLLED, [
        'class' => [
            'id' => (int) $class['id'],
            'subject_name' => $class['subject_name'],
            'section' => $class['section'],
            'course_code' => $class['course_code'],
            'faculty_name' => $class['faculty_name']
        ]
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
