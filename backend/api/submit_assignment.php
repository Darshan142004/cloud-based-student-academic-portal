<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/storage_service.php';

$student_id = requireStudentApi();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', null, 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$payload = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : $_POST;

$assignment_id = isset($payload['assignment_id']) ? (int) $payload['assignment_id'] : 0;
$action = strtolower(trim((string) ($payload['action'] ?? 'submit')));
$progress_pct = isset($payload['progress_pct']) ? (int) $payload['progress_pct'] : null;
$submission_text = epCleanText($payload['submission_text'] ?? ($payload['submission_notes'] ?? ''));

if ($assignment_id <= 0) {
    sendResponse(false, 'Valid assignment_id is required', null, 400);
}
if (!in_array($action, ['progress', 'submit'], true)) {
    sendResponse(false, 'Invalid action. Use "progress" or "submit".', null, 400);
}

// 1. Verify assignment exists AND student is enrolled in its course & semester
$sql_check = "SELECT a.assignment_id, a.course_id, a.semester, a.title, a.due_date,
                     s.submission_id, s.status AS current_status, s.file_path AS existing_file_path
              FROM assignments a
              JOIN enrollments e
                ON e.course_id = a.course_id
               AND e.semester = a.semester
               AND e.student_id = ?
              LEFT JOIN assignment_submissions s
                ON s.assignment_id = a.assignment_id
               AND s.student_id = ?
              WHERE a.assignment_id = ?";

$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "iii", $student_id, $student_id, $assignment_id);
mysqli_stmt_execute($stmt_check);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
mysqli_stmt_close($stmt_check);

if (!$row) {
    mysqli_close($conn);
    sendResponse(false, 'Assignment not found or you are not enrolled in this course.', null, 404);
}

if (($row['current_status'] ?? '') === 'graded') {
    mysqli_close($conn);
    sendResponse(false, 'This assignment has already been graded and cannot be modified.', null, 409);
}

// 2. Optional safe file upload handling
$savedFilePath = $row['existing_file_path'] ?? null;
if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $allowedExts = ['pdf', 'doc', 'docx', 'txt', 'zip', 'png', 'jpg', 'jpeg'];
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
        'image/png',
        'image/jpeg',
        'image/pjpeg',
    ];
    $val = epStorageValidateUpload($_FILES['submission_file'], $allowedExts, $allowedMimes, 5 * 1024 * 1024);
    if (!$val['ok']) {
        mysqli_close($conn);
        sendResponse(false, $val['error'], null, 400);
    }

    $storageKey = epStorageGenerateKey('assignments', "sub_{$assignment_id}_stu_{$student_id}", $val['ext']);
    $stored = epStoragePutUploadedFile($_FILES['submission_file']['tmp_name'], $storageKey, $val['mime']);
    if (!$stored['ok']) {
        mysqli_close($conn);
        sendResponse(false, $stored['error'] ?: 'Failed to store uploaded assignment file.', null, 500);
    }

    if (!empty($savedFilePath)) {
        epStorageDeleteObject((string) $savedFilePath);
    }
    $savedFilePath = $stored['provider'] === 'azure_blob'
        ? ('azure:' . $stored['storage_key'])
        : $stored['local_path'];
}

if ($action === 'submit') {
    if ($submission_text === '' && empty($savedFilePath)) {
        mysqli_close($conn);
        sendResponse(false, 'Please provide submission notes/link or attach a file before submitting.', null, 400);
    }
    $newStatus = 'submitted';
    $newProgress = 100;

    $sql_upsert = "INSERT INTO assignment_submissions
                       (assignment_id, student_id, status, progress_pct, submission_text, file_path, submitted_at)
                   VALUES (?, ?, 'submitted', 100, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE
                       status = 'submitted',
                       progress_pct = 100,
                       submission_text = VALUES(submission_text),
                       file_path = COALESCE(VALUES(file_path), file_path),
                       submitted_at = NOW()";
    $stmt = mysqli_prepare($conn, $sql_upsert);
    mysqli_stmt_bind_param($stmt, "iiss", $assignment_id, $student_id, $submission_text, $savedFilePath);
} else {
    $clampedProgress = max(0, min(99, $progress_pct ?? 50));
    $newStatus = $clampedProgress > 0 ? 'in_progress' : 'pending';
    $newProgress = $clampedProgress;

    $sql_upsert = "INSERT INTO assignment_submissions
                       (assignment_id, student_id, status, progress_pct, submission_text, file_path)
                   VALUES (?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE
                       status = IF(status IN ('submitted','graded'), status, VALUES(status)),
                       progress_pct = IF(status IN ('submitted','graded'), progress_pct, VALUES(progress_pct)),
                       submission_text = IF(VALUES(submission_text) <> '', VALUES(submission_text), submission_text),
                       file_path = COALESCE(VALUES(file_path), file_path)";
    $stmt = mysqli_prepare($conn, $sql_upsert);
    mysqli_stmt_bind_param($stmt, "iisiss", $assignment_id, $student_id, $newStatus, $newProgress, $submission_text, $savedFilePath);
}

if (!mysqli_stmt_execute($stmt)) {
    $err = mysqli_error($conn);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    sendResponse(false, 'Failed to save assignment submission: ' . $err, null, 500);
}
mysqli_stmt_close($stmt);

$stmt_out = mysqli_prepare(
    $conn,
    "SELECT status, progress_pct, submitted_at, file_path, submission_text
     FROM assignment_submissions
     WHERE assignment_id = ? AND student_id = ?"
);
mysqli_stmt_bind_param($stmt_out, "ii", $assignment_id, $student_id);
mysqli_stmt_execute($stmt_out);
$updated = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_out));
mysqli_stmt_close($stmt_out);
mysqli_close($conn);

sendResponse(
    true,
    $action === 'submit' ? 'Assignment submitted successfully' : 'Assignment progress updated',
    [
        'assignment_id' => $assignment_id,
        'status' => $updated['status'] ?? $newStatus,
        'progress_pct' => (int) ($updated['progress_pct'] ?? $newProgress),
        'submitted_at' => $updated['submitted_at'] ?? null,
        'file_path' => $updated['file_path'] ?? $savedFilePath,
    ]
);
?>
