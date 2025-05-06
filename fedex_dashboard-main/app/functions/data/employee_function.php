<?php

include '../functions/restrictions/restrictions.php';

// Initialize the base SQL query
$sql = "SELECT e.e_id, e.f_name, e.l_name, j.job_title, 
        m.f_name as manager_first_name, m.l_name as manager_last_name,
        l.city, l.state, d.f_name as director_first_name, d.l_name as director_last_name
        FROM FedEx_Employees e 
        JOIN FedEx_Jobs j ON e.job_code = j.job_code 
        LEFT JOIN FedEx_Employees m ON e.m_id = m.e_id
        LEFT JOIN FedEx_Employees d ON e.d_id = d.e_id
        LEFT JOIN FedEx_Locations l ON e.zip_code = l.zip_code";

// Initialize arrays for parameters
$params = [];
$types = "";

// Get filter values from GET parameters
$job_title_filter = $_GET['job_title'] ?? '';
$director_filter = $_GET['director'] ?? '';
$manager_filter = $_GET['manager'] ?? '';
$city_filter = $_GET['city'] ?? '';
$state_filter = $_GET['state'] ?? '';
$search_query = $_GET['search'] ?? '';
$sort_column = $_GET['sort'] ?? '';
$sort_direction = $_GET['dir'] ?? '';

// Add base restrictions from user role
switch($user_role) {
    case 'Manager':
        $sql .= " WHERE (e.m_id = ? OR e.e_id = ?)";
        $params[] = $e_id;
        $params[] = $e_id;
        $types .= "ss";
        break;
    case 'Director':
        $sql .= " WHERE (e.d_id = ? OR e.m_id = ? OR e.e_id = ?)";
        $params[] = $e_id;
        $params[] = $e_id;
        $params[] = $e_id;
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

// Search Bar Filter
if (!empty($search_query)) {

    $search_condition = (strpos($sql, 'WHERE') === false ? " WHERE " : " AND ") . 
                        "(e.e_id LIKE ? OR e.f_name LIKE ? OR e.l_name LIKE ? OR CONCAT(e.f_name, ' ', e.l_name) LIKE ? OR e.m_id LIKE ? OR e.d_id LIKE ?)";
    $sql .= $search_condition;
    $search_param = "%$search_query%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= "ssssss";

}


// Job title filter
if (!empty($job_title_filter)) {
    $sql .= (strpos($sql, 'WHERE') === false ? " WHERE " : " AND ") . "j.job_title = ?";
    $params[] = $job_title_filter;
    $types .= "s";
}

// Director filter
if (!empty($director_filter)) {
    $director_parts = explode(' ', $director_filter);
    $director_first_name = $director_parts[0];
    $director_last_name = $director_parts[1];
    $sql .= (strpos($sql, 'WHERE') === false ? " WHERE " : " AND ") . 
            "(d.f_name = ? AND d.l_name = ?)";
    $params[] = $director_first_name;
    $params[] = $director_last_name;
    $types .= "ss";
}

// Manager filter
if (!empty($manager_filter)) {
    $sql .= (strpos($sql, 'WHERE') === false ? " WHERE " : " AND ") . 
            "(m.f_name = ? AND m.l_name = ?)";
    $params[] = explode(' ', $manager_filter)[0];
    $params[] = explode(' ', $manager_filter)[1];
    $types .= "ss";
}

// City filter
if (!empty($city_filter)) {
    $sql .= (strpos($sql, 'WHERE') === false ? " WHERE " : " AND ") . "l.city = ?";
    $params[] = $city_filter;
    $types .= "s";
}

// State filter
if (!empty($state_filter)) {
    $sql .= (strpos($sql, 'WHERE') === false ? " WHERE " : " AND ") . "l.state = ?";
    $params[] = $state_filter;
    $types .= "s";
}

// Sorting filter
if (!empty($sort_column)) {
    if ($sort_column === 'director') {
        $sql .= " ORDER BY d.l_name " . ($sort_direction === 'asc' ? 'ASC' : 'DESC') . 
                ", d.f_name " . ($sort_direction === 'asc' ? 'ASC' : 'DESC');
    } elseif ($sort_column === 'manager') {
        $sql .= " ORDER BY m.l_name " . ($sort_direction === 'asc' ? 'ASC' : 'DESC') . 
                ", m.f_name " . ($sort_direction === 'asc' ? 'ASC' : 'DESC');
    } else {
        $sql .= " ORDER BY " . $sort_column . " " . ($sort_direction === 'asc' ? 'ASC' : 'DESC');
    }
}

// This grabs the total number of employees  
$count_sql = $sql;
$count_params = $params;
$count_types = $types;

$count_stmt = $conn->prepare($count_sql);

if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_employees = $count_result->num_rows;

// Now calculate pagination
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rows_per_page = 15;
$total_pages = ceil($total_employees / $rows_per_page);

// This makes sure the user can't go to a page that doesn't exist
if ($current_page < 1) {
    $current_page = 1;
} elseif ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

// This calculates the offset for the query
$offset = ($current_page - 1) * $rows_per_page;

// This adds the limit and offset to the original query
$sql .= " LIMIT ? OFFSET ?";
$params[] = $rows_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// This builds the query string for pagination links
$query_string = '';
if (!empty($search_query)) {
    $query_string .= '&search=' . urlencode($search_query);
}
if (!empty($job_title_filter)) {
    $query_string .= '&job_title=' . urlencode($job_title_filter);
}
if (!empty($director_filter)) {
    $query_string .= '&director=' . urlencode($director_filter);
}
if (!empty($manager_filter)) {
    $query_string .= '&manager=' . urlencode($manager_filter);
}
if (!empty($city_filter)) {
    $query_string .= '&city=' . urlencode($city_filter);
}
if (!empty($state_filter)) {
    $query_string .= '&state=' . urlencode($state_filter);
}
if (!empty($sort_column)) {
    $query_string .= '&sort=' . urlencode($sort_column);
}
if (!empty($sort_direction)) {
    $query_string .= '&dir=' . urlencode($sort_direction);
}

// This is the query that grabs the unique values for the filters
$filter_sql = "SELECT DISTINCT 
    j.job_title,
    d.f_name as director_first_name, d.l_name as director_last_name,
    m.f_name as manager_first_name, m.l_name as manager_last_name,
    l.city, l.state
    FROM FedEx_Employees e 
    JOIN FedEx_Jobs j ON e.job_code = j.job_code 
    LEFT JOIN FedEx_Employees m ON e.m_id = m.e_id
    LEFT JOIN FedEx_Employees d ON e.d_id = d.e_id
    LEFT JOIN FedEx_Locations l ON e.zip_code = l.zip_code";

// This is the query that grabs the unique values for the filters
if ($user_role === 'Manager') {
    $filter_sql .= " WHERE e.m_id = ?";
    $filter_params = [$e_id];
    $filter_types = "s";
} elseif ($user_role === 'Director') {
    $filter_sql .= " WHERE e.d_id = ? OR e.m_id IN (SELECT e_id FROM FedEx_Employees WHERE d_id = ?)";
    $filter_params = [$e_id, $e_id];
    $filter_types = "ss";
} elseif ($user_role === 'VP' || $user_role === 'SVP') {
    $filter_sql .= " WHERE e.vp_id = ? OR e.d_id IN (SELECT e_id FROM FedEx_Employees WHERE vp_id = ?) 
                    OR e.m_id IN (SELECT e_id FROM FedEx_Employees WHERE d_id IN (SELECT e_id FROM FedEx_Employees WHERE vp_id = ?))";
    $filter_params = [$e_id, $e_id, $e_id];
    $filter_types = "sss";
}

$filter_stmt = $conn->prepare($filter_sql);
if (!empty($filter_params)) {
    $filter_stmt->bind_param($filter_types, ...$filter_params);
}
$filter_stmt->execute();
$filter_result = $filter_stmt->get_result();

// Initialize arrays for filter options
$job_titles = [];
$directors = [];
$managers = [];
$states = [];

// Get all unique values for filters (except cities)
while ($row = $filter_result->fetch_assoc()) {
    if (!in_array($row['job_title'], $job_titles)) {
        $job_titles[] = $row['job_title'];
    }
    
    if ($row['director_first_name'] && $row['director_last_name']) {
        $director_name = $row['director_first_name'] . ' ' . $row['director_last_name'];
        if (!in_array($director_name, $directors)) {
            $directors[] = $director_name;
        }
    }
    
    if ($row['manager_first_name'] && $row['manager_last_name']) {
        $manager_name = $row['manager_first_name'] . ' ' . $row['manager_last_name'];
        if (!in_array($manager_name, $managers)) {
            $managers[] = $manager_name;
        }
    }
    
    if ($row['state'] && !in_array($row['state'], $states)) {
        $states[] = $row['state'];
    }
}

// Now, get cities based on selected state
$cities = [];
if (!empty($state_filter)) {
    $city_stmt = $conn->prepare("SELECT DISTINCT l.city FROM FedEx_Locations l WHERE l.state = ? ORDER BY l.city");
    $city_stmt->bind_param("s", $state_filter);
    $city_stmt->execute();
    $city_result = $city_stmt->get_result();
    while ($row = $city_result->fetch_assoc()) {
        $cities[] = $row['city'];
    }
    $city_stmt->close();
} else {
    // If no state selected, show all cities
    $city_stmt = $conn->prepare("SELECT DISTINCT l.city FROM FedEx_Locations l ORDER BY l.city");
    $city_stmt->execute();
    $city_result = $city_stmt->get_result();
    while ($row = $city_result->fetch_assoc()) {
        $cities[] = $row['city'];
    }
    $city_stmt->close();
}

// Sort the arrays alphabetically
sort($job_titles);
sort($directors);
sort($managers);
sort($cities);
sort($states);
?>