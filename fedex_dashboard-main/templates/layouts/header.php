        <header>
            <div class="header-main">
                <div class="logo">
                    <a href="<?php echo isset($basePath) ? $basePath : ''; ?>index.php">
                        <img src="<?php echo isset($basePath) ? $basePath : ''; ?>assets/images/logo.png" alt="FedEx Logo">
                    </a>
                </div>
                <nav>
                    <ul>
                        <li><a href="<?php echo isset($basePath) ? $basePath : ''; ?>index.php">Home</a></li>
                        <?php if (isset($_SESSION['e_id'])) { ?>
                            <li><a href="<?php echo isset($basePath) ? $basePath : ''; ?>app/pages/dashboard.php">Dashboard</a></li>
                            <?php if ($security_clearance > 1): ?>
                            <li><a href="<?php echo isset($basePath) ? $basePath : ''; ?>app/pages/analytics.php">Analytics</a></li>
                            <li><a href="<?php echo isset($basePath) ? $basePath : ''; ?>app/pages/employees_table.php">Employees</a></li>
                            <?php endif; ?>
                            <?php
                            // Get user's role for bonus management access
                            $user_role_stmt = $conn->prepare("
                                SELECT j.job_title 
                                FROM FedEx_Employees e
                                JOIN FedEx_Jobs j ON e.job_code = j.job_code
                                WHERE e.e_id = ?
                            ");
                            $user_role_stmt->bind_param("s", $_SESSION['e_id']);
                            $user_role_stmt->execute();
                            $user_role_result = $user_role_stmt->get_result();
                            $user_role_row = $user_role_result->fetch_assoc();
                            $user_role = $user_role_row['job_title'];

                            if ($user_role === 'SVP' || $user_role === 'Vice President IT'): ?>
                            <li><a href="<?php echo isset($basePath) ? $basePath : ''; ?>app/pages/bonus_management.php">Bonus Management</a></li>
                            <?php endif; ?>
                            <li><a href="<?php echo isset($basePath) ? $basePath : ''; ?>app/pages/profile.php">Profile</a></li>
                        <?php } ?>
                    </ul>
                </nav>
            </div>
            <div class="header-authentication">
                <?php
                    if (isset($_SESSION['e_id'])) {
                        // If logged in, show logout
                        echo '<a href="' . (isset($basePath) ? $basePath : '') . 'app/functions/auth/logout.php">Logout</a>';
                    } else {
                        // If not logged in, show login
                        echo '<a href="' . (isset($basePath) ? $basePath : '') . 'app/pages/login.php">Login</a>';
                    }
                ?>
            </div>
        </header>                   
                      