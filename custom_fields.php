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
$current_page = 'custom_fields';

// Handle AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            case 'getFields':
                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "SELECT field_id, entity_type, field_name, field_label, field_type,
                            field_options, is_required, display_order, is_active, created_at
                     FROM custom_fields
                     ORDER BY entity_type ASC, display_order ASC"
                );
                $stmt->execute();
                $res = $stmt->get_result();

                $fields = [];
                while ($row = $res->fetch_assoc()) {
                    $fields[] = [
                        'field_id'      => (int)$row['field_id'],
                        'entity_type'   => $row['entity_type'],
                        'field_name'    => $row['field_name'],
                        'field_label'   => $row['field_label'],
                        'field_type'    => $row['field_type'],
                        'field_options' => $row['field_options'] ?? '',
                        'is_required'   => (bool)$row['is_required'],
                        'display_order' => (int)$row['display_order'],
                        'is_active'     => (bool)$row['is_active'],
                        'created_at'    => date('M d, Y', strtotime($row['created_at']))
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $fields]);
                exit();

            case 'addField':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $entity_type   = isset($_POST['entity_type'])   ? trim($_POST['entity_type'])   : '';
                $field_label   = isset($_POST['field_label'])   ? trim($_POST['field_label'])   : '';
                $field_name    = isset($_POST['field_name'])    ? trim($_POST['field_name'])    : '';
                $field_type    = isset($_POST['field_type'])    ? trim($_POST['field_type'])    : 'text';
                $field_options = isset($_POST['field_options']) ? trim($_POST['field_options']) : '';
                $is_required   = isset($_POST['is_required'])  ? intval($_POST['is_required']) : 0;
                $display_order = isset($_POST['display_order']) ? intval($_POST['display_order']) : 0;

                if (empty($field_label)) {
                    echo json_encode(['success' => false, 'message' => 'Field label is required']);
                    exit();
                }

                if (!in_array($entity_type, ['customer', 'subscription'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid entity type']);
                    exit();
                }

                if (!in_array($field_type, ['text', 'number', 'date', 'select', 'textarea'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid field type']);
                    exit();
                }

                // validate field_name: alphanumeric + underscore
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $field_name)) {
                    echo json_encode(['success' => false, 'message' => 'Field name must be alphanumeric with underscores only']);
                    exit();
                }

                $optVal = ($field_type === 'select' && !empty($field_options)) ? $field_options : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "INSERT INTO custom_fields (entity_type, field_name, field_label, field_type, field_options, is_required, display_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
                );
                $stmt->bind_param("sssssii", $entity_type, $field_name, $field_label, $field_type, $optVal, $is_required, $display_order);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Custom Field Created', "Created field: $field_label ($entity_type)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Custom field added']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Field name already exists for this entity type']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add field']);
                    }
                }
                exit();

            case 'updateField':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $field_id      = isset($_POST['field_id'])      ? intval($_POST['field_id'])      : 0;
                $entity_type   = isset($_POST['entity_type'])   ? trim($_POST['entity_type'])     : '';
                $field_label   = isset($_POST['field_label'])   ? trim($_POST['field_label'])     : '';
                $field_name    = isset($_POST['field_name'])    ? trim($_POST['field_name'])      : '';
                $field_type    = isset($_POST['field_type'])    ? trim($_POST['field_type'])      : 'text';
                $field_options = isset($_POST['field_options']) ? trim($_POST['field_options'])   : '';
                $is_required   = isset($_POST['is_required'])  ? intval($_POST['is_required'])   : 0;
                $display_order = isset($_POST['display_order']) ? intval($_POST['display_order']) : 0;
                $is_active     = isset($_POST['is_active'])    ? intval($_POST['is_active'])     : 1;

                if ($field_id <= 0 || empty($field_label)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid input']);
                    exit();
                }

                if (!in_array($entity_type, ['customer', 'subscription'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid entity type']);
                    exit();
                }

                if (!in_array($field_type, ['text', 'number', 'date', 'select', 'textarea'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid field type']);
                    exit();
                }

                if (!preg_match('/^[a-zA-Z0-9_]+$/', $field_name)) {
                    echo json_encode(['success' => false, 'message' => 'Field name must be alphanumeric with underscores only']);
                    exit();
                }

                $optVal = ($field_type === 'select' && !empty($field_options)) ? $field_options : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "UPDATE custom_fields
                     SET entity_type=?, field_name=?, field_label=?, field_type=?, field_options=?,
                         is_required=?, display_order=?, is_active=?
                     WHERE field_id=?"
                );
                $stmt->bind_param("sssssiiii", $entity_type, $field_name, $field_label, $field_type, $optVal, $is_required, $display_order, $is_active, $field_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Custom Field Updated', "Updated field: $field_label (ID $field_id)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Custom field updated']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Field name already exists for this entity type']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update field']);
                    }
                }
                exit();

            case 'deleteField':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $field_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                if ($field_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid field ID']);
                    exit();
                }

                $conn = getDBConnection();

                // get label for log
                $stmt = $conn->prepare("SELECT field_label FROM custom_fields WHERE field_id=?");
                $stmt->bind_param("i", $field_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $deletedLabel = $res->num_rows > 0 ? $res->fetch_assoc()['field_label'] : '';
                $stmt->close();

                // cascade delete values
                $stmt = $conn->prepare("DELETE FROM custom_field_values WHERE field_id=?");
                $stmt->bind_param("i", $field_id);
                $stmt->execute();
                $stmt->close();

                // delete field
                $stmt = $conn->prepare("DELETE FROM custom_fields WHERE field_id=?");
                $stmt->bind_param("i", $field_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Custom Field Deleted', "Deleted field: $deletedLabel (ID $field_id)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Custom field deleted']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete field']);
                }
                exit();

            case 'toggleActive':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $field_id  = isset($_POST['id'])        ? intval($_POST['id'])        : 0;
                $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

                if ($field_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid field ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE custom_fields SET is_active=? WHERE field_id=?");
                $stmt->bind_param("ii", $is_active, $field_id);

                if ($stmt->execute()) {
                    $label = $is_active ? 'Custom Field Activated' : 'Custom Field Deactivated';
                    logActivity($user_id, $username, $label, "Changed active status for field ID $field_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_active ? 'Field activated' : 'Field deactivated']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                exit();

            case 'toggleRequired':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $field_id    = isset($_POST['id'])          ? intval($_POST['id'])          : 0;
                $is_required = isset($_POST['is_required']) ? intval($_POST['is_required']) : 0;

                if ($field_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid field ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE custom_fields SET is_required=? WHERE field_id=?");
                $stmt->bind_param("ii", $is_required, $field_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Custom Field Updated', "Changed required status for field ID $field_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_required ? 'Field is now required' : 'Field is now optional']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update']);
                }
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("custom_fields.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

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
    <title>Custom Fields - <?php echo htmlspecialchars($branding['site_name']); ?></title>

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

        .entity-badge { padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600; display: inline-block; text-transform: capitalize; }
        .entity-customer { background: #d4edda; color: #155724; }
        .entity-subscription { background: #cce5ff; color: #004085; }

        .type-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 500; background: #f0f0f0; color: #555; display: inline-block; }

        #optionsGroup { display: none; }

        .initially-hidden { visibility: hidden; }
    </style>
</head>
<body class="initially-hidden">
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span>Custom Fields</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-puzzle-piece"></i> Custom Fields</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Custom Fields</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadFields()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Field
                        </button>
                    </div>
                </div>

                <div class="filters-section initially-hidden" id="filtersSection">
                    <div class="filters-header">
                        <h3><i class="fas fa-filter"></i> Filters</h3>
                        <button class="btn btn-secondary btn-sm" onclick="clearFilters()">
                            <i class="fas fa-times-circle"></i> Clear All
                        </button>
                    </div>
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Field Label</label>
                            <input type="text" id="filterLabel" class="filter-input" placeholder="Search label...">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-layer-group"></i> Entity Type</label>
                            <select id="filterEntity" class="filter-input">
                                <option value="">All</option>
                                <option value="customer">Customer</option>
                                <option value="subscription">Subscription</option>
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
                    <table id="fieldsTable" class="display table-full-width"></table>
                </div>
            </div>
        </div>
    </div>

    <!-- Field Modal -->
    <div class="modal-overlay" id="fieldModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-puzzle-piece"></i> Add Custom Field</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="fieldForm">
                    <input type="hidden" id="fieldId" name="field_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-layer-group"></i> Entity Type *</label>
                            <select id="formEntityType" name="entity_type" required>
                                <option value="">-- Select --</option>
                                <option value="customer">Customer</option>
                                <option value="subscription">Subscription</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Field Label *</label>
                            <input type="text" id="formFieldLabel" name="field_label" required placeholder="e.g. Tax ID Number">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-code"></i> Field Name *</label>
                            <input type="text" id="formFieldName" name="field_name" required placeholder="e.g. tax_id_number" pattern="[a-zA-Z0-9_]+" title="Alphanumeric and underscores only">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-list"></i> Field Type *</label>
                            <select id="formFieldType" name="field_type" required onchange="toggleOptions()">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="select">Select (Dropdown)</option>
                                <option value="textarea">Textarea</option>
                            </select>
                        </div>

                        <div class="form-group" id="optionsGroup" style="grid-column: 1 / -1;">
                            <label><i class="fas fa-list-ol"></i> Options (comma-separated) *</label>
                            <textarea id="formFieldOptions" name="field_options" rows="2" placeholder="Option 1, Option 2, Option 3"></textarea>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-sort-numeric-up"></i> Display Order</label>
                            <input type="number" id="formDisplayOrder" name="display_order" value="0" min="0">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-asterisk"></i> Required</label>
                            <select id="formIsRequired" name="is_required">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
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
        let fieldsTable;
        let isEditMode = false;
        let fieldsData = [];

        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.remove('initially-hidden');
            loadFields();
        });

        function loadFields() {
            $.ajax({
                url: '?action=getFields',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        fieldsData = response.data;
                        $('#filtersSection').show().removeClass('initially-hidden');
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load fields' });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
                }
            });
        }

        function initializeDataTable(data) {
            if (fieldsTable) {
                fieldsTable.destroy();
                $('#fieldsTable').empty();
            }

            setTimeout(function() {
                fieldsTable = $('#fieldsTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        { data: 'field_id', title: 'ID', width: '50px' },
                        {
                            data: 'entity_type',
                            title: 'Entity Type',
                            render: function(data) {
                                var cls = data === 'customer' ? 'entity-customer' : 'entity-subscription';
                                return '<span class="entity-badge ' + cls + '">' + data + '</span>';
                            }
                        },
                        { data: 'field_label', title: 'Field Label' },
                        {
                            data: 'field_name',
                            title: 'Field Name',
                            render: function(data) {
                                return '<code style="background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:12px;">' + data + '</code>';
                            }
                        },
                        {
                            data: 'field_type',
                            title: 'Type',
                            render: function(data) {
                                return '<span class="type-badge">' + data + '</span>';
                            }
                        },
                        {
                            data: 'is_required',
                            title: 'Required',
                            render: function(data, type, row) {
                                var checked = data ? ' checked="checked"' : '';
                                return '<input type="checkbox"' + checked + ' class="toggle" onchange="toggleRequired(' + row.field_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        { data: 'display_order', title: 'Order', width: '60px' },
                        {
                            data: 'is_active',
                            title: 'Active',
                            render: function(data, type, row) {
                                var checked = data ? ' checked="checked"' : '';
                                return '<input type="checkbox"' + checked + ' class="toggle" onchange="toggleActive(' + row.field_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        { data: 'created_at', title: 'Created' },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                var rowJson = JSON.stringify(row).replace(/'/g, "\\'");
                                return '<button class="action-icon edit-icon" title="Edit" onclick=\'editField(' + rowJson + ')\'><i class="fas fa-edit"></i></button> ' +
                                       '<button class="action-icon delete-icon" title="Delete" onclick="deleteField(' + row.field_id + ')"><i class="fas fa-trash"></i></button>';
                            }
                        }
                    ],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 6, 8] }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: [0, 1, 2, 3, 4, 6, 8] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 6, 8] }
                        }
                    ],
                    order: [[1, 'asc'], [6, 'asc']]
                });

                $('#filterLabel').on('keyup', applyFilters);
                $('#filterEntity').on('change', applyFilters);
                $('#filterStatus').on('change', applyFilters);
            }, 100);
        }

        function applyFilters() {
            if (!fieldsTable) return;
            $.fn.dataTable.ext.search = [];

            var labelFilter  = document.getElementById('filterLabel').value.toLowerCase();
            var entityFilter = document.getElementById('filterEntity').value;
            var statusFilter = document.getElementById('filterStatus').value;

            $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
                var row = fieldsData[dataIndex];
                if (!row) return true;
                if (labelFilter && row.field_label.toLowerCase().indexOf(labelFilter) === -1) return false;
                if (entityFilter && row.entity_type !== entityFilter) return false;
                if (statusFilter === 'active'   && !row.is_active) return false;
                if (statusFilter === 'inactive' &&  row.is_active) return false;
                return true;
            });
            fieldsTable.draw();
        }

        function clearFilters() {
            document.getElementById('filterLabel').value  = '';
            document.getElementById('filterEntity').value = '';
            document.getElementById('filterStatus').value = '';
            if (fieldsTable) {
                $.fn.dataTable.ext.search = [];
                fieldsTable.columns().search('').draw();
            }
        }

        // auto-generate field_name from label
        document.getElementById('formFieldLabel').addEventListener('input', function() {
            if (!isEditMode) {
                var name = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                document.getElementById('formFieldName').value = name;
            }
        });

        function toggleOptions() {
            var type = document.getElementById('formFieldType').value;
            document.getElementById('optionsGroup').style.display = type === 'select' ? '' : 'none';
        }

        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-puzzle-piece"></i> Add Custom Field';
            document.getElementById('fieldForm').reset();
            document.getElementById('fieldId').value = '';
            document.getElementById('formDisplayOrder').value = '0';
            document.getElementById('activeGroup').style.display = 'none';
            document.getElementById('optionsGroup').style.display = 'none';
            document.getElementById('fieldModal').classList.add('active');
        }

        function editField(f) {
            isEditMode = true;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Custom Field';
            document.getElementById('fieldId').value          = f.field_id;
            document.getElementById('formEntityType').value   = f.entity_type;
            document.getElementById('formFieldLabel').value   = f.field_label;
            document.getElementById('formFieldName').value    = f.field_name;
            document.getElementById('formFieldType').value    = f.field_type;
            document.getElementById('formFieldOptions').value = f.field_options || '';
            document.getElementById('formIsRequired').value   = f.is_required ? '1' : '0';
            document.getElementById('formDisplayOrder').value = f.display_order;
            document.getElementById('formIsActive').value     = f.is_active ? '1' : '0';
            document.getElementById('activeGroup').style.display = '';
            toggleOptions();
            document.getElementById('fieldModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('fieldModal').classList.remove('active');
            document.getElementById('fieldForm').reset();
        }

        document.getElementById('fieldModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // form submit
        document.getElementById('fieldForm').addEventListener('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var action = isEditMode ? 'updateField' : 'addField';

            // validate options if select type
            if (formData.get('field_type') === 'select' && !formData.get('field_options').trim()) {
                Swal.fire({ icon: 'warning', title: 'Missing Options', text: 'Please enter comma-separated options for select field type' });
                return;
            }

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
                        setTimeout(function() { loadFields(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        });

        function toggleActive(fieldId, isActive) {
            var formData = new FormData();
            formData.append('id', fieldId);
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
                        Swal.fire({ icon: 'success', text: response.message, timer: 1500, showConfirmButton: false });
                        setTimeout(function() { loadFields(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        setTimeout(function() { loadFields(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        }

        function toggleRequired(fieldId, isRequired) {
            var formData = new FormData();
            formData.append('id', fieldId);
            formData.append('is_required', isRequired);

            $.ajax({
                url: '?action=toggleRequired',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', text: response.message, timer: 1500, showConfirmButton: false });
                        setTimeout(function() { loadFields(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        setTimeout(function() { loadFields(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        }

        function deleteField(fieldId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Custom Field?',
                text: 'This will also delete all saved values for this field. This action cannot be undone.',
                showCancelButton: true,
                confirmButtonColor: '#ea4335',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    var formData = new FormData();
                    formData.append('id', fieldId);

                    $.ajax({
                        url: '?action=deleteField',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', text: response.message, timer: 2000, showConfirmButton: false });
                                setTimeout(function() { loadFields(); }, 100);
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
