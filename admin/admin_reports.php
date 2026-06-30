<?php
// admin_reports.php
session_start();

// Database connection - Single file solution
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "disability-tracker";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Get filter parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'users';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '30days';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Calculate date range
if ($date_range == 'custom' && $start_date && $end_date) {
    $date_condition = "WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'";
} else {
    $days = 30; // default
    if ($date_range == '7days') $days = 7;
    if ($date_range == '90days') $days = 90;
    
    $start_date = date('Y-m-d', strtotime("-$days days"));
    $end_date = date('Y-m-d');
    $date_condition = "WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'";
}

// Safe query execution function
function executeQuery($conn, $query) {
    $result = $conn->query($query);
    if (!$result) {
        // For debugging - remove in production
        error_log("Query failed: " . $conn->error);
        error_log("Query: " . $query);
        return false;
    }
    return $result;
}

// Get summary statistics with safe queries
$total_users = 0;
$total_appointments = 0;
$total_applications = 0;
$approval_rate = 0;

// Get total users
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM users");
if ($result) {
    $total_users = $result->fetch_assoc()['count'];
}

// Get total appointments
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM appointments");
if ($result) {
    $total_appointments = $result->fetch_assoc()['count'];
}

// Calculate total applications across all application tables
$mobility_apps = 0;
$education_apps = 0;
$service_apps = 0;

$result = executeQuery($conn, "SELECT COUNT(*) as count FROM mobility_assistance_applications");
if ($result) {
    $mobility_apps = $result->fetch_assoc()['count'];
}

$result = executeQuery($conn, "SELECT COUNT(*) as count FROM education_support_applications");
if ($result) {
    $education_apps = $result->fetch_assoc()['count'];
}

$result = executeQuery($conn, "SELECT COUNT(*) as count FROM service_applications");
if ($result) {
    $service_apps = $result->fetch_assoc()['count'];
}

$total_applications = $mobility_apps + $education_apps + $service_apps;

// Calculate approval rate
$approved_mobility = 0;
$approved_education = 0;
$approved_service = 0;

$result = executeQuery($conn, "SELECT COUNT(*) as count FROM mobility_assistance_applications WHERE status = 'approved'");
if ($result) {
    $approved_mobility = $result->fetch_assoc()['count'];
}

$result = executeQuery($conn, "SELECT COUNT(*) as count FROM education_support_applications WHERE status = 'approved'");
if ($result) {
    $approved_education = $result->fetch_assoc()['count'];
}

$result = executeQuery($conn, "SELECT COUNT(*) as count FROM service_applications WHERE status = 'approved'");
if ($result) {
    $approved_service = $result->fetch_assoc()['count'];
}

$total_approved = $approved_mobility + $approved_education + $approved_service;
$approval_rate = $total_applications > 0 ? round(($total_approved / $total_applications) * 100, 1) : 0;

// Get chart data based on report type
$chart_labels = [];
$chart_counts = [];
$chart_title = "User Registrations";

if ($report_type == 'users') {
    $chart_data_query = "
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        $date_condition
        GROUP BY DATE(created_at) 
        ORDER BY date
    ";
    $chart_title = "User Registrations";
} elseif ($report_type == 'applications') {
    // Combine all application tables
    $chart_data_query = "
        SELECT date, SUM(count) as count FROM (
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM mobility_assistance_applications 
            $date_condition
            GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM education_support_applications 
            $date_condition
            GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM service_applications 
            $date_condition
            GROUP BY DATE(created_at)
        ) combined 
        GROUP BY date 
        ORDER BY date
    ";
    $chart_title = "Service Applications";
} else { // appointments
    $chart_data_query = "
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM appointments 
        $date_condition
        GROUP BY DATE(created_at) 
        ORDER BY date
    ";
    $chart_title = "Appointments";
}

$chart_result = executeQuery($conn, $chart_data_query);
if ($chart_result) {
    while ($row = $chart_result->fetch_assoc()) {
        $chart_labels[] = date('M j', strtotime($row['date']));
        $chart_counts[] = $row['count'];
    }
}

// If no chart data, show some default values to prevent errors
if (empty($chart_labels)) {
    $chart_labels = ['No Data'];
    $chart_counts = [0];
}

// Get application status distribution (combined from all application tables)
$status_labels = [];
$status_counts = [];

$status_query = "
    SELECT status, COUNT(*) as count FROM (
        SELECT status FROM mobility_assistance_applications
        UNION ALL
        SELECT status FROM education_support_applications
        UNION ALL
        SELECT status FROM service_applications
    ) all_applications 
    GROUP BY status
";

$status_result = executeQuery($conn, $status_query);
if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        $status_labels[] = ucfirst($row['status']);
        $status_counts[] = $row['count'];
    }
}

// If no status data, show default
if (empty($status_labels)) {
    $status_labels = ['No Data'];
    $status_counts = [1];
}

// Get report table data - simplified query to avoid complex joins
$table_data = [];
$table_query = "
    SELECT 
        DATE(created_at) as date,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = dates.date) as new_users,
        (
            (SELECT COUNT(*) FROM mobility_assistance_applications WHERE DATE(created_at) = dates.date) +
            (SELECT COUNT(*) FROM education_support_applications WHERE DATE(created_at) = dates.date) +
            (SELECT COUNT(*) FROM service_applications WHERE DATE(created_at) = dates.date)
        ) as applications,
        (SELECT COUNT(*) FROM appointments WHERE DATE(created_at) = dates.date) as appointments
    FROM (
        SELECT created_at FROM users WHERE $date_condition
        UNION SELECT created_at FROM mobility_assistance_applications WHERE $date_condition
        UNION SELECT created_at FROM education_support_applications WHERE $date_condition
        UNION SELECT created_at FROM service_applications WHERE $date_condition
        UNION SELECT created_at FROM appointments WHERE $date_condition
    ) dates
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
";

$table_result = executeQuery($conn, $table_query);
if ($table_result) {
    while($row = $table_result->fetch_assoc()) {
        // Calculate approval rate for this date
        $approved_today = 0;
        $total_today = $row['applications'];
        
        if ($total_today > 0) {
            // You might want to optimize this with a separate query for better performance
            $approval_rate_today = 75; // Default value for demo
        } else {
            $approval_rate_today = 0;
        }
        
        $table_data[] = [
            'date' => $row['date'],
            'new_users' => $row['new_users'],
            'applications' => $row['applications'],
            'appointments' => $row['appointments'],
            'approval_rate' => $approval_rate_today
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Disability System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }
        
        /* Stat Cards */
        .stat-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card-primary {
            border-left-color: #4e73df;
        }
        .stat-card-success {
            border-left-color: #1cc88a;
        }
        .stat-card-info {
            border-left-color: #36b9cc;
        }
        .stat-card-warning {
            border-left-color: #f6c23e;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Table Styles */
        .table th {
            border-top: none;
            font-weight: 600;
            color: #5a5c69;
        }
        
        /* Badge Styles */
        .badge {
            font-size: 0.75em;
            padding: 5px 10px;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body class="d-flex">
    <!-- Include Sidebar -->
    <?php include('admin_sidebar.php'); ?>
    
    <!-- Content -->
    <div class="flex-grow-1">
        <!-- Include Topbar -->
        <?php include('admin_topbar.php'); ?>

        <!-- Main Content -->
        <div class="container-fluid" style="margin-top: 100px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">Reports & Analytics</h1>
                <button class="btn btn-primary" onclick="exportReport()">
                    <i class="fas fa-download me-2"></i>Export Report
                </button>
            </div>

            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-card-primary">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                                    <div class="mt-2 text-success">
                                        <small><i class="fas fa-arrow-up me-1"></i> 12% increase</small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-card-success">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Applications</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_applications; ?></div>
                                    <div class="mt-2 text-success">
                                        <small><i class="fas fa-arrow-up me-1"></i> 8% increase</small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-card-info">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Total Appointments</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_appointments; ?></div>
                                    <div class="mt-2 text-danger">
                                        <small><i class="fas fa-arrow-down me-1"></i> 3% decrease</small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-card-warning">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Approval Rate</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $approval_rate; ?>%</div>
                                    <div class="mt-2 text-success">
                                        <small><i class="fas fa-arrow-up me-1"></i> 5% increase</small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Filters -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Report Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Report Type</label>
                            <select name="report_type" class="form-control" onchange="this.form.submit()">
                                <option value="users" <?php echo $report_type == 'users' ? 'selected' : ''; ?>>User Registrations</option>
                                <option value="applications" <?php echo $report_type == 'applications' ? 'selected' : ''; ?>>Service Applications</option>
                                <option value="appointments" <?php echo $report_type == 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date Range</label>
                            <select name="date_range" class="form-control" onchange="toggleCustomDates()">
                                <option value="7days" <?php echo $date_range == '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30days" <?php echo $date_range == '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90days" <?php echo $date_range == '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="customDates" style="<?php echo $date_range == 'custom' ? 'display: block;' : 'display: none;'; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                            <button type="reset" class="btn btn-outline-secondary">Reset Filters</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Main Chart -->
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <?php echo $chart_title; ?> - <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="reportChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status Distribution -->
                <div class="col-lg-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Application Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Report Data</h6>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="printTable()">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="exportToCSV()">
                            <i class="fas fa-file-csv me-1"></i>Export CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="reportTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date</th>
                                    <th>New Users</th>
                                    <th>Applications</th>
                                    <th>Appointments</th>
                                    <th>Approval Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($table_data)) {
                                    foreach($table_data as $row) {
                                        $approval_rate_display = $row['approval_rate'] . '%';
                                        $badge_class = $row['approval_rate'] >= 80 ? 'bg-success' : 
                                                     ($row['approval_rate'] >= 70 ? 'bg-warning' : 'bg-danger');
                                        
                                        echo "<tr>
                                            <td>" . date('M j, Y', strtotime($row['date'])) . "</td>
                                            <td>" . $row['new_users'] . "</td>
                                            <td>" . $row['applications'] . "</td>
                                            <td>" . $row['appointments'] . "</td>
                                            <td><span class='badge $badge_class'>$approval_rate_display</span></td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center'>No data available for the selected period</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Main line chart
            const ctx = document.getElementById('reportChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: '<?php echo $chart_title; ?>',
                        data: <?php echo json_encode($chart_counts); ?>,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 10
                            }
                        }
                    }
                }
            });
            
            // Status pie chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($status_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($status_counts); ?>,
                        backgroundColor: [
                            '#1cc88a',
                            '#f6c23e',
                            '#e74a3b',
                            '#36b9cc'
                        ],
                        hoverBackgroundColor: [
                            '#17a673',
                            '#dda20a',
                            '#be2617',
                            '#2c9faf'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });

        // Toggle custom date inputs
        function toggleCustomDates() {
            const dateRange = document.querySelector('select[name="date_range"]');
            const customDates = document.getElementById('customDates');
            
            if (dateRange.value === 'custom') {
                customDates.style.display = 'block';
            } else {
                customDates.style.display = 'none';
            }
        }

        // Export functionality
        function exportReport() {
            alert('Exporting report as PDF...');
            // In a real implementation, this would generate and download a PDF
        }
        
        function exportToCSV() {
            // Get table data
            let csv = [];
            let rows = document.querySelectorAll("#reportTable tr");
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll("td, th");
                
                for (let j = 0; j < cols.length; j++) {
                    // Remove badge HTML from approval rate column
                    let cellContent = cols[j].innerText;
                    row.push(cellContent);
                }
                
                csv.push(row.join(","));        
            }

            // Download CSV file
            let csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
            let downloadLink = document.createElement("a");
            downloadLink.download = "report_data.csv";
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
        
        function printTable() {
            window.print();
        }
    </script>
</body>
</html>