<?php

    $basePath = '../../';
    include '../../templates/session/private_session.php';


    echo "User role: " . $user_role . "<br>";
    echo "Security clearance: " . $security_clearance;

    if ($security_clearance < 2) {
        header("Location: team2/app/pages/login.php");
        exit();
    }


    // Get employee ID from URL
    $employee_id = $_GET['id'] ?? null;
    if (!$employee_id) {
        header("Location: employees_table.php?error=no_employee");
        exit();
    }


    // Fetch employee data
    $stmt = $conn->prepare("
        SELECT e.*, j.job_title, l.city, l.state, 
            m.f_name as m_fname, m.l_name as m_lname,
            d.f_name as d_fname, d.l_name as d_lname,
            vp.f_name as vp_fname, vp.l_name as vp_lname,
            svp.f_name as svp_fname, svp.l_name as svp_lname
        FROM FedEx_Employees e
        LEFT JOIN FedEx_Jobs j ON e.job_code = j.job_code
        LEFT JOIN FedEx_Locations l ON e.zip_code = l.zip_code
        LEFT JOIN FedEx_Employees m ON e.m_id = m.e_id
        LEFT JOIN FedEx_Employees d ON e.d_id = d.e_id
        LEFT JOIN FedEx_Employees vp ON e.vp_id = vp.e_id
        LEFT JOIN FedEx_Employees svp ON e.svp_id = svp.e_id
        WHERE e.e_id = ?
    ");

    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();

    // Displaying employee data
    echo "<pre>";
    print_r($employee);
    echo "</pre>";




    // Fetch available managers based on user role
    $managers = [];
    // Base query to get managers
    $managers_sql = "SELECT e.e_id, e.f_name, e.l_name, j.job_title 
                    FROM FedEx_Employees e
                    JOIN FedEx_Jobs j ON e.job_code = j.job_code
                    WHERE j.job_title LIKE '%Manager%' AND j.job_type = 'Management'";

    // Different filtering based on user role
    if (in_array($user_role, ['SVP', 'VP', 'System Admin', 'SystemAdmin'])) {
        // Higher level roles can see all managers
        $managers_stmt = $conn->prepare($managers_sql . " ORDER BY e.l_name, e.f_name");
    } else {
        // Role-based filtering
        switch($user_role) {
            
            case 'Director IT':
                // Directors can see managers who report to them
                $managers_sql .= " AND (e.d_id = ? OR e.m_id = ?)";
                $managers_stmt = $conn->prepare($managers_sql . " ORDER BY e.l_name, e.f_name");
                $managers_stmt->bind_param('ss', $_SESSION['e_id'], $_SESSION['e_id']);
                break;
            
            default:
                // Fallback (shouldn't happen, but just in case)
                $managers_stmt = $conn->prepare($managers_sql . " ORDER BY e.l_name, e.f_name");
        }
    }
    // Execute query
    $managers_stmt->execute();
    $managers_result = $managers_stmt->get_result();
    // Populate managers array
    while ($row = $managers_result->fetch_assoc()) {
        $managers[$row['e_id']] = $row['f_name'] . ' ' . $row['l_name'];
    }
    $managers_stmt->close();



    // Fetch jobs
    $jobs_result = $conn->query("SELECT job_code, job_title FROM FedEx_Jobs ORDER BY job_title");
    $jobs = [];
    while ($row = $jobs_result->fetch_assoc()) {
        $jobs[$row['job_code']] = $row['job_title'];
    }



    // Fetch security clearances
    $clearance_result = $conn->query("SELECT role_id, role_name FROM FedEx_Security_Clearance ORDER BY role_id");
    $clearances = [];
    while ($row = $clearance_result->fetch_assoc()) {
        $clearances[$row['role_id']] = $row['role_name'];
    }



    // Locations
    $locations = [];
    $locations_sql = "SELECT l.zip_code, l.city, l.state
                     FROM FedEx_Locations l";

    $locations_stmt = $conn->prepare($locations_sql);
    $locations_stmt->execute();
    $locations_result = $locations_stmt->get_result();
    while ($row = $locations_result->fetch_assoc()) {
        $locations[$row['zip_code']] = $row['city'] . ', ' . $row['state'];
    }
    $locations_stmt->close();




    // For directors - allow VPs, SVPs, and System Admins to see all directors
    $directors = [];
    if (in_array($user_role, ['VP', 'SVP', 'Systems Administrator'])) {
        // All high-level roles can see all directors
        $directors_sql = "SELECT e.e_id, e.f_name, e.l_name 
                         FROM FedEx_Employees e 
                         JOIN FedEx_Jobs j ON e.job_code = j.job_code
                         WHERE j.job_title LIKE '%Director%'
                         ORDER BY e.l_name, e.f_name";
        
        $directors_stmt = $conn->prepare($directors_sql);
        $directors_stmt->execute();
        $directors_result = $directors_stmt->get_result();
        
        while ($row = $directors_result->fetch_assoc()) {
            $directors[$row['e_id']] = $row['f_name'] . ' ' . $row['l_name'];
        }
        $directors_stmt->close();
    }



    // Fetch VPs
    $vps = [];
    $vps_sql = "SELECT e.e_id, e.f_name, e.l_name 
                FROM FedEx_Employees e 
                JOIN FedEx_Jobs j ON e.job_code = j.job_code
                WHERE j.job_title LIKE '%Vice President IT%'
                ORDER BY e.l_name, e.f_name";

    $vps_stmt = $conn->prepare($vps_sql);
    $vps_stmt->execute();
    $vps_result = $vps_stmt->get_result();
    while ($row = $vps_result->fetch_assoc()) {
        $vps[$row['e_id']] = $row['f_name'] . ' ' . $row['l_name'];
    }
    $vps_stmt->close();



    // Fetch SVPs
    $svps = [];
    $svps_sql = "SELECT e.e_id, e.f_name, e.l_name 
                FROM FedEx_Employees e 
                JOIN FedEx_Jobs j ON e.job_code = j.job_code
                WHERE j.job_title LIKE '%SVP%'";

    $svps_stmt = $conn->prepare($svps_sql); 
    $svps_stmt->execute();
    $svps_result = $svps_stmt->get_result();
    while ($row = $svps_result->fetch_assoc()) {
        $svps[$row['e_id']] = $row['f_name'] . ' ' . $row['l_name'];
    }
    $svps_stmt->close();




    echo "<pre>";
    print_r($locations);
    echo "</pre>";

    echo "<pre>";
    print_r($managers);
    echo "</pre>";

    echo "<pre>";
    print_r($directors);
    echo "</pre>";

    echo "<pre>";
    print_r($vps);
    echo "</pre>";

    echo "<pre>";
    print_r($svps);
    echo "</pre>";

?>

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>FedEx | Edit Employee</title>
    <?php include '../../templates/layouts/head.php'; ?>
</head>
<body>
    <?php include '../../templates/layouts/header.php'; ?>

    <main>
        <div class="form-container">
            <h1>Edit Employee</h1>
            <form action="../functions/data/update_employee_function.php" method="POST">
                <input type="hidden" name="e_id" value="<?php echo htmlspecialchars($employee['e_id']); ?>">
                
                <!-- Display-only fields -->
                <div class="form-group">
                    <label>Employee ID:</label>
                    <input type="text" value="<?php echo htmlspecialchars($employee['e_id']); ?>" disabled>
                </div>

                <!-- Only show editable manager field for Directors -->
                <?php if ($user_role === 'Director IT'): ?>
                    <div class="form-group">
                        <label>First Name:</label>
                        <input type="text" value="<?php echo htmlspecialchars($employee['f_name']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Last Name:</label>
                        <input type="text" value="<?php echo htmlspecialchars($employee['l_name']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Location:</label>
                        <select name="zip_code">
                            <option value="">None</option>
                            <?php foreach ($locations as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['zip_code'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Manager:</label>
                        <select name="m_id">
                            <option value="">None</option>
                            <?php foreach ($managers as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['m_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Only show editable location,manager, director, vice president, senior vice president for SVP and VP-->
                <?php if ($user_role === 'SVP' || $user_role === 'VP'): ?>
                    <div class="form-group">
                        <label>First Name:</label>
                        <input type="text" value="<?php echo htmlspecialchars($employee['f_name']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Last Name:</label>
                        <input type="text" value="<?php echo htmlspecialchars($employee['l_name']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Location:</label>
                        <select name="zip_code">
                            <option value="">None</option>
                            <?php foreach ($locations as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['zip_code'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Manager:</label>
                        <select name="m_id">
                            <option value="">None</option>
                            <?php foreach ($managers as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['m_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Director:</label>
                        <select name="d_id">
                            <option value="">None</option>
                            <?php foreach ($directors as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['d_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vice President:</label>
                        <select name="vp_id">
                            <option value="">None</option>
                            <?php foreach ($vps as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['vp_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Senior Vice President:</label>
                        <select name="svp_id">
                            <option value="">None</option>
                            <?php foreach ($svps as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['svp_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                    
                    
                    
                    
                    

                <!-- System Admin only fields -->
                <?php if ($user_role === 'Systems Administrator'): ?>
                    <!-- Include all the other editable fields here -->
                    <div class="form-group">
                        <label>First Name:</label>
                        <input type="text" name="f_name" value="<?php echo htmlspecialchars($employee['f_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Last Name:</label>
                        <input type="text" name="l_name" value="<?php echo htmlspecialchars($employee['l_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Organization Name:</label>
                        <input type="text" name="org_name" value="<?php echo htmlspecialchars($employee['org_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($employee['username']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Job Title:</label>
                        <select name="job_code">
                            <?php foreach ($jobs as $code => $title): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code == $employee['job_code'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Security Clearance:</label>
                        <select name="security_clearance">
                            <?php foreach ($clearances as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['security_clearance'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Manager:</label>
                        <select name="m_id">
                            <option value="">None</option>
                            <?php foreach ($managers as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['m_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Director:</label>
                        <select name="d_id">
                            <option value="">None</option>
                            <?php foreach ($directors as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['d_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Vice President:</label>
                        <select name="vp_id">
                            <option value="">None</option>
                            <?php foreach ($vps as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['vp_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Senior VP:</label>
                        <select name="svp_id">
                            <option value="">None</option>
                            <?php foreach ($svps as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $id == $employee['svp_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Location:</label>
                        <select name="zip_code">
                            <?php foreach ($locations as $code => $location): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code == $employee['zip_code'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Password Reset Required:</label>
                        <select name="password_reset_required">
                            <option value="1" <?php echo $employee['password_reset_required'] == 1 ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?php echo $employee['password_reset_required'] == 0 ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <a href="employees_table.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <?php include '../../templates/layouts/footer.php'; ?>
</body>
</html> 