<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/analytics_helpers.php';

$student_id = requireStudentApi();
header('Content-Type: application/json');

// 1. Student metadata
$studentRows = epFetchAll(
    $conn,
    "SELECT student_id, enrollment_id, first_name, last_name, department, program, current_semester, profile_photo
     FROM students
     WHERE student_id = ?",
    'i',
    [$student_id]
);
if (empty($studentRows)) {
    mysqli_close($conn);
    sendResponse(false, 'Student record not found', null, 404);
}
$student = $studentRows[0];
$currentSem = (int) ($student['current_semester'] ?? 1);

// 2. Student's own grades across all semesters
$gradeRows = epFetchAll(
    $conn,
    "SELECT g.semester, g.course_id, c.course_code, c.course_name, c.credits,
            g.internal_marks, g.external_marks, g.total_marks, g.grade, g.grade_points
     FROM grades g
     JOIN courses c ON g.course_id = c.course_id
     WHERE g.student_id = ?
     ORDER BY g.semester ASC, c.course_code ASC",
    'i',
    [$student_id]
);

// 3. Student's own attendance across all recorded semesters
$attendanceRows = epFetchAll(
    $conn,
    "SELECT a.semester, a.course_id, c.course_code, c.course_name, c.credits,
            a.total_classes, a.classes_attended, a.attendance_percentage
     FROM attendance a
     JOIN courses c ON a.course_id = c.course_id
     WHERE a.student_id = ?
     ORDER BY a.semester ASC, c.course_code ASC",
    'i',
    [$student_id]
);

// 4. Student's own assignments & submissions (enrolled courses only)
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
     ORDER BY a.due_date ASC",
    'ii',
    [$student_id, $student_id]
);

// 5. Anonymized Program-Scoped Cohort Benchmarks (excluding current student; minimum 3 peers required)
$benchmarks = epLoadCohortBenchmarks(
    $conn,
    $student_id,
    $student['program'] ?? null,
    $student['department'] ?? null
);
$cohortCourseBenchmarks = $benchmarks['course_benchmarks'];
$cohortSemAverages = $benchmarks['semester_benchmarks'];
$cohortAttBenchmarks = $benchmarks['attendance_benchmarks'];
$cohortScope = $benchmarks['scope'];

mysqli_close($conn);

// Compute analytics using shared pure helpers
$trends = epComputeSemesterAndCumulativeTrends($gradeRows, $cohortSemAverages);

$currentAttRows = array_values(array_filter(
    $attendanceRows,
    fn($r) => (int) $r['semester'] === $currentSem
));

$courseAnalysis = epAnalyzeCoursePerformance(
    $gradeRows,
    $cohortCourseBenchmarks,
    $currentAttRows,
    $cohortAttBenchmarks
);

$correlation = epAnalyzeAttendanceGradeCorrelation($gradeRows, $attendanceRows);
$assignments = epAnalyzeAssignments($assignmentRows);
$assignmentVsPerformance = epAnalyzeAssignmentsVsPerformance($gradeRows, $assignmentRows, $assignments);
$performanceFactors = epAnalyzePerformanceFactors(
    $trends,
    $courseAnalysis['graded_courses'],
    $courseAnalysis['attendance_courses'],
    $assignments,
    $cohortScope,
    $correlation
);

$strengthsWeaknesses = epIdentifyStrengthsAndWeaknesses(
    $courseAnalysis['graded_courses'],
    $courseAnalysis['attendance_courses'],
    $assignments
);

$recommendations = epGenerateExplainableRecommendations([
    'trends' => $trends,
    'graded_courses' => $courseAnalysis['graded_courses'],
    'attendance_courses' => $courseAnalysis['attendance_courses'],
    'assignments' => $assignments,
]);

// Current semester overall weighted attendance
$totalClasses = 0;
$totalAttended = 0;
foreach ($currentAttRows as $row) {
    $totalClasses += (int) ($row['total_classes'] ?? 0);
    $totalAttended += (int) ($row['classes_attended'] ?? 0);
}
$overallAttendance = epComputeAttendanceTargets($totalAttended, $totalClasses);

sendResponse(true, 'Performance analytics generated', [
    'student' => [
        'name' => trim($student['first_name'] . ' ' . $student['last_name']),
        'enrollment_id' => $student['enrollment_id'],
        'department' => $student['department'],
        'program' => $student['program'],
        'current_semester' => $currentSem,
        'profile_photo' => $student['profile_photo'],
        'profile_photo_url' => epGetStudentAvatarUrl(
            $student['profile_photo'],
            trim($student['first_name'] . ' ' . $student['last_name'])
        ),
    ],
    'overview' => [
        'cgpa' => $trends['cgpa'],
        'total_graded_credits' => $trends['total_graded_credits'],
        'latest_sgpa' => $trends['latest_sgpa'],
        'latest_semester' => $trends['latest_semester'],
        'highest_sgpa' => $trends['highest_sgpa'],
        'highest_semester' => $trends['highest_semester'],
        'trend' => $trends['trend'],
        'overall_attendance' => $overallAttendance,
    ],
    'sgpa_trend' => $trends['semesters'],
    'cohort_scope' => $cohortScope,
    'course_performance' => $courseAnalysis['graded_courses'],
    'attendance_performance' => $courseAnalysis['attendance_courses'],
    'attendance_grade_correlation' => $correlation,
    'assignment_vs_performance' => $assignmentVsPerformance,
    'performance_factors' => $performanceFactors,
    'strengths_weaknesses' => $strengthsWeaknesses,
    'recommendations' => $recommendations,
    'assignment_analytics' => [
        'has_data' => $assignments['has_data'],
        'summary' => $assignments['summary'],
        'timeliness' => $assignments['timeliness'],
        'by_course' => $assignments['by_course'],
    ],
]);
?>
