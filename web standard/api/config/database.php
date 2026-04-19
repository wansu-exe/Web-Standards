<?php
/**
 * Database Configuration
 * Elixr University Classroom Management System
 * 
 * IMPORTANT: Copy this file to config.local.php and update with your credentials
 * The config.local.php file should NOT be committed to version control
 */

// Prevent direct access
if (!defined('ELIXR_CMS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Database credentials from environment variables (recommended for production)
// For XAMPP development, you can use the defaults below
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'elixr_university');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Application constants
define('APP_NAME', 'Elixr University');
define('SESSION_DURATION', 86400); // 24 hours in seconds
define('JOIN_CODE_LENGTH', 8);
define('MIN_PASSWORD_LENGTH', 6);

// Faculty registration code (change this for security)
define('FACULTY_REGISTRATION_CODE', getenv('FACULTY_CODE') ?: 'ELIXR-FACULTY-2026');

// Error reporting (set to 0 in production)
define('DEBUG_MODE', getenv('DEBUG_MODE') ?: true);

/**
 * Get database connection using PDO
 * @return PDO
 * @throws PDOException
 */
function getDbConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                throw new PDOException("Database connection failed: " . $e->getMessage());
            }
            throw new PDOException("Database connection failed");
        }
    }
    
    return $pdo;
}
