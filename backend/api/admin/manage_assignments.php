<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/storage_service.php';

$admin_id = requireAdminApi();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $assignmentId = isset($_GET['assignment_id']) ? (int) $_GET['assignment_id'] : 0;

    if ($assignmentId > 0) {
        $stmtA = mysqli_prepare(
            $conn,
            "SELECT a.*, c.course_code, c.course_name
             FROM assignments a
             JOIN courses c ON a.course_id = c.course_id
             WHERE a.assignment_id = ?"
        );
        mysqli_stmt_bind_param($stmtA, "i", $assignmentId);
        mysqli_stmt_execute($stmtA);
        $assignment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));
        mysqli_stmt_close($stmtA);

        if (!$assignment) {
            mysqli_close($conn);
            sendResponse(false, 'Assignment not found', null, 404);
        }

        $stmtSubs = mysqli_prepare(
            $conn,
            "SELECT st.student_id, st.enrollment_id,
                    CONCAT(st.first_name, ' ', st.last_name) AS student_name,
                    COALESCE(s.status, 'pending') AS status,
                    COALESCE(s.progress_pct, 0) AS progress_pct,
                    s.submission_text, s.file_path, s.submitted_at,
                    s.marks_obtained, s.feedback, s.graded_at
             FROM enrollments e
             JOIN students st ON st.student_id = e.student_id
             LEFT JOIN assignment_submissions s
               ON s.assignment_id = ? AND s.student_id = st.student_id
             WHERE e.course_id = ? AND e.semester = ?
             ORDER BY st.enrollment_id ASC"
        );
        $cid = (int) $assignment['course_id'];
        $sem = (int) $assignment['semester'];
        mysqli_stmt_bind_param($stmtSubs, "iii", $assignmentId, $cid, $sem);
        mysqli_stmt_execute($stmtSubs);
        $resSubs = mysqli_stmt_get_result($stmtSubs);
        $submissions = [];
        while ($row = mysqli_fetch_assoc($resSubs)) {
            $submissions[] = $row;
        }
        mysqli_stmt_close($stmtSubs);
        mysqli_close($conn);

        sendResponse(true, 'Assignment submissions loaded', [
            'assignment' => $assignment,
            'submissions' => $submissions,
        ]);
    }

    // List assignments with optional course/semester filters + aggregate counts + available courses
    $filterCourseId = isset($_GET['course_id']) && $_GET['course_id'] !== 'all' ? (int) $_GET['course_id'] : 0;
    $filterSemester = isset($_GET['semester']) && $_GET['semester'] !== 'all' ? (int) $_GET['semester'] : 0;

    $sqlList = "SELECT a.assignment_id, a.course_id, a.semester, a.title, a.description,
                       a.due_date, a.max_marks, a.priority, a.created_at,
                       c.course_code, c.course_name,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = a.course_id AND e.semester = a.semester) AS enrolled_count,
                       (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.assignment_id AND s.status IN ('submitted','graded')) AS submitted_count,
                       (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.assignment_id AND s.status = 'graded') AS graded_count
                FROM assignments a
                JOIN courses c ON a.course_id = c.course_id
                WHERE 1=1";
    $types = '';
    $params = [];
    if ($filterCourseId > 0) {
        $sqlList .= " AND a.course_id = ?";
        $types .= 'i';
        $params[] = $filterCourseId;
    }
    if ($filterSemester > 0) {
        $sqlList .= " AND a.semester = ?";
        $types .= 'i';
        $params[] = $filterSemester;
    }
    $sqlList .= " ORDER BY a.due_date DESC, a.assignment_id DESC";
    $assignments = epFetchAll($conn, $sqlList, $types, $params);

    $courses = epFetchAll($conn, "SELECT course_id, course_code, course_name, semester FROM courses ORDER BY semester DESC, course_code ASC");

    mysqli_close($conn);
    sendResponse(true, 'Assignments loaded', [
        'assignments' => $assignments,
        'courses' => $courses,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mysqli_close($conn);
    sendResponse(false, 'Invalid request method', null, 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$payload = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : $_POST;

$action = strtolower(trim((string) ($payload['action'] ?? 'create')));

if ($action === 'delete') {
    $assignmentId = (int) ($payload['assignment_id'] ?? 0);
    if ($assignmentId <= 0) {
        mysqli_close($conn);
        sendResponse(false, 'Valid assignment_id is required', null, 400);
    }

    // Collect any uploaded files for cleanup after successful deletion
    $fileRows = epFetchAll($conn, "SELECT file_path FROM assignment_submissions WHERE assignment_id = ? AND file_path IS NOT NULL", 'i', [$assignmentId]);

    $stmt = mysqli_prepare($conn, "DELETE FROM assignments WHERE assignment_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $assignmentId);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    if ($ok && $affected > 0) {
        foreach ($fileRows as $fr) {
            $fp = trim((string) ($fr['file_path'] ?? ''));
            if ($fp !== '') {
                epStorageDeleteObject($fp);
            }
        }
        sendResponse(true, 'Assignment deleted successfully');
    }
    sendResponse(false, 'Assignment not found or could not be deleted', null, 404);
}

$courseId = (int) ($payload['course_id'] ?? 0);
$title = epCleanText($payload['title'] ?? '');
$description = epCleanText($payload['description'] ?? '');
$dueDateRaw = trim((string) ($payload['due_date'] ?? ''));
$maxMarks = isset($payload['max_marks']) ? (float) $payload['max_marks'] : 20.0;
$priority = strtolower(trim((string) ($payload['priority'] ?? 'medium')));

if ($courseId <= 0 || $title === '' || $dueDateRaw === '') {
    mysqli_close($conn);
    sendResponse(false, 'Course, title, and due date are required', null, 400);
}
if ($maxMarks <= 0 || $maxMarks > 1000) {
    mysqli_close($conn);
    sendResponse(false, 'max_marks must be between 1 and 1000', null, 400);
}
if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
    $priority = 'medium';
}

$dueTs = strtotime($dueDateRaw);
if ($dueTs === false) {
    mysqli_close($conn);
    sendResponse(false, 'Invalid due_date format', null, 400);
}
$dueDateSql = date('Y-m-d H:i:s', $dueTs);

// Verify course exists and use its authoritative semester
$stmtC = mysqli_prepare($conn, "SELECT course_id, semester FROM courses WHERE course_id = ?");
mysqli_stmt_bind_param($stmtC, "i", $courseId);
mysqli_stmt_execute($stmtC);
$course = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC));
mysqli_stmt_close($stmtC);

if (!$course) {
    mysqli_close($conn);
    sendResponse(false, 'Selected course does not exist', null, 404);
}
$semester = isset($payload['semester']) && (int) $payload['semester'] > 0
    ? (int) $payload['semester']
    : (int) $course['semester'];

if ($action === 'update') {
    $assignmentId = (int) ($payload['assignment_id'] ?? 0);
    if ($assignmentId <= 0) {
        mysqli_close($conn);
        sendResponse(false, 'Valid assignment_id is required for update', null, 400);
    }
    $exists = epFetchOne($conn, "SELECT assignment_id FROM assignments WHERE assignment_id = ?", 'i', [$assignmentId]);
    if (!$exists) {
        mysqli_close($conn);
        sendResponse(false, 'Assignment not found', null, 404);
    }
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE assignments
         SET course_id = ?, semester = ?, title = ?, description = ?, due_date = ?, max_marks = ?, priority = ?
         WHERE assignment_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "iisssdsi", $courseId, $semester, $title, $description, $dueDateSql, $maxMarks, $priority, $assignmentId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    if ($ok) {
        sendResponse(true, 'Assignment updated successfully', ['assignment_id' => $assignmentId]);
    }
    sendResponse(false, 'Failed to update assignment', null, 500);
}

// Create
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO assignments (course_id, semester, title, description, due_date, max_marks, priority, created_by_admin_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, "iisssdsi", $courseId, $semester, $title, $description, $dueDateSql, $maxMarks, $priority, $admin_id);

if (mysqli_stmt_execute($stmt)) {
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    sendResponse(true, 'Assignment created successfully', ['assignment_id' => $newId]);
}

$err = mysqli_error($conn);
mysqli_stmt_close($stmt);
mysqli_close($conn);
sendResponse(false, 'Failed to create assignment: ' . $err, null, 500);
?>
