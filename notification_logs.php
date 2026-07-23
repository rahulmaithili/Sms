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
$current_page = 'notification_logs';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            case 'getLogs':
                $conn = getDBConnection();
                $stmt = $conn->prepare("
                    SELECT nl.log_id, nl.subscription_sl, nl.recipient_email, nl.recipient_type,
                        nl.recipient_name, nl.notification_type, nl.days_before_expiry,
                        nl.subject, nl.body_preview, nl.status, nl.error_message,
                        nl.sent_at, nl.triggered_by, nl.triggered_by_user,
                        s.invoice_no, s.customer_name,
                        u.username AS triggered_by_username
                    FROM notification_logs nl
                    LEFT JOIN subscriptions s ON nl.subscription_sl = s.sl
                    LEFT JOIN users u ON nl.triggered_by_user = u.user_id
                    ORDER BY nl.sent_at DESC
                    LIMIT 5000
                ");
                $stmt->execute();
                $result = $stmt->get_result();

                $logs = [];
                while ($row = $result->fetch_assoc()) {
                    $logs[] = [
                        'log_id'                => (int)$row['log_id'],
                        'subscription_sl'       => $row['subscription_sl'],
                        'recipient_email'       => $row['recipient_email'],
                        'recipient_type'        => $row['recipient_type'],
                        'recipient_name'        => $row['recipient_name'],
                        'notification_type'     => $row['notification_type'],
                        'days_before_expiry'    => $row['days_before_expiry'] !== null ? (int)$row['days_before_expiry'] : null,
                        'subject'               => $row['subject'],
                        'body_preview'          => $row['body_preview'],
                        'status'                => $row['status'],
                        'error_message'         => $row['error_message'],
                        'sent_at'               => $row['sent_at'] ? date('M d, Y H:i', strtotime($row['sent_at'])) : '',
                        'triggered_by'          => $row['triggered_by'],
                        'triggered_by_user'     => $row['triggered_by_user'],
                        'triggered_by_username' => $row['triggered_by_username'],
                        'invoice_no'            => $row['invoice_no'],
                        'customer_name'         => $row['customer_name']
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $logs]);
                exit();

            case 'resendNotification':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                // Admin only (already checked above, but double-check)
                if ($role !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    exit();
                }

                $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;

                if ($log_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch original log entry
                $stmt = $conn->prepare("
                    SELECT nl.*, s.invoice_no, s.customer_name, s.expiry_date
                    FROM notification_logs nl
                    LEFT JOIN subscriptions s ON nl.subscription_sl = s.sl
                    WHERE nl.log_id = ?
                ");
                $stmt->bind_param("i", $log_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Log entry not found']);
                    exit();
                }

                $log = $result->fetch_assoc();
                $stmt->close();

                if (empty($log['recipient_email'])) {
                    echo json_encode(['success' => false, 'message' => 'No recipient email found']);
                    exit();
                }

                // Build resend email
                $resendSubject = "[Resend] " . $log['subject'];
                $resendBody = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>";
                $resendBody .= "<h2 style='color:#001f3f;'>Notification Resend</h2>";
                $resendBody .= "<p>This is a resend of a previous notification.</p>";
                $resendBody .= "<hr style='border:1px solid #eee;'>";
                if (!empty($log['customer_name'])) {
                    $resendBody .= "<p><strong>Customer:</strong> " . htmlspecialchars($log['customer_name']) . "</p>";
                }
                if (!empty($log['invoice_no'])) {
                    $resendBody .= "<p><strong>Invoice:</strong> " . htmlspecialchars($log['invoice_no']) . "</p>";
                }
                if (!empty($log['body_preview'])) {
                    $resendBody .= "<div style='padding:15px;background:#f8f9fa;border-radius:6px;margin:10px 0;'>";
                    $resendBody .= nl2br(htmlspecialchars($log['body_preview']));
                    $resendBody .= "</div>";
                }
                $resendBody .= "<p style='color:#666;font-size:12px;margin-top:20px;'>This email was resent by an administrator.</p>";
                $resendBody .= "</div>";

                // Send email
                $emailResult = sendEmail($log['recipient_email'], $resendSubject, $resendBody);

                $newStatus = $emailResult['success'] ? 'Sent' : 'Failed';
                $errorMsg = $emailResult['success'] ? null : $emailResult['message'];

                // Create NEW log entry (don't mutate original)
                logNotification(
                    $log['subscription_sl'],
                    $log['recipient_email'],
                    $log['recipient_type'],
                    $log['recipient_name'],
                    $log['notification_type'],
                    $log['days_before_expiry'],
                    $resendSubject,
                    $log['body_preview'],
                    $newStatus,
                    $errorMsg,
                    'manual',
                    $user_id
                );

                // Log activity
                logActivity($user_id, $username, 'Notification Resent', "Resent notification log #$log_id to " . $log['recipient_email']);

                if ($emailResult['success']) {
                    echo json_encode(['success' => true, 'message' => 'Notification resent successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to resend: ' . $emailResult['message']]);
                }
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("notification_logs.php error: " . $e->getMessage());
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
    <title>Notification Logs - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <style>
        /* Status badges */
        .notif-sent { background:#d4edda; color:#155724; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }
        .notif-failed { background:#f8d7da; color:#721c24; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }
        .notif-pending { background:#fff3cd; color:#856404; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }
        .notif-bounced { background:#ffe0b2; color:#e65100; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }

        /* Recipient type badges */
        .rtype-admin { background:#e3f2fd; color:#1565c0; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }
        .rtype-user { background:#e8f5e9; color:#2e7d32; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }
        .rtype-salesperson { background:#f3e5f5; color:#7b1fa2; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }
        .rtype-supplier { background:#fff3e0; color:#e65100; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }

        /* Notification type badges */
        .ntype-badge { background:#e8eaf6; color:#283593; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }

        /* Triggered by badges */
        .trigger-system { background:#eceff1; color:#546e7a; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }
        .trigger-manual { background:#e3f2fd; color:#1565c0; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; display:inline-block; }

        /* View detail modal styles */
        .detail-grid { display:grid; grid-template-columns:140px 1fr; gap:8px 12px; text-align:left; }
        .detail-grid .detail-label { font-weight:600; color:#001f3f; }
        .detail-grid .detail-value { color:#333; word-break:break-word; }
        .detail-error { background:#f8d7da; color:#721c24; padding:10px; border-radius:6px; margin-top:8px; }
        .detail-body-preview { background:#f8f9fa; padding:12px; border-radius:6px; margin-top:8px; max-height:200px; overflow-y:auto; text-align:left; white-space:pre-wrap; word-break:break-word; font-size:13px; }
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
                <span>System</span>
                <span class="breadcrumb-sep">/</span>
                <span>Notification Logs</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-envelope-open-text"></i> Notification Logs</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Notification Logs</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadLogs()">
                            <i class="fas fa-sync"></i> Refresh
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
                            <label><i class="fas fa-check-circle"></i> Status</label>
                            <select id="filterStatus" class="filter-input">
                                <option value="">All</option>
                                <option value="Sent">Sent</option>
                                <option value="Failed">Failed</option>
                                <option value="Pending">Pending</option>
                                <option value="Bounced">Bounced</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-bell"></i> Notification Type</label>
                            <select id="filterNotifType" class="filter-input">
                                <option value="">All</option>
                                <option value="expiry_reminder">Expiry Reminder</option>
                                <option value="expired_alert">Expired Alert</option>
                                <option value="payment_reminder">Payment Reminder</option>
                                <option value="renewal_notice">Renewal Notice</option>
                                <option value="manual_reminder">Manual Reminder</option>
                                <option value="welcome">Welcome</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-user-tag"></i> Recipient Type</label>
                            <select id="filterRecipientType" class="filter-input">
                                <option value="">All</option>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                                <option value="salesperson">Salesperson</option>
                                <option value="supplier">Supplier</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-robot"></i> Triggered By</label>
                            <select id="filterTriggeredBy" class="filter-input">
                                <option value="">All</option>
                                <option value="system">System</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Date From</label>
                            <input type="date" id="filterDateFrom" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-check"></i> Date To</label>
                            <input type="date" id="filterDateTo" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Customer Name</label>
                            <input type="text" id="filterCustomer" class="filter-input" placeholder="Search customer...">
                        </div>
                    </div>
                </div>

                <div class="table-scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                </div>
                <div class="table-responsive">
                    <table id="notifLogsTable" class="display table-full-width"></table>
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
        var notifLogsTable;
        var logsData = [];

        // Notification type readable labels
        var notifTypeLabels = {
            'expiry_reminder': 'Expiry Reminder',
            'expired_alert': 'Expired Alert',
            'payment_reminder': 'Payment Reminder',
            'renewal_notice': 'Renewal Notice',
            'manual_reminder': 'Manual Reminder',
            'welcome': 'Welcome',
            'custom': 'Custom'
        };

        $(document).ready(function() {
            loadLogs();
        });

        function loadLogs() {
            $.ajax({
                url: '?action=getLogs',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        logsData = response.data;
                        $('#filtersSection').show();
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to load notification logs'
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
            if (notifLogsTable) {
                notifLogsTable.destroy();
                $('#notifLogsTable').empty();
            }

            setTimeout(function() {
                notifLogsTable = $('#notifLogsTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        {
                            data: 'log_id',
                            title: '#'
                        },
                        {
                            data: 'invoice_no',
                            title: 'Invoice',
                            defaultContent: '<span style="color:#999;">Deleted</span>'
                        },
                        {
                            data: 'customer_name',
                            title: 'Customer',
                            defaultContent: '<span style="color:#999;">Deleted</span>'
                        },
                        {
                            data: 'recipient_email',
                            title: 'Recipient',
                            render: function(data, type, row) {
                                var html = '';
                                if (row.recipient_name) {
                                    html += '<strong>' + escapeHtml(row.recipient_name) + '</strong><br>';
                                }
                                html += '<span style="font-size:12px;color:#666;">' + escapeHtml(data || '') + '</span>';
                                return html;
                            }
                        },
                        {
                            data: 'recipient_type',
                            title: 'Type',
                            render: function(data) {
                                if (!data) return '-';
                                var cls = 'rtype-' + data;
                                var label = data.charAt(0).toUpperCase() + data.slice(1);
                                return '<span class="' + cls + '">' + escapeHtml(label) + '</span>';
                            }
                        },
                        {
                            data: 'notification_type',
                            title: 'Notification',
                            render: function(data) {
                                if (!data) return '-';
                                var label = notifTypeLabels[data] || (data.charAt(0).toUpperCase() + data.slice(1).replace(/_/g, ' '));
                                return '<span class="ntype-badge">' + escapeHtml(label) + '</span>';
                            }
                        },
                        {
                            data: 'subject',
                            title: 'Subject',
                            render: function(data) {
                                if (!data) return '-';
                                var truncated = data.length > 50 ? data.substring(0, 50) + '...' : data;
                                return '<span title="' + escapeAttr(data) + '">' + escapeHtml(truncated) + '</span>';
                            }
                        },
                        {
                            data: 'status',
                            title: 'Status',
                            render: function(data) {
                                if (!data) return '-';
                                var cls = 'notif-' + data.toLowerCase();
                                return '<span class="' + cls + '">' + escapeHtml(data) + '</span>';
                            }
                        },
                        {
                            data: 'triggered_by',
                            title: 'Triggered By',
                            render: function(data, type, row) {
                                if (!data) return '-';
                                var cls = data === 'system' ? 'trigger-system' : 'trigger-manual';
                                var label = data.charAt(0).toUpperCase() + data.slice(1);
                                if (data === 'manual' && row.triggered_by_username) {
                                    label += ' (' + escapeHtml(row.triggered_by_username) + ')';
                                }
                                return '<span class="' + cls + '">' + label + '</span>';
                            }
                        },
                        {
                            data: 'sent_at',
                            title: 'Sent At'
                        },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                var html = '';
                                // Resend button only for Failed or Bounced
                                if (row.status === 'Failed' || row.status === 'Bounced') {
                                    html += '<button class="action-icon edit-icon" onclick="resendNotification(' + row.log_id + ')" title="Resend"><i class="fas fa-paper-plane"></i></button>';
                                }
                                // View details button
                                html += '<button class="action-icon" onclick=\'viewDetails(' + JSON.stringify(row).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + ')\' title="View Details" style="color:#0074D9;"><i class="fas fa-eye"></i></button>';
                                return html;
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
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] }
                        }
                    ],
                    order: [[0, 'desc']]
                });

                // Apply custom filters on input/change
                $('#filterStatus').on('change', function() { applyFilters(); });
                $('#filterNotifType').on('change', function() { applyFilters(); });
                $('#filterRecipientType').on('change', function() { applyFilters(); });
                $('#filterTriggeredBy').on('change', function() { applyFilters(); });
                $('#filterDateFrom').on('change', function() { applyFilters(); });
                $('#filterDateTo').on('change', function() { applyFilters(); });
                $('#filterCustomer').on('keyup', function() { applyFilters(); });
            }, 100);
        }

        function applyFilters() {
            if (!notifLogsTable) return;

            $.fn.dataTable.ext.search = [];

            var statusFilter = document.getElementById('filterStatus').value;
            var notifTypeFilter = document.getElementById('filterNotifType').value;
            var recipientTypeFilter = document.getElementById('filterRecipientType').value;
            var triggeredByFilter = document.getElementById('filterTriggeredBy').value;
            var dateFromFilter = document.getElementById('filterDateFrom').value;
            var dateToFilter = document.getElementById('filterDateTo').value;
            var customerFilter = document.getElementById('filterCustomer').value.toLowerCase();

            $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
                var row = logsData[dataIndex];
                if (!row) return true;

                // Status filter
                if (statusFilter && row.status !== statusFilter) return false;

                // Notification type filter
                if (notifTypeFilter && row.notification_type !== notifTypeFilter) return false;

                // Recipient type filter
                if (recipientTypeFilter && row.recipient_type !== recipientTypeFilter) return false;

                // Triggered by filter
                if (triggeredByFilter && row.triggered_by !== triggeredByFilter) return false;

                // Date from filter
                if (dateFromFilter && row.sent_at) {
                    var rowDate = new Date(row.sent_at);
                    var fromDate = new Date(dateFromFilter);
                    if (rowDate < fromDate) return false;
                }

                // Date to filter
                if (dateToFilter && row.sent_at) {
                    var rowDate2 = new Date(row.sent_at);
                    var toDate = new Date(dateToFilter);
                    toDate.setHours(23, 59, 59, 999);
                    if (rowDate2 > toDate) return false;
                }

                // Customer name filter
                if (customerFilter) {
                    var custName = (row.customer_name || '').toLowerCase();
                    if (custName.indexOf(customerFilter) === -1) return false;
                }

                return true;
            });

            notifLogsTable.draw();
        }

        function clearFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterNotifType').value = '';
            document.getElementById('filterRecipientType').value = '';
            document.getElementById('filterTriggeredBy').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            document.getElementById('filterCustomer').value = '';

            if (notifLogsTable) {
                $.fn.dataTable.ext.search = [];
                notifLogsTable.columns().search('').draw();
            }
        }

        // ── View Details Modal ───────────────────────────────────────────────
        function viewDetails(row) {
            var statusClass = 'notif-' + (row.status || '').toLowerCase();
            var notifLabel = notifTypeLabels[row.notification_type] || (row.notification_type ? row.notification_type.charAt(0).toUpperCase() + row.notification_type.slice(1).replace(/_/g, ' ') : '-');

            var html = '<div class="detail-grid">';
            html += '<div class="detail-label">Subject:</div>';
            html += '<div class="detail-value">' + escapeHtml(row.subject || '-') + '</div>';
            html += '<div class="detail-label">Recipient:</div>';
            html += '<div class="detail-value">' + escapeHtml(row.recipient_name || '') + (row.recipient_name ? ' &mdash; ' : '') + escapeHtml(row.recipient_email || '-') + '</div>';
            html += '<div class="detail-label">Type:</div>';
            html += '<div class="detail-value"><span class="ntype-badge">' + escapeHtml(notifLabel) + '</span></div>';
            html += '<div class="detail-label">Status:</div>';
            html += '<div class="detail-value"><span class="' + statusClass + '">' + escapeHtml(row.status || '-') + '</span></div>';
            html += '<div class="detail-label">Invoice:</div>';
            html += '<div class="detail-value">' + escapeHtml(row.invoice_no || 'Deleted') + '</div>';
            html += '<div class="detail-label">Customer:</div>';
            html += '<div class="detail-value">' + escapeHtml(row.customer_name || 'Deleted') + '</div>';
            html += '<div class="detail-label">Sent At:</div>';
            html += '<div class="detail-value">' + escapeHtml(row.sent_at || '-') + '</div>';
            html += '<div class="detail-label">Days Before Expiry:</div>';
            html += '<div class="detail-value">' + (row.days_before_expiry !== null ? row.days_before_expiry : '-') + '</div>';
            html += '<div class="detail-label">Triggered By:</div>';
            html += '<div class="detail-value">' + escapeHtml(row.triggered_by || '-');
            if (row.triggered_by === 'manual' && row.triggered_by_username) {
                html += ' (' + escapeHtml(row.triggered_by_username) + ')';
            }
            html += '</div>';
            html += '</div>';

            // Error message
            if (row.status === 'Failed' && row.error_message) {
                html += '<div class="detail-error"><strong><i class="fas fa-exclamation-triangle"></i> Error:</strong> ' + escapeHtml(row.error_message) + '</div>';
            }

            // Body preview
            if (row.body_preview) {
                html += '<div style="text-align:left;margin-top:12px;"><strong>Body Preview:</strong></div>';
                html += '<div class="detail-body-preview">' + escapeHtml(row.body_preview) + '</div>';
            }

            Swal.fire({
                title: '<i class="fas fa-envelope-open-text" style="color:#0074D9;"></i> Notification Details',
                html: html,
                width: 650,
                showConfirmButton: true,
                confirmButtonText: 'Close',
                confirmButtonColor: '#001f3f'
            });
        }

        // ── Resend Notification ──────────────────────────────────────────────
        function resendNotification(logId) {
            Swal.fire({
                icon: 'question',
                title: 'Resend Notification?',
                text: 'This will send a new email to the original recipient.',
                showCancelButton: true,
                confirmButtonText: 'Resend',
                confirmButtonColor: '#0074D9'
            }).then(function(result) {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Sending...',
                        allowOutsideClick: false,
                        didOpen: function() {
                            Swal.showLoading();
                        }
                    });

                    var fd = new FormData();
                    fd.append('log_id', logId);

                    $.ajax({
                        url: '?action=resendNotification',
                        method: 'POST',
                        data: fd,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(r) {
                            if (r.success) {
                                Swal.fire({
                                    icon: 'success',
                                    text: r.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                setTimeout(function() { loadLogs(); }, 100);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: r.message
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

        // ── Utility functions ────────────────────────────────────────────────
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function escapeAttr(text) {
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    </script>
</body>
</html>
