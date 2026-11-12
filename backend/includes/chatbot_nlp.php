<?php
/**
 * Lightweight pure-PHP NLP intent classification, entity extraction,
 * and follow-up context helpers for Ask EduPulse.
 */

/**
 * Expand contractions and normalize text into clean space-separated tokens.
 */
function normalizeAcademicText(string $text): string {
    $q = strtolower(trim($text));
    $contractions = [
        "what's" => 'what is',
        "who's" => 'who is',
        "how's" => 'how is',
        "where's" => 'where is',
        "it's" => 'it is',
        "that's" => 'that is',
        "i'm" => 'i am',
        "i've" => 'i have',
        "i'll" => 'i will',
        "can't" => 'cannot',
        "won't" => 'will not',
        "don't" => 'do not',
        "didn't" => 'did not',
        "isn't" => 'is not',
        "aren't" => 'are not'
    ];
    $q = strtr($q, $contractions);
    // Preserve percentages and decimals while stripping other punctuation
    $q = preg_replace('/[^a-z0-9%.]+/', ' ', $q);
    return trim((string) preg_replace('/\s+/', ' ', (string) $q));
}

/**
 * Return domain vocabulary for typo correction and canonical token mapping.
 */
function getAcademicSynonymMap(): array {
    return [
        'attending' => 'attendance',
        'attended' => 'attendance',
        'attend' => 'attendance',
        'absences' => 'absent',
        'absence' => 'absent',
        'missing' => 'miss',
        'missed' => 'miss',
        'skipping' => 'skip',
        'skipped' => 'skip',
        'bunking' => 'bunk',
        'bunked' => 'bunk',
        'lectures' => 'classes',
        'lecture' => 'classes',
        'class' => 'classes',
        'eligibility' => 'eligible',
        'subjects' => 'courses',
        'subject' => 'course',
        'enrolled' => 'courses',
        'professor' => 'faculty',
        'professors' => 'faculty',
        'teacher' => 'faculty',
        'teachers' => 'faculty',
        'instructor' => 'faculty',
        'instructors' => 'faculty',
        'lecturer' => 'faculty',
        'teaches' => 'faculty',
        'teaching' => 'faculty',
        'taught' => 'faculty',
        'prof' => 'faculty',
        'marks' => 'marks',
        'mark' => 'marks',
        'scores' => 'score',
        'scored' => 'score',
        'scoring' => 'score',
        'grades' => 'grade',
        'graded' => 'grade',
        'results' => 'result',
        'performing' => 'performance',
        'activities' => 'activity',
        'extracurriculars' => 'extracurricular',
        'certificates' => 'certification',
        'certifications' => 'certification',
        'certificate' => 'certification',
        'achievements' => 'achievement',
        'assignments' => 'assignment',
        'deadlines' => 'deadline',
        'submissions' => 'submission',
        'tasks' => 'task',
        'semesters' => 'semester',
        'sem' => 'semester',
        'credits' => 'credit',
        'units' => 'syllabus',
        'topics' => 'syllabus',
        'percentage' => 'percent',
        'pct' => 'percent',
        'gpa' => 'cgpa',
        'strengths' => 'strength',
        'weaknesses' => 'weakness',
        'weak' => 'weakness',
        'strong' => 'strength',
        'recommendations' => 'recommendation',
        'recommend' => 'recommendation',
        'insights' => 'insight',
        'analyze' => 'analytics',
        'analysis' => 'analytics',
        'compare' => 'benchmark',
        'class' => 'cohort',
        'peer' => 'cohort',
        'peers' => 'cohort',
        'overdue' => 'deadline',
        'timeliness' => 'submission'
    ];
}

/**
 * Known vocabulary words eligible for Levenshtein typo correction.
 */
function getAcademicVocabulary(): array {
    static $vocab = null;
    if ($vocab !== null) {
        return $vocab;
    }
    $base = [
        'attendance', 'absent', 'eligible', 'shortage', 'warning', 'critical', 'recover',
        'recovery', 'classes', 'cgpa', 'sgpa', 'grade', 'marks', 'score', 'internal',
        'external', 'result', 'transcript', 'performance', 'summary', 'snapshot',
        'overview', 'course', 'courses', 'faculty', 'professor', 'teacher', 'syllabus',
        'credit', 'extracurricular', 'activity', 'activities', 'certification',
        'achievement', 'volunteer', 'hackathon', 'competition', 'profile', 'enrollment',
        'department', 'program', 'semester', 'admission', 'assignment', 'deadline',
        'submission', 'homework', 'database', 'networks', 'operating', 'software',
        'programming', 'physics', 'chemistry', 'mathematics', 'discrete', 'electronics',
        'algorithms', 'computation', 'structures', 'development', 'organization',
        'strength', 'weakness', 'recommendation', 'insight', 'analytics', 'correlation',
        'benchmark', 'cohort', 'average', 'improve', 'focus'
    ];
    $vocab = array_values(array_unique(array_merge($base, array_keys(getAcademicSynonymMap()))));
    return $vocab;
}

/**
 * Tokenize normalized text with lightweight typo correction and synonym canonicalization.
 */
function tokenizeAcademicText(string $normalizedText): array {
    $synonyms = getAcademicSynonymMap();
    $vocab = getAcademicVocabulary();
    $rawTokens = preg_split('/\s+/', $normalizedText, -1, PREG_SPLIT_NO_EMPTY);
    $tokens = [];

    foreach ($rawTokens as $tok) {
        $clean = rtrim($tok, '.');
        if ($clean === '') {
            continue;
        }
        // Strip trailing % for token classification while preserving raw text for numeric parsing
        $word = rtrim($clean, '%');

        if (!ctype_digit($word) && strlen($word) >= 5 && !isset($synonyms[$word]) && !in_array($word, $vocab, true)) {
            $maxDist = strlen($word) >= 9 ? 2 : 1;
            $bestMatch = null;
            $bestDist = $maxDist + 1;
            foreach ($vocab as $candidate) {
                if (abs(strlen($candidate) - strlen($word)) > $maxDist) {
                    continue;
                }
                $d = levenshtein($word, $candidate);
                if ($d < $bestDist) {
                    similar_text($word, $candidate, $simPct);
                    if ($simPct >= 75.0) {
                        $bestDist = $d;
                        $bestMatch = $candidate;
                    }
                }
            }
            if ($bestMatch !== null) {
                $word = $bestMatch;
            }
        }

        if ($word !== '') {
            $tokens[] = $word;
            if (isset($synonyms[$word]) && $synonyms[$word] !== $word) {
                $tokens[] = $synonyms[$word];
            }
        }
    }

    return $tokens;
}

/**
 * Extract unigram + bigram features from a token sequence.
 */
function buildNgramFeatures(array $tokens): array {
    $stop = [
        'the', 'is', 'are', 'am', 'a', 'an', 'of', 'to', 'in', 'for', 'on', 'my',
        'me', 'i', 'do', 'does', 'have', 'has', 'please', 'tell', 'show', 'give',
        'get', 'can', 'what', 'which', 'who', 'how', 'when', 'where', 'why', 'any',
        'about', 'with', 'from', 'that', 'this', 'there', 'all', 'just', 'will',
        'would', 'should', 'could', 'and', 'or', 'many', 'much', 'some', 'more',
        'most', 'very', 'still', 'right', 'now'
    ];
    $filtered = array_values(array_filter($tokens, fn($t) => !in_array($t, $stop, true)));
    if (empty($filtered)) {
        $filtered = $tokens;
    }

    $features = [];
    foreach ($filtered as $t) {
        $features[] = $t;
    }
    $count = count($filtered);
    for ($i = 0; $i < $count - 1; $i++) {
        $features[] = $filtered[$i] . '_' . $filtered[$i + 1];
    }
    return $features;
}

/**
 * Curated training utterances per academic intent for TF-IDF similarity scoring.
 */
function getIntentTrainingUtterances(): array {
    return [
        'greeting' => [
            'hi',
            'hello',
            'hey there',
            'good morning',
            'good afternoon',
            'good evening',
            'help me what can you do',
            'how can you assist me'
        ],
        'assignments_gap' => [
            'what are my upcoming assignments',
            'do i have any homework due',
            'show pending assignment deadlines',
            'when is my next submission due',
            'any pending tasks or assignments',
            'show my assignment marks and feedback',
            'which assignments are graded or overdue'
        ],
        'attendance_eligibility' => [
            'am i eligible for exams with my attendance',
            'which courses are at attendance risk',
            'which subjects are below 85 percent attendance',
            'do i have any attendance shortage or warning',
            'how many classes can i miss safely',
            'can i skip or bunk classes and stay above 75 percent',
            'what happens if i miss 3 classes',
            'how many classes do i need to attend to reach 75 percent',
            'am i safe or critical in attendance',
            'recover low attendance'
        ],
        'attendance_overview' => [
            'how is my attendance',
            'what is my overall attendance percentage',
            'show my subject wise attendance',
            'how many classes have i attended so far',
            'check my class attendance for this semester',
            'attendance report across all courses'
        ],
        'cgpa_sgpa' => [
            'what is my cgpa',
            'show my semester wise sgpa trend and progression',
            'what was my latest and highest sgpa',
            'if i score 9.5 sgpa next semester what will my cgpa be',
            'how can i improve or predict my cgpa',
            'compare my gpa across semesters'
        ],
        'grades_marks' => [
            'show my grades and marks',
            'what were my internal and external marks',
            'show semester 4 grades and results',
            'what was my highest scoring subject',
            'did i fail any course or subject',
            'show my academic transcript',
            'how much did i score in my exams'
        ],
        'courses_faculty' => [
            'show my enrolled courses',
            'what subjects am i taking this semester',
            'who teaches this course and what is faculty email',
            'show course syllabus units and description',
            'how many total credits am i taking',
            'list my subjects and instructors',
            'what courses does a professor teach'
        ],
        'academic_performance' => [
            'how am i doing academically overall',
            'give me my academic summary snapshot',
            'overall status of my studies and performance',
            'overview of my semester progress'
        ],
        'analytics_insights' => [
            'what are my academic strengths and weaknesses',
            'give me explainable performance recommendations and insights',
            'how do i compare to cohort average and program benchmark',
            'which subjects am i weak or strong in',
            'show my performance analytics and attendance grade correlation',
            'what should i focus on to improve my academic performance'
        ],
        'extracurricular' => [
            'show my extracurricular activities',
            'how many activity points have i earned',
            'what clubs sports or hackathons did i participate in',
            'show my ongoing activities and achievements',
            'what certifications or volunteer work do i have'
        ],
        'profile_semester' => [
            'show my student profile details',
            'which semester am i currently in',
            'what is my enrollment id department and program',
            'who am i',
            'what is my admission year email and phone'
        ]
    ];
}

/**
 * Precompute IDF table and normalized TF-IDF vectors for all intent prototypes.
 */
function getIntentTfIdfModel(): array {
    static $model = null;
    if ($model !== null) {
        return $model;
    }

    $corpus = getIntentTrainingUtterances();
    $docs = [];
    $df = [];

    foreach ($corpus as $intent => $utterances) {
        foreach ($utterances as $utt) {
            $norm = normalizeAcademicText($utt);
            $tokens = tokenizeAcademicText($norm);
            $feats = buildNgramFeatures($tokens);
            $docs[] = ['intent' => $intent, 'features' => $feats];
            foreach (array_unique($feats) as $f) {
                $df[$f] = ($df[$f] ?? 0) + 1;
            }
        }
    }

    $nDocs = count($docs);
    $idf = [];
    foreach ($df as $term => $freq) {
        $idf[$term] = log(($nDocs + 1) / ($freq + 1)) + 1.0;
    }

    $vectors = [];
    foreach ($docs as $doc) {
        $tf = array_count_values($doc['features']);
        $vec = [];
        $normSq = 0.0;
        foreach ($tf as $term => $cnt) {
            $weight = (1.0 + log($cnt)) * ($idf[$term] ?? 1.0);
            $vec[$term] = $weight;
            $normSq += $weight * $weight;
        }
        $norm = sqrt($normSq);
        if ($norm > 0) {
            foreach ($vec as $term => $w) {
                $vec[$term] = $w / $norm;
            }
        }
        $vectors[] = ['intent' => $doc['intent'], 'vec' => $vec];
    }

    $model = ['idf' => $idf, 'vectors' => $vectors];
    return $model;
}

/**
 * Score all intents for the normalized query using TF-IDF cosine similarity.
 */
function classifyAcademicIntent(string $normalizedQuery, array $tokens): array {
    $model = getIntentTfIdfModel();
    $idf = $model['idf'];
    $feats = buildNgramFeatures($tokens);
    $tf = array_count_values($feats);

    $qVec = [];
    $normSq = 0.0;
    foreach ($tf as $term => $cnt) {
        if (!isset($idf[$term])) {
            // Out-of-vocabulary terms still penalize vector norm so unrelated chatter stays low-confidence
            $normSq += 1.0;
            continue;
        }
        $w = (1.0 + log($cnt)) * $idf[$term];
        $qVec[$term] = $w;
        $normSq += $w * $w;
    }

    $qNorm = sqrt($normSq);
    if ($qNorm <= 0.0) {
        return ['intent' => 'unknown', 'confidence' => 0.0, 'scores' => []];
    }
    foreach ($qVec as $term => $w) {
        $qVec[$term] = $w / $qNorm;
    }

    $intentMatches = [];
    foreach ($model['vectors'] as $proto) {
        $dot = 0.0;
        foreach ($qVec as $term => $qw) {
            if (isset($proto['vec'][$term])) {
                $dot += $qw * $proto['vec'][$term];
            }
        }
        $intentMatches[$proto['intent']][] = $dot;
    }

    $scores = [];
    foreach ($intentMatches as $intent => $sims) {
        rsort($sims);
        $top1 = $sims[0] ?? 0.0;
        $top2 = $sims[1] ?? $top1;
        $scores[$intent] = round(($top1 * 0.85) + ($top2 * 0.15), 4);
    }

    arsort($scores);
    $bestIntent = array_key_first($scores) ?? 'unknown';
    $bestScore = $scores[$bestIntent] ?? 0.0;

    return [
        'intent' => $bestIntent,
        'confidence' => $bestScore,
        'scores' => $scores
    ];
}

/**
 * Extract explicit or relative semester numbers from text.
 */
function extractSemesterSlot(string $normalizedText, int $currentSemester): ?int {
    $wordNums = [
        'first' => 1, '1st' => 1,
        'second' => 2, '2nd' => 2,
        'third' => 3, '3rd' => 3,
        'fourth' => 4, '4th' => 4,
        'fifth' => 5, '5th' => 5,
        'sixth' => 6, '6th' => 6,
        'seventh' => 7, '7th' => 7,
        'eighth' => 8, '8th' => 8
    ];

    if (preg_match('/\b(?:last|previous|prior)\s+sem(?:ester)?\b/i', $normalizedText)) {
        return max(1, $currentSemester - 1);
    }
    if (preg_match('/\b(?:this|current|present)\s+sem(?:ester)?\b/i', $normalizedText)) {
        return $currentSemester;
    }
    if (preg_match('/\b(?:sem(?:ester)?)\s*[-:#]?\s*(\d{1,2})\b/i', $normalizedText, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)\s*sem(?:ester)?\b/i', $normalizedText, $m)) {
        return (int) $m[1];
    }
    foreach ($wordNums as $word => $num) {
        if (preg_match('/\b' . preg_quote($word, '/') . '\s+sem(?:ester)?\b/i', $normalizedText)) {
            return $num;
        }
    }
    return null;
}

/**
 * Find courses matching a faculty name mentioned in the query.
 */
function findFacultyCourses(string $normalizedText, array $enrollments): array {
    $matched = [];
    $seenIds = [];
    $titles = ['dr', 'prof', 'mr', 'ms', 'mrs'];

    foreach (array_reverse($enrollments) as $c) {
        $fac = strtolower(trim((string) ($c['faculty_name'] ?? '')));
        if ($fac === '') {
            continue;
        }
        $cleanFac = trim((string) preg_replace('/[^a-z\s]+/', ' ', $fac));
        $parts = array_values(array_filter(
            preg_split('/\s+/', $cleanFac, -1, PREG_SPLIT_NO_EMPTY),
            fn($p) => strlen($p) >= 3 && !in_array($p, $titles, true)
        ));

        $hit = false;
        if (str_contains($normalizedText, $cleanFac)) {
            $hit = true;
        } else {
            foreach ($parts as $p) {
                if (strlen($p) >= 5 && preg_match('/\b' . preg_quote($p, '/') . '\b/', $normalizedText)) {
                    $hit = true;
                    break;
                }
            }
        }

        if ($hit && !isset($seenIds[$c['course_id']])) {
            $seenIds[$c['course_id']] = true;
            $matched[] = $c;
        }
    }

    return $matched;
}

/**
 * Find the best matching course referenced by code, name, alias, or distinctive tokens.
 */
function findCourseSlot(string $normalizedText, array $tokens, array $enrollments): ?array {
    $aliases = [
        'dbms' => 'database management systems',
        'cn' => 'computer networks',
        'os' => 'operating systems',
        'se' => 'software engineering',
        'web dev' => 'web development',
        'dsa' => 'data structures',
        'oop' => 'object oriented programming',
        'toc' => 'theory of computation',
        'daa' => 'design analysis of algorithms'
    ];

    $expanded = $normalizedText;
    foreach ($aliases as $abbr => $full) {
        if (preg_match('/\b' . preg_quote($abbr, '/') . '\b/i', $normalizedText)) {
            $expanded .= ' ' . $full;
        }
    }

    $stopWords = [
        'and', 'the', 'for', 'with', 'lab', 'introduction', 'to', 'of',
        'computer', 'science', 'data', 'system', 'systems', 'design',
        'analysis', 'theory', 'digital', 'object', 'oriented', 'management'
    ];

    $ordered = array_reverse($enrollments);
    $bestCourse = null;
    $bestScore = 0;

    foreach ($ordered as $course) {
        $code = strtolower($course['course_code']);
        $name = strtolower(trim((string) preg_replace('/[^a-z0-9\s]+/', ' ', $course['course_name'])));

        if (preg_match('/\b' . preg_quote($code, '/') . '\b/i', $expanded)) {
            return $course;
        }
        if ($name !== '' && str_contains($expanded, $name)) {
            return $course;
        }

        $courseTokens = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $score = 0;
        $expandedTokens = array_unique(array_merge($tokens, preg_split('/\s+/', $expanded, -1, PREG_SPLIT_NO_EMPTY)));

        foreach ($courseTokens as $ct) {
            if (strlen($ct) < 4 || in_array($ct, $stopWords, true)) {
                continue;
            }
            if (in_array($ct, $expandedTokens, true)) {
                $score += 2;
                continue;
            }
            if (strlen($ct) >= 6) {
                foreach ($expandedTokens as $ut) {
                    if (strlen($ut) >= 5 && levenshtein($ct, $ut) <= 1) {
                        $score += 1;
                        break;
                    }
                }
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestCourse = $course;
        }
    }

    return $bestScore >= 2 ? $bestCourse : null;
}

/**
 * Extract all entities, numeric parameters, and follow-up cues from the query.
 */
function extractAcademicEntities(string $normalizedText, array $tokens, array $ctx): array {
    $tokenText = implode(' ', $tokens);
    $isProfileCue = (bool) preg_match('/\b(department|program|admission|enrollment|who am i|profile)\b/', $tokenText);
    $semester = extractSemesterSlot($tokenText, $ctx['current_semester'])
        ?? extractSemesterSlot($normalizedText, $ctx['current_semester']);
    $course = $isProfileCue ? null : findCourseSlot($normalizedText, $tokens, $ctx['enrollments']);
    $facultyCourses = $course === null ? findFacultyCourses($normalizedText, $ctx['enrollments']) : [];

    // Extract "miss N classes" / "skip N lectures" (supports digits and word numbers 1-10)
    $wordToInt = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
        'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10
    ];
    $missCount = null;
    if (preg_match('/\b(?:miss|skip|bunk|absent(?:\s+for)?)\s+(\d{1,2}|one|two|three|four|five|six|seven|eight|nine|ten)\s*(?:more\s+)?(?:class|classes|lecture|lectures)?\b/', $normalizedText, $m)) {
        $rawNum = $m[1];
        $missCount = ctype_digit($rawNum) ? max(1, (int) $rawNum) : ($wordToInt[$rawNum] ?? null);
    }

    // Extract custom attendance target % (e.g. "reach 80%", "get 90 percent")
    $targetAttendance = null;
    if (preg_match('/\b(?:reach|maintain|get|need|target|for|above|at least)\s*(\d{2})\s*(?:%|percent)\b/', $normalizedText, $m)) {
        $val = (int) $m[1];
        if ($val >= 50 && $val <= 99) {
            $targetAttendance = $val;
        }
    }

    // Extract target SGPA for projection
    $targetSgpa = null;
    if (preg_match('/\b(10(?:\.0+)?|[5-9](?:\.\d{1,2})?)\b/', $normalizedText, $m)
        && preg_match('/\b(target|if i|predict|improve|next|reach|goal|score|get)\b/', $normalizedText)) {
        $targetSgpa = (float) $m[1];
    }

    // Detect follow-up pronouns or elliptical continuation cues
    $hasPronoun = (bool) preg_match('/\b(it|its|that|this|those|them|then|same)\b/', $normalizedText);
    $isShortContinuation = count($tokens) <= 5 && (bool) preg_match('/\b(syllabus|attendance|grade|grades|marks|faculty|email|credits|courses|sgpa|what about|how about|and)\b/', $normalizedText);
    $isFollowUp = $hasPronoun || $isShortContinuation;

    return [
        'semester' => $semester,
        'course' => $course,
        'faculty_courses' => $facultyCourses,
        'miss_count' => $missCount,
        'target_attendance' => $targetAttendance,
        'target_sgpa' => $targetSgpa,
        'is_follow_up' => $isFollowUp
    ];
}

/**
 * Retrieve and validate short-lived student-scoped conversational context.
 */
function getChatSessionContext(int $studentId): array {
    $raw = $_SESSION['edupulse_chat_ctx'] ?? null;
    if (!is_array($raw) || (int) ($raw['student_id'] ?? 0) !== $studentId) {
        return [];
    }
    if ((time() - (int) ($raw['updated_at'] ?? 0)) > 900) {
        unset($_SESSION['edupulse_chat_ctx']);
        return [];
    }
    return $raw;
}

/**
 * Persist updated conversational context for follow-up resolution.
 */
function saveChatSessionContext(int $studentId, string $intent, ?array $course, ?int $semester): void {
    $_SESSION['edupulse_chat_ctx'] = [
        'student_id' => $studentId,
        'last_intent' => $intent,
        'last_course_id' => $course ? (int) $course['course_id'] : null,
        'last_semester' => $course ? (int) $course['semester'] : $semester,
        'updated_at' => time()
    ];
}

/**
 * Select up to two distinct, confident intents when a query asks multiple questions.
 */
function detectConfidentIntents(array $classification, string $normalizedText): array {
    $scores = $classification['scores'] ?? [];
    if (empty($scores)) {
        return [];
    }

    $topIntent = array_key_first($scores);
    $topScore = $scores[$topIntent] ?? 0.0;
    if ($topScore < 0.22) {
        return [];
    }

    $selected = [$topIntent];

    // Map sibling intents to the same family so we don't duplicate near-identical answers
    $familyOf = [
        'attendance_overview' => 'attendance',
        'attendance_eligibility' => 'attendance',
        'cgpa_sgpa' => 'grades',
        'grades_marks' => 'grades'
    ];
    $excludedSecondaries = ['greeting', 'unknown', 'academic_performance'];

    // Split compound queries into clauses so weak shared words don't trigger false secondary intents
    $clauses = preg_split('/\b(?:and|also|plus|as well as)\b/', $normalizedText, -1, PREG_SPLIT_NO_EMPTY);
    if (count($clauses) >= 2 && !in_array($topIntent, $excludedSecondaries, true)) {
        $topFamily = $familyOf[$topIntent] ?? $topIntent;
        foreach ($clauses as $clause) {
            $clauseNorm = trim($clause);
            if ($clauseNorm === '') {
                continue;
            }
            $clauseTokens = tokenizeAcademicText($clauseNorm);
            $clauseClass = classifyAcademicIntent($clauseNorm, $clauseTokens);
            $cand = $clauseClass['intent'];
            $candScore = $clauseClass['confidence'];
            $candFamily = $familyOf[$cand] ?? $cand;
            if ($cand !== $topIntent
                && $candFamily !== $topFamily
                && !in_array($cand, $excludedSecondaries, true)
                && $candScore >= 0.32
            ) {
                $selected[] = $cand;
                break;
            }
        }
    }

    return $selected;
}
?>
