<?php

// This grabs the users security role  
$role_sql = "SELECT role_name FROM FedEx_Security_Clearance WHERE role_id = ?";
$stmt = $conn->prepare($role_sql);
$stmt->bind_param("i", $security_clearance);
$stmt->execute();
$result = $stmt->get_result();
$user_role = $result->fetch_assoc()['role_name'];



// This grabs employee info from db
$sql = "SELECT e.e_id, e.f_name, e.l_name, j.job_title, 
        m.f_name as manager_first_name, m.l_name as manager_last_name,
        l.city, l.state, d.f_name as director_first_name, d.l_name as director_last_name
        FROM FedEx_Employees e 
        JOIN FedEx_Jobs j ON e.job_code = j.job_code 
        LEFT JOIN FedEx_Employees m ON e.m_id = m.e_id
        LEFT JOIN FedEx_Employees d ON e.d_id = d.e_id
        LEFT JOIN FedEx_Locations l ON e.zip_code = l.zip_code";

// Limiting results based on user security clearance 
$params = [];
$types = "";

switch($user_role) {

    case 'Employee':
        $sql .= " WHERE e.e_id = ?" ;
        $params[] = [$e_id];
        $types .= "s";
        break;

    case 'Manager':
        $sql .= " WHERE e.m_id = ? OR e.e_id = ?";
        $params[] = [$e_id];
        $types .= "ss";
        break;

    case 'Director':
        $sql .= " WHERE e.d_id = ? OR e.m_id = ? OR e.e_id = ?";
        $params[] = [$e_id];
        $types .= "sss";
        break;

    case 'SVP':
    case 'VP':
    case 'System Admin':
    case 'SystemAdmin':
        break;   

    default:
        header("Location: ../pages/dashboard.php?error=Invalid security clearance");
        exit();

}
?>