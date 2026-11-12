<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$admin_id = requireAdminApi();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mysqli_close($conn);
    sendResponse(false, 'Invalid request method', null, 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$payload = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : $_POST;

$assignmentId = (int) ($payload['assignment_id'] ?? 0);
$studentId = (int) ($payload['student_id'] ?? 0);
$targetStatus = strtolower(trim((string) ($payload['status'] ?? 'graded')));
$marksRaw = $payload['marks_obtained'] ?? null;
$feedback = epCleanText($payload['feedback'] ?? '');

if ($assignmentId <= 0 || $studentId <= 0) {
    mysqli_close($conn);
    sendResponse(false, 'assignment_id and student_id are required', null, 400);
}

$allowedStatuses = ['pending', 'in_progress', 'submitted', 'graded'];
if (!in_array($targetStatus, $allowedStatuses, true)) {
    mysqli_close($conn);
    sendResponse(false, 'Invalid status. Allowed: pending, in_progress, submitted, graded', null, 400);
}

// Verify assignment + student enrollment
$assignment = epFetchOne(
    $conn,
    "SELECT a.assignment_id, a.max_marks, s.marks_obtained AS existing_marks, s.progress_pct AS existing_progress
     FROM assignments a
     JOIN enrollments e
       ON e.course_id = a.course_id
      AND e.semester = a.semester
      AND e.student_id = ?
     LEFT JOIN assignment_submissions s
       ON s.assignment_id = a.assignment_id
      AND s.student_id = ?
     WHERE a.assignment_id = ?",
    'iii',
    [$studentId, $studentId, $assignmentId]
);

if (!$assignment) {
    mysqli_close($conn);
    sendResponse(false, 'Assignment not found or student is not enrolled in this course', null, 404);
}

$maxMarks = (float) $assignment['max_marks'];
$marksObtained = null;

if ($targetStatus === 'graded') {
    if ($marksRaw === null || $marksRaw === '') {
        if ($assignment['existing_marks'] !== null) {
            $marksObtained = (float) $assignment['existing_marks'];
        } else {
            mysqli_close($conn);
            sendResponse(false, 'marks_obtained is required when status is graded', null, 400);
        }
    } else {
        if (!is_numeric($marksRaw)) {
            mysqli_close($conn);
            sendResponse(false, 'marks_obtained must be numeric', null, 400);
        }
        $marksObtained = (float) $marksRaw;
    }

    if ($marksObtained < 0 || $marksObtained > $maxMarks) {
        mysqli_close($conn);
        sendResponse(false, "marks_obtained must be between 0 and {$maxMarks}", null, 400);
    }

    $sql = "INSERT INTO assignment_submissions
                (assignment_id, student_id, status, progress_pct, marks_obtained, feedback, graded_by_admin_id, graded_at)
            VALUES (?, ?, 'graded', 100, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = 'graded',
                progress_pct = 100,
                marks_obtained = VALUES(marks_obtained),
                feedback = VALUES(feedback),
                graded_by_admin_id = VALUES(graded_by_admin_id),
                graded_at = NOW()";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iidsi", $assignmentId, $studentId, $marksObtained, $feedback, $admin_id);
} elseif ($targetStatus === 'submitted') {
    $sql = "INSERT INTO assignment_submissions
                (assignment_id, student_id, status, progress_pct, submitted_at, feedback)
            VALUES (?, ?, 'submitted', 100, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                status = 'submitted',
                progress_pct = 100,
                submitted_at = COALESCE(submitted_at, NOW()),
                marks_obtained = NULL,
                graded_by_admin_id = NULL,
                graded_at = NULL,
                feedback = IF(VALUES(feedback) <> '', VALUES(feedback), feedback)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iis", $assignmentId, $studentId, $feedback);
} else {
    $newProgress = $targetStatus === 'in_progress'
        ? max(1, min(99, (int) ($payload['progress_pct'] ?? ($assignment['existing_progress'] ?: 50))))
        : 0;
    $sql = "INSERT INTO assignment_submissions
                (assignment_id, student_id, status, progress_pct, feedback)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                progress_pct = VALUES(progress_pct),
                submitted_at = NULL,
                marks_obtained = NULL,
                graded_by_admin_id = NULL,
                graded_at = NULL,
                feedback = IF(VALUES(feedback) <> '', VALUES(feedback), feedback)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iisis", $assignmentId, $studentId, $targetStatus, $newProgress, $feedback);
}

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    sendResponse(true, $targetStatus === 'graded' ? 'Submission graded successfully' : 'Submission status updated successfully', [
        'assignment_id' => $assignmentId,
        'student_id' => $studentId,
        'status' => $targetStatus,
        'marks_obtained' => $marksObtained,
    ]);
}

$err = mysqli_error($conn);
mysqli_stmt_close($stmt);
mysqli_close($conn);
sendResponse(false, 'Failed to update submission: ' . $err, null, 500);
?>
