<?php
// Turn off output buffering
ob_end_clean();
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

    $basePath = '../../';
    include '../../templates/session/private_session.php';

    // Getting user role and ID
    $role_sql = "SELECT role_name 
                    FROM FedEx_Security_Clearance 
                    WHERE role_id = ?";
    $stmt = $conn->prepare($role_sql);
    $stmt->bind_param('i', $security_clearance);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_role = $result->fetch_assoc()['role_name'];

?>

<!-- Grabbing Data: States dropdown filter -->
<?php

    // For SVP/VP/Admin - see all states
    if (in_array($user_role, ['SVP', 'VP', 'System Admin'])) {
        $stateQuery = "SELECT DISTINCT l.state 
                      FROM FedEx_Locations l
                      ORDER BY l.state";
        $stateStmt = $conn->prepare($stateQuery);
    }
    // For Manager and Director  
    else {
        $stateQuery = "SELECT DISTINCT l.state 
                      FROM FedEx_Locations l
                      INNER JOIN FedEx_Employees e ON l.zip_code = e.zip_code";
        
        // Add role-based filtering
        switch($user_role) {
            case 'Manager':
                $stateQuery .= " WHERE (e.m_id = ?)";
                $stateStmt = $conn->prepare($stateQuery . " ORDER BY l.state");
                $stateStmt->bind_param('s', $e_id);
                break;
            case 'Director':
                $stateQuery .= " WHERE (e.d_id = ? OR e.m_id IN (SELECT e_id FROM FedEx_Employees WHERE d_id = ?))";
                $stateStmt = $conn->prepare($stateQuery . " ORDER BY l.state");
                $stateStmt->bind_param('ss', $e_id, $e_id);
                break;
        }
    }

    $stateStmt->execute();
    $stateResult = $stateStmt->get_result();

    $states = [];
    while ($row = $stateResult->fetch_assoc()) {
        $states[] = $row['state'];
    }

?>

<!-- Grabing Data: Employee count by state for map -->
<?php
    
    // For SVP/VP/Admin - see all states
    if (in_array($user_role, ['SVP', 'VP', 'System Admin'])) {

        $stateCountQuery = "SELECT l.state, COUNT(e.e_id) as employee_count 
                            FROM FedEx_Locations l 
                            LEFT JOIN FedEx_Employees e ON l.zip_code = e.zip_code 
                            GROUP BY l.state 
                            ORDER BY l.state";

        $stateCountStmt = $conn->prepare($stateCountQuery);

    }
    // For Manager and Director
    else {

        $stateCountQuery = "SELECT l.state, COUNT(e.e_id) as employee_count 
                            FROM FedEx_Locations l 
                            INNER JOIN FedEx_Employees e ON l.zip_code = e.zip_code";
        // Role-based filtering
        switch($user_role) {
            case 'Manager':
                $stateCountQuery .= " WHERE (e.m_id = ?)";
                $stateCountStmt = $conn->prepare($stateCountQuery . " GROUP BY l.state ORDER BY l.state");
                $stateCountStmt->bind_param('s', $e_id);
                break;
            case 'Director':
                $stateCountQuery .= " WHERE (e.d_id = ? OR e.m_id IN (SELECT e_id FROM FedEx_Employees WHERE d_id = ?))";
                $stateCountStmt = $conn->prepare($stateCountQuery . " GROUP BY l.state ORDER BY l.state");
                $stateCountStmt->bind_param('ss', $e_id, $e_id);
                break;
        }

    }

    $stateCountStmt->execute();
    $stateCountResult = $stateCountStmt->get_result();

    $stateData = [];
    while ($row = $stateCountResult->fetch_assoc()) {
        $stateData[$row['state']] = $row['employee_count'];
    }

?>

<!-- Grabing Data: Job Code Distribution -->
<?php

    // Default query to get job code distribution. 
    if (in_array($user_role, ['SVP', 'VP', 'System Admin'])) {

        $jobCodeQuery = "SELECT j.job_code, j.job_title, COUNT(e.e_id) as employee_count
                        FROM FedEx_Employees e
                        INNER JOIN FedEx_Jobs j
                        ON e.job_code = j.job_code
                        GROUP BY j.job_code, j.job_title
                        ORDER BY j.job_title";

        $jobCodeStmt = $conn->prepare($jobCodeQuery);

    }
    // For Manager and Director
    else {

        $jobCodeQuery = "SELECT j.job_code, j.job_title, COUNT(e.e_id) as employee_count
                        FROM FedEx_Employees e
                        INNER JOIN FedEx_Jobs j 
                        ON e.job_code = j.job_code";

        // Role-based filtering
        switch($user_role) {
            case 'Manager':
                $jobCodeQuery .= " WHERE e.m_id = ?";
                $jobCodeStmt = $conn->prepare($jobCodeQuery . " GROUP BY j.job_title, j.job_code ORDER BY j.job_title");
                $jobCodeStmt->bind_param('s', $e_id);
                break;
            case 'Director':
                $jobCodeQuery .= " WHERE e.d_id = ? OR e.m_id IN (SELECT e_id FROM FedEx_Employees WHERE d_id = ?)";
                $jobCodeStmt = $conn->prepare($jobCodeQuery . " GROUP BY j.job_title, j.job_code ORDER BY j.job_title");
                $jobCodeStmt->bind_param('ss', $e_id, $e_id);
                break;
        }   

    }

    $jobCodeStmt->execute();
    $jobCodeResult = $jobCodeStmt->get_result();

    // Initialize arrays that will be used by JavaScript
    $jobCodes = [];
    $jobTitles = [];
    $employeeCounts = [];
    $jobCodeData = [];

    // Populate all arrays in a single loop
    while ($row = $jobCodeResult->fetch_assoc()) {
        // For the associative array display
        $jobCodeData[$row['job_title']] = $row['employee_count'];
        
        // For the JavaScript arrays
        $jobCodes[] = $row['job_code'] ?? ''; 
        $jobTitles[] = $row['job_title'];
        $employeeCounts[] = $row['employee_count'];
    }

?>  

<!-- Grabing Data: Headcount by Job Title and State -->
<?php
    // Initialize selected state
    $selected_state = isset($_GET['state']) ? $_GET['state'] : 'all';

    // For SVP, VP, System Admin
    if (in_array($user_role, ['SVP', 'VP', 'System Admin'])) {
        $jobTitleHeadcountQuery = "SELECT 
            j.job_title,
            COUNT(e.e_id) as employee_count
        FROM 
            FedEx_Employees e
        JOIN 
            FedEx_Jobs j ON e.job_code = j.job_code
        JOIN 
            FedEx_Locations l ON e.zip_code = l.zip_code
        WHERE 
            ? = 'all' OR l.state = ?
        GROUP BY 
            j.job_title
        ORDER BY 
            employee_count DESC, j.job_title";

        $jobTitleHeadcountStmt = $conn->prepare($jobTitleHeadcountQuery);
        $jobTitleHeadcountStmt->bind_param('ss', $selected_state, $selected_state);
    }
    // For Manager
    elseif ($user_role == 'Manager') {
        $jobTitleHeadcountQuery = "SELECT 
            j.job_title,
            COUNT(e.e_id) as employee_count
        FROM 
            FedEx_Employees e
        JOIN 
            FedEx_Jobs j ON e.job_code = j.job_code
        JOIN 
            FedEx_Locations l ON e.zip_code = l.zip_code
        WHERE 
            e.m_id = ?
            AND (? = 'all' OR l.state = ?)
        GROUP BY 
            j.job_title
        ORDER BY 
            employee_count DESC, j.job_title";

        $jobTitleHeadcountStmt = $conn->prepare($jobTitleHeadcountQuery);
        $jobTitleHeadcountStmt->bind_param('sss', $e_id, $selected_state, $selected_state);
    }
    // For Director
    elseif ($user_role == 'Director') {
        $jobTitleHeadcountQuery = "SELECT 
            j.job_title,
            COUNT(e.e_id) as employee_count
        FROM 
            FedEx_Employees e
        JOIN 
            FedEx_Jobs j ON e.job_code = j.job_code
        JOIN 
            FedEx_Locations l ON e.zip_code = l.zip_code
        WHERE 
            (e.d_id = ? OR e.m_id IN (SELECT e_id FROM FedEx_Employees WHERE d_id = ?))
            AND (? = 'all' OR l.state = ?)
        GROUP BY 
            j.job_title
        ORDER BY 
            employee_count DESC, j.job_title";

        $jobTitleHeadcountStmt = $conn->prepare($jobTitleHeadcountQuery);
        $jobTitleHeadcountStmt->bind_param('ssss', $e_id, $e_id, $selected_state, $selected_state);
    }

    $jobTitleHeadcountStmt->execute();
    $jobTitleHeadcountResult = $jobTitleHeadcountStmt->get_result();

    // Initialize the array to store job title headcount data
    $jobTitleHeadcountData = [];

    // Populate the array
    while ($row = $jobTitleHeadcountResult->fetch_assoc()) {
        $jobTitleHeadcountData[$row['job_title']] = $row['employee_count'];
    }

?>

<?php
// Only fetch leadership data if user is SVP, VP, or System Admin
if (in_array($user_role, ['SVP', 'VP', 'System Admin'])) {
    // Get the leadership type filter - keep existing state filter if present
    $leadership_type = isset($_GET['leadership_type']) ? $_GET['leadership_type'] : 'director';
    $selected_state = isset($_GET['state']) ? $_GET['state'] : 'all';
    
    // SQL for leaders headcount - changes based on selected filter
    if ($leadership_type == 'director') {
        $leaderQuery = "SELECT 
                          CONCAT(dir.f_name, ' ', dir.l_name) AS leader_name,
                          COUNT(e.e_id) AS employee_count
                        FROM 
                          FedEx_Employees e
                        JOIN 
                          FedEx_Employees dir ON e.d_id = dir.e_id
                        JOIN 
                          FedEx_Jobs j ON dir.job_code = j.job_code
                        WHERE 
                          j.job_title = 'Director IT'
                        GROUP BY 
                          dir.e_id, dir.f_name, dir.l_name
                        ORDER BY 
                          employee_count DESC";
    } else {
        $leaderQuery = "SELECT 
                          CONCAT(mgr.f_name, ' ', mgr.l_name) AS leader_name,
                          COUNT(e.e_id) AS employee_count
                        FROM 
                          FedEx_Employees e
                        JOIN 
                          FedEx_Employees mgr ON e.m_id = mgr.e_id
                        JOIN 
                          FedEx_Jobs j ON mgr.job_code = j.job_code
                        WHERE 
                          j.job_title = 'Manager IT'
                        GROUP BY 
                          mgr.e_id, mgr.f_name, mgr.l_name
                        ORDER BY 
                          employee_count DESC";
    }
    
    $leaderStmt = $conn->prepare($leaderQuery);
    $leaderStmt->execute();
    $leaderResult = $leaderStmt->get_result();
    
    // Initialize arrays
    $leaderNames = [];
    $leaderCounts = [];
    
    // Populate arrays
    while ($row = $leaderResult->fetch_assoc()) {
        $leaderNames[] = $row['leader_name'];
        $leaderCounts[] = $row['employee_count'];
    }
}
?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
    <?php
    
        $pageTitle = "Analytics";
        include '../../templates/layouts/head.php';
    
    ?>
    <head>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
        <script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
        <script src="https://cdn.jsdelivr.net/npm/topojson@3"></script>

        <style>
            .fixed-tooltip {
                background-color: #4D148C;
                color: white;
                padding: 8px 16px;
                z-index: 1000;
                text-align: center;
                position: absolute;
                font-weight: bold;
                top: 10px;
                left: 50%;
                transform: translateX(-50%);
                display: none;
            }
        </style>

    </head>

    <body>

        <?php include '../../templates/layouts/header.php'; ?>

        <script>
            // Store state data for the map
            const stateData = <?php echo json_encode($stateData); ?>;
            
            // Function to create the US map
            function createUSMap() {
                const width = document.getElementById('us-map').clientWidth;
                const height = 600;
                
                // Create SVG
                const svg = d3.select('#us-map')
                    .append('svg')
                    .attr('width', width)
                    .attr('height', height)
                    .attr('viewBox', '0 0 959 593')
                    .attr('preserveAspectRatio', 'xMidYMid meet');
                    
                // Get the fixed tooltip
                const fixedTooltip = d3.select('#fixed-tooltip');
                    
                // Load US map data
                d3.json('https://cdn.jsdelivr.net/npm/us-atlas@3/states-10m.json').then(data => {
                    const projection = d3.geoAlbersUsa()
                        .scale(1000)
                        .translate([width / 2 - 60, height / 2]);
                        
                    const path = d3.geoPath().projection(projection);
                    
                    // Convert TopoJSON to GeoJSON
                    const states = topojson.feature(data, data.objects.states);
                    
                    // Draw states
                    svg.append('g')
                        .selectAll('path')
                        .data(states.features)
                        .enter()
                        .append('path')
                        .attr('class', 'state')
                        .attr('d', path)
                        .attr('data-state', d => d.properties.name)
                        .style('fill', d => {
                            const stateAbbr = getStateAbbr(d.properties.name);
                            return stateData[stateAbbr] ? getColorScale(stateData[stateAbbr]) : '#e0e0e0';
                        })
                        .style('cursor', d => {
                            const stateAbbr = getStateAbbr(d.properties.name);
                            return stateData[stateAbbr] ? 'pointer' : 'default';
                        })
                        .on('mouseover', function(event, d) {
                            const stateAbbr = getStateAbbr(d.properties.name);
                            const count = stateData[stateAbbr] || 0;
                            
                            // Only change color if the state has employees
                            if (count > 0) {
                                d3.select(this).style('fill', '#FF6600'); 
                                
                                // Show and update the fixed tooltip
                                fixedTooltip.style('display', 'block')
                                           .html(`${d.properties.name}: ${count} employees`);
                            }
                        })
                        .on('mouseout', function(event, d) {
                            const stateAbbr = getStateAbbr(d.properties.name);
                            
                            d3.select(this)
                                .style('fill', stateData[stateAbbr] ? getColorScale(stateData[stateAbbr]) : '#e0e0e0');
                                
                            // Hide the fixed tooltip
                            fixedTooltip.style('display', 'none');
                        })
                        .on('click', function(event, d) {
                            const stateAbbr = getStateAbbr(d.properties.name);
                            // Only allow clicking if the state has employees
                            if (stateAbbr && stateData[stateAbbr] && stateData[stateAbbr] > 0) {
                                // Remove active class from all states
                                d3.selectAll('.state').classed('active', false);
                                
                                // Add active class to clicked state
                                d3.select(this).classed('active', true);
                                
                                if (document.getElementById('stateFilter')) {
                                    document.getElementById('stateFilter').value = stateAbbr;
                                    updateJobTitlePieChart();
                                    updateChart();
                                    updateStateBarChart(stateAbbr);
                                }
                            }
                        });
                });
            }
            
            // Function to get state abbreviation from full name
            function getStateAbbr(stateName) {
                const stateMap = {
                    'Alabama': 'AL', 'Alaska': 'AK', 'Arizona': 'AZ', 'Arkansas': 'AR', 'California': 'CA',
                    'Colorado': 'CO', 'Connecticut': 'CT', 'Delaware': 'DE', 'Florida': 'FL', 'Georgia': 'GA',
                    'Hawaii': 'HI', 'Idaho': 'ID', 'Illinois': 'IL', 'Indiana': 'IN', 'Iowa': 'IA',
                    'Kansas': 'KS', 'Kentucky': 'KY', 'Louisiana': 'LA', 'Maine': 'ME', 'Maryland': 'MD',
                    'Massachusetts': 'MA', 'Michigan': 'MI', 'Minnesota': 'MN', 'Mississippi': 'MS', 'Missouri': 'MO',
                    'Montana': 'MT', 'Nebraska': 'NE', 'Nevada': 'NV', 'New Hampshire': 'NH', 'New Jersey': 'NJ',
                    'New Mexico': 'NM', 'New York': 'NY', 'North Carolina': 'NC', 'North Dakota': 'ND', 'Ohio': 'OH',
                    'Oklahoma': 'OK', 'Oregon': 'OR', 'Pennsylvania': 'PA', 'Rhode Island': 'RI', 'South Carolina': 'SC',
                    'South Dakota': 'SD', 'Tennessee': 'TN', 'Texas': 'TX', 'Utah': 'UT', 'Vermont': 'VT',
                    'Virginia': 'VA', 'Washington': 'WA', 'West Virginia': 'WV', 'Wisconsin': 'WI', 'Wyoming': 'WY'
                };
                
                return stateMap[stateName];
            }
            
            // Function to get color based on employee count
            function getColorScale(count) {
                if (count === 0) return '#e0e0e0';
                if (count <= 5) return '#FFE5CC'; 
                if (count <= 10) return '#FFCC99'; 
                if (count <= 20) return '#FFB366'; 
                if (count <= 30) return '#FF9933'; 
                if (count <= 50) return '#FF8000'; 
                return '#FF6600'; 
            }
            
            // Manually call the map creation function when the page loads
            document.addEventListener('DOMContentLoaded', function() {
                createUSMap();
                console.log('Map initialization called');
            });
            
        </script>

        <main class="reports-container">
            <div class="reports-content">

                <h1>Analytics Dashboard</h1>
                <div class="analytics-section">
                    <h2>Employee Heatmap</h2>
                    <div class="map-container" id="us-map">
                        <div class="fixed-tooltip" id="fixed-tooltip"></div>
                    </div>
                </div>
                <div class="analytics-grid">
                    <!-- First section - Headcount by Job Title -->
                    <div class="analytics-section">
                        <h2>Headcount by Job Title</h2>
                        <div class="chart-controls">
                            <div class="filter-group">
                                <label for="stateFilter">Filter by State:</label>
                                <select id="stateFilter" onchange="updateJobTitlePieChart()">
                                    <option value="all" <?php echo $selected_state == 'all' ? 'selected' : ''; ?>>All States</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?php echo htmlspecialchars($state); ?>" <?php echo $selected_state == $state ? 'selected' : ''; ?>><?php echo htmlspecialchars($state); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="sliceCount">Number of Job Titles:</label>
                                <select id="sliceCount" onchange="updateJobTitlePieChart(false)">
                                    <option value="0">All Job Titles</option>
                                    <option value="5">Top 5</option>
                                    <option value="10" selected>Top 10</option>
                                    <option value="15">Top 15</option>
                                </select>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="jobTitlePieChart"></canvas>
                        </div>
                    </div>

                    <!-- Second section - Headcount by State -->
                    <div class="analytics-section">
                        <h2>Headcount by State</h2>
                        <div class="chart-container">
                            <canvas id="stateBarChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Add the Leadership chart in a new row AFTER the analytics-grid -->
                <?php if (in_array($user_role, ['SVP', 'VP', 'System Admin'])): ?>
                <div class="analytics-grid" style="grid-template-columns: 1fr; margin-top: 30px;">
                    <div class="analytics-section">
                        <h2>Headcount by Leadership</h2>
                        <div class="chart-controls">
                            <div class="filter-group">
                                <label for="leadershipType">View by:</label>
                                <select id="leadershipType" onchange="updateLeadershipChart(this.value)">
                                    <option value="director" <?php echo $leadership_type == 'director' ? 'selected' : ''; ?>>Directors</option>
                                    <option value="manager" <?php echo $leadership_type == 'manager' ? 'selected' : ''; ?>>Managers</option>
                                </select>
                            </div>
                        </div>
                        <div class="chart-container" style="height: 800px;">
                            <canvas id="leadershipBarChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </main>



        <?php include '../../templates/layouts/footer.php'; ?>

    </body>

</html>

<script>
// Store the job title headcount data
const jobTitleHeadcountData = <?php echo json_encode($jobTitleHeadcountData); ?>;
let jobTitlePieChart;

// Function to update the pie chart based on filters
function updateJobTitlePieChart(reload = true) {
    const stateFilter = document.getElementById('stateFilter').value;
    
    // If reload is true, fetch new data for the selected state
    if (reload) {
        window.location.href = window.location.pathname + '?state=' + stateFilter;
        return;
    }
    
    // Otherwise just update the display with existing data
    if (jobTitlePieChart) {
        jobTitlePieChart.destroy();
    }
    
    createJobTitlePieChart();
}

// Function to create the initial pie chart
function createJobTitlePieChart() {
    const ctx = document.getElementById('jobTitlePieChart').getContext('2d');
    
    // Clear any previous error messages
    const container = document.querySelector('#jobTitlePieChart').parentNode;
    const existingMsg = container.querySelector('.no-data-message');
    if (existingMsg) {
        container.removeChild(existingMsg);
    }
    
    // Get data for the chart
    let data = Object.entries(jobTitleHeadcountData);
    
    // Check if data exists
    if (data.length === 0) {
        // Create a message in the chart area
        const message = document.createElement('div');
        message.textContent = 'No data available for the selected state';
        message.className = 'no-data-message';
        message.style.position = 'absolute';
        message.style.top = '50%';
        message.style.left = '50%';
        message.style.transform = 'translate(-50%, -50%)';
        message.style.fontSize = '16px';
        message.style.fontWeight = 'bold';
        container.appendChild(message);
        return;
    }
    
    // Sort by count (descending)
    data.sort((a, b) => b[1] - a[1]);
    
    // Apply slice count filter if not 0 (all)
    const sliceCount = parseInt(document.getElementById('sliceCount').value);
    if (sliceCount > 0 && data.length > sliceCount) {
        // Get top N items without creating an "Other" category
        data = data.slice(0, sliceCount);
    }
    
    // Extract labels and values
    const labels = data.map(item => item[0]);
    const values = data.map(item => item[1]);
    
    // Define colors for the pie chart
    const colors = [
        '#4D148C', '#FF6600', '#003366', '#00A550', '#FF9900',
        '#6600CC', '#FF3300', '#006699', '#33CC33', '#FFCC00', 
        '#9900CC', '#FF6666', '#0099CC', '#66CC66', '#FFE066'
    ];
    
    // Create the chart
    jobTitlePieChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors.slice(0, data.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'left',
                    labels: {
                        boxWidth: 15,
                        padding: 15
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Initialize the chart when the page loads
document.addEventListener('DOMContentLoaded', function() {
    createJobTitlePieChart();
});
</script>

<script>
// For the state bar chart
let stateBarChart;

// Function to create or update the state bar chart
function createStateBarChart() {
    const ctx = document.getElementById('stateBarChart').getContext('2d');
    
    console.log("State data for chart:", stateData);
    
    let data = [];
    for (const state in stateData) {
        if (stateData.hasOwnProperty(state)) {
            data.push([state, stateData[state]]);
        }
    }
    
    // Sort by employee count (descending)
    data.sort((a, b) => b[1] - a[1]);
    
    // Limit to top 10 states
    if (data.length > 10) {
        data = data.slice(0, 10);
    }
    
    console.log("Processed data for chart:", data);
    
    // If no data, show message
    if (data.length === 0) {
        console.error("No state data available for chart");
        return;
    }
    
    // Extract labels and values
    const labels = data.map(item => item[0]);
    const values = data.map(item => item[1]);
    
    // If chart already exists, destroy it
    if (stateBarChart) {
        stateBarChart.destroy();
    }
    
    // Create the bar chart
    stateBarChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Employees',
                data: values,
                backgroundColor: '#4D148C',
                borderColor: '#4D148C',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'x', 
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Employees: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Employees'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'State'
                    }
                }
            }
        }
    });
}

// Function to update state bar chart when needed
function updateStateBarChart(stateAbbr) {
    // For now, just recreate the chart
    createStateBarChart();
}

// Initialize the chart when the page loads
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(createStateBarChart, 500);
});
</script>

<?php if (in_array($user_role, ['SVP', 'VP', 'System Admin'])): ?>
<script>
// Store the leadership data
const leaderNames = <?php echo json_encode($leaderNames); ?>;
const leaderCounts = <?php echo json_encode($leaderCounts); ?>;
const leadershipType = '<?php echo $leadership_type; ?>';
let leadershipChart;



// Function to create the leadership bar chart
function createLeadershipChart() {
    const ctx = document.getElementById('leadershipBarChart').getContext('2d');
    
    // Check if data exists
    if (leaderNames.length === 0) {
        return;
    }
    
    // Limit data to top 10 if too many
    let displayNames = [...leaderNames];
    let displayCounts = [...leaderCounts];
    
    if (displayNames.length > 10) {
        displayNames = displayNames;
        displayCounts = displayCounts;
    }
    
    // If chart already exists, destroy it
    if (leadershipChart) {
        leadershipChart.destroy();
    }
    
    // Create the chart
    leadershipChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: displayNames,
            datasets: [{
                label: 'Employees',
                data: displayCounts,
                backgroundColor: '#FF6600', 
                borderColor: '#FF6600',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y', 
            plugins: {
                title: {
                    display: true,
                    text: leadershipType === 'director' ? 'Headcount by Director' : 'Headcount by Manager'
                },
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Employees: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Employees'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: leadershipType === 'director' ? 'Director' : 'Manager'
                    }
                }
            }
        }
    });
}

// Function to update the leadership chart when filter changes
function updateLeadershipChart(type) {
    // Preserve existing state filter if present
    const stateFilter = document.getElementById('stateFilter').value;
    window.location.href = window.location.pathname + '?state=' + stateFilter + '&leadership_type=' + type;
}

// Initialize the leadership chart when the page loads
document.addEventListener('DOMContentLoaded', function() {
    createLeadershipChart();
});
</script>
<?php endif; ?>