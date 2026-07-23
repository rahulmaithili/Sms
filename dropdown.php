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

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'dropdown';

// Handle AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        $conn = getDBConnection();

        // check if table exists, create + seed only if missing
        $tbl_check = $conn->query("SHOW TABLES LIKE 'dropdown_options'");
        if ($tbl_check->num_rows === 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS dropdown_options (
                option_id INT AUTO_INCREMENT PRIMARY KEY,
                dropdown_type VARCHAR(50) NOT NULL,
                option_value VARCHAR(100) NOT NULL,
                display_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY idx_type_value (dropdown_type, option_value),
                INDEX idx_type_active (dropdown_type, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $defaults = ['Cash','Bank Transfer','Credit Card','Online','Cheque','Other'];
            $ins = $conn->prepare("INSERT IGNORE INTO dropdown_options (dropdown_type, option_value, display_order) VALUES ('payment_method', ?, ?)");
            foreach ($defaults as $i => $v) {
                $ord = ($i + 1) * 10;
                $ins->bind_param("si", $v, $ord);
                $ins->execute();
            }
            $ins->close();
        }

        switch ($_GET['action']) {

            case 'getOptions':
                $stmt = $conn->prepare("SELECT option_id, dropdown_type, option_value, display_order, is_active, created_at FROM dropdown_options WHERE dropdown_type = 'payment_method' ORDER BY display_order ASC, option_value ASC");
                $stmt->execute();
                $result = $stmt->get_result();

                $options = [];
                while ($r = $result->fetch_assoc()) {
                    $options[] = [
                        'option_id'     => (int)$r['option_id'],
                        'dropdown_type' => $r['dropdown_type'],
                        'option_value'  => $r['option_value'],
                        'display_order' => (int)$r['display_order'],
                        'is_active'     => (bool)$r['is_active'],
                        'created_at'    => date('M d, Y', strtotime($r['created_at']))
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $options]);
                exit();

            case 'addOption':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $option_value  = isset($_POST['option_value'])  ? trim($_POST['option_value'])  : '';
                $display_order = isset($_POST['display_order']) ? intval($_POST['display_order']) : 0;

                if (empty($option_value)) {
                    echo json_encode(['success' => false, 'message' => 'Payment method is required']);
                    exit();
                }

                $type = 'payment_method';
                $stmt = $conn->prepare("INSERT INTO dropdown_options (dropdown_type, option_value, display_order) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $type, $option_value, $display_order);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Dropdown Option Created', "Created payment method: $option_value");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Payment method added successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'This payment method already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add payment method']);
                    }
                }
                exit();

            case 'updateOption':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $option_id     = isset($_POST['option_id'])     ? intval($_POST['option_id'])     : 0;
                $option_value  = isset($_POST['option_value'])  ? trim($_POST['option_value'])    : '';
                $display_order = isset($_POST['display_order']) ? intval($_POST['display_order'])  : 0;
                $is_active     = isset($_POST['is_active'])     ? intval($_POST['is_active'])     : 1;

                if ($option_id <= 0 || empty($option_value)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid input']);
                    exit();
                }

                $stmt = $conn->prepare("UPDATE dropdown_options SET option_value = ?, display_order = ?, is_active = ? WHERE option_id = ?");
                $stmt->bind_param("siii", $option_value, $display_order, $is_active, $option_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Dropdown Option Updated', "Updated payment method: $option_value");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Payment method updated successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'This payment method already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update payment method']);
                    }
                }
                exit();

            case 'toggleActive':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $option_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

                if ($option_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid option ID']);
                    exit();
                }

                $stmt = $conn->prepare("UPDATE dropdown_options SET is_active = ? WHERE option_id = ?");
                $stmt->bind_param("ii", $is_active, $option_id);

                if ($stmt->execute()) {
                    $action_label = $is_active ? 'Dropdown Option Activated' : 'Dropdown Option Deactivated';
                    logActivity($user_id, $username, $action_label, "Changed active status for option ID $option_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_active ? 'Payment method activated' : 'Payment method deactivated']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                exit();

            case 'deleteOption':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $option_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                if ($option_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid option ID']);
                    exit();
                }

                // fetch name before delete
                $stmt = $conn->prepare("SELECT option_value FROM dropdown_options WHERE option_id = ?");
                $stmt->bind_param("i", $option_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $deletedName = '';
                if ($result->num_rows > 0) {
                    $deletedName = $result->fetch_assoc()['option_value'];
                }
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM dropdown_options WHERE option_id = ?");
                $stmt->bind_param("i", $option_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Dropdown Option Deleted', "Deleted payment method: $deletedName");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Payment method deleted successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete payment method']);
                }
                exit();

            case 'getMethodReport':
                $method = isset($_GET['method']) ? trim($_GET['method']) : '';
                if (empty($method)) {
                    echo json_encode(['success' => false, 'message' => 'Method required']); exit();
                }

                // subs using this method
                $stmt = $conn->prepare("SELECT s.sl, s.customer_name, s.invoice_no, s.total_amount, s.payment_status, s.invoice_date, s.expiry_date,
                        COALESCE(p2.product_name, s.product_description, 'N/A') AS product_name,
                        IFNULL((SELECT SUM(py.amount) FROM payments py WHERE py.subscription_sl = s.sl), 0) AS paid_amount
                    FROM subscriptions s LEFT JOIN products p2 ON s.product_id = p2.product_id
                    WHERE s.payment_method = ?
                    ORDER BY s.invoice_date DESC");
                $stmt->bind_param("s", $method);
                $stmt->execute();
                $res = $stmt->get_result();
                $subs = [];
                $totalAmt = 0; $totalPaid = 0;
                while ($r = $res->fetch_assoc()) {
                    $amt = (float)$r['total_amount'];
                    $paid = (float)$r['paid_amount'];
                    $totalAmt += $amt;
                    $totalPaid += $paid;
                    $subs[] = [
                        'sl' => (int)$r['sl'],
                        'customer_name' => $r['customer_name'],
                        'invoice_no' => $r['invoice_no'] ?? '',
                        'product_name' => $r['product_name'],
                        'total_amount' => round($amt, 3),
                        'paid_amount' => round($paid, 3),
                        'balance' => round($amt - $paid, 3),
                        'payment_status' => $r['payment_status'] ?? 'Unpaid',
                        'invoice_date' => $r['invoice_date'] ? date('M d, Y', strtotime($r['invoice_date'])) : '-',
                        'expiry_date' => $r['expiry_date'] ? date('M d, Y', strtotime($r['expiry_date'])) : '-'
                    ];
                }
                $stmt->close();

                // payments using this method
                $stmt2 = $conn->prepare("SELECT py.payment_id, py.subscription_sl, py.amount, py.payment_date, py.reference_no, py.notes, py.created_at,
                        s.invoice_no, s.customer_name, u.full_name AS added_by_name
                    FROM payments py
                    LEFT JOIN subscriptions s ON py.subscription_sl = s.sl
                    LEFT JOIN users u ON py.added_by = u.user_id
                    WHERE py.payment_method = ?
                    ORDER BY py.payment_date DESC");
                $stmt2->bind_param("s", $method);
                $stmt2->execute();
                $pRes = $stmt2->get_result();
                $payments = [];
                while ($p = $pRes->fetch_assoc()) {
                    $payments[] = [
                        'payment_id' => (int)$p['payment_id'],
                        'subscription_sl' => (int)$p['subscription_sl'],
                        'invoice_no' => $p['invoice_no'] ?? '',
                        'customer_name' => $p['customer_name'] ?? '-',
                        'amount' => round((float)$p['amount'], 3),
                        'payment_date' => $p['payment_date'] ? date('M d, Y', strtotime($p['payment_date'])) : '-',
                        'reference_no' => $p['reference_no'] ?? '',
                        'notes' => $p['notes'] ?? '',
                        'added_by_name' => $p['added_by_name'] ?? '-'
                    ];
                }
                $stmt2->close();

                echo json_encode([
                    'success' => true,
                    'subs' => $subs,
                    'payments' => $payments,
                    'total' => round($totalAmt, 3),
                    'total_paid' => round($totalPaid, 3),
                    'count' => count($subs),
                    'currency' => getCurrency()
                ]);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("dropdown.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
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
    <title>Payment Methods - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <style>
        .toggle { appearance: none; width: 44px; height: 24px; border-radius: 24px; background: #ccc; position: relative; cursor: pointer; transition: background .3s; border: none; outline: none; vertical-align: middle; }
        .toggle:checked { background: #0074D9; }
        .toggle::before { content: ""; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform .3s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
        .toggle:checked::before { transform: translateX(20px); }
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
                <span>Payment Methods</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-credit-card"></i> Payment Methods</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Payment Methods</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadOptions()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Payment Method
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section initially-hidden" id="filtersSection">
                    <div class="filters-header">
                        <h3><i class="fas fa-filter"></i> Filters</h3>
                        <button class="btn btn-secondary btn-sm" onclick="clearFilters()">
                            <i class="fas fa-times-circle"></i> Clear All
                        </button>
                    </div>
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Payment Method</label>
                            <input type="text" id="filterName" class="filter-input" placeholder="Search name...">
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
                    <table id="optionsTable" class="display table-full-width"></table>
                </div>
            </div>
        </div>
    </div>

    <!-- Option Modal -->
    <div class="modal-overlay" id="optionModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-credit-card"></i> Add Payment Method</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="optionForm">
                    <input type="hidden" id="optionId" name="option_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-credit-card"></i> Payment Method *</label>
                            <input type="text" id="formOptionValue" name="option_value" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-sort-numeric-up"></i> Display Order</label>
                            <input type="number" id="formDisplayOrder" name="display_order" value="0" min="0">
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
    // lazy-load PDF/Excel export deps
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
        let optionsTable;
        let isEditMode = false;
        let optionsData = [];

        $(document).ready(function() {
            loadOptions();
        });

        function loadOptions() {
            $.ajax({
                url: '?action=getOptions',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        optionsData = response.data;
                        $('#filtersSection').show();
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to load payment methods'
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
            if (optionsTable) {
                optionsTable.destroy();
                $('#optionsTable').empty();
            }

            setTimeout(function() {
                optionsTable = $('#optionsTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        { data: 'option_id', title: 'ID' },
                        { data: 'option_value', title: 'Payment Method' },
                        { data: 'display_order', title: 'Display Order' },
                        {
                            data: 'is_active',
                            title: 'Active',
                            render: function(data, type, row) {
                                var checked = data ? 'checked' : '';
                                return '<input type="checkbox" ' + checked + ' class="toggle" onchange="toggleActive(' + row.option_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        { data: 'created_at', title: 'Created' },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                return '<button class="action-icon" onclick="showMethodReport(\'' + row.option_value.replace(/'/g, "\\'") + '\')" title="View Subscriptions" style="color:#0074D9;"><i class="fas fa-chart-bar"></i></button>' +
                                       '<button class="action-icon edit-icon" onclick=\'editOption(' + JSON.stringify(row).replace(/'/g, "\\'") + ')\'><i class="fas fa-edit"></i></button>' +
                                       '<button class="action-icon delete-icon" onclick="deleteOption(' + row.option_id + ')"><i class="fas fa-trash"></i></button>';
                            }
                        }
                    ],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: [0, 1, 2, 4] }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: [0, 1, 2, 4] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: [0, 1, 2, 4] }
                        }
                    ],
                    order: [[2, 'asc'], [1, 'asc']]
                });

                // filter listeners
                $('#filterName').on('keyup', function() { applyFilters(); });
                $('#filterStatus').on('change', function() { applyFilters(); });
            }, 100);
        }

        function applyFilters() {
            if (!optionsTable) return;

            $.fn.dataTable.ext.search = [];

            var nameFilter   = document.getElementById('filterName').value.toLowerCase();
            var statusFilter = document.getElementById('filterStatus').value;

            $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
                var row = optionsData[dataIndex];
                if (!row) return true;

                if (nameFilter && row.option_value.toLowerCase().indexOf(nameFilter) === -1) return false;
                if (statusFilter === 'active'   && !row.is_active)  return false;
                if (statusFilter === 'inactive'  &&  row.is_active) return false;

                return true;
            });

            optionsTable.draw();
        }

        function clearFilters() {
            document.getElementById('filterName').value   = '';
            document.getElementById('filterStatus').value = '';

            if (optionsTable) {
                $.fn.dataTable.ext.search = [];
                optionsTable.columns().search('').draw();
            }
        }

        // modal helpers
        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-credit-card"></i> Add Payment Method';
            document.getElementById('optionForm').reset();
            document.getElementById('optionId').value = '';
            document.getElementById('formDisplayOrder').value = '0';
            document.getElementById('activeGroup').style.display = 'none';
            document.getElementById('optionModal').classList.add('active');
        }

        function editOption(opt) {
            isEditMode = true;
            document.getElementById('modalTitle').innerHTML   = '<i class="fas fa-edit"></i> Edit Payment Method';
            document.getElementById('optionId').value         = opt.option_id;
            document.getElementById('formOptionValue').value  = opt.option_value;
            document.getElementById('formDisplayOrder').value = opt.display_order;
            document.getElementById('formIsActive').value     = opt.is_active ? '1' : '0';
            document.getElementById('activeGroup').style.display = '';
            document.getElementById('optionModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('optionModal').classList.remove('active');
            document.getElementById('optionForm').reset();
        }

        document.getElementById('optionModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // form submit
        document.getElementById('optionForm').addEventListener('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var action   = isEditMode ? 'updateOption' : 'addOption';

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
                        setTimeout(function() { loadOptions(); }, 100);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Connection error: ' + error
                    });
                }
            });
        });

        // toggle active
        function toggleActive(optionId, isActive) {
            var formData = new FormData();
            formData.append('id', optionId);
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
                        setTimeout(function() { loadOptions(); }, 100);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                        setTimeout(function() { loadOptions(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Connection error: ' + error
                    });
                }
            });
        }

        // delete option
        function deleteOption(optionId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Payment Method?',
                text: 'This action cannot be undone',
                showCancelButton: true,
                confirmButtonColor: '#ea4335',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    var formData = new FormData();
                    formData.append('id', optionId);

                    $.ajax({
                        url: '?action=deleteOption',
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
                                setTimeout(function() { loadOptions(); }, 100);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Connection error: ' + error
                            });
                        }
                    });
                }
            });
        }
        // branding for prints
        var _brandName = <?php echo json_encode(getSiteBranding()['site_name']); ?>;
        var _brandLogo = <?php echo json_encode(getSiteBranding()['site_logo']); ?>;
        var _brandCopy = <?php echo json_encode(getSiteBranding()['copyright_text']); ?>;
        var _rptMethod = '';

        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(s));
            return d.innerHTML;
        }

        // payment method report — ledger style
        function showMethodReport(method) {
            _rptMethod = method;
            Swal.fire({
                title: '', html: '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#0074D9;"></i><p style="margin-top:10px;color:#666;">Loading report...</p></div>',
                width: 960, showConfirmButton: false, showCloseButton: true, padding: 0,
                customClass: { popup: 'swal-no-padding' },
                didOpen: function() { loadMethodReport(); }
            });
        }

        function loadMethodReport() {
            $.getJSON('?action=getMethodReport&method=' + encodeURIComponent(_rptMethod), function(r) {
                if (!r.success) { Swal.update({ html: '<p style="color:#dc3545;padding:20px;">Failed to load.</p>' }); return; }
                renderMethodReport(r.subs, r.payments, r.currency, r.total, r.total_paid);
            }).fail(function() { Swal.update({ html: '<p style="color:#dc3545;padding:20px;">Connection error.</p>' }); });
        }

        function renderMethodReport(subs, payments, cur, totAmt, totPaid) {
            var safeName = escapeHtml(_rptMethod);
            var totBalance = totAmt - totPaid;
            var balColor = totBalance > 0 ? '#dc3545' : '#28a745';

            var html = '';
            // header
            html += '<div style="background:linear-gradient(135deg,#001f3f 0%,#003366 100%);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;">';
            html += '<i class="fas fa-credit-card" style="font-size:20px;color:#0074D9;"></i>';
            html += '<div><div style="font-size:16px;font-weight:700;">' + safeName + '</div><div style="font-size:11px;opacity:.7;">Payment Method Report</div></div>';
            html += '</div>';

            // 3 stat cards
            html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-bottom:1px solid #e9ecef;">';
            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:#0074D9;">' + cur + ' ' + totAmt.toFixed(0) + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Total Amount</div></div>';
            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:#28a745;">' + cur + ' ' + totPaid.toFixed(0) + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Total Paid</div></div>';
            html += '<div style="padding:14px;text-align:center;"><div style="font-size:22px;font-weight:700;color:' + balColor + ';">' + cur + ' ' + totBalance.toFixed(0) + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Balance Due</div></div>';
            html += '</div>';

            // tabs
            html += '<div style="display:flex;border-bottom:2px solid #e9ecef;" id="mrTabs">';
            html += '<button onclick="switchMethodTab(\'subs\')" id="mrTabSubs" style="flex:1;padding:10px;border:none;background:#fff;cursor:pointer;font-size:13px;font-weight:600;color:#001f3f;border-bottom:2px solid #0074D9;margin-bottom:-2px;">Subscriptions (' + subs.length + ')</button>';
            html += '<button onclick="switchMethodTab(\'pays\')" id="mrTabPays" style="flex:1;padding:10px;border:none;background:#fff;cursor:pointer;font-size:13px;font-weight:600;color:#888;border-bottom:2px solid transparent;margin-bottom:-2px;">Payments (' + payments.length + ')</button>';
            html += '</div>';

            // subs tab
            html += '<div id="mrSubs" style="padding:14px;max-height:300px;overflow-y:auto;">';
            if (subs.length === 0) {
                html += '<div style="text-align:center;color:#888;padding:40px;"><i class="fas fa-inbox" style="font-size:36px;color:#ddd;display:block;margin-bottom:10px;"></i>No subscriptions</div>';
            } else {
                html += '<div class="about-table-wrapper" style="margin:0;border-radius:4px;overflow:hidden;border:1px solid #e0e0e0;">';
                html += '<table class="about-roles-table" style="font-size:12px;margin:0;">';
                html += '<thead><tr><th style="text-align:left;">Invoice</th><th>Customer</th><th>Product</th><th>Date</th><th style="text-align:right;">Amount</th><th style="text-align:right;">Paid</th><th style="text-align:right;">Balance</th><th>Status</th></tr></thead>';
                html += '<tbody>';
                var payColors = {'Paid':'#28a745','Unpaid':'#dc3545','Partial':'#e67e00','Refunded':'#0074D9'};
                subs.forEach(function(s) {
                    var bc = s.balance > 0 ? '#dc3545' : '#28a745';
                    var pyc = payColors[s.payment_status] || '#888';
                    html += '<tr>';
                    html += '<td style="text-align:left;font-weight:600;">' + escapeHtml(s.invoice_no) + '</td>';
                    html += '<td>' + escapeHtml(s.customer_name) + '</td>';
                    html += '<td>' + escapeHtml(s.product_name) + '</td>';
                    html += '<td>' + s.invoice_date + '</td>';
                    html += '<td style="text-align:right;">' + s.total_amount.toFixed(0) + '</td>';
                    html += '<td style="text-align:right;color:#28a745;font-weight:600;">' + s.paid_amount.toFixed(0) + '</td>';
                    html += '<td style="text-align:right;color:' + bc + ';font-weight:700;">' + s.balance.toFixed(0) + '</td>';
                    html += '<td><span class="role-badge" style="background:' + pyc + ';color:#fff;">' + escapeHtml(s.payment_status) + '</span></td>';
                    html += '</tr>';
                });
                // total row
                html += '<tr style="background:#f0f4f8;font-weight:700;border-top:2px solid #001f3f;">';
                html += '<td colspan="4" style="text-align:left;">TOTAL</td>';
                html += '<td style="text-align:right;">' + totAmt.toFixed(0) + '</td>';
                html += '<td style="text-align:right;color:#28a745;">' + totPaid.toFixed(0) + '</td>';
                html += '<td style="text-align:right;color:' + balColor + ';">' + totBalance.toFixed(0) + '</td>';
                html += '<td></td></tr>';
                html += '</tbody></table></div>';
            }
            html += '</div>';

            // payments tab
            html += '<div id="mrPays" style="padding:14px;max-height:300px;overflow-y:auto;display:none;">';
            if (payments.length === 0) {
                html += '<div style="text-align:center;color:#888;padding:40px;"><i class="fas fa-inbox" style="font-size:36px;color:#ddd;display:block;margin-bottom:10px;"></i>No payments recorded</div>';
            } else {
                html += '<div class="about-table-wrapper" style="margin:0;border-radius:4px;overflow:hidden;border:1px solid #e0e0e0;">';
                html += '<table class="about-roles-table" style="font-size:12px;margin:0;">';
                html += '<thead><tr><th>Date</th><th style="text-align:right;">Amount</th><th>Reference</th><th>Invoice</th><th>Customer</th><th>Notes</th><th>By</th></tr></thead>';
                html += '<tbody>';
                payments.forEach(function(p) {
                    html += '<tr>';
                    html += '<td>' + escapeHtml(p.payment_date) + '</td>';
                    html += '<td style="text-align:right;font-weight:700;color:#28a745;">' + p.amount.toFixed(0) + '</td>';
                    html += '<td>' + escapeHtml(p.reference_no || '-') + '</td>';
                    html += '<td style="font-weight:600;">' + escapeHtml(p.invoice_no || '#' + p.subscription_sl) + '</td>';
                    html += '<td>' + escapeHtml(p.customer_name) + '</td>';
                    html += '<td>' + escapeHtml(p.notes || '-') + '</td>';
                    html += '<td>' + escapeHtml(p.added_by_name || '-') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            }
            html += '</div>';

            // footer
            html += '<div style="padding:12px 20px;border-top:1px solid #e9ecef;background:#f8f9fa;display:flex;align-items:center;justify-content:space-between;">';
            html += '<span style="font-size:12px;color:#888;">Balance: <strong style="color:' + balColor + ';">' + cur + ' ' + totBalance.toFixed(0) + '</strong></span>';
            html += '<div style="display:flex;gap:8px;">';
            html += '<button onclick="thermalPrintMethodReport()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#e67e00;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:12px;font-weight:600;" title="Thermal / Receipt Print"><i class="fas fa-receipt"></i> Thermal</button>';
            html += '<button onclick="printMethodReport()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#001f3f;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:12px;font-weight:600;"><i class="fas fa-print"></i> A4 Print</button>';
            html += '</div></div>';

            Swal.update({ html: html });
        }

        function switchMethodTab(tab) {
            document.getElementById('mrSubs').style.display = tab === 'subs' ? '' : 'none';
            document.getElementById('mrPays').style.display = tab === 'pays' ? '' : 'none';
            var tabs = { subs: 'mrTabSubs', pays: 'mrTabPays' };
            Object.keys(tabs).forEach(function(k) {
                var el = document.getElementById(tabs[k]);
                el.style.color = k === tab ? '#001f3f' : '#888';
                el.style.borderBottomColor = k === tab ? '#0074D9' : 'transparent';
            });
        }

        // A4 print
        function printMethodReport() {
            var el = document.querySelector('.swal2-html-container');
            if (!el) return;
            var w = window.open('', '_blank', 'width=900,height=600');
            var s = '';
            s += '<!DOCTYPE html><html><head><title>Report - ' + escapeHtml(_rptMethod) + '</title>';
            s += '<style>';
            s += 'body{font-family:Arial,sans-serif;margin:20px;color:#333;}';
            s += '.header{display:flex;align-items:center;gap:14px;margin-bottom:15px;padding-bottom:12px;border-bottom:2px solid #001f3f;}';
            s += '.header img{width:50px;height:50px;border-radius:50%;object-fit:cover;}';
            s += '.header h1{font-size:18px;color:#001f3f;margin:0;}.header p{margin:0;font-size:12px;color:#666;}';
            s += '.method-name{font-size:15px;font-weight:700;color:#001f3f;margin:10px 0 4px;}';
            s += '.stats{display:flex;gap:30px;margin:8px 0 14px;font-size:13px;}';
            s += '.stats span{font-weight:700;}';
            s += 'h3{font-size:13px;color:#001f3f;margin:18px 0 6px;border-bottom:1px solid #ccc;padding-bottom:4px;}';
            s += 'table{width:100%;border-collapse:collapse;font-size:11px;}';
            s += 'th{background:#001f3f;color:#fff;padding:6px 8px;text-align:left;}';
            s += 'td{padding:5px 8px;border-bottom:1px solid #e0e0e0;}';
            s += 'tr:nth-child(even){background:#f8f9fa;}';
            s += '.footer{margin-top:20px;padding-top:10px;border-top:1px solid #ccc;font-size:10px;color:#888;text-align:center;}';
            s += '@media print{body{margin:10px;}}';
            s += '</style></head><body>';

            s += '<div class="header">';
            s += '<img src="' + escapeHtml(_brandLogo) + '" onerror="this.style.display=\'none\'">';
            s += '<div><h1>' + escapeHtml(_brandName) + '</h1><p>Payment Method Report</p></div>';
            s += '</div>';

            s += '<div class="method-name">' + escapeHtml(_rptMethod) + '</div>';

            var statsEl = el.querySelector('[style*="grid-template-columns: repeat(3"]');
            if (statsEl) s += statsEl.outerHTML;

            var subsT = document.getElementById('mrSubs');
            if (subsT) { var t = subsT.querySelector('.about-roles-table'); if (t) { s += '<h3>Subscriptions</h3>' + t.outerHTML; } }

            var paysT = document.getElementById('mrPays');
            if (paysT) { var t2 = paysT.querySelector('.about-roles-table'); if (t2) { s += '<h3>Payments</h3>' + t2.outerHTML; } }

            s += '<div class="footer">' + _brandCopy + ' &mdash; Generated: ' + new Date().toLocaleDateString() + '</div>';
            s += '</body></html>';

            w.document.write(s);
            w.document.close(); w.focus();
            setTimeout(function() { w.print(); }, 300);
        }

        // thermal print
        function thermalPrintMethodReport() {
            var el = document.querySelector('.swal2-html-container');
            if (!el) return;
            var w = window.open('', '_blank', 'width=350,height=600');
            var s = '';
            s += '<!DOCTYPE html><html><head><title>Receipt</title>';
            s += '<style>';
            s += '@page{size:80mm auto;margin:0;}';
            s += 'body{font-family:"Courier New",monospace;width:72mm;margin:4mm auto;color:#000;font-size:11px;line-height:1.4;}';
            s += '.center{text-align:center;}';
            s += '.logo{width:40px;height:40px;border-radius:50%;margin:0 auto 4px;display:block;}';
            s += '.brand{font-size:14px;font-weight:700;margin:2px 0;}';
            s += '.sub{font-size:9px;color:#666;}';
            s += '.line{border-top:1px dashed #000;margin:6px 0;}';
            s += '.bold{font-weight:700;}';
            s += '.row{display:flex;justify-content:space-between;}';
            s += 'table{width:100%;border-collapse:collapse;font-size:10px;margin:4px 0;}';
            s += 'th{text-align:left;font-size:9px;border-bottom:1px solid #000;padding:2px 0;}';
            s += 'td{padding:2px 0;border-bottom:1px dotted #ccc;}';
            s += 'td:last-child,th:last-child{text-align:right;}';
            s += '.footer{text-align:center;font-size:8px;color:#666;margin-top:8px;}';
            s += '@media print{body{margin:0 auto;}}';
            s += '</style></head><body>';

            s += '<div class="center">';
            s += '<img src="' + escapeHtml(_brandLogo) + '" class="logo" onerror="this.style.display=\'none\'">';
            s += '<div class="brand">' + escapeHtml(_brandName) + '</div>';
            s += '<div class="sub">PAYMENT METHOD REPORT</div>';
            s += '</div>';
            s += '<div class="line"></div>';

            s += '<div class="bold">' + escapeHtml(_rptMethod) + '</div>';
            s += '<div class="sub">Date: ' + new Date().toLocaleDateString() + '</div>';
            s += '<div class="line"></div>';

            // subs
            var subsT = document.getElementById('mrSubs');
            if (subsT) {
                var rows = subsT.querySelectorAll('.about-roles-table tbody tr');
                if (rows.length > 0) {
                    s += '<div class="bold" style="font-size:10px;margin-bottom:2px;">SUBSCRIPTIONS</div>';
                    s += '<table><thead><tr><th>Invoice</th><th>Amount</th><th>Paid</th><th>Bal</th></tr></thead><tbody>';
                    rows.forEach(function(tr) {
                        var tds = tr.querySelectorAll('td');
                        if (tds.length < 7) return;
                        var inv = tds[0].textContent.trim();
                        var amt = tds[4].textContent.trim();
                        var paid = tds[5].textContent.trim();
                        var bal = tds[6].textContent.trim();
                        if (inv === 'TOTAL') {
                            s += '<tr style="border-top:1px solid #000;font-weight:700;"><td>TOTAL</td><td>' + amt + '</td><td>' + paid + '</td><td>' + bal + '</td></tr>';
                        } else {
                            s += '<tr><td>' + escapeHtml(inv) + '</td><td>' + amt + '</td><td>' + paid + '</td><td>' + bal + '</td></tr>';
                        }
                    });
                    s += '</tbody></table>';
                }
            }

            s += '<div class="line"></div>';

            // payments
            var paysT = document.getElementById('mrPays');
            if (paysT) {
                var pRows = paysT.querySelectorAll('.about-roles-table tbody tr');
                if (pRows.length > 0) {
                    s += '<div class="bold" style="font-size:10px;margin-bottom:2px;">PAYMENTS</div>';
                    s += '<table><thead><tr><th>Date</th><th>Ref</th><th>Amt</th></tr></thead><tbody>';
                    pRows.forEach(function(tr) {
                        var tds = tr.querySelectorAll('td');
                        if (tds.length < 3) return;
                        s += '<tr><td>' + tds[0].textContent.trim() + '</td><td>' + tds[2].textContent.trim() + '</td><td>' + tds[1].textContent.trim() + '</td></tr>';
                    });
                    s += '</tbody></table>';
                }
            }

            s += '<div class="line"></div>';

            // summary
            var statsEl = el.querySelector('[style*="grid-template-columns: repeat(3"]');
            if (statsEl) {
                var statDivs = statsEl.querySelectorAll('[style*="text-align:center"]');
                if (statDivs.length >= 3) {
                    var total = statDivs[0].querySelector('div').textContent.trim();
                    var paid = statDivs[1].querySelector('div').textContent.trim();
                    var balance = statDivs[2].querySelector('div').textContent.trim();
                    s += '<div class="row"><span>Total Amount:</span><span class="bold">' + total + '</span></div>';
                    s += '<div class="row"><span>Total Paid:</span><span class="bold">' + paid + '</span></div>';
                    s += '<div class="line" style="border-top:1px solid #000;"></div>';
                    s += '<div class="row" style="font-size:13px;"><span class="bold">BALANCE DUE:</span><span class="bold">' + balance + '</span></div>';
                }
            }

            s += '<div class="line"></div>';
            s += '<div class="footer">' + _brandCopy + '</div>';
            s += '</body></html>';

            w.document.write(s);
            w.document.close(); w.focus();
            setTimeout(function() { w.print(); }, 300);
        }
    </script>
</body>
</html>
