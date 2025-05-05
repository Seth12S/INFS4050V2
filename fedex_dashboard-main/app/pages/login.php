<?php 
    $basePath = '../../';
    include '../../templates/session/public_session.php'; 

    // counter is always defined
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
    }
        
?>

<!DOCTYPE html>
<html lang="en">

    <?php 
        $pageTitle = 'Login';
        include '../../templates/layouts/head.php'; 
    ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <body>

        <!-- Header -->
        <?php include '../../templates/layouts/header.php'; ?>

        <!-- Main content -->
        <main class="login-container">
            <div class='login-sub-container'>
                <h2>Employee Login</h2>
                <p>Enter username and password to login.</p> 

                <!-- Error display -->
                <?php
                    if (isset($_GET['error'])) {
                        $error = $_GET['error'];
                        if ($error == 'notloggedin') {
                            echo '<div class="notification-error">Employee Login Required.</div>';
                        }
                        if ($error == 'incorrect_password') {

                                // Failed-login counter
                                $attempts = $_SESSION['login_attempts'];
                                if ($attempts < 3) {
                                    $left = 3 - $attempts;
                                    echo "<div class=\"notification error\">
                                            You have {$left} attempt"
                                          . ($left > 1 ? 's' : '') .
                                          " left.
                                          </div>";
                                } else {
                                    echo <<<HTML
                                    <script>
                                      Swal.fire({
                                        icon: 'error',
                                        title: 'Account Locked',
                                        text: 'Your account has been locked due to multiple failed login attempts. Please contact HR.',
                                        confirmButtonText: 'OK'
                                      });
                                    </script>
                                    HTML; 
                                }
                        
                            echo '<div class="notification-error">Incorrect Username or Password.</div>';
                        } else if ($error == 'user_not_found') {
                            echo '<div class="notification-error">Incorrect Username or Password.</div>';
                        }
                    }
                ?>
                
                <form action="../functions/auth/handler.php" method="post" class="login-form">
                    <input type="hidden" name="action" value="login"> 

                    <input type="text" id="username" name="username" placeholder="Username" required>

                    <input type="password" id="password" name="password" placeholder="Password" required>

                    <button type="submit" class="login-button">Login</button>
                </form>

            </div>
        </main>

        <!-- Footer -->
        <?php include '../../templates/layouts/footer.php'; ?>

    </body>

</html>
