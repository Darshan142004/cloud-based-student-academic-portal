<?php
/**
 * EduPulse Pure-PHP Explainable Academic Analytics Helpers.
 *
 * All calculations are deterministic, strictly grounded in actual database
 * records, and enforce privacy/sample-size guards for cohort benchmarks and
 * correlation metrics.
 */

/**
 * Classify attendance percentage using EduPulse policy thresholds.
 */
function epGetAttendanceStatus(float $percentage): string {
    if ($percentage >= 85.0) {
        return 'Safe';
    }
    if ($percentage >= 75.0) {
        return 'Warning';
    }
    return 'Critical';
}

/**
 * Compute consecutive classes required to reach targetRatio (e.g., 0.75 or 0.85).
 */
function epClassesNeededForTarget(int $attended, int $total, float $targetRatio): int {
    if ($targetRatio <= 0.0 || $targetRatio >= 1.0) {
        return 0;
    }
    if ($total <= 0) {
        return 0;
    }
    if (($attended / $total) >= $targetRatio) {
        return 0;
    }
    return (int) max(0, ceil(($targetRatio * $total - $attended) / (1.0 - $targetRatio)));
}

/**
 * Compute maximum future absences allowed while staying >= targetRatio.
 */
function epSafeAbsencesAllowed(int $attended, int $total, float $targetRatio): int {
    if ($targetRatio <= 0.0 || $targetRatio >= 1.0 || $total <= 0) {
        return 0;
    }
    if (($attended / $total) < $targetRatio) {
        return 0;
    }
    return (int) max(0, floor(($attended / $targetRatio) - $total));
}

/**
 * Return complete attendance target breakdown for a class count pair.
 */
function epComputeAttendanceTargets(int $attended, int $total): array {
    $pct = $total > 0 ? round(($attended / $total) * 100, 2) : 0.0;
    return [
        'classes_attended' => $attended,
        'total_classes' => $total,
        'percentage' => $pct,
        'status' => $total > 0 ? epGetAttendanceStatus($pct) : 'No Data',
        'classes_needed_75' => epClassesNeededForTarget($attended, $total, 0.75),
        'classes_needed_85' => epClassesNeededForTarget($attended, $total, 0.85),
        'safe_absences_75' => epSafeAbsencesAllowed($attended, $total, 0.75),
        'safe_absences_85' => epSafeAbsencesAllowed($attended, $total, 0.85),
    ];
}

/**
 * Classify overall CGPA/SGPA into an academic performance tier.
 */
function epClassifyPerformanceBand(float $gpa): string {
    if ($gpa >= 9.0) {
        return 'Outstanding';
    }
    if ($gpa >= 8.0) {
        return 'Strong';
    }
    if ($gpa >= 7.0) {
        return 'Satisfactory';
    }
    if ($gpa >= 5.0) {
        return 'Developing';
    }
    return 'At Risk';
}

/**
 * Compute credit-weighted semester SGPAs, running cumulative CGPA, and trend classification.
 *
 * @param array $gradeRows Graded course rows containing semester, credits, grade_points, total_marks.
 * @param array $cohortSemAverages Map of semester => ['cohort_size' => int, 'class_avg_sgpa' => float].
 */
function epComputeSemesterAndCumulativeTrends(array $gradeRows, array $cohortSemAverages = []): array {
    $bySem = [];
    foreach ($gradeRows as $row) {
        $sem = (int) ($row['semester'] ?? 0);
        if ($sem <= 0 || $row['grade_points'] === null || $row['grade_points'] === '') {
            continue;
        }
        $credits = (int) ($row['credits'] ?? 0);
        $gp = (float) $row['grade_points'];
        $marks = isset($row['total_marks']) && $row['total_marks'] !== null ? (float) $row['total_marks'] : null;

        if (!isset($bySem[$sem])) {
            $bySem[$sem] = [
                'semester' => $sem,
                'credits' => 0,
                'weighted_points' => 0.0,
                'course_count' => 0,
                'marks_sum' => 0.0,
                'marks_count' => 0,
            ];
        }
        if ($credits > 0) {
            $bySem[$sem]['credits'] += $credits;
            $bySem[$sem]['weighted_points'] += ($gp * $credits);
        }
        $bySem[$sem]['course_count']++;
        if ($marks !== null) {
            $bySem[$sem]['marks_sum'] += $marks;
            $bySem[$sem]['marks_count']++;
        }
    }

    ksort($bySem);

    $semesters = [];
    $cumCredits = 0;
    $cumWeighted = 0.0;
    $prevSgpa = null;
    $highestSgpa = 0.0;
    $highestSem = null;
    $lowestSgpa = null;
    $lowestSem = null;
    $latestSgpa = null;
    $latestSem = null;

    foreach ($bySem as $sem => $acc) {
        $sgpa = $acc['credits'] > 0 ? round($acc['weighted_points'] / $acc['credits'], 2) : 0.0;
        $cumCredits += $acc['credits'];
        $cumWeighted += $acc['weighted_points'];
        $cumCgpa = $cumCredits > 0 ? round($cumWeighted / $cumCredits, 2) : 0.0;
        $delta = $prevSgpa !== null ? round($sgpa - $prevSgpa, 2) : null;

        $peer = $cohortSemAverages[$sem] ?? null;
        $peerCount = $peer ? (int) ($peer['cohort_size'] ?? 0) : 0;
        $hasPeer = $peerCount >= 3 && isset($peer['class_avg_sgpa']);

        $semesters[] = [
            'semester' => $sem,
            'sgpa' => $sgpa,
            'cumulative_cgpa' => $cumCgpa,
            'credits' => $acc['credits'],
            'course_count' => $acc['course_count'],
            'avg_marks' => $acc['marks_count'] > 0 ? round($acc['marks_sum'] / $acc['marks_count'], 2) : null,
            'delta_sgpa' => $delta,
            'benchmark_available' => $hasPeer,
            'cohort_size' => $hasPeer ? $peerCount : 0,
            'class_avg_sgpa' => $hasPeer ? round((float) $peer['class_avg_sgpa'], 2) : null,
            'sgpa_vs_class' => $hasPeer ? round($sgpa - (float) $peer['class_avg_sgpa'], 2) : null,
        ];

        if ($sgpa > $highestSgpa) {
            $highestSgpa = $sgpa;
            $highestSem = $sem;
        }
        if ($lowestSgpa === null || $sgpa < $lowestSgpa) {
            $lowestSgpa = $sgpa;
            $lowestSem = $sem;
        }
        $latestSgpa = $sgpa;
        $latestSem = $sem;
        $prevSgpa = $sgpa;
    }

    $cgpa = $cumCredits > 0 ? round($cumWeighted / $cumCredits, 2) : 0.0;

    // Trend classification via latest delta & simple OLS slope over semester index
    $countSem = count($semesters);
    $trendStatus = 'insufficient_history';
    $trendLabel = 'Insufficient History';
    $deltaLatest = null;
    $slope = null;

    if ($countSem >= 2) {
        $last = $semesters[$countSem - 1];
        $prev = $semesters[$countSem - 2];
        $deltaLatest = round($last['sgpa'] - $prev['sgpa'], 2);

        // Compute OLS slope across all graded semesters
        $n = $countSem;
        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumX2 = 0.0;
        foreach ($semesters as $idx => $sItem) {
            $x = (float) ($idx + 1);
            $y = (float) $sItem['sgpa'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        $denom = ($n * $sumX2) - ($sumX * $sumX);
        $slope = abs($denom) > 1e-12 ? round((($n * $sumXY) - ($sumX * $sumY)) / $denom, 3) : 0.0;

        if ($deltaLatest >= 0.20) {
            $trendStatus = 'improving';
            $trendLabel = 'Improving';
        } elseif ($deltaLatest <= -0.20) {
            $trendStatus = 'declining';
            $trendLabel = 'Declining';
        } elseif ($slope >= 0.15) {
            $trendStatus = 'improving';
            $trendLabel = 'Gradually Improving';
        } elseif ($slope <= -0.15) {
            $trendStatus = 'declining';
            $trendLabel = 'Gradually Declining';
        } else {
            $trendStatus = 'stable';
            $trendLabel = 'Stable';
        }
    } elseif ($countSem === 1) {
        $trendLabel = 'Baseline (1 Semester)';
    }

    return [
        'semesters' => $semesters,
        'cgpa' => $cgpa,
        'total_graded_credits' => $cumCredits,
        'latest_sgpa' => $latestSgpa,
        'latest_semester' => $latestSem,
        'highest_sgpa' => $highestSgpa,
        'highest_semester' => $highestSem,
        'lowest_sgpa' => $lowestSgpa,
        'lowest_semester' => $lowestSem,
        'trend' => [
            'status' => $trendStatus,
            'label' => $trendLabel,
            'delta_latest' => $deltaLatest,
            'slope_per_semester' => $slope,
            'performance_band' => $cumCredits > 0 ? epClassifyPerformanceBand($cgpa) : 'No Grades Yet',
        ],
    ];
}

/**
 * Compute Pearson correlation coefficient with strict sample-size (n >= 3)
 * and zero-variance guards.
 */
function epComputePearsonCorrelation(array $x, array $y): array {
    if (count($x) !== count($y)) {
        return [
            'status' => 'invalid_input',
            'paired_count' => 0,
            'r' => null,
            'strength' => null,
            'direction' => null,
            'reason' => 'Mismatched array lengths for correlation calculation.',
        ];
    }

    $pairs = [];
    $count = count($x);
    for ($i = 0; $i < $count; $i++) {
        if (is_numeric($x[$i]) && is_numeric($y[$i])) {
            $pairs[] = [(float) $x[$i], (float) $y[$i]];
        }
    }

    $n = count($pairs);
    if ($n < 3) {
        return [
            'status' => 'insufficient_overlap',
            'paired_count' => $n,
            'r' => null,
            'strength' => null,
            'direction' => null,
            'reason' => "At least 3 overlapping course-semester observations are required (found {$n}).",
        ];
    }

    $sumX = 0.0;
    $sumY = 0.0;
    foreach ($pairs as [$xi, $yi]) {
        $sumX += $xi;
        $sumY += $yi;
    }
    $meanX = $sumX / $n;
    $meanY = $sumY / $n;

    $num = 0.0;
    $varX = 0.0;
    $varY = 0.0;
    foreach ($pairs as [$xi, $yi]) {
        $dx = $xi - $meanX;
        $dy = $yi - $meanY;
        $num += $dx * $dy;
        $varX += $dx * $dx;
        $varY += $dy * $dy;
    }

    $denom = sqrt($varX * $varY);
    if ($denom <= 1e-12) {
        return [
            'status' => 'zero_variance',
            'paired_count' => $n,
            'r' => null,
            'strength' => 'Undefined',
            'direction' => 'None',
            'reason' => 'Variance is zero across matched observations (all values identical), so correlation is undefined.',
        ];
    }

    $r = max(-1.0, min(1.0, $num / $denom));
    $rRounded = round($r, 4);
    $absR = abs($rRounded);

    if ($absR >= 0.70) {
        $strength = 'Strong';
    } elseif ($absR >= 0.40) {
        $strength = 'Moderate';
    } elseif ($absR >= 0.20) {
        $strength = 'Weak';
    } else {
        $strength = 'Negligible';
    }

    $direction = $rRounded > 0.05 ? 'Positive' : ($rRounded < -0.05 ? 'Negative' : 'Neutral');

    return [
        'status' => 'ok',
        'paired_count' => $n,
        'r' => $rRounded,
        'strength' => $strength,
        'direction' => $direction,
        'reason' => null,
    ];
}

/**
 * Analyze course-level academic grades and current-semester attendance against
 * anonymized cohort averages (requiring cohort_size >= 3 excluding the student).
 */
function epAnalyzeCoursePerformance(
    array $gradeRows,
    array $cohortCourseBenchmarks = [],
    array $attendanceRows = [],
    array $cohortAttendanceBenchmarks = []
): array {
    $gradedCourses = [];
    foreach ($gradeRows as $row) {
        $courseId = (int) ($row['course_id'] ?? 0);
        $sem = (int) ($row['semester'] ?? 0);
        $key = $courseId . ':' . $sem;
        $peer = $cohortCourseBenchmarks[$key] ?? null;
        $cohortSize = $peer ? (int) ($peer['cohort_size'] ?? 0) : 0;
        $hasBenchmark = $cohortSize >= 3 && isset($peer['class_avg_marks']);

        $internal = $row['internal_marks'] !== null ? (float) $row['internal_marks'] : null;
        $external = $row['external_marks'] !== null ? (float) $row['external_marks'] : null;
        $total = $row['total_marks'] !== null ? (float) $row['total_marks'] : null;
        $gp = $row['grade_points'] !== null ? (float) $row['grade_points'] : null;

        $intExtDiff = ($internal !== null && $external !== null) ? round($internal - $external, 2) : null;
        $balanceLabel = 'Balanced';
        if ($intExtDiff !== null) {
            if ($intExtDiff >= 6.0) {
                $balanceLabel = 'Stronger Internal';
            } elseif ($intExtDiff <= -6.0) {
                $balanceLabel = 'Stronger External';
            }
        }

        $classAvgMarks = $hasBenchmark ? round((float) $peer['class_avg_marks'], 2) : null;
        $classAvgGp = ($hasBenchmark && isset($peer['class_avg_gp'])) ? round((float) $peer['class_avg_gp'], 2) : null;

        $gradedCourses[] = [
            'course_id' => $courseId,
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'semester' => $sem,
            'credits' => (int) ($row['credits'] ?? 0),
            'internal_marks' => $internal,
            'external_marks' => $external,
            'total_marks' => $total,
            'grade' => $row['grade'],
            'grade_points' => $gp,
            'internal_external_diff' => $intExtDiff,
            'balance_label' => $balanceLabel,
            'benchmark_available' => $hasBenchmark,
            'cohort_size' => $hasBenchmark ? $cohortSize : 0,
            'class_avg_marks' => $classAvgMarks,
            'class_max_marks' => ($hasBenchmark && isset($peer['class_max_marks'])) ? round((float) $peer['class_max_marks'], 2) : null,
            'class_min_marks' => ($hasBenchmark && isset($peer['class_min_marks'])) ? round((float) $peer['class_min_marks'], 2) : null,
            'class_avg_gp' => $classAvgGp,
            'class_avg_internal' => ($hasBenchmark && isset($peer['class_avg_internal'])) ? round((float) $peer['class_avg_internal'], 2) : null,
            'class_avg_external' => ($hasBenchmark && isset($peer['class_avg_external'])) ? round((float) $peer['class_avg_external'], 2) : null,
            'marks_delta' => ($hasBenchmark && $total !== null) ? round($total - $classAvgMarks, 2) : null,
            'gp_delta' => ($hasBenchmark && $gp !== null && $classAvgGp !== null) ? round($gp - $classAvgGp, 2) : null,
        ];
    }

    $attendanceCourses = [];
    foreach ($attendanceRows as $row) {
        $courseId = (int) ($row['course_id'] ?? 0);
        $sem = (int) ($row['semester'] ?? 0);
        $attended = (int) ($row['classes_attended'] ?? 0);
        $total = (int) ($row['total_classes'] ?? 0);
        $targets = epComputeAttendanceTargets($attended, $total);

        $key = $courseId . ':' . $sem;
        $peer = $cohortAttendanceBenchmarks[$key] ?? null;
        $cohortSize = $peer ? (int) ($peer['cohort_size'] ?? 0) : 0;
        $hasBenchmark = $cohortSize >= 3 && isset($peer['class_avg_attendance']);
        $classAvgAtt = $hasBenchmark ? round((float) $peer['class_avg_attendance'], 2) : null;

        $attendanceCourses[] = array_merge([
            'course_id' => $courseId,
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'semester' => $sem,
            'credits' => (int) ($row['credits'] ?? 0),
            'benchmark_available' => $hasBenchmark,
            'cohort_size' => $hasBenchmark ? $cohortSize : 0,
            'class_avg_attendance' => $classAvgAtt,
            'attendance_delta' => ($hasBenchmark && $total > 0) ? round($targets['percentage'] - $classAvgAtt, 2) : null,
        ], $targets);
    }

    return [
        'graded_courses' => $gradedCourses,
        'attendance_courses' => $attendanceCourses,
    ];
}

/**
 * Compute attendance-vs-grade correlation strictly on matched (course_id, semester) pairs.
 */
function epAnalyzeAttendanceGradeCorrelation(array $gradeRows, array $attendanceRows): array {
    $attMap = [];
    foreach ($attendanceRows as $a) {
        $total = (int) ($a['total_classes'] ?? 0);
        if ($total <= 0) {
            continue;
        }
        $key = (int) $a['course_id'] . ':' . (int) $a['semester'];
        $attMap[$key] = round(((int) $a['classes_attended'] / $total) * 100, 2);
    }

    $points = [];
    $attSeries = [];
    $marksSeries = [];
    $gpSeries = [];

    foreach ($gradeRows as $g) {
        $key = (int) $g['course_id'] . ':' . (int) $g['semester'];
        if (!isset($attMap[$key]) || $g['total_marks'] === null || $g['grade_points'] === null) {
            continue;
        }
        $attPct = $attMap[$key];
        $marks = (float) $g['total_marks'];
        $gp = (float) $g['grade_points'];

        $attSeries[] = $attPct;
        $marksSeries[] = $marks;
        $gpSeries[] = $gp;
        $points[] = [
            'course_code' => $g['course_code'],
            'course_name' => $g['course_name'],
            'semester' => (int) $g['semester'],
            'attendance_percentage' => $attPct,
            'total_marks' => $marks,
            'grade_points' => $gp,
            'grade' => $g['grade'],
        ];
    }

    $marksCorr = epComputePearsonCorrelation($attSeries, $marksSeries);
    $gpCorr = epComputePearsonCorrelation($attSeries, $gpSeries);

    $note = null;
    if ($marksCorr['status'] === 'insufficient_overlap') {
        $gradeSems = array_values(array_unique(array_map(fn($r) => (int) $r['semester'], $gradeRows)));
        $attSems = array_values(array_unique(array_map(fn($r) => (int) $r['semester'], $attendanceRows)));
        sort($gradeSems);
        sort($attSems);
        if (count($points) === 0 && !empty($gradeSems) && !empty($attSems)) {
            $note = 'No overlapping course-semester records exist yet: grades are recorded for Semester(s) '
                . implode(', ', $gradeSems) . ', while attendance is recorded for Semester(s) '
                . implode(', ', $attSems) . '.';
        } else {
            $note = $marksCorr['reason'];
        }
    } elseif ($marksCorr['status'] === 'zero_variance') {
        $note = $marksCorr['reason'];
    } else {
        $note = sprintf(
            '%s %s correlation (r = %.2f) across %d matched course-semester records.',
            $marksCorr['strength'],
            strtolower((string) $marksCorr['direction']),
            $marksCorr['r'],
            $marksCorr['paired_count']
        );
    }

    return [
        'status' => $marksCorr['status'],
        'paired_count' => count($points),
        'pearson_r' => $marksCorr['r'],
        'strength' => $marksCorr['strength'],
        'direction' => $marksCorr['direction'],
        'grade_points_r' => $gpCorr['r'],
        'points' => $points,
        'note' => $note,
    ];
}

/**
 * Compute assignment completion, overdue risk, and timeliness metrics from real DB rows.
 */
function epAnalyzeAssignments(array $assignmentRows, ?DateTimeInterface $now = null): array {
    $nowDt = $now ? DateTimeImmutable::createFromInterface($now) : new DateTimeImmutable('now');

    if (empty($assignmentRows)) {
        return [
            'has_data' => false,
            'summary' => [
                'total_assigned' => 0,
                'pending_count' => 0,
                'in_progress_count' => 0,
                'submitted_count' => 0,
                'graded_count' => 0,
                'completed_count' => 0,
                'overdue_count' => 0,
                'upcoming_count' => 0,
            ],
            'timeliness' => [
                'has_submissions' => false,
                'on_time_count' => 0,
                'late_count' => 0,
                'on_time_rate' => null,
                'avg_lead_hours' => null,
                'avg_score_pct' => null,
            ],
            'by_course' => [],
            'items' => [],
        ];
    }

    $counts = [
        'total_assigned' => 0,
        'pending_count' => 0,
        'in_progress_count' => 0,
        'submitted_count' => 0,
        'graded_count' => 0,
        'completed_count' => 0,
        'overdue_count' => 0,
        'upcoming_count' => 0,
    ];

    $onTimeCount = 0;
    $lateCount = 0;
    $leadHoursSum = 0.0;
    $leadHoursCount = 0;
    $scorePctSum = 0.0;
    $scoreCount = 0;
    $byCourse = [];
    $items = [];

    foreach ($assignmentRows as $row) {
        $counts['total_assigned']++;
        $rawStatus = $row['submission_status'] ?? $row['status'] ?? 'pending';
        if (!in_array($rawStatus, ['pending', 'in_progress', 'submitted', 'graded'], true)) {
            $rawStatus = 'pending';
        }

        $dueDt = new DateTimeImmutable($row['due_date']);
        $isCompleted = in_array($rawStatus, ['submitted', 'graded'], true);
        $isOverdue = !$isCompleted && ($nowDt > $dueDt);
        $secondsRemaining = $dueDt->getTimestamp() - $nowDt->getTimestamp();
        $hoursRemaining = round($secondsRemaining / 3600, 1);

        if ($rawStatus === 'pending') {
            $counts['pending_count']++;
        } elseif ($rawStatus === 'in_progress') {
            $counts['in_progress_count']++;
        } elseif ($rawStatus === 'submitted') {
            $counts['submitted_count']++;
        } elseif ($rawStatus === 'graded') {
            $counts['graded_count']++;
        }

        if ($isCompleted) {
            $counts['completed_count']++;
        } elseif ($isOverdue) {
            $counts['overdue_count']++;
        } else {
            $counts['upcoming_count']++;
        }

        $timelinessStatus = $isOverdue ? 'overdue' : 'awaiting';
        $leadHours = null;
        if ($isCompleted && !empty($row['submitted_at'])) {
            $subDt = new DateTimeImmutable($row['submitted_at']);
            $leadHours = round(($dueDt->getTimestamp() - $subDt->getTimestamp()) / 3600, 2);
            $leadHoursSum += $leadHours;
            $leadHoursCount++;

            if ($subDt <= $dueDt) {
                $onTimeCount++;
                $timelinessStatus = 'on_time';
            } else {
                $lateCount++;
                $timelinessStatus = 'late';
            }
        }

        $maxMarks = (float) ($row['max_marks'] ?? 0);
        $marksObtained = isset($row['marks_obtained']) && $row['marks_obtained'] !== null
            ? (float) $row['marks_obtained']
            : null;
        $scorePct = ($marksObtained !== null && $maxMarks > 0)
            ? round(($marksObtained / $maxMarks) * 100, 2)
            : null;
        if ($scorePct !== null) {
            $scorePctSum += $scorePct;
            $scoreCount++;
        }

        $courseCode = $row['course_code'] ?? 'UNKNOWN';
        if (!isset($byCourse[$courseCode])) {
            $byCourse[$courseCode] = [
                'course_id' => (int) ($row['course_id'] ?? 0),
                'course_code' => $courseCode,
                'course_name' => $row['course_name'] ?? '',
                'total' => 0,
                'completed' => 0,
                'on_time' => 0,
                'late' => 0,
                'overdue' => 0,
            ];
        }
        $byCourse[$courseCode]['total']++;
        if ($isCompleted) {
            $byCourse[$courseCode]['completed']++;
        }
        if ($timelinessStatus === 'on_time') {
            $byCourse[$courseCode]['on_time']++;
        } elseif ($timelinessStatus === 'late') {
            $byCourse[$courseCode]['late']++;
        } elseif ($isOverdue) {
            $byCourse[$courseCode]['overdue']++;
        }

        $items[] = [
            'assignment_id' => (int) $row['assignment_id'],
            'course_id' => (int) ($row['course_id'] ?? 0),
            'course_code' => $courseCode,
            'course_name' => $row['course_name'] ?? '',
            'semester' => (int) ($row['semester'] ?? 0),
            'title' => $row['title'],
            'description' => $row['description'] ?? '',
            'due_date' => $row['due_date'],
            'max_marks' => $maxMarks,
            'priority' => $row['priority'] ?? 'medium',
            'status' => $rawStatus,
            'progress_pct' => (int) ($row['progress_pct'] ?? ($isCompleted ? 100 : 0)),
            'submitted_at' => $row['submitted_at'] ?? null,
            'marks_obtained' => $marksObtained,
            'score_pct' => $scorePct,
            'feedback' => $row['feedback'] ?? null,
            'submission_text' => $row['submission_text'] ?? null,
            'file_path' => $row['file_path'] ?? null,
            'is_overdue' => $isOverdue,
            'hours_remaining' => $hoursRemaining,
            'timeliness_status' => $timelinessStatus,
            'lead_hours' => $leadHours,
        ];
    }

    $totalSubmittedForTimeliness = $onTimeCount + $lateCount;

    return [
        'has_data' => true,
        'summary' => $counts,
        'timeliness' => [
            'has_submissions' => $totalSubmittedForTimeliness > 0,
            'on_time_count' => $onTimeCount,
            'late_count' => $lateCount,
            'on_time_rate' => $totalSubmittedForTimeliness > 0
                ? round(($onTimeCount / $totalSubmittedForTimeliness) * 100, 2)
                : null,
            'avg_lead_hours' => $leadHoursCount > 0 ? round($leadHoursSum / $leadHoursCount, 2) : null,
            'avg_score_pct' => $scoreCount > 0 ? round($scorePctSum / $scoreCount, 2) : null,
        ],
        'by_course' => array_values($byCourse),
        'items' => $items,
    ];
}

/**
 * Identify evidence-backed academic strengths and weaknesses.
 */
function epIdentifyStrengthsAndWeaknesses(
    array $gradedCourses,
    array $attendanceCourses,
    array $assignmentAnalysis
): array {
    $strengths = [];
    $weaknesses = [];

    foreach ($gradedCourses as $c) {
        $gp = $c['grade_points'];
        $marks = $c['total_marks'];
        $delta = $c['marks_delta'];
        $hasBench = $c['benchmark_available'] ?? false;

        if ($gp !== null && ($gp >= 9.0 || ($gp >= 8.0 && $hasBench && $delta !== null && $delta >= 4.0))) {
            $benchNote = ($hasBench && $delta !== null)
                ? sprintf(' (%+.1f vs cohort avg %.1f across %d peers)', $delta, $c['class_avg_marks'], $c['cohort_size'])
                : '';
            $strengths[] = [
                'category' => 'Course Mastery',
                'course_code' => $c['course_code'],
                'course_name' => $c['course_name'],
                'semester' => $c['semester'],
                'title' => "{$c['course_code']} — {$c['course_name']}",
                'evidence' => sprintf('Scored %.0f/100 (Grade %s, %.1f GP)%s.', $marks, $c['grade'], $gp, $benchNote),
                'score' => $marks,
            ];
        } elseif ($gp !== null && ($gp <= 7.0 || ($hasBench && $delta !== null && $delta <= -4.0) || ($marks !== null && $marks < 75))) {
            $benchNote = ($hasBench && $delta !== null)
                ? sprintf(' (%+.1f vs cohort avg %.1f across %d peers)', $delta, $c['class_avg_marks'], $c['cohort_size'])
                : '';
            $weaknesses[] = [
                'category' => 'Grade Improvement Area',
                'course_code' => $c['course_code'],
                'course_name' => $c['course_name'],
                'semester' => $c['semester'],
                'title' => "{$c['course_code']} — {$c['course_name']}",
                'evidence' => sprintf('Scored %.0f/100 (Grade %s, %.1f GP)%s.', $marks, $c['grade'], $gp, $benchNote),
                'severity' => ($gp < 7.0 || ($delta !== null && $delta <= -8.0)) ? 'high' : 'medium',
            ];
        }
    }

    foreach ($attendanceCourses as $a) {
        if ($a['total_classes'] <= 0) {
            continue;
        }
        $pct = (float) $a['percentage'];
        if ($pct < 85.0) {
            $weaknesses[] = [
                'category' => 'Attendance Risk',
                'course_code' => $a['course_code'],
                'course_name' => $a['course_name'],
                'semester' => $a['semester'],
                'title' => "{$a['course_code']} — {$a['course_name']} Attendance",
                'evidence' => sprintf(
                    '%d/%d classes attended (%.1f%% — %s; need %d consecutive classes for %s).',
                    $a['classes_attended'],
                    $a['total_classes'],
                    $pct,
                    $a['status'],
                    $pct < 75.0 ? $a['classes_needed_75'] : $a['classes_needed_85'],
                    $pct < 75.0 ? '75%' : '85%'
                ),
                'severity' => $pct < 75.0 ? 'high' : 'medium',
            ];
        } elseif ($pct >= 95.0) {
            $strengths[] = [
                'category' => 'Attendance Consistency',
                'course_code' => $a['course_code'],
                'course_name' => $a['course_name'],
                'semester' => $a['semester'],
                'title' => "{$a['course_code']} — High Class Presence",
                'evidence' => sprintf('%d/%d classes attended (%.1f%%, can miss up to %d while staying ≥75%%).', $a['classes_attended'], $a['total_classes'], $pct, $a['safe_absences_75']),
                'score' => $pct,
            ];
        }
    }

    if (!empty($assignmentAnalysis['has_data'])) {
        $tl = $assignmentAnalysis['timeliness'];
        $sum = $assignmentAnalysis['summary'];
        if ($sum['overdue_count'] > 0 || ($tl['has_submissions'] && $tl['on_time_rate'] < 75.0)) {
            $weaknesses[] = [
                'category' => 'Assignment Timeliness',
                'course_code' => null,
                'course_name' => null,
                'semester' => null,
                'title' => 'Assignment Deadlines & Timeliness',
                'evidence' => sprintf(
                    '%d overdue, %d late vs %d on-time submission(s).',
                    $sum['overdue_count'],
                    $tl['late_count'],
                    $tl['on_time_count']
                ),
                'severity' => $sum['overdue_count'] > 0 ? 'high' : 'medium',
            ];
        } elseif ($tl['has_submissions'] && $tl['on_time_rate'] >= 90.0) {
            $strengths[] = [
                'category' => 'Assignment Timeliness',
                'course_code' => null,
                'course_name' => null,
                'semester' => null,
                'title' => 'Consistent On-Time Submissions',
                'evidence' => sprintf('%.1f%% on-time rate (%d/%d submissions before due date).', $tl['on_time_rate'], $tl['on_time_count'], $tl['on_time_count'] + $tl['late_count']),
                'score' => $tl['on_time_rate'],
            ];
        }
    }

    // Sort strengths by score descending, limit noise
    usort($strengths, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

    return [
        'strengths' => $strengths,
        'weaknesses' => $weaknesses,
    ];
}

/**
 * Generate explainable, auditable academic recommendations backed by exact metrics.
 */
function epGenerateExplainableRecommendations(array $ctx): array {
    $recs = [];

    $trends = $ctx['trends'] ?? [];
    $gradedCourses = $ctx['graded_courses'] ?? [];
    $attendanceCourses = $ctx['attendance_courses'] ?? [];
    $assignments = $ctx['assignments'] ?? ['has_data' => false];

    // 1. Critical & Warning Attendance Recovery Rules
    $criticalAtt = array_values(array_filter($attendanceCourses, fn($a) => $a['total_classes'] > 0 && $a['percentage'] < 75.0));
    $warningAtt = array_values(array_filter($attendanceCourses, fn($a) => $a['total_classes'] > 0 && $a['percentage'] >= 75.0 && $a['percentage'] < 85.0));

    foreach ($criticalAtt as $a) {
        $recs[] = [
            'rule_id' => 'att_critical_' . $a['course_code'],
            'category' => 'attendance',
            'priority' => 'high',
            'title' => "Recover Critical Attendance in {$a['course_code']}",
            'summary' => "Attend the next {$a['classes_needed_75']} consecutive classes in {$a['course_name']} to regain 75% eligibility.",
            'explanation' => sprintf(
                'Triggered because %s attendance is %.2f%% (%d/%d), which is below the 75%% exam eligibility floor.',
                $a['course_code'],
                $a['percentage'],
                $a['classes_attended'],
                $a['total_classes']
            ),
            'evidence' => [
                "Current: {$a['classes_attended']}/{$a['total_classes']} ({$a['percentage']}%)",
                "Needed for 75%: {$a['classes_needed_75']} consecutive classes",
                "Needed for 85% Safe: {$a['classes_needed_85']} consecutive classes",
            ],
            'action_label' => 'View Attendance',
            'action_url' => 'attendance.html',
        ];
    }

    if (!empty($warningAtt)) {
        $lowestWarn = $warningAtt[0];
        foreach ($warningAtt as $w) {
            if ($w['percentage'] < $lowestWarn['percentage']) {
                $lowestWarn = $w;
            }
        }
        $codes = implode(', ', array_map(fn($w) => "{$w['course_code']} ({$w['percentage']}%)", $warningAtt));
        $recs[] = [
            'rule_id' => 'att_warning_buffer',
            'category' => 'attendance',
            'priority' => 'medium',
            'title' => 'Build Attendance Buffer Above 85% Safe Threshold',
            'summary' => sprintf(
                'Attend %d consecutive classes in %s to move from Warning into Safe (≥85%%).',
                $lowestWarn['classes_needed_85'],
                $lowestWarn['course_code']
            ),
            'explanation' => "Triggered because " . count($warningAtt) . " current course(s) fall in the 75–84.99% Warning band: {$codes}.",
            'evidence' => [
                "Warning courses: {$codes}",
                "{$lowestWarn['course_code']} can miss at most {$lowestWarn['safe_absences_75']} class(es) before dropping below 75%.",
            ],
            'action_label' => 'Check Attendance',
            'action_url' => 'attendance.html',
        ];
    }

    // 2. Semester SGPA Trend Rule
    $semList = $trends['semesters'] ?? [];
    $trendInfo = $trends['trend'] ?? [];
    if (($trendInfo['status'] ?? '') === 'declining' && count($semList) >= 2) {
        $last = $semList[count($semList) - 1];
        $prev = $semList[count($semList) - 2];
        $lastSemCourses = array_values(array_filter($gradedCourses, fn($c) => $c['semester'] === $last['semester']));
        usort($lastSemCourses, fn($a, $b) => ($a['total_marks'] ?? 999) <=> ($b['total_marks'] ?? 999));
        $lowestCourse = $lastSemCourses[0] ?? null;
        $lowestNote = $lowestCourse
            ? "Lowest subject in Sem {$last['semester']}: {$lowestCourse['course_code']} ({$lowestCourse['total_marks']}/100, {$lowestCourse['grade']})."
            : '';

        $recs[] = [
            'rule_id' => 'sgpa_decline_alert',
            'category' => 'gpa_trend',
            'priority' => 'high',
            'title' => "Address Semester {$last['semester']} SGPA Dip",
            'summary' => sprintf(
                'Your SGPA declined by %.2f from Sem %d (%.2f) to Sem %d (%.2f). Review weak subjects before current-semester finals.',
                abs((float) $trendInfo['delta_latest']),
                $prev['semester'],
                $prev['sgpa'],
                $last['semester'],
                $last['sgpa']
            ),
            'explanation' => sprintf(
                'Triggered because latest semester-over-semester SGPA change (%.2f) is below -0.20.%s',
                $trendInfo['delta_latest'],
                $lowestNote ? ' ' . $lowestNote : ''
            ),
            'evidence' => array_values(array_filter([
                "Sem {$prev['semester']} SGPA: " . number_format($prev['sgpa'], 2),
                "Sem {$last['semester']} SGPA: " . number_format($last['sgpa'], 2),
                $lowestNote ?: null,
            ])),
            'action_label' => 'Review Grades',
            'action_url' => 'grades.html',
        ];
    }

    // 3. Cohort-Benchmark Deficit Rule
    $laggingCourses = array_values(array_filter(
        $gradedCourses,
        fn($c) => ($c['benchmark_available'] ?? false) && $c['marks_delta'] !== null && $c['marks_delta'] <= -5.0
    ));
    usort($laggingCourses, fn($a, $b) => $a['marks_delta'] <=> $b['marks_delta']);
    if (!empty($laggingCourses)) {
        $worst = $laggingCourses[0];
        $recs[] = [
            'rule_id' => 'cohort_gap_' . $worst['course_code'],
            'category' => 'course_gap',
            'priority' => $worst['marks_delta'] <= -10.0 ? 'high' : 'medium',
            'title' => "Close Cohort Benchmark Gap in {$worst['course_code']}",
            'summary' => sprintf(
                'Your score in %s (%.0f/100) trailed the program cohort average (%.1f/100) by %.1f marks.',
                $worst['course_name'],
                $worst['total_marks'],
                $worst['class_avg_marks'],
                abs($worst['marks_delta'])
            ),
            'explanation' => sprintf(
                'Triggered because marks_delta (%.1f) is ≤ -5.0 compared against %d anonymized peers in your program for Semester %d.',
                $worst['marks_delta'],
                $worst['cohort_size'],
                $worst['semester']
            ),
            'evidence' => [
                "Your marks: {$worst['total_marks']}/100 (Int: {$worst['internal_marks']}, Ext: {$worst['external_marks']})",
                "Cohort average ({$worst['cohort_size']} peers): {$worst['class_avg_marks']}/100",
                "Assessment balance: {$worst['balance_label']}",
            ],
            'action_label' => 'Inspect Course Breakdown',
            'action_url' => '#course-comparison',
        ];
    }

    // 4. Internal vs External Exam Gap Rule
    $examImbalanced = array_values(array_filter(
        $gradedCourses,
        fn($c) => $c['internal_external_diff'] !== null && abs($c['internal_external_diff']) >= 8.0
    ));
    usort($examImbalanced, fn($a, $b) => abs($b['internal_external_diff']) <=> abs($a['internal_external_diff']));
    if (!empty($examImbalanced)) {
        $item = $examImbalanced[0];
        $isExtLower = $item['internal_external_diff'] > 0;
        $recs[] = [
            'rule_id' => 'exam_balance_' . $item['course_code'],
            'category' => 'assessment_balance',
            'priority' => 'low',
            'title' => $isExtLower
                ? "Strengthen End-Term Exam Readiness ({$item['course_code']})"
                : "Boost Continuous Internal Assessments ({$item['course_code']})",
            'summary' => $isExtLower
                ? sprintf('In %s, internal marks (%d) outpaced external exam marks (%d) by %d points.', $item['course_code'], $item['internal_marks'], $item['external_marks'], abs($item['internal_external_diff']))
                : sprintf('In %s, external marks (%d) outpaced internal marks (%d) by %d points.', $item['course_code'], $item['external_marks'], $item['internal_marks'], abs($item['internal_external_diff'])),
            'explanation' => 'Triggered when internal vs. external mark difference is ≥ 8 points, highlighting coursework vs. timed-exam disparity.',
            'evidence' => [
                "Course: {$item['course_code']} (Sem {$item['semester']})",
                "Internal: {$item['internal_marks']} | External: {$item['external_marks']} | Total: {$item['total_marks']}/100",
            ],
            'action_label' => 'View Grades',
            'action_url' => 'grades.html',
        ];
    }

    // 5. Assignment Timeliness Rules (strictly when real assignment records exist)
    if (!empty($assignments['has_data'])) {
        $sum = $assignments['summary'];
        $tl = $assignments['timeliness'];

        if ($sum['overdue_count'] > 0) {
            $recs[] = [
                'rule_id' => 'assignments_overdue',
                'category' => 'assignments',
                'priority' => 'high',
                'title' => "Resolve {$sum['overdue_count']} Overdue Assignment(s)",
                'summary' => "You have {$sum['overdue_count']} unsubmitted assignment(s) past their due date.",
                'explanation' => 'Triggered directly from assignment_submissions where due_date < current time and status is pending or in_progress.',
                'evidence' => [
                    "Overdue: {$sum['overdue_count']} | Pending/In-Progress: " . ($sum['pending_count'] + $sum['in_progress_count']),
                ],
                'action_label' => 'Open Assignments',
                'action_url' => 'assignments.html',
            ];
        } elseif ($tl['has_submissions'] && $tl['on_time_rate'] < 80.0) {
            $recs[] = [
                'rule_id' => 'assignments_timeliness_low',
                'category' => 'assignments',
                'priority' => 'medium',
                'title' => 'Improve Assignment Submission Timeliness',
                'summary' => sprintf('Only %.1f%% of your submitted assignments were turned in before the deadline.', $tl['on_time_rate']),
                'explanation' => 'Triggered because on_time_rate < 80% across actual submitted assignments.',
                'evidence' => [
                    "On-Time: {$tl['on_time_count']} | Late: {$tl['late_count']}",
                ],
                'action_label' => 'View Assignments',
                'action_url' => 'assignments.html',
            ];
        }
    }

    // 6. Positive Reinforcement / Maintenance Insight when performing strongly
    $cgpa = (float) ($trends['cgpa'] ?? 0);
    if (empty($recs) || $cgpa >= 8.5) {
        $topCourse = null;
        foreach ($gradedCourses as $c) {
            if ($topCourse === null || ($c['total_marks'] ?? 0) > ($topCourse['total_marks'] ?? 0)) {
                $topCourse = $c;
            }
        }
        if ($topCourse !== null) {
            $recs[] = [
                'rule_id' => 'strength_maintain',
                'category' => 'strength',
                'priority' => 'low',
                'title' => 'Sustain Strong Academic Momentum',
                'summary' => sprintf(
                    'Your CGPA is %.2f (%s), led by %s (%s, %.0f/100). Maintain ≥85%% attendance in current courses.',
                    $cgpa,
                    $trendInfo['performance_band'] ?? 'Strong',
                    $topCourse['course_code'],
                    $topCourse['grade'],
                    $topCourse['total_marks']
                ),
                'explanation' => 'Triggered as positive reinforcement from cumulative CGPA and highest-scoring graded course.',
                'evidence' => array_values(array_filter([
                    "Cumulative CGPA: " . number_format($cgpa, 2),
                    "Top course: {$topCourse['course_code']} — {$topCourse['total_marks']}/100 ({$topCourse['grade']})",
                    !empty($criticalAtt) || !empty($warningAtt) ? null : 'All current-semester courses meet the ≥85% Safe attendance target.',
                ])),
                'action_label' => 'Explore Courses',
                'action_url' => 'courses.html',
            ];
        }
    }

    $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
    usort($recs, fn($a, $b) => ($priorityOrder[$a['priority']] ?? 9) <=> ($priorityOrder[$b['priority']] ?? 9));

    return $recs;
}

/**
 * Compute grade-tier distribution counts consistently across dashboards and APIs.
 */
function epComputeGradeDistribution(array $gradeRows): array {
    $counts = [
        'a_plus' => 0,
        'a' => 0,
        'b_plus' => 0,
        'b' => 0,
        'total' => 0,
    ];
    foreach ($gradeRows as $row) {
        if ($row['grade_points'] === null || $row['grade_points'] === '') {
            continue;
        }
        $gp = (float) $row['grade_points'];
        $counts['total']++;
        if ($gp >= 9.0) {
            $counts['a_plus']++;
        } elseif ($gp >= 8.0) {
            $counts['a']++;
        } elseif ($gp >= 7.0) {
            $counts['b_plus']++;
        } else {
            $counts['b']++;
        }
    }
    return $counts;
}

/**
 * Load anonymized cohort benchmarks scoped strictly to the student's academic program.
 * Never combines unrelated cohorts, excludes the logged-in student, and enforces n >= 3.
 */
function epLoadCohortBenchmarks(
    mysqli $conn,
    int $studentId,
    ?string $program,
    ?string $department = null
): array {
    $prog = trim((string) ($program ?? ''));
    $dept = trim((string) ($department ?? ''));

    // Require an identifiable academic cohort scope; otherwise withhold peer benchmarks
    if ($prog !== '') {
        $scopeColumn = 's.program';
        $scopeValue = $prog;
        $scopeLabel = "{$prog} Program Cohort";
    } elseif ($dept !== '') {
        $scopeColumn = 's.department';
        $scopeValue = $dept;
        $scopeLabel = "{$dept} Department Cohort";
    } else {
        return [
            'course_benchmarks' => [],
            'semester_benchmarks' => [],
            'attendance_benchmarks' => [],
            'scope' => [
                'field' => null,
                'value' => null,
                'label' => 'No Cohort Scope',
                'min_peers' => 3,
                'eligible_peer_count' => 0,
            ],
        ];
    }

    $peerCountRows = epFetchAll(
        $conn,
        "SELECT COUNT(*) AS cnt FROM students s WHERE s.student_id <> ? AND {$scopeColumn} = ?",
        'is',
        [$studentId, $scopeValue]
    );
    $eligiblePeerCount = (int) ($peerCountRows[0]['cnt'] ?? 0);

    if ($eligiblePeerCount < 3) {
        return [
            'course_benchmarks' => [],
            'semester_benchmarks' => [],
            'attendance_benchmarks' => [],
            'scope' => [
                'field' => $prog !== '' ? 'program' : 'department',
                'value' => $scopeValue,
                'label' => $scopeLabel,
                'min_peers' => 3,
                'eligible_peer_count' => $eligiblePeerCount,
            ],
        ];
    }

    $peerCourseRows = epFetchAll(
        $conn,
        "SELECT g.course_id, g.semester,
                COUNT(*) AS cohort_size,
                ROUND(AVG(g.total_marks), 2) AS class_avg_marks,
                MAX(g.total_marks) AS class_max_marks,
                MIN(g.total_marks) AS class_min_marks,
                ROUND(AVG(g.grade_points), 2) AS class_avg_gp,
                ROUND(AVG(g.internal_marks), 2) AS class_avg_internal,
                ROUND(AVG(g.external_marks), 2) AS class_avg_external
         FROM grades g
         JOIN students s ON s.student_id = g.student_id
         WHERE g.student_id <> ?
           AND {$scopeColumn} = ?
           AND g.grade_points IS NOT NULL
         GROUP BY g.course_id, g.semester
         HAVING COUNT(*) >= 3",
        'is',
        [$studentId, $scopeValue]
    );
    $cohortCourseBenchmarks = [];
    foreach ($peerCourseRows as $p) {
        $cohortCourseBenchmarks[(int) $p['course_id'] . ':' . (int) $p['semester']] = $p;
    }

    $peerSemRows = epFetchAll(
        $conn,
        "SELECT sub.semester,
                COUNT(*) AS cohort_size,
                ROUND(AVG(sub.student_sgpa), 2) AS class_avg_sgpa
         FROM (
             SELECT g.student_id, g.semester,
                    SUM(g.grade_points * c.credits) / NULLIF(SUM(c.credits), 0) AS student_sgpa
             FROM grades g
             JOIN courses c ON g.course_id = c.course_id
             JOIN students s ON s.student_id = g.student_id
             WHERE g.student_id <> ?
               AND {$scopeColumn} = ?
               AND g.grade_points IS NOT NULL
             GROUP BY g.student_id, g.semester
         ) sub
         WHERE sub.student_sgpa IS NOT NULL
         GROUP BY sub.semester
         HAVING COUNT(*) >= 3",
        'is',
        [$studentId, $scopeValue]
    );
    $cohortSemAverages = [];
    foreach ($peerSemRows as $p) {
        $cohortSemAverages[(int) $p['semester']] = $p;
    }

    $peerAttRows = epFetchAll(
        $conn,
        "SELECT a.course_id, a.semester,
                COUNT(*) AS cohort_size,
                ROUND(AVG((a.classes_attended / a.total_classes) * 100), 2) AS class_avg_attendance
         FROM attendance a
         JOIN students s ON s.student_id = a.student_id
         WHERE a.student_id <> ?
           AND {$scopeColumn} = ?
           AND a.total_classes > 0
         GROUP BY a.course_id, a.semester
         HAVING COUNT(*) >= 3",
        'is',
        [$studentId, $scopeValue]
    );
    $cohortAttBenchmarks = [];
    foreach ($peerAttRows as $p) {
        $cohortAttBenchmarks[(int) $p['course_id'] . ':' . (int) $p['semester']] = $p;
    }

    return [
        'course_benchmarks' => $cohortCourseBenchmarks,
        'semester_benchmarks' => $cohortSemAverages,
        'attendance_benchmarks' => $cohortAttBenchmarks,
            'scope' => [
            'field' => $prog !== '' ? 'program' : 'department',
            'value' => $scopeValue,
            'label' => $scopeLabel,
            'min_peers' => 3,
            'eligible_peer_count' => $eligiblePeerCount,
        ],
    ];
}

/**
 * Analyze Assignment Completion & Scores vs. Academic Course Performance on matched (course_id, semester) pairs.
 * Requires >= 3 matched pairs with variance before computing Pearson r; never fabricates overlap.
 */
function epAnalyzeAssignmentsVsPerformance(array $gradeRows, array $assignmentRows, array $assignmentAnalytics = []): array {
    $summary = $assignmentAnalytics['summary'] ?? [];
    $timeliness = $assignmentAnalytics['timeliness'] ?? [];
    $totalAssigned = (int) ($summary['total_assigned'] ?? count($assignmentRows));
    $completedCount = (int) ($summary['completed_count'] ?? 0);
    $gradedCount = (int) ($summary['graded_count'] ?? 0);
    $completionRate = $totalAssigned > 0 ? round(($completedCount / $totalAssigned) * 100, 1) : null;

    if ($totalAssigned === 0) {
        return [
            'status' => 'no_assignments',
            'paired_count' => 0,
            'completion_rate' => null,
            'avg_assignment_score_pct' => null,
            'pearson_r' => null,
            'strength' => null,
            'direction' => null,
            'points' => [],
            'note' => 'No assignments are currently published for your enrolled courses, so assignment-to-grade impact cannot be evaluated yet.',
        ];
    }

    // Group assignment stats by course_id:semester
    $byCourseSem = [];
    foreach ($assignmentRows as $row) {
        $key = (int) $row['course_id'] . ':' . (int) $row['semester'];
        if (!isset($byCourseSem[$key])) {
            $byCourseSem[$key] = [
                'course_code' => $row['course_code'],
                'course_name' => $row['course_name'],
                'semester' => (int) $row['semester'],
                'assigned' => 0,
                'completed' => 0,
                'score_pcts' => [],
            ];
        }
        $byCourseSem[$key]['assigned']++;
        $st = $row['submission_status'] ?? $row['status'] ?? 'pending';
        if (in_array($st, ['submitted', 'graded'], true)) {
            $byCourseSem[$key]['completed']++;
        }
        $maxMarks = (float) ($row['max_marks'] ?? 0);
        if ($st === 'graded' && $row['marks_obtained'] !== null && $maxMarks > 0) {
            $byCourseSem[$key]['score_pcts'][] = ((float) $row['marks_obtained'] / $maxMarks) * 100;
        }
    }

    $points = [];
    $completionSeries = [];
    $marksSeries = [];
    $assignSems = [];
    $gradeSems = [];

    foreach ($assignmentRows as $row) {
        $assignSems[] = (int) $row['semester'];
    }
    foreach ($gradeRows as $g) {
        $gradeSems[] = (int) $g['semester'];
        $key = (int) $g['course_id'] . ':' . (int) $g['semester'];
        if (!isset($byCourseSem[$key]) || $g['total_marks'] === null) {
            continue;
        }
        $bucket = $byCourseSem[$key];
        $courseCompRate = $bucket['assigned'] > 0
            ? round(($bucket['completed'] / $bucket['assigned']) * 100, 1)
            : 0.0;
        $courseAvgScore = !empty($bucket['score_pcts'])
            ? round(array_sum($bucket['score_pcts']) / count($bucket['score_pcts']), 1)
            : null;
        $marks = (float) $g['total_marks'];

        $completionSeries[] = $courseCompRate;
        $marksSeries[] = $marks;
        $points[] = [
            'course_code' => $g['course_code'],
            'course_name' => $g['course_name'],
            'semester' => (int) $g['semester'],
            'completion_rate' => $courseCompRate,
            'avg_assignment_score_pct' => $courseAvgScore,
            'total_marks' => $marks,
            'grade' => $g['grade'],
        ];
    }

    $corr = epComputePearsonCorrelation($completionSeries, $marksSeries);
    $avgScorePct = $timeliness['avg_score_pct'] ?? null;

    $note = null;
    if ($corr['status'] === 'insufficient_overlap') {
        $assignSems = array_values(array_unique($assignSems));
        $gradeSems = array_values(array_unique($gradeSems));
        sort($assignSems);
        sort($gradeSems);
        if (empty($points) && !empty($assignSems) && !empty($gradeSems)) {
            $note = 'No overlapping course-semester records exist yet: assignments are in Semester(s) '
                . implode(', ', $assignSems) . ', while finalized grades exist for Semester(s) '
                . implode(', ', $gradeSems) . '. Current completion rate is ' . $completionRate . '% (' . $completedCount . '/' . $totalAssigned . ').';
        } else {
            $note = $corr['reason'];
        }
    } elseif ($corr['status'] === 'zero_variance') {
        $note = $corr['reason'];
    } else {
        $note = sprintf(
            '%s %s relationship (r = %.2f) between course assignment completion rate and final marks across %d matched course-semester records.',
            $corr['strength'],
            strtolower((string) $corr['direction']),
            $corr['r'],
            $corr['paired_count']
        );
    }

    return [
        'status' => $corr['status'],
        'paired_count' => count($points),
        'total_assigned' => $totalAssigned,
        'completed_count' => $completedCount,
        'graded_count' => $gradedCount,
        'completion_rate' => $completionRate,
        'avg_assignment_score_pct' => $avgScorePct,
        'pearson_r' => $corr['r'],
        'strength' => $corr['strength'],
        'direction' => $corr['direction'],
        'points' => $points,
        'note' => $note,
    ];
}

/**
 * Summarize structured factors measurably influencing the student's academic performance.
 */
function epAnalyzePerformanceFactors(
    array $trends,
    array $gradedCourses,
    array $attendanceCourses,
    array $assignments,
    array $cohortScope,
    array $correlation = []
): array {
    $factors = [];

    // 1. Attendance Eligibility & Consistency Factor
    if (!empty($attendanceCourses)) {
        $totalClasses = 0;
        $totalAttended = 0;
        $below75 = 0;
        foreach ($attendanceCourses as $att) {
            $totalClasses += (int) ($att['total_classes'] ?? 0);
            $totalAttended += (int) ($att['classes_attended'] ?? 0);
            if ((float) ($att['percentage'] ?? 0) < 75.0) {
                $below75++;
            }
        }
        $overallAtt = $totalClasses > 0 ? round(($totalAttended / $totalClasses) * 100, 2) : 0.0;
        $impact = $below75 > 0 || $overallAtt < 75.0 ? 'watch' : ($overallAtt >= 85.0 ? 'positive' : 'neutral');
        $factors[] = [
            'factor' => 'Attendance Regularity',
            'impact' => $impact,
            'metric' => $overallAtt . '% overall (' . count($attendanceCourses) . ' courses)',
            'detail' => $below75 > 0
                ? $below75 . ' course(s) are currently below the 75% eligibility threshold, risking exam/internal penalties.'
                : ($overallAtt >= 85.0
                    ? 'All tracked courses meet policy minimums with healthy eligibility buffers.'
                    : 'All tracked courses meet the 75% floor, though staying above 85% provides stronger exam-prep continuity.'),
        ];
    } else {
        $factors[] = [
            'factor' => 'Attendance Regularity',
            'impact' => 'unavailable',
            'metric' => 'No current-semester records',
            'detail' => 'No attendance logs exist for the active semester yet.',
        ];
    }

    // 2. Internal vs. External Assessment Balance
    if (!empty($gradedCourses)) {
        $intSum = 0.0;
        $extSum = 0.0;
        foreach ($gradedCourses as $gc) {
            $intSum += (float) ($gc['internal_marks'] ?? 0);
            $extSum += (float) ($gc['external_marks'] ?? 0);
        }
        $n = count($gradedCourses);
        $avgInt = round($intSum / $n, 1);
        $avgExt = round($extSum / $n, 1);
        $gap = round($avgInt - $avgExt, 1);
        $impact = abs($gap) <= 5.0 && ($avgInt + $avgExt) >= 75.0 ? 'positive' : (abs($gap) > 8.0 ? 'watch' : 'neutral');
        $factors[] = [
            'factor' => 'Internal vs. External Assessment Balance',
            'impact' => $impact,
            'metric' => 'Avg Internal ' . $avgInt . '/50 vs External ' . $avgExt . '/50',
            'detail' => abs($gap) > 5.0
                ? ($gap > 0
                    ? 'Coursework/internal scores average ' . abs($gap) . ' marks higher than end-semester exams — focus on cumulative exam revision.'
                    : 'End-semester exam scores average ' . abs($gap) . ' marks higher than internals — steady lab/quiz consistency can lift totals further.')
                : 'Continuous internal assessments and end-semester exam performance are well balanced across ' . $n . ' graded courses.',
        ];
    } else {
        $factors[] = [
            'factor' => 'Internal vs. External Assessment Balance',
            'impact' => 'unavailable',
            'metric' => 'No graded courses yet',
            'detail' => 'Assessment balance will compute once finalized grades are published.',
        ];
    }

    // 3. Assignment Engagement & Timeliness
    $summary = $assignments['summary'] ?? [];
    $totalAssigned = (int) ($summary['total_assigned'] ?? 0);
    if ($totalAssigned > 0) {
        $completed = (int) ($summary['completed_count'] ?? 0);
        $overdue = (int) ($summary['overdue_count'] ?? 0);
        $compPct = round(($completed / $totalAssigned) * 100, 1);
        $impact = $overdue > 0 ? 'watch' : ($compPct >= 80.0 ? 'positive' : 'neutral');
        $factors[] = [
            'factor' => 'Assignment Completion & Deadlines',
            'impact' => $impact,
            'metric' => $completed . '/' . $totalAssigned . ' completed (' . $compPct . '%)',
            'detail' => $overdue > 0
                ? $overdue . ' assignment(s) are overdue and need immediate submission to protect internal marks.'
                : ($completed === $totalAssigned
                    ? 'All active coursework assignments have been submitted.'
                    : ($totalAssigned - $completed) . ' active assignment(s) remain open before their due dates.'),
        ];
    } else {
        $factors[] = [
            'factor' => 'Assignment Completion & Deadlines',
            'impact' => 'unavailable',
            'metric' => '0 active assignments',
            'detail' => 'No published assignments exist for enrolled courses yet.',
        ];
    }

    // 4. Semester Trajectory & Cohort Relative Standing
    $trendInfo = $trends['trend'] ?? [];
    $direction = $trendInfo['direction'] ?? 'insufficient_data';
    $delta = $trendInfo['delta'] ?? null;
    $peerCount = (int) ($cohortScope['eligible_peer_count'] ?? 0);
    $scopeLabel = $cohortScope['label'] ?? 'Program Cohort';
    $impact = ($direction === 'up' || ($delta !== null && $delta >= 0)) ? 'positive' : ($direction === 'down' ? 'watch' : 'neutral');
    $corrStatus = $correlation['status'] ?? 'insufficient_overlap';

    $factors[] = [
        'factor' => 'Multi-Semester SGPA Trajectory & Program Benchmark',
        'impact' => $direction === 'insufficient_data' ? 'unavailable' : $impact,
        'metric' => $delta !== null
            ? 'SGPA change ' . ($delta >= 0 ? '+' : '') . $delta . ' (' . ($direction === 'up' ? 'Improving' : ($direction === 'down' ? 'Declining' : 'Stable')) . ')'
            : 'CGPA ' . number_format((float) ($trends['cgpa'] ?? 0), 2) . '/10',
        'detail' => sprintf(
            'Evaluated against %d peer(s) in %s%s.',
            $peerCount,
            $scopeLabel,
            $corrStatus === 'ok' && isset($correlation['pearson_r'])
                ? sprintf('; attendance-marks Pearson r = %.2f', $correlation['pearson_r'])
                : '; attendance-grade overlap across shared semesters is not yet sufficient for statistical regression'
        ),
    ];

    return $factors;
}
?>
