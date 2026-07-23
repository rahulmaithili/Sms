<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * Subscription Calendar View
 */

require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!checkSessionTimeout()) { header("Location: login.php"); exit(); }

$username  = $_SESSION['username'];
$role      = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_id   = $_SESSION['user_id'];
$sp_id     = $_SESSION['salesperson_id'] ?? null;
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'calendar';

// block customers
if ($role === 'customer') { header("Location: customer_portal.php"); exit(); }

// AJAX: calendar events
if (isset($_GET['action']) && $_GET['action'] === 'getCalendarEvents') {
    header('Content-Type: application/json');

    try {
        $conn = getDBConnection();
        $currency = getCurrency();

        $sql = "SELECT s.sl, s.invoice_no, s.customer_name, s.starting_date, s.expiry_date,
                       s.payment_date, s.total_amount, s.payment_status, s.salesperson_id
                FROM subscriptions s";

        if ($role === 'admin') {
            $stmt = $conn->prepare($sql . " ORDER BY s.sl DESC");
        } elseif ($role === 'salesperson' && $sp_id) {
            $stmt = $conn->prepare($sql . " WHERE s.salesperson_id = ? ORDER BY s.sl DESC");
            $stmt->bind_param("i", $sp_id);
        } else {
            $stmt = $conn->prepare($sql . " WHERE s.added_by = ? ORDER BY s.sl DESC");
            $stmt->bind_param("i", $user_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $events = [];
        while ($row = $result->fetch_assoc()) {
            $inv = $row['invoice_no'] ?: 'N/A';
            $cust = $row['customer_name'] ?: 'Unknown';
            $amt = (float)$row['total_amount'];
            $sl = (int)$row['sl'];
            $pay_status = $row['payment_status'] ?: 'Unpaid';

            // expiry event
            if (!empty($row['expiry_date'])) {
                $events[] = [
                    'title' => "Expiry: $inv ($cust)",
                    'start' => $row['expiry_date'],
                    'color' => '#dc3545',
                    'extendedProps' => [
                        'type' => 'Expiry',
                        'sl' => $sl,
                        'invoice_no' => $inv,
                        'customer' => $cust,
                        'amount' => $amt,
                        'payment_status' => $pay_status,
                        'currency' => $currency
                    ]
                ];
            }

            // start event
            if (!empty($row['starting_date'])) {
                $events[] = [
                    'title' => "Start: $inv ($cust)",
                    'start' => $row['starting_date'],
                    'color' => '#28a745',
                    'extendedProps' => [
                        'type' => 'Start',
                        'sl' => $sl,
                        'invoice_no' => $inv,
                        'customer' => $cust,
                        'amount' => $amt,
                        'payment_status' => $pay_status,
                        'currency' => $currency
                    ]
                ];
            }

            // payment event
            if (!empty($row['payment_date'])) {
                $events[] = [
                    'title' => "Payment: $inv ($cust)",
                    'start' => $row['payment_date'],
                    'color' => '#0074D9',
                    'extendedProps' => [
                        'type' => 'Payment',
                        'sl' => $sl,
                        'invoice_no' => $inv,
                        'customer' => $cust,
                        'amount' => $amt,
                        'payment_status' => $pay_status,
                        'currency' => $currency
                    ]
                ];
            }
        }

        $stmt->close();
        echo json_encode(['success' => true, 'data' => $events]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load events']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Calendar - Subscription Management</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <!-- fullcalendar v6 bundles css inside the js, no separate css link needed -->

    <style>
        /* Calendar wrapper */
        .calendar-wrap {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px var(--shadow-color);
            border: 1px solid var(--border-color);
        }

        /* Filter bar */
        .cal-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
            align-items: center;
        }
        .cal-filter-btn {
            padding: 7px 18px;
            border: 2px solid var(--border-color);
            border-radius: 20px;
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .cal-filter-btn:hover { border-color: #0074D9; color: #0074D9; }
        .cal-filter-btn.active { background: #0074D9; color: #fff; border-color: #0074D9; }
        .cal-filter-btn .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        /* Legend */
        .cal-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
            padding: 10px 14px;
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .cal-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .cal-legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        /* FullCalendar overrides */
        .fc {
            --fc-border-color: var(--border-color);
            --fc-page-bg-color: var(--bg-card);
            --fc-neutral-bg-color: var(--bg-secondary);
            --fc-today-bg-color: rgba(0,116,217,0.06);
            font-family: inherit;
        }
        .fc .fc-toolbar-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .fc .fc-button {
            background: var(--navy-primary);
            border-color: var(--navy-primary);
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            padding: 6px 14px;
            text-transform: capitalize;
        }
        .fc .fc-button:hover { background: var(--navy-light); border-color: var(--navy-light); }
        .fc .fc-button-active { background: #0074D9 !important; border-color: #0074D9 !important; }
        .fc .fc-daygrid-day-number { color: var(--text-primary); font-weight: 500; font-size: 13px; padding: 6px 8px; }
        .fc .fc-col-header-cell { background: var(--navy-primary); }
        .fc .fc-col-header-cell-cushion { color: #fff; font-weight: 600; font-size: 13px; padding: 8px 4px; text-decoration: none; }
        .fc .fc-daygrid-event { border-radius: 4px; padding: 2px 5px; font-size: 11px; font-weight: 600; cursor: pointer; border: none; }
        .fc .fc-daygrid-dot-event .fc-event-title { font-weight: 600; }
        .fc .fc-list-event-title a { color: var(--text-primary); }
        .fc .fc-list-day-cushion { background: var(--bg-secondary); }
        .fc .fc-scrollgrid { border-radius: 8px; overflow: hidden; }

        /* Skeleton */
        .cal-skeleton {
            height: 500px;
            background: linear-gradient(90deg, var(--skeleton-base) 25%, var(--skeleton-shine) 50%, var(--skeleton-base) 75%);
            background-size: 200% 100%;
            animation: calShimmer 1.5s infinite;
            border-radius: 8px;
        }
        @keyframes calShimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

        /* Stats row */
        .cal-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 18px;
        }
        .cal-stat-card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .cal-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #fff;
        }
        .cal-stat-val { font-size: 22px; font-weight: 800; color: var(--text-primary); }
        .cal-stat-lbl { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }

        @media (max-width: 768px) {
            .cal-stats { grid-template-columns: 1fr; }
            .fc .fc-toolbar { flex-direction: column; gap: 8px; }
            .fc .fc-toolbar-title { font-size: 16px; }
        }

        /* Dark mode - FullCalendar */
        body.dark-mode .fc { --fc-bg-color: #1e293b; --fc-border-color: #334155; --fc-text-color: #e2e8f0; }
        body.dark-mode .fc .fc-toolbar-title { color: #e2e8f0; }
        body.dark-mode .fc .fc-col-header-cell { background: #334155; }
        body.dark-mode .fc .fc-daygrid-day { background: #1e293b; }
        body.dark-mode .fc .fc-daygrid-day-number { color: #e2e8f0; }
        body.dark-mode .fc .fc-day-today { background: rgba(0,116,217,0.1) !important; }
        body.dark-mode .fc .fc-list-event-title a { color: #e2e8f0; }
        body.dark-mode .fc .fc-list-day-cushion { background: #334155; }
        body.dark-mode .fc .fc-button { background: #334155; border-color: #475569; }
        body.dark-mode .fc .fc-button:hover { background: #475569; }
        body.dark-mode .fc .fc-button-active { background: #0074D9 !important; border-color: #0074D9 !important; }
        body.dark-mode .fc .fc-scrollgrid, body.dark-mode .fc td, body.dark-mode .fc th { border-color: #334155; }
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
                <span>Calendar</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-calendar-alt"></i> Subscription Calendar</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <!-- Stats -->
            <div class="cal-stats" id="calStats">
                <div class="cal-stat-card">
                    <div class="cal-stat-icon" style="background:linear-gradient(135deg,#dc3545,#ff6b6b)"><i class="fas fa-clock"></i></div>
                    <div><div class="cal-stat-val" id="statExpiries">-</div><div class="cal-stat-lbl">Expiry Events</div></div>
                </div>
                <div class="cal-stat-card">
                    <div class="cal-stat-icon" style="background:linear-gradient(135deg,#155724,#28a745)"><i class="fas fa-play"></i></div>
                    <div><div class="cal-stat-val" id="statStarts">-</div><div class="cal-stat-lbl">Start Events</div></div>
                </div>
                <div class="cal-stat-card">
                    <div class="cal-stat-icon" style="background:linear-gradient(135deg,#003366,#0074D9)"><i class="fas fa-money-bill-wave"></i></div>
                    <div><div class="cal-stat-val" id="statPayments">-</div><div class="cal-stat-lbl">Payment Events</div></div>
                </div>
            </div>

            <!-- Filters + Legend -->
            <div class="calendar-wrap">
                <div class="cal-filters">
                    <button class="cal-filter-btn active" data-type="all" onclick="filterEvents('all', this)">
                        <i class="fas fa-layer-group"></i> All
                    </button>
                    <button class="cal-filter-btn" data-type="Expiry" onclick="filterEvents('Expiry', this)">
                        <span class="dot" style="background:#dc3545"></span> Expiries
                    </button>
                    <button class="cal-filter-btn" data-type="Start" onclick="filterEvents('Start', this)">
                        <span class="dot" style="background:#28a745"></span> Starts
                    </button>
                    <button class="cal-filter-btn" data-type="Payment" onclick="filterEvents('Payment', this)">
                        <span class="dot" style="background:#0074D9"></span> Payments
                    </button>
                </div>

                <div class="cal-legend">
                    <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#dc3545"></span> Expiry Date</div>
                    <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#28a745"></span> Start Date</div>
                    <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#0074D9"></span> Payment Date</div>
                </div>

                <!-- Calendar skeleton -->
                <div class="cal-skeleton" id="calSkeleton"></div>
                <div id="calendar" style="display:none;"></div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>

    <script>
        // all events cache + active filter
        var allEvents = [];
        var activeFilter = 'all';
        var calendar;

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function formatCurrency(amount, currency) {
            return (currency || '') + ' ' + parseFloat(amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function getPayBadge(status) {
            var cls = 'pay-unpaid';
            if (status === 'Paid') cls = 'pay-paid';
            else if (status === 'Partial') cls = 'pay-partial';
            else if (status === 'Refunded') cls = 'pay-refunded';
            return '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;' +
                (cls === 'pay-paid' ? 'background:#d4edda;color:#155724;' :
                 cls === 'pay-partial' ? 'background:#fff3cd;color:#856404;' :
                 cls === 'pay-refunded' ? 'background:#cce5ff;color:#004085;' :
                 'background:#f8d7da;color:#721c24;') + '">' + escapeHtml(status) + '</span>';
        }

        function getTypeBadge(type) {
            var colors = { 'Expiry': '#dc3545', 'Start': '#28a745', 'Payment': '#0074D9' };
            var c = colors[type] || '#666';
            return '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:' + c + ';color:#fff;">' + escapeHtml(type) + '</span>';
        }

        function filterEvents(type, btn) {
            activeFilter = type;
            // toggle active btn
            document.querySelectorAll('.cal-filter-btn').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            // refetch to trigger filter
            calendar.refetchEvents();
        }

        function updateStats(events) {
            var expiries = 0, starts = 0, payments = 0;
            events.forEach(function(e) {
                var t = e.extendedProps ? e.extendedProps.type : (e.extendedProperties ? e.extendedProperties.type : '');
                if (t === 'Expiry') expiries++;
                else if (t === 'Start') starts++;
                else if (t === 'Payment') payments++;
            });
            document.getElementById('statExpiries').textContent = expiries;
            document.getElementById('statStarts').textContent = starts;
            document.getElementById('statPayments').textContent = payments;
        }

        document.addEventListener('DOMContentLoaded', function() {
            var calEl = document.getElementById('calendar');

            calendar = new FullCalendar.Calendar(calEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek,listWeek'
                },
                height: 'auto',
                eventDisplay: 'block',
                dayMaxEvents: 3,
                events: function(info, successCallback, failureCallback) {
                    $.getJSON('?action=getCalendarEvents', function(r) {
                        if (r.success) {
                            allEvents = r.data;
                            updateStats(allEvents);
                            // apply filter
                            var filtered = activeFilter === 'all'
                                ? allEvents
                                : allEvents.filter(function(e) { return e.extendedProps && e.extendedProps.type === activeFilter; });
                            successCallback(filtered);
                        } else {
                            failureCallback();
                            Swal.fire('Error', 'Failed to load calendar events', 'error');
                        }
                    }).fail(function() {
                        failureCallback();
                        Swal.fire('Error', 'Network error loading events', 'error');
                    });
                },
                eventClick: function(info) {
                    var ep = info.event.extendedProps;
                    Swal.fire({
                        title: '<i class="fas fa-calendar-check" style="color:#0074D9;margin-right:6px;"></i> Event Details',
                        html:
                            '<div style="text-align:left;font-size:14px;line-height:2;">' +
                            '<b>Invoice No:</b> ' + escapeHtml(ep.invoice_no) + '<br>' +
                            '<b>Customer:</b> ' + escapeHtml(ep.customer) + '<br>' +
                            '<b>Type:</b> ' + getTypeBadge(ep.type) + '<br>' +
                            '<b>Date:</b> ' + escapeHtml(info.event.startStr) + '<br>' +
                            '<b>Amount:</b> ' + formatCurrency(ep.amount, ep.currency) + '<br>' +
                            '<b>Payment Status:</b> ' + getPayBadge(ep.payment_status) +
                            '</div>',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-external-link-alt"></i> View Subscription',
                        confirmButtonColor: '#0074D9',
                        cancelButtonText: 'Close'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            window.location.href = 'subscriptions.php?highlight=' + ep.sl;
                        }
                    });
                },
                loading: function(isLoading) {
                    if (isLoading) {
                        document.getElementById('calSkeleton').style.display = 'block';
                        calEl.style.display = 'none';
                    } else {
                        document.getElementById('calSkeleton').style.display = 'none';
                        calEl.style.display = 'block';
                    }
                }
            });

            calendar.render();
        });
    </script>
</body>
</html>
