<?php 
    $basePath = '../../';
    include '../../templates/session/private_session.php'; 
?>
<!DOCTYPE html>
<html lang="en">

    <?php 
        $pageTitle = 'Employee Management';
        include '../../templates/layouts/head.php'; 
    ?>

    <body>

        <!-- Header -->
        <?php include '../../templates/layouts/header.php'; ?>

        <!-- Main content -->
        <?php include '../functions/data/employee_function.php'; ?>

        <main class="employees-container">
        <div class="container">
            <div class="header-section">
                <h1>Employee Management</h1>
                <?php if ($user_role === 'System Admin' || $user_role === 'SystemAdmin'): ?>
                <div class="add-employee-section">
                    <a href="add_employee.php" class="btn-primary">Add New Employee</a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php
            if (isset($_GET['error'])) {
                $error = $_GET['error'];
                $errorMessage = '';
                
                switch($error) {
                    case 'unauthorized':
                        $errorMessage = 'Error: You do not have permission to perform this action.';
                        break;
                    case 'no_id':
                        $errorMessage = 'Error: No employee ID provided.';
                        break;
                    case 'archive_failed':
                        $errorMessage = 'Error: Failed to archive employee. ' . (isset($_GET['reason']) ? htmlspecialchars($_GET['reason']) : '');
                        break;
                    default:
                        $errorMessage = 'Error: ' . htmlspecialchars($error);
                }
                
                echo '<div class="alert alert-danger">' . $errorMessage . '</div>';
            }
            
            if (isset($_GET['success'])) {
                $success = $_GET['success'];
                $successMessage = '';
                
                switch($success) {
                    case 'employee_added':
                        $successMessage = 'Employee has been added successfully!';
                        break;
                    case 'employee_updated':
                        $successMessage = 'Employee has been updated successfully!';
                        break;
                    case 'archived':
                        $successMessage = 'Employee has been archived successfully!';
                        break;
                    default:
                        $successMessage = 'Success: ' . htmlspecialchars($success);
                }
                
                echo '<div class="alert alert-success">' . $successMessage . '</div>';
            }
            ?>
            
            <!-- Search and Filter Section -->
            <form method="GET" class="search-filter-section">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search by ID, First Name, or Last Name" value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="search-btn">Search</button>
                </div>
                
                <div class="filter-section">
                    <select name="job_title">
                        <option value="">All Job Titles</option>
                        <?php foreach ($job_titles as $title): ?>
                            <option value="<?php echo htmlspecialchars($title); ?>" <?php echo $job_title_filter === $title ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="city">
                        <option value="">All Cities</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $city_filter === $city ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($city); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="state">
                        <option value="">All States</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?php echo htmlspecialchars($state); ?>" <?php echo $state_filter === $state ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($state); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="manager">
                        <option value="">All Managers</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?php echo htmlspecialchars($manager); ?>" <?php echo $manager_filter === $manager ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($manager); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="director">
                        <option value="">All Directors</option>
                        <?php foreach ($directors as $director): ?>
                            <option value="<?php echo htmlspecialchars($director); ?>" <?php echo $director_filter === $director ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($director); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <a href="employees_table.php" class="clear-btn">Clear Filters</a>
                </div>
            </form>

            <div class="table-container">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-column="e_id">
                                <a href="?sort=<?php echo $sort_column === 'e_id' ? '' : 'e_id'; ?>&dir=<?php echo $sort_column === 'e_id' && $sort_direction === 'asc' ? 'desc' : 'asc'; ?>">
                                    ID
                                </a>
                            </th>
                            <th class="sortable" data-column="f_name">
                                <a href="?sort=<?php echo $sort_column === 'f_name' ? '' : 'f_name'; ?>&dir=<?php echo $sort_column === 'f_name' && $sort_direction === 'asc' ? 'desc' : 'asc'; ?>">
                                    First Name
                                </a>
                            </th>
                            <th class="sortable" data-column="l_name">
                                <a href="?sort=<?php echo $sort_column === 'l_name' ? '' : 'l_name'; ?>&dir=<?php echo $sort_column === 'l_name' && $sort_direction === 'asc' ? 'desc' : 'asc'; ?>">
                                    Last Name
                                </a>
                            </th>
                            <th class="sortable" data-column="job_title">
                                <a href="?sort=<?php echo $sort_column === 'job_title' ? '' : 'job_title'; ?>&dir=<?php echo $sort_column === 'job_title' && $sort_direction === 'asc' ? 'desc' : 'asc'; ?>">
                                    Job Title
                                </a>
                            </th>
                            <th class="sortable" data-column="director">
                                <a href="?sort=<?php echo $sort_column === 'director' ? '' : 'director'; ?>&dir=<?php echo $sort_column === 'director' && $sort_direction === 'asc' ? 'desc' : 'asc'; ?>">
                                    Director
                                </a>
                            </th>
                            <th class="sortable" data-column="manager">
                                <a href="?sort=<?php echo $sort_column === 'manager' ? '' : 'manager'; ?>&dir=<?php echo $sort_column === 'manager' && $sort_direction === 'asc' ? 'desc' : 'asc'; ?>">
                                    Manager
                                </a>
                            </th>
                            <th class="sortable" data-column="city">
                                <a href="?sort=<?php echo $sort_column === 'city' ? '' : 'city'; ?>&dir=<?php echo $sort_column === 'city' && $sort_direction === 'asc' ? 'desc' : 'asc'; ?>">
                                    City
                                </a>
                            </th>
                            <th class="sortable" data-column="state">
                                <a href="?sort=<?php echo $sort_column === 'state' ? '' : 'state'; ?>&dir=<?php echo $sort_column === 'state' && $sort_direction === 'asc' ? 'desc' : 'asc'; ?>">
                                    State
                                </a>
                            </th>
                            <?php if ($user_role === 'System Admin' || $user_role === 'SystemAdmin' || $user_role === 'SVP' || $user_role === 'VP' || $user_role === 'Director' || $user_role === 'Manager'): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['e_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['f_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['l_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['job_title']); ?></td>
                                <td><?php echo $row['director_first_name'] && $row['director_last_name'] ? 
                                    htmlspecialchars($row['director_first_name'] . ' ' . $row['director_last_name']) : 'N/A'; ?></td>
                                <td><?php echo $row['manager_first_name'] && $row['manager_last_name'] ? 
                                    htmlspecialchars($row['manager_first_name'] . ' ' . $row['manager_last_name']) : 'N/A'; ?></td>
                                <td><?php echo $row['city'] ? htmlspecialchars($row['city']) : 'N/A'; ?></td>
                                <td><?php echo $row['state'] ? htmlspecialchars($row['state']) : 'N/A'; ?></td>
                                <?php if ($user_role === 'System Admin' || $user_role === 'SystemAdmin' || $user_role === 'SVP' || $user_role === 'VP' || $user_role === 'Director' || $user_role === 'Manager'): ?>
                                    <td>
                                        <div class="employee-actions">
                                            <a href="edit_employee.php?id=<?php echo htmlspecialchars($row['e_id']); ?>" class="edit-btn">Edit</a>
                                            <?php if ($user_role === 'System Admin' || $user_role === 'SystemAdmin'): ?>
                                                <a href="../functions/data/archive_function.php?id=<?php echo htmlspecialchars($row['e_id']); ?>" 
                                                   class="archive-btn" 
                                                   onclick="return confirm('Are you sure you want to archive this employee? This action cannot be undone.');">
                                                    Archive
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div class="pagination">
                <?php if ($total_pages > 1): ?>
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1<?php echo $query_string; ?>" class="pagination-btn">First</a>
                        <a href="?page=<?php echo ($current_page - 1) . $query_string; ?>" class="pagination-btn">Previous</a>
                    <?php endif; ?>
                    
                    <span class="page-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo ($current_page + 1) . $query_string; ?>" class="pagination-btn">Next</a>
                        <a href="?page=<?php echo $total_pages . $query_string; ?>" class="pagination-btn">Last</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="total-employees">
                Total Employees: <?php echo $total_employees; ?>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const rows = document.querySelectorAll('.employee-table tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });

            // Add smooth transitions for filter changes
            const filterSelects = document.querySelectorAll('.filter-section select');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.closest('form').submit();
                });
            });

            // Get the current sort parameters from the URL
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentDir = urlParams.get('dir');

            // Update the sort indicators
            if (currentSort) {
                const th = document.querySelector(`th[data-column="${currentSort}"]`);
                if (th) {
                    th.setAttribute('data-sort', currentDir);
                }
            }

            // Add click handlers to sortable headers
            document.querySelectorAll('.sortable').forEach(th => {
                th.addEventListener('click', function(e) {
                    e.preventDefault();
                    const column = this.getAttribute('data-column');
                    const currentSort = urlParams.get('sort');
                    const currentDir = urlParams.get('dir');

                    // If clicking the same column
                    if (currentSort === column) {
                        if (!currentDir || currentDir === '') {
                            // First click: set to ascending
                            urlParams.set('dir', 'asc');
                        } else if (currentDir === 'asc') {
                            // Second click: set to descending
                            urlParams.set('dir', 'desc');
                        } else if (currentDir === 'desc') {
                            // Third click: remove sorting
                            urlParams.delete('sort');
                            urlParams.delete('dir');
                        }
                    } else {
                        // This is what happens when you click a different column, it starts with ascending
                        urlParams.set('sort', column);
                        urlParams.set('dir', 'asc');
                    }

                    // This updates the Url  
                    window.location.href = window.location.pathname + '?' + urlParams.toString();
                });
            });
        });
    </script>

        <!-- Footer -->
        <?php include '../../templates/layouts/footer.php'; ?>

    </body>

</html>