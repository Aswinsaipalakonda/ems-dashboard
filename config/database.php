<?php
/**
 * Database Configuration
 * Employee Management System
 */

// Load .env file if present (Hostinger production)
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if (!getenv($k) && !isset($_SERVER[$k])) {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}

// Environment-specific database configuration
// For production, these are loaded from .env or environment variables
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost'));
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? ''));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'ems_dashboard'));
}

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select database (safe for Hostinger shared hosting where CREATE DATABASE privilege is restricted)
if (!$conn->select_db(DB_NAME)) {
    @$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    if (!$conn->select_db(DB_NAME)) {
        die("Error selecting database " . DB_NAME . ": " . $conn->error);
    }
}

// Set charset
$conn->set_charset("utf8mb4");

// Auto-migration guard: ensure address column exists on employees
@$conn->query("ALTER TABLE employees ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL AFTER phone");

/**
 * Execute a prepared statement
 * @param string $sql SQL query with placeholders
 * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
 * @param array $params Parameters to bind
 * @return mysqli_result|bool
 */
function executeQuery($sql, $types = "", $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        // Log error for debugging
        error_log("SQL Prepare Error: " . $conn->error . " | Query: " . $sql);
        return false;
    }
    
    if (!empty($params)) {
        if (!$stmt->bind_param($types, ...$params)) {
            error_log("SQL Bind Error: " . $stmt->error . " | Types: " . $types);
            $stmt->close();
            return false;
        }
    }
    
    if (!$stmt->execute()) {
        error_log("SQL Execute Error: " . $stmt->error . " | Query: " . $sql);
        $stmt->close();
        return false;
    }
    
    if (strpos(strtoupper($sql), 'SELECT') === 0) {
        $result = $stmt->get_result();
        if ($result === false) {
            error_log("SQL Get Result Error: " . $stmt->error);
            $stmt->close();
            return false;
        }
        return $result;
    }
    
    return $stmt;
}

/**
 * Get single row from query
 */
function fetchOne($sql, $types = "", $params = []) {
    $result = executeQuery($sql, $types, $params);
    if ($result && $result instanceof mysqli_result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Get all rows from query
 */
function fetchAll($sql, $types = "", $params = []) {
    $result = executeQuery($sql, $types, $params);
    if ($result && $result instanceof mysqli_result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

/**
 * Get last inserted ID
 */
function lastInsertId() {
    global $conn;
    return $conn->insert_id;
}

/**
 * Escape string for safe SQL
 */
function escape($string) {
    global $conn;
    return $conn->real_escape_string($string);
}

/**
 * Insert a row and return the inserted ID
 * @param string $sql SQL INSERT query
 * @param string $types Parameter types
 * @param array $params Parameters to bind
 * @return int|false The inserted ID or false on failure
 */
function insert($sql, $types = "", $params = []) {
    $result = executeQuery($sql, $types, $params);
    if ($result) {
        return lastInsertId();
    }
    return false;
}

/**
 * Update rows and return affected count
 * @param string $sql SQL UPDATE query
 * @param string $types Parameter types
 * @param array $params Parameters to bind
 * @return int|false Number of affected rows or false on failure
 */
function update($sql, $types = "", $params = []) {
    global $conn;
    $result = executeQuery($sql, $types, $params);
    if ($result) {
        return $conn->affected_rows;
    }
    return false;
}

/**
 * Delete rows and return affected count
 * @param string $sql SQL DELETE query
 * @param string $types Parameter types
 * @param array $params Parameters to bind
 * @return int|false Number of affected rows or false on failure
 */
function delete($sql, $types = "", $params = []) {
    global $conn;
    $result = executeQuery($sql, $types, $params);
    if ($result) {
        return $conn->affected_rows;
    }
    return false;
}
?>
