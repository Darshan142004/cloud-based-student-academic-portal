<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/storage_service.php';

$student_id = requireStudentApi();
header('Content-Type: application/json');

$docs = epFetchAll(
    $conn,
    "SELECT d.document_id, d.course_id, d.semester, d.doc_type, d.title,
            d.file_name, d.storage_provider, d.mime_type, d.file_size, d.uploaded_at,
            c.course_code, c.course_name
     FROM academic_documents d
     LEFT JOIN courses c ON d.course_id = c.course_id
     WHERE d.student_id = ?
     ORDER BY d.uploaded_at DESC, d.document_id DESC",
    'i',
    [$student_id]
);

foreach ($docs as &$doc) {
    $doc['document_id'] = (int) $doc['document_id'];
    $doc['file_size'] = (int) $doc['file_size'];
    $doc['semester'] = $doc['semester'] !== null ? (int) $doc['semester'] : null;
    $doc['download_url'] = 'backend/api/download_document.php?type=document&id=' . $doc['document_id'];
}
unset($doc);

mysqli_close($conn);

sendResponse(true, 'Academic documents loaded', [
    'documents' => $docs,
    'storage' => epStorageStatus(),
]);

