<?php include 'templates/session/public_session.php'; ?>
<!DOCTYPE html>
<html lang="en">

    <?php
        $pageTitle = 'Home';
        $basePath = '';
        include 'templates/layouts/head.php';
    ?>

    <body>

        <!-- Header -->
        <?php include 'templates/layouts/header.php'; ?>

        <!-- Main content -->
        <main class="container">
            <!-- Banner -->
            <div class="banner-container">
                <img src="assets/images/banner.png" alt="Banner picture">
            </div>
            <!-- Box content -->
            <div class="home-content">
                <h1>Welcome to the FedEx Employee Management System</h1>
                <p>This portal is designed to help authorized employees to <strong>view, edit, and add or delete</strong> employee data based on the users clearance level.</p>
                <div class="home-sections">
                    <section>
                        <h2>View Analytics</h2>
                        <p>Management can view analytics of the company's employees based on location, job title, and more.</p>
                    </section>
                    <section>
                        <h2>Edit Employees</h2>
                        <p>Management can edit employee data such as the locations, managers, directors, and executives they work under.</p>
                    </section>
                    <section>
                        <h2>Adding or Deleting Employees</h2>
                        <p>This feature is only available to System Administrators. They can add or delete employees from the system.</p>
                    </section>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <?php include 'templates/layouts/footer.php'; ?>

    </body>

</html>     