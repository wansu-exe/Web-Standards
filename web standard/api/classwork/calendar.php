<?php
/**
 * Calendar API
 * GET /api/classwork/calendar.php?month={YYYY-MM}
 * 
 * Get all assignments with due dates for calendar view
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
    
    // Get month parameter (default: current month)
    $month = filter_input(INPUT_GET, 'month', FILTER_SANITIZE_SPECIAL_CHARS) ?: date('Y-m');
    
    // Validate month format
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }
    
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    // Get all classwork with due dates in the specified month
    $stmt = $pdo->prepare("
        SELECT cw.id, cw.title, cw.type, cw.due_date, cw.max_points,
               c.id as class_id, c.subject_name, c.course_code,
               s.status as submission_status, s.grade
        FROM classwork cw
        JOIN classes c ON cw.class_id = c.id
        JOIN enrollments e ON c.id = e.class_id
        LEFT JOIN submissions s ON cw.id = s.classwork_id AND s.student_id = ?
        WHERE e.student_id = ?
          AND cw.due_date IS NOT NULL
          AND DATE(cw.due_date) BETWEEN ? AND ?
        ORDER BY cw.due_date ASC
    ");
    $stmt->execute([$user['id'], $user['id'], $startDate, $endDate]);
    
    $events = $stmt->fetchAll();
    
    // Group by date for calendar
    $calendarEvents = [];
    foreach ($events as $event) {
        $date = date('Y-m-d', strtotime($event['due_date']));
        if (!isset($calendarEvents[$date])) {
            $calendarEvents[$date] = [];
        }
        $calendarEvents[$date][] = $event;
    }
    
    sendSuccess('Calendar events retrieved', [
        'month' => $month,
        'events' => $events,
        'calendar' => $calendarEvents
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
