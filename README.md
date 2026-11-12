# EduPulse – Integrated Student Academic Performance Management Platform

EduPulse is a role-based academic performance and coursework portal built with **PHP 8.2**, **MySQL 8.0**, **Bootstrap 5.3**, **Font Awesome**, and **Chart.js**, packaged with **Docker** and **Docker Compose**.

---

## Key Features

### 1. Student Performance Analytics (`analytics.html`)
- **Weighted SGPA & CGPA Summary**: Computes semester-wise SGPA and cumulative CGPA strictly from `grades` weighted by `courses.credits`.
- **Performance Trends & Course Comparison**: Interactive Chart.js visualizations for semester trends, course marks breakdown (`Internal / 50 + External / 50 = Total / 100`), and attendance percentages.
- **Attendance Eligibility Buffers**: Exact recovery targets (`ceil((0.75 * total - attended) / 0.25)`) and safe-absence allowances (`floor(attended / 0.75 - total)`).
- **Explainable Strengths, Weaknesses & Recommendations**: Data-driven feedback grounded directly in the authenticated student's records.
- **Privacy-Preserving Cohort Benchmarks**: Scoped to the student's academic program (`program`), excludes the logged-in student, requires at least **3 eligible peers** (`COUNT(*) >= 3`) before displaying any cohort average, and never exposes classmate identities or individual scores.
- **Attendance-Grade Correlation**: Uses Pearson correlation only when overlapping `(course_id, semester)` pairs exist between `grades` and `attendance`; displays an honest insufficient-data explanation when no overlapping records exist.

### 2. Assignment Management System (`assignments.html` & `admin/assignments.html`)
- **Student Workflow (`assignments.html`, `js/assignments.js`)**:
  - View assignments for enrolled courses filtered by status (`pending`, `in_progress`, `submitted`, `graded`), course, semester, or search query.
  - Update completion progress (`0–100%`) and save draft notes.
  - Submit assignments with notes and optional file uploads stored under `uploads/assignments/`.
  - View awarded marks and instructor feedback once graded.
- **Admin Workflow (`admin/assignments.html`, `admin/js/assignments.js`)**:
  - Create, edit, and delete course assignments (`backend/api/admin/manage_assignments.php`).
  - View all enrolled students' submission statuses, notes, and uploaded files per assignment.
  - Update submission status (`pending`, `in_progress`, `submitted`, `graded`) and award marks & feedback (`backend/api/admin/grade_submission.php`).

### 3. Enhanced Student Pages
- **Dashboard (`dashboard.html`)**: Consolidated single-request payload (`backend/api/get_dashboard.php`), real weighted attendance and CGPA KPIs, SGPA trend and grade distribution charts, real alerts dropdown, and upcoming assignment deadlines with honest empty states.
- **Grades (`grades.html`)**: Accurate `/50` internal, `/50` external, `/100` total display labels, working `#chartFilter` (`SGPA & CGPA Trend` vs `Semester Avg Marks (%)`), semester dropdown, What-If CGPA simulator, and CSV transcript export.
- **Attendance (`attendance.html`)**: Dynamic semester (`?semester=`) and course (`?course_id=`) filters, weighted attendance calculations, eligibility recovery planner table, CSV export, and honest empty states when selected semesters have no attendance records.
- **Ask EduPulse Chatbot (`js/chatbot.js`, `backend/api/ask_edupulse.php`, `backend/includes/chatbot_nlp.php`)**: Local TF-IDF + intent-matching assistant (no external paid LLM APIs) providing contextual answers on grades, attendance, courses, assignments, and analytics insights while blocking cross-student data queries.

---

## Technology Stack

- **Backend**: PHP 8.2 (Apache) with `mysqli` prepared statements
- **Database**: MySQL 8.0 (`student_portal`, persistent Docker volume `mysql_data`)
- **Frontend**: HTML5, CSS3, Vanilla ES6+ JavaScript, Bootstrap 5.3, Font Awesome 6.4, Chart.js 4.4.4
- **Containerization**: Docker & Docker Compose
- **CI**: GitHub Actions (`.github/workflows/ci.yml`)

---

## Quick Start (Docker Compose)

1. **Ensure `.env` exists** in the project root with your MySQL credentials matching `docker-compose.yml` (`DB_PASSWORD`).
2. **Start containers without resetting persistent data**:
   ```bash
   docker compose up -d --build
   ```
3. **Open the application**:
   - Student & Admin Login: `http://localhost:8080`

> **Important**: Never run `docker compose down -v` on an active environment, as `-v` deletes the `mysql_data` volume.

---

## Database Backup & Idempotent Migration

If running against an already initialized `mysql_data` volume (where `/docker-entrypoint-initdb.d` does not re-run), always back up first and apply migrations safely:

1. **Create a backup**:
   ```bash
   docker exec student-portal-mysql mysqldump -u root -p"$DB_PASSWORD" student_portal > backup_pre_migration.sql
   ```
2. **Apply safe, idempotent schema migration**:
   ```bash
   docker exec -i student-portal-mysql mysql -u root -p"$DB_PASSWORD" student_portal < database/migrations/001_analytics_and_assignments.sql
   ```

This creates `assignments`, `assignment_submissions`, and supporting performance indexes (`idx_grades_course_sem`, `idx_attendance_course_sem`) without altering existing student, grade, enrollment, attendance, or activity records.

---

## Key Backend Files & API Endpoints

### Shared Helpers
- `backend/config/database.php` — Env-driven MySQL connection supporting TLS (`DB_SSL_MODE`, `DB_SSL_CA`) for Azure Database for MySQL Flexible Server
- `backend/includes/functions.php` — Session & role guards (`requireStudentApi`, `requireAdminApi`), JSON response helpers, input sanitization (`epCleanText`), and prepared statement helpers (`epFetchAll`, `epFetchOne`)
- `backend/includes/analytics_helpers.php` — Weighted SGPA/CGPA calculations, grade distribution, attendance eligibility buffers, attendance-vs-grade & assignment-vs-performance correlations, performance factors, explainable recommendations, and program-scoped cohort benchmarks
- `backend/includes/storage_service.php` — Storage abstraction supporting `local` disk and `azure` Blob Storage (SharedKey HMAC-SHA256 & SAS) with MIME/size validation
- `backend/includes/telemetry.php` — Env-driven Azure Application Insights telemetry client with automatic PII/secret redaction
- `backend/includes/chatbot_nlp.php` — Local TF-IDF + keyword intent classifier for Ask EduPulse

### Student Endpoints (Require `student` session)
- `GET /backend/api/get_dashboard.php`
- `GET /backend/api/get_performance_analytics.php`
- `GET /backend/api/get_grades.php`
- `GET /backend/api/get_attendance.php?semester=<all|int>&course_id=<all|int>`
- `GET /backend/api/get_assignments.php?status=<all|...>&course_id=<all|int>&semester=<all|int>`
- `POST /backend/api/submit_assignment.php` (`action=progress|submit`, supports file upload)
- `GET /backend/api/get_documents.php` | `POST /backend/api/upload_document.php` | `POST /backend/api/delete_document.php`
- `GET /backend/api/download_document.php?type=document|assignment|profile&id=...` (RBAC-enforced file download proxy)
- `POST /backend/api/ask_edupulse.php`

### Operations & Admin Endpoints
- `GET /backend/api/health.php` — Application, MySQL schema, storage driver, and telemetry health probe
- `GET /backend/api/admin/get_dashboard_stats.php`
- `GET /backend/api/admin/get_analytics.php`
- `GET|POST /backend/api/admin/manage_assignments.php` (`list`, `submissions`, `create`, `update`, `delete`)
- `POST /backend/api/admin/grade_submission.php` (update status, award marks & feedback)

---

## Cloud & CI/CD Documentation
- `docs/ACADEMIC_ANALYTICS_METHODOLOGY.md` — Analytics formulas, statistical thresholds, and cohort scoping rules
- `docs/AZURE_MYSQL_MIGRATION.md` & `scripts/migrate.sh` — Safe Azure MySQL Flexible Server TLS migration & rollback guide
- `docs/AZURE_DEPLOYMENT.md`, `.env.azure.example`, `infra/containerapp.yaml` — Azure Container Registry, Container Apps, Blob Storage, and Application Insights deployment guides
- `.github/workflows/ci.yml` — Multi-stage CI/CD pipeline: PHP/JS syntax lint, secret scan, Docker Compose integration tests, and OIDC-authenticated Azure deployment gated by `AZURE_DEPLOY_ENABLED`


