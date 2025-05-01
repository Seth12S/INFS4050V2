<?php 
    $basePath = '../../';
    include '../../templates/session/private_session.php'; 
?>
<!DOCTYPE html>
<html lang="en">

    <?php 
        $pageTitle = 'Analytics';
        include '../../templates/layouts/head.php'; 
    ?>

    <body>

        <!-- Header -->
        <?php include '../../templates/layouts/header.php'; ?>

        <!-- Main content -->
        <main>
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1>Dashboard</h1>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <span class="card-icon">📢</span>
                        <h2>Company News</h2>
                    </div>
                    <div class="news-item">
                        <strong>FedEx Goes Hybrid</strong>
                        <p>Starting May 1, all departments will rotate in-office presence two days a week. Check your team calendar for details.</p>
                    </div>
                    <div class="news-item">
                        <strong>Q1 Standouts</strong>
                        <p>Congratulations to our top performers! Bonus rewards will be issued on April 20.</p>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <span class="card-icon">⚙️</span>
                        <h2>Updates</h2>
                    </div>
                    <ul class="updates-list">
                        <li>
                            <span class="update-icon">🔒</span>
                            Mandatory cybersecurity training due April 30
                        </li>
                        <li>
                            <span class="update-icon">📊</span>
                            New delivery analytics dashboard launches next week
                        </li>
                        <li>
                            <span class="update-icon">📱</span>
                            Portal benefits 
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

        <!-- Footer -->
        <?php include '../../templates/layouts/footer.php'; ?>

    </body>

</html>