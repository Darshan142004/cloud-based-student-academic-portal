# EduPulse Academic Analytics Methodology

All analytics on `analytics.html` and `backend/api/get_performance_analytics.php` (implemented in `backend/includes/analytics_helpers.php`) are computed strictly from real MySQL records without synthetic data generation.

## 1. SGPA & CGPA Calculations
- **Course Grade Points**: Uses the recorded `grades.grade_points` (10-point scale) joined with `courses.credits`.
- **Semester GPA (SGPA)**: Credit-weighted average within each semester:
  $$\text{SGPA}_s = \frac{\sum_{c \in s} (\text{grade\_points}_c \times \text{credits}_c)}{\sum_{c \in s} \text{credits}_c}$$
- **Cumulative GPA (CGPA)**: Credit-weighted average across all graded courses across all completed semesters:
  $$\text{CGPA} = \frac{\sum_{\text{all } c} (\text{grade\_points}_c \times \text{credits}_c)}{\sum_{\text{all } c} \text{credits}_c}$$
- **Trend Direction**: Compares the latest graded semester SGPA against the immediately preceding graded semester (`up` if delta `>= +0.05`, `down` if `<= -0.05`, `stable` otherwise, or `insufficient_data` when fewer than 2 graded semesters exist).

## 2. Attendance Thresholds & Recovery / Buffer Math
- Policy thresholds are `75%` (minimum exam eligibility floor) and `85%` (safe target).
- For current-semester course attendance `(classes_attended, total_classes)`:
  - **Safe absences remaining** (`>= target`): $\lfloor \frac{a}{p} - t \rfloor$ where $p \in \{0.75, 0.85\}$.
  - **Classes needed consecutively** (`< target`): $\lceil \frac{p \cdot t - a}{1 - p} \rceil$.

## 3. Course & Assessment Comparison
- **Internal vs. External Split**: Evaluates `internal_marks` (/50) vs `external_marks` (/50) alongside `total_marks` (/100). Gaps > 5 marks trigger diagnostic commentary on coursework vs. cumulative exam performance.

## 4. Program-Scoped Cohort Benchmarks (Privacy & Sample-Size Guardrails)
- Benchmarks are strictly scoped to the logged-in student's `program` (falling back to `department` only when `program` is absent) so unrelated academic programs are never combined.
- The logged-in student is always excluded (`student_id <> ?`).
- A benchmark is computed and shown **only when `HAVING COUNT(*) >= 3` eligible peers** exist in that course-semester or semester bucket.
- Only aggregate class means/min/max and cohort sizes are exposed; peer identities and individual marks are never returned.

## 5. Statistical Correlations (Attendance vs. Marks & Assignment Completion vs. Marks)
- Observations are paired strictly on identical `(course_id, semester)` keys.
- Pearson correlation coefficient $r$ is computed only when:
  1. `paired_count >= 3` matched course-semester records exist, AND
  2. Both X and Y series have non-zero variance.
- When semesters are disjoint (e.g., finalized grades exist for Semesters 1–4 while current attendance/assignments exist for Semester 5) or variance is zero, the endpoint returns an explicit `insufficient_overlap`, `no_assignments`, or `zero_variance` status rendered as an honest empty state.

## 6. Performance Factors & Explainable Recommendations
- **Performance Factors** evaluate four dimensions: Attendance Regularity, Internal vs. External Balance, Assignment Engagement/Overdue Risk, and SGPA Trajectory vs. Cohort Standing.
- **Explainable Recommendations** attach the concrete trigger metric (`evidence` chips), plain-language explanation (`why`), priority rank (`high`, `medium`, `low`), and direct action URL.

