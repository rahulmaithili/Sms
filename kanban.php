<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */

require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!checkSessionTimeout()) { header("Location: login.php"); exit(); }

$username = $_SESSION['username'];
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_id = $_SESSION['user_id'];
$sp_id = $_SESSION['salesperson_id'] ?? null;
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'kanban';

// block customers
if ($role === 'customer') { header("Location: customer_portal.php"); exit(); }

// AJAX handler
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            case 'getKanbanData':
                $conn = getDBConnection();
                $currency = getCurrency();

                $sql = "SELECT s.sl, s.customer_name, s.invoice_no, s.expiry_date,
                            s.total_amount, s.payment_status, s.subscription_status,
                            s.product_id, p.product_name,
                            s.salesperson_id, sp.name AS salesperson_name
                        FROM subscriptions s
                        LEFT JOIN products p ON s.product_id = p.product_id
                        LEFT JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id";

                if ($role === 'admin') {
                    $stmt = $conn->prepare($sql . " ORDER BY s.expiry_date ASC");
                } elseif ($role === 'salesperson' && $sp_id) {
                    $sql .= " WHERE s.salesperson_id = ?";
                    $stmt = $conn->prepare($sql . " ORDER BY s.expiry_date ASC");
                    $stmt->bind_param("i", $sp_id);
                } else {
                    $sql .= " WHERE s.added_by = ?";
                    $stmt = $conn->prepare($sql . " ORDER BY s.expiry_date ASC");
                    $stmt->bind_param("i", $user_id);
                }

                $stmt->execute();
                $result = $stmt->get_result();

                // cols: active, expiring_soon, expiring_today, expired, paused, cancelled
                $columns = [
                    'active'         => ['label' => 'Active',         'color' => '#28a745', 'cards' => []],
                    'expiring_soon'  => ['label' => 'Expiring Soon',  'color' => '#ff9800', 'cards' => []],
                    'expiring_today' => ['label' => 'Expiring Today', 'color' => '#dc3545', 'cards' => []],
                    'expired'        => ['label' => 'Expired',        'color' => '#6c757d', 'cards' => []],
                    'paused'         => ['label' => 'Paused',         'color' => '#ffc107', 'cards' => []],
                    'cancelled'      => ['label' => 'Cancelled',      'color' => '#343a40', 'cards' => []],
                ];

                while ($row = $result->fetch_assoc()) {
                    $sub_status = $row['subscription_status'];

                    // paused/cancelled go to their own cols
                    if ($sub_status === 'paused') {
                        $col_key = 'paused';
                    } elseif ($sub_status === 'cancelled') {
                        $col_key = 'cancelled';
                    } else {
                        // active — classify by expiry
                        $status = getSubscriptionStatus($row['expiry_date']);
                        switch ($status['label']) {
                            case 'Expired':        $col_key = 'expired'; break;
                            case 'Expiring Today':  $col_key = 'expiring_today'; break;
                            case 'Expiring Soon':   $col_key = 'expiring_soon'; break;
                            default:                $col_key = 'active'; break;
                        }
                    }

                    // days left
                    $days_left = null;
                    $days_text = '';
                    if (!empty($row['expiry_date'])) {
                        $now = new DateTime(date('Y-m-d'));
                        $expiry = new DateTime($row['expiry_date']);
                        $diff = (int)$now->diff($expiry)->format('%r%a');
                        $days_left = $diff;
                        if ($diff > 0) $days_text = $diff . ' days left';
                        elseif ($diff === 0) $days_text = 'Today';
                        else $days_text = abs($diff) . ' days ago';
                    }

                    $columns[$col_key]['cards'][] = [
                        'sl'               => (int)$row['sl'],
                        'customer_name'    => $row['customer_name'],
                        'invoice_no'       => $row['invoice_no'] ?? '',
                        'product_name'     => $row['product_name'] ?? '',
                        'total_amount'     => (float)$row['total_amount'],
                        'payment_status'   => $row['payment_status'],
                        'expiry_date'      => $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '',
                        'days_left'        => $days_left,
                        'days_text'        => $days_text,
                        'salesperson_name' => $row['salesperson_name'] ?? '',
                    ];
                }

                $stmt->close();
                $conn->close();

                echo json_encode(['success' => true, 'columns' => $columns, 'currency' => $currency]);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
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
    <title>Kanban Board - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <style>
        /* Kanban Board */
        .kanban-board { display: flex; gap: 16px; overflow-x: auto; padding: 10px 0; min-height: 500px; }
        .kanban-col { min-width: 280px; max-width: 320px; background: #f4f5f7; border-radius: 10px; display: flex; flex-direction: column; flex-shrink: 0; }
        .kanban-col-header { padding: 14px 16px; font-weight: 700; font-size: 14px; border-radius: 10px 10px 0 0; color: #fff; display: flex; justify-content: space-between; align-items: center; }
        .kanban-col-body { padding: 8px; flex: 1; overflow-y: auto; max-height: calc(100vh - 280px); }
        .kanban-card { background: #fff; border-radius: 8px; padding: 14px; margin-bottom: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); transition: transform .15s; cursor: default; }
        .kanban-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.12); }
        .kanban-card a { text-decoration: none; color: inherit; display: block; }
        .kanban-count { background: rgba(255,255,255,.3); padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }

        /* Status badges */
        .status-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block; }
        .pay-paid { background: #d4edda; color: #155724; }
        .pay-unpaid { background: #f8d7da; color: #721c24; }
        .pay-partial { background: #fff3cd; color: #856404; }
        .pay-refunded { background: #cce5ff; color: #004085; }

        /* Skeleton */
        .kanban-skeleton-card { background: #e9ecef; border-radius: 8px; height: 110px; margin-bottom: 10px; animation: skeleton-pulse 1.5s infinite; }
        @keyframes skeleton-pulse { 0%,100% { opacity: 0.6; } 50% { opacity: 1; } }

        /* Loading popup */
        .loading-ov { position: fixed; inset: 0; display: flex; justify-content: center; align-items: center; z-index: 10001; }
        .loading-popup { background: white; padding: 30px 40px; border-radius: 4px; box-shadow: 0 4px 24px rgba(0,0,0,0.18); display: flex; flex-direction: column; align-items: center; min-width: 240px; }
        .loading-progress { width: 200px; height: 6px; border-radius: 2px; background: #e0e0e0; overflow: hidden; margin-bottom: 16px; }
        .loading-progress-bar { width: 100%; height: 100%; background: var(--navy-accent, #0074D9); border-radius: 2px; animation: progressIndeterminate 1.5s ease-in-out infinite; transform-origin: left; }
        @keyframes progressIndeterminate { 0% { transform: translateX(-100%) scaleX(0.4); } 50% { transform: translateX(20%) scaleX(0.5); } 100% { transform: translateX(100%) scaleX(0.4); } }
        .loading-txt { font-size: 15px; color: #555; font-weight: 500; }

        /* Dark mode */
        .dark-mode .kanban-col { background: #1e293b; }
        .dark-mode .kanban-card { background: #263244; color: #e9ecef; }
        .dark-mode .kanban-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.3); }
        .dark-mode .kanban-skeleton-card { background: #334155; }
        .dark-mode .loading-popup { background: #1e293b; }
        .dark-mode .loading-txt { color: #ccc; }

        /* Responsive */
        @media (max-width: 768px) {
            .kanban-board { padding-bottom: 16px; }
            .kanban-col { min-width: 260px; }
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
                <span>Kanban Board</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-columns"></i> Kanban Board</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div id="kanbanRoot"></div>
        </div>
    </div>

    <script type="text/babel">
    /**
     * Developed by Rameez Scripts
     * Kanban Board — React Component
     */
    const { useState, useEffect, useCallback } = React;

    function KanbanBoard() {
        const [columns, setColumns] = useState(null);
        const [currency, setCurrency] = useState('INR');
        const [load, setLoad] = useState('Loading subscriptions...');

        const fetchData = useCallback(() => {
            setLoad('Loading subscriptions...');
            $.ajax({
                url: '?action=getKanbanData',
                method: 'GET',
                dataType: 'json',
                success: function(r) {
                    setLoad(null);
                    if (r.success) {
                        setColumns(r.columns);
                        setCurrency(r.currency || 'INR');
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Failed to load data' });
                    }
                },
                error: function(x, s, e) {
                    setLoad(null);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + e });
                }
            });
        }, []);

        useEffect(() => { fetchData(); }, [fetchData]);

        // format currency
        const fmtAmt = (amt) => {
            if (currency === 'INR' || currency === 'Rs' || currency === 'PKR') return '\u20B9 ' + Number(amt).toLocaleString('en-IN');
            if (currency === 'USD' || currency === '$') return '$ ' + Number(amt).toLocaleString('en-US');
            return currency + ' ' + Number(amt).toLocaleString();
        };

        const payClass = (s) => {
            switch(s) {
                case 'Paid': return 'pay-paid';
                case 'Unpaid': return 'pay-unpaid';
                case 'Partial': return 'pay-partial';
                case 'Refunded': return 'pay-refunded';
                default: return '';
            }
        };

        // column order
        const colOrder = ['active', 'expiring_soon', 'expiring_today', 'expired', 'paused', 'cancelled'];

        return (
            <div>
                <div style={{display:'flex', justifyContent:'flex-end', marginBottom: 10}}>
                    <button className="btn btn-primary" onClick={fetchData} style={{display:'flex', alignItems:'center', gap:6, padding:'8px 16px', background:'#0074D9', color:'#fff', border:'none', borderRadius:6, cursor:'pointer', fontSize:13, fontWeight:600}}>
                        <i className="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>

                {load && (
                    <div className="loading-ov">
                        <div className="loading-popup">
                            <div className="loading-progress"><div className="loading-progress-bar"></div></div>
                            <div className="loading-txt">{load}</div>
                        </div>
                    </div>
                )}

                {!columns && !load && null}

                {columns && (
                    <div className="kanban-board">
                        {colOrder.map(key => {
                            const col = columns[key];
                            if (!col) return null;
                            return (
                                <div className="kanban-col" key={key}>
                                    <div className="kanban-col-header" style={{background: col.color}}>
                                        <span>{col.label}</span>
                                        <span className="kanban-count">{col.cards.length}</span>
                                    </div>
                                    <div className="kanban-col-body">
                                        {col.cards.length === 0 && (
                                            <div style={{textAlign:'center', padding:'30px 10px', color:'#999', fontSize:12}}>
                                                <i className="fas fa-inbox" style={{fontSize:24, marginBottom:8, display:'block', opacity:.5}}></i>
                                                No subscriptions
                                            </div>
                                        )}
                                        {col.cards.map(card => (
                                            <div className="kanban-card" key={card.sl}>
                                                <a href={'subscriptions.php?highlight=' + card.sl} title="View subscription">
                                                    <div style={{fontWeight:600, fontSize:13, marginBottom:4}}>{card.customer_name}</div>
                                                    <div style={{fontSize:11, color:'#666'}}>{card.invoice_no}{card.product_name ? ' \u00B7 ' + card.product_name : ''}</div>
                                                    <div style={{display:'flex', justifyContent:'space-between', marginTop:8, fontSize:11, alignItems:'center'}}>
                                                        <span style={{color:'#0074D9', fontWeight:600}}>{fmtAmt(card.total_amount)}</span>
                                                        <span className={'status-badge ' + payClass(card.payment_status)}>{card.payment_status}</span>
                                                    </div>
                                                    <div style={{fontSize:10, color:'#999', marginTop:4}}>
                                                        Expires: {card.expiry_date || 'N/A'}{card.days_text ? ' \u00B7 ' + card.days_text : ''}
                                                    </div>
                                                    {card.salesperson_name && (
                                                        <div style={{fontSize:10, color:'#888', marginTop:2}}>SP: {card.salesperson_name}</div>
                                                    )}
                                                </a>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Skeleton while first load */}
                {!columns && load && (
                    <div className="kanban-board">
                        {colOrder.map(key => (
                            <div className="kanban-col" key={key}>
                                <div className="kanban-col-header" style={{background:'#ccc'}}>
                                    <span>&nbsp;</span>
                                </div>
                                <div className="kanban-col-body">
                                    <div className="kanban-skeleton-card"></div>
                                    <div className="kanban-skeleton-card"></div>
                                    <div className="kanban-skeleton-card"></div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    }

    ReactDOM.createRoot(document.getElementById('kanbanRoot')).render(<KanbanBoard />);
    </script>
</body>
</html>
