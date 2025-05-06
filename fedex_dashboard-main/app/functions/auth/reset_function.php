<?php
// Get the logged-in user's ID
$e_id = $_SESSION['e_id'];

// Initialize message variables
$success = null;
$error = null;
$redirect = false; // Track if we should redirect

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate password length and confirmation
    if (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = "Password must contain at least one number.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Hash new password
        $hashed_password = hash("sha256", $new_password);

        // Update password in the database & mark password_reset_required as FALSE
        $stmt = $conn->prepare("UPDATE FedEx_Employees SET password = ?, password_reset_required = 0 WHERE e_id = ?");
        $stmt->bind_param("ss", $hashed_password, $e_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Success message (display on same page)
            $success = "Password has been reset successfully!";
            $redirect = true; // Set redirect flag
            
            // Clear the form values after successful submission
            $_POST = array();
        } else {
            $error = "Password reset failed. Please try again.";
        }
    }
}
?>

<?php if (isset($success)): ?>
    <div class="message success-message">
        <?php echo htmlspecialchars($success); ?>
    </div>
    
    <?php if ($redirect): ?>
    <script>
        // Redirect after 2 seconds
        setTimeout(function() {
            window.location.href = "profile.php";
        }, 2000);
    </script>
    <?php endif; ?>
<?php endif; ?>