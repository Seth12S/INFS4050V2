<?php 
    $basePath = '../../';
    include '../../templates/session/private_session.php'; 
    include '../functions/auth/reset_function.php';
?>
<!DOCTYPE html>
<html lang="en">

    <?php 
        $pageTitle = 'Password Reset';
        include '../../templates/layouts/head.php'; 
    ?>

    <body>

        <!-- Header -->
        <?php include '../../templates/layouts/header.php'; ?>

        <!-- Main content -->
        <main>
            <div class="reset-container">
                <h2>Reset Your Password</h2>
                
                <?php if (isset($error)): ?>
                    <?php 
                    // Check if this is a success message
                    if (strpos($error, 'success:') === 0) {
                        $message_class = 'success-message';
                        $error = substr($error, 8); // Remove the 'success:' prefix
                    } else {
                        $message_class = 'error-message';
                    }
                    ?>
                    <div class="message <?php echo $message_class; ?>">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form action="password_reset.php" method="POST">
                    <div class="password-row">
                        <div>

                            <input placeholder="New Password" type="password" name="new_password" required>
                        </div>
                        <div>

                            <input placeholder="Confirm Password" type="password" name="confirm_password" required>
                        </div>
                    </div>
                    <button type="submit">Update Password</button>
                </form>
            </div>
        </main>

        <!-- Footer -->
        <?php include '../../templates/layouts/footer.php'; ?>

    </body>

</html>