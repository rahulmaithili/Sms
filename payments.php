<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * Payment Records Management Page
 */

require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!checkSessionTimeout()) { header("Location: login.php"); exit(); }

$username  = $_SESSION['username'];
$role      = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_id   = $_SESSION['user_id'];
$sp_id     = $_SESSION['salesperson_id'] ?? null;
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;

// CSV template download (before JSON header)
if (isset($_GET['action']) && $_GET['action'] === 'downloadPaymentTemplate') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payments_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['invoice_no', 'amount', 'payment_method', 'payment_date', 'reference_no', 'notes']);
    fputcsv($out, ['INV-2026-001', 5000, 'Bank Transfer', '2026-04-01', 'TXN-100', 'First installment']);
    fputcsv($out, ['INV-2026-002', 25000, 'Online', '2026-04-02', 'TXN-101', 'Full payment']);
    fputcsv($out, ['INV-2026-003', 3000, 'Cash', '2026-04-03', '', 'Partial']);
    fclose($out);
    exit();
}
$current_page = 'payments';

// ============================================================
// AJAX handlers
// ============================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            // ── 1. getPayments ─────────────────────────────────────────────
            case 'getPayments':
                $conn     = getDBConnection();
                $currency = getCurrency();

                $sql = "SELECT p.payment_id, p.subscription_sl, p.amount, p.payment_method,
                               p.payment_date, p.reference_no, p.notes, p.created_at,
                               s.invoice_no, s.customer_name, s.total_amount,
                               u.full_name AS added_by_name
                        FROM payments p
                        JOIN subscriptions s ON p.subscription_sl = s.sl
                        LEFT JOIN users u ON p.added_by = u.user_id";

                $conditions = [];
                $params     = [];
                $types      = '';

                if ($role === 'salesperson' && $sp_id) {
                    $conditions[] = "s.salesperson_id = ?";
                    $params[]     = $sp_id;
                    $types       .= 'i';
                } elseif ($role !== 'admin') {
                    $conditions[] = "s.added_by = ?";
                    $params[]     = $user_id;
                    $types       .= 'i';
                }

                if (!empty($_GET['subscription_sl'])) {
                    $filter_sl    = intval($_GET['subscription_sl']);
                    $conditions[] = "p.subscription_sl = ?";
                    $params[]     = $filter_sl;
                    $types       .= 'i';
                }

                if (!empty($conditions)) {
                    $sql .= " WHERE " . implode(' AND ', $conditions);
                }
                $sql .= " ORDER BY p.payment_id DESC";

                $stmt = $conn->prepare($sql);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                $payments = [];
                while ($row = $result->fetch_assoc()) {
                    $payments[] = [
                        'payment_id'      => (int)$row['payment_id'],
                        'subscription_sl' => (int)$row['subscription_sl'],
                        'invoice_no'      => $row['invoice_no'] ?? '',
                        'customer_name'   => $row['customer_name'] ?? '',
                        'total_amount'    => (float)$row['total_amount'],
                        'amount'          => (float)$row['amount'],
                        'payment_method'  => $row['payment_method'] ?? '',
                        'payment_date'    => $row['payment_date']
                                             ? date('M d, Y', strtotime($row['payment_date'])) : '',
                        'payment_date_raw' => $row['payment_date'] ?? '',
                        'reference_no'    => $row['reference_no'] ?? '',
                        'notes'           => $row['notes'] ?? '',
                        'added_by_name'   => $row['added_by_name'] ?? '',
                        'created_at'      => $row['created_at']
                                             ? date('M d, Y H:i', strtotime($row['created_at'])) : ''
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $payments, 'currency' => $currency]);
                exit();

            // ── 2. addPayment ──────────────────────────────────────────────
            case 'addPayment':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $sl             = isset($_POST['subscription_sl']) ? intval($_POST['subscription_sl']) : 0;
                $amount         = isset($_POST['amount'])          ? floatval($_POST['amount'])         : 0;
                $payment_date   = isset($_POST['payment_date'])    ? trim($_POST['payment_date'])       : '';
                $payment_method = isset($_POST['payment_method'])  ? trim($_POST['payment_method'])     : '';
                $reference_no   = isset($_POST['reference_no'])    ? trim($_POST['reference_no'])       : '';
                $notes          = isset($_POST['notes'])           ? trim($_POST['notes'])              : '';

                if ($sl <= 0 || $amount <= 0 || empty($payment_date)) {
                    echo json_encode(['success' => false, 'message' => 'Subscription, amount and payment date are required']);
                    exit();
                }

                $conn = getDBConnection();

                // RBAC: user can only add payments to own subscriptions
                $own_check = $conn->prepare("SELECT sl, customer_name, invoice_no FROM subscriptions WHERE sl = ?");
                $own_check->bind_param("i", $sl);
                $own_check->execute();
                $own_result = $own_check->get_result();

                if ($own_result->num_rows === 0) {
                    $own_check->close();
                    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
                    exit();
                }

                $sub_row = $own_result->fetch_assoc();
                $own_check->close();

                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        $rbac = $conn->prepare("SELECT sl FROM subscriptions WHERE sl = ? AND salesperson_id = ?");
                        $rbac->bind_param("ii", $sl, $sp_id);
                    } else {
                        $rbac = $conn->prepare("SELECT sl FROM subscriptions WHERE sl = ? AND added_by = ?");
                        $rbac->bind_param("ii", $sl, $user_id);
                    }
                    $rbac->execute();
                    $rbac_result = $rbac->get_result();
                    if ($rbac_result->num_rows === 0) {
                        $rbac->close();
                        echo json_encode(['success' => false, 'message' => 'Access denied. You can only add payments to your own subscriptions.']);
                        exit();
                    }
                    $rbac->close();
                }

                $methodVal    = !empty($payment_method) ? $payment_method : null;
                $referenceVal = !empty($reference_no)   ? $reference_no   : null;
                $notesVal     = !empty($notes)          ? $notes          : null;

                $stmt = $conn->prepare(
                    "INSERT INTO payments (subscription_sl, amount, payment_method, payment_date, reference_no, notes, added_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("idssssi", $sl, $amount, $methodVal, $payment_date, $referenceVal, $notesVal, $user_id);

                if (!$stmt->execute()) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to add payment']);
                    exit();
                }
                $stmt->close();

                // Recalculate subscription payment_status
                $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE subscription_sl = ?");
                $sum_stmt->bind_param("i", $sl);
                $sum_stmt->execute();
                $sum_total = (float)$sum_stmt->get_result()->fetch_assoc()['total'];
                $sum_stmt->close();

                $tot_stmt = $conn->prepare("SELECT total_amount FROM subscriptions WHERE sl = ?");
                $tot_stmt->bind_param("i", $sl);
                $tot_stmt->execute();
                $sub_total = (float)$tot_stmt->get_result()->fetch_assoc()['total_amount'];
                $tot_stmt->close();

                if ($sum_total >= $sub_total) {
                    $new_status = 'Paid';
                } elseif ($sum_total > 0) {
                    $new_status = 'Partial';
                } else {
                    $new_status = 'Unpaid';
                }

                $upd_stmt = $conn->prepare("UPDATE subscriptions SET payment_status = ? WHERE sl = ?");
                $upd_stmt->bind_param("si", $new_status, $sl);
                $upd_stmt->execute();
                $upd_stmt->close();

                logActivity($user_id, $username, 'Payment Added',
                    "Added payment of $amount for subscription SL $sl ({$sub_row['invoice_no']} - {$sub_row['customer_name']}). Status => $new_status");

                echo json_encode(['success' => true, 'message' => 'Payment added successfully. Subscription status updated to ' . $new_status . '.']);
                exit();

            // ── 3. deletePayment ───────────────────────────────────────────
            case 'deletePayment':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
                if ($payment_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch payment details for logging and recalculation
                $fetch = $conn->prepare("SELECT p.subscription_sl, p.amount, p.added_by AS pay_added_by,
                                                s.invoice_no, s.customer_name, s.added_by AS sub_owner, s.salesperson_id AS sub_sp
                                         FROM payments p
                                         JOIN subscriptions s ON p.subscription_sl = s.sl
                                         WHERE p.payment_id = ?");
                $fetch->bind_param("i", $payment_id);
                $fetch->execute();
                $fetch_result = $fetch->get_result();

                if ($fetch_result->num_rows === 0) {
                    $fetch->close();
                    echo json_encode(['success' => false, 'message' => 'Payment not found']);
                    exit();
                }

                $pay_row = $fetch_result->fetch_assoc();
                $fetch->close();

                // RBAC
                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($pay_row['sub_sp'] ?? 0) !== $sp_id) {
                            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                        }
                    } elseif ((int)$pay_row['sub_owner'] !== $user_id) {
                        echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                    }
                }

                $del_sl = (int)$pay_row['subscription_sl'];

                $del_stmt = $conn->prepare("DELETE FROM payments WHERE payment_id = ?");
                $del_stmt->bind_param("i", $payment_id);

                if (!$del_stmt->execute()) {
                    $del_stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete payment']);
                    exit();
                }
                $del_stmt->close();

                // Recalculate subscription payment_status after deletion
                $sum_stmt2 = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE subscription_sl = ?");
                $sum_stmt2->bind_param("i", $del_sl);
                $sum_stmt2->execute();
                $sum_total2 = (float)$sum_stmt2->get_result()->fetch_assoc()['total'];
                $sum_stmt2->close();

                $tot_stmt2 = $conn->prepare("SELECT total_amount FROM subscriptions WHERE sl = ?");
                $tot_stmt2->bind_param("i", $del_sl);
                $tot_stmt2->execute();
                $sub_total2 = (float)$tot_stmt2->get_result()->fetch_assoc()['total_amount'];
                $tot_stmt2->close();

                if ($sum_total2 >= $sub_total2) {
                    $new_status2 = 'Paid';
                } elseif ($sum_total2 > 0) {
                    $new_status2 = 'Partial';
                } else {
                    $new_status2 = 'Unpaid';
                }

                $upd_stmt2 = $conn->prepare("UPDATE subscriptions SET payment_status = ? WHERE sl = ?");
                $upd_stmt2->bind_param("si", $new_status2, $del_sl);
                $upd_stmt2->execute();
                $upd_stmt2->close();

                logActivity($user_id, $username, 'Payment Deleted',
                    "Deleted payment ID $payment_id for subscription SL $del_sl ({$pay_row['invoice_no']} - {$pay_row['customer_name']}). Status => $new_status2");

                echo json_encode(['success' => true, 'message' => 'Payment deleted. Subscription status updated to ' . $new_status2 . '.']);
                exit();

            // ── 4. editPayment ────────────────────────────────────────────
            case 'editPayment':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $payment_id     = isset($_POST['payment_id'])     ? intval($_POST['payment_id'])    : 0;
                $amount         = isset($_POST['amount'])          ? floatval($_POST['amount'])       : 0;
                $payment_date   = isset($_POST['payment_date'])    ? trim($_POST['payment_date'])     : '';
                $payment_method = isset($_POST['payment_method'])  ? trim($_POST['payment_method'])   : '';
                $reference_no   = isset($_POST['reference_no'])    ? trim($_POST['reference_no'])     : '';
                $notes          = isset($_POST['notes'])           ? trim($_POST['notes'])            : '';

                if ($payment_id <= 0 || $amount <= 0 || empty($payment_date)) {
                    echo json_encode(['success' => false, 'message' => 'Payment ID, amount and date are required']);
                    exit();
                }

                $conn = getDBConnection();

                // fetch existing payment + subscription
                $fetch = $conn->prepare("SELECT p.subscription_sl, p.amount AS old_amount, p.added_by AS pay_added_by,
                                                s.invoice_no, s.customer_name, s.total_amount, s.added_by AS sub_owner, s.salesperson_id AS sub_sp
                                         FROM payments p
                                         JOIN subscriptions s ON p.subscription_sl = s.sl
                                         WHERE p.payment_id = ?");
                $fetch->bind_param("i", $payment_id);
                $fetch->execute();
                $fetch_result = $fetch->get_result();

                if ($fetch_result->num_rows === 0) {
                    $fetch->close();
                    echo json_encode(['success' => false, 'message' => 'Payment not found']);
                    exit();
                }

                $pay_row = $fetch_result->fetch_assoc();
                $fetch->close();

                // RBAC
                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($pay_row['sub_sp'] ?? 0) !== $sp_id) {
                            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                        }
                    } elseif ((int)$pay_row['sub_owner'] !== $user_id) {
                        echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                    }
                }

                $methodVal    = !empty($payment_method) ? $payment_method : null;
                $referenceVal = !empty($reference_no)   ? $reference_no   : null;
                $notesVal     = !empty($notes)          ? $notes          : null;

                $stmt = $conn->prepare("UPDATE payments SET amount = ?, payment_method = ?, payment_date = ?, reference_no = ?, notes = ? WHERE payment_id = ?");
                $stmt->bind_param("dssssi", $amount, $methodVal, $payment_date, $referenceVal, $notesVal, $payment_id);

                if (!$stmt->execute()) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update payment']);
                    exit();
                }
                $stmt->close();

                // recalc subscription payment_status
                $edit_sl = (int)$pay_row['subscription_sl'];

                $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE subscription_sl = ?");
                $sum_stmt->bind_param("i", $edit_sl);
                $sum_stmt->execute();
                $sum_total = (float)$sum_stmt->get_result()->fetch_assoc()['total'];
                $sum_stmt->close();

                $sub_total = (float)$pay_row['total_amount'];

                if ($sum_total >= $sub_total) {
                    $new_status = 'Paid';
                } elseif ($sum_total > 0) {
                    $new_status = 'Partial';
                } else {
                    $new_status = 'Unpaid';
                }

                $upd_stmt = $conn->prepare("UPDATE subscriptions SET payment_status = ? WHERE sl = ?");
                $upd_stmt->bind_param("si", $new_status, $edit_sl);
                $upd_stmt->execute();
                $upd_stmt->close();

                logActivity($user_id, $username, 'Payment Updated',
                    "Updated payment ID $payment_id (was {$pay_row['old_amount']}, now $amount) for SL $edit_sl ({$pay_row['invoice_no']} - {$pay_row['customer_name']}). Status => $new_status");

                echo json_encode(['success' => true, 'message' => 'Payment updated. Status: ' . $new_status . '.']);
                exit();

            // ── 6. getPaymentsBySubscription ───────────────────────────────
            case 'getPaymentsBySubscription':
                $sl = isset($_GET['sl']) ? intval($_GET['sl']) : 0;
                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
                    exit();
                }

                $conn = getDBConnection();

                // RBAC check
                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        $rbac2 = $conn->prepare("SELECT sl FROM subscriptions WHERE sl = ? AND salesperson_id = ?");
                        $rbac2->bind_param("ii", $sl, $sp_id);
                    } else {
                        $rbac2 = $conn->prepare("SELECT sl FROM subscriptions WHERE sl = ? AND added_by = ?");
                        $rbac2->bind_param("ii", $sl, $user_id);
                    }
                    $rbac2->execute();
                    if ($rbac2->get_result()->num_rows === 0) {
                        $rbac2->close();
                        echo json_encode(['success' => false, 'message' => 'Access denied']);
                        exit();
                    }
                    $rbac2->close();
                }

                $currency2 = getCurrency();

                $stmt = $conn->prepare(
                    "SELECT p.payment_id, p.amount, p.payment_method, p.payment_date,
                            p.reference_no, p.notes, p.created_at,
                            u.full_name AS added_by_name
                     FROM payments p
                     LEFT JOIN users u ON p.added_by = u.user_id
                     WHERE p.subscription_sl = ?
                     ORDER BY p.payment_id DESC"
                );
                $stmt->bind_param("i", $sl);
                $stmt->execute();
                $result = $stmt->get_result();

                $payments2 = [];
                while ($row = $result->fetch_assoc()) {
                    $payments2[] = [
                        'payment_id'     => (int)$row['payment_id'],
                        'amount'         => (float)$row['amount'],
                        'payment_method' => $row['payment_method'] ?? '',
                        'payment_date'   => $row['payment_date']
                                            ? date('M d, Y', strtotime($row['payment_date'])) : '',
                        'reference_no'   => $row['reference_no'] ?? '',
                        'notes'          => $row['notes'] ?? '',
                        'added_by_name'  => $row['added_by_name'] ?? '',
                        'created_at'     => $row['created_at']
                                            ? date('M d, Y H:i', strtotime($row['created_at'])) : ''
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $payments2, 'currency' => $currency2]);
                exit();

            // ── 7. getSubscriptionsList ────────────────────────────────────
            case 'getSubscriptionsList':
                $conn = getDBConnection();

                if ($role === 'admin') {
                    $stmt = $conn->prepare(
                        "SELECT sl, invoice_no, customer_name, total_amount
                         FROM subscriptions
                         ORDER BY sl DESC"
                    );
                } elseif ($role === 'salesperson' && $sp_id) {
                    $stmt = $conn->prepare(
                        "SELECT sl, invoice_no, customer_name, total_amount
                         FROM subscriptions
                         WHERE salesperson_id = ?
                         ORDER BY sl DESC"
                    );
                    $stmt->bind_param("i", $sp_id);
                } else {
                    $stmt = $conn->prepare(
                        "SELECT sl, invoice_no, customer_name, total_amount
                         FROM subscriptions
                         WHERE added_by = ?
                         ORDER BY sl DESC"
                    );
                    $stmt->bind_param("i", $user_id);
                }

                $stmt->execute();
                $result = $stmt->get_result();

                $subs = [];
                while ($row = $result->fetch_assoc()) {
                    $subs[] = [
                        'sl'            => (int)$row['sl'],
                        'invoice_no'    => $row['invoice_no'] ?? '',
                        'customer_name' => $row['customer_name'],
                        'total_amount'  => (float)$row['total_amount']
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $subs]);
                exit();

            case 'getPaymentMethods':
                $conn = getDBConnection();
                $methods = [];
                $res = $conn->query("SELECT option_value FROM dropdown_options WHERE dropdown_type='payment_method' AND is_active=1 ORDER BY display_order ASC, option_value ASC");
                if ($res) {
                    while ($r = $res->fetch_assoc()) $methods[] = $r['option_value'];
                }
                echo json_encode(['success' => true, 'data' => $methods]);
                exit();

            // refund a payment - admin only
            case 'refundPayment':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }
                if ($role !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
                    exit();
                }

                $payment_id    = isset($_POST['payment_id'])    ? intval($_POST['payment_id'])    : 0;
                $refund_amount = isset($_POST['refund_amount']) ? floatval($_POST['refund_amount']) : 0;
                $reason        = isset($_POST['reason'])        ? trim($_POST['reason'])           : '';

                if ($payment_id <= 0 || $refund_amount <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Valid payment ID and refund amount are required']);
                    exit();
                }

                $conn = getDBConnection();

                // fetch payment + subscription
                $fetch = $conn->prepare(
                    "SELECT p.payment_id, p.subscription_sl, p.amount AS paid_amount,
                            s.invoice_no, s.customer_name, s.total_amount
                     FROM payments p
                     JOIN subscriptions s ON p.subscription_sl = s.sl
                     WHERE p.payment_id = ?"
                );
                $fetch->bind_param("i", $payment_id);
                $fetch->execute();
                $fr = $fetch->get_result();

                if ($fr->num_rows === 0) {
                    $fetch->close();
                    echo json_encode(['success' => false, 'message' => 'Payment not found']);
                    exit();
                }

                $prow = $fr->fetch_assoc();
                $fetch->close();

                // check existing refunds for this payment
                $ref_sum_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS refunded FROM refunds WHERE payment_id = ?");
                $ref_sum_stmt->bind_param("i", $payment_id);
                $ref_sum_stmt->execute();
                $already_refunded = (float)$ref_sum_stmt->get_result()->fetch_assoc()['refunded'];
                $ref_sum_stmt->close();

                $max_refundable = (float)$prow['paid_amount'] - $already_refunded;
                if ($refund_amount > $max_refundable) {
                    echo json_encode(['success' => false, 'message' => 'Refund amount exceeds refundable balance (' . number_format($max_refundable, 3) . ')']);
                    exit();
                }

                $ref_sl = (int)$prow['subscription_sl'];
                $reasonVal = !empty($reason) ? $reason : null;

                $ins = $conn->prepare("INSERT INTO refunds (payment_id, subscription_sl, amount, reason, refunded_by) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("iidsi", $payment_id, $ref_sl, $refund_amount, $reasonVal, $user_id);

                if (!$ins->execute()) {
                    $ins->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to insert refund']);
                    exit();
                }
                $refund_id = $ins->insert_id;
                $ins->close();

                // recalc: total paid - total refunds vs subscription total
                $paid_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE subscription_sl = ?");
                $paid_stmt->bind_param("i", $ref_sl);
                $paid_stmt->execute();
                $total_paid = (float)$paid_stmt->get_result()->fetch_assoc()['total'];
                $paid_stmt->close();

                $ref_all_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM refunds WHERE subscription_sl = ?");
                $ref_all_stmt->bind_param("i", $ref_sl);
                $ref_all_stmt->execute();
                $total_refunded = (float)$ref_all_stmt->get_result()->fetch_assoc()['total'];
                $ref_all_stmt->close();

                $net_paid = $total_paid - $total_refunded;
                $sub_total = (float)$prow['total_amount'];

                if ($total_refunded >= $total_paid) {
                    $new_status = 'Refunded';
                } elseif ($net_paid >= $sub_total) {
                    $new_status = 'Paid';
                } elseif ($net_paid > 0) {
                    $new_status = 'Partial';
                } else {
                    $new_status = 'Unpaid';
                }

                $upd = $conn->prepare("UPDATE subscriptions SET payment_status = ? WHERE sl = ?");
                $upd->bind_param("si", $new_status, $ref_sl);
                $upd->execute();
                $upd->close();

                logActivity($user_id, $username, 'Refund Issued',
                    "Refund #{$refund_id} of {$refund_amount} for payment #{$payment_id}, SL {$ref_sl} ({$prow['invoice_no']} - {$prow['customer_name']}). Status => {$new_status}");

                echo json_encode([
                    'success'   => true,
                    'message'   => 'Refund of ' . number_format($refund_amount, 3) . ' processed. Status: ' . $new_status,
                    'refund_id' => $refund_id,
                    'status'    => $new_status
                ]);
                exit();

            // get refunds list
            case 'getRefunds':
                $conn     = getDBConnection();
                $currency = getCurrency();

                $sql = "SELECT r.refund_id, r.payment_id, r.subscription_sl, r.amount AS refund_amount,
                               r.reason, r.created_at,
                               p.amount AS original_payment, p.payment_date,
                               s.invoice_no, s.customer_name,
                               u.full_name AS refunded_by_name
                        FROM refunds r
                        JOIN payments p ON r.payment_id = p.payment_id
                        JOIN subscriptions s ON r.subscription_sl = s.sl
                        LEFT JOIN users u ON r.refunded_by = u.user_id";

                $conditions = [];
                $params     = [];
                $types      = '';

                // RBAC
                if ($role === 'salesperson' && $sp_id) {
                    $conditions[] = "s.salesperson_id = ?";
                    $params[]     = $sp_id;
                    $types       .= 'i';
                } elseif ($role !== 'admin') {
                    $conditions[] = "s.added_by = ?";
                    $params[]     = $user_id;
                    $types       .= 'i';
                }

                // optional filters
                if (!empty($_GET['payment_id'])) {
                    $conditions[] = "r.payment_id = ?";
                    $params[]     = intval($_GET['payment_id']);
                    $types       .= 'i';
                }
                if (!empty($_GET['subscription_sl'])) {
                    $conditions[] = "r.subscription_sl = ?";
                    $params[]     = intval($_GET['subscription_sl']);
                    $types       .= 'i';
                }

                if (!empty($conditions)) {
                    $sql .= " WHERE " . implode(' AND ', $conditions);
                }
                $sql .= " ORDER BY r.refund_id DESC";

                $stmt = $conn->prepare($sql);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                $refunds = [];
                while ($row = $result->fetch_assoc()) {
                    $refunds[] = [
                        'refund_id'         => (int)$row['refund_id'],
                        'payment_id'        => (int)$row['payment_id'],
                        'subscription_sl'   => (int)$row['subscription_sl'],
                        'invoice_no'        => $row['invoice_no'] ?? '',
                        'customer_name'     => $row['customer_name'] ?? '',
                        'original_payment'  => (float)$row['original_payment'],
                        'refund_amount'     => (float)$row['refund_amount'],
                        'reason'            => $row['reason'] ?? '',
                        'refunded_by_name'  => $row['refunded_by_name'] ?? '',
                        'payment_date'      => $row['payment_date']
                                               ? date('M d, Y', strtotime($row['payment_date'])) : '',
                        'created_at'        => $row['created_at']
                                               ? date('M d, Y H:i', strtotime($row['created_at'])) : ''
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $refunds, 'currency' => $currency]);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("payments.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// CSV import handler (multipart POST)
if (isset($_POST['action']) && $_POST['action'] === 'importPayments') {
    header('Content-Type: application/json');

    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin only']); exit();
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']); exit();
    }

    $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        echo json_encode(['success' => false, 'message' => 'Only .csv files allowed']); exit();
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Cannot read file']); exit();
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'Empty CSV file']); exit();
    }

    // normalize headers
    $header = array_map(function($h) { return strtolower(trim(str_replace(["\xEF\xBB\xBF", '"'], '', $h))); }, $header);

    // require invoice_no + amount
    foreach (['invoice_no', 'amount'] as $req) {
        if (!in_array($req, $header)) {
            fclose($handle);
            echo json_encode(['success' => false, 'message' => "Missing required column: $req"]); exit();
        }
    }

    $conn = getDBConnection();

    // build invoice_no → sl lookup (O(1))
    $invMap = [];
    $res = $conn->query("SELECT sl, invoice_no, total_amount, customer_name FROM subscriptions");
    while ($r = $res->fetch_assoc()) {
        $invMap[strtolower(trim($r['invoice_no']))] = $r;
    }

    $stmt = $conn->prepare("INSERT INTO payments (subscription_sl, amount, payment_method, payment_date, reference_no, notes, added_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $imported = 0; $skipped = 0; $errors = [];
    $lineNum = 1;

    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;
        if (count($row) < count($header)) $row = array_pad($row, count($header), '');
        $d = array_combine($header, array_slice($row, 0, count($header)));

        $invNo = trim($d['invoice_no'] ?? '');
        $amount = floatval($d['amount'] ?? 0);

        if (empty($invNo)) { $skipped++; continue; }
        if ($amount <= 0) { $errors[] = "Line $lineNum: invalid amount"; continue; }

        // find subscription
        $sub = $invMap[strtolower($invNo)] ?? null;
        if (!$sub) { $errors[] = "Line $lineNum: invoice '$invNo' not found"; continue; }

        $sl = (int)$sub['sl'];
        $payDate = !empty($d['payment_date']) ? trim($d['payment_date']) : date('Y-m-d');
        $method = !empty($d['payment_method']) ? trim($d['payment_method']) : null;
        $refNo = !empty($d['reference_no']) ? trim($d['reference_no']) : null;
        $notes = !empty($d['notes']) ? trim($d['notes']) : null;

        $stmt->bind_param("idssssi", $sl, $amount, $method, $payDate, $refNo, $notes, $user_id);
        if ($stmt->execute()) {
            $imported++;

            // auto-update payment_status
            $paidStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total_paid FROM payments WHERE subscription_sl = ?");
            $paidStmt->bind_param("i", $sl); $paidStmt->execute();
            $totalPaid = (float)$paidStmt->get_result()->fetch_assoc()['total_paid'];
            $paidStmt->close();

            $subTotal = (float)$sub['total_amount'];
            if ($totalPaid >= $subTotal) $newStatus = 'Paid';
            elseif ($totalPaid > 0) $newStatus = 'Partial';
            else $newStatus = 'Unpaid';

            $upd = $conn->prepare("UPDATE subscriptions SET payment_status = ?, payment_method = ?, payment_date = ? WHERE sl = ?");
            $upd->bind_param("sssi", $newStatus, $method, $payDate, $sl);
            $upd->execute(); $upd->close();
        } else {
            $errors[] = "Line $lineNum: " . $stmt->error;
        }
    }
    $stmt->close();
    fclose($handle);

    logActivity($user_id, $username, 'Payments Imported', "Imported $imported payments from CSV");

    $msg = "$imported payment(s) imported successfully.";
    if ($skipped > 0) $msg .= " $skipped skipped.";
    if (!empty($errors)) $msg .= " Errors: " . implode('; ', array_slice($errors, 0, 5));
    if (count($errors) > 5) $msg .= " ... and " . (count($errors) - 5) . " more";

    echo json_encode(['success' => $imported > 0, 'message' => $msg, 'imported' => $imported, 'skipped' => $skipped, 'errors' => count($errors)]);
    exit();
}

// ============================================================
// Render HTML page
// ============================================================
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
    <title>Payment Records - <?php echo htmlspecialchars($branding['site_name']); ?></title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <style>
        /* ── Payment status badges ───────────────────────────────────── */
        .pay-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .4px;
            white-space: nowrap;
        }
        .pay-badge.paid      { background: #d4edda; color: #155724; }
        .pay-badge.partial   { background: #fff3cd; color: #856404; }
        .pay-badge.unpaid    { background: #f8d7da; color: #721c24; }

        /* ── Payment method pill ─────────────────────────────────────── */
        .method-pill {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
            background: #e8f0fe;
            color: #1a56db;
            white-space: nowrap;
        }

        /* ── Searchable select wrapper ───────────────────────────────── */
        .searchable-select-wrapper {
            position: relative;
        }
        .searchable-select-wrapper .ss-search-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
            background: #fff;
        }
        .searchable-select-wrapper .ss-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 220px;
            overflow-y: auto;
            z-index: 9999;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,.12);
        }
        .searchable-select-wrapper .ss-dropdown.open { display: block; }
        .searchable-select-wrapper .ss-option {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
        }
        .searchable-select-wrapper .ss-option:hover,
        .searchable-select-wrapper .ss-option.highlighted { background: #e8f0fe; }
        .searchable-select-wrapper .ss-option.no-result    { color: #999; cursor: default; }

        /* ── Summary bar ─────────────────────────────────────────────── */
        .payments-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }
        .summary-card {
            flex: 1 1 160px;
            background: #fff;
            border: 1px solid #e0e7ef;
            border-radius: 10px;
            padding: 14px 18px;
            text-align: center;
            box-shadow: 0 1px 4px rgba(0,31,63,.06);
        }
        .summary-card .s-label {
            font-size: 12px;
            color: #7a8fa6;
            margin-bottom: 4px;
            font-weight: 500;
        }
        .summary-card .s-value {
            font-size: 20px;
            font-weight: 700;
            color: #001f3f;
        }
        .summary-card.accent .s-value { color: #0074D9; }

        /* ── Skeleton loaders ────────────────────────────────────────── */
        .skeleton-loader {
            padding: 20px 0;
        }
        .skeleton-row {
            display: flex;
            gap: 12px;
            margin-bottom: 10px;
        }
        .skeleton-cell {
            height: 18px;
            border-radius: 4px;
            background: linear-gradient(90deg, #e8edf2 25%, #f4f7fa 50%, #e8edf2 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
        }
        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* ── Responsive ──────────────────────────────────────────────── */
        @media (max-width: 600px) {
            .payments-summary { flex-direction: column; }
            .summary-card { flex: 1 1 auto; }
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
                <span>Payments</span>
            </div>

            <div class="header">
                <h1><i class="fas fa-money-bill-wave"></i> Payment Records</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <!-- Summary Cards -->
            <div class="payments-summary" id="summaryBar" style="display:none;">
                <div class="summary-card accent">
                    <div class="s-label"><i class="fas fa-list"></i> Total Payments</div>
                    <div class="s-value" id="sumCount">0</div>
                </div>
                <div class="summary-card">
                    <div class="s-label"><i class="fas fa-dollar-sign"></i> Total Collected</div>
                    <div class="s-value" id="sumAmount">0</div>
                </div>
                <div class="summary-card">
                    <div class="s-label"><i class="fas fa-check-circle"></i> Fully Paid Subs</div>
                    <div class="s-value" id="sumPaid">0</div>
                </div>
            </div>

            <!-- Skeleton Loader -->
            <div id="skeletonLoader" class="skeleton-loader">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="skeleton-row">
                    <div class="skeleton-cell" style="width:5%;"></div>
                    <div class="skeleton-cell" style="width:12%;"></div>
                    <div class="skeleton-cell" style="width:16%;"></div>
                    <div class="skeleton-cell" style="width:10%;"></div>
                    <div class="skeleton-cell" style="width:12%;"></div>
                    <div class="skeleton-cell" style="width:11%;"></div>
                    <div class="skeleton-cell" style="width:13%;"></div>
                    <div class="skeleton-cell" style="width:10%;"></div>
                    <div class="skeleton-cell" style="flex:1;"></div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Data Section -->
            <div class="data-section" id="dataSection" style="display:none;">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Payment Records</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadPayments()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Payment
                        </button>
                        <?php if ($role === 'admin'): ?>
                        <button class="btn btn-info" onclick="openImportModal()">
                            <i class="fas fa-file-import"></i> Import CSV
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section" id="filtersSection">
                    <div class="filters-header">
                        <h3><i class="fas fa-filter"></i> Filters</h3>
                        <button class="btn btn-secondary btn-sm" onclick="clearFilters()">
                            <i class="fas fa-times-circle"></i> Clear All
                        </button>
                    </div>
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-file-invoice"></i> Invoice No</label>
                            <input type="text" id="filterInvoice" class="filter-input" placeholder="Search invoice...">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-user"></i> Customer</label>
                            <input type="text" id="filterCustomer" class="filter-input" placeholder="Search customer...">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-credit-card"></i> Payment Method</label>
                            <select id="filterMethod" class="filter-input">
                                <option value="">All Methods</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Date From</label>
                            <input type="date" id="filterDateFrom" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Date To</label>
                            <input type="date" id="filterDateTo" class="filter-input">
                        </div>
                    </div>
                </div>

                <div class="table-scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                </div>
                <div class="table-responsive">
                    <table id="paymentsTable" class="display table-full-width"></table>
                </div>
            </div>

            <!-- Refund History -->
            <div class="data-section" id="refundSection" style="display:none; margin-top:24px;">
                <div class="section-header">
                    <h2><i class="fas fa-undo" style="color:#dc3545;"></i> Refund History</h2>
                    <button class="btn btn-primary btn-sm" onclick="loadRefunds()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                <div class="table-scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                </div>
                <div class="table-responsive">
                    <table id="refundsTable" class="display table-full-width"></table>
                </div>
            </div>

        </div><!-- /.main-content -->
    </div><!-- /.app-container -->

    <!-- ════════════════════════════════════════════════════════
         Add Payment Modal
    ══════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-money-bill-wave"></i> Add Payment</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" autocomplete="off">
                    <input type="hidden" id="formPaymentId" name="payment_id" value="">

                    <!-- Subscription searchable select -->
                    <div class="form-group">
                        <label><i class="fas fa-file-contract"></i> Subscription *</label>
                        <div class="searchable-select-wrapper" id="ssWrapper">
                            <input type="text" id="ssInput" class="ss-search-input"
                                   placeholder="Search invoice or customer..."
                                   autocomplete="off" oninput="filterSSOptions()"
                                   onfocus="openSSDropdown()" onclick="openSSDropdown()">
                            <input type="hidden" id="formSubscriptionSl" name="subscription_sl">
                            <div class="ss-dropdown" id="ssDropdown"></div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-dollar-sign"></i> Amount *</label>
                            <input type="number" id="formAmount" name="amount"
                                   required min="0.001" step="0.001"
                                   placeholder="0.000" style="font-size:16px;">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-credit-card"></i> Payment Method</label>
                            <select id="formMethod" name="payment_method" style="font-size:16px;">
                                <option value="">-- Select Method --</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Payment Date *</label>
                            <input type="date" id="formPaymentDate" name="payment_date"
                                   required style="font-size:16px;">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> Reference No</label>
                            <input type="text" id="formReferenceNo" name="reference_no"
                                   placeholder="Cheque / transfer reference..."
                                   style="font-size:16px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Notes</label>
                        <textarea id="formNotes" name="notes" rows="2"
                                  placeholder="Optional notes..." style="font-size:16px;"></textarea>
                    </div>

                    <!-- Subscription info preview -->
                    <div id="subInfoPreview" style="display:none; margin-bottom:12px;
                         padding:10px 14px; background:#e8f4fd; border-radius:8px;
                         border-left:4px solid #0074D9; font-size:13px; color:#001f3f;">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="formSubmitBtn">
                            <i class="fas fa-save"></i> Save Payment
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <?php if ($role === 'admin'): ?>
    <div class="modal-overlay" id="importModal">
        <div class="modal" onclick="event.stopPropagation()" style="max-width:640px;">
            <div class="modal-header">
                <h3><i class="fas fa-file-import"></i> Import Payments</h3>
                <button class="close-btn" onclick="closeImportModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p style="color:var(--text-secondary);font-size:14px;margin-bottom:16px;">Upload a CSV file with payment records. Each row must reference a valid <strong>invoice_no</strong> from an existing subscription. Payment status auto-updates based on total paid vs subscription amount.</p>

                <div style="margin-bottom:20px;">
                    <a href="?action=downloadPaymentTemplate" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-download"></i> Download CSV Template
                    </a>
                </div>

                <div class="about-table-wrapper" style="margin:0 0 20px 0;border-radius:4px;overflow:hidden;border:1px solid var(--input-border);">
                    <table class="about-roles-table" style="font-size:12px;margin:0;">
                        <thead>
                            <tr>
                                <th style="text-align:left;">Column</th>
                                <th>Type</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="text-align:left;font-weight:600;color:#dc3545;">invoice_no *</td>
                                <td>Text</td>
                                <td style="text-align:left;">Must match existing subscription</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;font-weight:600;color:#dc3545;">amount *</td>
                                <td>Decimal</td>
                                <td style="text-align:left;">Payment amount (e.g. 5000)</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">payment_method</td>
                                <td>Text</td>
                                <td style="text-align:left;">Cash, Bank Transfer, Online, etc.</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">payment_date</td>
                                <td>Date</td>
                                <td style="text-align:left;">YYYY-MM-DD format</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">reference_no</td>
                                <td>Text</td>
                                <td style="text-align:left;">Transaction reference</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">notes</td>
                                <td>Text</td>
                                <td style="text-align:left;">Optional remarks</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-file-csv"></i> Select CSV File *</label>
                    <input type="file" id="importFile" accept=".csv">
                </div>

                <div id="importResult" style="display:none;margin-top:16px;"></div>

                <div class="form-actions">
                    <button type="button" class="btn btn-primary" id="importBtn" onclick="submitImport()">
                        <i class="fas fa-upload"></i> Import
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeImportModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
    // ── PHP vars passed to JS ───────────────────────────────────────────
    var IS_ADMIN = <?php echo ($role === 'admin') ? 'true' : 'false'; ?>;

    // ── Lazy-load export deps ───────────────────────────────────────────
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

    // ── State ───────────────────────────────────────────────────────────
    var paymentsTable  = null;
    var paymentsData   = [];
    var currency       = '';
    var subscriptionsList = [];
    var editMode = false;
    var editPaymentId = 0;
    var refundsTable = null;
    var refundsData  = [];

    // ── Init ────────────────────────────────────────────────────────────
    $(document).ready(function() {
        loadPayments();
        loadSubscriptionsList();
        loadPaymentMethods();
        loadRefunds();
        setDefaultDate();
    });

    // populate payment method dropdowns from DB
    function loadPaymentMethods() {
        $.ajax({
            url: '?action=getPaymentMethods',
            method: 'GET',
            dataType: 'json',
            success: function(r) {
                if (!r.success || !r.data) return;
                var filterSel = document.getElementById('filterMethod');
                var formSel = document.getElementById('formMethod');
                r.data.forEach(function(m) {
                    filterSel.appendChild(new Option(m, m));
                    formSel.appendChild(new Option(m, m));
                });
            }
        });
    }

    function setDefaultDate() {
        var today = new Date();
        var y = today.getFullYear();
        var m = String(today.getMonth() + 1).padStart(2, '0');
        var d = String(today.getDate()).padStart(2, '0');
        document.getElementById('formPaymentDate').value = y + '-' + m + '-' + d;
    }

    // ── Load payments via AJAX ──────────────────────────────────────────
    function loadPayments() {
        document.getElementById('skeletonLoader').style.display = 'block';
        document.getElementById('dataSection').style.display    = 'none';
        document.getElementById('summaryBar').style.display     = 'none';

        $.ajax({
            url: '?action=getPayments',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                document.getElementById('skeletonLoader').style.display = 'none';

                if (response.success) {
                    paymentsData = response.data;
                    currency     = response.currency || '';
                    document.getElementById('dataSection').style.display = 'block';
                    updateSummaryBar(paymentsData, currency);
                    initializeDataTable(paymentsData);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load payments' });
                }
            },
            error: function(xhr, status, error) {
                document.getElementById('skeletonLoader').style.display = 'none';
                console.error('AJAX Error:', error);
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
            }
        });
    }

    // ── Summary bar ─────────────────────────────────────────────────────
    function updateSummaryBar(data, cur) {
        var total    = data.length;
        var sumAmt   = data.reduce(function(acc, r) { return acc + r.amount; }, 0);

        document.getElementById('sumCount').textContent  = total;
        document.getElementById('sumAmount').textContent = cur + ' ' + sumAmt.toLocaleString('en', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

        // count unique subscription_sl where the subscription is fully paid
        // We derive this from the subscriptions list if available, otherwise skip
        document.getElementById('summaryBar').style.display = 'flex';
    }

    // ── DataTable init ──────────────────────────────────────────────────
    function initializeDataTable(data) {
        if (paymentsTable) {
            paymentsTable.destroy();
            $('#paymentsTable').empty();
        }

        setTimeout(function() {
            // Build columns
            var columns = [
                { data: 'payment_id',     title: 'ID' },
                { data: 'invoice_no',     title: 'Invoice No' },
                { data: 'customer_name',  title: 'Customer' },
                {
                    data: 'amount',
                    title: 'Amount',
                    render: function(val) {
                        return '<strong>' + currency + ' ' + parseFloat(val).toLocaleString('en', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + '</strong>';
                    }
                },
                {
                    data: 'payment_method',
                    title: 'Method',
                    render: function(val) {
                        if (!val) return '<span style="color:#aaa;">—</span>';
                        return '<span class="method-pill">' + escHtml(val) + '</span>';
                    }
                },
                { data: 'payment_date',  title: 'Payment Date' },
                {
                    data: 'reference_no',
                    title: 'Reference No',
                    render: function(val) {
                        return val ? escHtml(val) : '<span style="color:#aaa;">—</span>';
                    }
                },
                {
                    data: 'added_by_name',
                    title: 'Added By',
                    render: function(val) {
                        return val ? escHtml(val) : '<span style="color:#aaa;">—</span>';
                    }
                },
                { data: 'created_at', title: 'Created' }
            ];

            // Actions column
            columns.push({
                data: null,
                title: 'Actions',
                orderable: false,
                render: function(data, type, row) {
                    var btns = '<button class="action-icon edit-icon" title="Edit Payment" ' +
                            'onclick="openEditModal(' + row.payment_id + ')">' +
                            '<i class="fas fa-edit"></i></button> ';
                    btns += '<button class="action-icon delete-icon" title="Delete Payment" ' +
                            'onclick="deletePayment(' + row.payment_id + ', \'' +
                            escHtml(row.invoice_no).replace(/'/g, "\\'") + '\', \'' +
                            escHtml(row.customer_name).replace(/'/g, "\\'") + '\')">' +
                            '<i class="fas fa-trash"></i></button>';
                    if (IS_ADMIN) {
                        btns += ' <button class="action-icon" onclick="refundPayment(' + row.payment_id + ', ' + row.amount + ')" title="Refund" style="color:#dc3545;"><i class="fas fa-undo"></i></button>';
                    }
                    return btns;
                }
            });

            paymentsTable = $('#paymentsTable').DataTable({
                data: data,
                destroy: true,
                columns: columns,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                responsive: true,
                dom: 'Blfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
                    },
                    {
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        action: function(e, dt, node, config) {
                            loadExportDeps(function() {
                                $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                            });
                        },
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
                    }
                ],
                order: [[0, 'desc']]
            });

            // Attach filter listeners
            $('#filterInvoice, #filterCustomer').on('keyup', applyFilters);
            $('#filterMethod, #filterDateFrom, #filterDateTo').on('change', applyFilters);

        }, 100);
    }

    // ── Custom filters ──────────────────────────────────────────────────
    function applyFilters() {
        if (!paymentsTable) return;

        $.fn.dataTable.ext.search = [];

        var invoice  = document.getElementById('filterInvoice').value.toLowerCase().trim();
        var customer = document.getElementById('filterCustomer').value.toLowerCase().trim();
        var method   = document.getElementById('filterMethod').value;
        var dateFrom = document.getElementById('filterDateFrom').value;
        var dateTo   = document.getElementById('filterDateTo').value;

        $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
            var row = paymentsData[dataIndex];
            if (!row) return true;

            if (invoice  && row.invoice_no.toLowerCase().indexOf(invoice)    === -1) return false;
            if (customer && row.customer_name.toLowerCase().indexOf(customer) === -1) return false;
            if (method   && row.payment_method !== method)                            return false;

            if (dateFrom || dateTo) {
                var raw = row.payment_date_raw;
                if (raw) {
                    if (dateFrom && raw < dateFrom) return false;
                    if (dateTo   && raw > dateTo)   return false;
                } else {
                    if (dateFrom || dateTo) return false;
                }
            }

            return true;
        });

        paymentsTable.draw();
    }

    function clearFilters() {
        document.getElementById('filterInvoice').value  = '';
        document.getElementById('filterCustomer').value = '';
        document.getElementById('filterMethod').value   = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value   = '';

        if (paymentsTable) {
            $.fn.dataTable.ext.search = [];
            paymentsTable.draw();
        }
    }

    // ── Load subscriptions list for modal ───────────────────────────────
    function loadSubscriptionsList() {
        $.ajax({
            url: '?action=getSubscriptionsList',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    subscriptionsList = response.data;
                    renderSSOptions(subscriptionsList);
                }
            },
            error: function() { /* silent */ }
        });
    }

    // ── Modal open / close ──────────────────────────────────────────────
    function openAddModal() {
        editMode = false;
        editPaymentId = 0;
        document.getElementById('paymentForm').reset();
        document.getElementById('formPaymentId').value = '';
        document.getElementById('formSubscriptionSl').value = '';
        document.getElementById('ssInput').value = '';
        document.getElementById('ssInput').readOnly = false;
        document.getElementById('ssInput').style.background = '#fff';
        document.getElementById('subInfoPreview').style.display = 'none';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-money-bill-wave"></i> Add Payment';
        document.getElementById('formSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Save Payment';
        setDefaultDate();
        renderSSOptions(subscriptionsList);

        document.getElementById('paymentModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('paymentModal').classList.remove('active');
        document.getElementById('ssDropdown').classList.remove('open');
    }

    // Close on overlay click
    document.getElementById('paymentModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        var wrapper = document.getElementById('ssWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            document.getElementById('ssDropdown').classList.remove('open');
        }
    });

    // ── Searchable select ───────────────────────────────────────────────
    function renderSSOptions(list, searchTerm) {
        var dropdown = document.getElementById('ssDropdown');
        dropdown.innerHTML = '';

        var term = (searchTerm || '').toLowerCase().trim();
        var filtered = list.filter(function(s) {
            var text = ((s.invoice_no || '') + ' ' + s.customer_name).toLowerCase();
            return !term || text.indexOf(term) !== -1;
        });

        if (filtered.length === 0) {
            var no = document.createElement('div');
            no.className = 'ss-option no-result';
            no.textContent = 'No subscriptions found';
            dropdown.appendChild(no);
            return;
        }

        filtered.forEach(function(s) {
            var opt = document.createElement('div');
            opt.className = 'ss-option';
            opt.textContent = (s.invoice_no ? s.invoice_no + ' — ' : '') + s.customer_name;
            opt.dataset.sl = s.sl;
            opt.dataset.label = (s.invoice_no ? s.invoice_no + ' — ' : '') + s.customer_name;
            opt.dataset.total = s.total_amount;
            opt.addEventListener('click', function() {
                selectSSOption(this);
            });
            dropdown.appendChild(opt);
        });
    }

    function selectSSOption(el) {
        document.getElementById('ssInput').value            = el.dataset.label;
        document.getElementById('formSubscriptionSl').value = el.dataset.sl;
        document.getElementById('ssDropdown').classList.remove('open');

        // Show subscription info preview
        var preview = document.getElementById('subInfoPreview');
        preview.innerHTML = '<i class="fas fa-info-circle" style="color:#0074D9;"></i> ' +
            '<strong>' + escHtml(el.dataset.label) + '</strong>' +
            ' &nbsp;|&nbsp; Total Amount: <strong>' + currency + ' ' +
            parseFloat(el.dataset.total).toLocaleString('en', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + '</strong>';
        preview.style.display = 'block';
    }

    function openSSDropdown() {
        document.getElementById('ssDropdown').classList.add('open');
        filterSSOptions();
    }

    function filterSSOptions() {
        var term = document.getElementById('ssInput').value;
        renderSSOptions(subscriptionsList, term);
        document.getElementById('ssDropdown').classList.add('open');
    }

    // ── Open edit modal ────────────────────────────────────────────────
    function openEditModal(paymentId) {
        var row = paymentsData.find(function(p) { return p.payment_id === paymentId; });
        if (!row) { Swal.fire({ icon: 'error', title: 'Error', text: 'Payment not found' }); return; }

        editMode = true;
        editPaymentId = paymentId;

        document.getElementById('paymentForm').reset();
        document.getElementById('formPaymentId').value = paymentId;
        document.getElementById('formSubscriptionSl').value = row.subscription_sl;

        // subscription field readonly in edit mode
        var label = (row.invoice_no ? row.invoice_no + ' — ' : '') + row.customer_name;
        document.getElementById('ssInput').value = label;
        document.getElementById('ssInput').readOnly = true;
        document.getElementById('ssInput').style.background = '#f0f0f0';
        document.getElementById('ssDropdown').classList.remove('open');

        // show preview
        var preview = document.getElementById('subInfoPreview');
        preview.innerHTML = '<i class="fas fa-info-circle" style="color:#0074D9;"></i> ' +
            '<strong>' + escHtml(label) + '</strong>' +
            ' &nbsp;|&nbsp; Total Amount: <strong>' + currency + ' ' +
            parseFloat(row.total_amount).toLocaleString('en', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + '</strong>';
        preview.style.display = 'block';

        // fill fields
        document.getElementById('formAmount').value = row.amount;
        document.getElementById('formMethod').value = row.payment_method || '';
        document.getElementById('formPaymentDate').value = row.payment_date_raw || '';
        document.getElementById('formReferenceNo').value = row.reference_no || '';
        document.getElementById('formNotes').value = row.notes || '';

        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Payment';
        document.getElementById('formSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update Payment';

        document.getElementById('paymentModal').classList.add('active');
    }

    // ── Form submit ─────────────────────────────────────────────────────
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();

        if (!editMode) {
            var sl = document.getElementById('formSubscriptionSl').value;
            if (!sl) {
                Swal.fire({ icon: 'warning', title: 'Required', text: 'Please select a subscription.' });
                return;
            }
        }

        var formData = new FormData(this);
        var action = editMode ? 'editPayment' : 'addPayment';

        Swal.fire({
            title: editMode ? 'Updating...' : 'Saving...',
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
                        timer: 2500,
                        showConfirmButton: false
                    });
                    closeModal();
                    setTimeout(function() { loadPayments(); }, 100);
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

    // ── Delete payment ──────────────────────────────────────────────────
    function deletePayment(paymentId, invoiceNo, customerName) {
        Swal.fire({
            icon: 'warning',
            title: 'Delete Payment?',
            html: 'Invoice: <strong>' + escHtml(invoiceNo) + '</strong><br>Customer: <strong>' + escHtml(customerName) + '</strong><br><br>This will recalculate the subscription payment status.',
            showCancelButton: true,
            confirmButtonColor: '#ea4335',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            var formData = new FormData();
            formData.append('payment_id', paymentId);

            $.ajax({
                url: '?action=deletePayment',
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
                            timer: 2500,
                            showConfirmButton: false
                        });
                        setTimeout(function() { loadPayments(); }, 100);
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
    }

    // ── Refund payment (admin only) ────────────────────────────────────
    function refundPayment(paymentId, maxAmount) {
        Swal.fire({
            title: 'Issue Refund',
            html:
                '<div style="text-align:left;">' +
                '<label style="font-weight:600;font-size:13px;">Refund Amount *</label>' +
                '<input type="number" id="swalRefundAmt" class="swal2-input" value="' + maxAmount + '" min="0.001" step="0.001" style="font-size:16px;">' +
                '<label style="font-weight:600;font-size:13px;">Reason</label>' +
                '<textarea id="swalRefundReason" class="swal2-textarea" placeholder="Reason for refund..." style="font-size:16px;"></textarea>' +
                '</div>',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-undo"></i> Process Refund',
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Cancel',
            focusConfirm: false,
            preConfirm: function() {
                var amt = parseFloat(document.getElementById('swalRefundAmt').value);
                var reason = document.getElementById('swalRefundReason').value.trim();
                if (!amt || amt <= 0) {
                    Swal.showValidationMessage('Enter a valid refund amount');
                    return false;
                }
                if (amt > maxAmount) {
                    Swal.showValidationMessage('Amount cannot exceed ' + maxAmount.toFixed(3));
                    return false;
                }
                return { amount: amt, reason: reason };
            }
        }).then(function(result) {
            if (!result.isConfirmed) return;

            Swal.fire({ title: 'Processing refund...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

            var fd = new FormData();
            fd.append('payment_id', paymentId);
            fd.append('refund_amount', result.value.amount);
            fd.append('reason', result.value.reason);

            $.ajax({
                url: '?action=refundPayment',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Refunded', text: res.message, timer: 2500, showConfirmButton: false });
                        setTimeout(function() { loadPayments(); loadRefunds(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                },
                error: function(xhr, status, err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + err });
                }
            });
        });
    }

    // ── Load refunds ────────────────────────────────────────────────────
    function loadRefunds() {
        $.ajax({
            url: '?action=getRefunds',
            method: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    refundsData = res.data;
                    if (refundsData.length > 0) {
                        document.getElementById('refundSection').style.display = 'block';
                        initRefundsTable(refundsData, res.currency || currency);
                    } else {
                        document.getElementById('refundSection').style.display = 'none';
                    }
                }
            },
            error: function() { /* silent */ }
        });
    }

    function initRefundsTable(data, cur) {
        if (refundsTable) {
            refundsTable.destroy();
            $('#refundsTable').empty();
        }

        setTimeout(function() {
            refundsTable = $('#refundsTable').DataTable({
                data: data,
                destroy: true,
                columns: [
                    { data: 'refund_id', title: 'Refund ID' },
                    { data: 'invoice_no', title: 'Invoice No' },
                    { data: 'customer_name', title: 'Customer' },
                    {
                        data: 'original_payment',
                        title: 'Original Payment',
                        render: function(val) {
                            return cur + ' ' + parseFloat(val).toLocaleString('en', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
                        }
                    },
                    {
                        data: 'refund_amount',
                        title: 'Refund Amount',
                        render: function(val) {
                            return '<strong style="color:#dc3545;">' + cur + ' ' + parseFloat(val).toLocaleString('en', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + '</strong>';
                        }
                    },
                    {
                        data: 'reason',
                        title: 'Reason',
                        render: function(val) { return val ? escHtml(val) : '<span style="color:#aaa;">—</span>'; }
                    },
                    {
                        data: 'refunded_by_name',
                        title: 'Refunded By',
                        render: function(val) { return val ? escHtml(val) : '<span style="color:#aaa;">—</span>'; }
                    },
                    { data: 'created_at', title: 'Date' }
                ],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                responsive: true,
                dom: 'Blfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                    },
                    {
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        action: function(e, dt, node, config) {
                            loadExportDeps(function() {
                                $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                            });
                        },
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
                    }
                ],
                order: [[0, 'desc']]
            });
        }, 100);
    }

    // ── Import CSV ───────────────────────────────────────────────────────
    <?php if ($role === 'admin'): ?>
    function openImportModal() {
        document.getElementById('importFile').value = '';
        document.getElementById('importResult').style.display = 'none';
        document.getElementById('importResult').innerHTML = '';
        document.getElementById('importBtn').disabled = false;
        document.getElementById('importBtn').innerHTML = '<i class="fas fa-upload"></i> Import';
        document.getElementById('importModal').classList.add('active');
    }

    function closeImportModal() {
        document.getElementById('importModal').classList.remove('active');
    }

    document.getElementById('importModal').addEventListener('click', function(e) {
        if (e.target === this) closeImportModal();
    });

    function submitImport() {
        var fileInput = document.getElementById('importFile');
        if (!fileInput.files.length) {
            Swal.fire({ icon: 'warning', text: 'Please select a CSV file' });
            return;
        }
        if (!fileInput.files[0].name.toLowerCase().endsWith('.csv')) {
            Swal.fire({ icon: 'warning', text: 'Only .csv files allowed' });
            return;
        }

        var formData = new FormData();
        formData.append('action', 'importPayments');
        formData.append('csv_file', fileInput.files[0]);

        var btn = document.getElementById('importBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

        $.ajax({
            url: window.location.pathname,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(r) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Import';
                var resDiv = document.getElementById('importResult');

                if (r.success) {
                    var html = '<div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;padding:14px 16px;font-size:13px;">';
                    html += '<div style="font-weight:600;color:#155724;margin-bottom:6px;"><i class="fas fa-check-circle"></i> Import Complete</div>';
                    html += '<div style="color:#155724;">' + escHtml(r.message) + '</div>';
                    if (r.errors && r.errors > 0) {
                        html += '<div style="margin-top:6px;color:#856404;font-size:12px;">' + r.errors + ' row(s) had errors and were skipped</div>';
                    }
                    html += '</div>';
                    resDiv.innerHTML = html;
                    resDiv.style.display = '';
                    if (r.imported > 0) { loadPayments(); loadRefunds(); }
                } else {
                    var html = '<div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;padding:14px 16px;font-size:13px;">';
                    html += '<div style="font-weight:600;color:#721c24;"><i class="fas fa-exclamation-circle"></i> ' + escHtml(r.message) + '</div>';
                    html += '</div>';
                    resDiv.innerHTML = html;
                    resDiv.style.display = '';
                }
            },
            error: function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Import';
                Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error' });
            }
        });
    }
    <?php endif; ?>

    // ── Utility ─────────────────────────────────────────────────────────
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    </script>
</body>
</html>
