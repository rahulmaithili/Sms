<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

$username  = $_SESSION['username'];
$role      = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_id   = $_SESSION['user_id'];
$sp_id     = $_SESSION['salesperson_id'] ?? null;
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'subscriptions';

// Validate sl parameter early (needed for AJAX and HTML)
$sl = isset($_GET['sl']) ? intval($_GET['sl']) : 0;
if ($sl <= 0 && !isset($_GET['action'])) {
    header("Location: subscriptions.php");
    exit();
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            case 'getInvoiceData':
                $req_sl = isset($_GET['sl']) ? intval($_GET['sl']) : 0;
                if ($req_sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch subscription with all joined data
                $stmt = $conn->prepare(
                    "SELECT s.*, p.product_name, sp.name AS salesperson_name, sp.commission_rate,
                        u.full_name AS added_by_name,
                        cust.company_name AS cust_company, cust.contact_person AS cust_contact,
                        cust.email AS cust_email, cust.phone AS cust_phone, cust.address AS cust_address,
                        cust.city AS cust_city, cust.country AS cust_country
                    FROM subscriptions s
                    LEFT JOIN products p ON s.product_id = p.product_id
                    LEFT JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id
                    LEFT JOIN users u ON s.added_by = u.user_id
                    LEFT JOIN customers cust ON s.customer_id = cust.customer_id
                    WHERE s.sl = ?"
                );
                $stmt->bind_param("i", $req_sl);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
                    exit();
                }

                $sub = $result->fetch_assoc();
                $stmt->close();

                // RBAC
                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($sub['salesperson_id'] ?? 0) !== $sp_id) {
                            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                        }
                    } elseif ((int)$sub['added_by'] !== $user_id) {
                        echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                    }
                }

                // Fetch payment records
                $pstmt = $conn->prepare(
                    "SELECT * FROM payments WHERE subscription_sl = ? ORDER BY payment_date ASC"
                );
                $pstmt->bind_param("i", $req_sl);
                $pstmt->execute();
                $pres = $pstmt->get_result();
                $payments = [];
                while ($prow = $pres->fetch_assoc()) {
                    $payments[] = [
                        'payment_id'     => (int)$prow['payment_id'],
                        'payment_date'   => $prow['payment_date'] ? date('Y-m-d', strtotime($prow['payment_date'])) : '',
                        'amount'         => (float)$prow['amount'],
                        'payment_method' => $prow['payment_method'] ?? '',
                        'reference_no'   => $prow['reference_no'] ?? '',
                        'notes'          => $prow['notes'] ?? ''
                    ];
                }
                $pstmt->close();

                // Fetch company settings
                $company_name     = getSetting('company_name', 'Rameez Scripts');
                $company_email    = getSetting('company_email', '');
                $company_logo_url = getSetting('company_logo_url', 'https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEiGXxCe0WNNedmFqSWeF761f7Kshhc-NP5ChRQKz9fr97cO8VaarvD0KlCwqHojJVBWv-RAxfOqMI5rD4H78KnARyOc6QgwL1nRRFWf5xNQ1d9F9HfAoLPPGlTyP0GwNl4n-INMEsWLQ4Y7zJtz5bOdAnc2ePH9-uCRgshlo6BsS6gJEz6fhrxL-5U5O3sX/s160/channels4_profile.jpg');
                $currency         = getSetting('currency', 'USD');
                $tax_percentage   = getSetting('tax_percentage', '0');

                // Build invoice data
                $invoice = [
                    'sl'                  => (int)$sub['sl'],
                    'invoice_no'          => $sub['invoice_no'] ?? ('INV-' . str_pad($req_sl, 4, '0', STR_PAD_LEFT)),
                    'renewal_invoice'     => $sub['renewal_invoice'] ?? '',
                    'invoice_date'        => $sub['invoice_date'] ? date('Y-m-d', strtotime($sub['invoice_date'])) : date('Y-m-d'),
                    'starting_date'       => $sub['starting_date'] ? date('Y-m-d', strtotime($sub['starting_date'])) : '',
                    'expiry_date'         => $sub['expiry_date'] ? date('Y-m-d', strtotime($sub['expiry_date'])) : '',
                    'customer_name'       => $sub['customer_name'] ?? '',
                    'cust_company'        => $sub['cust_company'] ?? '',
                    'cust_contact'        => $sub['cust_contact'] ?? '',
                    'cust_email'          => $sub['cust_email'] ?? '',
                    'cust_phone'          => $sub['cust_phone'] ?? '',
                    'cust_address'        => $sub['cust_address'] ?? '',
                    'cust_city'           => $sub['cust_city'] ?? '',
                    'cust_country'        => $sub['cust_country'] ?? '',
                    'product_name'        => $sub['product_name'] ?? '',
                    'product_description' => $sub['product_description'] ?? 'Subscription Service',
                    'product_key'         => $sub['product_key'] ?? '',
                    'user_qty'            => (int)($sub['user_qty'] ?? 1),
                    'license_duration'    => $sub['license_duration'] ?? '',
                    'selling_price'       => (float)$sub['selling_price'],
                    'purchase_price'      => (float)$sub['purchase_price'],
                    'tax_amount'          => (float)$sub['tax_amount'],
                    'total_amount'        => (float)$sub['total_amount'],
                    'payment_status'      => $sub['payment_status'] ?? 'Unpaid',
                    'payment_method'      => $sub['payment_method'] ?? '',
                    'payment_date'        => $sub['payment_date'] ? date('Y-m-d', strtotime($sub['payment_date'])) : '',
                    'auto_renew'          => (bool)$sub['auto_renew'],
                    'priority'            => $sub['priority'] ?? 'Medium',
                    'supplier_name'       => $sub['supplier_name'] ?? '',
                    'supplier_email'      => $sub['supplier_email'] ?? '',
                    'supplier_phone'      => $sub['supplier_phone'] ?? '',
                    'contract_reference'  => $sub['contract_reference'] ?? '',
                    'remarks'             => $sub['remarks'] ?? '',
                    'salesperson_name'    => $sub['salesperson_name'] ?? '',
                    'commission_rate'     => (float)($sub['commission_rate'] ?? 0),
                    'added_by_name'       => $sub['added_by_name'] ?? ''
                ];

                echo json_encode([
                    'success'          => true,
                    'invoice'          => $invoice,
                    'payments'         => $payments,
                    'company_name'     => $company_name,
                    'company_email'    => $company_email,
                    'company_logo_url' => $company_logo_url,
                    'currency'         => $currency,
                    'tax_percentage'   => $tax_percentage
                ]);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("invoice.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// Re-validate sl for HTML page render
if ($sl <= 0) {
    header("Location: subscriptions.php");
    exit();
}
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
    <title>Invoice - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <!-- pdfmake loaded statically (required for PDF generation) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <style>
        /* Invoice preview card */
        .invoice-wrapper {
            max-width: 860px;
            margin: 0 auto;
        }

        .invoice-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .invoice-card {
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .invoice-header {
            background: linear-gradient(135deg, #001f3f 0%, #003366 100%);
            color: #fff;
            padding: 30px 35px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }

        .invoice-company {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .invoice-company-logo {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.35);
            flex-shrink: 0;
        }

        .invoice-company-logo-placeholder {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }

        .invoice-company-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .invoice-company-email {
            font-size: 13px;
            opacity: 0.85;
        }

        .invoice-title-block {
            text-align: right;
        }

        .invoice-title-block h2 {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin: 0 0 8px 0;
            opacity: 0.95;
        }

        .invoice-no-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 6px 14px;
            border-radius: 3px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .invoice-body {
            padding: 30px 35px;
        }

        .invoice-meta-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 28px;
        }

        .invoice-meta-block h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #001f3f;
        }

        .invoice-meta-block .meta-line {
            display: flex;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 14px;
            color: #444;
        }

        .invoice-meta-block .meta-line .meta-label {
            color: #888;
            min-width: 100px;
            flex-shrink: 0;
        }

        .invoice-meta-block .meta-line .meta-value {
            color: #222;
            font-weight: 500;
        }

        .invoice-divider {
            border: none;
            border-top: 1px solid #e8e8e8;
            margin: 0 0 24px 0;
        }

        /* Line items table */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .invoice-table thead th {
            background: #001f3f;
            color: #fff;
            padding: 12px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
        }

        .invoice-table thead th:last-child,
        .invoice-table tbody td:last-child,
        .invoice-table tfoot td:last-child {
            text-align: right;
        }

        .invoice-table thead th:nth-child(2),
        .invoice-table tbody td:nth-child(2),
        .invoice-table thead th:nth-child(3),
        .invoice-table tbody td:nth-child(3) {
            text-align: center;
        }

        .invoice-table tbody td {
            padding: 13px 14px;
            color: #555;
            border-bottom: 1px solid #f0f0f0;
        }

        .invoice-table tbody tr:last-child td {
            border-bottom: none;
        }

        .invoice-table tfoot td {
            padding: 10px 14px;
            font-size: 14px;
            color: #555;
        }

        .invoice-table tfoot tr.total-row td {
            font-weight: 700;
            font-size: 16px;
            color: #001f3f;
            border-top: 2px solid #001f3f;
            padding-top: 12px;
        }

        /* Totals block */
        .invoice-totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 28px;
        }

        .invoice-totals-inner {
            width: 280px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #555;
        }

        .totals-row:last-child {
            border-bottom: none;
            border-top: 2px solid #001f3f;
            margin-top: 4px;
            padding-top: 12px;
            font-weight: 700;
            font-size: 16px;
            color: #001f3f;
        }

        /* Payment status badge */
        .payment-status-section {
            margin-bottom: 28px;
        }

        .payment-status-section h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 12px;
        }

        .invoice-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 3px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .invoice-status-paid {
            background: #d4edda;
            color: #155724;
        }

        .invoice-status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }

        .invoice-status-partial {
            background: #fff3cd;
            color: #856404;
        }

        .invoice-status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        /* Payment history table */
        .payments-section {
            margin-bottom: 28px;
        }

        .payments-section h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #0074D9;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .payments-table thead th {
            background: #0074D9;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
        }

        .payments-table thead th:last-child,
        .payments-table tbody td:last-child {
            text-align: right;
        }

        .payments-table tbody td {
            padding: 10px 12px;
            color: #555;
            border-bottom: 1px solid #f0f0f0;
        }

        .payments-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Notes section */
        .invoice-notes {
            background: #f8f9fa;
            border-left: 4px solid #0074D9;
            padding: 14px 18px;
            border-radius: 0 3px 3px 0;
            margin-bottom: 28px;
            font-size: 14px;
            color: #555;
            font-style: italic;
        }

        .invoice-notes strong {
            font-style: normal;
            color: #001f3f;
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Invoice footer */
        .invoice-footer {
            background: #f8f9fa;
            border-top: 1px solid #e8e8e8;
            padding: 18px 35px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }

        /* Skeleton loader for invoice */
        .invoice-skeleton {
            padding: 30px 35px;
        }

        .skeleton-line {
            height: 16px;
            border-radius: 3px;
            margin-bottom: 12px;
        }

        .skeleton-block {
            height: 100px;
            border-radius: 3px;
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .invoice-header {
                padding: 20px;
                flex-direction: column;
            }

            .invoice-title-block {
                text-align: left;
            }

            .invoice-body {
                padding: 20px;
            }

            .invoice-meta-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .invoice-totals {
                justify-content: stretch;
            }

            .invoice-totals-inner {
                width: 100%;
            }

            .invoice-footer {
                padding: 15px 20px;
            }

            .invoice-actions {
                gap: 8px;
            }

            .invoice-actions .btn {
                flex: 1;
                min-width: 0;
                font-size: 13px;
                padding: 10px 12px;
            }
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
                <a href="subscriptions.php">Subscriptions</a>
                <span class="breadcrumb-sep">/</span>
                <span id="breadcrumbInvoiceNo">Invoice #<span id="breadcrumbNo">...</span></span>
            </div>

            <div class="header">
                <h1><i class="fas fa-file-invoice"></i> Invoice</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <!-- Action buttons -->
            <div class="invoice-wrapper">
                <div class="invoice-actions">
                    <button class="btn btn-primary" onclick="downloadPDF()" id="btnDownload" disabled>
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                    <button class="btn btn-secondary" onclick="printInvoice()" id="btnPrint" disabled>
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="subscriptions.php" class="btn btn-secondary" style="text-decoration:none;">
                        <i class="fas fa-arrow-left"></i> Back to Subscriptions
                    </a>
                </div>

                <!-- Invoice preview card -->
                <div class="invoice-card" id="invoiceCard">

                    <!-- Skeleton loader -->
                    <div id="invoiceSkeleton">
                        <div style="background:#001f3f;padding:30px 35px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;">
                                <div style="display:flex;align-items:center;gap:16px;">
                                    <div class="skeleton" style="width:64px;height:64px;border-radius:50%;flex-shrink:0;"></div>
                                    <div>
                                        <div class="skeleton" style="width:180px;height:22px;margin-bottom:8px;"></div>
                                        <div class="skeleton" style="width:120px;height:14px;"></div>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div class="skeleton" style="width:140px;height:30px;margin-bottom:10px;margin-left:auto;"></div>
                                    <div class="skeleton" style="width:110px;height:16px;margin-left:auto;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="invoice-skeleton">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-bottom:24px;">
                                <div>
                                    <div class="skeleton skeleton-line" style="width:80px;margin-bottom:16px;"></div>
                                    <div class="skeleton skeleton-line" style="width:100%;"></div>
                                    <div class="skeleton skeleton-line" style="width:85%;"></div>
                                    <div class="skeleton skeleton-line" style="width:75%;"></div>
                                </div>
                                <div>
                                    <div class="skeleton skeleton-line" style="width:80px;margin-bottom:16px;"></div>
                                    <div class="skeleton skeleton-line" style="width:100%;"></div>
                                    <div class="skeleton skeleton-line" style="width:70%;"></div>
                                    <div class="skeleton skeleton-line" style="width:80%;"></div>
                                </div>
                            </div>
                            <div class="skeleton skeleton-block"></div>
                            <div class="skeleton skeleton-block" style="height:60px;"></div>
                        </div>
                    </div>

                    <!-- Actual invoice content (hidden until loaded) -->
                    <div id="invoiceContent" style="display:none;">

                        <!-- Header -->
                        <div class="invoice-header">
                            <div class="invoice-company">
                                <div id="logoContainer">
                                    <div class="invoice-company-logo-placeholder">
                                        <i class="fas fa-building" style="color:rgba(255,255,255,0.7);"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="invoice-company-name" id="companyName">-</div>
                                    <div class="invoice-company-email" id="companyEmail">-</div>
                                </div>
                            </div>
                            <div class="invoice-title-block">
                                <h2>Invoice</h2>
                                <div class="invoice-no-badge" id="invoiceNoBadge">-</div>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="invoice-body">

                            <!-- Invoice info + Bill To -->
                            <div class="invoice-meta-row">
                                <div class="invoice-meta-block">
                                    <h4>Invoice Details</h4>
                                    <div class="meta-line">
                                        <span class="meta-label">Invoice No:</span>
                                        <span class="meta-value" id="metaInvoiceNo">-</span>
                                    </div>
                                    <div class="meta-line">
                                        <span class="meta-label">Date:</span>
                                        <span class="meta-value" id="metaInvoiceDate">-</span>
                                    </div>
                                    <div class="meta-line">
                                        <span class="meta-label">Due Date:</span>
                                        <span class="meta-value" id="metaDueDate">-</span>
                                    </div>
                                    <div class="meta-line" id="metaProductRow">
                                        <span class="meta-label">Product:</span>
                                        <span class="meta-value" id="metaProduct">-</span>
                                    </div>
                                    <div class="meta-line" id="metaSalespersonRow" style="display:none;">
                                        <span class="meta-label">Salesperson:</span>
                                        <span class="meta-value" id="metaSalesperson">-</span>
                                    </div>
                                    <div class="meta-line" id="metaAddedByRow">
                                        <span class="meta-label">Added By:</span>
                                        <span class="meta-value" id="metaAddedBy">-</span>
                                    </div>
                                </div>
                                <div class="invoice-meta-block">
                                    <h4>Bill To</h4>
                                    <div class="meta-line">
                                        <span class="meta-label">Name:</span>
                                        <span class="meta-value" id="billName">-</span>
                                    </div>
                                    <div class="meta-line" id="billCompanyRow" style="display:none;">
                                        <span class="meta-label">Company:</span>
                                        <span class="meta-value" id="billCompany">-</span>
                                    </div>
                                    <div class="meta-line" id="billEmailRow" style="display:none;">
                                        <span class="meta-label">Email:</span>
                                        <span class="meta-value" id="billEmail">-</span>
                                    </div>
                                    <div class="meta-line" id="billPhoneRow" style="display:none;">
                                        <span class="meta-label">Phone:</span>
                                        <span class="meta-value" id="billPhone">-</span>
                                    </div>
                                    <div class="meta-line" id="billAddressRow" style="display:none;">
                                        <span class="meta-label">Address:</span>
                                        <span class="meta-value" id="billAddress">-</span>
                                    </div>
                                </div>
                            </div>

                            <hr class="invoice-divider">

                            <!-- Line items -->
                            <table class="invoice-table">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th style="text-align:center;">Qty</th>
                                        <th style="text-align:right;">Unit Price</th>
                                        <th style="text-align:right;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="invoiceLineItems">
                                    <tr>
                                        <td colspan="4" style="text-align:center;color:#aaa;padding:20px;">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Totals -->
                            <div class="invoice-totals">
                                <div class="invoice-totals-inner">
                                    <div class="totals-row">
                                        <span>Subtotal</span>
                                        <span id="totalSubtotal">-</span>
                                    </div>
                                    <div class="totals-row">
                                        <span id="taxLabel">Tax</span>
                                        <span id="totalTax">-</span>
                                    </div>
                                    <div class="totals-row">
                                        <span>Total</span>
                                        <span id="totalAmount">-</span>
                                    </div>
                                </div>
                            </div>

                            <hr class="invoice-divider">

                            <!-- Payment status -->
                            <div class="payment-status-section">
                                <h4>Payment Status</h4>
                                <div class="invoice-status-badge" id="paymentStatusBadge">
                                    <i class="fas fa-circle-notch fa-spin"></i> Loading...
                                </div>
                            </div>

                            <!-- Payment history -->
                            <div class="payments-section" id="paymentsSection" style="display:none;">
                                <h4><i class="fas fa-history"></i> Payment History</h4>
                                <table class="payments-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Method</th>
                                            <th>Reference</th>
                                            <th style="text-align:right;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="paymentsTableBody"></tbody>
                                </table>
                            </div>

                            <!-- Notes / Remarks -->
                            <div class="invoice-notes" id="invoiceNotes" style="display:none;">
                                <strong>Notes</strong>
                                <span id="invoiceNotesText"></span>
                            </div>

                        </div><!-- /invoice-body -->

                        <!-- Footer -->
                        <div class="invoice-footer" id="invoiceFooter">
                            <span id="footerText">-</span>
                        </div>

                    </div><!-- /invoiceContent -->

                </div><!-- /invoice-card -->
            </div><!-- /invoice-wrapper -->

        </div><!-- /main-content -->
    </div><!-- /app-container -->

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    // ── State ─────────────────────────────────────────────────────────────────
    var invoiceData  = null;
    var paymentsData = [];
    var companyName  = '';
    var companyEmail = '';
    var currency     = 'USD';
    var docDefinition = null;

    // ── Init ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        loadInvoiceData();
    });

    // ── Helpers ───────────────────────────────────────────────────────────────
    function formatCurrency(amount) {
        var val = parseFloat(amount) || 0;
        return currency + ' ' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getStatusBadgeClass(status) {
        switch ((status || '').toLowerCase()) {
            case 'paid':    return 'invoice-status-paid';
            case 'partial': return 'invoice-status-partial';
            case 'overdue': return 'invoice-status-overdue';
            default:        return 'invoice-status-unpaid';
        }
    }

    function getStatusIcon(status) {
        switch ((status || '').toLowerCase()) {
            case 'paid':    return 'fa-check-circle';
            case 'partial': return 'fa-adjust';
            case 'overdue': return 'fa-exclamation-circle';
            default:        return 'fa-times-circle';
        }
    }

    function getStatusColor(status) {
        switch ((status || '').toLowerCase()) {
            case 'paid':    return '#28a745';
            case 'partial': return '#856404';
            case 'overdue': return '#dc3545';
            default:        return '#dc3545';
        }
    }

    // ── Load invoice data ─────────────────────────────────────────────────────
    function loadInvoiceData() {
        fetch('?action=getInvoiceData&sl=<?php echo $sl; ?>')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to load invoice data'
                    }).then(function() {
                        window.location.href = 'subscriptions.php';
                    });
                    return;
                }

                invoiceData  = data.invoice;
                paymentsData = data.payments || [];
                companyName  = data.company_name  || 'Company';
                companyEmail = data.company_email || '';
                currency     = data.currency      || 'USD';

                populatePreview(data);
                buildDocDefinition();
                enableButtons();
            })
            .catch(function(err) {
                console.error('Invoice load error:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Could not load invoice data. Please try again.'
                });
            });
    }

    // ── Populate HTML preview ─────────────────────────────────────────────────
    function populatePreview(data) {
        var inv = data.invoice;

        // Breadcrumb
        document.getElementById('breadcrumbNo').textContent = inv.invoice_no || ('INV-' + inv.sl);

        // Header
        document.getElementById('companyName').textContent  = companyName;
        document.getElementById('companyEmail').textContent = companyEmail;
        document.getElementById('invoiceNoBadge').textContent = inv.invoice_no || ('INV-' + inv.sl);

        // Logo
        if (data.company_logo_url) {
            var img = document.createElement('img');
            img.src = data.company_logo_url;
            img.alt = companyName;
            img.className = 'invoice-company-logo';
            img.onerror = function() {
                this.style.display = 'none';
            };
            document.getElementById('logoContainer').innerHTML = '';
            document.getElementById('logoContainer').appendChild(img);
        }

        // Invoice meta
        document.getElementById('metaInvoiceNo').textContent   = inv.invoice_no || '-';
        document.getElementById('metaInvoiceDate').textContent  = inv.invoice_date || '-';
        document.getElementById('metaDueDate').textContent      = inv.expiry_date || '-';
        document.getElementById('metaProduct').textContent      = inv.product_name || '-';
        document.getElementById('metaAddedBy').textContent      = inv.added_by_name || '-';

        if (inv.salesperson_name) {
            document.getElementById('metaSalesperson').textContent = inv.salesperson_name;
            document.getElementById('metaSalespersonRow').style.display = '';
        }

        // Bill to
        var customerDisplay = inv.cust_contact || inv.customer_name || '-';
        document.getElementById('billName').textContent = customerDisplay;

        if (inv.cust_company) {
            document.getElementById('billCompany').textContent = inv.cust_company;
            document.getElementById('billCompanyRow').style.display = '';
        }
        if (inv.cust_email) {
            document.getElementById('billEmail').textContent = inv.cust_email;
            document.getElementById('billEmailRow').style.display = '';
        }
        if (inv.cust_phone) {
            document.getElementById('billPhone').textContent = inv.cust_phone;
            document.getElementById('billPhoneRow').style.display = '';
        }
        if (inv.cust_address) {
            var addrParts = [inv.cust_address];
            if (inv.cust_city)    addrParts.push(inv.cust_city);
            if (inv.cust_country) addrParts.push(inv.cust_country);
            document.getElementById('billAddress').textContent = addrParts.join(', ');
            document.getElementById('billAddressRow').style.display = '';
        }

        // Line items
        var qty       = inv.user_qty || 1;
        var unitPrice = qty > 0 ? (inv.selling_price / qty) : inv.selling_price;
        var desc      = inv.product_description || 'Subscription Service';
        if (inv.license_duration) desc += ' (' + inv.license_duration + ')';

        var lineItemsHtml =
            '<tr>' +
            '<td>' + esc(desc) + '</td>' +
            '<td style="text-align:center;">' + qty + '</td>' +
            '<td style="text-align:right;">' + formatCurrency(unitPrice) + '</td>' +
            '<td style="text-align:right;">' + formatCurrency(inv.selling_price) + '</td>' +
            '</tr>';
        document.getElementById('invoiceLineItems').innerHTML = lineItemsHtml;

        // Totals
        document.getElementById('totalSubtotal').textContent = formatCurrency(inv.selling_price);
        var taxPct = parseFloat(data.tax_percentage) || 0;
        document.getElementById('taxLabel').textContent = taxPct > 0 ? ('Tax (' + taxPct + '%)') : 'Tax';
        document.getElementById('totalTax').textContent    = formatCurrency(inv.tax_amount);
        document.getElementById('totalAmount').textContent  = formatCurrency(inv.total_amount);

        // Payment status badge
        var statusBadge = document.getElementById('paymentStatusBadge');
        statusBadge.className = 'invoice-status-badge ' + getStatusBadgeClass(inv.payment_status);
        statusBadge.innerHTML = '<i class="fas ' + getStatusIcon(inv.payment_status) + '"></i> ' + esc(inv.payment_status || 'Unpaid');

        // Payment history
        if (paymentsData.length > 0) {
            var pbody = '';
            paymentsData.forEach(function(p) {
                pbody +=
                    '<tr>' +
                    '<td>' + esc(p.payment_date) + '</td>' +
                    '<td>' + esc(p.payment_method || '-') + '</td>' +
                    '<td>' + esc(p.reference_no || '-') + '</td>' +
                    '<td style="text-align:right;">' + formatCurrency(p.amount) + '</td>' +
                    '</tr>';
            });
            document.getElementById('paymentsTableBody').innerHTML = pbody;
            document.getElementById('paymentsSection').style.display = '';
        }

        // Notes / remarks
        if (inv.remarks) {
            document.getElementById('invoiceNotesText').textContent = inv.remarks;
            document.getElementById('invoiceNotes').style.display = '';
        }

        // Footer
        var footerParts = [companyName];
        if (companyEmail) footerParts.push(companyEmail);
        document.getElementById('footerText').textContent = footerParts.join(' | ');

        // Show invoice content, hide skeleton
        document.getElementById('invoiceSkeleton').style.display = 'none';
        document.getElementById('invoiceContent').style.display  = '';
    }

    // ── Enable action buttons ─────────────────────────────────────────────────
    function enableButtons() {
        document.getElementById('btnDownload').disabled = false;
        document.getElementById('btnPrint').disabled    = false;
    }

    // ── Build pdfmake doc definition ──────────────────────────────────────────
    function buildDocDefinition() {
        var inv = invoiceData;
        var qty       = inv.user_qty || 1;
        var unitPrice = qty > 0 ? (inv.selling_price / qty) : inv.selling_price;
        var desc      = inv.product_description || 'Subscription Service';
        if (inv.license_duration) desc += ' (' + inv.license_duration + ')';

        // Build customer address string
        var customerName  = inv.cust_contact || inv.customer_name || '';
        var customerLines = [];
        if (inv.cust_company)  customerLines.push(inv.cust_company);
        if (inv.cust_address)  customerLines.push(inv.cust_address);
        var cityCountry = [inv.cust_city, inv.cust_country].filter(Boolean).join(', ');
        if (cityCountry)       customerLines.push(cityCountry);
        if (inv.cust_email)    customerLines.push(inv.cust_email);
        if (inv.cust_phone)    customerLines.push(inv.cust_phone);

        // Payment history rows for PDF
        var paymentRows = paymentsData.map(function(p) {
            return [
                p.payment_date || '-',
                p.payment_method || '-',
                p.reference_no || '-',
                { text: formatCurrency(p.amount), alignment: 'right' }
            ];
        });

        // Footer
        var footerParts = [companyName];
        if (companyEmail) footerParts.push(companyEmail);
        var footerStr = footerParts.join(' | ');

        // Tax label
        var taxPctLabel = 'Tax';
        if (inv.tax_amount > 0) taxPctLabel = 'Tax';

        // Content array
        var content = [
            // Company header
            { text: companyName, style: 'header' },
            companyEmail ? { text: companyEmail, style: 'subheader' } : {},
            {
                canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 1.5, lineColor: '#001f3f' }]
            },
            '\n',
            // Invoice title + info columns
            {
                columns: [
                    {
                        width: '*',
                        stack: [
                            { text: 'INVOICE', style: 'invoiceTitle' },
                            { text: 'Invoice No: ' + (inv.invoice_no || ('INV-' + inv.sl)), bold: true, fontSize: 11, color: '#001f3f', margin: [0, 4, 0, 0] }
                        ]
                    },
                    {
                        width: 'auto',
                        alignment: 'right',
                        stack: [
                            { text: 'Date: ' + (inv.invoice_date || '-'),   fontSize: 10, color: '#555' },
                            { text: 'Due:  ' + (inv.expiry_date  || '-'),  fontSize: 10, color: '#555', margin: [0, 4, 0, 0] }
                        ]
                    }
                ]
            },
            '\n',
            // Bill to
            { text: 'Bill To', style: 'sectionHeader', margin: [0, 4, 0, 6] },
            { text: customerName, bold: true, fontSize: 11 }
        ];

        // Add customer detail lines
        customerLines.forEach(function(line) {
            content.push({ text: line, fontSize: 10, color: '#555' });
        });

        // Separator + line items table
        content.push('\n');
        content.push({
            table: {
                headerRows: 1,
                widths: ['*', 50, 100, 100],
                body: [
                    [
                        { text: 'Description', style: 'tableHeader' },
                        { text: 'Qty', style: 'tableHeader', alignment: 'center' },
                        { text: 'Unit Price', style: 'tableHeader', alignment: 'right' },
                        { text: 'Amount', style: 'tableHeader', alignment: 'right' }
                    ],
                    [
                        { text: desc, fontSize: 10 },
                        { text: String(qty), fontSize: 10, alignment: 'center' },
                        { text: formatCurrency(unitPrice), fontSize: 10, alignment: 'right' },
                        { text: formatCurrency(inv.selling_price), fontSize: 10, alignment: 'right' }
                    ]
                ]
            },
            layout: {
                hLineColor: function(i) { return i === 0 || i === 1 ? '#001f3f' : '#e0e0e0'; },
                vLineColor: function() { return '#e0e0e0'; }
            }
        });

        // Totals block
        content.push('\n');
        content.push({
            columns: [
                { text: '', width: '*' },
                {
                    width: 220,
                    table: {
                        widths: ['*', 'auto'],
                        body: [
                            [
                                { text: 'Subtotal:', alignment: 'left', color: '#555', fontSize: 10 },
                                { text: formatCurrency(inv.selling_price), alignment: 'right', fontSize: 10 }
                            ],
                            [
                                { text: taxPctLabel + ':', alignment: 'left', color: '#555', fontSize: 10 },
                                { text: formatCurrency(inv.tax_amount), alignment: 'right', fontSize: 10 }
                            ],
                            [
                                { text: 'Total:', alignment: 'left', bold: true, fontSize: 12, color: '#001f3f' },
                                { text: formatCurrency(inv.total_amount), alignment: 'right', bold: true, fontSize: 12, color: '#001f3f' }
                            ]
                        ]
                    },
                    layout: {
                        hLineColor: function(i, node) {
                            return i === node.table.body.length - 1 ? '#001f3f' : '#e0e0e0';
                        },
                        hLineWidth: function(i, node) {
                            return i === node.table.body.length - 1 ? 1.5 : 0.5;
                        },
                        vLineColor: function() { return 'transparent'; }
                    }
                }
            ]
        });

        // Payment status
        content.push('\n');
        content.push({
            text: 'Payment Status: ' + (inv.payment_status || 'Unpaid'),
            bold: true,
            fontSize: 11,
            color: getStatusColor(inv.payment_status)
        });

        // Payment history
        if (paymentsData.length > 0) {
            content.push({ text: 'Payment History', style: 'sectionHeader', margin: [0, 16, 0, 6] });
            content.push({
                table: {
                    headerRows: 1,
                    widths: [90, '*', 110, 90],
                    body: [
                        [
                            { text: 'Date',      style: 'tableHeader' },
                            { text: 'Method',    style: 'tableHeader' },
                            { text: 'Reference', style: 'tableHeader' },
                            { text: 'Amount',    style: 'tableHeader', alignment: 'right' }
                        ]
                    ].concat(paymentRows)
                },
                layout: {
                    hLineColor: function(i) { return i === 0 || i === 1 ? '#0074D9' : '#e0e0e0'; },
                    vLineColor: function() { return '#e0e0e0'; }
                }
            });
        }

        // Notes / remarks
        if (inv.remarks) {
            content.push({
                text: 'Notes: ' + inv.remarks,
                margin: [0, 16, 0, 0],
                italics: true,
                color: '#666',
                fontSize: 10
            });
        }

        docDefinition = {
            pageSize: 'A4',
            pageMargins: [40, 40, 40, 50],
            content: content,
            styles: {
                header: {
                    fontSize: 22,
                    bold: true,
                    color: '#001f3f',
                    margin: [0, 0, 0, 4]
                },
                subheader: {
                    fontSize: 10,
                    color: '#666',
                    margin: [0, 0, 0, 6]
                },
                invoiceTitle: {
                    fontSize: 26,
                    bold: true,
                    color: '#001f3f',
                    letterSpacing: 3
                },
                sectionHeader: {
                    fontSize: 12,
                    bold: true,
                    color: '#001f3f',
                    decoration: 'underline'
                },
                tableHeader: {
                    bold: true,
                    fillColor: '#001f3f',
                    color: '#ffffff',
                    fontSize: 10,
                    margin: [4, 6, 4, 6]
                }
            },
            defaultStyle: {
                fontSize: 10,
                lineHeight: 1.4
            },
            footer: function(currentPage, pageCount) {
                return {
                    text: footerStr + ' | Page ' + currentPage + ' of ' + pageCount,
                    alignment: 'center',
                    fontSize: 8,
                    color: '#999',
                    margin: [40, 10]
                };
            }
        };
    }

    // ── Download PDF ──────────────────────────────────────────────────────────
    function downloadPDF() {
        if (!docDefinition) {
            Swal.fire({ icon: 'warning', title: 'Not Ready', text: 'Invoice data is still loading. Please wait.' });
            return;
        }
        var filename = 'Invoice-' + (invoiceData.invoice_no || ('INV-' + invoiceData.sl)) + '.pdf';
        try {
            pdfMake.createPdf(docDefinition).download(filename);
        } catch (e) {
            console.error('PDF generation error:', e);
            Swal.fire({ icon: 'error', title: 'PDF Error', text: 'Failed to generate PDF: ' + e.message });
        }
    }

    // ── Print ─────────────────────────────────────────────────────────────────
    function printInvoice() {
        if (!docDefinition) {
            Swal.fire({ icon: 'warning', title: 'Not Ready', text: 'Invoice data is still loading. Please wait.' });
            return;
        }
        try {
            pdfMake.createPdf(docDefinition).print();
        } catch (e) {
            console.error('Print error:', e);
            Swal.fire({ icon: 'error', title: 'Print Error', text: 'Failed to print: ' + e.message });
        }
    }
    </script>
</body>
</html>
