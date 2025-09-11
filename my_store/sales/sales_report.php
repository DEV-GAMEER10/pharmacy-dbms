 <?php
// reports/sales_report.php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pharmacy_pos";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get sales data based on period
function getSalesData($pdo, $period = 'daily') {
    $data = [];
    
    switch($period) {
        case 'daily':
            // Last 30 days
            $sql = "SELECT 
                        DATE(created_at) as period_date,
                        DATE_FORMAT(created_at, '%b %d') as period,
                        SUM(total_amount) as sales,
                        COUNT(*) as orders,
                        ROUND(AVG(total_amount), 2) as avg_order
                    FROM sales 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY period_date ASC";
            break;
            
        case 'weekly':
            // Last 12 weeks
            $sql = "SELECT 
                        CONCAT('Week ', WEEK(created_at, 1)) as period,
                        DATE_FORMAT(DATE_SUB(created_at, INTERVAL DAYOFWEEK(created_at)-2 DAY), '%M %d') as period_start,
                        SUM(total_amount) as sales,
                        COUNT(*) as orders,
                        ROUND(AVG(total_amount), 2) as avg_order,
                        YEARWEEK(created_at, 1) as week_year
                    FROM sales 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                    GROUP BY YEARWEEK(created_at, 1)
                    ORDER BY week_year ASC";
            break;
            
        case 'monthly':
            // Last 12 months
            $sql = "SELECT 
                        DATE_FORMAT(created_at, '%b %Y') as period,
                        SUM(total_amount) as sales,
                        COUNT(*) as orders,
                        ROUND(AVG(total_amount), 2) as avg_order,
                        DATE_FORMAT(created_at, '%Y-%m') as month_year
                    FROM sales 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY YEAR(created_at), MONTH(created_at)
                    ORDER BY month_year ASC";
            break;
            
        case 'yearly':
            // Last 5 years
            $sql = "SELECT 
                        YEAR(created_at) as period,
                        SUM(total_amount) as sales,
                        COUNT(*) as orders,
                        ROUND(AVG(total_amount), 2) as avg_order
                    FROM sales 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR)
                    GROUP BY YEAR(created_at)
                    ORDER BY YEAR(created_at) ASC";
            break;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill missing periods with zero values for better visualization
    if ($period == 'daily' && count($data) < 30) {
        $data = fillMissingDays($data);
    }
    
    return $data;
}

function fillMissingDays($data) {
    $filledData = [];
    $existingDates = array_column($data, 'period_date');
    
    for($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $period = date('M d', strtotime("-$i days"));
        
        $found = false;
        foreach($data as $item) {
            if($item['period_date'] == $date) {
                $filledData[] = $item;
                $found = true;
                break;
            }
        }
        
        if(!$found) {
            $filledData[] = [
                'period_date' => $date,
                'period' => $period,
                'sales' => 0,
                'orders' => 0,
                'avg_order' => 0
            ];
        }
    }
    
    return $filledData;
}

// Get top selling medicines
function getTopMedicines($pdo, $limit = 5) {
    $sql = "SELECT 
                m.ItemName,
                m.SellingPrice,
                COUNT(si.item_id) as times_sold,
                SUM(si.quantity * si.price) as total_revenue
            FROM sales_items si
            JOIN medicines m ON si.item_id = m.ItemID
            JOIN sales s ON si.sale_id = s.sale_id
            WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY si.item_id, m.ItemName, m.SellingPrice
            ORDER BY times_sold DESC, total_revenue DESC
            LIMIT $limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get current period (default to daily)
$current_period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$sales_data = getSalesData($pdo, $current_period);
$top_medicines = getTopMedicines($pdo);

// Calculate summary statistics
$total_sales = array_sum(array_column($sales_data, 'sales'));
$total_orders = array_sum(array_column($sales_data, 'orders'));
$avg_order_value = $total_orders > 0 ? round($total_sales / $total_orders, 2) : 0;

// Calculate growth rate
$growth_rate = 0;
if (count($sales_data) >= 2) {
    $recent_periods = array_slice($sales_data, -6); // Last 6 periods
    $older_periods = array_slice($sales_data, 0, 6); // First 6 periods
    
    $recent_avg = array_sum(array_column($recent_periods, 'sales')) / count($recent_periods);
    $older_avg = array_sum(array_column($older_periods, 'sales')) / count($older_periods);
    
    if ($older_avg > 0) {
        $growth_rate = round((($recent_avg - $older_avg) / $older_avg) * 100, 1);
    }
}

// Get peak sales day/time
$peak_sales = 0;
$peak_period = '';
foreach($sales_data as $data_point) {
    if($data_point['sales'] > $peak_sales) {
        $peak_sales = $data_point['sales'];
        $peak_period = $data_point['period'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - MediVault</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .sidebar {
            background-color: #2c3e50;
            width: 250px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            padding: 20px 0;
            display: none; /* Hide the duplicate sidebar */
        }

        .sidebar .brand {
            background-color: #3498db;
            padding: 20px;
            margin: 0 20px 30px 20px;
            border-radius: 10px;
            text-align: center;
        }

        .sidebar .brand h3 {
            color: white;
            font-weight: 700;
            margin: 0;
        }

        .sidebar .nav-item {
            margin: 5px 20px;
        }

        .sidebar .nav-link {
            color: #bdc3c7;
            padding: 12px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover {
            background-color: #34495e;
            color: white;
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            background-color: #3498db;
            color: white;
        }

        .sidebar .nav-link i {
            margin-right: 12px;
            width: 20px;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            background-color: #f5f5f5;
            min-height: 100vh;
        }

        .header-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border-left: 4px solid #3498db;
        }

        .header-section h1 {
            color: #2c3e50;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .period-selector {
            margin-top: 25px;
        }

        .period-btn {
            background: white;
            border: 2px solid #e9ecef;
            color: #6c757d;
            padding: 10px 20px;
            margin-right: 10px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .period-btn:hover {
            border-color: #3498db;
            color: #3498db;
            transform: translateY(-2px);
        }

        .period-btn.active {
            background: #3498db;
            border-color: #3498db;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #3498db;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .stat-icon i {
            font-size: 1.8rem;
            color: #3498db;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .growth-indicator {
            margin-top: 15px;
            font-size: 0.9rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .growth-positive {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .growth-negative {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .chart-canvas {
            max-height: 400px;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
        }

        .table-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: #2c3e50;
            color: white;
            border: none;
            font-weight: 700;
            padding: 15px 20px;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }

        .table tbody td {
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            color: #495057;
        }

        .performance-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .performance-excellent {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .performance-good {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .performance-average {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .performance-below {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .top-medicines {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .medicine-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            margin: 15px 0;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            transition: all 0.3s ease;
        }

        .medicine-item:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .medicine-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .medicine-price {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .medicine-stats {
            text-align: right;
        }

        .sales-count {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .revenue-amount {
            color: #28a745;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(44, 62, 80, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-online {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    
    <div class="sidebar">
        <div class="brand">
            <h3><i class="fas fa-pills me-2"></i>MediVault</h3>
        </div>
        
        <div class="nav-item">
            <a href="../index.php" class="nav-link">
                <i class="fas fa-home"></i>
                About Us
            </a>
        </div>
        
        <div class="nav-item">
            <a href="../inventory.php" class="nav-link">
                <i class="fas fa-boxes"></i>
                Inventory
            </a>
        </div>
        
        <div class="nav-item">
            <a href="../sales_module.php" class="nav-link">
                <i class="fas fa-cash-register"></i>
                Sales Module
            </a>
        </div>
        
        <div class="nav-item">
            <a href="../waitlist.php" class="nav-link">
                <i class="fas fa-list"></i>
                Waitlist
            </a>
        </div>
        
        <div class="nav-item">
            <a href="#" class="nav-link active">
                <i class="fas fa-chart-bar"></i>
                Reports
            </a>
        </div>
        
        <div class="nav-item">
            <a href="../users.php" class="nav-link">
                <i class="fas fa-users"></i>
                Users
            </a>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

     
    <div class="main-content">
         Header Section 
        <div class="header-section">
            <h1><i class="fas fa-chart-line me-3"></i>Sales Reports</h1>
            <p class="header-subtitle">Comprehensive sales analytics and performance metrics</p>
            
            Period Selector 
            <div class="period-selector">
                <button class="btn period-btn <?php echo $current_period == 'daily' ? 'active' : ''; ?>" 
                        onclick="loadPeriod('daily')">
                    <i class="fas fa-calendar-day me-2"></i>Daily
                </button>
                <button class="btn period-btn <?php echo $current_period == 'weekly' ? 'active' : ''; ?>" 
                        onclick="loadPeriod('weekly')">
                    <i class="fas fa-calendar-week me-2"></i>Weekly
                </button>
                <button class="btn period-btn <?php echo $current_period == 'monthly' ? 'active' : ''; ?>" 
                        onclick="loadPeriod('monthly')">
                    <i class="fas fa-calendar-alt me-2"></i>Monthly
                </button>
                <button class="btn period-btn <?php echo $current_period == 'yearly' ? 'active' : ''; ?>" 
                        onclick="loadPeriod('yearly')">
                    <i class="fas fa-calendar me-2"></i>Yearly
                </button>
            </div>
        </div>

         Statistics Cards
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-value">₹<?php echo number_format($total_sales); ?></div>
                <div class="stat-label">Total Revenue</div>
                <div class="growth-indicator <?php echo $growth_rate >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                    <i class="fas fa-<?php echo $growth_rate >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo abs($growth_rate); ?>% Growth
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                <div class="stat-label">Total Orders</div>
                <div class="growth-indicator growth-positive">
                    <i class="fas fa-chart-line"></i>
                    Active Sales
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-value">₹<?php echo number_format($avg_order_value); ?></div>
                <div class="stat-label">Avg Order Value</div>
                <div class="growth-indicator growth-positive">
                    <i class="fas fa-coins"></i>
                    Per Transaction
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-value">₹<?php echo number_format($peak_sales); ?></div>
                <div class="stat-label">Peak Sales Day</div>
                <div class="growth-indicator growth-positive">
                    <i class="fas fa-star"></i>
                    <?php echo $peak_period; ?>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="fas fa-chart-area me-2"></i>
                    Sales Performance - <?php echo ucfirst($current_period); ?> Analysis
                </h3>
                <div>
                    <span class="status-badge status-online">
                        <i class="fas fa-circle me-1"></i>Live Data
                    </span>
                </div>
            </div>
            <canvas id="salesChart" class="chart-canvas"></canvas>
        </div>

        <?php if (!empty($top_medicines)): ?>
        <!-- Top Medicines Section -->
        <div class="top-medicines">
            <h3 class="chart-title mb-4">
                <i class="fas fa-pills me-2"></i>
                Top Performing Medicines
            </h3>
            <?php foreach($top_medicines as $index => $medicine): ?>
            <div class="medicine-item">
                <div>
                    <div class="medicine-name"><?php echo htmlspecialchars($medicine['ItemName']); ?></div>
                    <div class="medicine-price">₹<?php echo number_format($medicine['SellingPrice'], 2); ?> per unit</div>
                </div>
                <div class="medicine-stats">
                    <div class="sales-count"><?php echo $medicine['times_sold']; ?> sales</div>
                    <div class="revenue-amount">₹<?php echo number_format($medicine['total_revenue'], 2); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Data Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-table me-2"></i>Detailed Sales Breakdown</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar-alt me-2"></i>Period</th>
                            <th><i class="fas fa-rupee-sign me-2"></i>Sales Amount</th>
                            <th><i class="fas fa-shopping-bag me-2"></i>Orders</th>
                            <th><i class="fas fa-chart-line me-2"></i>Avg Order</th>
                            <th><i class="fas fa-award me-2"></i>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sales_data as $data): ?>
                        <tr>
                            <td><strong><?php echo $data['period']; ?></strong></td>
                            <td style="font-weight: 600;">₹<?php echo number_format($data['sales']); ?></td>
                            <td><?php echo number_format($data['orders']); ?></td>
                            <td>₹<?php echo number_format($data['avg_order']); ?></td>
                            <td>
                                <?php 
                                $performance = $total_sales > 0 ? ($data['sales'] / ($total_sales / count($sales_data))) : 0;
                                if ($performance > 1.5): ?>
                                    <span class="performance-badge performance-excellent">
                                        <i class="fas fa-star me-1"></i>Excellent
                                    </span>
                                <?php elseif ($performance > 1.2): ?>
                                    <span class="performance-badge performance-good">
                                        <i class="fas fa-thumbs-up me-1"></i>Very Good
                                    </span>
                                <?php elseif ($performance > 0.8): ?>
                                    <span class="performance-badge performance-average">
                                        <i class="fas fa-chart-line me-1"></i>Good
                                    </span>
                                <?php elseif ($performance > 0.5): ?>
                                    <span class="performance-badge performance-average">
                                        <i class="fas fa-minus-circle me-1"></i>Average
                                    </span>
                                <?php else: ?>
                                    <span class="performance-badge performance-below">
                                        <i class="fas fa-arrow-down me-1"></i>Below Avg
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let salesChart;
        
        // Sales data from PHP
        const salesData = <?php echo json_encode($sales_data); ?>;
        const currentPeriod = '<?php echo $current_period; ?>';
        
        // Initialize the chart
        function initChart() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (salesChart) {
                salesChart.destroy();
            }
            
            const labels = salesData.map(item => item.period);
            const sales = salesData.map(item => parseFloat(item.sales));
            const orders = salesData.map(item => parseInt(item.orders));
            
            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sales Revenue (₹)',
                        data: sales,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3498db',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 3,
                        pointHoverBackgroundColor: '#3498db',
                        pointHoverBorderColor: '#ffffff',
                    }, {
                        label: 'Order Count',
                        data: orders,
                        borderColor: '#2c3e50',
                        backgroundColor: 'rgba(44, 62, 80, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        pointBackgroundColor: '#2c3e50',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHoverBorderWidth: 2,
                        pointHoverBackgroundColor: '#2c3e50',
                        pointHoverBorderColor: '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    size: 12,
                                    weight: '600',
                                    family: "'Segoe UI', sans-serif"
                                },
                                color: '#2c3e50'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(44, 62, 80, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            borderColor: '#3498db',
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: true,
                            titleFont: {
                                size: 13,
                                weight: '600'
                            },
                            bodyFont: {
                                size: 12,
                                weight: '500'
                            },
                            padding: 12,
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        return 'Revenue: ₹' + context.parsed.y.toLocaleString('en-IN');
                                    } else {
                                        return 'Orders: ' + context.parsed.y.toLocaleString('en-IN');
                                    }
                                },
                                afterBody: function(context) {
                                    const dataIndex = context[0].dataIndex;
                                    const avgOrder = salesData[dataIndex].avg_order;
                                    return 'Avg Order: ₹' + parseFloat(avgOrder).toLocaleString('en-IN');
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Time Period',
                                font: {
                                    size: 14,
                                    weight: '600',
                                    family: "'Segoe UI', sans-serif"
                                },
                                color: '#2c3e50'
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.1)',
                                lineWidth: 1
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    weight: '500'
                                },
                                color: '#6c757d',
                                maxRotation: 45
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Sales Revenue (₹)',
                                font: {
                                    size: 14,
                                    weight: '600',
                                    family: "'Segoe UI', sans-serif"
                                },
                                color: '#3498db'
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    weight: '500'
                                },
                                color: '#3498db',
                                callback: function(value) {
                                    return '₹' + value.toLocaleString('en-IN');
                                }
                            },
                            grid: {
                                color: 'rgba(52, 152, 219, 0.2)',
                                lineWidth: 1
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Number of Orders',
                                font: {
                                    size: 14,
                                    weight: '600',
                                    family: "'Segoe UI', sans-serif"
                                },
                                color: '#2c3e50'
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    weight: '500'
                                },
                                color: '#2c3e50'
                            },
                            grid: {
                                drawOnChartArea: false,
                                color: 'rgba(44, 62, 80, 0.2)'
                            },
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }
        
        // Load different time periods
        function loadPeriod(period) {
            if (period === currentPeriod) return;
            
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            // Navigate to new period
            setTimeout(() => {
                window.location.href = '?period=' + period;
            }, 500);
        }
        
        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initChart();
            
            // Add number counting animation for stat values
            const countElements = document.querySelectorAll('.stat-value');
            countElements.forEach(element => {
                const target = parseFloat(element.textContent.replace(/[₹,]/g, ''));
                let current = 0;
                const increment = target / 30;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    if (element.textContent.includes('₹')) {
                        element.textContent = '₹' + Math.floor(current).toLocaleString('en-IN');
                    } else {
                        element.textContent = Math.floor(current).toLocaleString('en-IN');
                    }
                }, 50);
            });
        });
        
        // Add responsive chart resizing
        window.addEventListener('resize', function() {
            if (salesChart) {
                salesChart.resize();
            }
        });
    </script>
</body>
</html> 
