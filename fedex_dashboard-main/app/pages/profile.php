<?php 
    $basePath = '../../';
    include '../../templates/session/private_session.php'; 

    $success_message = '';
    $error_message = '';

    // Fetch employee information
    $stmt = $conn->prepare("SELECT f_name, l_name FROM FedEx_Employees WHERE e_id = ?");
    $stmt->bind_param("s", $e_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $full_name = $employee['f_name'] . ' ' . $employee['l_name'];

    // Handle password change form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Validate current password
        $stmt = $conn->prepare("SELECT password FROM FedEx_Employees WHERE e_id = ?");
        $stmt->bind_param("s", $e_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (hash("sha256", $current_password) !== $user['password']) {
            $error_message = "Current password is incorrect.";
        } elseif (strlen($new_password) < 8) {
            $error_message = "New password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $error_message = "New password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $error_message = "New password must contain at least one number.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } else {
            // Update password
            $hashed_password = hash("sha256", $new_password);
            $update_stmt = $conn->prepare("UPDATE FedEx_Employees SET password = ? WHERE e_id = ?");
            $update_stmt->bind_param("ss", $hashed_password, $e_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Password updated successfully!";
            } else {
                $error_message = "Error updating password. Please try again.";
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">

    <?php 
        $pageTitle = 'Profile';
        include '../../templates/layouts/head.php'; 
    ?>

    <head>
        <style>
            .notification {
                padding: 15px 20px;
                margin-bottom: 20px;
                border-radius: 6px;
                font-weight: bold;
                display: flex;
                align-items: center;
                animation: fadeIn 0.5s ease-in-out;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .notification.success {
                background-color: #e8f5e9;
                color: #2e7d32;
                border-left: 4px solid #2e7d32;
            }
            
            .notification.error {
                background-color: #ffebee;
                color: #c62828;
                border-left: 4px solid #c62828;
            }
            
            .notification-icon {
                margin-right: 10px;
                font-size: 20px;
            }
            
            .notification-content {
                flex-grow: 1;
            }
            
            .notification-close {
                cursor: pointer;
                font-size: 18px;
                opacity: 0.7;
            }
            
            .notification-close:hover {
                opacity: 1;
            }
        </style>
        <script>
            // Function to automatically hide notifications after a few seconds
            function hideNotification(elementId) {
                setTimeout(function() {
                    const element = document.getElementById(elementId);
                    if (element) {
                        element.style.opacity = '0';
                        element.style.transform = 'translateY(-10px)';
                        setTimeout(function() {
                            element.style.display = 'none';
                        }, 500);
                    }
                }, 5000); // Hide after 5 seconds
            }
            
            // Function to manually close notification
            function closeNotification(elementId) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.style.opacity = '0';
                    element.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        element.style.display = 'none';
                    }, 500);
                }
            }
        </script>
    </head>

    <body>

        <!-- Header -->
        <?php include '../../templates/layouts/header.php'; ?>

        <!-- Main content -->
        <main class="profile-container">
            <div class="profile-content">
                <h1>Profile Settings</h1>
                
                <?php if ($success_message): ?>
                    <div id="success-notification" class="notification success">
                        <span class="notification-icon">✓</span>
                        <div class="notification-content"><?php echo htmlspecialchars($success_message); ?></div>
                        <span class="notification-close" onclick="closeNotification('success-notification')">×</span>
                    </div>
                    <script>hideNotification('success-notification');</script>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div id="error-notification" class="notification error">
                        <span class="notification-icon">!</span>
                        <div class="notification-content"><?php echo htmlspecialchars($error_message); ?></div>
                        <span class="notification-close" onclick="closeNotification('error-notification')">×</span>
                    </div>
                    <script>hideNotification('error-notification');</script>
                <?php endif; ?>

                <div class="profile-info">
                    <h2>Account Information</h2>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($full_name); ?></p>
                    <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($e_id); ?></p>
                    <p><strong>Role:</strong> <?php echo htmlspecialchars($role_name); ?></p>
                    <p><strong>Security Clearance Level:</strong> <?php echo htmlspecialchars($security_clearance); ?></p>
                </div>

                <div class="password-change">
                    <h2>Change Password</h2>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="form-group">
                            <label for="current_password">Current Password:</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password:</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small>Password must be at least 8 characters long and contain at least one uppercase letter and one number.</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>

                        <button type="submit" class="submit-btn">Change Password</button>
                    </form>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <?php include '../../templates/layouts/footer.php'; ?>

    </body>

</html>