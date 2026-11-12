<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/analytics_helpers.php';

$student_id = requireStudentApi();
header('Content-Type: application/json');

$sql_student = "SELECT first_name, last_name, current_semester, profile_photo FROM students WHERE student_id = ?";
$stmt_student = mysqli_prepare($conn, $sql_student);
mysqli_stmt_bind_param($stmt_student, "i", $student_id);
mysqli_stmt_execute($stmt_student);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student));
mysqli_stmt_close($stmt_student);

if (!$student) {
    mysqli_close($conn);
    sendResponse(false, 'Student record not found', null, 404);
}

$sql = "SELECT
            a.assignment_id,
            a.course_id,
            a.semester,
            a.title,
            a.description,
            a.due_date,
            a.max_marks,
            a.priority,
            c.course_code,
            c.course_name,
            COALESCE(s.status, 'pending') AS submission_status,
            COALESCE(s.progress_pct, 0) AS progress_pct,
            s.submitted_at,
            s.marks_obtained,
            s.feedback,
            s.submission_text,
            s.file_path
        FROM assignments a
        JOIN courses c ON a.course_id = c.course_id
        JOIN enrollments e
          ON e.course_id = a.course_id
         AND e.semester = a.semester
         AND e.student_id = ?
        LEFT JOIN assignment_submissions s
          ON s.assignment_id = a.assignment_id
         AND s.student_id = ?
        ORDER BY a.due_date ASC, a.assignment_id ASC";

$rows = epFetchAll($conn, $sql, 'ii', [$student_id, $student_id]);

$enrolledCourses = epFetchAll(
    $conn,
    "SELECT DISTINCT c.course_id, c.course_code, c.course_name, e.semester
     FROM enrollments e
     JOIN courses c ON e.course_id = c.course_id
     WHERE e.student_id = ?
     ORDER BY e.semester DESC, c.course_code ASC",
    'i',
    [$student_id]
);

mysqli_close($conn);

$analysis = epAnalyzeAssignments($rows);

// Optional query filter (does not alter overall summary counts)
$filterStatus = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : 'all';
$filterCourseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$filterSemester = isset($_GET['semester']) && $_GET['semester'] !== 'all' ? (int) $_GET['semester'] : 0;

$items = $analysis['items'];
if ($filterStatus !== '' && $filterStatus !== 'all') {
    $items = array_values(array_filter($items, function ($item) use ($filterStatus) {
        if ($filterStatus === 'completed') {
            return in_array($item['status'], ['submitted', 'graded'], true);
        }
        if ($filterStatus === 'progress') {
            return $item['status'] === 'in_progress';
        }
        if ($filterStatus === 'overdue') {
            return (bool) $item['is_overdue'];
        }
        return $item['status'] === $filterStatus;
    }));
}
if ($filterCourseId > 0) {
    $items = array_values(array_filter($items, fn($item) => (int) $item['course_id'] === $filterCourseId));
}
if ($filterSemester > 0) {
    $items = array_values(array_filter($items, fn($item) => (int) $item['semester'] === $filterSemester));
}

sendResponse(true, 'Assignments loaded', [
    'student_name' => trim($student['first_name'] . ' ' . $student['last_name']),
    'current_semester' => (int) $student['current_semester'],
    'profile_photo' => $student['profile_photo'],
    'profile_photo_url' => epGetStudentAvatarUrl(
        $student['profile_photo'],
        trim($student['first_name'] . ' ' . $student['last_name'])
    ),
    'has_data' => $analysis['has_data'],
    'summary' => $analysis['summary'],
    'timeliness' => $analysis['timeliness'],
    'by_course' => $analysis['by_course'],
    'enrolled_courses' => $enrolledCourses,
    'assignments' => $items,
]);
?>
