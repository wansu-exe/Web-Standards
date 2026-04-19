<?php
/**
 * Class Detail API
 * GET /api/classes/detail.php?id={classId}
 * 
 * Get detailed information about a specific class
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
    
    $classId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$classId) {
        sendError('Class ID is required', HTTP_BAD_REQUEST);
    }
    
    // Get class details
    $stmt = $pdo->prepare("
        SELECT c.*, 
               CONCAT(u.first_name, ' ', u.last_name) as faculty_name,
               u.email as faculty_email
        FROM classes c
        JOIN users u ON c.faculty_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$classId]);
    $class = $stmt->fetch();
    
    if (!$class) {
        sendError(ERROR_CLASS_NOT_FOUND, HTTP_NOT_FOUND);
    }
    
    // Verify access
    $hasAccess = false;
    
    if ($user['role'] === 'faculty' && (int) $class['faculty_id'] === (int) $user['id']) {
        $hasAccess = true;
    } else if ($user['role'] === 'student') {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND class_id = ?");
        $stmt->execute([$user['id'], $classId]);
        $hasAccess = (bool) $stmt->fetch();
    }
    
    if (!$hasAccess) {
        sendError(ERROR_FORBIDDEN, HTTP_FORBIDDEN);
    }
    
    // Get student count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE class_id = ?");
    $stmt->execute([$classId]);
    $class['student_count'] = (int) $stmt->fetch()['count'];
    
    // Remove sensitive info for students
    if ($user['role'] === 'student') {
        unset($class['join_code']);
    }
    
    sendSuccess('Class details retrieved', ['class' => $class]);
    
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
