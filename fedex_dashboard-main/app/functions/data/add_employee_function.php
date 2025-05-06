<?php
session_start();
include '../../../templates/session/connection_check.php';

// Get user's security clearance from session
$security_clearance = $_SESSION['security_clearance'] ?? '0';

// At least System Admin clearance (level 6)
if ($security_clearance != '6') {
    error_log("Access denied. Required: System Admin (6), Current: " . $security_clearance);
    header("Location: ../../pages/dashboard.php?error=unauthorized");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check current employee count against approved headcount
    $count_sql = "SELECT COUNT(*) as current_count FROM FedEx_Employees";
    $count_result = $conn->query($count_sql);
    $count_row = $count_result->fetch_assoc();
    $current_count = $count_row['current_count'];

    $approved_sql = "SELECT `FY23 Approved Headcount` FROM approved_headcount LIMIT 1";
    $approved_result = $conn->query($approved_sql);
    $approved_row = $approved_result->fetch_assoc();
    $approved_count = $approved_row['FY23 Approved Headcount'];

    if ($current_count >= $approved_count) {
        header("Location: ../../pages/add_employee.php?error=headcount_exceeded");
        exit();
    }

    $e_id = $_POST['e_id'];
    
    // Check for duplicate ID in FedEx_Employees table
    $check_active_sql = "SELECT e_id FROM FedEx_Employees WHERE e_id = ?";
    $check_active_stmt = $conn->prepare($check_active_sql);
    $check_active_stmt->bind_param("s", $e_id);
    $check_active_stmt->execute();
    $check_active_result = $check_active_stmt->get_result();
    
    // Check for duplicate ID in FedEx_Archived table
    $check_archived_sql = "SELECT e_id FROM FedEx_Archived WHERE e_id = ?";
    $check_archived_stmt = $conn->prepare($check_archived_sql);
    $check_archived_stmt->bind_param("s", $e_id);
    $check_archived_stmt->execute();
    $check_archived_result = $check_archived_stmt->get_result();
    
    if ($check_active_result->num_rows > 0) {
        header("Location: ../../pages/add_employee.php?error=duplicate_id_active");
        exit();
    }
    
    if ($check_archived_result->num_rows > 0) {
        header("Location: ../../pages/add_employee.php?error=duplicate_id_archived");
        exit();
    }
    
    $f_name = $_POST['f_name'];
    $l_name = $_POST['l_name'];
    
    // Format dates 
    $anniversary_date = date('d-M-Y', strtotime($_POST['anniversary_date']));
    $birthdate = date('d-M-Y', strtotime($_POST['birthdate']));
    
    $job_code = $_POST['job_code'];
    $security_clearance = $_POST['security_clearance'];
    $m_id = !empty($_POST['m_id']) ? $_POST['m_id'] : null;
    $d_id = !empty($_POST['d_id']) ? $_POST['d_id'] : null;
    $vp = $_POST['vp'];
    $zip_code = $_POST['zip_code'];
    
    // Get department ID from department name
    $dept_name = $_POST['department_id']; 
    $dept_sql = "SELECT DepartmentID FROM Departments WHERE DepartmentName = ? LIMIT 1";
    $dept_stmt = $conn->prepare($dept_sql);
    $dept_stmt->bind_param("s", $dept_name);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    $dept_row = $dept_result->fetch_assoc();
    $department_id = $dept_row ? $dept_row['DepartmentID'] : null;
    $dept_stmt->close();
    
    // Set default values
    $tenure = 0; 
    $username = strtolower($l_name); 
    $password = hash("sha256", $e_id); 
    $password_reset_required = 'TRUE'; 
    
    // Get current SVP's ID
    $svp_sql = "SELECT e_id FROM FedEx_Employees 
                WHERE job_code IN (SELECT job_code FROM FedEx_Jobs 
                WHERE job_title = 'SVP')";
    $svp_result = $conn->query($svp_sql);
    $svp_row = $svp_result->fetch_assoc();
    $svp = $svp_row ? $svp_row['e_id'] : null;

    // Insert employee into db
    $sql = "INSERT INTO FedEx_Employees (
                e_id, f_name, l_name, tenure, anniversary, birth_date, 
                username, password, password_reset_required, job_code, 
                security_clearance, m_id, d_id, vp_id, svp_id, zip_code,
                org_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param(
            "sssissssssiiiiiss",
            $e_id, $f_name, $l_name, $tenure, $anniversary_date, $birthdate,
            $username, $password, $password_reset_required, $job_code, 
            $security_clearance, $m_id, $d_id, $vp, $svp, $zip_code,
            $dept_name  
        );

        if ($stmt->execute()) {
            header("Location: ../../pages/employees_table.php?success=employee_added");
            exit();
        } else {
            header("Location: ../../pages/add_employee.php?error=" . urlencode($stmt->error));
            exit();
        }

        $stmt->close();
    } else {
        header("Location: ../../pages/add_employee.php?error=" . urlencode($conn->error));
        exit();
    }
} else {
    header("Location: ../../pages/add_employee.php");
    exit();
}
?> 