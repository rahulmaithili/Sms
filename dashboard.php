<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check session timeout
if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_id = $_SESSION['user_id'];
$sp_id = $_SESSION['salesperson_id'] ?? null;
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'dashboard';

// Handle AJAX requests for dashboard stats
if (isset($_GET['action']) && $_GET['action'] === 'getDashboardStats') {
    header('Content-Type: application/json');

    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }

    $conn = getDBConnection();

    // Total users
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $result->fetch_assoc()['total'];

    // Admin users
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
    $totalAdmins = $result->fetch_assoc()['total'];

    // Regular users
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $totalRegularUsers = $result->fetch_assoc()['total'];


    echo json_encode([
        'success' => true,
        'data' => [
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'totalRegularUsers' => $totalRegularUsers
        ]
    ]);
    exit();
}

// AJAX: Get recent activities
if (isset($_GET['action']) && $_GET['action'] === 'getRecentActivities') {
    header('Content-Type: application/json');

    try {
        if ($role === 'admin') {
            $logs = getActivityLogs(null, 'admin', 10);
        } else {
            $logs = getActivityLogs($user_id, 'user', 10);
        }

        echo json_encode(['success' => true, 'data' => $logs]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading activities']);
    }
    exit();
}

// AJAX: Get subscription stats
if (isset($_GET['action']) && $_GET['action'] === 'getSubscriptionStats') {
    header('Content-Type: application/json');

    try {
        $conn = getDBConnection();

        $base_sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date = CURDATE() THEN 1 ELSE 0 END) AS expiring_today,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date > CURDATE() AND DATEDIFF(expiry_date, CURDATE()) <= 30 THEN 1 ELSE 0 END) AS expiring_soon,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date > CURDATE() AND DATEDIFF(expiry_date, CURDATE()) > 30 THEN 1 ELSE 0 END) AS active,
                SUM(total_amount) AS total_revenue,
                SUM(CASE WHEN payment_status = 'Unpaid' THEN total_amount ELSE 0 END) AS unpaid_amount
                FROM subscriptions";

        if ($role === 'admin') {
            $result = $conn->query($base_sql);
        } elseif ($role === 'salesperson' && $sp_id) {
            $stmt = $conn->prepare($base_sql . " WHERE salesperson_id = ?");
            $stmt->bind_param("i", $sp_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $stmt = $conn->prepare($base_sql . " WHERE added_by = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        $stats = $result->fetch_assoc();
        if (isset($stmt)) $stmt->close();

        echo json_encode([
            'success' => true,
            'data' => [
                'total'          => (int)($stats['total'] ?? 0),
                'active'         => (int)($stats['active'] ?? 0),
                'expiring_soon'  => (int)($stats['expiring_soon'] ?? 0),
                'expiring_today' => (int)($stats['expiring_today'] ?? 0),
                'expired'        => (int)($stats['expired'] ?? 0),
                'total_revenue'  => round((float)($stats['total_revenue'] ?? 0), 3),
                'unpaid_amount'  => round((float)($stats['unpaid_amount'] ?? 0), 3),
            ],
            'currency' => getCurrency()
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading subscription stats']);
    }
    exit();
}

// AJAX: Get chart data for dashboard
if (isset($_GET['action']) && $_GET['action'] === 'getChartData') {
    header('Content-Type: application/json');

    try {
        $conn = getDBConnection();
        if ($role === 'admin') {
            $where = ''; $whereSimple = '';
        } elseif ($role === 'salesperson' && $sp_id) {
            $where = 'WHERE s.salesperson_id = ' . intval($sp_id);
            $whereSimple = 'WHERE salesperson_id = ' . intval($sp_id);
        } else {
            $where = 'WHERE s.added_by = ' . intval($user_id);
            $whereSimple = 'WHERE added_by = ' . intval($user_id);
        }

        // a) Status distribution
        $status_result = $conn->query("SELECT
            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date > CURDATE() AND DATEDIFF(expiry_date,CURDATE())>30 THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date > CURDATE() AND DATEDIFF(expiry_date,CURDATE())<=30 THEN 1 ELSE 0 END) AS expiring_soon,
            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date = CURDATE() THEN 1 ELSE 0 END) AS expiring_today,
            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired
            FROM subscriptions $whereSimple");
        $statusData = $status_result->fetch_assoc();

        // b) Monthly revenue (last 12 months)
        $monthly_result = $conn->query("SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month,
            SUM(total_amount) AS revenue, SUM((selling_price - tax_amount) - purchase_price) AS profit
            FROM subscriptions $whereSimple
            " . ($whereSimple ? "AND" : "WHERE") . " invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY month ASC");
        $monthlyData = [];
        while ($row = $monthly_result->fetch_assoc()) {
            $monthlyData[] = ['month' => $row['month'], 'revenue' => (float)$row['revenue'], 'profit' => (float)$row['profit']];
        }

        // c) Product distribution
        $cat_result = $conn->query("SELECT COALESCE(p.product_name,'Uncategorized') AS name, COALESCE(p.color_code,'#999') AS color, COUNT(s.sl) AS count
            FROM subscriptions s LEFT JOIN products p ON s.product_id = p.product_id $where
            GROUP BY s.product_id ORDER BY count DESC");
        $productData = [];
        while ($row = $cat_result->fetch_assoc()) {
            $productData[] = $row;
        }

        // d) Payment status
        $pay_result = $conn->query("SELECT payment_status, COUNT(*) AS count FROM subscriptions $whereSimple GROUP BY payment_status");
        $paymentData = [];
        while ($row = $pay_result->fetch_assoc()) {
            $paymentData[] = $row;
        }

        // e) Top 5 customers by revenue
        $cust_result = $conn->query("SELECT customer_name, SUM(total_amount) AS revenue, COUNT(*) AS count
            FROM subscriptions $whereSimple GROUP BY customer_name ORDER BY revenue DESC LIMIT 5");
        $customerData = [];
        while ($row = $cust_result->fetch_assoc()) {
            $customerData[] = $row;
        }

        // f) Salesperson leaderboard
        $spData = [];
        if ($role === 'admin') {
            $sp_result = $conn->query("SELECT sp.name, COUNT(s.sl) AS deals, SUM(s.total_amount) AS revenue
                FROM subscriptions s JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id
                GROUP BY s.salesperson_id ORDER BY revenue DESC LIMIT 5");
            while ($row = $sp_result->fetch_assoc()) {
                $spData[] = $row;
            }
        } elseif ($role === 'salesperson' && $sp_id) {
            $sp_stmt = $conn->prepare("SELECT sp.name, sp.commission_rate, COUNT(s.sl) AS deals, SUM(s.total_amount) AS revenue,
                SUM(CASE WHEN (s.selling_price - s.tax_amount) - s.purchase_price > 0 THEN ((s.selling_price - s.tax_amount) - s.purchase_price) * sp.commission_rate / 100 ELSE 0 END) AS commission
                FROM subscriptions s JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id
                WHERE s.salesperson_id = ? GROUP BY s.salesperson_id");
            $sp_stmt->bind_param("i", $sp_id);
            $sp_stmt->execute();
            $sp_result = $sp_stmt->get_result();
            while ($row = $sp_result->fetch_assoc()) { $spData[] = $row; }
            $sp_stmt->close();
        }

        echo json_encode([
            'success' => true,
            'status' => $statusData,
            'monthly' => $monthlyData,
            'products' => $productData,
            'payment' => $paymentData,
            'customers' => $customerData,
            'salespersons' => $spData,
            'currency' => getCurrency()
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading chart data']);
    }
    exit();
}

// AJAX: Get critical alerts
if (isset($_GET['action']) && $_GET['action'] === 'getAlerts') {
    header('Content-Type: application/json');

    try {
        $conn = getDBConnection();
        if ($role === 'admin') { $w = ''; }
        elseif ($role === 'salesperson' && $sp_id) { $w = 'AND salesperson_id = ' . intval($sp_id); }
        else { $w = 'AND added_by = ' . intval($user_id); }

        $res = $conn->query("SELECT
            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date = CURDATE() THEN 1 ELSE 0 END) AS expired_today,
            SUM(CASE WHEN payment_status = 'Unpaid' AND invoice_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS unpaid_30,
            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_total
            FROM subscriptions WHERE 1=1 $w");
        $data = $res->fetch_assoc();

        echo json_encode([
            'success' => true,
            'expired_today' => (int)($data['expired_today'] ?? 0),
            'unpaid_30' => (int)($data['unpaid_30'] ?? 0),
            'expired_total' => (int)($data['expired_total'] ?? 0)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading alerts']);
    }
    exit();
}

// AJAX: Get upcoming subscriptions (expiring in next 30 days)
if (isset($_GET['action']) && $_GET['action'] === 'getUpcomingSubscriptions') {
    header('Content-Type: application/json');

    try {
        $conn = getDBConnection();

        $upcoming_sql = "SELECT s.sl, s.customer_name, s.invoice_no, s.expiry_date, s.total_amount,
                s.payment_status, s.priority, p.product_name,
                DATEDIFF(s.expiry_date, CURDATE()) AS days_left
                FROM subscriptions s
                LEFT JOIN products p ON s.product_id = p.product_id
                WHERE s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";

        if ($role === 'admin') {
            $stmt = $conn->prepare($upcoming_sql . " ORDER BY s.expiry_date ASC LIMIT 10");
            $stmt->execute();
        } elseif ($role === 'salesperson' && $sp_id) {
            $stmt = $conn->prepare($upcoming_sql . " AND s.salesperson_id = ? ORDER BY s.expiry_date ASC LIMIT 10");
            $stmt->bind_param("i", $sp_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare($upcoming_sql . " AND s.added_by = ? ORDER BY s.expiry_date ASC LIMIT 10");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }

        $result = $stmt->get_result();
        $subs = [];
        while ($row = $result->fetch_assoc()) {
            $subs[] = [
                'sl' => (int)$row['sl'],
                'customer_name' => $row['customer_name'],
                'invoice_no' => $row['invoice_no'],
                'expiry_date' => $row['expiry_date'],
                'total_amount' => (float)$row['total_amount'],
                'payment_status' => $row['payment_status'],
                'priority' => $row['priority'],
                'product_name' => $row['product_name'] ?? '-',
                'days_left' => (int)$row['days_left']
            ];
        }
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $subs, 'currency' => getCurrency()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading upcoming subscriptions']);
    }
    exit();
}

// AJAX: Feedback/NPS stats (admin only)
if (isset($_GET['action']) && $_GET['action'] === 'getFeedbackStats') {
    header('Content-Type: application/json');
    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    try {
        $conn = getDBConnection();
        $r = $conn->query("SELECT COUNT(*) AS total, AVG(rating) AS avg_rating,
            SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) AS promoters,
            SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) AS detractors
            FROM customer_feedback");
        $stats = $r->fetch_assoc();
        $nps = 0;
        if ($stats['total'] > 0) {
            $nps = round(($stats['promoters'] / $stats['total'] * 100) - ($stats['detractors'] / $stats['total'] * 100));
        }
        echo json_encode(['success' => true, 'total' => (int)$stats['total'], 'avg' => round((float)$stats['avg_rating'], 1), 'nps' => $nps]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading feedback stats']);
    }
    exit();
}

// If we reach here, render the HTML page
?>
<!--
  Developed by Rameez Scripts
  WhatsApp: https://wa.me/923224083545 (For Custom Projects)
  YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Dashboard - Management System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <style>
        /* Gradient Stat Cards */
        .dash-stats { display:grid; grid-template-columns:repeat(6,1fr); gap:16px; margin-bottom:24px; }
        @media(max-width:1200px) { .dash-stats { grid-template-columns:repeat(3,1fr); } }
        @media(max-width:768px) { .dash-stats { grid-template-columns:repeat(2,1fr); } }
        @media(max-width:480px) { .dash-stats { grid-template-columns:1fr; } }

        .dash-card { padding:22px 20px; border-radius:12px; color:#fff; position:relative; overflow:hidden; min-height:100px; box-shadow:0 4px 15px rgba(0,0,0,0.15); transition:transform .2s,box-shadow .2s; }
        .dash-card:hover { transform:translateY(-3px); box-shadow:0 8px 25px rgba(0,0,0,0.2); }
        .dash-card-icon { position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:40px; opacity:0.15; }
        .dash-card-value { font-size:26px; font-weight:800; margin-bottom:4px; line-height:1.1; }
        .dash-card-label { font-size:11px; opacity:0.85; text-transform:uppercase; letter-spacing:0.8px; font-weight:600; }

        .dash-card-navy   { background:linear-gradient(135deg,#001f3f 0%,#003366 100%); }
        .dash-card-blue   { background:linear-gradient(135deg,#003366 0%,#0074D9 100%); }
        .dash-card-green  { background:linear-gradient(135deg,#155724 0%,#28a745 100%); }
        .dash-card-orange { background:linear-gradient(135deg,#854d0e 0%,#f59e0b 100%); }
        .dash-card-red    { background:linear-gradient(135deg,#7f1d1d 0%,#dc3545 100%); }
        .dash-card-purple { background:linear-gradient(135deg,#3b0764 0%,#7c3aed 100%); }

        /* Chart grid — 4 per row compact */
        .dash-charts-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
        @media(max-width:1200px) { .dash-charts-4 { grid-template-columns:repeat(2,1fr); } }
        @media(max-width:600px) { .dash-charts-4 { grid-template-columns:1fr; } }

        .dash-chart-card { background:#fff; border-radius:8px; padding:10px 14px; box-shadow:0 2px 8px rgba(0,31,63,.08); }
        .dash-chart-title { font-size:12px; font-weight:700; color:#001f3f; margin-bottom:6px; display:flex; align-items:center; gap:6px; }
        .dash-chart-title i { color:#0074D9; font-size:11px; }

        /* Skeleton shimmer */
        .dash-skeleton { background:linear-gradient(90deg,#e8edf2 25%,#f4f6f8 50%,#e8edf2 75%); background-size:200% 100%; animation:dashShimmer 1.3s infinite; border-radius:12px; }

        /* Alerts banner */
        .dash-alerts { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .dash-alert { flex:1 1 200px; padding:12px 16px; border-radius:8px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:10px; cursor:pointer; transition:opacity .2s; text-decoration:none; }
        .dash-alert:hover { opacity:0.85; }
        .dash-alert i { font-size:18px; }
        .dash-alert-red { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
        .dash-alert-orange { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }

        /* Clickable stat cards */
        .dash-card { cursor:pointer; }
        .dash-card-link { text-decoration:none; color:inherit; }

        /* 2x2 tables grid */
        .dash-tables-2x2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        @media(max-width:900px) { .dash-tables-2x2 { grid-template-columns:1fr; } }
        .dash-tables-2x2 .settings-mega-card { margin-bottom:0; }
        @keyframes dashShimmer { 0%{background-position:200% 0;} 100%{background-position:-200% 0;} }
    </style>
</head>
<body>
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span>Overview</span>
            </div>
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <!-- Alerts Banner -->
            <div class="dash-alerts" id="alertsBanner" style="display:none;"></div>

            <!-- Row 1: Gradient Stat Cards -->
            <div class="dash-stats" id="statsRow">
                <a href="subscriptions.php" class="dash-card-link">
                    <div class="dash-card dash-card-navy">
                        <div class="dash-card-icon"><i class="fas fa-file-contract"></i></div>
                        <div class="dash-card-value" id="statTotal">-</div>
                        <div class="dash-card-label">Total Subscriptions</div>
                    </div>
                </a>
                <a href="subscriptions.php" class="dash-card-link">
                    <div class="dash-card dash-card-blue">
                        <div class="dash-card-icon"><i class="fas fa-coins"></i></div>
                        <div class="dash-card-value" id="statRevenue">-</div>
                        <div class="dash-card-label">Revenue (<span id="statCurrency">-</span>)</div>
                    </div>
                </a>
                <a href="subscriptions.php?status=active" class="dash-card-link">
                    <div class="dash-card dash-card-green">
                        <div class="dash-card-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="dash-card-value" id="statActive">-</div>
                        <div class="dash-card-label">Active</div>
                    </div>
                </a>
                <a href="subscriptions.php?status=expiring" class="dash-card-link">
                    <div class="dash-card dash-card-orange">
                        <div class="dash-card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="dash-card-value" id="statExpiringSoon">-</div>
                        <div class="dash-card-label">Expiring Soon</div>
                    </div>
                </a>
                <a href="subscriptions.php?status=expired" class="dash-card-link">
                    <div class="dash-card dash-card-red">
                        <div class="dash-card-icon"><i class="fas fa-ban"></i></div>
                        <div class="dash-card-value" id="statExpired">-</div>
                        <div class="dash-card-label">Expired</div>
                    </div>
                </a>
                <a href="subscriptions.php?status=unpaid" class="dash-card-link">
                    <div class="dash-card dash-card-purple">
                        <div class="dash-card-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="dash-card-value" id="statUnpaid">-</div>
                        <div class="dash-card-label">Unpaid Amount</div>
                    </div>
                </a>
            </div>

            <!-- Charts: 4 in a row compact -->
            <div class="dash-charts-4">
                <div class="dash-chart-card">
                    <div class="dash-chart-title"><i class="fas fa-chart-area"></i> Revenue & Profit</div>
                    <canvas id="revenueChart" height="180"></canvas>
                </div>
                <div class="dash-chart-card">
                    <div class="dash-chart-title"><i class="fas fa-chart-line"></i> Monthly Trend</div>
                    <canvas id="statusChart" height="180"></canvas>
                </div>
                <div class="dash-chart-card">
                    <div class="dash-chart-title"><i class="fas fa-tags"></i> Products</div>
                    <canvas id="productChart" height="180"></canvas>
                </div>
                <div class="dash-chart-card">
                    <div class="dash-chart-title"><i class="fas fa-credit-card"></i> Payments</div>
                    <div style="max-height:200px;display:flex;justify-content:center;"><canvas id="paymentChart"></canvas></div>
                </div>
            </div>

            <!-- Top 5 Customers + Salesperson Leaderboard -->
            <div class="dash-tables-2x2" style="margin-bottom:20px;">
                <div class="dash-chart-card" id="customerChartCard">
                    <div class="dash-chart-title"><i class="fas fa-users"></i> Top 5 Customers by Revenue</div>
                    <canvas id="customerChart" height="120"></canvas>
                </div>
                <?php if ($role === 'admin'): ?>
                <div class="dash-chart-card">
                    <div class="dash-chart-title"><i class="fas fa-trophy"></i> Salesperson Leaderboard</div>
                    <div class="about-table-wrapper" style="margin:0;max-height:180px;overflow-y:auto;">
                        <table class="about-roles-table" id="spLeaderboard" style="font-size:11px;">
                            <thead><tr><th style="text-align:left;padding:5px 8px;">#</th><th style="text-align:left;padding:5px 8px;">Name</th><th style="text-align:right;padding:5px 8px;">Deals</th><th style="text-align:right;padding:5px 8px;">Revenue</th></tr></thead>
                            <tbody id="spLeaderboardBody"><tr><td colspan="4" style="text-align:center;color:#888;padding:10px;">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
                <?php elseif ($role === 'salesperson'): ?>
                <div class="dash-chart-card" id="myCommissionCard">
                    <div class="dash-chart-title"><i class="fas fa-coins"></i> My Commission</div>
                    <div id="myCommissionBody" style="padding:12px;text-align:center;color:#888;">Loading...</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Row 4: Monthly Breakdown + Upcoming Subscriptions (2x2) -->
            <div class="dash-tables-2x2">
                <div class="settings-mega-card">
                    <div class="settings-card-header">
                        <div class="settings-card-icon icon-gradient-navy"><i class="fas fa-table"></i></div>
                        <div>
                            <h3 class="settings-card-title">Monthly Breakdown</h3>
                            <p class="settings-card-subtitle">Revenue by month</p>
                        </div>
                    </div>
                    <div class="settings-card-body" style="max-height:350px;overflow-y:auto;">
                        <div class="about-table-wrapper" style="margin:0;">
                            <table class="about-roles-table" id="monthlyTable" style="font-size:13px;">
                                <thead><tr><th style="text-align:left;">Month</th><th style="text-align:right;">Revenue</th><th style="text-align:right;">Profit</th></tr></thead>
                                <tbody id="monthlyTableBody">
                                    <tr><td colspan="3" style="text-align:center;color:#888;padding:20px;">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="settings-mega-card">
                    <div class="settings-card-header">
                        <div class="settings-card-icon icon-gradient-navy"><i class="fas fa-calendar-times"></i></div>
                        <div>
                            <h3 class="settings-card-title">Upcoming Expirations</h3>
                            <p class="settings-card-subtitle">Subscriptions expiring within 30 days</p>
                        </div>
                    </div>
                    <div class="settings-card-body" style="max-height:350px;overflow-y:auto;">
                        <div class="about-table-wrapper" style="margin:0;">
                            <table class="about-roles-table" id="upcomingTable" style="font-size:13px;">
                                <thead><tr><th style="text-align:left;">Customer</th><th>Invoice</th><th>Days Left</th><th>Payment</th></tr></thead>
                                <tbody id="upcomingTableBody">
                                    <tr><td colspan="4" style="text-align:center;color:#888;padding:20px;">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Satisfaction (admin only) -->
            <?php if ($role === 'admin'): ?>
            <div class="dash-tables-2x2" style="margin-bottom:20px;" id="feedbackStatsRow">
                <div class="dash-chart-card" id="npsCard">
                    <div class="dash-chart-title"><i class="fas fa-star" style="color:#ffc107;"></i> Customer Satisfaction</div>
                    <div style="padding:16px;display:flex;gap:28px;align-items:center;flex-wrap:wrap;" id="npsContent">
                        <div style="text-align:center;">
                            <div style="font-size:28px;font-weight:800;color:#001f3f;" id="npsAvg">-</div>
                            <div id="npsStars" style="margin:4px 0;"></div>
                            <div style="font-size:11px;color:#888;">Avg Rating</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:28px;font-weight:800;color:#0074D9;" id="npsTotal">-</div>
                            <div style="font-size:11px;color:#888;">Total Reviews</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:28px;font-weight:800;" id="npsScore">-</div>
                            <div style="font-size:11px;color:#888;">NPS Score</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Row 5: Recent Activities -->
            <div class="settings-mega-card mt-24">
                <div class="settings-card-header">
                    <div class="settings-card-icon icon-gradient-navy">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <h3 class="settings-card-title">Recent Activities</h3>
                        <p class="settings-card-subtitle">Last 10 activity logs</p>
                    </div>
                </div>
                <div class="settings-card-body card-body-flush-scroll">
                    <div id="activitiesLoading" class="activities-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading activities...</p>
                    </div>
                    <div id="activitiesContent" class="initially-hidden">
                        <table class="setup-guide-table">
                            <thead>
                                <tr>
                                    <?php if ($role === 'admin'): ?>
                                    <th><i class="fas fa-user"></i> User</th>
                                    <?php endif; ?>
                                    <th><i class="fas fa-bolt"></i> Action</th>
                                    <th><i class="fas fa-info-circle"></i> Details</th>
                                    <th><i class="fas fa-globe"></i> IP Address</th>
                                    <th><i class="fas fa-clock"></i> Time</th>
                                </tr>
                            </thead>
                            <tbody id="activitiesTableBody">
                            </tbody>
                        </table>
                        <div id="noActivities" class="activities-empty initially-hidden">
                            <i class="fas fa-inbox"></i>
                            <p>No recent activities found</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <script>
        // ── IP Masking Utilities ──
        function maskIP(ip) {
            var parts = ip.split('.');
            if (parts.length === 4) return parts[0] + '.' + parts[1] + '.***. ***';
            return ip.replace(/:[\da-f]+:[\da-f]+$/i, ':***:***');
        }

        function renderIP(ip) {
            var masked = maskIP(ip);
            var escaped = $('<span>').text(ip).html();
            var escapedMasked = $('<span>').text(masked).html();
            return '<span class="ip-mask-wrap">' +
                '<code class="ip-display" data-full="' + escaped + '" data-masked="' + escapedMasked + '">' + escapedMasked + '</code>' +
                '<button class="ip-toggle-btn" onclick="toggleIP(this)" title="Show/Hide IP"><i class="fas fa-eye"></i></button>' +
                '</span>';
        }

        function toggleIP(btn) {
            var code = btn.previousElementSibling;
            var icon = btn.querySelector('i');
            if (code.textContent === code.getAttribute('data-masked')) {
                code.textContent = code.getAttribute('data-full');
                icon.className = 'fas fa-eye-slash';
            } else {
                code.textContent = code.getAttribute('data-masked');
                icon.className = 'fas fa-eye';
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ── Load Subscription Stats ──
        function loadSubscriptionStats() {
            $.ajax({
                url: '?action=getSubscriptionStats',
                method: 'GET', dataType: 'json',
                success: function(r) {
                    if (!r.success) return;
                    var d = r.data;
                    document.getElementById('statTotal').textContent = d.total;
                    document.getElementById('statActive').textContent = d.active;
                    document.getElementById('statExpiringSoon').textContent = d.expiring_soon;
                    document.getElementById('statExpired').textContent = d.expired;
                    document.getElementById('statRevenue').textContent = d.total_revenue;
                    document.getElementById('statUnpaid').textContent = d.unpaid_amount;
                    document.getElementById('statCurrency').textContent = r.currency;
                }
            });
        }

        // ── Load Charts + Monthly Table ──
        function loadChartData() {
            $.ajax({
                url: '?action=getChartData',
                method: 'GET', dataType: 'json',
                success: function(r) {
                    if (!r.success) return;
                    var currency = r.currency;

                    // Revenue Area Chart with gradient fill
                    var revCtx = document.getElementById('revenueChart').getContext('2d');
                    var revGradient = revCtx.createLinearGradient(0, 0, 0, 300);
                    revGradient.addColorStop(0, 'rgba(0,116,217,0.35)');
                    revGradient.addColorStop(1, 'rgba(0,116,217,0.02)');
                    var profitGradient = revCtx.createLinearGradient(0, 0, 0, 300);
                    profitGradient.addColorStop(0, 'rgba(40,167,69,0.25)');
                    profitGradient.addColorStop(1, 'rgba(40,167,69,0.02)');

                    new Chart(revCtx, {
                        type: 'line',
                        data: {
                            labels: r.monthly.map(function(m) { return formatMonth(m.month); }),
                            datasets: [
                                { label: 'Revenue (' + currency + ')', data: r.monthly.map(function(m){ return m.revenue; }),
                                  borderColor: '#0074D9', backgroundColor: revGradient, fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#0074D9', borderWidth: 2 },
                                { label: 'Profit (' + currency + ')', data: r.monthly.map(function(m){ return m.profit; }),
                                  borderColor: '#28a745', backgroundColor: profitGradient, fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#28a745', borderWidth: 2 }
                            ]
                        },
                        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
                    });

                    // Monthly Trend Line Chart (revenue + profit over time)
                    var trendCtx = document.getElementById('statusChart').getContext('2d');
                    var trendGrad = trendCtx.createLinearGradient(0, 0, 0, 200);
                    trendGrad.addColorStop(0, 'rgba(0,116,217,0.2)');
                    trendGrad.addColorStop(1, 'rgba(0,116,217,0)');
                    new Chart(trendCtx, {
                        type: 'line',
                        data: {
                            labels: r.monthly.map(function(m) { return formatMonth(m.month); }),
                            datasets: [
                                { label: 'Revenue', data: r.monthly.map(function(m){ return m.revenue; }),
                                  borderColor: '#0074D9', backgroundColor: trendGrad, fill: true, tension: 0.4, pointRadius: 3, borderWidth: 2 },
                                { label: 'Profit', data: r.monthly.map(function(m){ return m.profit; }),
                                  borderColor: '#28a745', backgroundColor: 'transparent', fill: false, tension: 0.4, pointRadius: 3, borderWidth: 2, borderDash: [5,3] }
                            ]
                        },
                        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } }, scales: { y: { beginAtZero: true, ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 9 } } } } }
                    });

                    // Product Bar Chart
                    new Chart(document.getElementById('productChart'), {
                        type: 'bar',
                        data: {
                            labels: r.products.map(function(c){ return c.name; }),
                            datasets: [{ label: 'Subscriptions', data: r.products.map(function(c){ return parseInt(c.count); }),
                                backgroundColor: r.products.map(function(c){ return c.color; }), borderRadius: 4, borderWidth: 0 }]
                        },
                        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { font: { size: 10 }, stepSize: 1 } }, x: { ticks: { font: { size: 9 } } } } }
                    });

                    // Payment Pie
                    var payColors = { 'Paid': '#28a745', 'Unpaid': '#dc3545', 'Partial': '#ffc107', 'Refunded': '#0074D9' };
                    new Chart(document.getElementById('paymentChart'), {
                        type: 'pie',
                        data: {
                            labels: r.payment.map(function(p){ return p.payment_status; }),
                            datasets: [{ data: r.payment.map(function(p){ return parseInt(p.count); }),
                                backgroundColor: r.payment.map(function(p){ return payColors[p.payment_status] || '#999'; }), borderWidth: 2 }]
                        },
                        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                    });

                    // Monthly Table
                    buildMonthlyTable(r.monthly, currency);

                    // Customer horizontal bar chart
                    if (r.customers && r.customers.length > 0) {
                        new Chart(document.getElementById('customerChart'), {
                            type: 'bar',
                            data: {
                                labels: r.customers.map(function(c){ return c.customer_name; }),
                                datasets: [{
                                    label: 'Revenue (' + currency + ')',
                                    data: r.customers.map(function(c){ return parseFloat(c.revenue); }),
                                    backgroundColor: ['#001f3f','#0074D9','#28a745','#f59e0b','#7c3aed'],
                                    borderRadius: 4, borderWidth: 0
                                }]
                            },
                            options: {
                                indexAxis: 'y', responsive: true,
                                plugins: { legend: { display: false } },
                                scales: { x: { beginAtZero: true, ticks: { font: { size: 10 } } }, y: { ticks: { font: { size: 10 } } } }
                            }
                        });
                    }

                    // Salesperson leaderboard
                    <?php if ($role === 'admin'): ?>
                    buildSPLeaderboard(r.salespersons || [], currency);
                    <?php elseif ($role === 'salesperson'): ?>
                    buildMyCommission(r.salespersons || [], currency);
                    <?php else: ?>
                    // full width for customer chart
                    var ccCard = document.getElementById('customerChartCard');
                    if (ccCard) ccCard.parentElement.style.gridTemplateColumns = '1fr';
                    <?php endif; ?>
                }
            });
        }

        // ── Build Salesperson Leaderboard ──
        <?php if ($role === 'admin'): ?>
        function buildSPLeaderboard(data, currency) {
            var tbody = document.getElementById('spLeaderboardBody');
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#888;padding:16px;">No data</td></tr>';
                return;
            }
            var medals = ['#FFD700','#C0C0C0','#CD7F32']; // gold, silver, bronze
            var html = '';
            data.forEach(function(sp, i) {
                var rank = i < 3 ? '<span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:' + medals[i] + ';color:#fff;font-size:9px;font-weight:700;">' + (i+1) + '</span>' : (i+1);
                html += '<tr>';
                html += '<td style="text-align:left;padding:4px 8px;">' + rank + '</td>';
                html += '<td style="text-align:left;font-weight:600;padding:4px 8px;">' + sp.name + '</td>';
                html += '<td style="text-align:right;padding:4px 8px;">' + parseInt(sp.deals) + '</td>';
                html += '<td style="text-align:right;padding:4px 8px;">' + currency + ' ' + parseFloat(sp.revenue).toFixed(0) + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        }
        <?php endif; ?>

        <?php if ($role === 'salesperson'): ?>
        function buildMyCommission(data, currency) {
            var el = document.getElementById('myCommissionBody');
            if (!data || data.length === 0) { el.innerHTML = '<p style="color:#888;">No deals yet</p>'; return; }
            var sp = data[0];
            el.innerHTML = '<div style="text-align:left;font-size:14px;line-height:2;">' +
                '<div><strong>Deals:</strong> ' + parseInt(sp.deals) + '</div>' +
                '<div><strong>Revenue:</strong> ' + currency + ' ' + parseFloat(sp.revenue).toFixed(3) + '</div>' +
                '<div><strong>Rate:</strong> ' + parseFloat(sp.commission_rate || 0).toFixed(2) + '%</div>' +
                '<div style="font-size:18px;font-weight:700;color:#28a745;margin-top:4px;"><strong>Commission:</strong> ' + currency + ' ' + parseFloat(sp.commission || 0).toFixed(3) + '</div>' +
                '</div>';
        }
        <?php endif; ?>

        function formatMonth(ym) {
            var parts = ym.split('-');
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return months[parseInt(parts[1])-1] + ' ' + parts[0];
        }

        function buildMonthlyTable(monthly, currency) {
            var tbody = document.getElementById('monthlyTableBody');
            if (!monthly || monthly.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#888;padding:16px;">No data available</td></tr>';
                return;
            }
            var html = '';
            var totalRev = 0, totalProfit = 0;
            monthly.forEach(function(m) {
                totalRev += parseFloat(m.revenue) || 0;
                totalProfit += parseFloat(m.profit) || 0;
                html += '<tr>';
                html += '<td style="text-align:left;font-weight:600;">' + formatMonth(m.month) + '</td>';
                html += '<td style="text-align:right;">' + currency + ' ' + parseFloat(m.revenue).toFixed(0) + '</td>';
                html += '<td style="text-align:right;">' + currency + ' ' + parseFloat(m.profit).toFixed(0) + '</td>';
                html += '</tr>';
            });
            html += '<tr style="background:#001f3f;color:#fff;font-weight:700;">';
            html += '<td style="text-align:left;background:#001f3f;color:#fff;">Total</td>';
            html += '<td style="text-align:right;background:#001f3f;color:#fff;">' + currency + ' ' + totalRev.toFixed(0) + '</td>';
            html += '<td style="text-align:right;background:#001f3f;color:#fff;">' + currency + ' ' + totalProfit.toFixed(0) + '</td>';
            html += '</tr>';
            tbody.innerHTML = html;
        }

        // ── Load Alerts Banner ──
        function loadAlerts() {
            $.ajax({
                url: '?action=getAlerts',
                method: 'GET', dataType: 'json',
                success: function(r) {
                    if (!r.success) return;
                    var html = '';
                    if (r.expired_today > 0)
                        html += '<a href="subscriptions.php?filter=expired" class="dash-alert dash-alert-red"><i class="fas fa-clock"></i> ' + r.expired_today + ' subscription(s) expiring today!</a>';
                    if (r.unpaid_30 > 0)
                        html += '<a href="subscriptions.php?filter=unpaid" class="dash-alert dash-alert-orange"><i class="fas fa-exclamation-circle"></i> ' + r.unpaid_30 + ' subscription(s) unpaid for 30+ days</a>';
                    if (r.expired_total > 0)
                        html += '<a href="subscriptions.php?filter=expired" class="dash-alert dash-alert-red"><i class="fas fa-ban"></i> ' + r.expired_total + ' total expired subscription(s)</a>';
                    if (html) {
                        var banner = document.getElementById('alertsBanner');
                        banner.innerHTML = html;
                        banner.style.display = 'flex';
                    }
                }
            });
        }

        // ── Load Upcoming Subscriptions ──
        function loadUpcomingSubscriptions() {
            $.ajax({
                url: '?action=getUpcomingSubscriptions',
                method: 'GET',
                dataType: 'json',
                success: function(r) {
                    var tbody = document.getElementById('upcomingTableBody');
                    if (!r.success || !r.data || r.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#888;padding:16px;">No upcoming expirations</td></tr>';
                        return;
                    }
                    var html = '';
                    var payColors = { 'Paid': '#28a745', 'Unpaid': '#dc3545', 'Partial': '#e67e00', 'Refunded': '#0074D9' };
                    r.data.forEach(function(s) {
                        var daysColor = s.days_left <= 3 ? '#dc3545' : (s.days_left <= 7 ? '#e65100' : (s.days_left <= 15 ? '#ffc107' : '#28a745'));
                        var pc = payColors[s.payment_status] || '#888';
                        html += '<tr>';
                        html += '<td style="text-align:left;font-weight:600;">' + s.customer_name + '</td>';
                        html += '<td>' + s.invoice_no + '</td>';
                        html += '<td><span class="role-badge" style="background:' + daysColor + ';color:#fff;">' + s.days_left + 'd</span></td>';
                        html += '<td><span class="role-badge" style="background:' + pc + ';color:#fff;">' + s.payment_status + '</span></td>';
                        html += '</tr>';
                    });
                    tbody.innerHTML = html;
                }
            });
        }

        // ── Load Recent Activities ──
        function loadRecentActivities() {
            $.ajax({
                url: '?action=getRecentActivities',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#activitiesLoading').hide();
                    $('#activitiesContent').show();

                    if (response.success && response.data.length > 0) {
                        const tbody = document.getElementById('activitiesTableBody');
                        tbody.innerHTML = '';

                        response.data.forEach(function(log) {
                            const time = new Date(log.timestamp);
                            const timeStr = time.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                                + ' ' + time.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

                            let badgeClass = 'action-badge-default';
                            const actionLower = log.action.toLowerCase();
                            if (actionLower.includes('login') || actionLower.includes('signup')) {
                                badgeClass = 'action-badge-success';
                            } else if (actionLower.includes('delete') || actionLower.includes('logout')) {
                                badgeClass = 'action-badge-danger';
                            } else if (actionLower.includes('update') || actionLower.includes('change')) {
                                badgeClass = 'action-badge-warning';
                            }

                            let row = '<tr>';
                            <?php if ($role === 'admin'): ?>
                            row += '<td><strong>' + escapeHtml(log.username) + '</strong></td>';
                            <?php endif; ?>
                            row += '<td><span class="action-badge ' + badgeClass + '">' + escapeHtml(log.action) + '</span></td>';
                            row += '<td class="activity-detail">' + escapeHtml(log.details || '-') + '</td>';
                            row += '<td class="activity-ip">' + renderIP(log.ip_address) + '</td>';
                            row += '<td class="activity-time">' + timeStr + '</td>';
                            row += '</tr>';

                            tbody.innerHTML += row;
                        });
                    } else {
                        $('#noActivities').show();
                    }
                },
                error: function() {
                    $('#activitiesLoading').hide();
                    $('#activitiesContent').show();
                    $('#noActivities').show();
                }
            });
        }

        // ── Admin Stats (if admin) ──
        <?php if ($role === 'admin'): ?>
        function loadDashboardStats() {
            $.ajax({
                url: '?action=getDashboardStats',
                method: 'GET', dataType: 'json',
                success: function(r) {
                    if (!r.success) return;
                    // Admin-specific stats available; subscription stats cover the main dashboard
                }
            });
        }
        loadDashboardStats();
        <?php endif; ?>

        // ── Feedback/NPS Stats (admin) ──
        <?php if ($role === 'admin'): ?>
        function loadFeedbackStats() {
            $.ajax({
                url: '?action=getFeedbackStats',
                method: 'GET', dataType: 'json',
                success: function(r) {
                    if (!r.success) return;
                    document.getElementById('npsAvg').textContent = r.avg + ' / 5';
                    document.getElementById('npsTotal').textContent = r.total;
                    // color NPS
                    var npsEl = document.getElementById('npsScore');
                    npsEl.textContent = r.nps;
                    npsEl.style.color = r.nps >= 50 ? '#28a745' : (r.nps >= 0 ? '#f59e0b' : '#dc3545');
                    // render stars
                    var starsHtml = '';
                    var full = Math.floor(r.avg);
                    var half = r.avg - full >= 0.5;
                    for (var i = 1; i <= 5; i++) {
                        if (i <= full) starsHtml += '<i class="fas fa-star" style="color:#ffc107;font-size:16px;"></i>';
                        else if (i === full + 1 && half) starsHtml += '<i class="fas fa-star-half-alt" style="color:#ffc107;font-size:16px;"></i>';
                        else starsHtml += '<i class="far fa-star" style="color:#ddd;font-size:16px;"></i>';
                    }
                    document.getElementById('npsStars').innerHTML = starsHtml;
                }
            });
        }
        <?php endif; ?>

        // ── Init ──
        loadAlerts();
        loadSubscriptionStats();
        loadChartData();
        loadUpcomingSubscriptions();
        loadRecentActivities();
        <?php if ($role === 'admin'): ?>
        loadFeedbackStats();
        <?php endif; ?>
    </script>
</body>
</html>
