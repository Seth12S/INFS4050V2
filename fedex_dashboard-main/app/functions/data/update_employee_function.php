<?php
session_start();
include '../../../templates/session/connection_check.php';

$security_clearance = $_SESSION['security_clearance'] ?? '0';
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['e_id'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $e_id = $_POST['e_id'] ?? '';
    if (empty($e_id)) {
        header("Location: ../../pages/employees_table.php?error=no_id");
        exit();
    }

    try {
        if ($user_role === 'Director IT') {
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

        $conn->begin_transaction();
        $updates = [];
        $params = [];
        $types = "";

        // Director IT
        if ($user_role === 'Director IT') {
            if (isset($_POST['m_id'])) {
                $updates[] = "m_id = ?";
                $params[] = $_POST['m_id'];
                $types .= "s";
            }
            if (isset($_POST['zip_code'])) {
                $updates[] = "zip_code = ?";
                $params[] = $_POST['zip_code'];
                $types .= "s";
            }
        }

        // VP or SVP
        elseif ($user_role === 'VP' || $user_role === 'SVP') {
            foreach (['m_id', 'd_id', 'vp_id', 'svp_id', 'zip_code'] as $field) {
                if (isset($_POST[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $_POST[$field];
                    $types .= "s";
                }
            }
        }

        // System Admin
        elseif ($user_role === 'Systems Administrator') {
            $fields = [
                'f_name', 'l_name', 'org_name', 'username', 'job_code',
                'security_clearance', 'm_id', 'd_id', 'vp_id', 'svp_id',
                'zip_code', 'password_reset_required'
            ];
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $_POST[$field];
                    $types .= is_numeric($_POST[$field]) ? "i" : "s";
                }
            }
        }

        if (empty($updates)) {
            header("Location: ../../pages/employees_table.php?error=no_changes");
            exit();
        }

        $sql = "UPDATE FedEx_Employees SET " . implode(", ", $updates) . " WHERE e_id = ?";
        $params[] = $e_id;
        $types .= "s";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
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
