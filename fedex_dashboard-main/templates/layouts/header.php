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
                      