<?php
    session_start();
    include 'connection_check.php';

    // If user is logged in, fetch their security clearance
    if (isset($_SESSION['e_id'])) {
        $stmt = $conn->prepare("SELECT security_clearance FROM FedEx_Employees WHERE e_id = ?");
        $stmt->bind_param("s", $_SESSION['e_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $_SESSION['security_clearance'] = $row['security_clearance'];
            $security_clearance = $row['security_clearance'];
        }
        $stmt->close();
    }
?>