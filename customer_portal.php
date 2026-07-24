<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */

require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!checkSessionTimeout()) { header("Location: login.php"); exit(); }

// customer role only
if ($_SESSION['role'] !== 'customer') { header("Location: dashboard.php"); exit(); }

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$customer_id = $_SESSION['customer_id'] ?? null;
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'customer_portal';

// fallback: fetch customer_id from DB if missing in session
if (!$customer_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT customer_id FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r && $r['customer_id']) {
        $customer_id = (int)$r['customer_id'];
        $_SESSION['customer_id'] = $customer_id;
    }
}

// AJAX handlers
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if (!$customer_id) {
        echo json_encode(['success' => false, 'message' => 'No customer linked to this account']);
        exit();
    }

    try {
        switch ($_GET['action']) {

            case 'getMySubscriptions':
                $conn = getDBConnection();
                $currency = getCurrency();

                 $stmt = $conn->prepare("SELECT s.sl, s.invoice_no, s.customer_name,
                        s.product_id, p.product_name, p.color_code, p.download_url AS product_download_url,
                        s.invoice_date, s.starting_date, s.expiry_date,
                        s.product_description, s.user_qty, s.license_duration,
                        s.selling_price, s.tax_amount, s.total_amount,
                        s.payment_status, s.payment_method, s.payment_date,
                        s.auto_renew, s.priority, s.contract_reference, s.remarks, s.product_key
                    FROM subscriptions s
                    LEFT JOIN products p ON s.product_id = p.product_id
                    WHERE s.customer_id = ?
                    ORDER BY s.sl DESC");
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $subs = [];
                while ($row = $result->fetch_assoc()) {
                    $status = getSubscriptionStatus($row['expiry_date']);
                    $days_left = null;
                    if (!empty($row['expiry_date'])) {
                        $now = new DateTime();
                        $expiry = new DateTime($row['expiry_date']);
                        $days_left = (int)$now->diff($expiry)->format('%r%a');
                    }

                    $subs[] = [
                        'sl'                  => (int)$row['sl'],
                        'invoice_no'          => $row['invoice_no'] ?? '',
                        'customer_name'       => $row['customer_name'],
                        'product_name'        => $row['product_name'] ?? '',
                        'color_code'          => $row['color_code'] ?? '#0078D4',
                        'invoice_date'        => $row['invoice_date'] ? date('M d, Y', strtotime($row['invoice_date'])) : '',
                        'starting_date'       => $row['starting_date'] ? date('M d, Y', strtotime($row['starting_date'])) : '',
                        'expiry_date'         => $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '',
                        'expiry_date_raw'     => $row['expiry_date'] ?? '',
                        'product_description' => $row['product_description'] ?? '',
                        'user_qty'            => (int)$row['user_qty'],
                        'license_duration'    => $row['license_duration'] ?? '',
                        'selling_price'       => (float)$row['selling_price'],
                        'tax_amount'          => (float)$row['tax_amount'],
                        'total_amount'        => (float)$row['total_amount'],
                        'payment_status'      => $row['payment_status'] ?? 'Unpaid',
                        'payment_method'      => $row['payment_method'] ?? '',
                        'payment_date'        => $row['payment_date'] ? date('M d, Y', strtotime($row['payment_date'])) : '',
                        'auto_renew'          => (bool)$row['auto_renew'],
                        'priority'            => $row['priority'] ?? 'Medium',
                        'contract_reference'  => $row['contract_reference'] ?? '',
                        'remarks'             => $row['remarks'] ?? '',
                        'days_left'           => $days_left,
                        'status_label'        => $status['label'],
                        'status_class'        => $status['class'],
                        'product_key'         => $row['product_key'] ?? '',
                        'product_download_url'=> $row['product_download_url'] ?? ''
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $subs, 'currency' => $currency]);
                exit();

            case 'getMyPayments':
                $conn = getDBConnection();
                $currency = getCurrency();

                $stmt = $conn->prepare("SELECT p.payment_id, p.subscription_sl, p.amount,
                        p.payment_method, p.payment_date, p.reference_no, p.notes,
                        s.invoice_no
                    FROM payments p
                    JOIN subscriptions s ON p.subscription_sl = s.sl
                    WHERE s.customer_id = ?
                    ORDER BY p.payment_date DESC, p.payment_id DESC");
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $payments = [];
                while ($row = $result->fetch_assoc()) {
                    $payments[] = [
                        'payment_id'     => (int)$row['payment_id'],
                        'subscription_sl'=> (int)$row['subscription_sl'],
                        'invoice_no'     => $row['invoice_no'] ?? '',
                        'amount'         => (float)$row['amount'],
                        'payment_method' => $row['payment_method'] ?? '',
                        'payment_date'   => $row['payment_date'] ? date('M d, Y', strtotime($row['payment_date'])) : '',
                        'reference_no'   => $row['reference_no'] ?? '',
                        'notes'          => $row['notes'] ?? ''
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $payments, 'currency' => $currency]);
                exit();

            case 'getMyStats':
                $conn = getDBConnection();
                $currency = getCurrency();

                // total, active, expiring soon, total spent, paid
                $stmt = $conn->prepare("SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN expiry_date >= CURDATE() AND DATEDIFF(expiry_date, CURDATE()) > 30 THEN 1 ELSE 0 END) AS active,
                        SUM(CASE WHEN expiry_date >= CURDATE() AND DATEDIFF(expiry_date, CURDATE()) <= 30 THEN 1 ELSE 0 END) AS expiring_soon,
                        COALESCE(SUM(total_amount), 0) AS total_amount,
                        COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE 0 END), 0) AS paid_amount
                    FROM subscriptions
                    WHERE customer_id = ?");
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                echo json_encode([
                    'success'  => true,
                    'currency' => $currency,
                    'data'     => [
                        'total'         => (int)$row['total'],
                        'active'        => (int)$row['active'],
                        'expiring_soon' => (int)$row['expiring_soon'],
                        'total_amount'  => (float)$row['total_amount'],
                        'paid_amount'   => (float)$row['paid_amount']
                    ]
                ]);
                exit();

            case 'submitFeedback':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'POST only']);
                    exit();
                }
                $input = json_decode(file_get_contents('php://input'), true);
                $sub_sl = (int)($input['subscription_sl'] ?? 0);
                $rating = (int)($input['rating'] ?? 0);
                $comment = trim($input['comment'] ?? '');

                if ($rating < 1 || $rating > 5) {
                    echo json_encode(['success' => false, 'message' => 'Rating must be 1-5']);
                    exit();
                }

                $conn = getDBConnection();

                // verify sub belongs to this customer
                $chk = $conn->prepare("SELECT sl FROM subscriptions WHERE sl = ? AND customer_id = ?");
                $chk->bind_param("ii", $sub_sl, $customer_id);
                $chk->execute();
                if (!$chk->get_result()->fetch_assoc()) {
                    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
                    $chk->close();
                    exit();
                }
                $chk->close();

                $stmt = $conn->prepare("INSERT INTO customer_feedback (subscription_sl, customer_id, rating, comment, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("iiisi", $sub_sl, $customer_id, $rating, $comment, $user_id);
                $stmt->execute();
                $stmt->close();

                logActivity($user_id, 'Submit Feedback', 'Rated subscription #' . $sub_sl . ' — ' . $rating . '/5');
                echo json_encode(['success' => true]);
                exit();

            case 'getMyFeedback':
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT f.feedback_id, f.subscription_sl, f.rating, f.comment, f.created_at,
                        s.invoice_no, p.product_name
                    FROM customer_feedback f
                    LEFT JOIN subscriptions s ON f.subscription_sl = s.sl
                    LEFT JOIN products p ON s.product_id = p.product_id
                    WHERE f.customer_id = ?
                    ORDER BY f.created_at DESC");
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $list = [];
                while ($row = $res->fetch_assoc()) {
                    $list[] = [
                        'feedback_id'     => (int)$row['feedback_id'],
                        'subscription_sl' => (int)$row['subscription_sl'],
                        'invoice_no'      => $row['invoice_no'] ?? '',
                        'product_name'    => $row['product_name'] ?? '',
                        'rating'          => (int)$row['rating'],
                        'comment'         => $row['comment'] ?? '',
                        'created_at'      => $row['created_at'] ? date('M d, Y h:i A', strtotime($row['created_at'])) : ''
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $list]);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("customer_portal.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>My Portal - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <style>
        /* Summary Cards */
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .summary-card { background: #fff; border-radius: 10px; padding: 18px 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); display: flex; align-items: center; gap: 14px; transition: transform 0.2s; }
        .summary-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
        .summary-card .card-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; flex-shrink: 0; }
        .summary-card .card-info h3 { font-size: 22px; font-weight: 700; margin: 0; color: #001f3f; }
        .summary-card .card-info p { font-size: 12px; color: #666; margin: 2px 0 0 0; }
        .card-icon.bg-primary { background: #001f3f; }
        .card-icon.bg-success { background: #28a745; }
        .card-icon.bg-warning { background: #ffc107; }
        .card-icon.bg-info { background: #0074D9; }

        /* Skeleton */
        .skeleton-card .card-info h3, .skeleton-card .card-info p { background: #e9ecef; border-radius: 4px; animation: skeleton-pulse 1.5s infinite; }
        .skeleton-card .card-info h3 { width: 60px; height: 22px; }
        .skeleton-card .card-info p { width: 80px; height: 12px; margin-top: 6px; }
        @keyframes skeleton-pulse { 0%,100% { opacity: 0.6; } 50% { opacity: 1; } }

        /* Status Badges */
        .status-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block; }
        .status-active { background: #d4edda; color: #155724; }
        .status-expiring-soon { background: #fff3cd; color: #856404; }
        .status-expiring-today { background: #ffe0b2; color: #e65100; }
        .status-expired { background: #f8d7da; color: #721c24; }

        /* Payment Badges */
        .pay-paid { background: #d4edda; color: #155724; }
        .pay-unpaid { background: #f8d7da; color: #721c24; }
        .pay-partial { background: #fff3cd; color: #856404; }
        .pay-refunded { background: #cce5ff; color: #004085; }

        /* Product Badge */
        .cat-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block; }

        /* Days Left */
        .days-negative { color: #dc3545; font-weight: 700; }
        .days-critical { color: #e65100; font-weight: 700; }
        .days-warning { color: #856404; font-weight: 600; }
        .days-ok { color: #155724; font-weight: 600; }

        /* Tab switcher */
        .portal-tabs { display: flex; gap: 0; margin-bottom: 20px; border-bottom: 2px solid #e0e7ef; }
        .portal-tab { padding: 10px 24px; font-size: 14px; font-weight: 600; color: #666; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; background: none; border-top: none; border-left: none; border-right: none; }
        .portal-tab:hover { color: #001f3f; }
        .portal-tab.active { color: #001f3f; border-bottom-color: #0074D9; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Dark mode */
        .dark-mode .summary-card { background: #1a2332; }
        .dark-mode .summary-card .card-info h3 { color: #e9ecef; }
        .dark-mode .summary-card .card-info p { color: #adb5bd; }
        .dark-mode .portal-tabs { border-bottom-color: #2a3a4a; }
        .dark-mode .portal-tab { color: #adb5bd; }
        .dark-mode .portal-tab.active { color: #e9ecef; border-bottom-color: #0074D9; }
    </style>
</head>
<body>
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="breadcrumb">
                <a href="customer_portal.php"><i class="fas fa-home"></i> Portal</a>
                <span class="breadcrumb-sep">/</span>
                <span>My Subscriptions</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-id-card"></i> Welcome, <?php echo htmlspecialchars($full_name); ?></h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <!-- Stats Cards -->
            <div class="summary-cards" id="summaryCards">
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-primary"><i class="fas fa-file-alt"></i></div>
                    <div class="card-info"><h3 id="statTotal">&nbsp;</h3><p>Total Subscriptions</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-success"><i class="fas fa-check-circle"></i></div>
                    <div class="card-info"><h3 id="statActive">&nbsp;</h3><p>Active</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="card-info"><h3 id="statExpiring">&nbsp;</h3><p>Expiring Soon</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-info"><i class="fas fa-coins"></i></div>
                    <div class="card-info"><h3 id="statSpent">&nbsp;</h3><p>Total Spent</p></div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="data-section">
                <div class="portal-tabs">
                    <button class="portal-tab active" onclick="switchTab('subs', this)"><i class="fas fa-file-contract"></i> Subscriptions</button>
                    <button class="portal-tab" onclick="switchTab('payments', this)"><i class="fas fa-money-bill-wave"></i> Payments</button>
                    <button class="portal-tab" onclick="switchTab('feedback', this)"><i class="fas fa-star" style="color:#ffc107;"></i> My Ratings</button>
                </div>

                <!-- Subscriptions Tab -->
                <div class="tab-content active" id="tab-subs">
                    <div class="section-header">
                        <h2><i class="fas fa-table"></i> My Subscriptions</h2>
                        <button class="btn btn-primary" onclick="loadSubscriptions()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    <div class="table-scroll-hint">
                        <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                    </div>
                    <div class="table-responsive">
                        <table id="subsTable" class="display table-full-width"></table>
                    </div>
                </div>

                <!-- Feedback Tab -->
                <div class="tab-content" id="tab-feedback">
                    <div class="section-header">
                        <h2><i class="fas fa-star" style="color:#ffc107;"></i> My Ratings</h2>
                        <button class="btn btn-primary" onclick="loadFeedback()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="feedbackTable" class="display table-full-width"></table>
                    </div>
                </div>

                <!-- Payments Tab -->
                <div class="tab-content" id="tab-payments">
                    <div class="section-header">
                        <h2><i class="fas fa-money-bill-wave"></i> My Payments</h2>
                        <button class="btn btn-primary" onclick="loadPayments()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    <div class="table-scroll-hint">
                        <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                    </div>
                    <div class="table-responsive">
                        <table id="paymentsTable" class="display table-full-width"></table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
    // lazy-load PDF/Excel deps
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
        var subsTable, paymentsTable;
        var subsData = [], paymentsData = [];
        var globalCurrency = 'INR';
        var paymentsLoaded = false;

        $(document).ready(function() {
            loadStats();
            loadSubscriptions();
        });

        // tab switch
        function switchTab(tab, btn) {
            document.querySelectorAll('.portal-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
            if (tab === 'payments' && !paymentsLoaded) loadPayments();
            if (tab === 'feedback' && !feedbackLoaded) loadFeedback();
        }

        // stats
        function loadStats() {
            $.ajax({
                url: '?action=getMyStats',
                method: 'GET',
                dataType: 'json',
                success: function(r) {
                    if (r.success) {
                        var d = r.data;
                        var c = r.currency || 'INR';
                        globalCurrency = c;
                        document.getElementById('statTotal').textContent = d.total;
                        document.getElementById('statActive').textContent = d.active;
                        document.getElementById('statExpiring').textContent = d.expiring_soon;
                        document.getElementById('statSpent').textContent = c + ' ' + formatNumber(d.total_amount);
                        document.querySelectorAll('.skeleton-card').forEach(function(el) {
                            el.classList.remove('skeleton-card');
                        });
                    }
                },
                error: function() {
                    document.getElementById('statTotal').textContent = '-';
                    document.getElementById('statActive').textContent = '-';
                    document.getElementById('statExpiring').textContent = '-';
                    document.getElementById('statSpent').textContent = '-';
                }
            });
        }

        function formatNumber(n) {
            var val = parseFloat(n || 0);
            if (val % 1 === 0) return val.toLocaleString('en-IN');
            return val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
        }

        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        // subscriptions
        function loadSubscriptions() {
            $.ajax({
                url: '?action=getMySubscriptions',
                method: 'GET',
                dataType: 'json',
                success: function(r) {
                    if (r.success) {
                        subsData = r.data;
                        globalCurrency = r.currency || 'INR';
                        initSubsTable(r.data);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Failed to load subscriptions' });
                    }
                },
                error: function(x, s, e) {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
                }
            });
        }

        function initSubsTable(data) {
            if (subsTable) {
                subsTable.destroy();
                $('#subsTable').empty();
            }

            setTimeout(function() {
                var columns = [
                    { data: 'invoice_no', title: 'Invoice No', defaultContent: '-' },
                    {
                        data: 'product_name',
                        title: 'Product',
                        render: function(data, type, row) {
                            if (!data) return '-';
                            var bg = row.color_code || '#0078D4';
                            var r = parseInt(bg.substr(1,2), 16), g = parseInt(bg.substr(3,2), 16), b = parseInt(bg.substr(5,2), 16);
                            var tc = (r*0.299 + g*0.587 + b*0.114) > 186 ? '#000' : '#fff';
                            return '<span class="cat-badge" style="background:' + bg + ';color:' + tc + '">' + escapeHtml(data) + '</span>';
                        }
                    },
                    {
                        data: 'product_key',
                        title: 'License Key',
                        defaultContent: '-',
                        render: function(data) {
                            if (!data) return '-';
                            return '<code style="background:#e8f4fd;color:#0074D9;padding:4px 8px;border-radius:4px;font-family:monospace;font-weight:bold;user-select:all;border:1px solid #b8daff;" title="Double click to copy">' + escapeHtml(data) + '</code>';
                        }
                    },
                    { data: 'starting_date', title: 'Start Date', defaultContent: '-' },
                    { data: 'expiry_date', title: 'Expiry Date', defaultContent: '-' },
                    {
                        data: 'days_left',
                        title: 'Days Left',
                        render: function(data) {
                            if (data === null || data === undefined) return '-';
                            var cls = 'days-ok';
                            if (data < 0) cls = 'days-negative';
                            else if (data <= 7) cls = 'days-critical';
                            else if (data <= 30) cls = 'days-warning';
                            return '<span class="' + cls + '">' + data + '</span>';
                        }
                    },
                    {
                        data: 'total_amount',
                        title: 'Amount',
                        render: function(data) {
                            var val = parseFloat(data || 0);
                            var fmt = val % 1 === 0 ? val.toLocaleString('en-IN') : val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
                            return globalCurrency + ' ' + fmt;
                        }
                    },
                    {
                        data: 'payment_status',
                        title: 'Payment',
                        render: function(data) {
                            var cls = 'pay-unpaid';
                            if (data === 'Paid') cls = 'pay-paid';
                            else if (data === 'Partial') cls = 'pay-partial';
                            else if (data === 'Refunded') cls = 'pay-refunded';
                            return '<span class="status-badge ' + cls + '">' + data + '</span>';
                        }
                    },
                    {
                        data: 'status_label',
                        title: 'Status',
                        render: function(data, type, row) {
                            return '<span class="status-badge ' + row.status_class + '">' + data + '</span>';
                        }
                    },
                    {
                        data: null,
                        title: 'Action',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            var html = '<a href="invoice.php?sl=' + row.sl + '" class="action-icon" title="View Invoice"><i class="fas fa-file-pdf" style="color:#dc3545;"></i></a>';
                            html += ' <button class="action-icon" onclick="rateSub(' + row.sl + ', \'' + escapeQuote(row.invoice_no) + '\')" title="Rate" style="color:#ffc107;"><i class="fas fa-star"></i></button>';
                            // Pay Now button — only if not fully paid
                            if (row.payment_status !== 'Paid') {
                                html += ' <button class="action-icon" onclick="startRazorpayPayment(' + row.sl + ', \'' + escapeQuote(row.invoice_no) + '\', ' + parseFloat(row.total_amount || 0) + ')" title="Pay Now" style="background:#28a745;color:#fff;border:none;border-radius:4px;padding:4px 10px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-credit-card"></i> Pay Now</button>';
                            }
                            // Download button — only if paid and download link is available
                            if (row.payment_status === 'Paid' && row.product_download_url) {
                                html += ' <a href="download.php?sl=' + row.sl + '" class="action-icon" title="Download Product/Extension" target="_blank" style="background:#0074D9;color:#fff;border-radius:4px;padding:4px 10px;font-size:12px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px;vertical-align:middle;margin-left:4px;"><i class="fas fa-download"></i> Download</a>';
                            }
                            return html;
                        }
                    }
                ];

                var exportCols = [];
                for (var i = 0; i < columns.length - 1; i++) exportCols.push(i);

                subsTable = $('#subsTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: columns,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: exportCols }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: exportCols }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: exportCols }
                        }
                    ],
                    order: [[0, 'desc']]
                });
            }, 100);
        }

        // payments
        function loadPayments() {
            $.ajax({
                url: '?action=getMyPayments',
                method: 'GET',
                dataType: 'json',
                success: function(r) {
                    if (r.success) {
                        paymentsData = r.data;
                        paymentsLoaded = true;
                        globalCurrency = r.currency || globalCurrency;
                        initPaymentsTable(r.data);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Failed to load payments' });
                    }
                },
                error: function(x, s, e) {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
                }
            });
        }

        function initPaymentsTable(data) {
            if (paymentsTable) {
                paymentsTable.destroy();
                $('#paymentsTable').empty();
            }

            setTimeout(function() {
                var columns = [
                    { data: 'invoice_no', title: 'Invoice No', defaultContent: '-' },
                    {
                        data: 'amount',
                        title: 'Amount',
                        render: function(data) {
                            var val = parseFloat(data || 0);
                            var fmt = val % 1 === 0 ? val.toLocaleString('en-IN') : val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
                            return globalCurrency + ' ' + fmt;
                        }
                    },
                    { data: 'payment_method', title: 'Payment Method', defaultContent: '-' },
                    { data: 'payment_date', title: 'Date', defaultContent: '-' },
                    { data: 'reference_no', title: 'Reference', defaultContent: '-' },
                    { data: 'notes', title: 'Notes', defaultContent: '-' }
                ];

                var exportCols = [];
                for (var i = 0; i < columns.length; i++) exportCols.push(i);

                paymentsTable = $('#paymentsTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: columns,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: exportCols }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: exportCols }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: exportCols }
                        }
                    ],
                    order: [[3, 'desc']]
                });
            }, 100);
        }

        // escape single quotes for onclick attrs
        function escapeQuote(str) {
            if (!str) return '';
            return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        // star rating in swal
        function setRating(val) {
            document.getElementById('ratingVal').value = val;
            var stars = document.querySelectorAll('#starRating i');
            stars.forEach(function(s) {
                var v = parseInt(s.getAttribute('data-val'));
                s.className = v <= val ? 'fas fa-star' : 'far fa-star';
                s.style.color = v <= val ? '#ffc107' : '#ddd';
            });
        }

        // rate subscription
        function rateSub(sl, invoiceNo) {
            Swal.fire({
                title: 'Rate Subscription',
                html: '<p style="color:#666;margin-bottom:12px;">' + invoiceNo + '</p>' +
                    '<div id="starRating" style="font-size:32px;cursor:pointer;margin-bottom:16px;">' +
                    '<i class="far fa-star" data-val="1" onclick="setRating(1)" style="color:#ddd;"></i> ' +
                    '<i class="far fa-star" data-val="2" onclick="setRating(2)" style="color:#ddd;"></i> ' +
                    '<i class="far fa-star" data-val="3" onclick="setRating(3)" style="color:#ddd;"></i> ' +
                    '<i class="far fa-star" data-val="4" onclick="setRating(4)" style="color:#ddd;"></i> ' +
                    '<i class="far fa-star" data-val="5" onclick="setRating(5)" style="color:#ddd;"></i>' +
                    '</div>' +
                    '<input type="hidden" id="ratingVal" value="0">' +
                    '<textarea id="ratingComment" placeholder="Any comments? (optional)" rows="3" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:14px;"></textarea>',
                showCancelButton: true,
                confirmButtonText: 'Submit Rating',
                confirmButtonColor: '#001f3f',
                preConfirm: function() {
                    var rating = parseInt(document.getElementById('ratingVal').value);
                    if (!rating || rating < 1) {
                        Swal.showValidationMessage('Please select a rating');
                        return false;
                    }
                    return { rating: rating, comment: document.getElementById('ratingComment').value.trim() };
                }
            }).then(function(result) {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: '?action=submitFeedback',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ subscription_sl: sl, rating: result.value.rating, comment: result.value.comment }),
                    dataType: 'json',
                    success: function(r) {
                        if (r.success) {
                            Swal.fire({ icon: 'success', title: 'Thank you!', text: 'Your rating has been submitted.', timer: 2000, showConfirmButton: false });
                            if (feedbackLoaded) loadFeedback();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Failed to submit rating' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
                    }
                });
            });
        }

        // feedback tab
        var feedbackLoaded = false;
        var feedbackTable;

        function loadFeedback() {
            $.ajax({
                url: '?action=getMyFeedback',
                method: 'GET',
                dataType: 'json',
                success: function(r) {
                    if (r.success) {
                        feedbackLoaded = true;
                        initFeedbackTable(r.data);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Failed to load feedback' });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
                }
            });
        }

        function renderStars(rating) {
            var html = '';
            for (var i = 1; i <= 5; i++) {
                html += '<i class="' + (i <= rating ? 'fas' : 'far') + ' fa-star" style="color:' + (i <= rating ? '#ffc107' : '#ddd') + ';font-size:14px;"></i>';
            }
            return html;
        }

        function initFeedbackTable(data) {
            if (feedbackTable) { feedbackTable.destroy(); $('#feedbackTable').empty(); }
            setTimeout(function() {
                feedbackTable = $('#feedbackTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        { data: 'invoice_no', title: 'Invoice', defaultContent: '-' },
                        { data: 'product_name', title: 'Product', defaultContent: '-' },
                        { data: 'rating', title: 'Rating', render: function(d) { return renderStars(d); } },
                        { data: 'comment', title: 'Comment', defaultContent: '-', render: function(d) { return d ? escapeHtml(d) : '-'; } },
                        { data: 'created_at', title: 'Date', defaultContent: '-' }
                    ],
                    pageLength: 10,
                    responsive: true,
                    order: [[4, 'desc']]
                });
            }, 100);
        }

        // ============================================================
        // Razorpay Payment Integration
        // ============================================================
        function startRazorpayPayment(sl, invoiceNo, totalAmount) {
            // Step 1: Create order on server
            Swal.fire({
                title: 'Initiating Payment…',
                text: 'Please wait while we connect to Razorpay.',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            $.ajax({
                url: 'razorpay_create_order.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ subscription_sl: sl }),
                dataType: 'json',
                success: function(r) {
                    Swal.close();
                    if (!r.success) {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Could not initiate payment.' });
                        return;
                    }

                    // Step 2: Open Razorpay Checkout popup
                    var options = {
                        key:         r.key_id,
                        amount:      r.amount,
                        currency:    r.currency || 'INR',
                        name:        '<?php echo addslashes(htmlspecialchars(getSetting("company_name", "Subscription System"))); ?>',
                        description: r.description,
                        order_id:    r.order_id,
                        theme:       { color: '#0074D9' },
                        prefill:     {
                            name:  '<?php echo addslashes(htmlspecialchars($full_name)); ?>',
                            email: ''
                        },
                        handler: function(response) {
                            // Step 3: Verify payment on server
                            verifyRazorpayPayment(response, sl, r.amount);
                        },
                        modal: {
                            ondismiss: function() {
                                Swal.fire({ icon: 'info', title: 'Payment Cancelled', text: 'You cancelled the payment. You can try again anytime.', timer: 2500, showConfirmButton: false });
                            }
                        }
                    };

                    var rzp = new Razorpay(options);
                    rzp.on('payment.failed', function(response) {
                        Swal.fire({ icon: 'error', title: 'Payment Failed', text: response.error.description || 'Payment failed. Please try again.' });
                    });
                    rzp.open();
                },
                error: function() {
                    Swal.close();
                    Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not connect to payment gateway. Please try again.' });
                }
            });
        }

        function verifyRazorpayPayment(response, sl, amount) {
            Swal.fire({
                title: 'Verifying Payment…',
                text: 'Please wait, do not close this window.',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            $.ajax({
                url: 'razorpay_verify.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    razorpay_order_id:   response.razorpay_order_id,
                    razorpay_payment_id: response.razorpay_payment_id,
                    razorpay_signature:  response.razorpay_signature,
                    subscription_sl:     sl,
                    amount:              amount
                }),
                dataType: 'json',
                success: function(r) {
                    Swal.close();
                    if (r.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '🎉 Payment Successful!',
                            html: r.amount_paid + ' paid successfully.<br><small>Payment ID: <code>' + r.payment_id + '</code></small>',
                            confirmButtonText: 'Great!'
                        }).then(function() {
                            // Reload subscriptions to reflect new payment status
                            loadSubscriptions();
                            loadStats();
                        });
                    } else {
                        Swal.fire({ icon: 'warning', title: 'Verification Issue', text: r.message, footer: 'If money was deducted, contact admin with your Payment ID.' });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({ icon: 'error', title: 'Verification Failed', text: 'Payment may have gone through but could not verify. Please contact admin with your Razorpay Payment ID.' });
                }
            });
        }

    </script>
    <!-- Razorpay Checkout Script -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</body>
</html>
