<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    sendResponse(false, 'Unauthorized access');
}

header('Content-Type: application/json');

$sql = "SELECT 
    student_id,
    enrollment_id,
    first_name,
    last_name,
    email,
    phone,
    date_of_birth,
    gender,
    address,
    department,
    program,
    current_semester,
    admission_year
FROM students
ORDER BY enrollment_id ASC";

$result = mysqli_query($conn, $sql);
$students = [];

while ($row = mysqli_fetch_assoc($result)) {
    $students[] = $row;
}

$response = [
    'success' => true,
    'data' => $students
];

echo json_encode($response);
mysqli_close($conn);
?>