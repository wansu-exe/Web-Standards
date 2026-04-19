<?php
/**
 * Pending Activities API
 * GET /api/classwork/pending.php
 * 
 * Get pending activities for student (assignments not yet submitted, sorted by due date)
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
    requireStudent($user);
    
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 10;
    $limit = min($limit, 50); // Max 50
    
    // Get pending activities (classwork with due dates that haven't been submitted)
    $stmt = $pdo->prepare("
        SELECT cw.*, c.subject_name, c.course_code, c.section,
               DATEDIFF(cw.due_date, NOW()) as days_until_due,
               CASE 
                   WHEN cw.due_date < NOW() THEN 'overdue'
                   WHEN DATEDIFF(cw.due_date, NOW()) <= 1 THEN 'urgent'
                   WHEN DATEDIFF(cw.due_date, NOW()) <= 3 THEN 'soon'
                   ELSE 'upcoming'
               END as urgency
        FROM classwork cw
        JOIN classes c ON cw.class_id = c.id
        JOIN enrollments e ON c.id = e.class_id
        LEFT JOIN submissions s ON cw.id = s.classwork_id AND s.student_id = ?
        WHERE e.student_id = ?
          AND cw.type IN ('assignment', 'lab', 'quiz')
          AND cw.due_date IS NOT NULL
          AND (s.id IS NULL OR s.status = 'pending')
        ORDER BY 
            CASE 
                WHEN cw.due_date < NOW() THEN 0
                ELSE 1 
            END,
            cw.due_date ASC
        LIMIT ?
    ");
    $stmt->execute([$user['id'], $user['id'], $limit]);
    
    $pending = $stmt->fetchAll();
    
    // Count by urgency
    $counts = [
        'overdue' => 0,
        'urgent' => 0,
        'soon' => 0,
        'upcoming' => 0
    ];
    
    foreach ($pending as $item) {
        $counts[$item['urgency']]++;
    }
    
    sendSuccess('Pending activities retrieved', [
        'pending' => $pending,
        'counts' => $counts,
        'total' => count($pending)
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
