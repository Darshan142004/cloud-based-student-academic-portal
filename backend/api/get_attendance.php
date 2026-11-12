<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/analytics_helpers.php';

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

// Determine available semesters from enrollments + attendance
$semRows = epFetchAll(
    $conn,
    "SELECT DISTINCT semester FROM (
         SELECT semester FROM attendance WHERE student_id = ?
         UNION
         SELECT semester FROM enrollments WHERE student_id = ?
     ) s
     ORDER BY semester DESC",
    'ii',
    [$student_id, $student_id]
);
$availableSemesters = array_map(fn($r) => (int) $r['semester'], $semRows);
if (!in_array($current_semester, $availableSemesters, true)) {
    array_unshift($availableSemesters, $current_semester);
    rsort($availableSemesters);
}

$semParam = isset($_GET['semester']) ? trim((string) $_GET['semester']) : (string) $current_semester;
$selectedSemester = strtolower($semParam) === 'all' ? 'all' : max(1, (int) $semParam);
$selectedCourseId = isset($_GET['course_id']) && $_GET['course_id'] !== 'all' ? (int) $_GET['course_id'] : 0;

// Courses available in the selected semester filter (for course dropdown)
if ($selectedSemester === 'all') {
    $courseOptionRows = epFetchAll(
        $conn,
        "SELECT DISTINCT c.course_id, c.course_code, c.course_name, a.semester
         FROM attendance a
         JOIN courses c ON a.course_id = c.course_id
         WHERE a.student_id = ?
         ORDER BY a.semester DESC, c.course_code ASC",
        'i',
        [$student_id]
    );
} else {
    $courseOptionRows = epFetchAll(
        $conn,
        "SELECT DISTINCT c.course_id, c.course_code, c.course_name, a.semester
         FROM attendance a
         JOIN courses c ON a.course_id = c.course_id
         WHERE a.student_id = ? AND a.semester = ?
         ORDER BY c.course_code ASC",
        'ii',
        [$student_id, $selectedSemester]
    );
}

// Fetch filtered attendance records
$sql = "SELECT
            a.course_id,
            a.semester,
            c.course_code,
            c.course_name,
            c.credits,
            a.total_classes,
            a.classes_attended
        FROM attendance a
        JOIN courses c ON a.course_id = c.course_id
        WHERE a.student_id = ?";
$types = 'i';
$params = [$student_id];

if ($selectedSemester !== 'all') {
    $sql .= " AND a.semester = ?";
    $types .= 'i';
    $params[] = $selectedSemester;
}
if ($selectedCourseId > 0) {
    $sql .= " AND a.course_id = ?";
    $types .= 'i';
    $params[] = $selectedCourseId;
}
$sql .= " ORDER BY a.semester DESC, c.course_code ASC";

$rawRows = epFetchAll($conn, $sql, $types, $params);
mysqli_close($conn);

$attendanceData = [];
$totalClassesAll = 0;
$totalAttendedAll = 0;

foreach ($rawRows as $row) {
    $tot = (int) ($row['total_classes'] ?? 0);
    $att = (int) ($row['classes_attended'] ?? 0);
    $targets = epComputeAttendanceTargets($att, $tot);
    $totalClassesAll += $tot;
    $totalAttendedAll += $att;

    $attendanceData[] = [
        'course_id' => (int) $row['course_id'],
        'semester' => (int) $row['semester'],
        'course_code' => $row['course_code'],
        'course_name' => $row['course_name'],
        'credits' => (int) $row['credits'],
        'total_classes' => $tot,
        'classes_attended' => $att,
        'classes_missed' => max(0, $tot - $att),
        'attendance_percentage' => $targets['percentage'],
        'status' => $targets['status'],
        'classes_needed_75' => $targets['classes_needed_75'],
        'classes_needed_85' => $targets['classes_needed_85'],
        'safe_absences_75' => $targets['safe_absences_75'],
        'safe_absences_85' => $targets['safe_absences_85'],
    ];
}

$overallTargets = epComputeAttendanceTargets($totalAttendedAll, $totalClassesAll);

sendResponse(true, 'Attendance loaded', [
    'attendance' => $attendanceData,
    'overall' => [
        'total_classes' => $totalClassesAll,
        'attended' => $totalAttendedAll,
        'missed' => max(0, $totalClassesAll - $totalAttendedAll),
        'percentage' => $overallTargets['percentage'],
        'status' => $overallTargets['status'],
        'classes_needed_75' => $overallTargets['classes_needed_75'],
        'classes_needed_85' => $overallTargets['classes_needed_85'],
        'safe_absences_75' => $overallTargets['safe_absences_75'],
        'safe_absences_85' => $overallTargets['safe_absences_85'],
    ],
    'semester' => $current_semester,
    'selected_semester' => $selectedSemester,
    'selected_course_id' => $selectedCourseId > 0 ? $selectedCourseId : 'all',
    'available_semesters' => $availableSemesters,
    'available_courses' => $courseOptionRows,
    'student_name' => trim($student['first_name'] . ' ' . $student['last_name']),
    'profile_photo' => $student['profile_photo'],
    'profile_photo_url' => epGetStudentAvatarUrl(
        $student['profile_photo'],
        trim($student['first_name'] . ' ' . $student['last_name'])
    ),
]);
?>
