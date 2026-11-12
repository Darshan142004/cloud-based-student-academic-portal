<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$student_id = requireStudentApi();
header('Content-Type: application/json');

$student = epFetchOne(
    $conn,
    "SELECT first_name, last_name, current_semester, profile_photo FROM students WHERE student_id = ?",
    'i',
    [$student_id]
);

if (!$student) {
    mysqli_close($conn);
    sendResponse(false, 'Student record not found', null, 404);
}

$current_semester = isset($student['current_semester']) ? (int) $student['current_semester'] : 0;
if ($current_semester <= 0) {
    mysqli_close($conn);
    sendResponse(false, 'Invalid current semester recorded for student', null, 400);
}

$semRows = epFetchAll(
    $conn,
    "SELECT DISTINCT semester FROM enrollments WHERE student_id = ? ORDER BY semester DESC",
    'i',
    [$student_id]
);
$availableSemesters = array_map(fn($r) => (int) $r['semester'], $semRows);
if (!in_array($current_semester, $availableSemesters, true)) {
    array_unshift($availableSemesters, $current_semester);
    rsort($availableSemesters);
}

$semParam = isset($_GET['semester']) ? trim((string) $_GET['semester']) : (string) $current_semester;
$selectedSemester = strtolower($semParam) === 'all' ? 'all' : max(1, (int) $semParam);

$sql = "SELECT
    c.course_id,
    c.course_code,
    c.course_name,
    c.credits,
    c.semester,
    c.faculty_name,
    c.faculty_email,
    c.description,
    c.syllabus,
    a.total_classes,
    a.classes_attended,
    CASE
        WHEN a.total_classes > 0 THEN ROUND((a.classes_attended / a.total_classes) * 100, 2)
        ELSE a.attendance_percentage
    END AS attendance_percentage
FROM enrollments e
JOIN courses c ON e.course_id = c.course_id
LEFT JOIN attendance a
  ON a.course_id = c.course_id
 AND a.student_id = e.student_id
 AND a.semester = e.semester
WHERE e.student_id = ?";

$types = 'i';
$params = [$student_id];

if ($selectedSemester !== 'all') {
    $sql .= " AND e.semester = ?";
    $types .= 'i';
    $params[] = $selectedSemester;
}
$sql .= " ORDER BY e.semester DESC, c.course_code ASC";

$courses = epFetchAll($conn, $sql, $types, $params);
mysqli_close($conn);

sendResponse(true, 'Courses loaded', [
    'courses' => $courses,
    'semester' => $current_semester,
    'selected_semester' => $selectedSemester,
    'available_semesters' => $availableSemesters,
    'total_courses' => count($courses),
    'total_credits' => array_sum(array_map(fn($c) => (int) ($c['credits'] ?? 0), $courses)),
    'student_name' => trim($student['first_name'] . ' ' . $student['last_name']),
    'profile_photo' => $student['profile_photo'],
    'profile_photo_url' => epGetStudentAvatarUrl(
        $student['profile_photo'],
        trim($student['first_name'] . ' ' . $student['last_name'])
    ),
]);
?>
