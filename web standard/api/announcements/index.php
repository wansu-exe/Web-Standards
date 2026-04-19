<?php
/**
 * Announcements API
 * GET /api/announcements/?class_id={id} - Get announcements for a class
 * GET /api/announcements/ - Get all announcements for enrolled classes (student)
 * POST /api/announcements/ - Create announcement (faculty only)
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
            handleGetAnnouncements($pdo, $user);
            break;
        case 'POST':
            handleCreateAnnouncement($pdo, $user);
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
 * Get announcements
 */
function handleGetAnnouncements(PDO $pdo, array $user): void {
    $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    
    if ($classId) {
        // Verify access to class
        if (!hasClassAccess($pdo, $user, $classId)) {
            sendError(ERROR_FORBIDDEN, HTTP_FORBIDDEN);
        }
        
        $stmt = $pdo->prepare("
            SELECT a.*, c.subject_name, c.course_code,
                   CONCAT(u.first_name, ' ', u.last_name) as author_name
            FROM announcements a
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON a.faculty_id = u.id
            WHERE a.class_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$classId]);
    } else {
        // Get all announcements for user's classes
        if ($user['role'] === 'faculty') {
            $stmt = $pdo->prepare("
                SELECT a.*, c.subject_name, c.course_code,
                       CONCAT(u.first_name, ' ', u.last_name) as author_name
                FROM announcements a
                JOIN classes c ON a.class_id = c.id
                JOIN users u ON a.faculty_id = u.id
                WHERE c.faculty_id = ?
                ORDER BY a.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$user['id']]);
        } else {
            $stmt = $pdo->prepare("
                SELECT a.*, c.subject_name, c.course_code,
                       CONCAT(u.first_name, ' ', u.last_name) as author_name
                FROM announcements a
                JOIN classes c ON a.class_id = c.id
                JOIN users u ON a.faculty_id = u.id
                JOIN enrollments e ON c.id = e.class_id
                WHERE e.student_id = ?
                ORDER BY a.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$user['id']]);
        }
    }
    
    $announcements = $stmt->fetchAll();
    
    sendSuccess('Announcements retrieved', ['announcements' => $announcements]);
}

/**
 * Create announcement (faculty only)
 */
function handleCreateAnnouncement(PDO $pdo, array $user): void {
    requireFaculty($user);
    
    $data = getJsonInput();
    
    // Validate required fields
    $required = ['class_id', 'title', 'content'];
    $missing = validateRequired($data, $required);
    
    if (!empty($missing)) {
        sendError(ERROR_MISSING_FIELDS, HTTP_BAD_REQUEST, ['missing' => $missing]);
    }
    
    $classId = (int) $data['class_id'];
    $title = trim($data['title']);
    $content = trim($data['content']);
    
    // Verify faculty owns this class
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND faculty_id = ?");
    $stmt->execute([$classId, $user['id']]);
    
    if (!$stmt->fetch()) {
        sendError(ERROR_CLASS_NOT_FOUND, HTTP_NOT_FOUND);
    }
    
    // Insert announcement
    $stmt = $pdo->prepare("
        INSERT INTO announcements (class_id, faculty_id, title, content)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$classId, $user['id'], $title, $content]);
    
    $announcementId = (int) $pdo->lastInsertId();
    
    sendSuccess(SUCCESS_ANNOUNCEMENT_CREATED, [
        'announcement' => [
            'id' => $announcementId,
            'class_id' => $classId,
            'title' => $title,
            'content' => $content,
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
