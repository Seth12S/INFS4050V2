<?php
session_start();
include '../../../templates/session/connection_check.php';

// Get user's security clearance from session
$security_clearance = $_SESSION['security_clearance'] ?? '0';

// Only System Admin (level 6) can archive employees
if ($security_clearance != '6') {
    header("Location: ../../pages/employees_table.php?error=unauthorized");
    exit();
}

// Check if employee ID is provided
if (!isset($_GET['id'])) {
    header("Location: ../../pages/employees_table.php?error=no_id");
    exit();
}

$employee_id = $_GET['id'];

// Start transaction
$conn->begin_transaction();

try {
    // Get employee data
    $sql = "SELECT e_id, f_name, l_name, job_code, zip_code, m_id, d_id, vp_id, svp_id 
            FROM FedEx_Employees 
            WHERE e_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Employee not found");
    }
    
    $employee = $result->fetch_assoc();
    
    // Check if employee is referenced by other employees
    $check_sql = "SELECT e_id FROM FedEx_Employees 
                  WHERE m_id = ? OR d_id = ? OR vp_id = ? OR svp_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ssss", 
        $employee['e_id'],
        $employee['e_id'],
        $employee['e_id'],
        $employee['e_id']
    );
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        throw new Exception("Cannot archive: Employee is referenced by other employees as manager/director/VP/SVP");
    }
    
    // Insert into archived table
    $sql = "INSERT INTO FedEx_Archived (e_id, f_name, l_name, job_code, zip_code, m_id, d_id, vp_id, svp_id, archived_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", 
        $employee['e_id'],
        $employee['f_name'],
        $employee['l_name'],
        $employee['job_code'],
        $employee['zip_code'],
        $employee['m_id'],
        $employee['d_id'],
        $employee['vp_id'],
        $employee['svp_id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert into archive: " . $stmt->error);
    }
    
    // Delete from employees table
    $sql = "DELETE FROM FedEx_Employees WHERE e_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete from employees: " . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Redirect with success message
    header("Location: ../../pages/employees_table.php?success=archived");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Archive failed for employee $employee_id: " . $e->getMessage());
    header("Location: ../../pages/employees_table.php?error=archive_failed&reason=" . urlencode($e->getMessage()));
    exit();
}
?> 