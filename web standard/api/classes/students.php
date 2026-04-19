<?php
/**
 * Class Students API
 * GET /api/classes/students.php?id={classId}
 * GET /api/classes/students.php (all students across all faculty classes)
 * 
 * Faculty only - Get list of students in a class or all classes
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
    requireFaculty($user);
    
    $classId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if ($classId) {
        // Get students for specific class
        // Verify faculty owns this class
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND faculty_id = ?");
        $stmt->execute([$classId, $user['id']]);
        
        if (!$stmt->fetch()) {
            sendError(ERROR_CLASS_NOT_FOUND, HTTP_NOT_FOUND);
        }
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, e.enrolled_at
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            WHERE e.class_id = ?
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$classId]);
    } else {
        // Get all students across all faculty's classes (Master Student List)
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.username, u.email, u.first_name, u.last_name,
                   GROUP_CONCAT(DISTINCT c.subject_name SEPARATOR ', ') as enrolled_subjects,
                   COUNT(DISTINCT e.class_id) as class_count,
                   MIN(e.enrolled_at) as first_enrolled
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            JOIN classes c ON e.class_id = c.id
            WHERE c.faculty_id = ?
            GROUP BY u.id, u.username, u.email, u.first_name, u.last_name
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$user['id']]);
    }
    
    $students = $stmt->fetchAll();
    
    sendSuccess('Students retrieved', ['students' => $students]);
    
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
