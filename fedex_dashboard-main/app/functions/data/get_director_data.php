<?php

    // Get parameters from the request
    $state = isset($_POST['state']) ? $_POST['state'] : 'all';
    $sortBy = isset($_POST['sort_by']) ? $_POST['sort_by'] : 'name';

    // Base query to get headcount by employee levels under different directors
    $query = "SELECT 
                l.job_title as director_title,
                l.f_name as director_fname,
                l.l_name as director_lname,
                COUNT(e.e_id) as employee_count
             FROM FedEx_Leadership l
             LEFT JOIN FedEx_Employees e ON l.e_id = e.d_id";
    
    // Add state filter if a specific state is selected
    if ($state !== 'all') {
        $query .= " LEFT JOIN FedEx_Locations loc ON e.zip_code = loc.zip_code
                   WHERE l.job_title LIKE '%Director%' AND loc.state = ?";
    } else {
        $query .= " WHERE l.job_title LIKE '%Director%'";
    }
    
    $query .= " GROUP BY l.e_id, l.job_title, l.f_name, l.l_name";
    
    // Add sorting
    if ($sortBy === 'count') {
        $query .= " ORDER BY employee_count DESC, l.l_name";
    } else {
        $query .= " ORDER BY l.l_name";
    }
    
    $stmt = $conn->prepare($query);
    
    // Bind parameters if a specific state is selected
    if ($state !== 'all') {
        $stmt->bind_param('s', $state);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $directorTitles = [];
    $directorNames = [];
    $directorCounts = [];
    
    while ($row = $result->fetch_assoc()) {
        $directorTitles[] = $row['director_title'];
        $directorNames[] = $row['director_fname'] . ' ' . $row['director_lname'];
        $directorCounts[] = $row['employee_count'];
    }
    
    // Create labels combining director title and name
    $labels = [];
    for ($i = 0; $i < count($directorNames); $i++) {
        $labels[] = $directorTitles[$i] . ' - ' . $directorNames[$i];
    }
    
    // Generate colors based on employee count
    $colors = [];
    foreach ($directorCounts as $count) {
        if ($count === 0) {
            $colors[] = '#e0e0e0';
        } elseif ($count <= 5) {
            $colors[] = '#E6CCFF';
        } elseif ($count <= 10) {
            $colors[] = '#CC99FF'; 
        } elseif ($count <= 20) {
            $colors[] = '#B366FF'; 
        } elseif ($count <= 30) {
            $colors[] = '#9933FF'; 
        } elseif ($count <= 50) {
            $colors[] = '#8000FF'; 
        } else {
            $colors[] = '#4D148C'; 
        }
    }
    
    // Return the data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'labels' => $labels,
        'data' => $directorCounts,
        'colors' => $colors
    ]);
?> 