<?php 
    $basePath = '../../';
    include '../../templates/session/private_session.php'; 

    // Get user's role and ID
    $role_sql = "SELECT role_name FROM FedEx_Security_Clearance WHERE role_id = ?";
    $stmt = $conn->prepare($role_sql);
    $stmt->bind_param("i", $security_clearance);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_role = $result->fetch_assoc()['role_name'];
   ?>

<?php
    // Get all states for the filter dropdown
    $stateQuery = "SELECT DISTINCT l.state FROM FedEx_Locations l ORDER BY l.state";
    $stateStmt = $conn->prepare($stateQuery);
    $stateStmt->execute();
    $stateResult = $stateStmt->get_result();
    
    $states = [];
    while ($row = $stateResult->fetch_assoc()) {
        $states[] = $row['state'];
    }

    // Get employee count by state for the map
    $stateCountQuery = "SELECT l.state, COUNT(e.e_id) as employee_count 
                       FROM FedEx_Locations l 
                       LEFT JOIN FedEx_Employees e ON l.zip_code = e.zip_code 
                       GROUP BY l.state 
                       ORDER BY l.state";
    
    $stateCountStmt = $conn->prepare($stateCountQuery);
    $stateCountStmt->execute();
    $stateCountResult = $stateCountStmt->get_result();
    
    $stateData = [];
    while ($row = $stateCountResult->fetch_assoc()) {
        $stateData[$row['state']] = $row['employee_count'];
    }

    // Default query to get job code distribution
    $query = "SELECT j.job_code, j.job_title, COUNT(e.e_id) as employee_count 
              FROM FedEx_Jobs j 
              LEFT JOIN FedEx_Employees e ON j.job_code = e.job_code";

    // Add role-based restrictions
    switch($user_role) {
        case 'Employee':
            $query .= " WHERE e.e_id = ?";
            break;
        case 'Manager':
            $query .= " WHERE (e.m_id = ? OR e.e_id = ?)";
            break;
        case 'Director':
            $query .= " WHERE (e.d_id = ? OR e.m_id = ? OR e.e_id = ?)";
            break;
        // SVP, VP, and System Admin can see all data
        default:
            break;
    }

    // Add GROUP BY and ORDER BY after the WHERE clause
    $query .= " GROUP BY j.job_code, j.job_title ORDER BY employee_count DESC";
    
    // Bind parameters based on role
    $stmt = $conn->prepare($query);
    switch($user_role) {
        case 'Employee':
            $stmt->bind_param('s', $e_id);
            break;
        case 'Manager':
            $stmt->bind_param('ss', $e_id, $e_id);
            break;
        case 'Director':
            $stmt->bind_param('sss', $e_id, $e_id, $e_id);
            break;
        default:
            // No parameters needed for higher roles
            break;
    }
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
    
    // Get headcount by employee levels under different directors
    $directorQuery = "SELECT 
                        l.job_title as director_title,
                        l.f_name as director_fname,
                        l.l_name as director_lname,
                        COUNT(e.e_id) as employee_count
                     FROM FedEx_Leadership l
                     LEFT JOIN FedEx_Employees e ON l.e_id = e.d_id
                     WHERE l.job_title LIKE '%Director%'
                     GROUP BY l.e_id, l.job_title, l.f_name, l.l_name
                     ORDER BY l.job_title, l.l_name";
    
    $directorStmt = $conn->prepare($directorQuery);
    $directorStmt->execute();
    $directorResult = $directorStmt->get_result();
    
    $directorTitles = [];
    $directorNames = [];
    $directorCounts = [];
    
    while ($row = $directorResult->fetch_assoc()) {
        $directorTitles[] = $row['director_title'];
        $directorNames[] = $row['director_fname'] . ' ' . $row['director_lname'];
        $directorCounts[] = $row['employee_count'];
    }
?>

<!DOCTYPE html>
<html lang="en">

    <?php 
        $pageTitle = 'Analytics';
        include '../../templates/layouts/head.php'; 
    ?>

    <head>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
        <script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
        <script src="https://cdn.jsdelivr.net/npm/topojson@3"></script>
        <style>
            .analytics-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 20px;
            }
            
            .analytics-section {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .chart-container {
                width: 100%;
                height: 500px; 
                margin-top: 15px;
                position: relative;
            }
            
            .analytics-chart {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
            
            .chart-controls {
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .chart-controls select {
                padding: 5px 10px;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            
            .chart-controls label {
                font-weight: 500;
            }
            
            .filter-group {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-right: 15px;
            }
            
            .map-container {
                width: 100%;
                height: 500px;
                position: relative;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            
            .state-tooltip {
                position: absolute;
                padding: 8px 12px;
                background: rgba(0, 0, 0, 0.8);
                color: white;
                border-radius: 4px;
                font-size: 14px;
                pointer-events: none;
                z-index: 100;
            }
            
            .state {
                fill: #e0e0e0;
                stroke: #fff;
                stroke-width: 0.5;
                transition: fill 0.3s;
            }
            
            .state:hover {
                fill: #FF6600; 
                cursor: pointer;
            }
            
            .state.active {
                fill: #4D148C; 
            }
            
            /* Add style for bonus editor */
            .bonus-editor-container {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                margin-top: 20px;
            }
            
            .bonus-cell {
                width: 100%;
                padding: 5px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 14px;
            }
        </style>
    </head>

    <body>

        <!-- Header -->
        <?php include '../../templates/layouts/header.php'; ?>

        <?php if (in_array($user_role, ['SVP', 'VP', 'System Admin', 'SystemAdmin'])): ?>
        <!-- SVP, VP, System Admin Analytics Dashboard -->
        <main class="reports-container">
        <div class="reports-content">
            <h1>Analytics Dashboard</h1>
            
            
            
            <div class="analytics-section">
                <h2>Employee Overview</h2>
                <div class="map-container" id="us-map">
                    <div class="state-tooltip" id="state-tooltip" style="display: none;"></div>
                </div>
            </div>

            <div class="analytics-grid">
                <div class="analytics-section">
                    <h2>Headcount by Job Code</h2>
                    <div class="chart-controls">
                        <div class="filter-group">
                            <label for="sliceCount">Number of Slices:</label>
                            <select id="sliceCount" onchange="updateChart()">
                                <option value="5">Top 5</option>
                                <option value="10">Top 10</option>
                                <option value="15">Top 15</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="stateFilter">State:</label>
                            <select id="stateFilter" onchange="updateChart()">
                                <option value="all">All States</option>
                                <?php foreach ($states as $state): ?>
                                    <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="jobDistributionChart"></canvas>
                    </div>
                </div>

                <div class="analytics-section">
                    <h2>Employees by State</h2>
                    <div class="chart-controls">
                        <div class="filter-group">
                            <label for="stateBarDirectorFilter">Filter by Director:</label>
                            <select id="stateBarDirectorFilter" onchange="updateStateBarChart()">
                                <option value="all">All Directors</option>
                                <?php foreach ($directorNames as $index => $name): ?>
                                    <option value="<?php echo htmlspecialchars($directorTitles[$index] . ' - ' . $name); ?>">
                                        <?php echo htmlspecialchars($directorTitles[$index] . ' - ' . $name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="stateBarChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="analytics-section" style="margin-top: 20px;">
                <h2>Headcount by Director</h2>
                <div class="chart-controls">
                    <div class="filter-group">
                        <label for="directorStateFilter">State:</label>
                        <select id="directorStateFilter" onchange="updateDirectorChart()">
                            <option value="all">All States</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="directorSortBy">Sort By:</label>
                        <select id="directorSortBy" onchange="updateDirectorChart()">
                            <option value="name">Director Name</option>
                            <option value="count">Employee Count</option>
                        </select>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="directorBarChart"></canvas>
                </div>
            </div>
            
            

    <script>
        // Store the full data
        const fullData = {
            jobCodes: <?php echo json_encode($jobCodes); ?>,
            jobTitles: <?php echo json_encode($jobTitles); ?>,
            employeeCounts: <?php echo json_encode($employeeCounts); ?>
        };
        
        // Store state data for the map
        const stateData = <?php echo json_encode($stateData); ?>;
        
        // Store director data for the bar chart
        const directorData = {
            titles: <?php echo json_encode($directorTitles); ?>,
            names: <?php echo json_encode($directorNames); ?>,
            counts: <?php echo json_encode($directorCounts); ?>
        };
        
        let chart;
        let stateBarChart;
        let directorBarChart;
        
        // Function to create the state bar chart
        function createStateBarChart() {
            const ctx = document.getElementById('stateBarChart').getContext('2d');
            
            // Sort states by employee count
            const sortedStates = Object.entries(stateData)
                .sort(([,a], [,b]) => b - a)
                .reduce((r, [k, v]) => ({ ...r, [k]: v }), {});
            
            const labels = Object.keys(sortedStates);
            const data = Object.values(sortedStates);
            
            // Generate colors based on employee count using purple shades
            const backgroundColors = data.map(count => {
                if (count === 0) return '#e0e0e0';
                if (count <= 5) return '#E6CCFF'; 
                if (count <= 10) return '#CC99FF'; 
                if (count <= 20) return '#B366FF'; 
                if (count <= 30) return '#9933FF'; 
                if (count <= 50) return '#8000FF'; 
                return '#4D148C'; 
            });
            
            stateBarChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Number of Employees',
                        data: data,
                        backgroundColor: backgroundColors,
                        borderColor: '#fff',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.raw} employees`;
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            formatter: function(value) {
                                return value;
                            },
                            font: {
                                weight: 'bold',
                                size: 12
                            },
                            color: '#000'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Employees',
                                font: {
                                    size: 14
                                }
                            },
                            ticks: {
                                font: {
                                    size: 12
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
        }
        
        // Function to create the US map
        function createUSMap() {
            const width = document.getElementById('us-map').clientWidth;
            const height = 500;
            
            // Create SVG
            const svg = d3.select('#us-map')
                .append('svg')
                .attr('width', width)
                .attr('height', height)
                .attr('viewBox', '0 0 959 593')
                .attr('preserveAspectRatio', 'xMidYMid meet');
                
            // Create tooltip
            const tooltip = d3.select('#state-tooltip');
            
            // Load US map data
            d3.json('https://cdn.jsdelivr.net/npm/us-atlas@3/states-10m.json').then(data => {
                const projection = d3.geoAlbersUsa()
                    .scale(1000)
                    .translate([width / 2, height / 2]);
                    
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
                            d3.select(this)
                                .style('fill', '#FF6600'); 
                            
                            // Get the SVG coordinates of the mouse
                            const svgRect = svg.node().getBoundingClientRect();
                            const x = event.clientX - svgRect.left;
                            const y = event.clientY - svgRect.top;
                            
                            // Fetch additional data for this state
                            fetch('../../app/functions/data/get_state_data.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `state=${encodeURIComponent(stateAbbr)}&data_type=leadership`
                            })
                            .then(response => response.json())
                            .then(data => {
                                tooltip.style('display', 'block')
                                    .html(`${d.properties.name}:<br>
                                          Employees: ${count}<br>
                                          Directors: ${data.director_count || 0}<br>
                                          Managers: ${data.manager_count || 0}`)
                                    .style('left', x + 'px')
                                    .style('top', y + 'px');
                            })
                            .catch(error => {
                                console.error('Error fetching leadership data:', error);
                                // Fallback to just showing employee count
                                tooltip.style('display', 'block')
                                    .html(`${d.properties.name}: ${count} employees`)
                                    .style('left', x + 'px')
                                    .style('top', y + 'px');
                            });
                        }
                    })
                    .on('mouseout', function(event, d) {
                        const stateAbbr = getStateAbbr(d.properties.name);
                        
                        d3.select(this)
                            .style('fill', stateData[stateAbbr] ? getColorScale(stateData[stateAbbr]) : '#e0e0e0');
                            
                        tooltip.style('display', 'none');
                    })
                    .on('click', function(event, d) {
                        const stateAbbr = getStateAbbr(d.properties.name);
                        // Only allow clicking if the state has employees
                        if (stateAbbr && stateData[stateAbbr] && stateData[stateAbbr] > 0) {
                            // Remove active class from all states
                            d3.selectAll('.state').classed('active', false);
                            
                            // Add active class to clicked state
                            d3.select(this).classed('active', true);
                            
                            document.getElementById('stateFilter').value = stateAbbr;
                            updateChart();
                            updateStateBarChart(stateAbbr);
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
        
        function updateChart() {
            const sliceCount = document.getElementById('sliceCount').value;
            const selectedState = document.getElementById('stateFilter').value;
            
            // If a specific state is selected, fetch data for that state
            if (selectedState !== 'all') {
                fetchStateData(selectedState, sliceCount);
                return;
            }
            
            let labels = [];
            let data = [];
            let backgroundColors = [];
            
            const colors = [
                '#FF0000', 
                '#00FF00', 
                '#0000FF', 
                '#FFA500', 
                '#800080', 
                '#008080', 
                '#FFD700', 
                '#FF69B4', 
                '#4B0082', 
                '#006400', 
                '#8B0000', 
                '#FF4500', 
                '#008000', 
                '#800000'  
            ];
            
            if (sliceCount === 'all') {
                labels = fullData.jobTitles;
                data = fullData.employeeCounts;
                backgroundColors = colors.slice(0, fullData.jobCodes.length);
            } else {
                const count = parseInt(sliceCount);
                labels = fullData.jobTitles.slice(0, count);
                data = fullData.employeeCounts.slice(0, count);
                backgroundColors = colors.slice(0, count);
            }
            
            renderChart(labels, data, backgroundColors);
        }
        
        function fetchStateData(state, sliceCount) {
            // Submit the form and handle the response
            fetch('../../app/functions/data/get_state_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `state=${encodeURIComponent(state)}&slice_count=${encodeURIComponent(sliceCount)}`
            })
            .then(response => response.json())
            .then(data => {
                const colors = [
                    '#FF0000', 
                    '#00FF00', 
                    '#0000FF', 
                    '#FFA500', 
                    '#800080', 
                    '#008080', 
                    '#FFD700', 
                    '#FF69B4', 
                    '#4B0082', 
                    '#006400', 
                    '#8B0000', 
                    '#000080', 
                    '#FF4500', 
                    '#008000', 
                    '#800000'  
                ];
                
                const backgroundColors = colors.slice(0, data.labels.length);
                renderChart(data.labels, data.data, backgroundColors);
            })
            .catch(error => {
                console.error('Error fetching state data:', error);
                alert('Error fetching data for the selected state. Please try again.');
            });
        }
        
        function renderChart(labels, data, backgroundColors) {
            if (chart) {
                chart.destroy();
            }
            
            const ctx = document.getElementById('jobDistributionChart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                font: {
                                    size: 14
                                },
                                boxWidth: 15,
                                padding: 10,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => ({
                                            text: label,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            hidden: false,
                                            lineCap: 'butt',
                                            lineDash: [],
                                            lineDashOffset: 0,
                                            lineJoin: 'miter',
                                            lineWidth: 1,
                                            strokeStyle: data.datasets[0].backgroundColor[i],
                                            pointStyle: 'circle',
                                            rotation: 0
                                        }));
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} employees (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Function to create the director bar chart
        function createDirectorBarChart() {
            const ctx = document.getElementById('directorBarChart').getContext('2d');
            
            // Create labels combining director title and name
            const labels = directorData.names.map((name, index) => 
                `${directorData.titles[index]} - ${name}`
            );
            
            // Generate colors based on employee count using purple shades
            const backgroundColors = directorData.counts.map(count => {
                if (count === 0) return '#e0e0e0';
                if (count <= 5) return '#E6CCFF'; 
                if (count <= 10) return '#CC99FF'; 
                if (count <= 20) return '#B366FF'; 
                if (count <= 30) return '#9933FF'; 
                if (count <= 50) return '#8000FF'; 
                return '#4D148C';       
            });
            
            directorBarChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Number of Employees',
                        data: directorData.counts,
                        backgroundColor: backgroundColors,
                        borderColor: '#fff',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.raw} employees`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Employees',
                                font: {
                                    size: 14
                                }
                            },
                            ticks: {
                                font: {
                                    size: 12
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Function to update the director chart based on filters
        function updateDirectorChart() {
            const selectedState = document.getElementById('directorStateFilter').value;
            const sortBy = document.getElementById('directorSortBy').value;
            
            // If a specific state is selected, fetch data for that state
            if (selectedState !== 'all') {
                fetchDirectorDataByState(selectedState, sortBy);
                return;
            }
            
            // Otherwise, use the existing data but apply sorting
            let sortedIndices = [...Array(directorData.names.length).keys()];
            
            if (sortBy === 'count') {
                // Sort by employee count (descending)
                sortedIndices.sort((a, b) => directorData.counts[b] - directorData.counts[a]);
            } else {
                // Sort by name (ascending)
                sortedIndices.sort((a, b) => directorData.names[a].localeCompare(directorData.names[b]));
            }
            
            // Apply the sorting
            const sortedLabels = sortedIndices.map(i => 
                `${directorData.titles[i]} - ${directorData.names[i]}`
            );
            const sortedData = sortedIndices.map(i => directorData.counts[i]);
            
            // Generate colors based on employee count
            const backgroundColors = sortedData.map(count => {
                if (count === 0) return '#e0e0e0';
                if (count <= 5) return '#E6CCFF'; 
                if (count <= 10) return '#CC99FF'; 
                if (count <= 20) return '#B366FF'; 
                if (count <= 30) return '#9933FF'; 
                if (count <= 50) return '#8000FF'; 
                return '#4D148C'; 
            });
            
            // Update the chart
            directorBarChart.data.labels = sortedLabels;
            directorBarChart.data.datasets[0].data = sortedData;
            directorBarChart.data.datasets[0].backgroundColor = backgroundColors;
            directorBarChart.update();
        }
        
        // Function to fetch director data filtered by state
        function fetchDirectorDataByState(state, sortBy) {
            // Submit the form and handle the response
            fetch('../../app/functions/data/get_director_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `state=${encodeURIComponent(state)}&sort_by=${encodeURIComponent(sortBy)}`
            })
            .then(response => response.json())
            .then(data => {
                // Update the chart with the new data
                directorBarChart.data.labels = data.labels;
                directorBarChart.data.datasets[0].data = data.data;
                directorBarChart.data.datasets[0].backgroundColor = data.colors;
                directorBarChart.update();
            })
            .catch(error => {
                console.error('Error fetching director data:', error);
                alert('Error fetching data for the selected state. Please try again.');
            });
        }
        
        // Function to update the state bar chart
        function updateStateBarChart() {
            const selectedDirector = document.getElementById('stateBarDirectorFilter').value;
            
            // Submit the form and handle the response
            fetch('../../app/functions/data/get_state_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `chart_type=state_bar&director=${encodeURIComponent(selectedDirector)}`
            })
            .then(response => response.json())
            .then(data => {
                // Update the state bar chart with the new data
                stateBarChart.data.labels = data.labels;
                stateBarChart.data.datasets[0].data = data.data;
                stateBarChart.data.datasets[0].backgroundColor = data.colors;
                stateBarChart.update();
            })
            .catch(error => {
                console.error('Error fetching state data:', error);
                alert('Error fetching data for the selected director. Please try again.');
            });
        }
        
        // Initialize the charts and map
        updateChart();
        createUSMap();
        createStateBarChart();
        createDirectorBarChart();
    </script>
            </div>
        </main>
        
        <?php elseif ($user_role === 'Director'): ?>
        <!-- Director Analytics Dashboard -->
        <main class="reports-container">
            <div class="reports-content">
                <h1>Director Analytics Dashboard</h1>
                <div class="analytics-section">
                    <h2>Your Team Overview</h2>
                    <p>This section will display analytics specific to your team as a Director.</p>
                    <p>You can customize this dashboard to show team performance, department metrics, and other director-specific data.</p>
                </div>
                
                <div class="analytics-grid">
                    <div class="analytics-section">
                        <h2>Team Distribution</h2>
                        <p>Here you can add charts showing the distribution of your team members by job code, location, etc.</p>
                    </div>
                    
                    <div class="analytics-section">
                        <h2>Team Performance</h2>
                        <p>Here you can add performance metrics for your team.</p>
                    </div>
                </div>
            </div>
        </main>
        
        <?php elseif ($user_role === 'Manager'): ?>
        <!-- Manager Analytics Dashboard -->
        <main class="reports-container">
            <div class="reports-content">
                <h1>Manager Analytics Dashboard</h1>
                <div class="analytics-section">
                    <h2>Your Team Overview</h2>
                    <p>This section will display analytics specific to your direct reports as a Manager.</p>
                    <p>You can customize this dashboard to show team performance, individual metrics, and other manager-specific data.</p>
                </div>
                
                <div class="analytics-grid">
                    <div class="analytics-section">
                        <h2>Team Composition</h2>
                        <p>Here you can add charts showing the composition of your direct reports.</p>
                    </div>
                    
                    <div class="analytics-section">
                        <h2>Individual Performance</h2>
                        <p>Here you can add individual performance metrics for your team members.</p>
                    </div>
                </div>
            </div>
        </main>
        
        <?php else: ?>
        <!-- Other Roles (Employee, etc.) -->
        <main class="reports-container">
            <div class="reports-content">
                <h1>Analytics Dashboard</h1>
                <div class="analytics-section">
                    <h2>Access Limited</h2>
                    <p>Your current role does not have access to the full analytics dashboard.</p>
                    <p>Please contact your manager or system administrator if you need access to specific analytics.</p>
                </div>
            </div>
        </main>
        <?php endif; ?>

        <!-- Footer -->
        <?php include '../../templates/layouts/footer.php'; ?>

    </body>

</html>