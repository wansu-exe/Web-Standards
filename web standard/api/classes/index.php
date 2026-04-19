<?php
/**
 * Classes API
 * GET /api/classes/ - Get all classes for current user
 * POST /api/classes/ - Create a new class (faculty only)
 */

define('ELIXR_CMS', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/auth.php';

// Handle CORS
handleCors();

try {
    $pdo = getDbConnection();
    $user = requireAuth($pdo);
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetClasses($pdo, $user);
            break;
        case 'POST':
            handleCreateClass($pdo, $user);
            break;
        default:
            sendError(ERROR_METHOD_NOT_ALLOWED, HTTP_METHOD_NOT_ALLOWED);
    }
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

/**
 * Get all classes for current user
 */
function handleGetClasses(PDO $pdo, array $user): void {
    if ($user['role'] === 'faculty') {
        // Get classes created by this faculty
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id) as student_count
            FROM classes c
            WHERE c.faculty_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user['id']]);
    } else {
        // Get classes student is enrolled in
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as faculty_name,
                   e.enrolled_at
            FROM classes c
            JOIN enrollments e ON c.id = e.class_id
            JOIN users u ON c.faculty_id = u.id
            WHERE e.student_id = ?
            ORDER BY e.enrolled_at DESC
        ");
        $stmt->execute([$user['id']]);
    }
    
    $classes = $stmt->fetchAll();
    
    sendSuccess('Classes retrieved', ['classes' => $classes]);
}

/**
 * Create a new class (faculty only)
 */
function handleCreateClass(PDO $pdo, array $user): void {
    requireFaculty($user);
    
    $data = getJsonInput();
    
    // Validate required fields
    $required = ['subject_name', 'section', 'course_code'];
    $missing = validateRequired($data, $required);
    
    if (!empty($missing)) {
        sendError(ERROR_MISSING_FIELDS, HTTP_BAD_REQUEST, ['missing' => $missing]);
    }
    
    $subjectName = trim($data['subject_name']);
    $section = trim($data['section']);
    $courseCode = strtoupper(trim($data['course_code']));
    $description = trim($data['description'] ?? '');
    
    // Generate unique join code
    $joinCode = generateUniqueJoinCode($pdo);
    
    // Insert class
    $stmt = $pdo->prepare("
        INSERT INTO classes (faculty_id, subject_name, section, course_code, join_code, description)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user['id'], $subjectName, $section, $courseCode, $joinCode, $description]);
    
    $classId = (int) $pdo->lastInsertId();
    
    sendSuccess(SUCCESS_CLASS_CREATED, [
        'class' => [
            'id' => $classId,
            'subject_name' => $subjectName,
            'section' => $section,
            'course_code' => $courseCode,
            'join_code' => $joinCode,
            'description' => $description,
            'student_count' => 0
        ]
    ], HTTP_CREATED);
}

/**
 * Generate unique join code
 */
function generateUniqueJoinCode(PDO $pdo): string {
    $maxAttempts = 10;
    $attempts = 0;
    
    do {
        $code = generateJoinCode();
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE join_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetch();
        $attempts++;
    } while ($exists && $attempts < $maxAttempts);
    
    if ($attempts >= $maxAttempts) {
        throw new Exception("Failed to generate unique join code");
    }
    
    return $code;
}
