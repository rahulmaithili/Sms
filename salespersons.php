<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * Sales Persons Management Page
 * Admin-only CRUD for managing sales persons
 */

require_once 'config.php';

// Admin-only access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Check session timeout
if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

$username  = $_SESSION['username'];
$role      = $_SESSION['role'];
$user_id   = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'salespersons';

// ============================================================
// AJAX Request Handler
// ============================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            // --------------------------------------------------
            // GET: List all sales persons
            // --------------------------------------------------
            case 'getSalesPersons':
                $conn  = getDBConnection();
                $stmt  = $conn->prepare(
                    "SELECT salesperson_id, name, email, phone, department,
                            commission_rate, is_active, created_at
                     FROM salespersons
                     ORDER BY name ASC"
                );
                $stmt->execute();
                $result = $stmt->get_result();

                $records = [];
                while ($row = $result->fetch_assoc()) {
                    $records[] = [
                        'salesperson_id'  => (int)$row['salesperson_id'],
                        'name'            => $row['name'],
                        'email'           => $row['email'],
                        'phone'           => $row['phone'],
                        'department'      => $row['department'],
                        'commission_rate' => (float)$row['commission_rate'],
                        'is_active'       => (bool)$row['is_active'],
                        'created_at'      => date('M d, Y', strtotime($row['created_at']))
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $records]);
                exit();

            // --------------------------------------------------
            // POST: Add a new sales person
            // --------------------------------------------------
            case 'addSalesPerson':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $name            = isset($_POST['name'])            ? trim($_POST['name'])            : '';
                $email           = isset($_POST['email'])           ? trim($_POST['email'])           : '';
                $phone           = isset($_POST['phone'])           ? trim($_POST['phone'])           : '';
                $department      = isset($_POST['department'])      ? trim($_POST['department'])      : '';
                $commission_rate = isset($_POST['commission_rate']) ? (float)$_POST['commission_rate'] : 0.0;

                // Validation
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Name is required']);
                    exit();
                }

                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    exit();
                }

                if ($commission_rate < 0 || $commission_rate > 100) {
                    echo json_encode(['success' => false, 'message' => 'Commission rate must be between 0 and 100']);
                    exit();
                }

                // Use nullable values for optional fields
                $emailVal  = !empty($email)      ? $email      : null;
                $phoneVal  = !empty($phone)      ? $phone      : null;
                $deptVal   = !empty($department) ? $department : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "INSERT INTO salespersons (name, email, phone, department, commission_rate, is_active)
                     VALUES (?, ?, ?, ?, ?, 1)"
                );
                $stmt->bind_param("ssssd", $name, $emailVal, $phoneVal, $deptVal, $commission_rate);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'SalesPerson Created', "Created sales person: $name");
                    try {
                        createNotificationForAdmins(
                            'Sales Person Created',
                            'Admin "' . htmlspecialchars($username) . '" added sales person "' . htmlspecialchars($name) . '".',
                            'info',
                            'salespersons.php'
                        );
                    } catch (Exception $e) {}

                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Sales person added successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to add sales person']);
                }
                exit();

            // --------------------------------------------------
            // POST: Update an existing sales person
            // --------------------------------------------------
            case 'updateSalesPerson':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $salesperson_id  = isset($_POST['salesperson_id'])  ? intval($_POST['salesperson_id'])  : 0;
                $name            = isset($_POST['name'])            ? trim($_POST['name'])            : '';
                $email           = isset($_POST['email'])           ? trim($_POST['email'])           : '';
                $phone           = isset($_POST['phone'])           ? trim($_POST['phone'])           : '';
                $department      = isset($_POST['department'])      ? trim($_POST['department'])      : '';
                $commission_rate = isset($_POST['commission_rate']) ? (float)$_POST['commission_rate'] : 0.0;
                $is_active       = isset($_POST['is_active'])       ? intval($_POST['is_active'])       : 1;

                // Validation
                if ($salesperson_id <= 0 || empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid input — name is required']);
                    exit();
                }

                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    exit();
                }

                if ($commission_rate < 0 || $commission_rate > 100) {
                    echo json_encode(['success' => false, 'message' => 'Commission rate must be between 0 and 100']);
                    exit();
                }

                $emailVal = !empty($email)      ? $email      : null;
                $phoneVal = !empty($phone)      ? $phone      : null;
                $deptVal  = !empty($department) ? $department : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "UPDATE salespersons
                     SET name = ?, email = ?, phone = ?, department = ?,
                         commission_rate = ?, is_active = ?
                     WHERE salesperson_id = ?"
                );
                $stmt->bind_param("ssssdii", $name, $emailVal, $phoneVal, $deptVal, $commission_rate, $is_active, $salesperson_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'SalesPerson Updated', "Updated sales person: $name (ID: $salesperson_id)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Sales person updated successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update sales person']);
                }
                exit();

            // --------------------------------------------------
            // POST: Toggle active status
            // --------------------------------------------------
            case 'toggleActive':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $salesperson_id = isset($_POST['id'])        ? intval($_POST['id'])        : 0;
                $is_active      = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

                if ($salesperson_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid sales person ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE salespersons SET is_active = ? WHERE salesperson_id = ?");
                $stmt->bind_param("ii", $is_active, $salesperson_id);

                if ($stmt->execute()) {
                    $action_label = $is_active ? 'SalesPerson Activated' : 'SalesPerson Deactivated';
                    logActivity($user_id, $username, $action_label, "Changed active status for sales person ID $salesperson_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_active ? 'Sales person activated' : 'Sales person deactivated']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                exit();

            // --------------------------------------------------
            // POST: Delete a sales person
            // --------------------------------------------------
            case 'deleteSalesPerson':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $salesperson_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                if ($salesperson_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid sales person ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch name before deleting for logging
                $stmt = $conn->prepare("SELECT name FROM salespersons WHERE salesperson_id = ?");
                $stmt->bind_param("i", $salesperson_id);
                $stmt->execute();
                $result      = $stmt->get_result();
                $deletedName = '';
                if ($result->num_rows > 0) {
                    $deletedName = $result->fetch_assoc()['name'];
                }
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM salespersons WHERE salesperson_id = ?");
                $stmt->bind_param("i", $salesperson_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'SalesPerson Deleted', "Deleted sales person: $deletedName (ID: $salesperson_id)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Sales person deleted successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete sales person']);
                }
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("salespersons.php error: " . $e->getMessage());
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
    <title>Sales Persons - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <style>
        /* Toggle Switch */
        .toggle { appearance: none; width: 44px; height: 24px; border-radius: 24px; background: #ccc; position: relative; cursor: pointer; transition: background .3s; border: none; outline: none; vertical-align: middle; }
        .toggle:checked { background: #0074D9; }
        .toggle::before { content: ""; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform .3s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
        .toggle:checked::before { transform: translateX(20px); }
        .swal-no-padding { padding: 0 !important; }
        .swal-no-padding .swal2-html-container { padding: 0 !important; margin: 0 !important; }
        .swal-no-padding .swal2-close { color: #fff !important; opacity: .8; z-index: 10; }
        .swal-no-padding .swal2-close:hover { opacity: 1; }
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
                <span>Sales Persons</span>
            </div>

            <div class="header">
                <h1><i class="fas fa-user-tie"></i> Sales Persons</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Sales Persons</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadSalesPersons()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add SalesPerson
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
                            <label><i class="fas fa-building"></i> Department</label>
                            <select id="filterDepartment" class="filter-input">
                                <option value="">All Departments</option>
                            </select>
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
                    <table id="salespersonsTable" class="display table-full-width"></table>
                </div>
            </div>

        </div><!-- /.main-content -->
    </div><!-- /.app-container -->

    <!-- ============================================================ -->
    <!-- Sales Person Modal                                           -->
    <!-- ============================================================ -->
    <div class="modal-overlay" id="salespersonModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add Sales Person</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="salespersonForm">
                    <input type="hidden" id="salespersonId" name="salesperson_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Name *</label>
                            <input type="text" id="formName" name="name" required placeholder="Full name">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="formEmail" name="email" placeholder="email@example.com">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone</label>
                            <input type="text" id="formPhone" name="phone" placeholder="+1 234 567 8900">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Department</label>
                            <input type="text" id="formDepartment" name="department" placeholder="e.g. North Region">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-percent"></i> Commission Rate (%)</label>
                            <input type="number" id="formCommissionRate" name="commission_rate"
                                   step="0.01" min="0" max="100" value="0"
                                   placeholder="0.00">
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        let salespersonsTable;
        let isEditMode = false;
        let salespersonsData = [];

        $(document).ready(function() {
            loadSalesPersons();
        });

        // --------------------------------------------------------
        // Load all sales persons via AJAX
        // --------------------------------------------------------
        function loadSalesPersons() {
            $.ajax({
                url: '?action=getSalesPersons',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        salespersonsData = response.data;
                        $('#filtersSection').show();
                        populateDepartmentFilter(response.data);
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to load sales persons'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Could not connect to server. Please check the console for details.'
                    });
                }
            });
        }

        // --------------------------------------------------------
        // Populate department filter from loaded data
        // --------------------------------------------------------
        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(s));
            return d.innerHTML;
        }

        function populateDepartmentFilter(data) {
            const depts = [...new Set(data.map(r => r.department).filter(d => d))];
            const sel = document.getElementById('filterDepartment');
            sel.innerHTML = '<option value="">All Departments</option>';
            depts.sort().forEach(function(d) {
                var opt = document.createElement('option');
                opt.value = d;
                opt.textContent = d;
                sel.appendChild(opt);
            });
        }

        // --------------------------------------------------------
        // Initialise / re-initialise DataTable
        // --------------------------------------------------------
        function initializeDataTable(data) {
            if (salespersonsTable) {
                salespersonsTable.destroy();
                $('#salespersonsTable').empty();
            }

            setTimeout(function() {
                salespersonsTable = $('#salespersonsTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        { data: 'salesperson_id', title: 'ID' },
                        { data: 'name',           title: 'Name' },
                        { data: 'email',          title: 'Email',      defaultContent: '-' },
                        { data: 'phone',          title: 'Phone',      defaultContent: '-' },
                        { data: 'department',     title: 'Department', defaultContent: '-' },
                        {
                            data: 'commission_rate',
                            title: 'Commission',
                            render: function(data) {
                                return data + '%';
                            }
                        },
                        {
                            data: 'is_active',
                            title: 'Active',
                            render: function(data, type, row) {
                                const checked = data ? 'checked' : '';
                                return '<input type="checkbox" ' + checked + ' class="toggle" onchange="toggleActive(' + row.salesperson_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        { data: 'created_at', title: 'Created' },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                return '<button class="action-icon" onclick="viewSalespersonSubs(' + row.salesperson_id + ', \'' + (row.name || '').replace(/\'/g, "\\'") + '\')" title="View Subscriptions" style="color:#0074D9;"><i class="fas fa-eye"></i></button> ' +
                                    '<button class="action-icon" onclick="viewSpProfit(' + row.salesperson_id + ', \'' + (row.name || '').replace(/\'/g, "\\'") + '\')" title="Margin & Profit" style="color:#28a745;"><i class="fas fa-chart-line"></i></button> ' +
                                    '<button class="action-icon edit-icon" ' +
                                    'onclick=\'editSalesPerson(' + JSON.stringify(row).replace(/'/g, "\\'") + ')\'>' +
                                    '<i class="fas fa-edit"></i></button> ' +
                                    '<button class="action-icon delete-icon" ' +
                                    'onclick="deleteSalesPerson(' + row.salesperson_id + ')">' +
                                    '<i class="fas fa-trash"></i></button>';
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
                    order: [[0, 'asc']]
                });

                // Apply filters on change
                $('#filterDepartment, #filterStatus').on('change', function() {
                    applyFilters();
                });

            }, 100);
        }

        // --------------------------------------------------------
        // Filter logic
        // --------------------------------------------------------
        function applyFilters() {
            if (!salespersonsTable) return;

            $.fn.dataTable.ext.search = [];

            const department = document.getElementById('filterDepartment').value;
            const status     = document.getElementById('filterStatus').value;

            $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
                const row = salespersonsData[dataIndex];
                if (!row) return true;

                // Department filter
                if (department && row.department !== department) return false;

                // Status filter
                if (status === 'active'   && !row.is_active) return false;
                if (status === 'inactive' && row.is_active)  return false;

                return true;
            });

            salespersonsTable.draw();
        }

        function clearFilters() {
            document.getElementById('filterDepartment').value = '';
            document.getElementById('filterStatus').value     = '';

            if (salespersonsTable) {
                $.fn.dataTable.ext.search = [];
                salespersonsTable.columns().search('').draw();
            }
        }

        // --------------------------------------------------------
        // Modal: Open Add
        // --------------------------------------------------------
        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add Sales Person';
            document.getElementById('salespersonForm').reset();
            document.getElementById('salespersonId').value     = '';
            document.getElementById('formCommissionRate').value = '0';
            document.getElementById('activeGroup').style.display = 'none';
            document.getElementById('salespersonModal').classList.add('active');
        }

        // --------------------------------------------------------
        // Modal: Open Edit
        // --------------------------------------------------------
        function editSalesPerson(sp) {
            isEditMode = true;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Sales Person';
            document.getElementById('salespersonId').value      = sp.salesperson_id;
            document.getElementById('formName').value           = sp.name;
            document.getElementById('formEmail').value          = sp.email        || '';
            document.getElementById('formPhone').value          = sp.phone        || '';
            document.getElementById('formDepartment').value     = sp.department   || '';
            document.getElementById('formCommissionRate').value = sp.commission_rate;
            document.getElementById('formIsActive').value       = sp.is_active ? '1' : '0';
            document.getElementById('activeGroup').style.display = '';
            document.getElementById('salespersonModal').classList.add('active');
        }

        // --------------------------------------------------------
        // Modal: Close
        // --------------------------------------------------------
        function closeModal() {
            document.getElementById('salespersonModal').classList.remove('active');
            document.getElementById('salespersonForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('salespersonModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // --------------------------------------------------------
        // Form submit — Add or Update
        // --------------------------------------------------------
        document.getElementById('salespersonForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const action   = isEditMode ? 'updateSalesPerson' : 'addSalesPerson';

            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
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
                        setTimeout(function() { loadSalesPersons(); }, 100);
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

        // --------------------------------------------------------
        // Toggle active status inline
        // --------------------------------------------------------
        function toggleActive(id, isActive) {
            const formData = new FormData();
            formData.append('id', id);
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
                        setTimeout(function() { loadSalesPersons(); }, 100);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                        setTimeout(function() { loadSalesPersons(); }, 100);
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

        // --------------------------------------------------------
        // Delete sales person with SweetAlert2 confirmation
        // --------------------------------------------------------
        function deleteSalesPerson(id) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Sales Person?',
                text: 'This action cannot be undone.',
                showCancelButton: true,
                confirmButtonColor: '#ea4335',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', id);

                    $.ajax({
                        url: '?action=deleteSalesPerson',
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
                                setTimeout(function() { loadSalesPersons(); }, 100);
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

        // --------------------------------------------------------
        // ── Margin & Profit Popup ────────────────────────────────────────────
        function viewSpProfit(spId, spName) {
            // get commission rate from loaded data
            var spRow = salespersonsData.find(function(x) { return x.salesperson_id == spId; });
            var commRate = spRow ? parseFloat(spRow.commission_rate) || 0 : 0;

            Swal.fire({
                title: '',
                html: '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#0074D9;"></i><p style="margin-top:10px;color:#666;">Loading...</p></div>',
                width: 950,
                showConfirmButton: false,
                showCloseButton: true,
                padding: 0,
                customClass: { popup: 'swal-no-padding' },
                didOpen: function() {
                    $.ajax({
                        url: 'subscriptions.php?action=getSubscriptions',
                        method: 'GET',
                        dataType: 'json',
                        success: function(r) {
                            if (!r.success) { Swal.update({ html: '<p style="color:#dc3545;padding:20px;">Failed to load.</p>' }); return; }
                            var subs = r.data.filter(function(s) { return s.salesperson_id == spId; });
                            var cur = r.currency || 'INR';
                            var safeName = escapeHtml(spName);

                            // calc totals
                            var totSell = 0, totBuy = 0, totTax = 0, totProfit = 0, totComm = 0;
                            subs.forEach(function(s) {
                                var sell = parseFloat(s.selling_price) || 0;
                                var buy  = parseFloat(s.purchase_price) || 0;
                                var tax  = parseFloat(s.tax_amount) || 0;
                                var prof = (sell - tax) - buy;
                                totSell += sell;
                                totBuy  += buy;
                                totTax  += tax;
                                totProfit += prof;
                                totComm += (prof > 0 ? prof * commRate / 100 : 0);
                            });
                            var marginPct = totSell > 0 ? ((totProfit / totSell) * 100).toFixed(1) : '0.0';
                            var profitColor = totProfit >= 0 ? '#28a745' : '#dc3545';
                            var netAfterComm = totProfit - totComm;

                            var html = '';
                            // header
                            html += '<div style="background:linear-gradient(135deg,#001f3f 0%,#003366 100%);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;">';
                            html += '<i class="fas fa-chart-line" style="font-size:20px;color:#28a745;"></i>';
                            html += '<div><div style="font-size:16px;font-weight:700;">' + safeName + ' <span style="font-size:12px;font-weight:400;opacity:.7;">(' + commRate + '% commission)</span></div><div style="font-size:11px;opacity:.7;">Margin &amp; Profit Analysis</div></div>';
                            html += '</div>';

                            // stats row
                            html += '<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:0;border-bottom:1px solid #e9ecef;">';
                            html += '<div style="padding:12px 6px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:18px;font-weight:700;color:#001f3f;">' + subs.length + '</div><div style="font-size:10px;color:#888;margin-top:2px;">Deals</div></div>';
                            html += '<div style="padding:12px 6px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:18px;font-weight:700;color:#0074D9;">' + cur + ' ' + totSell.toFixed(0) + '</div><div style="font-size:10px;color:#888;margin-top:2px;">Selling</div></div>';
                            html += '<div style="padding:12px 6px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:18px;font-weight:700;color:#e67e00;">' + cur + ' ' + totBuy.toFixed(0) + '</div><div style="font-size:10px;color:#888;margin-top:2px;">Purchase</div></div>';
                            html += '<div style="padding:12px 6px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:18px;font-weight:700;color:' + profitColor + ';">' + cur + ' ' + totProfit.toFixed(0) + '</div><div style="font-size:10px;color:#888;margin-top:2px;">Profit</div></div>';
                            html += '<div style="padding:12px 6px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:18px;font-weight:700;color:#7c3aed;">' + cur + ' ' + totComm.toFixed(0) + '</div><div style="font-size:10px;color:#888;margin-top:2px;">Commission</div></div>';
                            html += '<div style="padding:12px 6px;text-align:center;"><div style="font-size:18px;font-weight:700;color:#001f3f;">' + cur + ' ' + netAfterComm.toFixed(0) + '</div><div style="font-size:10px;color:#888;margin-top:2px;">Net (After Comm.)</div></div>';
                            html += '</div>';

                            if (subs.length === 0) {
                                html += '<div style="padding:50px 20px;text-align:center;color:#888;"><i class="fas fa-inbox" style="font-size:40px;color:#ddd;display:block;margin-bottom:14px;"></i>No subscriptions assigned</div>';
                            } else {
                                html += '<div style="padding:16px;max-height:340px;overflow-y:auto;">';
                                html += '<div class="about-table-wrapper" style="margin:0;border-radius:4px;overflow:hidden;border:1px solid #e0e0e0;">';
                                html += '<table class="about-roles-table" style="font-size:11px;margin:0;">';
                                html += '<thead><tr><th style="text-align:left;">Invoice</th><th>Customer</th><th style="text-align:right;">Selling</th><th style="text-align:right;">Purchase</th><th style="text-align:right;">Profit</th><th style="text-align:right;">Commission</th><th style="text-align:right;">Net</th><th>Payment</th></tr></thead>';
                                html += '<tbody>';

                                var payColors = { 'Paid': '#28a745', 'Unpaid': '#dc3545', 'Partial': '#e67e00', 'Refunded': '#0074D9' };
                                subs.forEach(function(s) {
                                    var sell = parseFloat(s.selling_price) || 0;
                                    var buy  = parseFloat(s.purchase_price) || 0;
                                    var tax  = parseFloat(s.tax_amount) || 0;
                                    var prof = (sell - tax) - buy;
                                    var comm = prof > 0 ? prof * commRate / 100 : 0;
                                    var net  = prof - comm;
                                    var pc   = prof >= 0 ? '#28a745' : '#dc3545';
                                    var pyc  = payColors[s.payment_status] || '#888';

                                    html += '<tr>';
                                    html += '<td style="text-align:left;font-weight:600;">' + escapeHtml(s.invoice_no || '-') + '</td>';
                                    html += '<td>' + escapeHtml(s.customer_name || '-') + '</td>';
                                    html += '<td style="text-align:right;">' + sell.toFixed(0) + '</td>';
                                    html += '<td style="text-align:right;">' + buy.toFixed(0) + '</td>';
                                    html += '<td style="text-align:right;font-weight:700;color:' + pc + ';">' + prof.toFixed(0) + '</td>';
                                    html += '<td style="text-align:right;color:#7c3aed;font-weight:600;">' + comm.toFixed(0) + '</td>';
                                    html += '<td style="text-align:right;font-weight:700;">' + net.toFixed(0) + '</td>';
                                    html += '<td><span class="role-badge" style="background:' + pyc + ';color:#fff;">' + escapeHtml(s.payment_status || 'Unknown') + '</span></td>';
                                    html += '</tr>';
                                });

                                // totals row
                                html += '<tr style="background:#f0f4f8;font-weight:700;border-top:2px solid #001f3f;">';
                                html += '<td style="text-align:left;" colspan="2">TOTAL</td>';
                                html += '<td style="text-align:right;">' + totSell.toFixed(0) + '</td>';
                                html += '<td style="text-align:right;">' + totBuy.toFixed(0) + '</td>';
                                html += '<td style="text-align:right;color:' + profitColor + ';">' + totProfit.toFixed(0) + '</td>';
                                html += '<td style="text-align:right;color:#7c3aed;">' + totComm.toFixed(0) + '</td>';
                                html += '<td style="text-align:right;">' + netAfterComm.toFixed(0) + '</td>';
                                html += '<td></td>';
                                html += '</tr>';

                                html += '</tbody></table></div></div>';
                            }

                            // footer with print
                            html += '<div style="padding:12px 20px;border-top:1px solid #e9ecef;background:#f8f9fa;display:flex;align-items:center;justify-content:space-between;">';
                            html += '<span style="font-size:12px;color:#888;">Pay to <strong>' + safeName + '</strong>: <strong style="color:#7c3aed;">' + cur + ' ' + totComm.toFixed(0) + '</strong> &nbsp;|&nbsp; You keep: <strong style="color:#001f3f;">' + cur + ' ' + netAfterComm.toFixed(0) + '</strong></span>';
                            html += '<button onclick="printSpProfit()" style="display:inline-flex;align-items:center;gap:8px;padding:8px 20px;background:#001f3f;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:13px;font-weight:600;" onmouseover="this.style.opacity=\'0.85\'" onmouseout="this.style.opacity=\'1\'"><i class="fas fa-print"></i> Print</button>';
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

        function printSpProfit() {
            var el = document.querySelector('.swal2-html-container');
            if (!el) return;
            var w = window.open('', '_blank', 'width=900,height=600');
            w.document.write('<!DOCTYPE html><html><head><title>Profit Report</title><style>body{font-family:Arial,sans-serif;margin:20px;color:#333;}h2{color:#001f3f;margin-bottom:5px;}table{width:100%;border-collapse:collapse;font-size:12px;}th{background:#001f3f;color:#fff;padding:8px 10px;text-align:left;}td{padding:6px 10px;border-bottom:1px solid #e0e0e0;}tr:nth-child(even){background:#f8f9fa;}.stats{display:flex;gap:20px;margin:10px 0 15px;font-size:13px;}@media print{body{margin:10px;}}</style></head><body>');
            // grab stats + table from popup
            var statsEl = el.querySelector('[style*="grid-template-columns"]');
            var tableEl = el.querySelector('.about-roles-table');
            if (statsEl) w.document.write(statsEl.outerHTML);
            if (tableEl) w.document.write(tableEl.outerHTML);
            w.document.write('<p style="color:#666;font-size:11px;margin-top:15px;">Generated: ' + new Date().toLocaleDateString() + '</p>');
            w.document.write('</body></html>');
            w.document.close();
            w.focus();
            setTimeout(function() { w.print(); }, 300);
        }

        // ── View Subscriptions Popup ─────────────────────────────────────────
        // Pagination + Print state
        var _spPopupSubs = [];
        var _spPopupPage = 1;
        var _spPopupPerPage = 10;
        var _spPopupCurrency = 'INR';
        var _spPopupName = '';

        function viewSalespersonSubs(spId, spName) {
            _spPopupName = spName;
            _spPopupPage = 1;
            Swal.fire({
                title: '',
                html: '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#0074D9;"></i><p style="margin-top:10px;color:#666;">Loading...</p></div>',
                width: 880,
                showConfirmButton: false,
                showCloseButton: true,
                padding: 0,
                customClass: { popup: 'swal-no-padding' },
                didOpen: function() {
                    $.ajax({
                        url: 'subscriptions.php?action=getSubscriptions',
                        method: 'GET',
                        dataType: 'json',
                        success: function(r) {
                            if (!r.success) { Swal.update({ html: '<p style="color:#dc3545;padding:20px;">Failed to load data.</p>' }); return; }
                            _spPopupSubs = r.data.filter(function(s) { return s.salesperson_id == spId; });
                            _spPopupCurrency = r.currency || 'INR';
                            renderSpPopup();
                        },
                        error: function() {
                            Swal.update({ html: '<p style="color:#dc3545;padding:20px;">Connection error.</p>' });
                        }
                    });
                }
            });
        }

        function renderSpPopup() {
            var subs = _spPopupSubs;
            var currency = _spPopupCurrency;
            var totalRev = 0, paidCount = 0, unpaidCount = 0;
            subs.forEach(function(s) {
                totalRev += parseFloat(s.total_amount) || 0;
                if (s.payment_status === 'Paid') paidCount++;
                else if (s.payment_status === 'Unpaid') unpaidCount++;
            });

            var totalPages = Math.ceil(subs.length / _spPopupPerPage);
            if (_spPopupPage > totalPages) _spPopupPage = totalPages || 1;
            var start = (_spPopupPage - 1) * _spPopupPerPage;
            var pageItems = subs.slice(start, start + _spPopupPerPage);

            var html = '';

            // Branded header (no print button here — moved to bottom)
            html += '<div style="background:linear-gradient(135deg,var(--navy-primary,#001f3f) 0%,var(--navy-light,#003366) 100%);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;">';
            html += '<i class="fas fa-user-tie" style="font-size:20px;color:var(--navy-accent,#0074D9);"></i>';
            html += '<div><div style="font-size:16px;font-weight:700;">' + escapeHtml(_spPopupName) + '</div><div style="font-size:11px;opacity:.7;">Subscription Overview</div></div>';
            html += '</div>';

            // Stats row
            html += '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-bottom:1px solid #e9ecef;">';
            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:var(--navy-primary,#001f3f);">' + subs.length + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Total</div></div>';
            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:var(--navy-accent,#0074D9);">' + currency + ' ' + totalRev.toFixed(0) + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Revenue</div></div>';
            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:#28a745;">' + paidCount + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Paid</div></div>';
            html += '<div style="padding:14px;text-align:center;"><div style="font-size:22px;font-weight:700;color:#dc3545;">' + unpaidCount + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Unpaid</div></div>';
            html += '</div>';

            if (subs.length === 0) {
                html += '<div style="padding:50px 20px;text-align:center;color:#888;"><i class="fas fa-inbox" style="font-size:40px;color:#ddd;display:block;margin-bottom:14px;"></i>No subscriptions assigned to this salesperson</div>';
            } else {
                // Table with padding on all 4 sides
                html += '<div style="padding:20px;" id="spPopupTableWrap">';
                html += '<div class="about-table-wrapper" style="margin:0;border-radius:4px;overflow:hidden;border:1px solid #e0e0e0;">';
                html += '<table class="about-roles-table" style="font-size:13px;margin:0;">';
                html += '<thead><tr><th style="text-align:left;">Invoice</th><th>Customer</th><th>Status</th><th>Payment</th><th style="text-align:right;">Amount</th></tr></thead>';
                html += '<tbody>';
                var statusColors = { 'Active': '#28a745', 'Expired': '#dc3545', 'Expiring Soon': '#e67e00', 'Expiring Today': '#e65100' };
                var payColors = { 'Paid': '#28a745', 'Unpaid': '#dc3545', 'Partial': '#e67e00', 'Refunded': '#0074D9' };
                pageItems.forEach(function(s) {
                    var sc = statusColors[s.status_label] || '#888';
                    var pc = payColors[s.payment_status] || '#888';
                    html += '<tr>';
                    html += '<td style="text-align:left;font-weight:600;">' + escapeHtml(s.invoice_no) + '</td>';
                    html += '<td>' + escapeHtml(s.customer_name) + '</td>';
                    html += '<td><span class="role-badge" style="background:' + sc + ';color:#fff;">' + s.status_label + '</span></td>';
                    html += '<td><span class="role-badge" style="background:' + pc + ';color:#fff;">' + s.payment_status + '</span></td>';
                    html += '<td style="text-align:right;font-weight:600;">' + currency + ' ' + parseFloat(s.total_amount).toFixed(0) + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';

                // Pagination
                if (totalPages > 1) {
                    html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;font-size:12px;color:#666;">';
                    html += '<span>Showing ' + (start + 1) + '–' + Math.min(start + _spPopupPerPage, subs.length) + ' of ' + subs.length + '</span>';
                    html += '<div style="display:flex;gap:4px;">';
                    html += '<button onclick="_spPopupPage=1;renderSpPopup();" ' + (_spPopupPage <= 1 ? 'disabled' : '') + ' style="padding:5px 10px;border:1px solid #ced4da;border-radius:3px;background:#fff;cursor:pointer;font-size:12px;">&laquo;</button>';
                    html += '<button onclick="_spPopupPage--;renderSpPopup();" ' + (_spPopupPage <= 1 ? 'disabled' : '') + ' style="padding:5px 10px;border:1px solid #ced4da;border-radius:3px;background:#fff;cursor:pointer;font-size:12px;">&lsaquo; Prev</button>';
                    html += '<span style="padding:5px 14px;background:var(--navy-primary,#001f3f);color:#fff;border-radius:3px;font-weight:600;">' + _spPopupPage + ' / ' + totalPages + '</span>';
                    html += '<button onclick="_spPopupPage++;renderSpPopup();" ' + (_spPopupPage >= totalPages ? 'disabled' : '') + ' style="padding:5px 10px;border:1px solid #ced4da;border-radius:3px;background:#fff;cursor:pointer;font-size:12px;">Next &rsaquo;</button>';
                    html += '<button onclick="_spPopupPage=' + totalPages + ';renderSpPopup();" ' + (_spPopupPage >= totalPages ? 'disabled' : '') + ' style="padding:5px 10px;border:1px solid #ced4da;border-radius:3px;background:#fff;cursor:pointer;font-size:12px;">&raquo;</button>';
                    html += '</div></div>';
                }

                html += '</div>'; // close padding wrapper
            }

            // Bottom bar with Print button
            html += '<div style="padding:14px 20px;border-top:1px solid #e9ecef;background:#f8f9fa;display:flex;align-items:center;justify-content:space-between;">';
            html += '<span style="font-size:12px;color:#888;">Total Revenue: <strong style="color:var(--navy-primary,#001f3f);">' + currency + ' ' + totalRev.toFixed(0) + '</strong></span>';
            html += '<button onclick="printSpPopup()" style="display:inline-flex;align-items:center;gap:8px;padding:8px 20px;background:var(--navy-primary,#001f3f);color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:13px;font-weight:600;transition:all .2s;" onmouseover="this.style.opacity=\'0.85\'" onmouseout="this.style.opacity=\'1\'"><i class="fas fa-print"></i> Print Report</button>';
            html += '</div>';

            Swal.update({ html: html });
        }

        function printSpPopup() {
            var subs = _spPopupSubs;
            var currency = _spPopupCurrency;
            var totalRev = 0;
            subs.forEach(function(s) { totalRev += parseFloat(s.total_amount) || 0; });

            var printHtml = '<!DOCTYPE html><html><head><title>Subscriptions - ' + _spPopupName + '</title>';
            printHtml += '<style>body{font-family:Arial,sans-serif;margin:20px;color:#333;}';
            printHtml += 'h2{color:#001f3f;margin-bottom:5px;}p.sub{color:#666;font-size:13px;margin-bottom:15px;}';
            printHtml += 'table{width:100%;border-collapse:collapse;font-size:13px;}';
            printHtml += 'th{background:#001f3f;color:#fff;padding:10px 12px;text-align:left;font-weight:600;}';
            printHtml += 'td{padding:8px 12px;border-bottom:1px solid #e0e0e0;}';
            printHtml += 'tr:nth-child(even){background:#f8f9fa;}';
            printHtml += '.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;color:#fff;}';
            printHtml += '.summary{margin-top:15px;padding:10px;background:#f8f9fa;border-radius:4px;font-size:13px;}';
            printHtml += '@media print{body{margin:10px;}}</style></head><body>';
            printHtml += '<h2><i class="fas fa-user-tie"></i> ' + escapeHtml(_spPopupName) + '</h2>';
            printHtml += '<p class="sub">Subscription Report &mdash; ' + new Date().toLocaleDateString() + '</p>';
            printHtml += '<table><thead><tr><th>Invoice</th><th>Customer</th><th>Status</th><th>Payment</th><th style="text-align:right;">Amount</th></tr></thead><tbody>';
            subs.forEach(function(s) {
                printHtml += '<tr><td>' + escapeHtml(s.invoice_no) + '</td><td>' + escapeHtml(s.customer_name) + '</td><td>' + escapeHtml(s.status_label) + '</td><td>' + escapeHtml(s.payment_status) + '</td><td style="text-align:right;">' + currency + ' ' + parseFloat(s.total_amount).toFixed(0) + '</td></tr>';
            });
            printHtml += '</tbody></table>';
            printHtml += '<div class="summary"><strong>Total: ' + subs.length + ' subscriptions</strong> &nbsp;|&nbsp; <strong>Revenue: ' + currency + ' ' + totalRev.toFixed(0) + '</strong></div>';
            printHtml += '</body></html>';

            var w = window.open('', '_blank', 'width=800,height=600');
            w.document.write(printHtml);
            w.document.close();
            w.focus();
            setTimeout(function() { w.print(); }, 300);
        }
    </script>
</body>
</html>
