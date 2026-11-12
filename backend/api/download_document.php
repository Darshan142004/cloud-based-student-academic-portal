<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/storage_service.php';

$isStudent = isStudentSession();
$isAdmin = isAdminSession();

if (!$isStudent && !$isAdmin) {
    mysqli_close($conn);
    sendResponse(false, 'Unauthorized access. Please login first.', null, 401);
}

$type = strtolower(trim((string) ($_GET['type'] ?? 'document')));
$id = (int) ($_GET['id'] ?? 0);
$mode = strtolower(trim((string) ($_GET['mode'] ?? 'stream'))); // 'stream' or 'sas_info'

$storageKey = '';
$provider = 'local';
$mimeType = 'application/octet-stream';
$fileName = 'download.bin';

if ($type === 'document') {
    if ($id <= 0) {
        mysqli_close($conn);
        sendResponse(false, 'Valid document id is required.', null, 400);
    }
    $doc = epFetchOne(
        $conn,
        "SELECT document_id, student_id, file_name, storage_key, storage_provider, mime_type
         FROM academic_documents
         WHERE document_id = ?",
        'i',
        [$id]
    );
    mysqli_close($conn);

    if (!$doc) {
        sendResponse(false, 'Document not found.', null, 404);
    }
    if (!$isAdmin && (int) $doc['student_id'] !== (int) $_SESSION['student_id']) {
        sendResponse(false, 'Access denied.', null, 403);
    }
    $storageKey = $doc['storage_key'];
    $provider = $doc['storage_provider'];
    $mimeType = $doc['mime_type'];
    $fileName = $doc['file_name'];
} elseif ($type === 'assignment') {
    $assignmentId = (int) ($_GET['assignment_id'] ?? $id);
    $reqStudentId = $isAdmin ? (int) ($_GET['student_id'] ?? 0) : (int) $_SESSION['student_id'];
    if ($assignmentId <= 0 || $reqStudentId <= 0) {
        mysqli_close($conn);
        sendResponse(false, 'Valid assignment_id and student_id are required.', null, 400);
    }
    $sub = epFetchOne(
        $conn,
        "SELECT student_id, file_path
         FROM assignment_submissions
         WHERE assignment_id = ? AND student_id = ?",
        'ii',
        [$assignmentId, $reqStudentId]
    );
    mysqli_close($conn);

    if (!$sub || empty($sub['file_path'])) {
        sendResponse(false, 'Assignment attachment not found.', null, 404);
    }
    $fp = (string) $sub['file_path'];
    if (str_starts_with($fp, 'azure:')) {
        $provider = 'azure_blob';
        $storageKey = substr($fp, strlen('azure:'));
    } else {
        $provider = 'local';
        $storageKey = $fp;
    }
    $fileName = basename($storageKey);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeType = match ($ext) {
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        default => 'application/octet-stream',
    };
} elseif ($type === 'profile') {
    $sessionStudentId = $isStudent ? (int) $_SESSION['student_id'] : 0;
    if (!$isAdmin && isset($_GET['student_id']) && (int) $_GET['student_id'] !== $sessionStudentId) {
        mysqli_close($conn);
        sendResponse(false, 'Access denied.', null, 403);
    }
    $reqStudentId = $isAdmin
        ? (int) ($_GET['student_id'] ?? 0)
        : $sessionStudentId;
    if ($reqStudentId <= 0) {
        mysqli_close($conn);
        sendResponse(false, 'Valid student_id is required.', null, 400);
    }
    $st = epFetchOne($conn, "SELECT student_id, profile_photo FROM students WHERE student_id = ?", 'i', [$reqStudentId]);
    mysqli_close($conn);
    if (!$st || empty($st['profile_photo']) || $st['profile_photo'] === 'default-avatar.png') {
        sendResponse(false, 'Profile photo not found.', null, 404);
    }
    $pp = (string) $st['profile_photo'];
    if (str_starts_with($pp, 'azure:')) {
        $provider = 'azure_blob';
        $storageKey = substr($pp, strlen('azure:'));
    } else {
        $provider = 'local';
        $storageKey = 'profile_photos/' . ltrim($pp, '/');
    }
    $fileName = basename($storageKey);
    $mimeType = str_ends_with(strtolower($fileName), '.png') ? 'image/png' : 'image/jpeg';
} else {
    mysqli_close($conn);
    sendResponse(false, 'Unsupported resource type.', null, 400);
}

if ($mode === 'sas_info' && $provider === 'azure_blob') {
    $cfg = epParseAzureStorageConfig();
    if (!$cfg) {
        sendResponse(false, 'Azure Blob configuration missing.', null, 500);
    }
    $sasUrl = epAzureGenerateBlobSasUrl($cfg, $storageKey, 900);
    sendResponse(true, 'Temporary SAS download URL generated', ['url' => $sasUrl, 'expires_in_seconds' => 900]);
}

$obj = epStorageReadObject($storageKey, $provider);
if (!$obj['ok']) {
    sendResponse(false, $obj['error'] ?: 'File could not be retrieved.', null, 404);
}

$safeFileName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($fileName));
$disposition = str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf' ? 'inline' : 'attachment';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($obj['bytes']));
header('Content-Disposition: ' . $disposition . '; filename="' . $safeFileName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
echo $obj['bytes'];
exit;

