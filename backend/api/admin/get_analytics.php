<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    sendResponse(false, 'Unauthorized access');
}

header('Content-Type: application/json');

// 1. Attendance Range Categorization (Students categorized by overall attendance percentage)
$sql_att_dist = "SELECT 
    s.student_id,
    AVG(a.attendance_percentage) as avg_attendance
FROM students s
JOIN attendance a ON s.student_id = a.student_id
GROUP BY s.student_id";

$result_att_dist = mysqli_query($conn, $sql_att_dist);

$attendance_ranges = [
    'safe' => 0,       // >= 85%
    'warning' => 0,    // 75% - 84.99%
    'critical' => 0    // < 75%
];

if ($result_att_dist) {
    while ($row = mysqli_fetch_assoc($result_att_dist)) {
        $avg = (float)$row['avg_attendance'];
        if ($avg >= 85.0) {
            $attendance_ranges['safe']++;
        } else if ($avg >= 75.0) {
            $attendance_ranges['warning']++;
        } else {
            $attendance_ranges['critical']++;
        }
    }
}

// 2. Semester-wise Student Distribution
$sql_sem_dist = "SELECT 
    current_semester, 
    COUNT(*) as total_students 
FROM students 
WHERE current_semester IS NOT NULL 
GROUP BY current_semester 
ORDER BY current_semester ASC";

$result_sem_dist = mysqli_query($conn, $sql_sem_dist);
$semester_distribution = [];

if ($result_sem_dist) {
    while ($row = mysqli_fetch_assoc($result_sem_dist)) {
        $semester_distribution[] = [
            'semester' => (int)$row['current_semester'],
            'total_students' => (int)$row['total_students']
        ];
    }
}

// 3. Course-wise Attendance
$sql_course_att = "SELECT 
    c.course_id,
    c.course_code,
    c.course_name,
    c.credits,
    ROUND(AVG(a.attendance_percentage), 2) as avg_attendance
FROM courses c
JOIN attendance a ON c.course_id = a.course_id
GROUP BY c.course_id, c.course_code, c.course_name, c.credits
ORDER BY c.course_code ASC";

$result_course_att = mysqli_query($conn, $sql_course_att);
$course_attendance = [];

if ($result_course_att) {
    while ($row = mysqli_fetch_assoc($result_course_att)) {
        $course_attendance[] = [
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'credits' => (int)$row['credits'],
            'avg_attendance' => (float)$row['avg_attendance']
        ];
    }
}

// 4. Academic Performance Analytics (Credit-weighted system CGPA & Grade Distribution)
$sql_system_cgpa = "SELECT 
    SUM(g.grade_points * c.credits) / SUM(c.credits) as system_cgpa
FROM grades g
JOIN courses c ON g.course_id = c.course_id
WHERE g.grade_points IS NOT NULL";

$result_system_cgpa = mysqli_query($conn, $sql_system_cgpa);
$system_cgpa = 0.00;
if ($result_system_cgpa && $row_cgpa = mysqli_fetch_assoc($result_system_cgpa)) {
    $system_cgpa = round((float)($row_cgpa['system_cgpa'] ?? 0), 2);
}

// Grade distribution
$sql_grades_dist = "SELECT 
    grade, 
    COUNT(*) as grade_count 
FROM grades 
WHERE grade IS NOT NULL AND grade != '' 
GROUP BY grade 
ORDER BY FIELD(grade, 'O', 'A+', 'A', 'B+', 'B', 'C', 'F')";

$result_grades_dist = mysqli_query($conn, $sql_grades_dist);
$grade_distribution = [];

if ($result_grades_dist) {
    while ($row = mysqli_fetch_assoc($result_grades_dist)) {
        $grade_distribution[$row['grade']] = (int)$row['grade_count'];
    }
}

$response = [
    'success' => true,
    'data' => [
        'attendance_ranges' => $attendance_ranges,
        'semester_distribution' => $semester_distribution,
        'course_attendance' => $course_attendance,
        'performance' => [
            'system_cgpa' => $system_cgpa,
            'grade_distribution' => $grade_distribution
        ]
    ]
];

echo json_encode($response);
mysqli_close($conn);
?>
