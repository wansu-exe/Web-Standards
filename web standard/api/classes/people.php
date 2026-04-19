<?php
/**
 * Class People API
 * GET /api/classes/people.php?id={classId}
 * 
 * Get classmates and instructor for a class (student view)
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
    
    // Get class and verify access
    $stmt = $pdo->prepare("
        SELECT c.*, u.id as faculty_user_id, u.first_name as faculty_first_name, 
               u.last_name as faculty_last_name, u.email as faculty_email
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
    } else {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND class_id = ?");
        $stmt->execute([$user['id'], $classId]);
        $hasAccess = (bool) $stmt->fetch();
    }
    
    if (!$hasAccess) {
        sendError(ERROR_FORBIDDEN, HTTP_FORBIDDEN);
    }
    
    // Get instructor
    $instructor = [
        'id' => (int) $class['faculty_user_id'],
        'first_name' => $class['faculty_first_name'],
        'last_name' => $class['faculty_last_name'],
        'email' => $class['faculty_email'],
        'role' => 'instructor'
    ];
    
    // Get classmates (all enrolled students)
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        WHERE e.class_id = ?
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$classId]);
    $classmates = $stmt->fetchAll();
    
    sendSuccess('People retrieved', [
        'instructor' => $instructor,
        'classmates' => $classmates,
        'total_students' => count($classmates)
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
