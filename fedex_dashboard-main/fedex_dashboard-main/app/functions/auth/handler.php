<?php
session_start();
include '../../../templates/session/connection_check.php'; 


if (!isset($_POST['action'])) {
    header("Location: login.php?error=noaction");
    exit();
}

// Check if the database connection is established
if (!$conn) {
    die("Database connection error.");
}

// Handle login 
if ($_POST['action'] == "login") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Debugging
    echo "Username entered: " . htmlspecialchars($username) . "<br>";
    echo "Password entered: " . htmlspecialchars($password) . "<br>";

    // Check if user exists
    $stmt = $conn->prepare("SELECT e_id, password, security_clearance, password_reset_required FROM FedEx_Employees WHERE username = ?");
    if (!$stmt) {
        die("SQL error: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    echo "Rows found: " . $stmt->num_rows . "<br>"; 

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($e_id, $hashed_password, $security_clearance, $password_reset_required);
        $stmt->fetch();

        // Debugging: Output database values
        echo "User ID: " . $e_id . "<br>";
        echo "Hashed Password in DB: " . $hashed_password . "<br>";
        echo "Security Clearance: " . $security_clearance . "<br>";
        echo "Password Reset Required: " . $password_reset_required . "<br>";

        // Verify password 
        if (hash("sha256", $password) === $hashed_password) {
            echo "Password match! Logging in...<br>";

            // Set session variables
            $_SESSION['e_id'] = $e_id;
            $_SESSION['security_clearance'] = $security_clearance;

            // Redirect to password reset page if required
            if ($password_reset_required === 'TRUE') {
                header("Location: ../../../app/pages/password_reset.php");
                exit();
            }

            // Redirect to dashboard
            header("Location: ../../../app/pages/dashboard.php");
            exit();
        } else {
            // Password is incorrect
            header("Location: ../../../app/pages/login.php?error=incorrect_password");
            exit();
        }
    } else {
        // User not found
        header("Location: ../../../app/pages/login.php?error=user_not_found");
        exit();
    }
}
?>