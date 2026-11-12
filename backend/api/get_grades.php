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

$gradeRows = epFetchAll(
    $conn,
    "SELECT
        g.semester,
        g.course_id,
        c.course_code,
        c.course_name,
        c.credits,
        g.internal_marks,
        g.external_marks,
        g.total_marks,
        g.grade,
        g.grade_points
     FROM grades g
     JOIN courses c ON g.course_id = c.course_id
     WHERE g.student_id = ?
     ORDER BY g.semester ASC, c.course_code ASC",
    'i',
    [$student_id]
);

mysqli_close($conn);

$trends = epComputeSemesterAndCumulativeTrends($gradeRows);
$gradeDist = epComputeGradeDistribution($gradeRows);

$semMeta = [];
foreach ($trends['semesters'] as $s) {
    $semMeta[(int) $s['semester']] = $s;
}

$gradesBySemester = [];
foreach ($gradeRows as $row) {
    $sem = (int) $row['semester'];
    if (!isset($gradesBySemester[$sem])) {
        $gradesBySemester[$sem] = [
            'courses' => [],
            'total_credits' => $semMeta[$sem]['credits'] ?? 0,
            'sgpa' => $semMeta[$sem]['sgpa'] ?? 0,
        ];
    }
    $gradesBySemester[$sem]['courses'][] = $row;
}

sendResponse(true, 'Grades loaded', [
    'grades' => $gradesBySemester,
    'sgpa_trend' => $trends['semesters'],
    'cgpa' => $trends['cgpa'],
    'latest_sgpa' => $trends['latest_sgpa'],
    'latest_semester' => $trends['latest_semester'],
    'highest_sgpa' => $trends['highest_sgpa'],
    'highest_semester' => $trends['highest_semester'],
    'total_credits' => $trends['total_graded_credits'],
    'grade_distribution' => $gradeDist,
    'marking_scheme' => [
        'internal_max' => 50,
        'external_max' => 50,
        'total_max' => 100,
    ],
    'current_semester' => (int) $student['current_semester'],
    'student_name' => trim($student['first_name'] . ' ' . $student['last_name']),
    'profile_photo' => $student['profile_photo'],
    'profile_photo_url' => epGetStudentAvatarUrl(
        $student['profile_photo'],
        trim($student['first_name'] . ' ' . $student['last_name'])
    ),
]);
?>
