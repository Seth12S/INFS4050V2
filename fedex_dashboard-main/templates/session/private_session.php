<?php
session_start();
include $basePath . 'templates/session/connection_check.php'; 

// Check if user is logged in
if (!isset($_SESSION['e_id'])) {
    header("Location: /app/pages/login.php?error=notloggedin");
    exit();
}

// Fetch user details from session
$e_id = $_SESSION['e_id'];
$security_clearance = $_SESSION['security_clearance'];

// Get role name from security clearance
$stmt = $conn->prepare("SELECT role_name FROM FedEx_Security_Clearance WHERE role_id = ?");
$stmt->bind_param("s", $security_clearance);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($role_name);
$stmt->fetch();
$stmt->close();

// Get user's job title from database to use as user_role
$job_stmt = $conn->prepare("
    SELECT j.job_title 
    FROM FedEx_Employees e
    JOIN FedEx_Jobs j ON e.job_code = j.job_code 
    WHERE e.e_id = ? 
");
$job_stmt->bind_param("s", $e_id);
$job_stmt->execute();
$job_result = $job_stmt->get_result();
$job_row = $job_result->fetch_assoc();
$user_role = $job_row['job_title'] ?? 'Unknown';
$_SESSION['user_role'] = $user_role;
$job_stmt->close();
?>

