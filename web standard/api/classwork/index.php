<?php
/**
 * Classwork API
 * GET /api/classwork/?class_id={id} - Get classwork for a class
 * GET /api/classwork/?type={type} - Filter by type (lesson, lab, quiz, assignment)
 * POST /api/classwork/ - Create classwork (faculty only)
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
            handleGetClasswork($pdo, $user);
            break;
        case 'POST':
            handleCreateClasswork($pdo, $user);
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
 * Get classwork
 */
function handleGetClasswork(PDO $pdo, array $user): void {
    $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    $type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
    
    $validTypes = ['lesson', 'lab', 'quiz', 'assignment'];
    
    if ($classId) {
        // Verify access to class
        if (!hasClassAccess($pdo, $user, $classId)) {
            sendError(ERROR_FORBIDDEN, HTTP_FORBIDDEN);
        }
        
        $sql = "
            SELECT cw.*, c.subject_name, c.course_code
            FROM classwork cw
            JOIN classes c ON cw.class_id = c.id
            WHERE cw.class_id = ?
        ";
        $params = [$classId];
        
        if ($type && in_array($type, $validTypes)) {
            $sql .= " AND cw.type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY cw.due_date ASC, cw.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // Get all classwork for user's classes
        if ($user['role'] === 'faculty') {
            $sql = "
                SELECT cw.*, c.subject_name, c.course_code
                FROM classwork cw
                JOIN classes c ON cw.class_id = c.id
                WHERE c.faculty_id = ?
            ";
            $params = [$user['id']];
        } else {
            $sql = "
                SELECT cw.*, c.subject_name, c.course_code,
                       s.status as submission_status, s.grade, s.submitted_at
                FROM classwork cw
                JOIN classes c ON cw.class_id = c.id
                JOIN enrollments e ON c.id = e.class_id
                LEFT JOIN submissions s ON cw.id = s.classwork_id AND s.student_id = ?
                WHERE e.student_id = ?
            ";
            $params = [$user['id'], $user['id']];
        }
        
        if ($type && in_array($type, $validTypes)) {
            $sql .= " AND cw.type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY cw.due_date ASC, cw.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    $classwork = $stmt->fetchAll();
    
    // Group by type for easier frontend handling
    $grouped = [
        'lesson' => [],
        'lab' => [],
        'quiz' => [],
        'assignment' => []
    ];
    
    foreach ($classwork as $item) {
        $grouped[$item['type']][] = $item;
    }
    
    sendSuccess('Classwork retrieved', [
        'classwork' => $classwork,
        'grouped' => $grouped
    ]);
}

/**
 * Create classwork (faculty only)
 */
function handleCreateClasswork(PDO $pdo, array $user): void {
    requireFaculty($user);
    
    $data = getJsonInput();
    
    // Validate required fields
    $required = ['class_id', 'title', 'type'];
    $missing = validateRequired($data, $required);
    
    if (!empty($missing)) {
        sendError(ERROR_MISSING_FIELDS, HTTP_BAD_REQUEST, ['missing' => $missing]);
    }
    
    $classId = (int) $data['class_id'];
    $title = trim($data['title']);
    $description = trim($data['description'] ?? '');
    $type = strtolower(trim($data['type']));
    $dueDate = $data['due_date'] ?? null;
    $maxPoints = (int) ($data['max_points'] ?? 100);
    $attachmentUrl = trim($data['attachment_url'] ?? '');
    
    // Validate type
    $validTypes = ['lesson', 'lab', 'quiz', 'assignment'];
    if (!in_array($type, $validTypes)) {
        sendError('Invalid classwork type', HTTP_BAD_REQUEST);
    }
    
    // Verify faculty owns this class
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND faculty_id = ?");
    $stmt->execute([$classId, $user['id']]);
    
    if (!$stmt->fetch()) {
        sendError(ERROR_CLASS_NOT_FOUND, HTTP_NOT_FOUND);
    }
    
    // Insert classwork
    $stmt = $pdo->prepare("
        INSERT INTO classwork (class_id, faculty_id, title, description, type, due_date, max_points, attachment_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $classId, 
        $user['id'], 
        $title, 
        $description, 
        $type, 
        $dueDate, 
        $maxPoints, 
        $attachmentUrl
    ]);
    
    $classworkId = (int) $pdo->lastInsertId();
    
    sendSuccess(SUCCESS_CLASSWORK_CREATED, [
        'classwork' => [
            'id' => $classworkId,
            'class_id' => $classId,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'due_date' => $dueDate,
            'max_points' => $maxPoints,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], HTTP_CREATED);
}

/**
 * Check if user has access to class
 */
function hasClassAccess(PDO $pdo, array $user, int $classId): bool {
    if ($user['role'] === 'faculty') {
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND faculty_id = ?");
        $stmt->execute([$classId, $user['id']]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE class_id = ? AND student_id = ?");
        $stmt->execute([$classId, $user['id']]);
    }
    
    return (bool) $stmt->fetch();
}
