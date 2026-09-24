<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

$student_id = getStudentId();

// Get grades for all semesters
$sql = "SELECT 
    g.semester,
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
ORDER BY g.semester, c.course_code";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$grades_by_semester = [];
$total_credits = 0;
$total_grade_points = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $semester = $row['semester'];
    
    if (!isset($grades_by_semester[$semester])) {
        $grades_by_semester[$semester] = [
            'courses' => [],
            'total_credits' => 0,
            'sgpa' => 0
        ];
    }
    
    $grades_by_semester[$semester]['courses'][] = $row;
    
    if ($row['grade_points'] !== null) {
        $grades_by_semester[$semester]['total_credits'] += $row['credits'];
        $total_credits += $row['credits'];
        $total_grade_points += ($row['grade_points'] * $row['credits']);
    }
}

// Calculate SGPA for each semester
foreach ($grades_by_semester as $sem => &$data) {
    $sem_grade_points = 0;
    foreach ($data['courses'] as $course) {
        if ($course['grade_points'] !== null) {
            $sem_grade_points += ($course['grade_points'] * $course['credits']);
        }
    }
    $data['sgpa'] = $data['total_credits'] > 0 ? 
        round($sem_grade_points / $data['total_credits'], 2) : 0;
}

// Calculate overall CGPA
$cgpa = $total_credits > 0 ? round($total_grade_points / $total_credits, 2) : 0;

// Get student name
$sql_student = "SELECT first_name, last_name, current_semester FROM students WHERE student_id = ?";
$stmt_student = mysqli_prepare($conn, $sql_student);
mysqli_stmt_bind_param($stmt_student, "i", $student_id);
mysqli_stmt_execute($stmt_student);
$result_student = mysqli_stmt_get_result($stmt_student);
$student = mysqli_fetch_assoc($result_student);

$response = [
    'success' => true,
    'data' => [
        'grades' => $grades_by_semester,
        'cgpa' => $cgpa,
        'total_credits' => $total_credits,
        'current_semester' => $student['current_semester'],
        'student_name' => $student['first_name'] . ' ' . $student['last_name']
    ]
];

echo json_encode($response);

mysqli_stmt_close($stmt);
mysqli_stmt_close($stmt_student);
mysqli_close($conn);
?>