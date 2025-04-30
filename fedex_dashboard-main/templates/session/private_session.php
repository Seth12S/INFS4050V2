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


$stmt = $conn->prepare("SELECT role_name FROM FedEx_Security_Clearance WHERE role_id = ?");
$stmt->bind_param("s", $security_clearance);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($role_name);
$stmt->fetch();
$stmt->close();
?>