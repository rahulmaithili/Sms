<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */

require_once 'config.php';

// Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

$username  = $_SESSION['username'];
$role      = $_SESSION['role'];
$user_id   = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'suppliers';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            // ── 1. getSuppliers ──────────────────────────────────────────────
            case 'getSuppliers':
                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "SELECT supplier_id, company_name, contact_person, email, phone,
                            city, country, address, notes, is_active, created_at
                     FROM suppliers
                     ORDER BY company_name ASC"
                );
                $stmt->execute();
                $result = $stmt->get_result();

                $suppliers = [];
                while ($row = $result->fetch_assoc()) {
                    $suppliers[] = [
                        'supplier_id'    => (int)$row['supplier_id'],
                        'company_name'   => $row['company_name'],
                        'contact_person' => $row['contact_person'] ?? '',
                        'email'          => $row['email'] ?? '',
                        'phone'          => $row['phone'] ?? '',
                        'city'           => $row['city'] ?? '',
                        'country'        => $row['country'] ?? '',
                        'address'        => $row['address'] ?? '',
                        'notes'          => $row['notes'] ?? '',
                        'is_active'      => (bool)$row['is_active'],
                        'created_at'     => date('M d, Y', strtotime($row['created_at']))
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $suppliers]);
                exit();

            // ── 2. addSupplier ───────────────────────────────────────────────
            case 'addSupplier':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $company_name   = isset($_POST['company_name'])   ? trim($_POST['company_name'])   : '';
                $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
                $email          = isset($_POST['email'])          ? trim($_POST['email'])          : '';
                $phone          = isset($_POST['phone'])          ? trim($_POST['phone'])          : '';
                $address        = isset($_POST['address'])        ? trim($_POST['address'])        : '';
                $city           = isset($_POST['city'])           ? trim($_POST['city'])           : '';
                $country        = isset($_POST['country'])        ? trim($_POST['country'])        : '';
                $notes          = isset($_POST['notes'])          ? trim($_POST['notes'])          : '';

                if (empty($company_name)) {
                    echo json_encode(['success' => false, 'message' => 'Company name is required']);
                    exit();
                }

                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    exit();
                }

                // Nullable values
                $contactVal = !empty($contact_person) ? $contact_person : null;
                $emailVal   = !empty($email)          ? $email          : null;
                $phoneVal   = !empty($phone)          ? $phone          : null;
                $addressVal = !empty($address)        ? $address        : null;
                $cityVal    = !empty($city)           ? $city           : null;
                $countryVal = !empty($country)        ? $country        : null;
                $notesVal   = !empty($notes)          ? $notes          : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "INSERT INTO suppliers
                        (company_name, contact_person, email, phone, address, city, country, notes, added_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "ssssssssi",
                    $company_name, $contactVal, $emailVal, $phoneVal,
                    $addressVal, $cityVal, $countryVal, $notesVal, $user_id
                );

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Supplier Created', "Created supplier: $company_name");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Supplier added successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Supplier already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add supplier']);
                    }
                }
                exit();

            // ── 3. updateSupplier ────────────────────────────────────────────
            case 'updateSupplier':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $supplier_id    = isset($_POST['supplier_id'])    ? intval($_POST['supplier_id'])    : 0;
                $company_name   = isset($_POST['company_name'])   ? trim($_POST['company_name'])     : '';
                $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person'])   : '';
                $email          = isset($_POST['email'])          ? trim($_POST['email'])             : '';
                $phone          = isset($_POST['phone'])          ? trim($_POST['phone'])             : '';
                $address        = isset($_POST['address'])        ? trim($_POST['address'])           : '';
                $city           = isset($_POST['city'])           ? trim($_POST['city'])              : '';
                $country        = isset($_POST['country'])        ? trim($_POST['country'])           : '';
                $notes          = isset($_POST['notes'])          ? trim($_POST['notes'])             : '';
                $is_active      = isset($_POST['is_active'])      ? intval($_POST['is_active'])       : 1;

                if ($supplier_id <= 0 || empty($company_name)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid input']);
                    exit();
                }

                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    exit();
                }

                $contactVal = !empty($contact_person) ? $contact_person : null;
                $emailVal   = !empty($email)          ? $email          : null;
                $phoneVal   = !empty($phone)          ? $phone          : null;
                $addressVal = !empty($address)        ? $address        : null;
                $cityVal    = !empty($city)           ? $city           : null;
                $countryVal = !empty($country)        ? $country        : null;
                $notesVal   = !empty($notes)          ? $notes          : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "UPDATE suppliers
                     SET company_name=?, contact_person=?, email=?, phone=?,
                         address=?, city=?, country=?, notes=?, is_active=?
                     WHERE supplier_id=?"
                );
                $stmt->bind_param(
                    "ssssssssii",
                    $company_name, $contactVal, $emailVal, $phoneVal,
                    $addressVal, $cityVal, $countryVal, $notesVal,
                    $is_active, $supplier_id
                );

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Supplier Updated', "Updated supplier: $company_name");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Company name already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update supplier']);
                    }
                }
                exit();

            // ── 4. toggleActive ──────────────────────────────────────────────
            case 'toggleActive':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $supplier_id = isset($_POST['id'])        ? intval($_POST['id'])        : 0;
                $is_active   = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

                if ($supplier_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE suppliers SET is_active=? WHERE supplier_id=?");
                $stmt->bind_param("ii", $is_active, $supplier_id);

                if ($stmt->execute()) {
                    $label = $is_active ? 'Supplier Activated' : 'Supplier Deactivated';
                    logActivity($user_id, $username, $label, "Changed active status for supplier ID $supplier_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_active ? 'Supplier activated' : 'Supplier deactivated']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                exit();

            // ── 5. deleteSupplier ────────────────────────────────────────────
            case 'deleteSupplier':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $supplier_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                if ($supplier_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch company name before deletion for log
                $stmt = $conn->prepare("SELECT company_name FROM suppliers WHERE supplier_id=?");
                $stmt->bind_param("i", $supplier_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $deletedName = '';
                if ($result->num_rows > 0) {
                    $deletedName = $result->fetch_assoc()['company_name'];
                }
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id=?");
                $stmt->bind_param("i", $supplier_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Supplier Deleted', "Deleted supplier: $deletedName");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete supplier']);
                }
                exit();

            // ── 6. viewSupplierSubscriptions ─────────────────────────────────
            case 'viewSupplierSubscriptions':
                $supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

                if ($supplier_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "SELECT s.sl, s.invoice_no, s.customer_name, s.expiry_date,
                            s.total_amount, s.payment_status, p.product_name
                     FROM subscriptions s
                     LEFT JOIN products p ON s.product_id = p.product_id
                     WHERE s.supplier_id = ?
                     ORDER BY s.invoice_date DESC"
                );
                $stmt->bind_param("i", $supplier_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $subscriptions = [];
                while ($row = $result->fetch_assoc()) {
                    $status = getSubscriptionStatus($row['expiry_date']);
                    $subscriptions[] = [
                        'sl'             => (int)$row['sl'],
                        'invoice_no'     => $row['invoice_no'] ?? '',
                        'customer_name'  => $row['customer_name'],
                        'product_name'   => $row['product_name'] ?? 'N/A',
                        'expiry_date'    => $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '-',
                        'total_amount'   => number_format((float)$row['total_amount'], 2),
                        'payment_status' => $row['payment_status'] ?? '',
                        'status_label'   => $status['label'],
                        'status_class'   => $status['class']
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $subscriptions]);
                exit();

            // ── 7. viewSupplierReport ────────────────────────────────────────
            case 'viewSupplierReport':
                $supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

                if ($supplier_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "SELECT s.sl, s.invoice_no, s.customer_name, s.product_description,
                            s.purchase_price, s.selling_price, s.total_amount, s.payment_status,
                            s.invoice_date, s.expiry_date, p.product_name,
                            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.subscription_sl = s.sl) AS paid_amount
                     FROM subscriptions s
                     LEFT JOIN products p ON s.product_id = p.product_id
                     WHERE s.supplier_id = ?
                     ORDER BY s.invoice_date DESC"
                );
                $stmt->bind_param("i", $supplier_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $rows = [];
                $total_purchase_value     = 0;
                $paid_invoices_count      = 0;
                $unpaid_invoices_count    = 0;
                $total_subscriptions_count = 0;

                while ($row = $result->fetch_assoc()) {
                    $total_subscriptions_count++;
                    $total_purchase_value += (float)$row['purchase_price'];

                    $payStatus = strtolower($row['payment_status'] ?? '');
                    if ($payStatus === 'paid') {
                        $paid_invoices_count++;
                    } else {
                        $unpaid_invoices_count++;
                    }

                    $rows[] = [
                        'sl'                  => (int)$row['sl'],
                        'invoice_no'          => $row['invoice_no'] ?? '',
                        'customer_name'       => $row['customer_name'] ?? '',
                        'product_description' => $row['product_description'] ?? '',
                        'purchase_price'      => number_format((float)$row['purchase_price'], 2),
                        'selling_price'       => number_format((float)$row['selling_price'], 2),
                        'total_amount'        => number_format((float)$row['total_amount'], 2),
                        'payment_status'      => $row['payment_status'] ?? '',
                        'invoice_date'        => $row['invoice_date'] ? date('M d, Y', strtotime($row['invoice_date'])) : '-',
                        'expiry_date'         => $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '-',
                        'product_name'        => $row['product_name'] ?? 'N/A',
                        'paid_amount'         => number_format((float)$row['paid_amount'], 2)
                    ];
                }
                $stmt->close();

                echo json_encode([
                    'success' => true,
                    'data'    => $rows,
                    'summary' => [
                        'total_subscriptions_count' => $total_subscriptions_count,
                        'total_purchase_value'      => number_format($total_purchase_value, 2),
                        'paid_invoices_count'       => $paid_invoices_count,
                        'unpaid_invoices_count'     => $unpaid_invoices_count
                    ]
                ]);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("suppliers.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// If we reach here, render the HTML page
$branding = getSiteBranding();
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
    <title>Suppliers - <?php echo htmlspecialchars($branding['site_name']); ?></title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <style>
        /* Toggle switch */
        .toggle { appearance: none; width: 44px; height: 24px; border-radius: 24px; background: #ccc; position: relative; cursor: pointer; transition: background .3s; border: none; outline: none; vertical-align: middle; }
        .toggle:checked { background: #0074D9; }
        .toggle::before { content: ""; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform .3s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
        .toggle:checked::before { transform: translateX(20px); }
        .swal-no-padding { padding: 0 !important; }
        .swal-no-padding .swal2-html-container { padding: 0 !important; margin: 0 !important; }
        .swal-no-padding .swal2-close { color: #fff !important; opacity: .8; z-index: 10; }
        .swal-no-padding .swal2-close:hover { opacity: 1; }

        /* Report icon */
        .action-icon.report-icon { color: #0074D9; }
        .action-icon.report-icon:hover { background: rgba(0,116,217,0.1); transform: scale(1.15); }

        /* Payment status badges */
        .payment-badge { padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; display: inline-block; }
        .payment-paid     { background: #d4edda; color: #155724; }
        .payment-unpaid   { background: #f8d7da; color: #721c24; }
        .payment-partial  { background: #fff3cd; color: #856404; }
        .payment-refunded { background: #cce5ff; color: #004085; }

        /* Report summary cards */
        .report-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 18px; }
        .report-card { background: #f8f9fa; border-radius: 4px; padding: 14px 16px; text-align: center; border-left: 4px solid #001f3f; }
        .report-card .rc-value { font-size: 22px; font-weight: 700; color: #001f3f; margin-bottom: 4px; }
        .report-card .rc-label { font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600; }
        .report-card.rc-paid { border-left-color: #34a853; }
        .report-card.rc-paid .rc-value { color: #34a853; }
        .report-card.rc-unpaid { border-left-color: #ea4335; }
        .report-card.rc-unpaid .rc-value { color: #ea4335; }
        .report-card.rc-total { border-left-color: #0074D9; }
        .report-card.rc-total .rc-value { color: #0074D9; }

        /* Report table inside Swal */
        .report-table-wrapper { overflow-x: auto; margin-top: 10px; }
        .report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .report-table th { background: #001f3f; color: #fff; padding: 10px 12px; text-align: left; font-weight: 600; white-space: nowrap; }
        .report-table td { padding: 10px 12px; border-bottom: 1px solid #eee; color: #555; white-space: nowrap; }
        .report-table tbody tr:hover { background: #f9f9f9; }
        .report-table .no-data td { text-align: center; color: #999; font-style: italic; padding: 20px; }

        /* Subscription status badges inside Swal */
        .sub-status { padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-active         { background: #d4edda; color: #155724; }
        .status-expired        { background: #f8d7da; color: #721c24; }
        .status-expiring-soon  { background: #fff3cd; color: #856404; }
        .status-expiring-today { background: #ffe0b2; color: #e65100; }
        .status-unknown        { background: #e2e3e5; color: #383d41; }

        /* initially-hidden */
        .initially-hidden { visibility: hidden; }

        @media (max-width: 768px) {
            .report-summary { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .report-summary { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="initially-hidden">
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span>Suppliers</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-truck"></i> Suppliers</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Suppliers</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadSuppliers()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Supplier
                        </button>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filters-section initially-hidden" id="filtersSection">
                    <div class="filters-header">
                        <h3><i class="fas fa-filter"></i> Filters</h3>
                        <button class="btn btn-secondary btn-sm" onclick="clearFilters()">
                            <i class="fas fa-times-circle"></i> Clear All
                        </button>
                    </div>
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-building"></i> Company Name</label>
                            <input type="text" id="filterCompany" class="filter-input" placeholder="Search company...">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-city"></i> City</label>
                            <input type="text" id="filterCity" class="filter-input" placeholder="Search city...">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-toggle-on"></i> Status</label>
                            <select id="filterStatus" class="filter-input">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                </div>
                <div class="table-responsive">
                    <table id="suppliersTable" class="display table-full-width"></table>
                </div>
            </div>

        </div>
    </div>

    <!-- Supplier Modal -->
    <div class="modal-overlay" id="supplierModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-truck"></i> Add Supplier</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="supplierForm">
                    <input type="hidden" id="supplierId" name="supplier_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Company Name *</label>
                            <input type="text" id="formCompanyName" name="company_name" required placeholder="Enter company name">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Contact Person</label>
                            <input type="text" id="formContactPerson" name="contact_person" placeholder="Enter contact person name">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="formEmail" name="email" placeholder="Enter email address">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone</label>
                            <input type="text" id="formPhone" name="phone" placeholder="Enter phone number">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-city"></i> City</label>
                            <input type="text" id="formCity" name="city" placeholder="Enter city">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-globe"></i> Country</label>
                            <input type="text" id="formCountry" name="country" placeholder="Enter country">
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea id="formAddress" name="address" rows="2" placeholder="Enter full address"></textarea>
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label><i class="fas fa-sticky-note"></i> Notes</label>
                            <textarea id="formNotes" name="notes" rows="2" placeholder="Any additional notes..."></textarea>
                        </div>

                        <div class="form-group" id="activeGroup" style="display:none;">
                            <label><i class="fas fa-toggle-on"></i> Active Status</label>
                            <select id="formIsActive" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
    // Lazy-load PDF/Excel export dependencies on first use
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
        var supplierCurrency = '<?php echo getCurrency(); ?>';
        var suppliersTable;
        var isEditMode    = false;
        var suppliersData = [];

        // Escape HTML helper
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Reveal body once sidebar/theme JS has run
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.remove('initially-hidden');
            loadSuppliers();
        });

        // ── Load & render table ───────────────────────────────────────────────
        function loadSuppliers() {
            $.ajax({
                url: '?action=getSuppliers',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        suppliersData = response.data;
                        $('#filtersSection').show().removeClass('initially-hidden');
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to load suppliers'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Could not connect to server. Please check console for details.'
                    });
                }
            });
        }

        function initializeDataTable(data) {
            if (suppliersTable) {
                suppliersTable.destroy();
                $('#suppliersTable').empty();
            }

            setTimeout(function() {
                suppliersTable = $('#suppliersTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        { data: 'supplier_id',    title: 'ID',             width: '50px' },
                        { data: 'company_name',   title: 'Company Name' },
                        { data: 'contact_person', title: 'Contact Person', defaultContent: '-' },
                        {
                            data: 'email',
                            title: 'Email',
                            defaultContent: '-',
                            render: function(data) {
                                if (!data) return '-';
                                return '<a href="mailto:' + escapeHtml(data) + '" style="color:#0074D9;text-decoration:none;">' + escapeHtml(data) + '</a>';
                            }
                        },
                        { data: 'phone', title: 'Phone', defaultContent: '-' },
                        { data: 'city',  title: 'City',  defaultContent: '-' },
                        {
                            data: 'is_active',
                            title: 'Active',
                            render: function(data, type, row) {
                                var checked = data ? 'checked="checked"' : '';
                                return '<input type="checkbox" ' + checked + ' class="toggle" onchange="toggleActive(' + row.supplier_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        { data: 'created_at', title: 'Created' },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                var rowJson = JSON.stringify(row).replace(/'/g, "\\'");
                                return '<button class="action-icon report-icon" onclick="viewSupplierReport(' + row.supplier_id + ', \'' + escapeHtml(row.company_name).replace(/'/g, "\\'") + '\')" title="Purchase Report" style="color:#0074D9;"><i class="fas fa-chart-line"></i></button> ' +
                                       '<button class="action-icon edit-icon" title="Edit" onclick=\'editSupplier(' + rowJson + ')\'><i class="fas fa-edit"></i></button> ' +
                                       '<button class="action-icon delete-icon" title="Delete" onclick="deleteSupplier(' + row.supplier_id + ')"><i class="fas fa-trash"></i></button>';
                            }
                        }
                    ],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 7] }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 7] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 7] }
                        }
                    ],
                    order: [[1, 'asc']]
                });

                // Custom filters
                $('#filterCompany').on('keyup', applyFilters);
                $('#filterCity').on('keyup', applyFilters);
                $('#filterStatus').on('change', applyFilters);
            }, 100);
        }

        // ── Custom filter logic ───────────────────────────────────────────────
        function applyFilters() {
            if (!suppliersTable) return;

            $.fn.dataTable.ext.search = [];

            var companyFilter = document.getElementById('filterCompany').value.toLowerCase();
            var cityFilter    = document.getElementById('filterCity').value.toLowerCase();
            var statusFilter  = document.getElementById('filterStatus').value;

            $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
                var row = suppliersData[dataIndex];
                if (!row) return true;

                if (companyFilter && row.company_name.toLowerCase().indexOf(companyFilter) === -1) return false;
                if (cityFilter    && (row.city || '').toLowerCase().indexOf(cityFilter) === -1)    return false;
                if (statusFilter === 'active'   && !row.is_active) return false;
                if (statusFilter === 'inactive' &&  row.is_active) return false;

                return true;
            });

            suppliersTable.draw();
        }

        function clearFilters() {
            document.getElementById('filterCompany').value = '';
            document.getElementById('filterCity').value    = '';
            document.getElementById('filterStatus').value  = '';

            if (suppliersTable) {
                $.fn.dataTable.ext.search = [];
                suppliersTable.columns().search('').draw();
            }
        }

        // ── Modal helpers ─────────────────────────────────────────────────────
        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-truck"></i> Add Supplier';
            document.getElementById('supplierForm').reset();
            document.getElementById('supplierId').value = '';
            document.getElementById('activeGroup').style.display = 'none';
            document.getElementById('supplierModal').classList.add('active');
        }

        function editSupplier(sup) {
            isEditMode = true;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Supplier';
            document.getElementById('supplierId').value          = sup.supplier_id;
            document.getElementById('formCompanyName').value     = sup.company_name;
            document.getElementById('formContactPerson').value   = sup.contact_person || '';
            document.getElementById('formEmail').value           = sup.email           || '';
            document.getElementById('formPhone').value           = sup.phone           || '';
            document.getElementById('formCity').value            = sup.city            || '';
            document.getElementById('formCountry').value         = sup.country         || '';
            document.getElementById('formAddress').value         = sup.address         || '';
            document.getElementById('formNotes').value           = sup.notes           || '';
            document.getElementById('formIsActive').value        = sup.is_active ? '1' : '0';
            document.getElementById('activeGroup').style.display = '';
            document.getElementById('supplierModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('supplierModal').classList.remove('active');
            document.getElementById('supplierForm').reset();
        }

        document.getElementById('supplierModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // ── Form submit ───────────────────────────────────────────────────────
        document.getElementById('supplierForm').addEventListener('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var action   = isEditMode ? 'updateSupplier' : 'addSupplier';

            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            $.ajax({
                url: '?action=' + action,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        closeModal();
                        setTimeout(function() { loadSuppliers(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        });

        // ── Toggle active ─────────────────────────────────────────────────────
        function toggleActive(supplierId, isActive) {
            var formData = new FormData();
            formData.append('id',        supplierId);
            formData.append('is_active', isActive);

            $.ajax({
                url: '?action=toggleActive',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        setTimeout(function() { loadSuppliers(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        setTimeout(function() { loadSuppliers(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        }

        // ── Delete supplier ───────────────────────────────────────────────────
        function deleteSupplier(supplierId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Supplier?',
                text: 'This action cannot be undone. All linked data may also be affected.',
                showCancelButton: true,
                confirmButtonColor: '#ea4335',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    var formData = new FormData();
                    formData.append('id', supplierId);

                    $.ajax({
                        url: '?action=deleteSupplier',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                setTimeout(function() { loadSuppliers(); }, 100);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                        }
                    });
                }
            });
        }

        // ── View supplier purchase report ─────────────────────────────────────
        function viewSupplierReport(supplierId, companyName) {
            Swal.fire({
                title: '',
                html: '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#0074D9;"></i><p style="margin-top:10px;color:#666;">Loading...</p></div>',
                width: 920,
                showConfirmButton: false,
                showCloseButton: true,
                padding: 0,
                customClass: { popup: 'swal-no-padding' },
                didOpen: function() {
                    $.ajax({
                        url: '?action=viewSupplierReport&supplier_id=' + supplierId,
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (!response.success) {
                                Swal.update({ html: '<p style="color:#dc3545;padding:20px;">Failed to load report.</p>' });
                                return;
                            }
                            var rows = response.data;
                            var summary = response.summary;
                            var html = '';

                            // Branded header
                            html += '<div style="background:linear-gradient(135deg,var(--navy-primary,#001f3f) 0%,var(--navy-light,#003366) 100%);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;">';
                            html += '<i class="fas fa-truck" style="font-size:20px;color:var(--navy-accent,#0074D9);"></i>';
                            html += '<div><div style="font-size:16px;font-weight:700;">' + escapeHtml(companyName) + '</div><div style="font-size:11px;opacity:.7;">Purchase Report</div></div>';
                            html += '</div>';

                            // Stats row
                            html += '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-bottom:1px solid #e9ecef;">';
                            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:var(--navy-primary,#001f3f);">' + summary.total_subscriptions_count + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Total</div></div>';
                            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:var(--navy-accent,#0074D9);">' + supplierCurrency + ' ' + summary.total_purchase_value + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Purchase Value</div></div>';
                            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:#28a745;">' + summary.paid_invoices_count + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Paid</div></div>';
                            html += '<div style="padding:14px;text-align:center;"><div style="font-size:22px;font-weight:700;color:#dc3545;">' + summary.unpaid_invoices_count + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Unpaid</div></div>';
                            html += '</div>';

                            if (rows.length === 0) {
                                html += '<div style="padding:50px 20px;text-align:center;color:#888;"><i class="fas fa-inbox" style="font-size:40px;color:#ddd;display:block;margin-bottom:14px;"></i>No purchases found for this supplier</div>';
                            } else {
                                html += '<div style="padding:20px;">';
                                html += '<div class="about-table-wrapper" style="margin:0;border-radius:4px;overflow:hidden;border:1px solid #e0e0e0;">';
                                html += '<table class="about-roles-table" style="font-size:12px;margin:0;">';
                                html += '<thead><tr><th style="text-align:left;">Invoice</th><th>Customer</th><th>Product</th><th>Purchase Price</th><th>Payment</th><th style="text-align:right;">Paid</th></tr></thead>';
                                html += '<tbody>';
                                var payColors = { 'Paid': '#28a745', 'Unpaid': '#dc3545', 'Partial': '#e67e00', 'Refunded': '#0074D9' };
                                rows.forEach(function(r) {
                                    var pc = payColors[r.payment_status] || '#888';
                                    var pName = (r.product_name || r.product_description || '-');
                                    if (pName.length > 40) pName = pName.substring(0, 40) + '...';
                                    html += '<tr>';
                                    html += '<td style="text-align:left;font-weight:600;">' + (r.invoice_no || '-') + '</td>';
                                    html += '<td>' + escapeHtml(r.customer_name || '-') + '</td>';
                                    html += '<td title="' + escapeHtml(r.product_description || '') + '">' + escapeHtml(pName) + '</td>';
                                    html += '<td>' + supplierCurrency + ' ' + parseFloat(r.purchase_price).toFixed(0) + '</td>';
                                    html += '<td><span class="role-badge" style="background:' + pc + ';color:#fff;">' + (r.payment_status || 'Unknown') + '</span></td>';
                                    html += '<td style="text-align:right;font-weight:600;">' + supplierCurrency + ' ' + parseFloat(r.paid_amount).toFixed(0) + '</td>';
                                    html += '</tr>';
                                });
                                html += '</tbody></table></div></div>';
                            }

                            // Bottom bar with print
                            html += '<div style="padding:14px 20px;border-top:1px solid #e9ecef;background:#f8f9fa;display:flex;align-items:center;justify-content:space-between;">';
                            html += '<span style="font-size:12px;color:#888;">Total Purchase: <strong style="color:var(--navy-primary,#001f3f);">' + supplierCurrency + ' ' + summary.total_purchase_value + '</strong></span>';
                            html += '<button onclick="printSupplierReport(\'' + escapeHtml(companyName).replace(/'/g, "\\'") + '\')" style="display:inline-flex;align-items:center;gap:8px;padding:8px 20px;background:var(--navy-primary,#001f3f);color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:13px;font-weight:600;transition:all .2s;" onmouseover="this.style.opacity=\'0.85\'" onmouseout="this.style.opacity=\'1\'"><i class="fas fa-print"></i> Print Report</button>';
                            html += '</div>';

                            Swal.update({ html: html });
                        },
                        error: function() {
                            Swal.update({ html: '<p style="color:#dc3545;padding:20px;">Connection error.</p>' });
                        }
                    });
                }
            });
        }

        function printSupplierReport(name) {
            var el = document.querySelector('.swal2-html-container');
            if (!el) return;
            var w = window.open('', '_blank', 'width=800,height=600');
            w.document.write('<!DOCTYPE html><html><head><title>Purchase Report - ' + name + '</title><style>body{font-family:Arial,sans-serif;margin:20px;color:#333;}h2{color:#001f3f;margin-bottom:5px;}table{width:100%;border-collapse:collapse;font-size:13px;}th{background:#001f3f;color:#fff;padding:10px 12px;text-align:left;}td{padding:8px 12px;border-bottom:1px solid #e0e0e0;}tr:nth-child(even){background:#f8f9fa;}@media print{body{margin:10px;}}</style></head><body>');
            w.document.write('<h2>' + name + ' — Purchase Report</h2><p style="color:#666;font-size:13px;margin-bottom:15px;">Generated: ' + new Date().toLocaleDateString() + '</p>');
            var table = el.querySelector('.about-roles-table');
            if (table) w.document.write(table.outerHTML);
            w.document.write('</body></html>');
            w.document.close();
            w.focus();
            setTimeout(function() { w.print(); }, 300);
        }
    </script>
</body>
</html>
