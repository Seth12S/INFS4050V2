<?php
session_start();
include '../../../templates/session/connection_check.php';

// Get user's security clearance and role from session
$security_clearance = $_SESSION['security_clearance'] ?? '0';
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['e_id'] ?? '';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the employee ID from the form
    $e_id = $_POST['e_id'] ?? '';
    
    if (empty($e_id)) {
        header("Location: ../../pages/employees_table.php?error=no_id");
        exit();
    }

    try {
        // For Directors, verify they're updating an employee under them
        if ($user_role === 'Director') {
            $check_stmt = $conn->prepare("SELECT d_id FROM FedEx_Employees WHERE e_id = ?");
            $check_stmt->bind_param("s", $e_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $employee_data = $check_result->fetch_assoc();

            if ($employee_data['d_id'] !== $user_id) {
                header("Location: ../../pages/employees_table.php?error=unauthorized");
                exit();
            }
        }

        // Start transaction
        $conn->begin_transaction();

        // Build the update SQL based on security clearance
        $updates = [];
        $params = [];
        $types = "";

        // Only Directors and System Admins can update
        if ($security_clearance >= 3) {
            // Directors can only update manager
            if ($user_role === 'Director') {
                if (isset($_POST['m_id'])) {
                    // Verify the selected manager is under this director
                    if (!empty($_POST['m_id'])) {
                        $manager_check = $conn->prepare("SELECT d_id FROM FedEx_Employees WHERE e_id = ?");
                        $manager_check->bind_param("s", $_POST['m_id']);
                        $manager_check->execute();
                        $manager_result = $manager_check->get_result();
                        $manager_data = $manager_result->fetch_assoc();

                        if ($manager_data['d_id'] === $user_id) {
                            $updates[] = "m_id = ?";
                            $params[] = $_POST['m_id'];
                            $types .= "s";
                        } else {
                            throw new Exception("Selected manager is not under your supervision.");
                        }
                    } else {
                        $updates[] = "m_id = NULL";
                    }
                }
            }
            // System Admin can update everything
            elseif ($security_clearance >= 6) {
                // Add all the system admin update fields here
                if (isset($_POST['f_name'])) {
                    $updates[] = "f_name = ?";
                    $params[] = $_POST['f_name'];
                    $types .= "s";
                }

                if (isset($_POST['l_name'])) {
                    $updates[] = "l_name = ?";
                    $params[] = $_POST['l_name'];
                    $types .= "s";
                }

                if (isset($_POST['tenure'])) {
                    $updates[] = "tenure = ?";
                    $params[] = $_POST['tenure'];
                    $types .= "i";
                }

                if (isset($_POST['anniversary'])) {
                    $updates[] = "anniversary = ?";
                    $params[] = $_POST['anniversary'];
                    $types .= "s";
                }

                if (isset($_POST['birth_date'])) {
                    $updates[] = "birth_date = ?";
                    $params[] = $_POST['birth_date'];
                    $types .= "s";
                }

                if (isset($_POST['org_name'])) {
                    $updates[] = "org_name = ?";
                    $params[] = $_POST['org_name'];
                    $types .= "s";
                }

                if (isset($_POST['username'])) {
                    $updates[] = "username = ?";
                    $params[] = $_POST['username'];
                    $types .= "s";
                }

                if (isset($_POST['job_code'])) {
                    $updates[] = "job_code = ?";
                    $params[] = $_POST['job_code'];
                    $types .= "s";
                }

                if (isset($_POST['security_clearance'])) {
                    $updates[] = "security_clearance = ?";
                    $params[] = $_POST['security_clearance'];
                    $types .= "i";
                }
            }
        }

        // If there are no updates, redirect back
        if (empty($updates)) {
            header("Location: ../../pages/employees_table.php?error=no_changes");
            exit();
        }

        // Build and execute the update query
        $sql = "UPDATE FedEx_Employees SET " . implode(", ", $updates) . " WHERE e_id = ?";
        $params[] = $e_id;
        $types .= "s";

        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Create reference array for bind_param
            $refs = [];
            foreach($params as $key => $value) {
                $refs[$key] = &$params[$key];
            }
            array_unshift($refs, $types);
            
            call_user_func_array([$stmt, 'bind_param'], $refs);
            
            if ($stmt->execute()) {
                $conn->commit();
                header("Location: ../../pages/employees_table.php?success=employee_updated");
                exit();
            } else {
                throw new Exception("Failed to update employee: " . $stmt->error);
            }
        } else {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Update failed for employee $e_id: " . $e->getMessage());
        header("Location: ../../pages/employees_table.php?error=update_failed&reason=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../../pages/employees_table.php");
    exit();
}
?> 