-- Migration 002: Academic Documents Storage Metadata
-- Safe, idempotent migration preserving all existing data.

CREATE TABLE IF NOT EXISTS academic_documents (
    document_id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT DEFAULT NULL,
    semester TINYINT DEFAULT NULL,
    doc_type ENUM('transcript','certificate','marksheet','project_report','id_proof','other') NOT NULL DEFAULT 'other',
    title VARCHAR(200) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    storage_key VARCHAR(255) NOT NULL,
    storage_provider ENUM('local','azure_blob') NOT NULL DEFAULT 'local',
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (document_id),
    KEY idx_acad_docs_student_time (student_id, uploaded_at),
    KEY idx_acad_docs_course (course_id),
    KEY idx_acad_docs_type (doc_type),
    CONSTRAINT fk_acad_docs_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_acad_docs_course
        FOREIGN KEY (course_id) REFERENCES courses (course_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

