-- EduPulse Migration 001: Assignments, Submissions, and Analytics Indexes
-- Safe, idempotent, non-destructive migration for MySQL 8.0+

SET NAMES utf8mb4;

-- 1. Assignments table (course + semester scoped)
CREATE TABLE IF NOT EXISTS `assignments` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `semester` int NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text,
  `due_date` datetime NOT NULL,
  `max_marks` decimal(6,2) NOT NULL DEFAULT 20.00,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `created_by_admin_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`assignment_id`),
  KEY `idx_assignments_course_sem` (`course_id`, `semester`),
  KEY `idx_assignments_due_date` (`due_date`),
  CONSTRAINT `assignments_ibfk_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- 2. Assignment submissions table (student-scoped tracking + timeliness)
CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `submission_id` int NOT NULL AUTO_INCREMENT,
  `assignment_id` int NOT NULL,
  `student_id` int NOT NULL,
  `status` enum('pending','in_progress','submitted','graded') NOT NULL DEFAULT 'pending',
  `progress_pct` tinyint unsigned NOT NULL DEFAULT 0,
  `submission_text` text,
  `file_path` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `marks_obtained` decimal(6,2) DEFAULT NULL,
  `feedback` text,
  `graded_by_admin_id` int DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`submission_id`),
  UNIQUE KEY `uniq_assignment_student` (`assignment_id`, `student_id`),
  KEY `idx_submissions_student_status` (`student_id`, `status`),
  KEY `idx_submissions_submitted_at` (`submitted_at`),
  CONSTRAINT `submissions_ibfk_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`assignment_id`) ON DELETE CASCADE,
  CONSTRAINT `submissions_ibfk_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `submissions_ibfk_admin` FOREIGN KEY (`graded_by_admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- 3. Composite index on grades(course_id, semester) for anonymized cohort benchmarks
SET @grades_idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'grades'
    AND INDEX_NAME = 'idx_grades_course_sem'
);
SET @sql_grades_idx := IF(
  @grades_idx_exists = 0,
  'ALTER TABLE `grades` ADD KEY `idx_grades_course_sem` (`course_id`, `semester`)',
  'SELECT "idx_grades_course_sem already exists" AS migration_info'
);
PREPARE stmt_grades_idx FROM @sql_grades_idx;
EXECUTE stmt_grades_idx;
DEALLOCATE PREPARE stmt_grades_idx;

-- 4. Composite index on attendance(course_id, semester) for anonymized cohort benchmarks
SET @att_idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'attendance'
    AND INDEX_NAME = 'idx_attendance_course_sem'
);
SET @sql_att_idx := IF(
  @att_idx_exists = 0,
  'ALTER TABLE `attendance` ADD KEY `idx_attendance_course_sem` (`course_id`, `semester`)',
  'SELECT "idx_attendance_course_sem already exists" AS migration_info'
);
PREPARE stmt_att_idx FROM @sql_att_idx;
EXECUTE stmt_att_idx;
DEALLOCATE PREPARE stmt_att_idx;
