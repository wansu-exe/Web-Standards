<?php
/**
 * Submissions API
 * GET /api/submissions/?classwork_id={id} - Get submissions for classwork (faculty)
 * POST /api/submissions/ - Submit work (student)
 * PUT /api/submissions/ - Grade submission (faculty)
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
            handleGetSubmissions($pdo, $user);
            break;
        case 'POST':
            handleCreateSubmission($pdo, $user);
            break;
        case 'PUT':
            handleGradeSubmission($pdo, $user);
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
 * Get submissions (faculty only)
 */
function handleGetSubmissions(PDO $pdo, array $user): void {
    requireFaculty($user);
    
    $classworkId = filter_input(INPUT_GET, 'classwork_id', FILTER_VALIDATE_INT);
    
    if (!$classworkId) {
        sendError('Classwork ID is required', HTTP_BAD_REQUEST);
    }
    
    // Verify faculty owns the classwork
    $stmt = $pdo->prepare("
        SELECT cw.* FROM classwork cw
        JOIN classes c ON cw.class_id = c.id
        WHERE cw.id = ? AND c.faculty_id = ?
    ");
    $stmt->execute([$classworkId, $user['id']]);
    
    if (!$stmt->fetch()) {
        sendError('Classwork not found', HTTP_NOT_FOUND);
    }
    
    // Get all submissions
    $stmt = $pdo->prepare("
        SELECT s.*, u.first_name, u.last_name, u.email
        FROM submissions s
        JOIN users u ON s.student_id = u.id
        WHERE s.classwork_id = ?
        ORDER BY s.submitted_at DESC
    ");
    $stmt->execute([$classworkId]);
    
    $submissions = $stmt->fetchAll();
    
    sendSuccess('Submissions retrieved', ['submissions' => $submissions]);
}

/**
 * Create/update submission (student)
 */
function handleCreateSubmission(PDO $pdo, array $user): void {
    requireStudent($user);
    
    $data = getJsonInput();
    
    if (empty($data['classwork_id'])) {
        sendError('Classwork ID is required', HTTP_BAD_REQUEST);
    }
    
    $classworkId = (int) $data['classwork_id'];
    $content = trim($data['content'] ?? '');
    $attachmentUrl = trim($data['attachment_url'] ?? '');
    
    // Verify student has access to this classwork
    $stmt = $pdo->prepare("
        SELECT cw.*, c.id as class_id
        FROM classwork cw
        JOIN classes c ON cw.class_id = c.id
        JOIN enrollments e ON c.id = e.class_id
        WHERE cw.id = ? AND e.student_id = ?
    ");
    $stmt->execute([$classworkId, $user['id']]);
    $classwork = $stmt->fetch();
    
    if (!$classwork) {
        sendError('Classwork not found or not enrolled', HTTP_NOT_FOUND);
    }
    
    // Check if submission exists
    $stmt = $pdo->prepare("SELECT id, status FROM submissions WHERE classwork_id = ? AND student_id = ?");
    $stmt->execute([$classworkId, $user['id']]);
    $existing = $stmt->fetch();
    
    // Determine status
    $status = 'submitted';
    if ($classwork['due_date'] && strtotime($classwork['due_date']) < time()) {
        $status = 'late';
    }
    
    if ($existing) {
        // Update existing submission
        $stmt = $pdo->prepare("
            UPDATE submissions 
            SET content = ?, attachment_url = ?, status = ?, submitted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$content, $attachmentUrl, $status, $existing['id']]);
        $submissionId = (int) $existing['id'];
    } else {
        // Create new submission
        $stmt = $pdo->prepare("
            INSERT INTO submissions (classwork_id, student_id, content, attachment_url, status, submitted_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$classworkId, $user['id'], $content, $attachmentUrl, $status]);
        $submissionId = (int) $pdo->lastInsertId();
    }
    
    sendSuccess(SUCCESS_SUBMISSION_SAVED, [
        'submission' => [
            'id' => $submissionId,
            'classwork_id' => $classworkId,
            'status' => $status,
            'submitted_at' => date('Y-m-d H:i:s')
        ]
    ], HTTP_CREATED);
}

/**
 * Grade submission (faculty)
 */
function handleGradeSubmission(PDO $pdo, array $user): void {
    requireFaculty($user);
    
    $data = getJsonInput();
    
    if (empty($data['submission_id'])) {
        sendError('Submission ID is required', HTTP_BAD_REQUEST);
    }
    
    $submissionId = (int) $data['submission_id'];
    $grade = isset($data['grade']) ? (int) $data['grade'] : null;
    $feedback = trim($data['feedback'] ?? '');
    
    // Verify faculty owns the classwork
    $stmt = $pdo->prepare("
        SELECT s.* FROM submissions s
        JOIN classwork cw ON s.classwork_id = cw.id
        JOIN classes c ON cw.class_id = c.id
        WHERE s.id = ? AND c.faculty_id = ?
    ");
    $stmt->execute([$submissionId, $user['id']]);
    
    if (!$stmt->fetch()) {
        sendError('Submission not found', HTTP_NOT_FOUND);
    }
    
    // Update grade
    $stmt = $pdo->prepare("
        UPDATE submissions 
        SET grade = ?, feedback = ?, status = 'graded', graded_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$grade, $feedback, $submissionId]);
    
    sendSuccess('Submission graded', [
        'submission' => [
            'id' => $submissionId,
            'grade' => $grade,
            'feedback' => $feedback,
            'status' => 'graded',
            'graded_at' => date('Y-m-d H:i:s')
        ]
    ]);
}
