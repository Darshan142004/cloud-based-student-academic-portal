<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/storage_service.php';

$student_id = requireStudentApi();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mysqli_close($conn);
    sendResponse(false, 'Method not allowed', null, 405);
}

$title = epCleanText($_POST['title'] ?? '');
$docType = strtolower(trim((string) ($_POST['doc_type'] ?? 'other')));
$courseId = isset($_POST['course_id']) && $_POST['course_id'] !== '' ? (int) $_POST['course_id'] : null;
$semester = isset($_POST['semester']) && $_POST['semester'] !== '' ? (int) $_POST['semester'] : null;

$allowedTypes = ['transcript', 'certificate', 'marksheet', 'project_report', 'id_proof', 'other'];
if (!in_array($docType, $allowedTypes, true)) {
    mysqli_close($conn);
    sendResponse(false, 'Invalid document type specified.', null, 400);
}

if ($title === '' || mb_strlen($title) > 200) {
    mysqli_close($conn);
    sendResponse(false, 'Document title is required (max 200 characters).', null, 400);
}

$validation = epStorageValidateUpload(
    $_FILES['document'] ?? [],
    ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'],
    [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/png',
        'image/jpeg',
        'image/pjpeg',
    ],
    10 * 1024 * 1024
);

if (!$validation['ok']) {
    mysqli_close($conn);
    sendResponse(false, $validation['error'], null, 400);
}

if ($courseId !== null) {
    $enrolled = epFetchOne(
        $conn,
        "SELECT enrollment_id FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1",
        'ii',
        [$student_id, $courseId]
    );
    if (!$enrolled) {
        mysqli_close($conn);
        sendResponse(false, 'You can only link documents to your enrolled courses.', null, 403);
    }
}

$storageKey = epStorageGenerateKey('documents', 'stu_' . $student_id, $validation['ext']);
$stored = epStoragePutUploadedFile($_FILES['document']['tmp_name'], $storageKey, $validation['mime']);
if (!$stored['ok']) {
    mysqli_close($conn);
    sendResponse(false, $stored['error'], null, 500);
}

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO academic_documents
        (student_id, course_id, semester, doc_type, title, file_name, storage_key, storage_provider, mime_type, file_size)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param(
    $stmt,
    'iiissssssi',
    $student_id,
    $courseId,
    $semester,
    $docType,
    $title,
    $validation['original_name'],
    $stored['storage_key'],
    $stored['provider'],
    $validation['mime'],
    $validation['size']
);

if (!mysqli_stmt_execute($stmt)) {
    $err = mysqli_error($conn);
    mysqli_stmt_close($stmt);
    epStorageDeleteObject($stored['storage_key'], $stored['provider']);
    mysqli_close($conn);
    sendResponse(false, 'Failed to save document metadata: ' . $err, null, 500);
}

$docId = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);
mysqli_close($conn);

sendResponse(true, 'Academic document uploaded successfully.', [
    'document_id' => $docId,
    'title' => $title,
    'doc_type' => $docType,
    'file_name' => $validation['original_name'],
    'storage_provider' => $stored['provider'],
    'file_size' => $validation['size'],
    'download_url' => 'backend/api/download_document.php?type=document&id=' . $docId,
]);

