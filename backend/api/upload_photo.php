<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/storage_service.php';

// Check if user is logged in as a student
$student_id = requireStudentApi();

$validation = epStorageValidateUpload(
    $_FILES['photo'] ?? [],
    ['jpg', 'jpeg', 'png'],
    ['image/jpeg', 'image/png', 'image/pjpeg'],
    2 * 1024 * 1024
);

if (!$validation['ok']) {
    mysqli_close($conn);
    sendResponse(false, $validation['error'], null, 400);
}

$tmpPath = $_FILES['photo']['tmp_name'];
$imageInfo = @getimagesize($tmpPath);
if ($imageInfo === false) {
    mysqli_close($conn);
    sendResponse(false, 'Uploaded file is not a valid image.', null, 400);
}

$storageKey = epStorageGenerateKey('profile_photos', 'student_' . $student_id, $validation['ext']);
$newFilename = basename($storageKey);

// Get current profile photo for cleanup after DB update
$sql = "SELECT profile_photo FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$oldPhoto = $student ? $student['profile_photo'] : null;
mysqli_stmt_close($stmt);

$stored = epStoragePutUploadedFile($tmpPath, $storageKey, $validation['mime']);
if (!$stored['ok']) {
    mysqli_close($conn);
    sendResponse(false, $stored['error'], null, 500);
}

$dbPhotoValue = $stored['provider'] === 'azure_blob'
    ? ('azure:' . $stored['storage_key'])
    : $newFilename;

$sqlUpdate = "UPDATE students SET profile_photo = ? WHERE student_id = ?";
$stmtUpdate = mysqli_prepare($conn, $sqlUpdate);
mysqli_stmt_bind_param($stmtUpdate, "si", $dbPhotoValue, $student_id);

if (mysqli_stmt_execute($stmtUpdate)) {
    if ($oldPhoto && $oldPhoto !== 'default-avatar.png') {
        $oldKey = str_starts_with($oldPhoto, 'azure:')
            ? $oldPhoto
            : ('profile_photos/' . ltrim($oldPhoto, '/'));
        epStorageDeleteObject($oldKey);
    }

    mysqli_stmt_close($stmtUpdate);
    mysqli_close($conn);

    $_SESSION['profile_photo'] = $dbPhotoValue;
    $photoUrl = epResolveProfilePhotoUrl($dbPhotoValue)
        ?? ('backend/api/download_document.php?type=profile&v=' . substr(md5($dbPhotoValue), 0, 10));

    sendResponse(true, 'Profile photo updated successfully.', [
        'profile_photo' => $dbPhotoValue,
        'photo_url' => $photoUrl,
        'profile_photo_url' => $photoUrl,
        'storage_provider' => $stored['provider'],
    ]);
} else {
    epStorageDeleteObject($stored['storage_key'], $stored['provider']);
    mysqli_stmt_close($stmtUpdate);
    mysqli_close($conn);
    sendResponse(false, 'Failed to update profile photo in the database.', null, 500);
}
?>
