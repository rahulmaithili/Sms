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
$current_page = 'currencies';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            case 'getCurrencies':
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT currency_id, code, name, symbol, exchange_rate, is_default, is_active, created_at FROM currencies ORDER BY is_default DESC, code ASC");
                $stmt->execute();
                $result = $stmt->get_result();

                $currencies = [];
                while ($row = $result->fetch_assoc()) {
                    $currencies[] = [
                        'currency_id'   => (int)$row['currency_id'],
                        'code'          => $row['code'],
                        'name'          => $row['name'],
                        'symbol'        => $row['symbol'],
                        'exchange_rate'  => (float)$row['exchange_rate'],
                        'is_default'    => (int)$row['is_default'],
                        'is_active'     => (int)$row['is_active'],
                        'created_at'    => date('M d, Y', strtotime($row['created_at']))
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $currencies]);
                exit();

            case 'addCurrency':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $code          = isset($_POST['code']) ? strtoupper(trim($_POST['code'])) : '';
                $name          = isset($_POST['name']) ? trim($_POST['name']) : '';
                $symbol        = isset($_POST['symbol']) ? trim($_POST['symbol']) : '';
                $exchange_rate = isset($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : 1;
                $is_default    = isset($_POST['is_default']) ? intval($_POST['is_default']) : 0;

                if (empty($code) || strlen($code) !== 3) {
                    echo json_encode(['success' => false, 'message' => 'Currency code must be exactly 3 characters']);
                    exit();
                }
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Currency name is required']);
                    exit();
                }
                if ($exchange_rate <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Exchange rate must be greater than 0']);
                    exit();
                }

                $conn = getDBConnection();

                // unset prev default
                if ($is_default) {
                    $conn->query("UPDATE currencies SET is_default = 0");
                }

                $stmt = $conn->prepare("INSERT INTO currencies (code, name, symbol, exchange_rate, is_default, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssdi", $code, $name, $symbol, $exchange_rate, $is_default);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Currency Created', "Created currency: $code ($name)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Currency added successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Currency code already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add currency']);
                    }
                }
                exit();

            case 'updateCurrency':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $currency_id   = isset($_POST['currency_id']) ? intval($_POST['currency_id']) : 0;
                $code          = isset($_POST['code']) ? strtoupper(trim($_POST['code'])) : '';
                $name          = isset($_POST['name']) ? trim($_POST['name']) : '';
                $symbol        = isset($_POST['symbol']) ? trim($_POST['symbol']) : '';
                $exchange_rate = isset($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : 1;
                $is_default    = isset($_POST['is_default']) ? intval($_POST['is_default']) : 0;

                if ($currency_id <= 0 || empty($code) || strlen($code) !== 3 || empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid input']);
                    exit();
                }

                $conn = getDBConnection();

                // unset prev default
                if ($is_default) {
                    $stmt = $conn->prepare("UPDATE currencies SET is_default = 0 WHERE currency_id != ?");
                    $stmt->bind_param("i", $currency_id);
                    $stmt->execute();
                    $stmt->close();
                }

                $stmt = $conn->prepare("UPDATE currencies SET code = ?, name = ?, symbol = ?, exchange_rate = ?, is_default = ? WHERE currency_id = ?");
                $stmt->bind_param("sssdii", $code, $name, $symbol, $exchange_rate, $is_default, $currency_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Currency Updated', "Updated currency: $code ($name)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Currency updated successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Currency code already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update currency']);
                    }
                }
                exit();

            case 'toggleCurrencyActive':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $currency_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                $is_active   = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

                if ($currency_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid currency ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE currencies SET is_active = ? WHERE currency_id = ?");
                $stmt->bind_param("ii", $is_active, $currency_id);

                if ($stmt->execute()) {
                    $label = $is_active ? 'Currency Activated' : 'Currency Deactivated';
                    logActivity($user_id, $username, $label, "Changed active status for currency_id $currency_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_active ? 'Currency activated' : 'Currency deactivated']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                exit();

            case 'toggleCurrencyDefault':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $currency_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                $is_default  = isset($_POST['is_default']) ? intval($_POST['is_default']) : 0;

                if ($currency_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid currency ID']);
                    exit();
                }

                $conn = getDBConnection();

                // unset all defaults first
                if ($is_default) {
                    $conn->query("UPDATE currencies SET is_default = 0");
                }

                $stmt = $conn->prepare("UPDATE currencies SET is_default = ? WHERE currency_id = ?");
                $stmt->bind_param("ii", $is_default, $currency_id);

                if ($stmt->execute()) {
                    $label = $is_default ? 'Set Default Currency' : 'Unset Default Currency';
                    logActivity($user_id, $username, $label, "currency_id $currency_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_default ? 'Set as default' : 'Default removed']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update default']);
                }
                exit();

            case 'deleteCurrency':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $currency_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                if ($currency_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid currency ID']);
                    exit();
                }

                $conn = getDBConnection();

                // check if default — can't delete default
                $stmt = $conn->prepare("SELECT code, is_default FROM currencies WHERE currency_id = ?");
                $stmt->bind_param("i", $currency_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $cur = $result->fetch_assoc();
                $stmt->close();

                if (!$cur) {
                    echo json_encode(['success' => false, 'message' => 'Currency not found']);
                    exit();
                }
                if ($cur['is_default']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete the default currency']);
                    exit();
                }

                $stmt = $conn->prepare("DELETE FROM currencies WHERE currency_id = ?");
                $stmt->bind_param("i", $currency_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Currency Deleted', "Deleted currency: " . $cur['code']);
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Currency deleted successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete currency']);
                }
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("currencies.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// Render HTML
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
    <title>Currencies - Dashboard System</title>

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
                <span>Currencies</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-coins"></i> Currencies</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Currencies</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadCurrencies()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Currency
                        </button>
                    </div>
                </div>

                <div class="table-scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                </div>
                <div class="table-responsive">
                    <table id="currenciesTable" class="display table-full-width"></table>
                </div>
            </div>
        </div>
    </div>

    <!-- Currency Modal -->
    <div class="modal-overlay" id="currencyModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-coins"></i> Add Currency</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="currencyForm">
                    <input type="hidden" id="currencyId" name="currency_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-code"></i> Currency Code *</label>
                            <input type="text" id="formCode" name="code" required maxlength="3" placeholder="e.g. USD" style="text-transform:uppercase;">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Currency Name *</label>
                            <input type="text" id="formName" name="name" required placeholder="e.g. US Dollar">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-dollar-sign"></i> Symbol</label>
                            <input type="text" id="formSymbol" name="symbol" maxlength="10" placeholder="e.g. $">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-exchange-alt"></i> Exchange Rate (to base) *</label>
                            <input type="number" id="formExchangeRate" name="exchange_rate" step="0.000001" min="0.000001" value="1" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-star"></i> Set as Default</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="formIsDefault">
                                <label for="formIsDefault">Make this the default currency</label>
                            </div>
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
        let currencyTable;
        let isEditMode = false;
        let currencyData = [];

        $(document).ready(function() {
            loadCurrencies();
        });

        function loadCurrencies() {
            $.ajax({
                url: '?action=getCurrencies',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        currencyData = response.data;
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load currencies' });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
                }
            });
        }

        function initializeDataTable(data) {
            if (currencyTable) {
                currencyTable.destroy();
                $('#currenciesTable').empty();
            }

            setTimeout(function() {
                currencyTable = $('#currenciesTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        { data: 'currency_id', title: 'ID' },
                        { data: 'code', title: 'Code' },
                        { data: 'name', title: 'Name' },
                        { data: 'symbol', title: 'Symbol', defaultContent: '-' },
                        {
                            data: 'exchange_rate',
                            title: 'Exchange Rate',
                            render: function(data) {
                                return parseFloat(data).toFixed(6).replace(/\.?0+$/, '');
                            }
                        },
                        {
                            data: 'is_default',
                            title: 'Default',
                            render: function(data, type, row) {
                                var checked = data ? ' checked="checked"' : '';
                                return '<input type="checkbox"' + checked + ' class="toggle" onchange="toggleDefault(' + row.currency_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        {
                            data: 'is_active',
                            title: 'Active',
                            render: function(data, type, row) {
                                var checked = data ? ' checked="checked"' : '';
                                return '<input type="checkbox"' + checked + ' class="toggle" onchange="toggleActive(' + row.currency_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        { data: 'created_at', title: 'Created' },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                return '<button class="action-icon edit-icon" onclick=\'editCurrency(' + JSON.stringify(row).replace(/'/g, "\\'") + ')\'><i class="fas fa-edit"></i></button>' +
                                       '<button class="action-icon delete-icon" onclick="deleteCurrency(' + row.currency_id + ')"><i class="fas fa-trash"></i></button>';
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
                            exportOptions: { columns: [0, 1, 2, 3, 4, 7] }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: [0, 1, 2, 3, 4, 7] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 7] }
                        }
                    ],
                    order: [[1, 'asc']]
                });
            }, 100);
        }

        // modal helpers
        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-coins"></i> Add Currency';
            document.getElementById('currencyForm').reset();
            document.getElementById('currencyId').value = '';
            document.getElementById('formExchangeRate').value = '1';
            document.getElementById('formIsDefault').checked = false;
            document.getElementById('currencyModal').classList.add('active');
        }

        function editCurrency(row) {
            isEditMode = true;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Currency';
            document.getElementById('currencyId').value = row.currency_id;
            document.getElementById('formCode').value = row.code;
            document.getElementById('formName').value = row.name;
            document.getElementById('formSymbol').value = row.symbol || '';
            document.getElementById('formExchangeRate').value = row.exchange_rate;
            document.getElementById('formIsDefault').checked = !!row.is_default;
            document.getElementById('currencyModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('currencyModal').classList.remove('active');
            document.getElementById('currencyForm').reset();
        }

        document.getElementById('currencyModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // form submit
        document.getElementById('currencyForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // force uppercase code
            var codeEl = document.getElementById('formCode');
            codeEl.value = codeEl.value.toUpperCase().trim();

            if (codeEl.value.length !== 3) {
                Swal.fire({ icon: 'warning', text: 'Currency code must be exactly 3 characters' });
                return;
            }

            var formData = new FormData(this);
            formData.set('is_default', document.getElementById('formIsDefault').checked ? 1 : 0);

            var action = isEditMode ? 'updateCurrency' : 'addCurrency';

            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

            $.ajax({
                url: '?action=' + action,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Success!', text: response.message, timer: 2000, showConfirmButton: false });
                        closeModal();
                        setTimeout(function() { loadCurrencies(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        });

        // toggle active
        function toggleActive(currencyId, isActive) {
            var fd = new FormData();
            fd.append('id', currencyId);
            fd.append('is_active', isActive);

            $.ajax({
                url: '?action=toggleCurrencyActive',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', text: response.message, timer: 1500, showConfirmButton: false });
                        setTimeout(function() { loadCurrencies(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        setTimeout(function() { loadCurrencies(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        }

        // toggle default
        function toggleDefault(currencyId, isDefault) {
            var fd = new FormData();
            fd.append('id', currencyId);
            fd.append('is_default', isDefault);

            $.ajax({
                url: '?action=toggleCurrencyDefault',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', text: response.message, timer: 1500, showConfirmButton: false });
                        setTimeout(function() { loadCurrencies(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        setTimeout(function() { loadCurrencies(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        }

        // delete
        function deleteCurrency(currencyId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Currency?',
                text: 'This action cannot be undone',
                showCancelButton: true,
                confirmButtonColor: '#ea4335',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    var fd = new FormData();
                    fd.append('id', currencyId);

                    $.ajax({
                        url: '?action=deleteCurrency',
                        method: 'POST',
                        data: fd,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', text: response.message, timer: 2000, showConfirmButton: false });
                                setTimeout(function() { loadCurrencies(); }, 100);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
