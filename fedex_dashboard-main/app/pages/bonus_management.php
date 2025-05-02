<?php
$basePath = '../../';
include '../../templates/session/private_session.php';

// Ensure we're using the team2 database
$conn->select_db('team2');

// Check if the fedex_bonus_data table exists
$check_table = $conn->query("SHOW TABLES LIKE 'fedex_bonus_data'");
if ($check_table->num_rows == 0) {
    // Read and execute the setup SQL file
    $sql = file_get_contents('../../database/fedex_bonus_data.sql');
    if ($sql === false) {
        die("Error: Could not read fedex_bonus_data.sql file");
    }
    
    // Split the SQL file into individual statements and execute them
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            if (!$conn->query($statement)) {
                die("Error executing SQL: " . $conn->error . "\nStatement: " . $statement);
            }
        }
    }
}

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

// Check if user has sufficient clearance (SVP or Vice President IT only)
if ($user_role !== 'SVP' && $user_role !== 'Vice President IT') {
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

// Handle form submission for editing bonus ratings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rating'])) {
    $bonus_id = $_POST['bonus_id'];
    $new_rating = $_POST['performance_rating'];
    
    // First update the performance rating
    $update_query = "UPDATE fedex_bonus_data SET fy25_performance_rating = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $new_rating, $bonus_id);
    $stmt->execute();
    
    // Get all employees and their ratings
    $employees_query = "SELECT id, fy25_performance_rating FROM fedex_bonus_data";
    $employees_result = $conn->query($employees_query);
    
    // Calculate sum of all performance ratings
    $total_sum = 0;
    $employees = [];
    while ($row = $employees_result->fetch_assoc()) {
        $total_sum += $row['fy25_performance_rating'];
        $employees[] = $row;
    }
    
    // Calculate and update each employee's weighted performance and bonus amount
    $total_bonus_pool = 2500000; // Fixed bonus pool amount
    foreach ($employees as $employee) {
        // Calculate weighted performance as a proportion of total ratings
        $weighted_performance = $employee['fy25_performance_rating'] / $total_sum;
        
        // Calculate bonus amount as a proportion of the total bonus pool
        $bonus_amount = $weighted_performance * $total_bonus_pool;
        
        // Update the employee's record
        $update_query = "UPDATE fedex_bonus_data 
                       SET weighted_performance = ?,
                           bonus_amount = ?
                       WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ddi", $weighted_performance, $bonus_amount, $employee['id']);
        $stmt->execute();
    }
    
    // Verify total bonus amount
    $verify_query = "SELECT SUM(bonus_amount) as total FROM fedex_bonus_data";
    $verify_result = $conn->query($verify_query);
    $total_bonus = $verify_result->fetch_assoc()['total'];
    
    // If there's any discrepancy due to rounding, adjust the last employee
    if (abs($total_bonus - $total_bonus_pool) > 0.01) {
        $adjustment = $total_bonus_pool - $total_bonus;
        $last_employee = end($employees);
        
        $adjust_query = "UPDATE fedex_bonus_data 
                       SET bonus_amount = bonus_amount + ?
                       WHERE id = ?";
        $stmt = $conn->prepare($adjust_query);
        $stmt->bind_param("di", $adjustment, $last_employee['id']);
        $stmt->execute();
    }
    
    // Redirect to refresh the page
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get total number of employees receiving bonuses
$total_query = "SELECT COUNT(*) as total FROM fedex_bonus_data";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_employees = 234; // Set fixed number of employees

// Set fixed total bonus amount
$total_amount = 2500000.00;

// Get average performance rating
$rating_query = "SELECT AVG(fy25_performance_rating) as avg_rating FROM fedex_bonus_data";
$rating_result = $conn->query($rating_query);
$rating_row = $rating_result->fetch_assoc();
$avg_rating = round($rating_row['avg_rating'], 2);

// Fetch all bonuses with employee information
$bonuses_query = "
    SELECT 
        b.*,
        e.job_code,
        j.job_title,
        e.org_name
    FROM fedex_bonus_data b
    LEFT JOIN FedEx_Employees e ON b.id = e.e_id
    LEFT JOIN FedEx_Jobs j ON e.job_code = j.job_code
    ORDER BY e.org_name, e.l_name, e.f_name
";
$bonuses_result = $conn->query($bonuses_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php 
        $pageTitle = 'Bonus Management';
        include '../../templates/layouts/head.php'; 
    ?>
    <style>
        .bonus-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .bonus-header {
            margin-bottom: 20px;
        }
        .bonus-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .bonus-table th, .bonus-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .bonus-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .bonus-table tr:hover {
            background-color: #f9f9f9;
        }
        .department-header {
            background-color: #e9ecef;
            font-weight: bold;
            padding: 10px;
            margin-top: 20px;
        }
        .edit-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .edit-form input {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .edit-form button {
            margin-top: 10px;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .edit-form button:hover {
            background-color: #0056b3;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .summary-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .summary-stats {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-box {
            flex: 1;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-box h3 {
            margin: 0;
            color: #495057;
            font-size: 0.9em;
        }
        .stat-box p {
            margin: 10px 0 0;
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
        }
        .employee-list {
            margin-top: 30px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .summary-table th,
        .summary-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .summary-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .summary-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Add Select2 styles */
        .select2-container {
            width: 100% !important;
            margin-bottom: 15px;
        }
        
        .select2-container--default .select2-selection--single {
            height: 38px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

        .filter-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }

        .filter-group {
            flex: 1;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include '../../templates/layouts/header.php'; ?>

    <main class="bonus-container">
        <h1>Bonus Management</h1>
        <p>Manage and update employee bonus ratings and amounts.</p>

        <!-- Search section -->
        <div class="search-section">
            <div class="search-row">
                <div class="search-group">
                    <label for="roleFilter">Filter by Role:</label>
                    <select id="roleFilter" class="form-control">
                        <option value="">All Roles</option>
                        <?php
                        $roles_query = "SELECT DISTINCT j.job_title 
                                      FROM FedEx_Jobs j 
                                      JOIN FedEx_Employees e ON j.job_code = e.job_code 
                                      JOIN fedex_bonus_data b ON e.e_id = b.id 
                                      WHERE b.bonus_amount > 0
                                      ORDER BY j.job_title";
                        $roles_result = $conn->query($roles_query);
                        while ($role = $roles_result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($role['job_title']) . '">' . 
                                 htmlspecialchars($role['job_title']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="search-group">
                    <label for="employeeSearch">Search Employee:</label>
                    <select id="employeeSearch" class="form-control">
                        <option value="">Search by name or ID...</option>
                        <?php
                        $employees_query = "
                            SELECT 
                                b.id,
                                b.first_name,
                                b.last_name,
                                j.job_title,
                                b.fy25_performance_rating,
                                b.weighted_performance,
                                b.bonus_amount
                            FROM fedex_bonus_data b
                            LEFT JOIN FedEx_Employees e ON b.id = e.e_id
                            LEFT JOIN FedEx_Jobs j ON e.job_code = j.job_code
                            WHERE b.bonus_amount > 0
                            ORDER BY b.last_name, b.first_name";
                        $employees_result = $conn->query($employees_query);
                        while ($employee = $employees_result->fetch_assoc()) {
                            $employee_data = json_encode([
                                'id' => $employee['id'],
                                'first_name' => $employee['first_name'],
                                'last_name' => $employee['last_name'],
                                'job_title' => $employee['job_title'],
                                'fy25_performance_rating' => $employee['fy25_performance_rating'],
                                'weighted_performance' => $employee['weighted_performance'],
                                'bonus_amount' => $employee['bonus_amount']
                            ]);
                            echo '<option value="' . htmlspecialchars($employee_data) . '">' . 
                                 htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name'] . 
                                 ' (' . $employee['job_title'] . ')') . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="search-group" style="display: flex; align-items: flex-end;">
                    <button id="clearFilters" class="btn btn-sm btn-outline-secondary" style="height: 38px; padding: 0 15px;">Clear Filters</button>
                </div>
            </div>
        </div>

        <!-- Edit form -->
        <div class="overlay" id="overlay"></div>
        <div class="edit-form" id="editForm">
            <h3>Edit Bonus Rating</h3>
            <form action="bonus_management.php" method="POST">
                <input type="hidden" name="update_rating" value="1">
                <input type="hidden" name="bonus_id" id="editBonusId">
                
                <div class="form-group">
                    <label>Employee Details:</label>
                    <div id="employeeDetails" class="employee-details">
                        <p><strong>Employee:</strong> <span id="selectedEmployee"></span></p>
                        <p><strong>Role:</strong> <span id="selectedRole"></span></p>
                        <p><strong>Current Rating:</strong> <span id="currentRating"></span></p>
                        <p><strong>Current Bonus:</strong> <span id="currentBonus"></span></p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="performance_rating">New Performance Rating (1-10):</label>
                    <input type="number" name="performance_rating" id="editPerformanceRating" min="1" max="10" required>
                </div>

                <button type="submit" class="btn-primary">Update Rating</button>
                <button type="button" onclick="closeEditForm()" class="btn-secondary">Cancel</button>
            </form>
        </div>

        <!-- Main table -->
        <div class="bonus-tables">
            <?php
            // Get all unique organizations
            $org_query = "SELECT DISTINCT e.org_name 
                         FROM FedEx_Employees e 
                         JOIN fedex_bonus_data b ON e.e_id = b.id 
                         WHERE b.bonus_amount > 0 
                         ORDER BY e.org_name";
            $org_result = $conn->query($org_query);
            
            while ($org = $org_result->fetch_assoc()) {
                $org_name = $org['org_name'] ?: 'Unassigned Organization';
                echo '<h3>' . htmlspecialchars($org_name) . '</h3>';
                echo '<table class="bonus-table">';
                echo '<thead><tr>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Performance Rating</th>
                        <th>Weighted Performance</th>
                        <th>Bonus Amount</th>
                        <th>Actions</th>
                      </tr></thead>';
                echo '<tbody>';
                
                // Get employees for this organization
                $emp_query = "SELECT 
                                b.id,
                                b.first_name,
                                b.last_name,
                                j.job_title,
                                b.fy25_performance_rating,
                                b.weighted_performance,
                                b.bonus_amount
                            FROM fedex_bonus_data b
                            LEFT JOIN FedEx_Employees e ON b.id = e.e_id
                            LEFT JOIN FedEx_Jobs j ON e.job_code = j.job_code
                            WHERE (e.org_name = ? OR (e.org_name IS NULL AND ? = 'Unassigned Organization'))
                            AND b.bonus_amount > 0
                            ORDER BY b.last_name, b.first_name";
                $stmt = $conn->prepare($emp_query);
                $stmt->bind_param("ss", $org['org_name'], $org['org_name']);
                $stmt->execute();
                $emp_result = $stmt->get_result();
                
                while ($employee = $emp_result->fetch_assoc()) {
                    $employee_data = json_encode([
                        'id' => $employee['id'],
                        'first_name' => $employee['first_name'],
                        'last_name' => $employee['last_name'],
                        'job_title' => $employee['job_title'],
                        'fy25_performance_rating' => $employee['fy25_performance_rating'],
                        'weighted_performance' => $employee['weighted_performance'],
                        'bonus_amount' => $employee['bonus_amount']
                    ]);
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($employee['job_title']) . '</td>';
                    echo '<td>' . htmlspecialchars($employee['fy25_performance_rating']) . '/10</td>';
                    echo '<td>' . number_format($employee['weighted_performance'] * 100, 2) . '%</td>';
                    echo '<td>$' . number_format($employee['bonus_amount'], 2) . '</td>';
                    echo '<td><button onclick="openEditForm(' . htmlspecialchars($employee_data) . ')" class="btn-edit">Edit Rating</button></td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            }
            ?>
        </div>
    </main>

    <!-- Add Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize Select2 for both dropdowns
            $('#roleFilter').select2({
                placeholder: "Select a role",
                allowClear: true
            });

            $('#employeeSearch').select2({
                placeholder: "Search by name or ID",
                allowClear: true,
                matcher: function(params, data) {
                    if ($.trim(params.term) === '') {
                        return data;
                    }

                    if (typeof data.text === 'undefined') {
                        return null;
                    }

                    var searchStr = data.text.toLowerCase();
                    if (searchStr.indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }

                    return null;
                }
            });

            // Function to filter the table
            function filterTable() {
                var selectedRole = $('#roleFilter').val();
                var searchTerm = $('#employeeSearch').val();
                var searchData = searchTerm ? JSON.parse(searchTerm) : null;

                $('.bonus-table tbody tr').each(function() {
                    var $row = $(this);
                    var rowRole = $row.find('td:eq(1)').text().trim();
                    var rowName = $row.find('td:eq(0)').text().trim();
                    var showRow = true;

                    // Filter by role
                    if (selectedRole && rowRole !== selectedRole) {
                        showRow = false;
                    }

                    // Filter by search
                    if (searchData) {
                        var searchName = searchData.last_name + ', ' + searchData.first_name;
                        if (rowName !== searchName) {
                            showRow = false;
                        }
                    }

                    // Show/hide the row
                    $row.toggle(showRow);

                    // Handle organization headers
                    if ($row.hasClass('org-header')) {
                        var $nextRows = $row.nextUntil('.org-header');
                        var hasVisibleRows = $nextRows.filter(':visible').length > 0;
                        $row.toggle(hasVisibleRows);
                    }
                });

                // Hide empty tables
                $('.bonus-table').each(function() {
                    var $table = $(this);
                    var hasVisibleRows = $table.find('tbody tr:visible').length > 0;
                    $table.toggle(hasVisibleRows);
                    if ($table.prev('h3').length) {
                        $table.prev('h3').toggle(hasVisibleRows);
                    }
                });
            }

            // Function to reset the view
            function resetView() {
                // Clear the select2 dropdowns
                $('#roleFilter').val(null).trigger('change');
                $('#employeeSearch').val(null).trigger('change');
                
                // Show all rows and tables
                $('.bonus-table tbody tr').show();
                $('.bonus-table').show();
                $('.bonus-table').prev('h3').show();
            }

            // Handle role filter change
            $('#roleFilter').on('change', function() {
                filterTable();
            });

            // Handle employee search change
            $('#employeeSearch').on('change', function() {
                filterTable();
            });

            // Handle clear filters button
            $('#clearFilters').on('click', function() {
                resetView();
            });

            // Handle employee selection
            $('#employeeSearch').on('select2:select', function(e) {
                var selectedData = e.params.data.id;
                if (selectedData) {
                    var employee = JSON.parse(selectedData);
                    // Find and click the edit button for the selected employee
                    var editButton = document.querySelector(`button[onclick="openEditForm(${JSON.stringify(employee)})"]`);
                    if (editButton) {
                        editButton.click();
                    }
                }
            });
        });

        function openEditForm(bonus) {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('editBonusId').value = bonus.id;
            document.getElementById('editPerformanceRating').value = bonus.fy25_performance_rating;
            document.getElementById('selectedEmployee').text = bonus.first_name + ' ' + bonus.last_name;
            document.getElementById('selectedRole').text = bonus.job_title;
            document.getElementById('currentRating').text = bonus.fy25_performance_rating + '/10';
            document.getElementById('currentBonus').text = '$' + parseFloat(bonus.bonus_amount).toFixed(2);
        }

        function closeEditForm() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('editForm').style.display = 'none';
            $('#employeeSearch').val(null).trigger('change');
            $('#roleFilter').val(null).trigger('change');
        }
    </script>

            </div>
        </main>
        
        <!-- Footer -->
        <?php include '../../templates/layouts/footer.php'; ?>
    </body>
</html> 