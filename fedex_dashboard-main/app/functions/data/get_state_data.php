<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chartType = $_POST['chart_type'] ?? '';
    
    // Handle state bar chart with director filter
    if ($chartType === 'state_bar') {
        $director = $_POST['director'] ?? 'all';
        
        // Base query for state data
        $query = "SELECT l.state, COUNT(e.e_id) as employee_count 
                 FROM FedEx_Locations l 
                 LEFT JOIN FedEx_Employees e ON l.zip_code = e.zip_code";

        // Add director filter if selected
        if ($director !== 'all') {
            $query .= " LEFT JOIN FedEx_Leadership d ON e.d_id = d.e_id 
                       WHERE CONCAT(d.job_title, ' - ', d.f_name, ' ', d.l_name) = ?";
        }

        $query .= " GROUP BY l.state ORDER BY l.state";

        $stmt = $conn->prepare($query);
        
        if ($director !== 'all') {
            $stmt->bind_param('s', $director);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        $labels = [];
        $data = [];
        $colors = [];

        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['state'];
            $data[] = $row['employee_count'];
            
            // Generate color based on count
            if ($row['employee_count'] == 0) {
                $colors[] = '#e0e0e0';
            } elseif ($row['employee_count'] <= 5) {
                $colors[] = '#FFE5CC';
            } elseif ($row['employee_count'] <= 10) {
                $colors[] = '#FFCC99';
            } elseif ($row['employee_count'] <= 20) {
                $colors[] = '#FFB366';
            } elseif ($row['employee_count'] <= 30) {
                $colors[] = '#FF9933';
            } elseif ($row['employee_count'] <= 50) {
                $colors[] = '#FF8000';
            } else {
                $colors[] = '#FF6600';
            }
        }

        echo json_encode([
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors
        ]);
    } 
    // Handle state-specific data for pie chart
    else if (isset($_POST['state'])) {
        $state = $_POST['state'];
        $sliceCount = isset($_POST['slice_count']) ? $_POST['slice_count'] : 'all';
        $dataType = isset($_POST['data_type']) ? $_POST['data_type'] : '';

        // If requesting leadership data
        if ($dataType === 'leadership') {
            $query = "SELECT 
                        SUM(CASE WHEN l.job_title LIKE '%Director%' THEN 1 ELSE 0 END) as director_count,
                        SUM(CASE WHEN l.job_title LIKE '%Manager%' THEN 1 ELSE 0 END) as manager_count
                     FROM FedEx_Leadership l
                     JOIN FedEx_Employees e ON l.e_id = e.e_id
                     JOIN FedEx_Locations loc ON e.zip_code = loc.zip_code
                     WHERE loc.state = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $state);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $row = $result->fetch_assoc();
            
            echo json_encode([
                'director_count' => $row['director_count'] ?? 0,
                'manager_count' => $row['manager_count'] ?? 0
            ]);
            exit;
        }

        // Query to get job code distribution for the selected state
        $query = "SELECT j.job_code, j.job_title, COUNT(e.e_id) as employee_count 
                FROM FedEx_Jobs j 
                LEFT JOIN FedEx_Employees e ON j.job_code = e.job_code 
                LEFT JOIN FedEx_Locations l ON e.zip_code = l.zip_code 
                WHERE l.state = ?
                GROUP BY j.job_code, j.job_title 
                ORDER BY employee_count DESC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $state);
        $stmt->execute();
        $result = $stmt->get_result();

        $jobCodes = [];
        $jobTitles = [];
        $employeeCounts = [];

        while ($row = $result->fetch_assoc()) {
            $jobCodes[] = $row['job_code'];
            $jobTitles[] = $row['job_title'];
            $employeeCounts[] = $row['employee_count'];
        }

        // Format labels and data based on slice count
        $labels = [];
        $data = [];

        if ($sliceCount === 'all') {
            $labels = $jobTitles;
            $data = $employeeCounts;
        } else {
            $count = intval($sliceCount);
            $labels = array_slice($jobTitles, 0, $count);
            $data = array_slice($employeeCounts, 0, $count);
        }

        // Return JSON response
        echo json_encode([
            'labels' => $labels,
            'data' => $data
        ]);
    }
}
?> 