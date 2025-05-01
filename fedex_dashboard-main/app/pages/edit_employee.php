<?php
$basePath = '../../';
include '../../templates/session/private_session.php';

// Get user's role
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

// Check if user has sufficient clearance (Director or higher)
if ($security_clearance < 3) {
    header("Location: employees_table.php?error=unauthorized");
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
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    header("Location: employees_table.php?error=employee_not_found");
    exit();
}

// For Directors, check if they're trying to edit an employee under them
if ($user_role === 'Director' && $employee['d_id'] !== $_SESSION['e_id']) {
    header("Location: employees_table.php?error=unauthorized");
    exit();
}

// Fetch available managers based on user role
$managers_sql = "SELECT e_id, f_name, l_name 
                 FROM FedEx_Employees 
                 WHERE job_code IN (SELECT job_code FROM FedEx_Jobs WHERE job_title LIKE '%Manager%')";

// If user is a Director, only show managers under them
if ($user_role === 'Director') {
    $managers_sql .= " AND d_id = ?";
    $managers_stmt = $conn->prepare($managers_sql);
    $managers_stmt->bind_param("s", $_SESSION['e_id']);
} else {
    $managers_stmt = $conn->prepare($managers_sql);
}

$managers_stmt->execute();
$managers_result = $managers_stmt->get_result();
$managers = [];
while ($row = $managers_result->fetch_assoc()) {
    $managers[$row['e_id']] = $row['f_name'] . ' ' . $row['l_name'];
}

// Only fetch other dropdowns if user is System Admin
if ($security_clearance >= 6) {
    // Fetch jobs
    $jobs_result = $conn->query("SELECT job_code, job_title FROM FedEx_Jobs ORDER BY job_title");
    $jobs = [];
    while ($row = $jobs_result->fetch_assoc()) {
        $jobs[$row['job_code']] = $row['job_title'];
    }

    // Fetch locations
    $locations_result = $conn->query("SELECT zip_code, city, state FROM FedEx_Locations ORDER BY city");
    $locations = [];
    while ($row = $locations_result->fetch_assoc()) {
        $locations[$row['zip_code']] = $row['city'] . ', ' . $row['state'];
    }

    // Fetch directors
    $directors_result = $conn->query("
        SELECT e_id, f_name, l_name 
        FROM FedEx_Employees 
        WHERE job_code IN (SELECT job_code FROM FedEx_Jobs WHERE job_title LIKE '%Director%')
        ORDER BY l_name, f_name
    ");
    $directors = [];
    while ($row = $directors_result->fetch_assoc()) {
        $directors[$row['e_id']] = $row['f_name'] . ' ' . $row['l_name'];
    }

    // Fetch VPs
    $vps_result = $conn->query("
        SELECT e_id, f_name, l_name 
        FROM FedEx_Employees 
        WHERE job_code IN (SELECT job_code FROM FedEx_Jobs WHERE job_title LIKE '%Vice President%')
        ORDER BY l_name, f_name
    ");
    $vps = [];
    while ($row = $vps_result->fetch_assoc()) {
        $vps[$row['e_id']] = $row['f_name'] . ' ' . $row['l_name'];
    }

    // Fetch SVPs
    $svps_result = $conn->query("
        SELECT e_id, f_name, l_name 
        FROM FedEx_Employees 
        WHERE job_code IN (SELECT job_code FROM FedEx_Jobs WHERE job_title = 'SVP')
        ORDER BY l_name, f_name
    ");
    $svps = [];
    while ($row = $svps_result->fetch_assoc()) {
        $svps[$row['e_id']] = $row['f_name'] . ' ' . $row['l_name'];
    }

    // Fetch security clearances
    $clearance_result = $conn->query("SELECT role_id, role_name FROM FedEx_Security_Clearance ORDER BY role_id");
    $clearances = [];
    while ($row = $clearance_result->fetch_assoc()) {
        $clearances[$row['role_id']] = $row['role_name'];
    }
}
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

                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" value="<?php echo htmlspecialchars($employee['f_name']); ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" value="<?php echo htmlspecialchars($employee['l_name']); ?>" disabled>
                </div>

                <!-- Only show editable manager field for Directors -->
                <?php if ($user_role === 'Director' || $security_clearance >= 6): ?>
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

                <!-- System Admin only fields -->
                <?php if ($security_clearance >= 6): ?>
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
                        <label>Tenure:</label>
                        <input type="text" name="tenure" value="<?php echo htmlspecialchars($employee['tenure']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Anniversary Date:</label>
                        <input type="date" name="anniversary" value="<?php echo htmlspecialchars($employee['anniversary']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Birth Date:</label>
                        <input type="date" name="birth_date" value="<?php echo htmlspecialchars($employee['birth_date']); ?>">
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