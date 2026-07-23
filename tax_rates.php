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
$current_page = 'tax_rates';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            case 'getTaxRates':
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT tax_id, name, rate, is_default, is_active, created_at FROM tax_rates ORDER BY name ASC");
                $stmt->execute();
                $result = $stmt->get_result();

                $rates = [];
                while ($row = $result->fetch_assoc()) {
                    $rates[] = [
                        'tax_id'     => (int)$row['tax_id'],
                        'name'       => $row['name'],
                        'rate'       => (float)$row['rate'],
                        'is_default' => (int)$row['is_default'],
                        'is_active'  => (int)$row['is_active'],
                        'created_at' => date('M d, Y', strtotime($row['created_at']))
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $rates]);
                exit();

            case 'addTaxRate':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $name = isset($_POST['name']) ? trim($_POST['name']) : '';
                $rate = isset($_POST['rate']) ? floatval($_POST['rate']) : 0;
                $is_default = isset($_POST['is_default']) ? intval($_POST['is_default']) : 0;

                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Tax name is required']);
                    exit();
                }

                $conn = getDBConnection();

                // unset prev default
                if ($is_default) {
                    $conn->query("UPDATE tax_rates SET is_default = 0");
                }

                $stmt = $conn->prepare("INSERT INTO tax_rates (name, rate, is_default, is_active) VALUES (?, ?, ?, 1)");
                $stmt->bind_param("sdi", $name, $rate, $is_default);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Tax Rate Created', "Created tax rate: $name ($rate%)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Tax rate added successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Tax rate name already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add tax rate']);
                    }
                }
                exit();

            case 'updateTaxRate':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $tax_id     = isset($_POST['tax_id']) ? intval($_POST['tax_id']) : 0;
                $name       = isset($_POST['name']) ? trim($_POST['name']) : '';
                $rate       = isset($_POST['rate']) ? floatval($_POST['rate']) : 0;
                $is_default = isset($_POST['is_default']) ? intval($_POST['is_default']) : 0;

                if ($tax_id <= 0 || empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid input']);
                    exit();
                }

                $conn = getDBConnection();

                // unset prev default
                if ($is_default) {
                    $stmt = $conn->prepare("UPDATE tax_rates SET is_default = 0 WHERE tax_id != ?");
                    $stmt->bind_param("i", $tax_id);
                    $stmt->execute();
                    $stmt->close();
                }

                $stmt = $conn->prepare("UPDATE tax_rates SET name = ?, rate = ?, is_default = ? WHERE tax_id = ?");
                $stmt->bind_param("sdii", $name, $rate, $is_default, $tax_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Tax Rate Updated', "Updated tax rate: $name ($rate%)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Tax rate updated successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Tax rate name already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update tax rate']);
                    }
                }
                exit();

            case 'toggleTaxActive':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $tax_id    = isset($_POST['id']) ? intval($_POST['id']) : 0;
                $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

                if ($tax_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid tax rate ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE tax_rates SET is_active = ? WHERE tax_id = ?");
                $stmt->bind_param("ii", $is_active, $tax_id);

                if ($stmt->execute()) {
                    $label = $is_active ? 'Tax Rate Activated' : 'Tax Rate Deactivated';
                    logActivity($user_id, $username, $label, "Changed active status for tax_id $tax_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_active ? 'Tax rate activated' : 'Tax rate deactivated']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                exit();

            case 'toggleTaxDefault':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $tax_id    = isset($_POST['id']) ? intval($_POST['id']) : 0;
                $is_default = isset($_POST['is_default']) ? intval($_POST['is_default']) : 0;

                if ($tax_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid tax rate ID']);
                    exit();
                }

                $conn = getDBConnection();

                // unset all defaults first
                if ($is_default) {
                    $conn->query("UPDATE tax_rates SET is_default = 0");
                }

                $stmt = $conn->prepare("UPDATE tax_rates SET is_default = ? WHERE tax_id = ?");
                $stmt->bind_param("ii", $is_default, $tax_id);

                if ($stmt->execute()) {
                    $label = $is_default ? 'Set Default Tax' : 'Unset Default Tax';
                    logActivity($user_id, $username, $label, "tax_id $tax_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_default ? 'Set as default' : 'Default removed']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update default']);
                }
                exit();

            case 'deleteTaxRate':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $tax_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                if ($tax_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid tax rate ID']);
                    exit();
                }

                $conn = getDBConnection();

                // fetch name for log
                $stmt = $conn->prepare("SELECT name FROM tax_rates WHERE tax_id = ?");
                $stmt->bind_param("i", $tax_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $deletedName = $result->num_rows > 0 ? $result->fetch_assoc()['name'] : '';
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM tax_rates WHERE tax_id = ?");
                $stmt->bind_param("i", $tax_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Tax Rate Deleted', "Deleted tax rate: $deletedName");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Tax rate deleted successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete tax rate']);
                }
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("tax_rates.php error: " . $e->getMessage());
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
    <title>Tax Rates - Dashboard System</title>

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
                <span>Tax Rates</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-percentage"></i> Tax Rates</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Tax Rates</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadTaxRates()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Tax Rate
                        </button>
                    </div>
                </div>

                <div class="table-scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                </div>
                <div class="table-responsive">
                    <table id="taxRatesTable" class="display table-full-width"></table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tax Rate Modal -->
    <div class="modal-overlay" id="taxModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-percentage"></i> Add Tax Rate</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="taxForm">
                    <input type="hidden" id="taxId" name="tax_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Tax Name *</label>
                            <input type="text" id="formName" name="name" required placeholder="e.g. GST 18%">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-percent"></i> Rate (%) *</label>
                            <input type="number" id="formRate" name="rate" step="0.01" min="0" max="100" value="0" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-star"></i> Set as Default</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="formIsDefault">
                                <label for="formIsDefault">Make this the default tax rate</label>
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
        let taxTable;
        let isEditMode = false;
        let taxData = [];

        $(document).ready(function() {
            loadTaxRates();
        });

        function loadTaxRates() {
            $.ajax({
                url: '?action=getTaxRates',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        taxData = response.data;
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load tax rates' });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
                }
            });
        }

        function initializeDataTable(data) {
            if (taxTable) {
                taxTable.destroy();
                $('#taxRatesTable').empty();
            }

            setTimeout(function() {
                taxTable = $('#taxRatesTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        { data: 'tax_id', title: 'ID' },
                        { data: 'name', title: 'Name' },
                        {
                            data: 'rate',
                            title: 'Rate (%)',
                            render: function(data) {
                                return parseFloat(data).toFixed(2) + '%';
                            }
                        },
                        {
                            data: 'is_default',
                            title: 'Default',
                            render: function(data, type, row) {
                                var checked = data ? ' checked="checked"' : '';
                                return '<input type="checkbox"' + checked + ' class="toggle" onchange="toggleDefault(' + row.tax_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        {
                            data: 'is_active',
                            title: 'Active',
                            render: function(data, type, row) {
                                var checked = data ? ' checked="checked"' : '';
                                return '<input type="checkbox"' + checked + ' class="toggle" onchange="toggleActive(' + row.tax_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        { data: 'created_at', title: 'Created' },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                return '<button class="action-icon edit-icon" onclick=\'editTax(' + JSON.stringify(row).replace(/'/g, "\\'") + ')\'><i class="fas fa-edit"></i></button>' +
                                       '<button class="action-icon delete-icon" onclick="deleteTax(' + row.tax_id + ')"><i class="fas fa-trash"></i></button>';
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
                            exportOptions: { columns: [0, 1, 2, 5] }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: [0, 1, 2, 5] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: [0, 1, 2, 5] }
                        }
                    ],
                    order: [[1, 'asc']]
                });
            }, 100);
        }

        // modal helpers
        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-percentage"></i> Add Tax Rate';
            document.getElementById('taxForm').reset();
            document.getElementById('taxId').value = '';
            document.getElementById('formRate').value = '0';
            document.getElementById('formIsDefault').checked = false;
            document.getElementById('taxModal').classList.add('active');
        }

        function editTax(row) {
            isEditMode = true;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Tax Rate';
            document.getElementById('taxId').value = row.tax_id;
            document.getElementById('formName').value = row.name;
            document.getElementById('formRate').value = row.rate;
            document.getElementById('formIsDefault').checked = !!row.is_default;
            document.getElementById('taxModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('taxModal').classList.remove('active');
            document.getElementById('taxForm').reset();
        }

        document.getElementById('taxModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // form submit
        document.getElementById('taxForm').addEventListener('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            formData.set('is_default', document.getElementById('formIsDefault').checked ? 1 : 0);

            var action = isEditMode ? 'updateTaxRate' : 'addTaxRate';

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
                        setTimeout(function() { loadTaxRates(); }, 100);
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
        function toggleActive(taxId, isActive) {
            var fd = new FormData();
            fd.append('id', taxId);
            fd.append('is_active', isActive);

            $.ajax({
                url: '?action=toggleTaxActive',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', text: response.message, timer: 1500, showConfirmButton: false });
                        setTimeout(function() { loadTaxRates(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        setTimeout(function() { loadTaxRates(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        }

        // toggle default
        function toggleDefault(taxId, isDefault) {
            var fd = new FormData();
            fd.append('id', taxId);
            fd.append('is_default', isDefault);

            $.ajax({
                url: '?action=toggleTaxDefault',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', text: response.message, timer: 1500, showConfirmButton: false });
                        setTimeout(function() { loadTaxRates(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        setTimeout(function() { loadTaxRates(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        }

        // delete
        function deleteTax(taxId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Tax Rate?',
                text: 'This action cannot be undone',
                showCancelButton: true,
                confirmButtonColor: '#ea4335',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    var fd = new FormData();
                    fd.append('id', taxId);

                    $.ajax({
                        url: '?action=deleteTaxRate',
                        method: 'POST',
                        data: fd,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', text: response.message, timer: 2000, showConfirmButton: false });
                                setTimeout(function() { loadTaxRates(); }, 100);
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
