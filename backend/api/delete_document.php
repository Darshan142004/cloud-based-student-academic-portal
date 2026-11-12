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

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$payload = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : $_POST;

$docId = (int) ($payload['document_id'] ?? 0);
if ($docId <= 0) {
    mysqli_close($conn);
    sendResponse(false, 'Valid document_id is required', null, 400);
}

$doc = epFetchOne(
    $conn,
    "SELECT document_id, student_id, storage_key, storage_provider
     FROM academic_documents
     WHERE document_id = ?",
    'i',
    [$docId]
);

if (!$doc || (int) $doc['student_id'] !== $student_id) {
    mysqli_close($conn);
    sendResponse(false, 'Document not found or access denied', null, 404);
}

$stmt = mysqli_prepare($conn, "DELETE FROM academic_documents WHERE document_id = ? AND student_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $docId, $student_id);
$ok = mysqli_stmt_execute($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

if ($ok && $affected > 0) {
    epStorageDeleteObject($doc['storage_key'], $doc['storage_provider']);
    sendResponse(true, 'Document deleted successfully');
}

sendResponse(false, 'Failed to delete document', null, 500);

