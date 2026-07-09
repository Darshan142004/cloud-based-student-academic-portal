<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    sendResponse(false, "Unauthorized: Please log in first.");
}

$student_id = getStudentId();

// Verify that a file was uploaded
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = "No file uploaded or upload error occurred.";
    if (isset($_FILES['photo'])) {
        switch ($_FILES['photo']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg = "The uploaded file exceeds the maximum allowed size.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_msg = "No file was selected.";
                break;
        }
    }
    sendResponse(false, $error_msg);
}

$file = $_FILES['photo'];

// Validate file size (max 2MB)
$max_size = 2 * 1024 * 1024;
if ($file['size'] > $max_size) {
    sendResponse(false, "File size exceeds 2MB limit.");
}

// Validate file is actually an image using getimagesize
$image_info = getimagesize($file['tmp_name']);
if ($image_info === false) {
    sendResponse(false, "Uploaded file is not a valid image.");
}

// Validate mime type
$mime = $image_info['mime'];
$allowed_mimes = ['image/jpeg', 'image/png', 'image/jpg'];
if (!in_array($mime, $allowed_mimes)) {
    sendResponse(false, "Only JPG, JPEG, and PNG images are allowed.");
}

// Validate extension
$allowed_extensions = ['jpg', 'jpeg', 'png'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_ext, $allowed_extensions)) {
    sendResponse(false, "Only JPG, JPEG, and PNG image extensions are allowed.");
}

// Normalize extension
if ($file_ext === 'jpeg') {
    $file_ext = 'jpg';
}

// Rename file to student_{student_id}_{timestamp}.{ext}
$timestamp = time();
$new_filename = "student_" . $student_id . "_" . $timestamp . "." . $file_ext;

// Set target path
$target_dir = "../../uploads/profile_photos/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0755, true);
}
$target_file = $target_dir . $new_filename;

// Get the current profile photo to delete it later
$sql = "SELECT profile_photo FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);
$old_photo = $student ? $student['profile_photo'] : null;
mysqli_stmt_close($stmt);

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $target_file)) {
    // Update profile_photo column in students table
    $sql_update = "UPDATE students SET profile_photo = ? WHERE student_id = ?";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "si", $new_filename, $student_id);
    
    if (mysqli_stmt_execute($stmt_update)) {
        // Delete old photo if it exists and is not the default
        if ($old_photo && $old_photo !== 'default-avatar.png') {
            $old_photo_path = $target_dir . $old_photo;
            if (file_exists($old_photo_path)) {
                unlink($old_photo_path);
            }
        }
        
        mysqli_stmt_close($stmt_update);
        mysqli_close($conn);
        
        // Return success with photo URL
        sendResponse(true, "Profile photo updated successfully.", [
            'profile_photo' => $new_filename,
            'photo_url' => "uploads/profile_photos/" . $new_filename
        ]);
    } else {
        // Delete uploaded file if DB update failed
        if (file_exists($target_file)) {
            unlink($target_file);
        }
        mysqli_stmt_close($stmt_update);
        mysqli_close($conn);
        sendResponse(false, "Failed to update profile photo in the database.");
    }
} else {
    mysqli_close($conn);
    sendResponse(false, "Failed to save the uploaded image.");
}
?>
