<?php
require_once __DIR__ . '/telemetry.php';
require_once __DIR__ . '/storage_service.php';
epRegisterTelemetryHooks();

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as a student
function isStudentSession() {
    return isset($_SESSION['student_id'], $_SESSION['user_type'])
        && $_SESSION['user_type'] === 'student'
        && (int)$_SESSION['student_id'] > 0;
}

// Check if user is logged in as an admin
function isAdminSession() {
    return isset($_SESSION['user_type'], $_SESSION['admin_id'])
        && $_SESSION['user_type'] === 'admin'
        && (int)$_SESSION['admin_id'] > 0;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']);
}

// Get logged in student ID
function getStudentId() {
    return isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : null;
}

// Redirect to login if not logged in (legacy HTML-style guard)
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../../index.html');
        exit();
    }
}

// Enforce authenticated student session for JSON API endpoints
function requireStudentApi() {
    if (!isStudentSession()) {
        sendResponse(false, 'Unauthorized: Please log in as a student.', null, 401);
    }
    return (int)$_SESSION['student_id'];
}

// Enforce authenticated admin session for JSON API endpoints
function requireAdminApi() {
    if (!isAdminSession()) {
        sendResponse(false, 'Unauthorized access', null, 401);
    }
    return (int)$_SESSION['admin_id'];
}

// Send JSON response
function sendResponse($success, $message, $data = null, $statusCode = null) {
    if ($statusCode !== null) {
        http_response_code((int)$statusCode);
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Sanitize input (legacy HTML-encoded trim)
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Clean plain text input without HTML-entity double encoding (for prepared SQL + JSON APIs)
function epCleanText($data): string {
    return trim(strip_tags((string) $data));
}

// Execute a prepared SELECT query and return all associative rows
function epFetchAll(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// Execute a prepared SELECT query and return first row or null
function epFetchOne(mysqli $conn, string $sql, string $types = '', array $params = []): ?array {
    $rows = epFetchAll($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}
?>