<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/analytics_helpers.php';

$student_id = requireStudentApi();
header('Content-Type: application/json');

$student = epFetchOne(
    $conn,
    "SELECT student_id, enrollment_id, first_name, last_name, email, phone,
            department, program, current_semester, admission_year, profile_photo
     FROM students
     WHERE student_id = ?",
    'i',
    [$student_id]
);

if (!$student) {
    mysqli_close($conn);
    sendResponse(false, 'Student record not found', null, 404);
}

$currentSem = (int) ($student['current_semester'] ?? 1);

// Current semester attendance (weighted by classes_attended / total_classes)
$attRows = epFetchAll(
    $conn,
    "SELECT a.course_id, a.semester, a.total_classes, a.classes_attended,
            c.course_code, c.course_name
     FROM attendance a
     JOIN courses c ON a.course_id = c.course_id
     WHERE a.student_id = ? AND a.semester = ?
     ORDER BY c.course_code ASC",
    'ii',
    [$student_id, $currentSem]
);

$totalClasses = 0;
$classesAttended = 0;
$attendanceAlerts = [];
foreach ($attRows as $r) {
    $tot = (int) ($r['total_classes'] ?? 0);
    $att = (int) ($r['classes_attended'] ?? 0);
    $totalClasses += $tot;
    $classesAttended += $att;
    if ($tot > 0) {
        $t = epComputeAttendanceTargets($att, $tot);
        if ($t['percentage'] < 85.0) {
            $attendanceAlerts[] = [
                'type' => $t['percentage'] < 75.0 ? 'danger' : 'warning',
                'title' => "{$r['course_code']} Attendance ({$t['percentage']}%)",
                'detail' => $t['percentage'] < 75.0
                    ? "Attend next {$t['classes_needed_75']} class(es) for 75% eligibility."
                    : "Attend next {$t['classes_needed_85']} class(es) to reach 85% Safe.",
                'url' => 'attendance.html',
            ];
        }
    }
}
$overallAttendance = epComputeAttendanceTargets($classesAttended, $totalClasses);

// Current semester enrolled courses count & credits
$courseAgg = epFetchOne(
    $conn,
    "SELECT COUNT(*) AS total_courses, COALESCE(SUM(c.credits), 0) AS total_credits
     FROM enrollments e
     JOIN courses c ON e.course_id = c.course_id
     WHERE e.student_id = ? AND e.semester = ?",
    'ii',
    [$student_id, $currentSem]
);

// All grades for credit-weighted SGPA / CGPA & grade distribution
$gradeRows = epFetchAll(
    $conn,
    "SELECT g.grade_id, g.semester, g.course_id, c.course_code, c.course_name, c.credits,
            g.internal_marks, g.external_marks, g.total_marks, g.grade, g.grade_points
     FROM grades g
     JOIN courses c ON g.course_id = c.course_id
     WHERE g.student_id = ?
     ORDER BY g.semester ASC, c.course_code ASC",
    'i',
    [$student_id]
);

$trends = epComputeSemesterAndCumulativeTrends($gradeRows);
$gradeDist = epComputeGradeDistribution($gradeRows);

// Recent grades (most recent graded semesters/courses)
$recentGrades = $gradeRows;
usort($recentGrades, function ($a, $b) {
    if ((int) $b['semester'] !== (int) $a['semester']) {
        return (int) $b['semester'] <=> (int) $a['semester'];
    }
    return (int) $b['grade_id'] <=> (int) $a['grade_id'];
});
$recentGrades = array_slice(array_map(fn($g) => [
    'course_code' => $g['course_code'],
    'course_name' => $g['course_name'],
    'semester' => (int) $g['semester'],
    'credits' => (int) $g['credits'],
    'total_marks' => $g['total_marks'] !== null ? (int) $g['total_marks'] : null,
    'grade' => $g['grade'],
    'grade_points' => $g['grade_points'] !== null ? (float) $g['grade_points'] : null,
], $recentGrades), 0, 4);

// Enrolled assignments & submissions
$assignmentRows = epFetchAll(
    $conn,
    "SELECT a.assignment_id, a.course_id, a.semester, a.title, a.description,
            a.due_date, a.max_marks, a.priority,
            c.course_code, c.course_name,
            COALESCE(s.status, 'pending') AS submission_status,
            COALESCE(s.progress_pct, 0) AS progress_pct,
            s.submitted_at, s.marks_obtained, s.feedback, s.submission_text, s.file_path
     FROM assignments a
     JOIN courses c ON a.course_id = c.course_id
     JOIN enrollments e ON e.course_id = a.course_id AND e.semester = a.semester AND e.student_id = ?
     LEFT JOIN assignment_submissions s ON s.assignment_id = a.assignment_id AND s.student_id = ?
     ORDER BY a.due_date ASC, a.assignment_id ASC",
    'ii',
    [$student_id, $student_id]
);
$assignAnalysis = epAnalyzeAssignments($assignmentRows);

$activeAssignments = array_values(array_filter(
    $assignAnalysis['items'],
    fn($item) => !in_array($item['status'], ['submitted', 'graded'], true)
));
$upcomingAssignments = array_slice($activeAssignments, 0, 4);

$assignmentAlerts = [];
foreach ($activeAssignments as $item) {
    if ($item['is_overdue'] || ($item['hours_remaining'] >= 0 && $item['hours_remaining'] <= 72)) {
        $assignmentAlerts[] = [
            'type' => $item['is_overdue'] ? 'danger' : 'warning',
            'title' => "{$item['course_code']}: {$item['title']}",
            'detail' => $item['is_overdue']
                ? 'Overdue — submit as soon as possible.'
                : 'Due within ' . max(1, (int) ceil($item['hours_remaining'])) . ' hour(s).',
            'url' => 'assignments.html',
        ];
    }
}

$alerts = array_merge($assignmentAlerts, $attendanceAlerts);
$pendingTasksCount = $assignAnalysis['summary']['pending_count'] + $assignAnalysis['summary']['in_progress_count'];

mysqli_close($conn);

sendResponse(true, 'Dashboard loaded', [
    'student' => [
        'name' => trim($student['first_name'] . ' ' . $student['last_name']),
        'enrollment_id' => $student['enrollment_id'],
        'email' => $student['email'],
        'phone' => $student['phone'],
        'department' => $student['department'],
        'program' => $student['program'],
        'semester' => $currentSem,
        'admission_year' => $student['admission_year'] ? (int) $student['admission_year'] : null,
        'profile_photo' => $student['profile_photo'],
        'profile_photo_url' => epGetStudentAvatarUrl(
            $student['profile_photo'],
            trim($student['first_name'] . ' ' . $student['last_name'])
        ),
    ],
    'stats' => [
        'attendance' => $overallAttendance['percentage'],
        'attendance_status' => $overallAttendance['status'],
        'total_classes' => $overallAttendance['total_classes'],
        'classes_attended' => $overallAttendance['classes_attended'],
        'cgpa' => $trends['cgpa'],
        'latest_sgpa' => $trends['latest_sgpa'],
        'latest_semester' => $trends['latest_semester'],
        'highest_sgpa' => $trends['highest_sgpa'],
        'highest_semester' => $trends['highest_semester'],
        'total_graded_credits' => $trends['total_graded_credits'],
        'total_courses' => (int) ($courseAgg['total_courses'] ?? 0),
        'total_credits' => (int) ($courseAgg['total_credits'] ?? 0),
        'pending_assignments' => $pendingTasksCount,
        'overdue_assignments' => $assignAnalysis['summary']['overdue_count'],
        'total_assignments' => $assignAnalysis['summary']['total_assigned'],
    ],
    'sgpa_trend' => $trends['semesters'],
    'grade_distribution' => $gradeDist,
    'recent_grades' => $recentGrades,
    'upcoming_assignments' => $upcomingAssignments,
    'alerts' => $alerts,
]);
?>
