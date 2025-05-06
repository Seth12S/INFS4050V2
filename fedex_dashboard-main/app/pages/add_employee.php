<?php 
    $basePath = '../../';
    include '../../templates/session/private_session.php'; 
?>
<!DOCTYPE html>
<html lang="en">

    <?php 
        $pageTitle = 'Add Employee';
        include '../../templates/layouts/head.php'; 
    ?>

    <body>

        <!-- Header -->
        <?php include '../../templates/layouts/header.php'; ?>

        <!-- Main content -->

        <main class="employees-container">
        <div class="container">
            <h1>Add New Employee</h1>
            
            <div class="add-employee-form">
                <?php
                if (isset($_GET['error'])) {
                    $error = $_GET['error'];
                    $errorMessage = '';
                    
                    switch($error) {
                        case 'duplicate_id_active':
                            $errorMessage = 'Employee ID is already in use.';
                            break;
                        case 'duplicate_id_archived':
                            $errorMessage = 'Employee ID is already in use.';
                            break;
                        case 'headcount_exceeded':
                            $errorMessage = 'Cannot add new employee: Approved headcount limit of 270 has been reached.';
                            break;
                        default:
                            $errorMessage = 'Error: ' . htmlspecialchars($error);
                    }
                    
                    echo '<div class="alert alert-danger">' . $errorMessage . '</div>';
                }
                ?>
                <form method="POST" action="../functions/data/add_employee_function.php">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="e_id">Employee ID:</label>
                            <input type="text" id="e_id" name="e_id" required>
                        </div>
                        <div class="form-group">
                            <label for="f_name">First Name:</label>
                            <input type="text" id="f_name" name="f_name" required>
                        </div>
                        <div class="form-group">
                            <label for="l_name">Last Name:</label>
                            <input type="text" id="l_name" name="l_name" required>
                        </div>
                        <div class="form-group">
                            <label for="birthdate">Birth Date:</label>
                            <input type="date" id="birthdate" name="birthdate" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="anniversary_date">Anniversary Date:</label>
                            <input type="date" id="anniversary_date" name="anniversary_date" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="job_code">Job Title:</label>
                            <select id="job_code" name="job_code" required>
                                <option value="">Select Job Title</option>
                                <?php
                                $jobs_sql = "SELECT job_code, job_title FROM FedEx_Jobs ORDER BY job_title";
                                $jobs_result = $conn->query($jobs_sql);
                                while ($job = $jobs_result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($job['job_code']) . "'>" . 
                                         htmlspecialchars($job['job_title']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="department_id">Organization:</label>
                            <input type="text" id="department_id" name="department_id" required>
                        </div>
                        <div class="form-group">
                            <label for="security_clearance">Security Clearance:</label>
                            <select id="security_clearance" name="security_clearance" required>
                                <option value="">Select Security Clearance</option>
                                <?php
                                $clearance_sql = "SELECT role_id, role_name FROM FedEx_Security_Clearance ORDER BY role_id";
                                $clearance_result = $conn->query($clearance_sql);
                                while ($clearance = $clearance_result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($clearance['role_id']) . "'>" . 
                                         htmlspecialchars($clearance['role_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="m_id">Manager:</label>
                            <select id="m_id" name="m_id" required>
                                <option value="">Select Manager</option>
                                <?php
                                $managers_sql = "SELECT e_id, f_name, l_name FROM FedEx_Employees 
                                               WHERE job_code IN (SELECT job_code FROM FedEx_Jobs 
                                               WHERE job_title LIKE '%Manager%') 
                                               ORDER BY l_name, f_name";
                                $managers_result = $conn->query($managers_sql);
                                while ($manager = $managers_result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($manager['e_id']) . "'>" . 
                                         htmlspecialchars($manager['f_name'] . ' ' . $manager['l_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="d_id">Director:</label>
                            <select id="d_id" name="d_id" required>
                                <option value="">Select Director</option>
                                <?php
                                $directors_sql = "SELECT e_id, f_name, l_name FROM FedEx_Employees 
                                                WHERE job_code IN (SELECT job_code FROM FedEx_Jobs 
                                                WHERE job_title LIKE '%Director%') 
                                                ORDER BY l_name, f_name";
                                $directors_result = $conn->query($directors_sql);
                                while ($director = $directors_result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($director['e_id']) . "'>" . 
                                         htmlspecialchars($director['f_name'] . ' ' . $director['l_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="vp">Vice President:</label>
                            <select name="vp" id="vp" required>
                                <option value="">Select VP</option>
                                <?php
                                $vp_sql = "SELECT e.e_id, e.f_name, e.l_name 
                                         FROM FedEx_Employees e 
                                         JOIN FedEx_Jobs j ON e.job_code = j.job_code 
                                         WHERE j.job_title = 'Vice President IT' 
                                         ORDER BY e.l_name, e.f_name";
                                $vp_result = $conn->query($vp_sql);
                                while ($vp = $vp_result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($vp['e_id']) . "'>" . 
                                         htmlspecialchars($vp['f_name'] . ' ' . $vp['l_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="location">Location:</label>
                            <select id="zip_code" name="zip_code" required>
                                <option value="">Select Location</option>
                                <?php
                                    $locations_sql = "SELECT zip_code, city, state FROM FedEx_Locations ORDER BY city, state";
                                    $locations_result = $conn->query($locations_sql);
                                    while ($location = $locations_result->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($location['zip_code']) . "'>" . 
                                            htmlspecialchars($location['city'] . ', ' . $location['state']) . "</option>";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Add Employee</button>
                        <a href="employees_table.php" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        </main>

        <!-- Footer -->
        <?php include '../../templates/layouts/footer.php'; ?>

    </body>

</html>