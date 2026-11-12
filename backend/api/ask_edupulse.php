<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/chatbot_nlp.php';
require_once '../includes/analytics_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Invalid request method');
}

$student_id = requireStudentApi();

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

// Allow UI clear-conversation action to reset server-side follow-up memory
if (is_array($payload) && (($payload['action'] ?? '') === 'clear')) {
    unset($_SESSION['edupulse_chat_ctx']);
    mysqli_close($conn);
    sendResponse(true, 'Conversation context cleared', ['cleared' => true]);
}

$messageRaw = is_array($payload) ? ($payload['message'] ?? '') : ($_POST['message'] ?? '');
$message = trim(strip_tags((string) $messageRaw));

if ($message === '') {
    http_response_code(400);
    sendResponse(false, 'Please enter a question for Ask EduPulse.');
}

if (mb_strlen($message) > 500) {
    $message = mb_substr($message, 0, 500);
}

/**
 * Execute a prepared SELECT query and return all matching rows.
 */
function fetchAllRows(mysqli $conn, string $sql, string $types, array $params): array {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Execute a prepared SELECT query and return the first row or null.
 */
function fetchSingleRow(mysqli $conn, string $sql, string $types, array $params): ?array {
    $rows = fetchAllRows($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

/**
 * Classify attendance percentage using EduPulse's dashboard thresholds.
 */
function getAttendanceStatusLabel(float $percentage): string {
    if ($percentage >= 85.0) {
        return 'Safe';
    }
    if ($percentage >= 75.0) {
        return 'Warning';
    }
    return 'Critical';
}

/**
 * Compute classes needed to reach a target ratio (e.g. 0.75 or 0.85).
 */
function classesNeededForTarget(int $attended, int $total, float $targetRatio): int {
    if ($total <= 0 || ($attended / $total) >= $targetRatio) {
        return 0;
    }
    return (int) max(0, ceil(($targetRatio * $total - $attended) / (1.0 - $targetRatio)));
}

/**
 * Compute maximum consecutive future absences while remaining at or above targetRatio.
 */
function safeAbsencesAllowed(int $attended, int $total, float $targetRatio): int {
    if ($total <= 0 || ($attended / $total) < $targetRatio) {
        return 0;
    }
    return (int) max(0, floor(($attended / $targetRatio) - $total));
}

/**
 * Load all academic data for the authenticated student from MySQL.
 */
function loadStudentAcademicContext(mysqli $conn, int $studentId): ?array {
    $student = fetchSingleRow(
        $conn,
        "SELECT student_id, enrollment_id, first_name, last_name, email, phone,
                department, program, current_semester, admission_year
         FROM students
         WHERE student_id = ?",
        'i',
        [$studentId]
    );

    if (!$student) {
        return null;
    }

    $currentSem = (int) ($student['current_semester'] ?? 1);

    $enrollments = fetchAllRows(
        $conn,
        "SELECT e.semester, c.course_id, c.course_code, c.course_name, c.credits,
                c.faculty_name, c.faculty_email, c.description, c.syllabus,
                a.total_classes, a.classes_attended, a.attendance_percentage
         FROM enrollments e
         JOIN courses c ON e.course_id = c.course_id
         LEFT JOIN attendance a
           ON a.course_id = c.course_id
          AND a.student_id = e.student_id
          AND a.semester = e.semester
         WHERE e.student_id = ?
         ORDER BY e.semester ASC, c.course_code ASC",
        'i',
        [$studentId]
    );

    $grades = fetchAllRows(
        $conn,
        "SELECT g.semester, c.course_id, c.course_code, c.course_name, c.credits,
                g.internal_marks, g.external_marks, g.total_marks, g.grade, g.grade_points
         FROM grades g
         JOIN courses c ON g.course_id = c.course_id
         WHERE g.student_id = ?
         ORDER BY g.semester ASC, c.course_code ASC",
        'i',
        [$studentId]
    );

    $attendanceAll = fetchAllRows(
        $conn,
        "SELECT a.semester, a.course_id, c.course_code, c.course_name, c.credits,
                a.total_classes, a.classes_attended, a.attendance_percentage
         FROM attendance a
         JOIN courses c ON a.course_id = c.course_id
         WHERE a.student_id = ?
         ORDER BY a.semester ASC, c.course_code ASC",
        'i',
        [$studentId]
    );

    $assignmentRows = fetchAllRows(
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
        [$studentId, $studentId]
    );

    $benchmarks = epLoadCohortBenchmarks(
        $conn,
        $studentId,
        $student['program'] ?? null,
        $student['department'] ?? null
    );
    $cohortCourseBenchmarks = $benchmarks['course_benchmarks'];
    $cohortSemBenchmarks = $benchmarks['semester_benchmarks'];
    $cohortAttBenchmarks = $benchmarks['attendance_benchmarks'];

    $activities = fetchAllRows(
        $conn,
        "SELECT activity_id, activity_type, activity_name, description, organization,
                start_date, end_date, status, achievement, points
         FROM extracurricular_activities
         WHERE student_id = ?
         ORDER BY CASE WHEN status = 'Ongoing' THEN 0 ELSE 1 END, start_date DESC",
        'i',
        [$studentId]
    );

    $currentCourses = [];
    $coursesBySemester = [];
    $coursesById = [];
    $totalClasses = 0;
    $totalAttended = 0;

    foreach ($enrollments as $row) {
        $sem = (int) $row['semester'];
        $coursesBySemester[$sem][] = $row;
        $coursesById[(int) $row['course_id']] = $row;
        if ($sem === $currentSem) {
            $currentCourses[] = $row;
            $totalClasses += (int) ($row['total_classes'] ?? 0);
            $totalAttended += (int) ($row['classes_attended'] ?? 0);
        }
    }

    $overallAttendance = $totalClasses > 0 ? round(($totalAttended / $totalClasses) * 100, 2) : 0.0;

    $gradesBySemester = [];
    $totalCredits = 0;
    $totalWeightedPoints = 0.0;

    foreach ($grades as $g) {
        $sem = (int) $g['semester'];
        if (!isset($gradesBySemester[$sem])) {
            $gradesBySemester[$sem] = [
                'courses' => [],
                'credits' => 0,
                'weighted_points' => 0.0,
                'sgpa' => 0.0
            ];
        }
        $gradesBySemester[$sem]['courses'][] = $g;
        if ($g['grade_points'] !== null) {
            $cr = (int) $g['credits'];
            $gp = (float) $g['grade_points'];
            $gradesBySemester[$sem]['credits'] += $cr;
            $gradesBySemester[$sem]['weighted_points'] += ($gp * $cr);
            $totalCredits += $cr;
            $totalWeightedPoints += ($gp * $cr);
        }
    }

    $highestSgpa = 0.0;
    $highestSgpaSem = null;
    $latestSgpa = null;
    $latestSgpaSem = null;

    foreach ($gradesBySemester as $sem => &$semInfo) {
        $semInfo['sgpa'] = $semInfo['credits'] > 0
            ? round($semInfo['weighted_points'] / $semInfo['credits'], 2)
            : 0.0;
        if ($semInfo['sgpa'] > 0) {
            if ($semInfo['sgpa'] > $highestSgpa) {
                $highestSgpa = $semInfo['sgpa'];
                $highestSgpaSem = $sem;
            }
            $latestSgpa = $semInfo['sgpa'];
            $latestSgpaSem = $sem;
        }
    }
    unset($semInfo);

    $cgpa = $totalCredits > 0 ? round($totalWeightedPoints / $totalCredits, 2) : 0.0;
    $totalActivityPoints = array_sum(array_map(fn($a) => (int) ($a['points'] ?? 0), $activities));

    $trends = epComputeSemesterAndCumulativeTrends($grades, $cohortSemBenchmarks);
    $currentAttRows = array_values(array_filter($attendanceAll, fn($r) => (int) $r['semester'] === $currentSem));
    $courseAnalysis = epAnalyzeCoursePerformance($grades, $cohortCourseBenchmarks, $currentAttRows, $cohortAttBenchmarks);
    $correlation = epAnalyzeAttendanceGradeCorrelation($grades, $attendanceAll);
    $assignmentAnalysis = epAnalyzeAssignments($assignmentRows);
    $strengthsWeaknesses = epIdentifyStrengthsAndWeaknesses(
        $courseAnalysis['graded_courses'],
        $courseAnalysis['attendance_courses'],
        $assignmentAnalysis
    );
    $recommendations = epGenerateExplainableRecommendations([
        'trends' => $trends,
        'graded_courses' => $courseAnalysis['graded_courses'],
        'attendance_courses' => $courseAnalysis['attendance_courses'],
        'assignments' => $assignmentAnalysis,
    ]);

    return [
        'student' => $student,
        'full_name' => trim($student['first_name'] . ' ' . $student['last_name']),
        'current_semester' => $currentSem,
        'enrollments' => $enrollments,
        'courses_by_id' => $coursesById,
        'courses_by_semester' => $coursesBySemester,
        'current_courses' => $currentCourses,
        'attendance' => [
            'total_classes' => $totalClasses,
            'classes_attended' => $totalAttended,
            'overall_percentage' => $overallAttendance,
            'status' => getAttendanceStatusLabel($overallAttendance)
        ],
        'grades' => $grades,
        'grades_by_semester' => $gradesBySemester,
        'cgpa' => $cgpa,
        'total_graded_credits' => $totalCredits,
        'latest_sgpa' => $latestSgpa,
        'latest_sgpa_sem' => $latestSgpaSem,
        'highest_sgpa' => $highestSgpa,
        'highest_sgpa_sem' => $highestSgpaSem,
        'activities' => $activities,
        'total_activity_points' => $totalActivityPoints,
        'analytics' => [
            'trends' => $trends,
            'cohort_scope' => $benchmarks['scope'],
            'correlation' => $correlation,
            'graded_courses' => $courseAnalysis['graded_courses'],
            'assignments' => $assignmentAnalysis,
            'strengths_weaknesses' => $strengthsWeaknesses,
            'recommendations' => $recommendations,
        ]
    ];
}

/**
 * Generate personalized quick-reply suggestions from live student DB data.
 */
function buildPersonalizedSuggestions(array $ctx, string $intent, ?array $activeCourse = null): array {
    $suggestions = [];

    // Highlight an active low-attendance course if one exists
    $lowestCourse = null;
    $lowestPct = 101.0;
    foreach ($ctx['current_courses'] as $c) {
        if ($c['total_classes'] !== null && (int) $c['total_classes'] > 0) {
            $pct = (float) $c['attendance_percentage'];
            if ($pct < $lowestPct) {
                $lowestPct = $pct;
                $lowestCourse = $c;
            }
        }
    }

    if ($activeCourse !== null) {
        $code = $activeCourse['course_code'];
        if ($intent !== 'attendance_overview' && $intent !== 'attendance_eligibility') {
            $suggestions[] = "What is my attendance in {$code}?";
        }
        $suggestions[] = "Can I miss 2 classes in {$code}?";
        if (!empty($activeCourse['syllabus'])) {
            $suggestions[] = "Show {$code} syllabus";
        }
        if (!empty($activeCourse['faculty_name'])) {
            $suggestions[] = "Who teaches {$code}?";
        }
    }

    // Check for declining SGPA trend between last two graded semesters
    $sgpaTrendDeclined = false;
    $gradedSemKeys = array_keys(array_filter($ctx['grades_by_semester'], fn($s) => ($s['sgpa'] ?? 0) > 0));
    sort($gradedSemKeys);
    if (count($gradedSemKeys) >= 2) {
        $lastKey = $gradedSemKeys[count($gradedSemKeys) - 1];
        $prevKey = $gradedSemKeys[count($gradedSemKeys) - 2];
        if ((float) $ctx['grades_by_semester'][$lastKey]['sgpa'] < (float) $ctx['grades_by_semester'][$prevKey]['sgpa']) {
            $sgpaTrendDeclined = true;
        }
    }

    if ($lowestCourse !== null && $lowestPct < 85.0 && $intent !== 'attendance_eligibility') {
        $suggestions[] = "How to recover {$lowestCourse['course_code']} attendance?";
    } elseif ($intent !== 'attendance_eligibility' && $intent !== 'attendance_overview') {
        $suggestions[] = 'How many classes can I miss safely?';
    }

    if ($sgpaTrendDeclined && $intent !== 'cgpa_sgpa') {
        $suggestions[] = 'Compare my semester SGPA trend';
    } elseif ($intent !== 'cgpa_sgpa' && $intent !== 'grades_marks') {
        $suggestions[] = 'What is my CGPA and SGPA trend?';
    } elseif ($ctx['latest_sgpa_sem'] !== null) {
        $suggestions[] = "Show Semester {$ctx['latest_sgpa_sem']} grades";
    }

    $ongoingCount = count(array_filter($ctx['activities'], fn($a) => $a['status'] === 'Ongoing'));
    if ($ongoingCount > 0 && $intent !== 'extracurricular') {
        $suggestions[] = 'Show my ongoing activities';
    } elseif ($intent !== 'courses_faculty') {
        $suggestions[] = 'Show my current courses';
    }

    if ($intent !== 'analytics_insights') {
        $suggestions[] = 'Show my strengths & recommendations';
    } elseif ($intent !== 'academic_performance') {
        $suggestions[] = 'Give me my academic summary';
    }

    return array_slice(array_values(array_unique($suggestions)), 0, 4);
}

/**
 * Build a detailed response for a single course (with optional what-if attendance math).
 */
function buildCourseDetailReply(array $course, array $ctx, array $entities, string $intent): array {
    $courseId = (int) $course['course_id'];
    $code = $course['course_code'];
    $name = $course['course_name'];
    $sem = (int) $course['semester'];

    $lines = ["**{$code} — {$name}** (Semester {$sem}, {$course['credits']} Credits)"];

    if (!empty($course['faculty_name'])) {
        $emailPart = !empty($course['faculty_email']) ? " ({$course['faculty_email']})" : '';
        $lines[] = "• **Faculty:** {$course['faculty_name']}{$emailPart}";
    }

    if ($course['total_classes'] !== null && (int) $course['total_classes'] > 0) {
        $attPct = (float) $course['attendance_percentage'];
        $attended = (int) $course['classes_attended'];
        $total = (int) $course['total_classes'];
        $status = getAttendanceStatusLabel($attPct);
        $canMiss = safeAbsencesAllowed($attended, $total, 0.75);
        $need85 = classesNeededForTarget($attended, $total, 0.85);
        $need75 = classesNeededForTarget($attended, $total, 0.75);

        $attNote = $attPct < 75.0
            ? "Need **{$need75}** consecutive classes to reach 75%."
            : ($attPct < 85.0
                ? "Need **{$need85}** consecutive classes for Safe (85%); can miss **{$canMiss}** and stay ≥75%."
                : "Safe! You can miss up to **{$canMiss}** classes and still remain ≥75%.");
        $lines[] = "• **Attendance:** {$attended}/{$total} classes (**" . number_format($attPct, 2) . "%** — {$status}). {$attNote}";

        if ($entities['miss_count'] !== null) {
            $m = $entities['miss_count'];
            $newPct = round(($attended / ($total + $m)) * 100, 2);
            $newStatus = getAttendanceStatusLabel($newPct);
            $lines[] = "• **What-if (miss {$m} classes):** Attendance would become {$attended}/" . ($total + $m) . " (**" . number_format($newPct, 2) . "%** — **{$newStatus}**).";
        }

        if ($entities['target_attendance'] !== null) {
            $tPct = $entities['target_attendance'];
            $needed = classesNeededForTarget($attended, $total, $tPct / 100.0);
            $lines[] = $needed === 0
                ? "• **Target ({$tPct}%):** Already met!"
                : "• **Target ({$tPct}%):** Attend the next **{$needed}** consecutive classes.";
        }
    }

    foreach ($ctx['grades'] as $g) {
        if ((int) $g['course_id'] === $courseId && (int) $g['semester'] === $sem) {
            $lines[] = "• **Grade:** **{$g['grade']}** ({$g['grade_points']} GP) — Total **{$g['total_marks']}/100** (Internal: {$g['internal_marks']}, External: {$g['external_marks']})";
            break;
        }
    }

    $courseAssignments = array_values(array_filter(
        $ctx['analytics']['assignments']['items'] ?? [],
        fn($a) => (int) $a['course_id'] === $courseId && (int) $a['semester'] === $sem
    ));
    if (!empty($courseAssignments)) {
        $lines[] = "• **Assignments (" . count($courseAssignments) . "):**";
        foreach (array_slice($courseAssignments, 0, 3) as $ca) {
            $dueLabel = date('M j, Y', strtotime($ca['due_date']));
            $scorePart = $ca['marks_obtained'] !== null ? " — Score: **{$ca['marks_obtained']}/{$ca['max_marks']}**" : '';
            $lines[] = "  - **{$ca['title']}** (" . ucfirst(str_replace('_', ' ', $ca['status'])) . ", Due: {$dueLabel}){$scorePart}";
        }
    }

    if (!empty($course['description'])) {
        $lines[] = "• **Overview:** {$course['description']}";
    }
    if (!empty($course['syllabus'])) {
        $lines[] = "• **Syllabus:** {$course['syllabus']}";
    } elseif ($intent === 'courses_faculty') {
        $lines[] = "• **Syllabus:** Detailed syllabus units have not been uploaded for Semester {$sem} yet.";
    }

    return [
        'intent' => 'course_detail',
        'reply' => implode("\n", $lines),
        'active_course' => $course,
        'active_semester' => $sem
    ];
}

/**
 * Build overall and eligibility-focused attendance response.
 */
function buildAttendanceReply(array $ctx, array $entities, bool $focusEligibility): array {
    $att = $ctx['attendance'];
    $courses = $ctx['current_courses'];
    $sem = $ctx['current_semester'];

    if (empty($courses)) {
        return [
            'intent' => $focusEligibility ? 'attendance_eligibility' : 'attendance_overview',
            'reply' => "I couldn't find attendance records for Semester {$sem} yet."
        ];
    }

    $lines = [
        "**Semester {$sem} Attendance Overview**",
        "• **Overall:** {$att['classes_attended']}/{$att['total_classes']} classes (**" . number_format($att['overall_percentage'], 2) . "%** — **{$att['status']}**)"
    ];

    $targetPct = $entities['target_attendance'];
    $missCount = $entities['miss_count'];
    $atRisk = [];
    $safeList = [];

    foreach ($courses as $c) {
        if ($c['total_classes'] === null || (int) $c['total_classes'] === 0) {
            continue;
        }
        $pct = (float) $c['attendance_percentage'];
        $attended = (int) $c['classes_attended'];
        $total = (int) $c['total_classes'];
        $status = getAttendanceStatusLabel($pct);

        $extra = '';
        if ($missCount !== null) {
            $proj = round(($attended / ($total + $missCount)) * 100, 2);
            $extra = " (if miss {$missCount}: **" . number_format($proj, 2) . "%** — " . getAttendanceStatusLabel($proj) . ")";
        } elseif ($targetPct !== null) {
            $needCustom = classesNeededForTarget($attended, $total, $targetPct / 100.0);
            $extra = $needCustom === 0 ? " (already ≥{$targetPct}%)" : " (need **{$needCustom}** for {$targetPct}%)";
        }

        if ($pct < 85.0) {
            $need = $pct < 75.0
                ? 'need **' . classesNeededForTarget($attended, $total, 0.75) . '** for 75%'
                : 'need **' . classesNeededForTarget($attended, $total, 0.85) . '** for 85%';
            $atRisk[] = "  - **{$c['course_code']} ({$c['course_name']}):** {$attended}/{$total} (**" . number_format($pct, 2) . "%**, {$status} — {$need}){$extra}";
        } else {
            $miss = safeAbsencesAllowed($attended, $total, 0.75);
            $safeList[] = "  - **{$c['course_code']} ({$c['course_name']}):** {$attended}/{$total} (**" . number_format($pct, 2) . "%**) — can miss up to **{$miss}** classes (≥75%){$extra}";
        }
    }

    if ($focusEligibility) {
        if (empty($atRisk)) {
            $lines[] = "\nYou are **eligible across all Semester {$sem} courses** — none are below 85%!";
            if (!empty($safeList)) {
                $lines[] = "\n**Safe Buffers:**\n" . implode("\n", $safeList);
            }
        } else {
            $lines[] = "\n**Courses Needing Attention (< 85%):**\n" . implode("\n", $atRisk);
            if (!empty($safeList)) {
                $lines[] = "\n**Eligible & Safe (≥ 85%):**\n" . implode("\n", $safeList);
            }
        }
    } else {
        if (!empty($atRisk)) {
            $lines[] = "\n**Attention (< 85%):**\n" . implode("\n", $atRisk);
        }
        if (!empty($safeList)) {
            $lines[] = "\n**Safe Courses (≥ 85%):**\n" . implode("\n", $safeList);
        }
    }

    $lines[] = "\n_Policy thresholds: ≥85% Safe · 75–84% Warning · <75% Critical._";

    return [
        'intent' => $focusEligibility ? 'attendance_eligibility' : 'attendance_overview',
        'reply' => implode("\n", $lines),
        'active_semester' => $sem
    ];
}

/**
 * Build CGPA, SGPA, and grades/marks response.
 */
function buildGradesReply(array $ctx, array $entities, bool $focusMarks): array {
    $gradesBySem = $ctx['grades_by_semester'];
    if (empty($gradesBySem)) {
        return [
            'intent' => $focusMarks ? 'grades_marks' : 'cgpa_sgpa',
            'reply' => 'No published grade records were found for your account yet.'
        ];
    }

    $reqSem = $entities['semester'];

    if ($reqSem !== null) {
        if (!isset($gradesBySem[$reqSem])) {
            $available = implode(', ', array_map(fn($s) => "Sem {$s}", array_keys($gradesBySem)));
            return [
                'intent' => 'grades_marks',
                'reply' => "No grades are recorded for **Semester {$reqSem}** yet. Graded semesters available: {$available}."
            ];
        }
        $semData = $gradesBySem[$reqSem];
        $sgpaFmt = number_format((float) $semData['sgpa'], 2);
        $lines = ["**Semester {$reqSem} Grades — SGPA: {$sgpaFmt}** ({$semData['credits']} Credits)"];
        foreach ($semData['courses'] as $c) {
            $lines[] = "• **{$c['course_code']} ({$c['course_name']}):** Grade **{$c['grade']}** ({$c['grade_points']} GP) — {$c['total_marks']}/100 (Int: {$c['internal_marks']}, Ext: {$c['external_marks']})";
        }
        return [
            'intent' => 'grades_marks',
            'reply' => implode("\n", $lines),
            'active_semester' => $reqSem
        ];
    }

    $cgpaFmt = number_format((float) $ctx['cgpa'], 2);
    $lines = [
        "**Academic Performance & GPA Summary**",
        "• **Overall CGPA:** **{$cgpaFmt}** (across {$ctx['total_graded_credits']} graded credits)"
    ];

    if ($ctx['latest_sgpa'] !== null) {
        $lines[] = "• **Latest SGPA (Sem {$ctx['latest_sgpa_sem']}):** **" . number_format((float) $ctx['latest_sgpa'], 2) . "**";
    }
    if ($ctx['highest_sgpa'] > 0) {
        $lines[] = "• **Highest SGPA (Sem {$ctx['highest_sgpa_sem']}):** **" . number_format((float) $ctx['highest_sgpa'], 2) . "**";
    }

    $semSummary = [];
    foreach ($gradesBySem as $sem => $info) {
        $semSummary[] = "Sem {$sem}: **" . number_format((float) $info['sgpa'], 2) . "**";
    }
    $lines[] = "• **Semester Trend:** " . implode('  |  ', $semSummary);

    $sortedGrades = $ctx['grades'];
    usort($sortedGrades, fn($a, $b) => ($b['total_marks'] <=> $a['total_marks']));
    $top = $sortedGrades[0] ?? null;
    if ($top) {
        $lines[] = "• **Top Subject:** {$top['course_code']} ({$top['course_name']}) — **{$top['total_marks']}/100** (Grade {$top['grade']}) in Sem {$top['semester']}";
    }

    if ($focusMarks && $ctx['latest_sgpa_sem'] !== null) {
        $latestSem = $ctx['latest_sgpa_sem'];
        $lines[] = "\n**Latest Graded Semester (Sem {$latestSem}):**";
        foreach ($gradesBySem[$latestSem]['courses'] as $c) {
            $lines[] = "  - **{$c['course_code']} ({$c['course_name']}):** {$c['total_marks']}/100 (Int: {$c['internal_marks']}, Ext: {$c['external_marks']}) — Grade **{$c['grade']}**";
        }
    }

    if ($entities['target_sgpa'] !== null) {
        $targetSgpa = $entities['target_sgpa'];
        $currSemCredits = array_sum(array_map(fn($c) => (int) $c['credits'], $ctx['current_courses']));
        if ($currSemCredits > 0 && $ctx['total_graded_credits'] > 0) {
            $newCgpa = (($ctx['cgpa'] * $ctx['total_graded_credits']) + ($targetSgpa * $currSemCredits)) / ($ctx['total_graded_credits'] + $currSemCredits);
            $lines[] = "\n**CGPA Projection:** With **{$currSemCredits} credits** in Semester {$ctx['current_semester']}, scoring **" . number_format($targetSgpa, 2) . " SGPA** would bring your CGPA to **" . number_format($newCgpa, 2) . "**.";
        }
    } else {
        $lines[] = "\n_Ask for any semester (e.g., \"Show Semester 3 grades\") or a what-if target (e.g., \"If I score 9.5 SGPA next sem?\")._";
    }

    return [
        'intent' => $focusMarks ? 'grades_marks' : 'cgpa_sgpa',
        'reply' => implode("\n", $lines),
        'active_semester' => $ctx['latest_sgpa_sem']
    ];
}

/**
 * Build enrolled courses, faculty lookup, and syllabus response.
 */
function buildCoursesReply(array $ctx, array $entities, string $normalizedQuery): array {
    if (!empty($entities['faculty_courses'])) {
        $list = $entities['faculty_courses'];
        $facName = $list[0]['faculty_name'];
        $facEmail = !empty($list[0]['faculty_email']) ? " ({$list[0]['faculty_email']})" : '';
        $lines = ["**Courses Taught by {$facName}{$facEmail}**"];
        foreach ($list as $c) {
            $lines[] = "• **{$c['course_code']} — {$c['course_name']}** (Semester {$c['semester']}, {$c['credits']} Cr)";
        }
        return [
            'intent' => 'courses_faculty',
            'reply' => implode("\n", $lines),
            'active_course' => count($list) === 1 ? $list[0] : null
        ];
    }

    $reqSem = $entities['semester'] ?? $ctx['current_semester'];
    $courses = $ctx['courses_by_semester'][$reqSem] ?? [];

    if (empty($courses)) {
        return [
            'intent' => 'courses_faculty',
            'reply' => "No enrolled courses were found for **Semester {$reqSem}**."
        ];
    }

    $totalCredits = array_sum(array_map(fn($c) => (int) $c['credits'], $courses));
    $lines = ["**Semester {$reqSem} Enrolled Courses** (" . count($courses) . " courses, **{$totalCredits} total credits**)"];
    $wantsSyllabus = str_contains($normalizedQuery, 'syllabus') || str_contains($normalizedQuery, 'description');

    foreach ($courses as $c) {
        $faculty = !empty($c['faculty_name']) ? " — {$c['faculty_name']}" : '';
        $extra = ($wantsSyllabus && !empty($c['syllabus'])) ? "\n  _Syllabus:_ {$c['syllabus']}" : '';
        $lines[] = "• **{$c['course_code']} — {$c['course_name']}** ({$c['credits']} Cr){$faculty}{$extra}";
    }

    $lines[] = "\n_Ask about any subject by code, name, or faculty (e.g., \"Who teaches DBMS?\" or \"CS301 syllabus\") for details._";

    return [
        'intent' => 'courses_faculty',
        'reply' => implode("\n", $lines),
        'active_semester' => $reqSem
    ];
}

/**
 * Build extracurricular activities response.
 */
function buildActivitiesReply(array $ctx, string $normalizedQuery): array {
    $activities = $ctx['activities'];
    if (empty($activities)) {
        return [
            'intent' => 'extracurricular',
            'reply' => "You don't have any extracurricular activities recorded yet."
        ];
    }

    $ongoingOnly = str_contains($normalizedQuery, 'ongoing');
    $list = $ongoingOnly
        ? array_values(array_filter($activities, fn($a) => $a['status'] === 'Ongoing'))
        : $activities;

    $ongoingCount = count(array_filter($activities, fn($a) => $a['status'] === 'Ongoing'));
    $lines = [
        "**Extracurricular Activities Summary**",
        "• **Total Activities:** " . count($activities) . " ({$ongoingCount} Ongoing)  |  **Total Points:** **{$ctx['total_activity_points']} pts**"
    ];

    if ($ongoingOnly && empty($list)) {
        $lines[] = "\nYou have **no ongoing** activities right now — all " . count($activities) . " recorded activities are marked Completed.";
    } else {
        foreach (array_slice($list, 0, 5) as $a) {
            $ach = !empty($a['achievement']) ? " — _{$a['achievement']}_" : '';
            $lines[] = "• **[{$a['activity_type']}] {$a['activity_name']}** ({$a['points']} pts, {$a['status']}){$ach}";
        }
        if (count($list) > 5) {
            $lines[] = "_Showing 5 of " . count($list) . " activities. Visit the Activities page to view all._";
        }
    }

    return [
        'intent' => 'extracurricular',
        'reply' => implode("\n", $lines)
    ];
}

/**
 * Build student profile summary response.
 */
function buildProfileReply(array $ctx): array {
    $s = $ctx['student'];
    $lines = [
        "**Student Profile Summary**",
        "• **Name:** {$ctx['full_name']}",
        "• **Enrollment ID:** {$s['enrollment_id']}",
        "• **Program & Department:** " . ($s['program'] ?: 'N/A') . " — " . ($s['department'] ?: 'N/A'),
        "• **Current Semester:** Semester {$ctx['current_semester']} (Admission Year: " . ($s['admission_year'] ?: 'N/A') . ")",
        "• **Email:** {$s['email']}" . (!empty($s['phone']) ? "  |  **Phone:** {$s['phone']}" : '')
    ];

    return [
        'intent' => 'profile_semester',
        'reply' => implode("\n", $lines),
        'active_semester' => $ctx['current_semester']
    ];
}

/**
 * Build overall academic snapshot response.
 */
function buildSummaryReply(array $ctx): array {
    $s = $ctx['student'];
    $att = $ctx['attendance'];
    $courseCount = count($ctx['current_courses']);
    $totalCredits = array_sum(array_map(fn($c) => (int) $c['credits'], $ctx['current_courses']));
    $cgpaFmt = number_format((float) $ctx['cgpa'], 2);
    $latestPart = $ctx['latest_sgpa'] !== null
        ? " (Latest Sem {$ctx['latest_sgpa_sem']} SGPA: **" . number_format((float) $ctx['latest_sgpa'], 2) . "**)"
        : '';

    $lines = [
        "**Academic Snapshot for {$ctx['full_name']}** ({$s['enrollment_id']})",
        "• **Program:** {$s['program']} ({$s['department']}) — **Semester {$ctx['current_semester']}**",
        "• **CGPA:** **{$cgpaFmt}**" . $latestPart,
        "• **Current Attendance:** **" . number_format($att['overall_percentage'], 2) . "%** ({$att['classes_attended']}/{$att['total_classes']} classes — **{$att['status']}**)",
        "• **Enrolled Courses:** {$courseCount} courses ({$totalCredits} credits) this semester",
        "• **Extracurriculars:** " . count($ctx['activities']) . " activities (**{$ctx['total_activity_points']} pts**)"
    ];

    return [
        'intent' => 'academic_performance',
        'reply' => implode("\n", $lines),
        'active_semester' => $ctx['current_semester']
    ];
}

/**
 * Build explainable strengths, weaknesses, peer benchmark, and recommendations reply.
 */
function buildAnalyticsInsightsReply(array $ctx): array {
    $an = $ctx['analytics'] ?? [];
    $sw = $an['strengths_weaknesses'] ?? ['strengths' => [], 'weaknesses' => []];
    $recs = $an['recommendations'] ?? [];
    $trend = $an['trends']['trend'] ?? [];
    $scope = $an['cohort_scope'] ?? [];
    $corr = $an['correlation'] ?? [];

    $lines = ['**Explainable Performance Insights**'];
    if (!empty($trend['label'])) {
        $deltaStr = isset($trend['delta_latest']) && $trend['delta_latest'] !== null
            ? sprintf(' (Latest ΔSGPA: %+.2f)', $trend['delta_latest'])
            : '';
        $lines[] = "• **Academic Trajectory:** **{$trend['label']}**{$deltaStr} — Band: **{$trend['performance_band']}**";
    }

    // Cohort benchmark summary (strictly program-scoped, minimum 3 peers)
    $semList = $an['trends']['semesters'] ?? [];
    $benchSems = array_values(array_filter($semList, fn($s) => !empty($s['benchmark_available'])));
    if (!empty($benchSems)) {
        $latestB = $benchSems[count($benchSems) - 1];
        $diffStr = $latestB['sgpa_vs_class'] !== null ? sprintf('%+.2f', $latestB['sgpa_vs_class']) : '0.00';
        $lines[] = "• **Cohort Comparison ({$scope['label']}, n={$latestB['cohort_size']} peers):** Sem {$latestB['semester']} SGPA **{$latestB['sgpa']}** vs. cohort avg **{$latestB['class_avg_sgpa']}** ({$diffStr})";
    } elseif (!empty($scope['label'])) {
        $lines[] = "• **Cohort Comparison ({$scope['label']}):** Unavailable — at least 3 other students in your program are required for anonymized cohort averages (found " . (int) ($scope['eligible_peer_count'] ?? 0) . ").";
    }

    if (!empty($corr['status'])) {
        $lines[] = "• **Attendance–Grade Correlation:** " . ($corr['status'] === 'ok'
            ? "r = {$corr['pearson_r']} ({$corr['strength']} {$corr['direction']}, n={$corr['paired_count']})"
            : ($corr['note'] ?? 'Insufficient overlapping course-semester records.'));
    }

    if (!empty($sw['strengths'])) {
        $lines[] = "\n**Academic Strengths:**";
        foreach (array_slice($sw['strengths'], 0, 3) as $s) {
            $lines[] = "  - **{$s['title']}:** {$s['evidence']}";
        }
    }

    if (!empty($sw['weaknesses'])) {
        $lines[] = "\n**Focus Areas / Weaknesses:**";
        foreach (array_slice($sw['weaknesses'], 0, 3) as $w) {
            $lines[] = "  - **{$w['title']}:** {$w['evidence']}";
        }
    } else {
        $lines[] = "\n**Focus Areas:** No critical academic or attendance weaknesses flagged right now.";
    }

    if (!empty($recs)) {
        $lines[] = "\n**Top Recommendations:**";
        foreach (array_slice($recs, 0, 2) as $r) {
            $lines[] = "  - **[" . strtoupper($r['priority']) . "] {$r['title']}:** {$r['summary']}";
        }
    }

    $lines[] = "\n_Open **Performance Analytics** (`analytics.html`) for charts, peer benchmarks, and full explanations._";

    return [
        'intent' => 'analytics_insights',
        'reply' => implode("\n", $lines),
        'active_semester' => $ctx['current_semester']
    ];
}

/**
 * Build real assignments and timeliness reply from database records.
 */
function buildAssignmentsReply(array $ctx): array {
    $assign = $ctx['analytics']['assignments'] ?? ['has_data' => false];
    if (empty($assign['has_data'])) {
        return [
            'intent' => 'assignments_gap',
            'reply' => "You currently have **no assignments** recorded in the EduPulse database for your enrolled courses. Once instructors publish assignments, your deadlines and timeliness insights will appear here and on `assignments.html`."
        ];
    }

    $sum = $assign['summary'];
    $tl = $assign['timeliness'];
    $lines = [
        "**Assignments & Timeliness Overview**",
        "• **Total:** {$sum['total_assigned']} | **Pending:** {$sum['pending_count']} | **In Progress:** {$sum['in_progress_count']} | **Completed:** {$sum['completed_count']} | **Overdue:** {$sum['overdue_count']}"
    ];
    if (!empty($tl['has_submissions'])) {
        $lines[] = "• **Timeliness:** **{$tl['on_time_rate']}%** on-time ({$tl['on_time_count']} on-time, {$tl['late_count']} late)";
    }

    $upcoming = array_values(array_filter(
        $assign['items'],
        fn($i) => !in_array($i['status'], ['submitted', 'graded'], true)
    ));
    if (!empty($upcoming)) {
        $lines[] = "\n**Active Tasks:**";
        foreach (array_slice($upcoming, 0, 4) as $u) {
            $dueLabel = date('M j, Y', strtotime($u['due_date']));
            $flag = $u['is_overdue'] ? ' _(Overdue)_' : '';
            $lines[] = "  - **{$u['course_code']} — {$u['title']}** (Due: {$dueLabel}){$flag}";
        }
    }

    $graded = array_values(array_filter(
        $assign['items'],
        fn($i) => $i['status'] === 'graded'
    ));
    if (!empty($graded)) {
        $lines[] = "\n**Graded Results:**";
        foreach (array_slice($graded, 0, 3) as $g) {
            $fb = !empty($g['feedback']) ? " — _\"{$g['feedback']}\"_" : '';
            $lines[] = "  - **{$g['course_code']} — {$g['title']}:** **{$g['marks_obtained']}/{$g['max_marks']}**{$fb}";
        }
    }

    $lines[] = "\n_Manage progress or submit work on the **Assignments** page (`assignments.html`)._";

    return [
        'intent' => 'assignments_gap',
        'reply' => implode("\n", $lines)
    ];
}

/**
 * Dispatch a single classified academic intent to its response builder.
 */
function dispatchSingleIntent(string $intent, array $ctx, array $entities, string $normalized, string $firstName): array {
    return match ($intent) {
        'attendance_eligibility' => buildAttendanceReply($ctx, $entities, true),
        'attendance_overview' => buildAttendanceReply($ctx, $entities, false),
        'cgpa_sgpa' => buildGradesReply($ctx, $entities, false),
        'grades_marks' => buildGradesReply($ctx, $entities, true),
        'courses_faculty' => buildCoursesReply($ctx, $entities, $normalized),
        'extracurricular' => buildActivitiesReply($ctx, $normalized),
        'profile_semester' => buildProfileReply($ctx),
        'academic_performance' => buildSummaryReply($ctx),
        'analytics_insights' => buildAnalyticsInsightsReply($ctx),
        'assignments_gap' => buildAssignmentsReply($ctx),
        default => [
            'intent' => 'unknown',
            'reply' => "I'm not sure I understood that question, {$firstName}. Could you clarify? You can ask about your **attendance**, **CGPA/grades**, **strengths & recommendations**, **courses & faculty**, **assignments**, or **extracurricular activities**."
        ]
    };
}

/**
 * Route the student's natural-language question using NLP classification, entities, and session context.
 */
function routeAcademicQuery(string $message, array $ctx): array {
    $studentId = (int) $ctx['student']['student_id'];
    $firstName = $ctx['student']['first_name'];
    $ownEnrollment = strtoupper((string) ($ctx['student']['enrollment_id'] ?? ''));

    // Privacy guard: block requests asking about another student's ID or private records
    if (preg_match('/\b(stu\d{3,})\b/i', $message, $mId)) {
        $reqId = strtoupper($mId[1]);
        if ($reqId !== $ownEnrollment) {
            return [
                'intent' => 'privacy_guard',
                'intents' => ['privacy_guard'],
                'reply' => "For student privacy, I can only access **your own** academic records (`{$ownEnrollment}`). I cannot view or share individual grades, attendance, or submissions for `{$reqId}`.",
                'suggestions' => buildPersonalizedSuggestions($ctx, 'analytics_insights')
            ];
        }
    }
    if (preg_match('/\b(other\s+student|another\s+student|classmate|friend|someone\s+else)(?:\'?s)?\s+(?:grade|grades|marks|cgpa|sgpa|attendance|submission|profile)/i', $message)) {
        return [
            'intent' => 'privacy_guard',
            'intents' => ['privacy_guard'],
            'reply' => "For privacy protection, I cannot share individual academic records of other students. You can ask **\"How do I compare to the cohort average?\"** to view anonymized program cohort averages when at least 3 peers are available.",
            'suggestions' => buildPersonalizedSuggestions($ctx, 'analytics_insights')
        ];
    }

    $normalized = normalizeAcademicText($message);
    $tokens = tokenizeAcademicText($normalized);
    $entities = extractAcademicEntities($normalized, $tokens, $ctx);
    $sessionCtx = getChatSessionContext($studentId);
    $classification = classifyAcademicIntent($normalized, $tokens);

    $intent = $classification['intent'];
    $confidence = $classification['confidence'];

    // Resolve contextual follow-ups (e.g. "what is my attendance in it?", "can i miss two classes in that subject?")
    $isPluralOrSemQuery = (bool) preg_match('/\b(courses|subjects|all|semesters)\b/', $normalized) || $entities['semester'] !== null;
    $resolvedCourse = $entities['course'];
    if ($resolvedCourse === null && !$isPluralOrSemQuery && $entities['is_follow_up'] && !empty($sessionCtx['last_course_id'])) {
        $prevCourse = $ctx['courses_by_id'][(int) $sessionCtx['last_course_id']] ?? null;
        if ($prevCourse !== null && in_array($intent, ['attendance_overview', 'attendance_eligibility', 'courses_faculty', 'grades_marks', 'assignments_gap', 'unknown'], true)) {
            $resolvedCourse = $prevCourse;
        }
    }

    $resolvedSemester = $entities['semester'];
    if ($resolvedSemester === null && $entities['is_follow_up'] && !empty($sessionCtx['last_semester'])) {
        if (in_array($intent, ['grades_marks', 'courses_faculty'], true) && !preg_match('/\b(overall|cgpa|trend|all)\b/', $normalized)) {
            $resolvedSemester = (int) $sessionCtx['last_semester'];
            $entities['semester'] = $resolvedSemester;
        }
    }

    // 1. Greeting
    if ($intent === 'greeting' && $resolvedCourse === null && $confidence >= 0.35) {
        return [
            'intent' => 'greeting',
            'intents' => ['greeting'],
            'reply' => "Hi **{$firstName}**! I'm **Ask EduPulse**, your academic assistant for Semester {$ctx['current_semester']}.\n\nAsk me naturally about:\n• **Attendance & Eligibility:** overall status, risky subjects, safe absences, what-if math\n• **CGPA, SGPA & Grades:** semester marks, GPA trends, target projections\n• **Analytics & Recommendations:** strengths, weaknesses, cohort averages\n• **Assignments & Deadlines:** pending tasks, overdue status, graded feedback\n• **Courses, Faculty & Activities:** enrolled subjects, instructors, points",
            'suggestions' => buildPersonalizedSuggestions($ctx, 'greeting')
        ];
    }

    // 2. Specific course (explicitly mentioned or resolved from follow-up)
    if ($resolvedCourse !== null) {
        $res = buildCourseDetailReply($resolvedCourse, $ctx, $entities, $intent);
        saveChatSessionContext($studentId, $res['intent'], $res['active_course'], $res['active_semester']);
        $res['intents'] = [$res['intent']];
        $res['suggestions'] = buildPersonalizedSuggestions($ctx, $res['intent'], $res['active_course']);
        return $res;
    }

    // 3. Faculty reverse lookup (e.g., "What does Sarah Connor teach?")
    if (!empty($entities['faculty_courses'])) {
        $res = buildCoursesReply($ctx, $entities, $normalized);
        saveChatSessionContext($studentId, $res['intent'], $res['active_course'] ?? null, $ctx['current_semester']);
        $res['intents'] = [$res['intent']];
        $res['suggestions'] = buildPersonalizedSuggestions($ctx, $res['intent'], $res['active_course'] ?? null);
        return $res;
    }

    // 4. Confident single- or multi-intent detection with confidence thresholding
    $confidentIntents = detectConfidentIntents($classification, $normalized);
    if (empty($confidentIntents)) {
        return [
            'intent' => 'unknown',
            'intents' => ['unknown'],
            'reply' => "I'm not sure what you'd like me to check, {$firstName} — could you clarify? I can help with your **EduPulse academic records**, such as **attendance & eligibility**, **CGPA/SGPA & marks**, **courses & faculty**, or **extracurricular activities**.",
            'suggestions' => buildPersonalizedSuggestions($ctx, 'unknown')
        ];
    }

    if (count($confidentIntents) === 1) {
        $res = dispatchSingleIntent($confidentIntents[0], $ctx, $entities, $normalized, $firstName);
        saveChatSessionContext(
            $studentId,
            $res['intent'],
            $res['active_course'] ?? null,
            $res['active_semester'] ?? $resolvedSemester
        );
        $res['intents'] = [$res['intent']];
        $res['suggestions'] = buildPersonalizedSuggestions($ctx, $res['intent'], $res['active_course'] ?? null);
        return $res;
    }

    // Multiple confident intents in one question: answer each cleanly
    $parts = [];
    $lastSem = $resolvedSemester;
    foreach ($confidentIntents as $ci) {
        $sub = dispatchSingleIntent($ci, $ctx, $entities, $normalized, $firstName);
        $parts[] = $sub['reply'];
        if (isset($sub['active_semester'])) {
            $lastSem = $sub['active_semester'];
        }
    }

    saveChatSessionContext($studentId, $confidentIntents[0], null, $lastSem);
    return [
        'intent' => implode('+', $confidentIntents),
        'intents' => $confidentIntents,
        'reply' => implode("\n\n", $parts),
        'suggestions' => buildPersonalizedSuggestions($ctx, $confidentIntents[0])
    ];
}

$ctx = loadStudentAcademicContext($conn, $student_id);
mysqli_close($conn);

if ($ctx === null) {
    http_response_code(404);
    sendResponse(false, 'Student record not found.');
}

$result = routeAcademicQuery($message, $ctx);

sendResponse(true, 'Response generated', [
    'reply' => $result['reply'],
    'intent' => $result['intent'],
    'intents' => $result['intents'] ?? [$result['intent']],
    'suggestions' => $result['suggestions'],
    'meta' => [
        'student_name' => $ctx['full_name'],
        'semester' => $ctx['current_semester']
    ]
]);
?>
