<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */

require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!checkSessionTimeout()) { header("Location: login.php"); exit(); }

$username = $_SESSION['username'];
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_id = $_SESSION['user_id'];
$sp_id = $_SESSION['salesperson_id'] ?? null;
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'reports';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        $conn = getDBConnection();

        switch ($_GET['action']) {

            case 'getRevenueReport':
                $rev_sql = "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month,
                        COUNT(*) AS count,
                        SUM(total_amount) AS revenue,
                        SUM((selling_price - tax_amount) - purchase_price) AS profit,
                        SUM(CASE WHEN payment_status='Unpaid' THEN total_amount ELSE 0 END) AS unpaid
                        FROM subscriptions
                        WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
                if ($role === 'admin') {
                    $stmt = $conn->prepare($rev_sql . " GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY month ASC");
                    $stmt->execute();
                } elseif ($role === 'salesperson' && $sp_id) {
                    $stmt = $conn->prepare($rev_sql . " AND salesperson_id = ? GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY month ASC");
                    $stmt->bind_param("i", $sp_id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare($rev_sql . " AND added_by = ? GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY month ASC");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                }

                $result = $stmt->get_result();
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'month'   => $row['month'],
                        'count'   => (int)$row['count'],
                        'revenue' => round((float)($row['revenue'] ?? 0), 3),
                        'profit'  => round((float)($row['profit'] ?? 0), 3),
                        'unpaid'  => round((float)($row['unpaid'] ?? 0), 3)
                    ];
                }
                $stmt->close();

                echo json_encode(['success' => true, 'data' => $data, 'currency' => getCurrency()]);
                exit();

            case 'getProductReport':
                $prod_base = "SELECT COALESCE(p.product_name, 'Uncategorized') AS product_name,
                        COALESCE(p.color_code, '#999999') AS color_code,
                        COUNT(s.sl) AS count,
                        SUM(s.total_amount) AS revenue,
                        SUM((s.selling_price - s.tax_amount) - s.purchase_price) AS profit
                        FROM subscriptions s
                        LEFT JOIN products p ON s.product_id = p.product_id";
                if ($role === 'admin') {
                    $stmt = $conn->prepare($prod_base . " GROUP BY s.product_id ORDER BY revenue DESC");
                    $stmt->execute();
                } elseif ($role === 'salesperson' && $sp_id) {
                    $stmt = $conn->prepare($prod_base . " WHERE s.salesperson_id = ? GROUP BY s.product_id ORDER BY revenue DESC");
                    $stmt->bind_param("i", $sp_id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare($prod_base . " WHERE s.added_by = ? GROUP BY s.product_id ORDER BY revenue DESC");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                }

                $result = $stmt->get_result();
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'product_name' => $row['product_name'],
                        'color_code'    => $row['color_code'],
                        'count'         => (int)$row['count'],
                        'revenue'       => round((float)($row['revenue'] ?? 0), 3),
                        'profit'        => round((float)($row['profit'] ?? 0), 3)
                    ];
                }
                $stmt->close();

                echo json_encode(['success' => true, 'data' => $data, 'currency' => getCurrency()]);
                exit();

            case 'getSalespersonReport':
                if ($role !== 'admin' && $role !== 'salesperson') {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit();
                }

                $sp_sql = "SELECT sp.name, sp.commission_rate,
                    COUNT(s.sl) AS count,
                    SUM(s.total_amount) AS revenue,
                    SUM((s.selling_price - s.tax_amount) - s.purchase_price) AS profit,
                    SUM(CASE WHEN (s.selling_price - s.tax_amount) - s.purchase_price > 0 THEN ((s.selling_price - s.tax_amount) - s.purchase_price) * sp.commission_rate / 100 ELSE 0 END) AS commission_earned
                    FROM subscriptions s
                    JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id";
                if ($role === 'salesperson' && $sp_id) {
                    $stmt = $conn->prepare($sp_sql . " WHERE s.salesperson_id = ? GROUP BY s.salesperson_id ORDER BY revenue DESC");
                    $stmt->bind_param("i", $sp_id);
                } else {
                    $stmt = $conn->prepare($sp_sql . " GROUP BY s.salesperson_id ORDER BY revenue DESC");
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'name'              => $row['name'],
                        'commission_rate'    => round((float)($row['commission_rate'] ?? 0), 2),
                        'count'             => (int)$row['count'],
                        'revenue'           => round((float)($row['revenue'] ?? 0), 3),
                        'profit'            => round((float)($row['profit'] ?? 0), 3),
                        'commission_earned' => round((float)($row['commission_earned'] ?? 0), 3)
                    ];
                }
                $stmt->close();

                echo json_encode(['success' => true, 'data' => $data, 'currency' => getCurrency()]);
                exit();

            case 'getPaymentReport':
                if ($role === 'admin') {
                    $stmt = $conn->prepare("SELECT payment_status, COUNT(*) AS count, SUM(total_amount) AS amount
                        FROM subscriptions GROUP BY payment_status");
                    $stmt->execute();
                } elseif ($role === 'salesperson' && $sp_id) {
                    $stmt = $conn->prepare("SELECT payment_status, COUNT(*) AS count, SUM(total_amount) AS amount
                        FROM subscriptions WHERE salesperson_id = ? GROUP BY payment_status");
                    $stmt->bind_param("i", $sp_id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("SELECT payment_status, COUNT(*) AS count, SUM(total_amount) AS amount
                        FROM subscriptions WHERE added_by = ? GROUP BY payment_status");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                }

                $result = $stmt->get_result();
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'payment_status' => $row['payment_status'],
                        'count'          => (int)$row['count'],
                        'amount'         => round((float)($row['amount'] ?? 0), 3)
                    ];
                }
                $stmt->close();

                echo json_encode(['success' => true, 'data' => $data, 'currency' => getCurrency()]);
                exit();

            case 'getExpiryReport':
                $exp_sql = "SELECT s.sl, s.customer_name, s.invoice_no, s.expiry_date, s.total_amount,
                        s.payment_status, s.priority, p.product_name, sp.name AS salesperson_name,
                        DATEDIFF(s.expiry_date, CURDATE()) AS days_left
                        FROM subscriptions s
                        LEFT JOIN products p ON s.product_id = p.product_id
                        LEFT JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id
                        WHERE s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
                if ($role === 'admin') {
                    $stmt = $conn->prepare($exp_sql . " ORDER BY s.expiry_date ASC");
                    $stmt->execute();
                } elseif ($role === 'salesperson' && $sp_id) {
                    $stmt = $conn->prepare($exp_sql . " AND s.salesperson_id = ? ORDER BY s.expiry_date ASC");
                    $stmt->bind_param("i", $sp_id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare($exp_sql . " AND s.added_by = ? ORDER BY s.expiry_date ASC");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                }

                $result = $stmt->get_result();
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'sl'               => (int)$row['sl'],
                        'customer_name'    => $row['customer_name'],
                        'invoice_no'       => $row['invoice_no'],
                        'expiry_date'      => $row['expiry_date'],
                        'total_amount'     => round((float)($row['total_amount'] ?? 0), 3),
                        'payment_status'   => $row['payment_status'],
                        'priority'         => $row['priority'],
                        'product_name'     => $row['product_name'] ?? 'Uncategorized',
                        'salesperson_name' => $row['salesperson_name'] ?? '-',
                        'days_left'        => (int)$row['days_left']
                    ];
                }
                $stmt->close();

                echo json_encode(['success' => true, 'data' => $data, 'currency' => getCurrency()]);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("reports.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
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
    <title>Reports & Analytics - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <style>
        .dashboard-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        @media(max-width:900px) { .dashboard-grid-2 { grid-template-columns:1fr; } }
        .report-summary-card { background:#f8f9fa; border-radius:8px; padding:20px; text-align:center; margin-bottom:12px; }
        .report-summary-value { font-size:28px; font-weight:700; color:#001f3f; }
        .report-summary-label { font-size:13px; color:#7a8fa6; margin-top:4px; }
        .report-chart-wrap { background:#fff; border-radius:8px; padding:16px; border:1px solid #e2e8f0; min-height:300px; position:relative; }
        .report-chart-wrap canvas { max-height:320px; }
        .report-table-wrap { background:#fff; border-radius:8px; padding:16px; border:1px solid #e2e8f0; }
        .report-section-title { font-size:16px; font-weight:600; color:#001f3f; margin-bottom:14px; }
        .report-section-title i { margin-right:6px; color:#0074D9; }

        /* Status and payment badges */
        .status-badge, .pay-badge, .prio-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; white-space:nowrap; }
        .status-active { background:#d4edda; color:#155724; }
        .status-expiring-soon { background:#fff3cd; color:#856404; }
        .status-expired { background:#f8d7da; color:#721c24; }
        .pay-paid { background:#d4edda; color:#155724; }
        .pay-unpaid { background:#f8d7da; color:#721c24; }
        .pay-partial { background:#fff3cd; color:#856404; }
        .pay-refunded { background:#cce5ff; color:#004085; }
        .prio-critical { background:#f8d7da; color:#721c24; }
        .prio-high { background:#ffe0b2; color:#e65100; }
        .prio-medium { background:#cce5ff; color:#004085; }
        .prio-low { background:#e2e3e5; color:#383d41; }

        .days-left-danger { color:#dc3545; font-weight:700; }
        .days-left-warning { color:#e67e00; font-weight:600; }
        .days-left-ok { color:#28a745; font-weight:600; }

        /* Skeleton loaders */
        .skeleton-chart { height:300px; background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:8px; }
        .skeleton-table { height:200px; background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:8px; }
        @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

        /* Totals row */
        .totals-row td { font-weight:700 !important; background:#f0f4f8 !important; border-top:2px solid #001f3f !important; }

        /* Dark mode overrides */
        body.dark-mode .report-summary-card { background:#1a2332; }
        body.dark-mode .report-summary-value { color:#e2e8f0; }
        body.dark-mode .report-summary-label { color:#8a9bb5; }
        body.dark-mode .report-chart-wrap { background:#1e293b; border-color:#334155; }
        body.dark-mode .report-table-wrap { background:#1e293b; border-color:#334155; }
        body.dark-mode .report-section-title { color:#e2e8f0; }
        body.dark-mode .totals-row td { background:#1a2332 !important; border-top-color:#0074D9 !important; }

        /* Report Tabs */
        .report-tab {
            padding: 12px 24px;
            border: none;
            background: transparent;
            color: #666;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .report-tab:hover { color: #001f3f; background: rgba(0,116,217,0.05); }
        .report-tab.active { color: #0074D9; border-bottom-color: #0074D9; }
        .report-tab i { font-size: 13px; }
        @media (max-width: 768px) {
            .report-tab { padding: 10px 16px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span>Reports</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <!-- Report Tabs -->
            <div class="report-tabs" style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid #e0e6ef;overflow-x:auto;">
                <button class="report-tab active" data-tab="revenue" onclick="switchTab('revenue')">
                    <i class="fas fa-chart-area"></i> Revenue
                </button>
                <button class="report-tab" data-tab="product" onclick="switchTab('product')">
                    <i class="fas fa-tags"></i> Products
                </button>
                <button class="report-tab" data-tab="payment" onclick="switchTab('payment')">
                    <i class="fas fa-credit-card"></i> Payments
                </button>
                <?php if ($role === 'admin'): ?>
                <button class="report-tab" data-tab="salesperson" onclick="switchTab('salesperson')">
                    <i class="fas fa-user-tie"></i> Sales Team
                </button>
                <?php elseif ($role === 'salesperson'): ?>
                <button class="report-tab" data-tab="salesperson" onclick="switchTab('salesperson')">
                    <i class="fas fa-user-tie"></i> My Commission
                </button>
                <?php endif; ?>
                <button class="report-tab" data-tab="expiry" onclick="switchTab('expiry')">
                    <i class="fas fa-calendar-times"></i> Expirations
                </button>
            </div>

            <div style="text-align:right;margin-bottom:16px;">
                <button class="btn btn-primary" onclick="loadAllReports()">
                    <i class="fas fa-sync"></i> Refresh All
                </button>
            </div>

            <!-- Section 1: Revenue Overview -->
            <div id="tab-revenue" class="report-tab-panel active">
            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-area"></i> Revenue Overview (Last 12 Months)</h2>
                </div>
                <div id="revenueSkeletonChart" class="skeleton-chart"></div>
                <div id="revenueChartWrap" style="display:none; position:relative; height:320px; margin-bottom:16px;">
                    <canvas id="revenueChart"></canvas>
                </div>
                <div id="revenueSkeletonTable" class="skeleton-table" style="margin-top:12px;"></div>
                <div id="revenueTableWrap" style="display:none;">
                    <div class="table-scroll-hint">
                        <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                    </div>
                    <div class="table-responsive">
                        <table id="revenueTable" class="display table-full-width"></table>
                    </div>
                </div>
            </div>
            </div><!-- /tab-revenue -->

            <!-- Section 2: Product Breakdown -->
            <div id="tab-product" class="report-tab-panel" style="display:none;">
            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-tags"></i> Product Breakdown</h2>
                </div>
                <div class="dashboard-grid-2">
                    <div class="report-chart-wrap">
                        <div class="report-section-title"><i class="fas fa-chart-pie"></i> Revenue by Product</div>
                        <div id="productSkeletonChart" class="skeleton-chart"></div>
                        <div id="productChartWrap" style="display:none; position:relative; height:300px;">
                            <canvas id="productChart"></canvas>
                        </div>
                    </div>
                    <div class="report-table-wrap">
                        <div class="report-section-title"><i class="fas fa-table"></i> Product Details</div>
                        <div id="productSkeletonTable" class="skeleton-table"></div>
                        <div id="productTableWrap" style="display:none;">
                            <div class="table-responsive">
                                <table id="productTable" class="display table-full-width"></table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div><!-- /tab-product -->

            <!-- Section 3: Payment Analysis -->
            <div id="tab-payment" class="report-tab-panel" style="display:none;">
            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-credit-card"></i> Payment Analysis</h2>
                </div>
                <div class="dashboard-grid-2">
                    <div class="report-chart-wrap">
                        <div class="report-section-title"><i class="fas fa-chart-pie"></i> Payment Status</div>
                        <div id="paymentSkeletonChart" class="skeleton-chart"></div>
                        <div id="paymentChartWrap" style="display:none; position:relative; height:300px;">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                    <div class="report-table-wrap">
                        <div class="report-section-title"><i class="fas fa-coins"></i> Payment Summary</div>
                        <div id="paymentSkeletonCards" class="skeleton-table"></div>
                        <div id="paymentCardsWrap" style="display:none;">
                            <div class="report-summary-card">
                                <div class="report-summary-value" id="paidAmount" style="color:#28a745;">-</div>
                                <div class="report-summary-label">Total Paid</div>
                            </div>
                            <div class="report-summary-card">
                                <div class="report-summary-value" id="unpaidAmount" style="color:#dc3545;">-</div>
                                <div class="report-summary-label">Total Unpaid</div>
                            </div>
                            <div class="report-summary-card">
                                <div class="report-summary-value" id="partialAmount" style="color:#e67e00;">-</div>
                                <div class="report-summary-label">Total Partial</div>
                            </div>
                            <div class="report-summary-card">
                                <div class="report-summary-value" id="collectionRate" style="color:#0074D9;">-</div>
                                <div class="report-summary-label">Collection Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div><!-- /tab-payment -->

            <!-- Section 4: Salesperson Performance -->
            <?php if ($role === 'admin' || $role === 'salesperson'): ?>
            <div id="tab-salesperson" class="report-tab-panel" style="display:none;">
            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-user-tie"></i> <?php echo $role === 'salesperson' ? 'My Commission' : 'Salesperson Performance'; ?></h2>
                </div>
                <div id="spSkeletonChart" class="skeleton-chart" style="margin-bottom:16px;"></div>
                <div id="spChartWrap" style="display:none; position:relative; height:300px; margin-bottom:16px;">
                    <canvas id="spChart"></canvas>
                </div>
                <div id="spSkeletonTable" class="skeleton-table"></div>
                <div id="spTableWrap" style="display:none;">
                    <div class="table-scroll-hint">
                        <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                    </div>
                    <div class="table-responsive">
                        <table id="spTable" class="display table-full-width"></table>
                    </div>
                </div>
            </div>
            </div><!-- /tab-salesperson -->
            <?php endif; ?>

            <!-- Section 5: Upcoming Expirations -->
            <div id="tab-expiry" class="report-tab-panel" style="display:none;">
            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-times"></i> Upcoming Expirations (Next 90 Days)</h2>
                </div>
                <div id="expirySkeletonTable" class="skeleton-table"></div>
                <div id="expiryTableWrap" style="display:none;">
                    <div class="table-scroll-hint">
                        <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                    </div>
                    <div class="table-responsive">
                        <table id="expiryTable" class="display table-full-width"></table>
                    </div>
                </div>
            </div>
            </div><!-- /tab-expiry -->

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
    // Lazy-load export dependencies (PDF/Excel) on first use
    function loadExportDeps(callback) {
        if (window.pdfMake) { callback(); return; }
        var urls = [
            'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js'
        ];
        var loaded = 0;
        function loadNext() {
            if (loaded >= urls.length) { callback(); return; }
            var s = document.createElement('script');
            s.src = urls[loaded];
            s.onload = function() { loaded++; loadNext(); };
            document.head.appendChild(s);
        }
        loadNext();
    }
    </script>

    <script>
        var isAdmin = <?php echo $role === 'admin' ? 'true' : 'false'; ?>;
        var isSalesperson = <?php echo $role === 'salesperson' ? 'true' : 'false'; ?>;
        var revenueChartInstance = null;
        var productChartInstance = null;
        var paymentChartInstance = null;
        var spChartInstance = null;
        var revenueTableInstance = null;
        var productTableInstance = null;
        var spTableInstance = null;
        var expiryTableInstance = null;

        function switchTab(tabName) {
            // Hide all panels
            document.querySelectorAll('.report-tab-panel').forEach(function(panel) {
                panel.style.display = 'none';
            });
            // Deactivate all tabs
            document.querySelectorAll('.report-tab').forEach(function(tab) {
                tab.classList.remove('active');
            });
            // Show selected panel
            var panel = document.getElementById('tab-' + tabName);
            if (panel) panel.style.display = 'block';
            // Activate selected tab
            var tab = document.querySelector('.report-tab[data-tab="' + tabName + '"]');
            if (tab) tab.classList.add('active');

            // Trigger chart resize after tab switch (Chart.js needs this)
            setTimeout(function() {
                if (window.revenueChartInstance) window.revenueChartInstance.resize();
                if (window.productChartInstance) window.productChartInstance.resize();
                if (window.paymentChartInstance) window.paymentChartInstance.resize();
                if (window.spChartInstance) window.spChartInstance.resize();
            }, 100);
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatNumber(num) {
            if (num === null || num === undefined) return '0.000';
            return parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
        }

        function formatMonthLabel(ym) {
            var parts = ym.split('-');
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return months[parseInt(parts[1]) - 1] + ' ' + parts[0];
        }

        $(document).ready(function() {
            loadAllReports();
        });

        function loadAllReports() {
            loadRevenueReport();
            loadProductReport();
            loadPaymentReport();
            if (isAdmin || isSalesperson) { loadSalespersonReport(); }
            loadExpiryReport();
        }

        // ========================================================================
        // 1. Revenue Report
        // ========================================================================
        function loadRevenueReport() {
            $('#revenueSkeletonChart').show();
            $('#revenueSkeletonTable').show();
            $('#revenueChartWrap').hide();
            $('#revenueTableWrap').hide();

            $.ajax({
                url: '?action=getRevenueReport',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#revenueSkeletonChart').hide();
                    $('#revenueSkeletonTable').hide();

                    if (response.success) {
                        buildRevenueChart(response.data, response.currency);
                        buildRevenueTable(response.data, response.currency);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load revenue report' });
                    }
                },
                error: function(xhr, status, error) {
                    $('#revenueSkeletonChart').hide();
                    $('#revenueSkeletonTable').hide();
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not load revenue report.' });
                }
            });
        }

        function buildRevenueChart(data, currency) {
            var labels = data.map(function(d) { return formatMonthLabel(d.month); });
            var revenues = data.map(function(d) { return d.revenue; });
            var profits = data.map(function(d) { return d.profit; });

            if (revenueChartInstance) { revenueChartInstance.destroy(); }

            var ctx = document.getElementById('revenueChart').getContext('2d');
            revenueChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue (' + currency + ')',
                            data: revenues,
                            backgroundColor: 'rgba(0, 116, 217, 0.7)',
                            borderColor: '#0074D9',
                            borderWidth: 1,
                            borderRadius: 4,
                            order: 2
                        },
                        {
                            label: 'Profit (' + currency + ')',
                            data: profits,
                            type: 'line',
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            borderWidth: 2,
                            pointBackgroundColor: '#28a745',
                            pointRadius: 4,
                            fill: true,
                            tension: 0.3,
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { position: 'top', labels: { color: '#333', usePointStyle: true } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) { return ctx.dataset.label + ': ' + formatNumber(ctx.raw); }
                            }
                        }
                    },
                    scales: {
                        x: { ticks: { color: '#666' }, grid: { display: false } },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#666',
                                callback: function(val) { return formatNumber(val); }
                            },
                            grid: { color: 'rgba(0,0,0,0.06)' }
                        }
                    }
                }
            });
            $('#revenueChartWrap').show();
        }

        function buildRevenueTable(data, currency) {
            if (revenueTableInstance) { revenueTableInstance.destroy(); $('#revenueTable').empty(); }

            // Calculate totals
            var totals = { count: 0, revenue: 0, profit: 0, unpaid: 0 };
            data.forEach(function(d) {
                totals.count += d.count;
                totals.revenue += d.revenue;
                totals.profit += d.profit;
                totals.unpaid += d.unpaid;
            });

            // Add totals row to data
            var tableData = data.slice();
            tableData.push({ month: 'TOTAL', count: totals.count, revenue: totals.revenue, profit: totals.profit, unpaid: totals.unpaid, _isTotal: true });

            setTimeout(function() {
                revenueTableInstance = $('#revenueTable').DataTable({
                    data: tableData,
                    destroy: true,
                    paging: false,
                    searching: false,
                    info: false,
                    responsive: true,
                    columns: [
                        {
                            data: 'month',
                            title: 'Month',
                            render: function(data, type, row) {
                                if (row._isTotal) return '<strong>TOTAL</strong>';
                                return formatMonthLabel(data);
                            }
                        },
                        {
                            data: 'count',
                            title: 'Subscriptions',
                            className: 'dt-center',
                            render: function(data, type, row) {
                                return row._isTotal ? '<strong>' + data + '</strong>' : data;
                            }
                        },
                        {
                            data: 'revenue',
                            title: 'Revenue (' + currency + ')',
                            className: 'dt-right',
                            render: function(data, type, row) {
                                var val = formatNumber(data);
                                return row._isTotal ? '<strong>' + val + '</strong>' : val;
                            }
                        },
                        {
                            data: 'profit',
                            title: 'Profit (' + currency + ')',
                            className: 'dt-right',
                            render: function(data, type, row) {
                                var val = formatNumber(data);
                                return row._isTotal ? '<strong>' + val + '</strong>' : val;
                            }
                        },
                        {
                            data: 'unpaid',
                            title: 'Unpaid (' + currency + ')',
                            className: 'dt-right',
                            render: function(data, type, row) {
                                var val = formatNumber(data);
                                var html = row._isTotal ? '<strong style="color:#dc3545;">' + val + '</strong>' : '<span style="color:#dc3545;">' + val + '</span>';
                                return html;
                            }
                        }
                    ],
                    createdRow: function(row, data) {
                        if (data._isTotal) {
                            $(row).addClass('totals-row');
                        }
                    },
                    order: []
                });

                $('#revenueTableWrap').show();
            }, 100);
        }

        // ========================================================================
        // 2. Product Report
        // ========================================================================
        function loadProductReport() {
            $('#productSkeletonChart').show();
            $('#productSkeletonTable').show();
            $('#productChartWrap').hide();
            $('#productTableWrap').hide();

            $.ajax({
                url: '?action=getProductReport',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#productSkeletonChart').hide();
                    $('#productSkeletonTable').hide();

                    if (response.success) {
                        buildProductChart(response.data, response.currency);
                        buildProductTable(response.data, response.currency);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load product report' });
                    }
                },
                error: function() {
                    $('#productSkeletonChart').hide();
                    $('#productSkeletonTable').hide();
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not load product report.' });
                }
            });
        }

        function buildProductChart(data, currency) {
            var labels = data.map(function(d) { return d.product_name; });
            var revenues = data.map(function(d) { return d.revenue; });
            var colors = data.map(function(d) { return d.color_code; });

            if (productChartInstance) { productChartInstance.destroy(); }

            var ctx = document.getElementById('productChart').getContext('2d');
            productChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: revenues,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#333', usePointStyle: true, padding: 12 } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                                    return ctx.label + ': ' + formatNumber(ctx.raw) + ' ' + currency + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
            $('#productChartWrap').show();
        }

        function buildProductTable(data, currency) {
            if (productTableInstance) { productTableInstance.destroy(); $('#productTable').empty(); }

            var totalRevenue = 0;
            data.forEach(function(d) { totalRevenue += d.revenue; });

            setTimeout(function() {
                productTableInstance = $('#productTable').DataTable({
                    data: data,
                    destroy: true,
                    paging: false,
                    searching: false,
                    info: false,
                    responsive: true,
                    columns: [
                        {
                            data: 'product_name',
                            title: 'Product',
                            render: function(data, type, row) {
                                return '<span style="display:inline-block;width:12px;height:12px;background:' + escapeHtml(row.color_code) + ';border-radius:3px;margin-right:6px;vertical-align:middle;"></span>' + escapeHtml(data);
                            }
                        },
                        { data: 'count', title: 'Count', className: 'dt-center' },
                        {
                            data: 'revenue',
                            title: 'Revenue (' + currency + ')',
                            className: 'dt-right',
                            render: function(data) { return formatNumber(data); }
                        },
                        {
                            data: 'profit',
                            title: 'Profit (' + currency + ')',
                            className: 'dt-right',
                            render: function(data) { return formatNumber(data); }
                        },
                        {
                            data: 'revenue',
                            title: '% of Total',
                            className: 'dt-center',
                            render: function(data) {
                                var pct = totalRevenue > 0 ? ((data / totalRevenue) * 100).toFixed(1) : '0.0';
                                return pct + '%';
                            }
                        }
                    ],
                    order: [[2, 'desc']]
                });

                $('#productTableWrap').show();
            }, 100);
        }

        // ========================================================================
        // 3. Payment Report
        // ========================================================================
        function loadPaymentReport() {
            $('#paymentSkeletonChart').show();
            $('#paymentSkeletonCards').show();
            $('#paymentChartWrap').hide();
            $('#paymentCardsWrap').hide();

            $.ajax({
                url: '?action=getPaymentReport',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#paymentSkeletonChart').hide();
                    $('#paymentSkeletonCards').hide();

                    if (response.success) {
                        buildPaymentChart(response.data, response.currency);
                        buildPaymentCards(response.data, response.currency);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load payment report' });
                    }
                },
                error: function() {
                    $('#paymentSkeletonChart').hide();
                    $('#paymentSkeletonCards').hide();
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not load payment report.' });
                }
            });
        }

        function buildPaymentChart(data, currency) {
            var colorMap = {
                'Paid': '#28a745',
                'Unpaid': '#dc3545',
                'Partial': '#ffc107',
                'Refunded': '#0074D9'
            };

            var labels = data.map(function(d) { return d.payment_status; });
            var amounts = data.map(function(d) { return d.amount; });
            var colors = data.map(function(d) { return colorMap[d.payment_status] || '#999999'; });

            if (paymentChartInstance) { paymentChartInstance.destroy(); }

            var ctx = document.getElementById('paymentChart').getContext('2d');
            paymentChartInstance = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: amounts,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#333', usePointStyle: true, padding: 12 } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.label + ': ' + formatNumber(ctx.raw) + ' ' + currency + ' (' + ctx.dataset.data.length + ')';
                                }
                            }
                        }
                    }
                }
            });
            $('#paymentChartWrap').show();
        }

        function buildPaymentCards(data, currency) {
            var paid = 0, unpaid = 0, partial = 0, total = 0;
            data.forEach(function(d) {
                total += d.amount;
                var status = d.payment_status ? d.payment_status.toLowerCase() : '';
                if (status === 'paid') paid += d.amount;
                else if (status === 'unpaid') unpaid += d.amount;
                else if (status === 'partial') partial += d.amount;
            });

            var collectionRate = total > 0 ? ((paid / total) * 100).toFixed(1) : '0.0';

            document.getElementById('paidAmount').textContent = formatNumber(paid) + ' ' + currency;
            document.getElementById('unpaidAmount').textContent = formatNumber(unpaid) + ' ' + currency;
            document.getElementById('partialAmount').textContent = formatNumber(partial) + ' ' + currency;
            document.getElementById('collectionRate').textContent = collectionRate + '%';

            $('#paymentCardsWrap').show();
        }

        // ========================================================================
        // 4. Salesperson Report (Admin Only)
        // ========================================================================
        <?php if ($role === 'admin'): ?>
        function loadSalespersonReport() {
            $('#spSkeletonChart').show();
            $('#spSkeletonTable').show();
            $('#spChartWrap').hide();
            $('#spTableWrap').hide();

            $.ajax({
                url: '?action=getSalespersonReport',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#spSkeletonChart').hide();
                    $('#spSkeletonTable').hide();

                    if (response.success) {
                        buildSPChart(response.data, response.currency);
                        buildSPTable(response.data, response.currency);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load salesperson report' });
                    }
                },
                error: function() {
                    $('#spSkeletonChart').hide();
                    $('#spSkeletonTable').hide();
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not load salesperson report.' });
                }
            });
        }

        function buildSPChart(data, currency) {
            // Top 5 by revenue
            var top5 = data.slice(0, 5);
            var labels = top5.map(function(d) { return d.name; });
            var revenues = top5.map(function(d) { return d.revenue; });
            var profits = top5.map(function(d) { return d.profit; });

            if (spChartInstance) { spChartInstance.destroy(); }

            var ctx = document.getElementById('spChart').getContext('2d');
            spChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue (' + currency + ')',
                            data: revenues,
                            backgroundColor: 'rgba(0, 116, 217, 0.7)',
                            borderColor: '#0074D9',
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Profit (' + currency + ')',
                            data: profits,
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: '#28a745',
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { color: '#333', usePointStyle: true } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) { return ctx.dataset.label + ': ' + formatNumber(ctx.raw); }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { color: '#666', callback: function(val) { return formatNumber(val); } },
                            grid: { color: 'rgba(0,0,0,0.06)' }
                        },
                        y: { ticks: { color: '#666' }, grid: { display: false } }
                    }
                }
            });
            $('#spChartWrap').show();
        }

        function buildSPTable(data, currency) {
            if (spTableInstance) { spTableInstance.destroy(); $('#spTable').empty(); }

            setTimeout(function() {
                spTableInstance = $('#spTable').DataTable({
                    data: data,
                    destroy: true,
                    pageLength: 10,
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: ':visible' }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: ':visible' }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: ':visible' }
                        }
                    ],
                    columns: [
                        { data: 'name', title: 'Name' },
                        { data: 'count', title: 'Subscriptions', className: 'dt-center' },
                        {
                            data: 'revenue',
                            title: 'Revenue (' + currency + ')',
                            className: 'dt-right',
                            render: function(data) { return formatNumber(data); }
                        },
                        {
                            data: 'profit',
                            title: 'Profit (' + currency + ')',
                            className: 'dt-right',
                            render: function(data) { return formatNumber(data); }
                        },
                        {
                            data: 'commission_rate',
                            title: 'Commission Rate',
                            className: 'dt-center',
                            render: function(data) { return data + '%'; }
                        },
                        {
                            data: 'commission_earned',
                            title: 'Commission Earned (' + currency + ')',
                            className: 'dt-right',
                            render: function(data) { return formatNumber(data); }
                        }
                    ],
                    order: [[2, 'desc']]
                });

                $('#spTableWrap').show();
            }, 100);
        }
        <?php endif; ?>

        // ========================================================================
        // 5. Expiry Report
        // ========================================================================
        function loadExpiryReport() {
            $('#expirySkeletonTable').show();
            $('#expiryTableWrap').hide();

            $.ajax({
                url: '?action=getExpiryReport',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#expirySkeletonTable').hide();

                    if (response.success) {
                        buildExpiryTable(response.data, response.currency);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load expiry report' });
                    }
                },
                error: function() {
                    $('#expirySkeletonTable').hide();
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not load expiry report.' });
                }
            });
        }

        function buildExpiryTable(data, currency) {
            if (expiryTableInstance) { expiryTableInstance.destroy(); $('#expiryTable').empty(); }

            setTimeout(function() {
                expiryTableInstance = $('#expiryTable').DataTable({
                    data: data,
                    destroy: true,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: ':visible' }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: ':visible' }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: ':visible' }
                        }
                    ],
                    columns: [
                        { data: 'sl', title: 'SL', className: 'dt-center' },
                        {
                            data: 'customer_name',
                            title: 'Customer',
                            render: function(data) { return escapeHtml(data); }
                        },
                        {
                            data: 'invoice_no',
                            title: 'Invoice',
                            render: function(data) { return escapeHtml(data); }
                        },
                        {
                            data: 'product_name',
                            title: 'Product',
                            render: function(data) { return escapeHtml(data); }
                        },
                        {
                            data: 'expiry_date',
                            title: 'Expiry Date',
                            render: function(data) {
                                if (!data) return '-';
                                var d = new Date(data);
                                return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        },
                        {
                            data: 'days_left',
                            title: 'Days Left',
                            className: 'dt-center',
                            render: function(data) {
                                var cls = 'days-left-ok';
                                if (data <= 7) cls = 'days-left-danger';
                                else if (data <= 30) cls = 'days-left-warning';
                                return '<span class="' + cls + '">' + data + ' days</span>';
                            }
                        },
                        {
                            data: 'total_amount',
                            title: 'Amount (' + currency + ')',
                            className: 'dt-right',
                            render: function(data) { return formatNumber(data); }
                        },
                        {
                            data: 'payment_status',
                            title: 'Payment',
                            className: 'dt-center',
                            render: function(data) {
                                var cls = 'pay-' + (data ? data.toLowerCase() : 'unpaid');
                                return '<span class="pay-badge ' + cls + '">' + escapeHtml(data) + '</span>';
                            }
                        },
                        {
                            data: 'priority',
                            title: 'Priority',
                            className: 'dt-center',
                            render: function(data) {
                                if (!data) return '-';
                                var cls = 'prio-' + data.toLowerCase();
                                return '<span class="prio-badge ' + cls + '">' + escapeHtml(data) + '</span>';
                            }
                        },
                        {
                            data: 'salesperson_name',
                            title: 'Salesperson',
                            render: function(data) { return escapeHtml(data); }
                        }
                    ],
                    order: [[5, 'asc']]
                });

                $('#expiryTableWrap').show();
            }, 100);
        }
    </script>
</body>
</html>
