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

            .password-reset-section {
                margin-top: 30px;
                padding: 20px;
            }
            
            .btn-primary {
                display: inline-block;
                padding: 10px 20px;
                background-color: #4e1187;
                color: white;
                border-radius: 4px;
                text-decoration: none;
                font-weight: bold;
                margin-top: 10px;
                transition: background-color 0.3s;
            }
            
            .btn-primary:hover {
                background-color: #3a0c66;
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

                <div class="password-reset-section">
                    <a href="password_reset.php" class="btn-primary">Reset Password</a>
                </div>

            </div>
        </main>

        <!-- Footer -->
        <?php include '../../templates/layouts/footer.php'; ?>

    </body>

</html>