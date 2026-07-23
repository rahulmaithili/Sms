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
$current_page = 'subscriptions';

// CSV template download (before JSON header)
if (isset($_GET['action']) && $_GET['action'] === 'downloadSubscriptionTemplate') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscriptions_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['customer_name','invoice_no','invoice_date','starting_date','expiry_date','product_description','selling_price','purchase_price','tax_amount','total_amount','payment_status','payment_method','payment_date','priority','user_qty','license_duration','supplier_name','supplier_email','supplier_phone','contract_reference','remarks','auto_renew']);
    fputcsv($out, ['ABC Corporation','INV-2026-00001','2026-03-01','2026-03-01','2027-03-01','Annual Software License',10000,8000,0,10000,'Paid','Bank Transfer','2026-03-01','High',5,'1 Year','Vendor Co','vendor@demo.com','04231234567','REF-001','First year license',0]);
    fputcsv($out, ['XYZ Trading','','2026-03-15','2026-03-15','2026-09-15','Cloud Hosting 6 Months',5000,4000,0,5000,'Unpaid','','','Medium',1,'6 Months','','','','','',0]);
    fclose($out);
    exit();
}

// download doc — streams file, not JSON
if (isset($_GET['action']) && $_GET['action'] === 'downloadDocument') {
    $doc_id = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;
    if ($doc_id <= 0) { http_response_code(400); echo 'Invalid document ID'; exit(); }

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT d.*, s.added_by, s.salesperson_id FROM documents d JOIN subscriptions s ON d.subscription_sl = s.sl WHERE d.document_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doc) { http_response_code(404); echo 'Document not found'; exit(); }

    // RBAC
    if ($role !== 'admin') {
        if ($role === 'salesperson' && $sp_id) {
            if ((int)($doc['salesperson_id'] ?? 0) !== $sp_id) { http_response_code(403); echo 'Access denied'; exit(); }
        } elseif ((int)$doc['added_by'] !== $user_id) { http_response_code(403); echo 'Access denied'; exit(); }
    }

    $file_path = __DIR__ . '/uploads/documents/' . $doc['file_name'];
    if (!file_exists($file_path)) { http_response_code(404); echo 'File not found on disk'; exit(); }

    header('Content-Type: ' . ($doc['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($doc['original_name']) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($file_path);
    exit();
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            case 'getSubscriptions':
                $conn = getDBConnection();
                $currency = getCurrency();

                $sql = "SELECT s.sl, s.customer_name, s.invoice_no, s.renewal_invoice,
                            s.customer_id, cust.phone AS customer_phone,
                            s.product_id, p.product_name, p.color_code,
                            s.invoice_date, s.product_key, s.user_qty, s.license_duration,
                            s.starting_date, s.expiry_date, s.product_description,
                            s.selling_price, s.purchase_price, s.tax_amount, s.total_amount,
                            s.payment_status, s.payment_method, s.payment_date, s.auto_renew,
                            s.priority, s.supplier_name, s.supplier_email, s.supplier_phone,
                            s.contract_reference, s.attachment_url, s.remarks,
                            s.added_by, u.full_name AS added_by_name,
                            s.salesperson_id, sp.name AS salesperson_name,
                            s.added_date, s.updated_at,
                            s.subscription_status, s.paused_at, s.cancelled_at, s.cancel_reason,
                            s.currency_code
                        FROM subscriptions s
                        LEFT JOIN products p ON s.product_id = p.product_id
                        LEFT JOIN users u ON s.added_by = u.user_id
                        LEFT JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id
                        LEFT JOIN customers cust ON s.customer_id = cust.customer_id";

                if ($role === 'admin') {
                    $stmt = $conn->prepare($sql . " ORDER BY s.sl DESC");
                } elseif ($role === 'salesperson' && $sp_id) {
                    $sql .= " WHERE s.salesperson_id = ?";
                    $stmt = $conn->prepare($sql . " ORDER BY s.sl DESC");
                    $stmt->bind_param("i", $sp_id);
                } else {
                    $sql .= " WHERE s.added_by = ?";
                    $stmt = $conn->prepare($sql . " ORDER BY s.sl DESC");
                    $stmt->bind_param("i", $user_id);
                }

                $stmt->execute();
                $result = $stmt->get_result();

                $subscriptions = [];
                while ($row = $result->fetch_assoc()) {
                    $status = getSubscriptionStatus($row['expiry_date']);
                    $profit = ((float)$row['selling_price'] - (float)$row['tax_amount']) - (float)$row['purchase_price'];

                    // Days left calculation
                    $days_left = null;
                    if (!empty($row['expiry_date'])) {
                        $now = new DateTime();
                        $expiry = new DateTime($row['expiry_date']);
                        $days_left = (int)$now->diff($expiry)->format('%r%a');
                    }

                    $subscriptions[] = [
                        'sl'                  => (int)$row['sl'],
                        'customer_name'       => $row['customer_name'],
                        'customer_phone'      => $row['customer_phone'] ?? '',
                        'invoice_no'          => $row['invoice_no'] ?? '',
                        'renewal_invoice'     => $row['renewal_invoice'] ?? '',
                        'product_id'          => (int)$row['product_id'],
                        'product_name'        => $row['product_name'] ?? '',
                        'color_code'          => $row['color_code'] ?? '#0078D4',
                        'invoice_date'        => $row['invoice_date'] ? date('M d, Y', strtotime($row['invoice_date'])) : '',
                        'invoice_date_raw'    => $row['invoice_date'] ?? '',
                        'product_key'         => $row['product_key'] ?? '',
                        'user_qty'            => (int)$row['user_qty'],
                        'license_duration'    => $row['license_duration'] ?? '',
                        'starting_date'       => $row['starting_date'] ? date('M d, Y', strtotime($row['starting_date'])) : '',
                        'starting_date_raw'   => $row['starting_date'] ?? '',
                        'expiry_date'         => $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '',
                        'expiry_date_raw'     => $row['expiry_date'] ?? '',
                        'product_description' => $row['product_description'] ?? '',
                        'selling_price'       => (float)$row['selling_price'],
                        'purchase_price'      => (float)$row['purchase_price'],
                        'tax_amount'          => (float)$row['tax_amount'],
                        'total_amount'        => (float)$row['total_amount'],
                        'profit'              => $profit,
                        'payment_status'      => $row['payment_status'] ?? 'Unpaid',
                        'payment_method'      => $row['payment_method'] ?? '',
                        'payment_date'        => $row['payment_date'] ? date('M d, Y', strtotime($row['payment_date'])) : '',
                        'payment_date_raw'    => $row['payment_date'] ?? '',
                        'auto_renew'          => (bool)$row['auto_renew'],
                        'priority'            => $row['priority'] ?? 'Medium',
                        'supplier_name'       => $row['supplier_name'] ?? '',
                        'supplier_email'      => $row['supplier_email'] ?? '',
                        'supplier_phone'      => $row['supplier_phone'] ?? '',
                        'contract_reference'  => $row['contract_reference'] ?? '',
                        'attachment_url'      => $row['attachment_url'] ?? '',
                        'remarks'             => $row['remarks'] ?? '',
                        'added_by'            => (int)$row['added_by'],
                        'added_by_name'       => $row['added_by_name'] ?? '',
                        'salesperson_id'      => (int)$row['salesperson_id'],
                        'salesperson_name'    => $row['salesperson_name'] ?? '',
                        'added_date'          => $row['added_date'] ? date('M d, Y H:i', strtotime($row['added_date'])) : '',
                        'added_date_raw'      => $row['added_date'] ?? '',
                        'updated_at'          => $row['updated_at'] ? date('M d, Y H:i', strtotime($row['updated_at'])) : '',
                        'days_left'           => $days_left,
                        'status_label'        => $status['label'],
                        'status_class'        => $status['class'],
                        'subscription_status' => $row['subscription_status'] ?? 'active',
                        'paused_at'           => $row['paused_at'] ?? '',
                        'cancelled_at'        => $row['cancelled_at'] ?? '',
                        'cancel_reason'       => $row['cancel_reason'] ?? '',
                        'currency_code'       => $row['currency_code'] ?? ''
                    ];
                }

                $stmt->close();
                echo json_encode(['success' => true, 'data' => $subscriptions, 'currency' => $currency]);
                exit();

            case 'getSubscription':
                $sl = isset($_GET['sl']) ? intval($_GET['sl']) : 0;
                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT s.*, p.product_name, p.color_code, u.full_name AS added_by_name, sp.name AS salesperson_name
                    FROM subscriptions s
                    LEFT JOIN products p ON s.product_id = p.product_id
                    LEFT JOIN users u ON s.added_by = u.user_id
                    LEFT JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id
                    WHERE s.sl = ?");
                $stmt->bind_param("i", $sl);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
                    exit();
                }

                $row = $result->fetch_assoc();
                $stmt->close();

                // RBAC ownership check for user role
                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($row['salesperson_id'] ?? 0) !== $sp_id) {
                            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                        }
                    } elseif ((int)$row['added_by'] !== $user_id) {
                        echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                    }
                }

                echo json_encode(['success' => true, 'data' => $row]);
                exit();

            case 'deleteSubscription':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                if ($role !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
                    exit();
                }

                $sl = isset($_POST['sl']) ? intval($_POST['sl']) : 0;
                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch details for logging
                $stmt = $conn->prepare("SELECT customer_name, invoice_no FROM subscriptions WHERE sl = ?");
                $stmt->bind_param("i", $sl);
                $stmt->execute();
                $result = $stmt->get_result();
                $deletedName = '';
                $deletedInvoice = '';
                if ($result->num_rows > 0) {
                    $delRow = $result->fetch_assoc();
                    $deletedName = $delRow['customer_name'];
                    $deletedInvoice = $delRow['invoice_no'];
                }
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM subscriptions WHERE sl = ?");
                $stmt->bind_param("i", $sl);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Subscription Deleted', "Deleted subscription: $deletedName (Invoice: $deletedInvoice)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Subscription deleted successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete subscription']);
                }
                exit();

            case 'bulkDeleteSubscriptions':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                if ($role !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
                    exit();
                }

                $ids = isset($_POST['ids']) ? $_POST['ids'] : '';
                if (empty($ids)) {
                    echo json_encode(['success' => false, 'message' => 'No subscriptions selected']);
                    exit();
                }

                // Sanitize: only allow comma-separated integers
                $idArray = array_filter(array_map('intval', explode(',', $ids)));
                if (empty($idArray)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid IDs']);
                    exit();
                }

                $conn = getDBConnection();
                $placeholders = implode(',', array_fill(0, count($idArray), '?'));
                $types = str_repeat('i', count($idArray));

                $stmt = $conn->prepare("DELETE FROM subscriptions WHERE sl IN ($placeholders)");
                $stmt->bind_param($types, ...$idArray);

                if ($stmt->execute()) {
                    $count = $stmt->affected_rows;
                    logActivity($user_id, $username, 'Bulk Delete Subscriptions', "Deleted $count subscriptions (IDs: $ids)");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => "$count subscription(s) deleted successfully"]);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete subscriptions']);
                }
                exit();

            case 'updatePaymentStatus':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $sl = isset($_POST['sl']) ? intval($_POST['sl']) : 0;
                $payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : '';

                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
                    exit();
                }

                $allowed_statuses = ['Paid', 'Unpaid', 'Partial', 'Refunded'];
                if (!in_array($payment_status, $allowed_statuses)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid payment status']);
                    exit();
                }

                $conn = getDBConnection();

                // RBAC ownership check
                if ($role !== 'admin') {
                    $chk = $conn->prepare("SELECT added_by, salesperson_id FROM subscriptions WHERE sl = ?");
                    $chk->bind_param("i", $sl);
                    $chk->execute();
                    $chkResult = $chk->get_result();
                    if ($chkResult->num_rows === 0) {
                        $chk->close();
                        echo json_encode(['success' => false, 'message' => 'Subscription not found']); exit();
                    }
                    $owner = $chkResult->fetch_assoc(); $chk->close();
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($owner['salesperson_id'] ?? 0) !== $sp_id) {
                            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                        }
                    } elseif ((int)$owner['added_by'] !== $user_id) {
                        echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                    }
                }

                // If setting to Paid, auto-set payment_date to today
                if ($payment_status === 'Paid') {
                    $today = date('Y-m-d');
                    $stmt = $conn->prepare("UPDATE subscriptions SET payment_status = ?, payment_date = ?, updated_at = NOW() WHERE sl = ?");
                    $stmt->bind_param("ssi", $payment_status, $today, $sl);
                } else {
                    $stmt = $conn->prepare("UPDATE subscriptions SET payment_status = ?, updated_at = NOW() WHERE sl = ?");
                    $stmt->bind_param("si", $payment_status, $sl);
                }

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Payment Status Updated', "Updated payment status to '$payment_status' for subscription SL#$sl");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Payment status updated successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update payment status']);
                }
                exit();

            case 'sendManualReminder':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $sl = isset($_POST['sl']) ? intval($_POST['sl']) : 0;
                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch subscription details
                $stmt = $conn->prepare("SELECT s.*, u.full_name AS owner_name, u.email AS owner_email
                    FROM subscriptions s
                    LEFT JOIN users u ON s.added_by = u.user_id
                    WHERE s.sl = ?");
                $stmt->bind_param("i", $sl);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
                    exit();
                }

                $sub = $result->fetch_assoc();
                $stmt->close();

                // RBAC ownership check
                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($sub['salesperson_id'] ?? 0) !== $sp_id) {
                            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                        }
                    } elseif ((int)$sub['added_by'] !== $user_id) {
                        echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                    }
                }

                $branding = getSiteBranding();
                $recipient_email = !empty($sub['supplier_email']) ? $sub['supplier_email'] : ($sub['owner_email'] ?? '');

                if (empty($recipient_email)) {
                    echo json_encode(['success' => false, 'message' => 'No email address available for this subscription']);
                    exit();
                }

                $subject = "Subscription Renewal Reminder - " . $sub['customer_name'] . " (Invoice: " . $sub['invoice_no'] . ")";
                $body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">'
                    . '<div style="background:#001f3f;padding:20px;text-align:center;border-radius:8px 8px 0 0;">'
                    . '<h1 style="color:#fff;margin:0;font-size:22px;">' . htmlspecialchars($branding['site_name']) . '</h1>'
                    . '</div>'
                    . '<div style="background:#fff;padding:30px;border:1px solid #e9ecef;border-top:none;">'
                    . '<h2 style="color:#333;margin-top:0;">Subscription Renewal Reminder</h2>'
                    . '<p style="color:#666;">This is a reminder for the following subscription:</p>'
                    . '<table style="width:100%;border-collapse:collapse;margin:15px 0;">'
                    . '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Customer</td><td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($sub['customer_name']) . '</td></tr>'
                    . '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Invoice No</td><td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($sub['invoice_no']) . '</td></tr>'
                    . '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Expiry Date</td><td style="padding:8px;border:1px solid #ddd;">' . ($sub['expiry_date'] ? date('M d, Y', strtotime($sub['expiry_date'])) : 'N/A') . '</td></tr>'
                    . '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Total Amount</td><td style="padding:8px;border:1px solid #ddd;">' . getCurrency() . ' ' . number_format((float)$sub['total_amount'], 2) . '</td></tr>'
                    . '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Payment Status</td><td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($sub['payment_status']) . '</td></tr>'
                    . '</table>'
                    . '<p style="color:#666;">Please take the necessary action to renew this subscription.</p>'
                    . '</div>'
                    . '<div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:0 0 8px 8px;border:1px solid #e9ecef;border-top:none;">'
                    . '<p style="color:#999;font-size:12px;margin:0;">This is an automated reminder from ' . htmlspecialchars($branding['site_name']) . '</p>'
                    . '</div></div>';

                $emailResult = sendEmail($recipient_email, $subject, $body);

                // Log notification
                $logStatus = $emailResult['success'] ? 'Sent' : 'Failed';
                $statusInfo = getSubscriptionStatus($sub['expiry_date']);
                logNotification(
                    $sl, $recipient_email, 'user', $sub['owner_name'] ?? $username,
                    'manual_reminder', $statusInfo['days_left'], $subject,
                    substr(strip_tags($body), 0, 500),
                    $logStatus, $emailResult['success'] ? null : $emailResult['message'],
                    'manual', $user_id
                );

                logActivity($user_id, $username, 'Manual Reminder Sent', "Sent reminder for subscription SL#$sl to $recipient_email ($logStatus)");

                if ($emailResult['success']) {
                    echo json_encode(['success' => true, 'message' => 'Reminder sent successfully to ' . $recipient_email]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send reminder: ' . $emailResult['message']]);
                }
                exit();

            case 'getFormDropdowns':
                $conn = getDBConnection();

                // Active products
                $catStmt = $conn->prepare("SELECT product_id, product_name, color_code FROM products WHERE is_active = 1 ORDER BY display_order ASC, product_name ASC");
                $catStmt->execute();
                $catResult = $catStmt->get_result();
                $products = [];
                while ($row = $catResult->fetch_assoc()) {
                    $products[] = [
                        'product_id'   => (int)$row['product_id'],
                        'product_name' => $row['product_name'],
                        'color_code'   => $row['color_code'] ?? '#0078D4'
                    ];
                }
                $catStmt->close();

                // Active salespersons
                $spStmt = $conn->prepare("SELECT salesperson_id, name FROM salespersons WHERE is_active = 1 ORDER BY name ASC");
                $spStmt->execute();
                $spResult = $spStmt->get_result();
                $salespersons = [];
                while ($row = $spResult->fetch_assoc()) {
                    $salespersons[] = [
                        'salesperson_id' => (int)$row['salesperson_id'],
                        'name'           => $row['name']
                    ];
                }
                $spStmt->close();

                echo json_encode(['success' => true, 'products' => $products, 'salespersons' => $salespersons]);
                exit();

            case 'getFinancialSummary':
                $conn = getDBConnection();

                $sql = "SELECT COUNT(*) AS total,
                            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date > CURDATE() AND DATEDIFF(expiry_date, CURDATE()) > 30 THEN 1 ELSE 0 END) AS active,
                            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date > CURDATE() AND DATEDIFF(expiry_date, CURDATE()) <= 30 THEN 1 ELSE 0 END) AS expiring_soon,
                            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date = CURDATE() THEN 1 ELSE 0 END) AS expiring_today,
                            SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
                            SUM(total_amount) AS total_revenue,
                            SUM(CASE WHEN payment_status = 'Unpaid' THEN total_amount ELSE 0 END) AS unpaid_amount,
                            SUM((selling_price - tax_amount) - purchase_price) AS total_profit,
                            SUM(CASE WHEN subscription_status='paused' THEN 1 ELSE 0 END) AS paused,
                            SUM(CASE WHEN subscription_status='cancelled' THEN 1 ELSE 0 END) AS cancelled
                        FROM subscriptions";

                if ($role === 'admin') {
                    $stmt = $conn->prepare($sql);
                } elseif ($role === 'salesperson' && $sp_id) {
                    $sql .= " WHERE salesperson_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $sp_id);
                } else {
                    $sql .= " WHERE added_by = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                }

                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                $currency = getCurrency();

                echo json_encode([
                    'success'  => true,
                    'data'     => [
                        'total'          => (int)($row['total'] ?? 0),
                        'active'         => (int)($row['active'] ?? 0),
                        'expiring_soon'  => (int)($row['expiring_soon'] ?? 0),
                        'expiring_today' => (int)($row['expiring_today'] ?? 0),
                        'expired'        => (int)($row['expired'] ?? 0),
                        'total_revenue' => (float)($row['total_revenue'] ?? 0),
                        'unpaid_amount' => (float)($row['unpaid_amount'] ?? 0),
                        'total_profit'  => (float)($row['total_profit'] ?? 0),
                        'paused'        => (int)($row['paused'] ?? 0),
                        'cancelled'     => (int)($row['cancelled'] ?? 0)
                    ],
                    'currency' => $currency
                ]);
                exit();

            case 'renewSubscription':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $original_sl = isset($_POST['sl']) ? intval($_POST['sl']) : 0;
                if ($original_sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch original subscription
                $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE sl = ?");
                $stmt->bind_param("i", $original_sl);
                $stmt->execute();
                $original = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$original) {
                    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
                    exit();
                }

                // RBAC: ownership check
                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($original['salesperson_id'] ?? 0) !== $sp_id) {
                            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                        }
                    } elseif ((int)$original['added_by'] !== $user_id) {
                        echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                    }
                }

                // Generate new invoice number
                $new_invoice = generateInvoiceNo('RENEW');

                // Compute new dates based on license_duration
                $new_start = $original['expiry_date'] ?: date('Y-m-d');
                $duration = strtolower($original['license_duration'] ?? '1 year');
                $new_expiry_dt = new DateTime($new_start);
                if (strpos($duration, 'month') !== false) {
                    $months = intval($duration) ?: 1;
                    $new_expiry_dt->modify("+{$months} months");
                } else {
                    $years = intval($duration) ?: 1;
                    $new_expiry_dt->modify("+{$years} years");
                }
                $new_expiry = $new_expiry_dt->format('Y-m-d');

                // Insert renewal
                $stmt = $conn->prepare("INSERT INTO subscriptions
                    (customer_id, customer_name, invoice_no, renewal_invoice, product_id, invoice_date,
                     product_key, user_qty, license_duration, starting_date, expiry_date, product_description,
                     selling_price, purchase_price, tax_amount, total_amount,
                     payment_status, auto_renew, priority,
                     supplier_name, supplier_email, supplier_phone, contract_reference,
                     added_by, salesperson_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid', ?, ?, ?, ?, ?, ?, ?, ?)");

                $today_date = date('Y-m-d');
                $stmt->bind_param("isssississssddddisssssii",
                    $original['customer_id'], $original['customer_name'], $new_invoice, $original['invoice_no'],
                    $original['product_id'], $today_date,
                    $original['product_key'], $original['user_qty'], $original['license_duration'],
                    $new_start, $new_expiry, $original['product_description'],
                    $original['selling_price'], $original['purchase_price'], $original['tax_amount'], $original['total_amount'],
                    $original['auto_renew'], $original['priority'],
                    $original['supplier_name'], $original['supplier_email'], $original['supplier_phone'], $original['contract_reference'],
                    $user_id, $original['salesperson_id']);

                if ($stmt->execute()) {
                    $new_sl = $conn->insert_id;
                    $stmt->close();
                    logActivity($user_id, $username, 'Subscription Renewed',
                        "Renewed {$original['invoice_no']} ({$original['customer_name']}) as $new_invoice");
                    try { createNotificationForAdmins('Subscription Renewed',
                        "Invoice {$original['invoice_no']} renewed as $new_invoice by $username",
                        'info', 'subscriptions.php'); } catch (Exception $e) {}
                    echo json_encode(['success' => true, 'message' => 'Subscription renewed successfully', 'new_sl' => $new_sl, 'new_invoice' => $new_invoice]);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to create renewal']);
                }
                exit();

            case 'bulkMarkPaid':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit();
                }
                if ($role !== 'admin') { echo json_encode(['success'=>false,'message'=>'Admin only']); exit(); }
                $ids = isset($_POST['ids']) ? trim($_POST['ids']) : '';
                if (empty($ids)) { echo json_encode(['success'=>false,'message'=>'No IDs']); exit(); }
                $idArr = array_map('intval', explode(',', $ids));
                $idArr = array_filter($idArr, function($v){return $v>0;});
                if (empty($idArr)) { echo json_encode(['success'=>false,'message'=>'Invalid IDs']); exit(); }
                $conn = getDBConnection();
                $placeholders = implode(',', array_fill(0, count($idArr), '?'));
                $types = str_repeat('i', count($idArr));
                $today = date('Y-m-d');
                $stmt = $conn->prepare("UPDATE subscriptions SET payment_status='Paid', payment_date=?, updated_at=NOW() WHERE sl IN ($placeholders)");
                $stmt->bind_param('s' . $types, $today, ...$idArr);
                if ($stmt->execute()) {
                    $count = $stmt->affected_rows;
                    $stmt->close();
                    $ids_str = implode(',', $idArr);
                    logActivity($user_id, $username, 'Bulk Mark Paid', "Marked $count subscriptions as Paid (IDs: $ids_str)");
                    echo json_encode(['success'=>true,'message'=>"$count subscription(s) marked as Paid"]);
                } else {
                    $stmt->close();
                    echo json_encode(['success'=>false,'message'=>'Failed']);
                }
                exit();

            case 'bulkRenew':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit();
                }
                $ids = isset($_POST['ids']) ? trim($_POST['ids']) : '';
                if (empty($ids)) { echo json_encode(['success'=>false,'message'=>'No IDs']); exit(); }
                $idArr = array_map('intval', explode(',', $ids));
                $idArr = array_filter($idArr, function($v){return $v>0;});
                if (empty($idArr)) { echo json_encode(['success'=>false,'message'=>'Invalid IDs']); exit(); }

                $conn = getDBConnection();
                $renewed = 0;
                $errors = [];

                foreach ($idArr as $orig_sl) {
                    $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE sl = ?");
                    $stmt->bind_param("i", $orig_sl);
                    $stmt->execute();
                    $original = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$original) { $errors[] = "SL#$orig_sl not found"; continue; }

                    // RBAC
                    if ($role !== 'admin') {
                        if ($role === 'salesperson' && $sp_id) {
                            if ((int)($original['salesperson_id'] ?? 0) !== $sp_id) { $errors[] = "SL#$orig_sl access denied"; continue; }
                        } elseif ((int)$original['added_by'] !== $user_id) { $errors[] = "SL#$orig_sl access denied"; continue; }
                    }

                    $new_invoice = generateInvoiceNo('RENEW');
                    $new_start = $original['expiry_date'] ?: date('Y-m-d');
                    $duration = strtolower($original['license_duration'] ?? '1 year');
                    $new_expiry_dt = new DateTime($new_start);
                    if (strpos($duration, 'month') !== false) {
                        $months = intval($duration) ?: 1;
                        $new_expiry_dt->modify("+{$months} months");
                    } else {
                        $years = intval($duration) ?: 1;
                        $new_expiry_dt->modify("+{$years} years");
                    }
                    $new_expiry = $new_expiry_dt->format('Y-m-d');
                    $today_date = date('Y-m-d');

                    $ins = $conn->prepare("INSERT INTO subscriptions
                        (customer_id, customer_name, invoice_no, renewal_invoice, product_id, invoice_date,
                         product_key, user_qty, license_duration, starting_date, expiry_date, product_description,
                         selling_price, purchase_price, tax_amount, total_amount,
                         payment_status, auto_renew, priority,
                         supplier_name, supplier_email, supplier_phone, contract_reference,
                         added_by, salesperson_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid', ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->bind_param("isssississssddddisssssii",
                        $original['customer_id'], $original['customer_name'], $new_invoice, $original['invoice_no'],
                        $original['product_id'], $today_date,
                        $original['product_key'], $original['user_qty'], $original['license_duration'],
                        $new_start, $new_expiry, $original['product_description'],
                        $original['selling_price'], $original['purchase_price'], $original['tax_amount'], $original['total_amount'],
                        $original['auto_renew'], $original['priority'],
                        $original['supplier_name'], $original['supplier_email'], $original['supplier_phone'], $original['contract_reference'],
                        $user_id, $original['salesperson_id']);

                    if ($ins->execute()) {
                        $renewed++;
                        logActivity($user_id, $username, 'Subscription Renewed',
                            "Renewed {$original['invoice_no']} ({$original['customer_name']}) as $new_invoice (bulk)");
                    } else {
                        $errors[] = "SL#$orig_sl insert failed";
                    }
                    $ins->close();
                }

                $msg = "$renewed subscription(s) renewed successfully";
                if (!empty($errors)) $msg .= '. Errors: ' . implode(', ', $errors);
                echo json_encode(['success' => $renewed > 0, 'message' => $msg]);
                exit();

            case 'updatePriority':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit();
                }
                $sl = isset($_POST['sl']) ? intval($_POST['sl']) : 0;
                $priority = isset($_POST['priority']) ? trim($_POST['priority']) : '';
                if ($sl <= 0 || !in_array($priority, ['Low','Medium','High','Critical'])) {
                    echo json_encode(['success'=>false,'message'=>'Invalid input']); exit();
                }
                $conn = getDBConnection();
                if ($role !== 'admin') {
                    $chk = $conn->prepare("SELECT added_by, salesperson_id FROM subscriptions WHERE sl = ?");
                    $chk->bind_param("i", $sl); $chk->execute();
                    $owner = $chk->get_result()->fetch_assoc(); $chk->close();
                    if (!$owner) { echo json_encode(['success'=>false,'message'=>'Not found']); exit(); }
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($owner['salesperson_id'] ?? 0) !== $sp_id) { echo json_encode(['success'=>false,'message'=>'Access denied']); exit(); }
                    } elseif ((int)$owner['added_by'] !== $user_id) {
                        echo json_encode(['success'=>false,'message'=>'Access denied']); exit();
                    }
                }
                $stmt = $conn->prepare("UPDATE subscriptions SET priority = ?, updated_at = NOW() WHERE sl = ?");
                $stmt->bind_param("si", $priority, $sl);
                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Priority Updated', "Changed priority to '$priority' for SL#$sl");
                    $stmt->close();
                    echo json_encode(['success'=>true,'message'=>'Priority updated']);
                } else {
                    $stmt->close();
                    echo json_encode(['success'=>false,'message'=>'Failed']);
                }
                exit();

            case 'pauseSubscription':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit();
                }
                if ($role !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Admin only']); exit();
                }
                $sl = isset($_POST['sl']) ? intval($_POST['sl']) : 0;
                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit();
                }
                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE subscriptions SET subscription_status='paused', paused_at=NOW(), updated_at=NOW() WHERE sl=? AND subscription_status='active'");
                $stmt->bind_param("i", $sl);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logActivity($user_id, $username, 'Subscription Paused', "Paused subscription SL#$sl");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Subscription paused']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to pause — may not be active']);
                }
                exit();

            case 'resumeSubscription':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit();
                }
                if ($role !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Admin only']); exit();
                }
                $sl = isset($_POST['sl']) ? intval($_POST['sl']) : 0;
                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit();
                }
                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE subscriptions SET subscription_status='active', resumed_at=NOW(), updated_at=NOW() WHERE sl=? AND subscription_status='paused'");
                $stmt->bind_param("i", $sl);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logActivity($user_id, $username, 'Subscription Resumed', "Resumed subscription SL#$sl");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Subscription resumed']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to resume — may not be paused']);
                }
                exit();

            case 'cancelSubscription':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit();
                }
                if ($role !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Admin only']); exit();
                }
                $sl = isset($_POST['sl']) ? intval($_POST['sl']) : 0;
                $cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit();
                }
                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE subscriptions SET subscription_status='cancelled', cancelled_at=NOW(), cancel_reason=?, updated_at=NOW() WHERE sl=? AND subscription_status IN('active','paused')");
                $stmt->bind_param("si", $cancel_reason, $sl);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logActivity($user_id, $username, 'Subscription Cancelled', "Cancelled subscription SL#$sl" . ($cancel_reason ? " — $cancel_reason" : ''));
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Subscription cancelled']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to cancel — may already be cancelled']);
                }
                exit();

            case 'getDocuments':
                $sl = isset($_GET['subscription_sl']) ? intval($_GET['subscription_sl']) : 0;
                if ($sl <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit(); }

                $conn = getDBConnection();

                // RBAC ownership
                $chk = $conn->prepare("SELECT added_by, salesperson_id FROM subscriptions WHERE sl = ?");
                $chk->bind_param("i", $sl); $chk->execute();
                $owner = $chk->get_result()->fetch_assoc(); $chk->close();
                if (!$owner) { echo json_encode(['success' => false, 'message' => 'Subscription not found']); exit(); }
                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($owner['salesperson_id'] ?? 0) !== $sp_id) { echo json_encode(['success' => false, 'message' => 'Access denied']); exit(); }
                    } elseif ((int)$owner['added_by'] !== $user_id) { echo json_encode(['success' => false, 'message' => 'Access denied']); exit(); }
                }

                $stmt = $conn->prepare("SELECT d.document_id, d.file_name, d.original_name, d.file_size, d.file_type, d.created_at, u.full_name AS uploaded_by_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE d.subscription_sl = ? ORDER BY d.created_at DESC");
                $stmt->bind_param("i", $sl);
                $stmt->execute();
                $res = $stmt->get_result();
                $docs = [];
                while ($r = $res->fetch_assoc()) {
                    $r['created_at_fmt'] = $r['created_at'] ? date('M d, Y H:i', strtotime($r['created_at'])) : '';
                    $docs[] = $r;
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $docs]);
                exit();

            case 'uploadDocument':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit();
                }

                $sl = isset($_POST['subscription_sl']) ? intval($_POST['subscription_sl']) : 0;
                if ($sl <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']); exit(); }

                if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']); exit();
                }

                $conn = getDBConnection();

                // RBAC ownership
                $chk = $conn->prepare("SELECT added_by, salesperson_id, customer_name FROM subscriptions WHERE sl = ?");
                $chk->bind_param("i", $sl); $chk->execute();
                $owner = $chk->get_result()->fetch_assoc(); $chk->close();
                if (!$owner) { echo json_encode(['success' => false, 'message' => 'Subscription not found']); exit(); }
                if ($role !== 'admin') {
                    if ($role === 'salesperson' && $sp_id) {
                        if ((int)($owner['salesperson_id'] ?? 0) !== $sp_id) { echo json_encode(['success' => false, 'message' => 'Access denied']); exit(); }
                    } elseif ((int)$owner['added_by'] !== $user_id) { echo json_encode(['success' => false, 'message' => 'Access denied']); exit(); }
                }

                $file = $_FILES['document'];
                $max_size = 10 * 1024 * 1024; // 10MB
                if ($file['size'] > $max_size) {
                    echo json_encode(['success' => false, 'message' => 'File too large. Max 10MB allowed']); exit();
                }

                $allowed_ext = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','txt','csv','zip'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) {
                    echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed: ' . implode(', ', $allowed_ext)]); exit();
                }

                // ensure upload dir
                $upload_dir = __DIR__ . '/uploads/documents/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

                // unique filename
                $new_name = 'doc_' . $sl . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                $dest = $upload_dir . $new_name;

                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']); exit();
                }

                $original = $file['name'];
                $size = $file['size'];
                $type = $file['type'] ?: ('application/' . $ext);

                $stmt = $conn->prepare("INSERT INTO documents (subscription_sl, file_name, original_name, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issisi", $sl, $new_name, $original, $size, $type, $user_id);

                if ($stmt->execute()) {
                    $doc_id = $conn->insert_id;
                    $stmt->close();
                    logActivity($user_id, $username, 'Document Uploaded', "Uploaded '$original' for subscription SL#$sl");
                    echo json_encode(['success' => true, 'message' => 'Document uploaded successfully', 'document_id' => $doc_id, 'file_name' => $new_name, 'original_name' => $original]);
                } else {
                    $stmt->close();
                    @unlink($dest); // cleanup
                    echo json_encode(['success' => false, 'message' => 'Failed to save document record']);
                }
                exit();

            case 'deleteDocument':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit();
                }
                if ($role !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Admin only']); exit();
                }

                $doc_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;
                if ($doc_id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid document ID']); exit(); }

                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT file_name, original_name, subscription_sl FROM documents WHERE document_id = ?");
                $stmt->bind_param("i", $doc_id);
                $stmt->execute();
                $doc = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$doc) { echo json_encode(['success' => false, 'message' => 'Document not found']); exit(); }

                // delete file from disk
                $file_path = __DIR__ . '/uploads/documents/' . $doc['file_name'];
                if (file_exists($file_path)) @unlink($file_path);

                $stmt = $conn->prepare("DELETE FROM documents WHERE document_id = ?");
                $stmt->bind_param("i", $doc_id);
                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Document Deleted', "Deleted '{$doc['original_name']}' from SL#{$doc['subscription_sl']}");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Document deleted']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete document']);
                }
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("subscriptions.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// CSV import handler
if (isset($_POST['action']) && $_POST['action'] === 'importSubscriptions') {
    header('Content-Type: application/json');

    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        exit();
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit();
    }

    $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        echo json_encode(['success' => false, 'message' => 'Only .csv files allowed']);
        exit();
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Cannot read file']);
        exit();
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'Empty CSV file']);
        exit();
    }

    $header = array_map(function($h) { return strtolower(trim(str_replace(["\xEF\xBB\xBF", '"'], '', $h))); }, $header);
    $required = ['customer_name'];
    foreach ($required as $r) {
        if (!in_array($r, $header)) {
            fclose($handle);
            echo json_encode(['success' => false, 'message' => "Missing required column: $r"]);
            exit();
        }
    }

    $conn = getDBConnection();

    // build lookup maps for FK resolution
    $custMap = [];
    $res = $conn->query("SELECT customer_id, company_name FROM customers WHERE is_active=1");
    while ($r = $res->fetch_assoc()) $custMap[strtolower(trim($r['company_name']))] = (int)$r['customer_id'];

    $suppMap = [];
    $res = $conn->query("SELECT supplier_id, company_name FROM suppliers WHERE is_active=1");
    while ($r = $res->fetch_assoc()) $suppMap[strtolower(trim($r['company_name']))] = (int)$r['supplier_id'];

    // get invoice prefix
    $invPrefix = getSetting('invoice_prefix', 'CID');

    $stmt = $conn->prepare(
        "INSERT INTO subscriptions
            (customer_id, customer_name, invoice_no, invoice_date, starting_date, expiry_date,
             product_description, selling_price, purchase_price, tax_amount, total_amount,
             payment_status, payment_method, payment_date, priority, user_qty, license_duration,
             supplier_name, supplier_email, supplier_phone, supplier_id,
             contract_reference, remarks, auto_renew, added_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $imported = 0; $skipped = 0; $errors = [];
    $lineNum = 1;
    $validPayment = ['Paid','Unpaid','Partial','Refunded'];
    $validPriority = ['Low','Medium','High','Critical'];

    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;
        if (count($row) < count($header)) $row = array_pad($row, count($header), '');
        $d = array_combine($header, array_slice($row, 0, count($header)));

        $custName = trim($d['customer_name'] ?? '');
        if (empty($custName)) { $skipped++; continue; }

        // resolve customer FK
        $custId = $custMap[strtolower($custName)] ?? null;

        // invoice_no: use provided or auto-generate
        $invoiceNo = trim($d['invoice_no'] ?? '');
        if (empty($invoiceNo)) $invoiceNo = generateInvoiceNo($invPrefix);

        $invDate   = trim($d['invoice_date'] ?? date('Y-m-d'));
        $startDate = !empty($d['starting_date']) ? trim($d['starting_date']) : null;
        $expDate   = !empty($d['expiry_date'])   ? trim($d['expiry_date'])   : null;
        $prodDesc  = !empty($d['product_description']) ? trim($d['product_description']) : null;

        $sellPrice = floatval($d['selling_price'] ?? 0);
        $buyPrice  = floatval($d['purchase_price'] ?? 0);
        $taxAmt    = floatval($d['tax_amount'] ?? 0);
        $totalAmt  = floatval($d['total_amount'] ?? 0);
        if ($totalAmt == 0 && $sellPrice > 0) $totalAmt = $sellPrice;

        $payStat   = trim($d['payment_status'] ?? 'Unpaid');
        if (!in_array($payStat, $validPayment)) $payStat = 'Unpaid';

        $payMethod = !empty($d['payment_method']) ? trim($d['payment_method']) : null;
        $payDate   = !empty($d['payment_date'])   ? trim($d['payment_date'])   : null;

        $priority  = trim($d['priority'] ?? 'Medium');
        if (!in_array($priority, $validPriority)) $priority = 'Medium';

        $userQty   = max(1, intval($d['user_qty'] ?? 1));
        $licDur    = !empty($d['license_duration']) ? trim($d['license_duration']) : null;

        $suppName  = !empty($d['supplier_name'])  ? trim($d['supplier_name'])  : null;
        $suppEmail = !empty($d['supplier_email']) ? trim($d['supplier_email']) : null;
        $suppPhone = !empty($d['supplier_phone']) ? trim($d['supplier_phone']) : null;
        $suppId    = $suppName ? ($suppMap[strtolower($suppName)] ?? null) : null;

        $contRef   = !empty($d['contract_reference']) ? trim($d['contract_reference']) : null;
        $remarks   = !empty($d['remarks']) ? trim($d['remarks']) : null;
        $autoRenew = intval($d['auto_renew'] ?? 0);

        $stmt->bind_param("sssssssddddssssississssii",
            $custId, $custName, $invoiceNo, $invDate, $startDate, $expDate,
            $prodDesc, $sellPrice, $buyPrice, $taxAmt, $totalAmt,
            $payStat, $payMethod, $payDate, $priority, $userQty, $licDur,
            $suppName, $suppEmail, $suppPhone, $suppId,
            $contRef, $remarks, $autoRenew, $user_id);

        if ($stmt->execute()) {
            $imported++;
        } else {
            if ($conn->errno === 1062) {
                $errors[] = "Row $lineNum: Invoice '$invoiceNo' already exists";
            } else {
                $errors[] = "Row $lineNum: " . $stmt->error;
            }
            $skipped++;
        }
    }
    $stmt->close();
    fclose($handle);

    if ($imported > 0) {
        logActivity($user_id, $username, 'Subscriptions Imported', "Imported $imported subscriptions from CSV");
    }

    $msg = "$imported subscription(s) imported successfully.";
    if ($skipped > 0) $msg .= " $skipped row(s) skipped.";

    echo json_encode(['success' => true, 'message' => $msg, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]);
    exit();
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
    <title>Subscriptions - Dashboard System</title>

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

        /* Summary Cards */
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .summary-card { background: #fff; border-radius: 10px; padding: 18px 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); display: flex; align-items: center; gap: 14px; transition: transform 0.2s; }
        .summary-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
        .summary-card .card-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; flex-shrink: 0; }
        .summary-card .card-info h3 { font-size: 22px; font-weight: 700; margin: 0; color: #001f3f; }
        .summary-card .card-info p { font-size: 12px; color: #666; margin: 2px 0 0 0; }
        .card-icon.bg-primary { background: #001f3f; }
        .card-icon.bg-success { background: #28a745; }
        .card-icon.bg-warning { background: #ffc107; }
        .card-icon.bg-danger { background: #dc3545; }
        .card-icon.bg-info { background: #0074D9; }
        .card-icon.bg-purple { background: #6f42c1; }
        .card-icon.bg-orange { background: #ff9800; }

        /* Skeleton Loader */
        .skeleton-card .card-info h3, .skeleton-card .card-info p { background: #e9ecef; border-radius: 4px; animation: skeleton-pulse 1.5s infinite; }
        .skeleton-card .card-info h3 { width: 60px; height: 22px; }
        .skeleton-card .card-info p { width: 80px; height: 12px; margin-top: 6px; }
        @keyframes skeleton-pulse { 0%,100% { opacity: 0.6; } 50% { opacity: 1; } }

        /* Status Badges */
        .status-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block; }
        .status-active { background: #d4edda; color: #155724; }
        .status-expiring-soon { background: #fff3cd; color: #856404; }
        .status-expiring-today { background: #ffe0b2; color: #e65100; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-danger { background: #f8d7da; color: #721c24; }

        /* Payment Badges */
        .pay-paid { background: #d4edda; color: #155724; }
        .pay-unpaid { background: #f8d7da; color: #721c24; }
        .pay-partial { background: #fff3cd; color: #856404; }
        .pay-refunded { background: #cce5ff; color: #004085; }

        /* Row bg by payment status */
        .row-paid td { background: #f0faf3 !important; }
        .row-unpaid td { background: #fef0f0 !important; }
        .row-partial td { background: #fefbf0 !important; }
        .dark-mode .row-paid td { background: rgba(40,167,69,0.08) !important; }
        .dark-mode .row-unpaid td { background: rgba(220,53,69,0.08) !important; }
        .dark-mode .row-partial td { background: rgba(255,193,7,0.08) !important; }

        /* Priority Badges */
        .prio-critical { background: #f8d7da; color: #721c24; font-weight: 700; }
        .prio-high { background: #ffe0b2; color: #e65100; }
        .prio-medium { background: #cce5ff; color: #004085; }
        .prio-low { background: #e2e3e5; color: #383d41; }

        /* Product Badge */
        .cat-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block; }

        /* Days Left Color Coding */
        .days-negative { color: #dc3545; font-weight: 700; }
        .days-critical { color: #e65100; font-weight: 700; }
        .days-warning { color: #856404; font-weight: 600; }
        .days-ok { color: #155724; font-weight: 600; }

        /* Payment Quick Select */
        .payment-quick-select { font-size: 12px; padding: 2px 6px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; }

        /* Customer Link */
        .customer-link { color:#0074D9; text-decoration:none; font-weight:600; }
        .customer-link:hover { text-decoration:underline; }

        /* Column Chooser */
        .column-chooser { background:#fff; border:1px solid #e0e7ef; border-radius:8px; padding:12px; position:absolute; right:0; top:100%; z-index:100; box-shadow:0 4px 16px rgba(0,0,0,.12); min-width:200px; max-height:400px; overflow-y:auto; }
        .column-chooser label { display:flex; align-items:center; gap:8px; padding:4px 0; font-size:13px; cursor:pointer; }
        .dark-mode .column-chooser { background:#1a2332; border-color:#2a3a4a; color:#e9ecef; }

        /* Bulk Delete Bar */
        .bulk-actions { display: none; align-items: center; gap: 10px; margin-bottom: 10px; padding: 10px 16px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffc107; }
        .bulk-actions.visible { display: flex; }
        .bulk-count { font-weight: 600; color: #856404; }

        /* Dark mode overrides */
        .dark-mode .summary-card { background: #1a2332; }
        .dark-mode .summary-card .card-info h3 { color: #e9ecef; }
        .dark-mode .summary-card .card-info p { color: #adb5bd; }
        .dark-mode .bulk-actions { background: #2a2a1a; border-color: #665500; }
        .dark-mode .bulk-count { color: #ffc107; }
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
                <span>Subscriptions</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-file-invoice"></i> Subscriptions</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <!-- Summary Cards -->
            <div class="summary-cards" id="summaryCards">
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-primary"><i class="fas fa-file-alt"></i></div>
                    <div class="card-info"><h3 id="statTotal">&nbsp;</h3><p>Total</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-success"><i class="fas fa-check-circle"></i></div>
                    <div class="card-info"><h3 id="statActive">&nbsp;</h3><p>Active</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="card-info"><h3 id="statExpiring">&nbsp;</h3><p>Expiring Soon</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-orange"><i class="fas fa-clock"></i></div>
                    <div class="card-info"><h3 id="statExpiringToday">&nbsp;</h3><p>Expiring Today</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-danger"><i class="fas fa-times-circle"></i></div>
                    <div class="card-info"><h3 id="statExpired">&nbsp;</h3><p>Expired</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-info"><i class="fas fa-coins"></i></div>
                    <div class="card-info"><h3 id="statRevenue">&nbsp;</h3><p>Revenue</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-purple"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="card-info"><h3 id="statUnpaid">&nbsp;</h3><p>Unpaid</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-orange"><i class="fas fa-pause-circle"></i></div>
                    <div class="card-info"><h3 id="statPaused">&nbsp;</h3><p>Paused</p></div>
                </div>
                <div class="summary-card skeleton-card">
                    <div class="card-icon bg-danger"><i class="fas fa-ban"></i></div>
                    <div class="card-info"><h3 id="statCancelled">&nbsp;</h3><p>Cancelled</p></div>
                </div>
            </div>

            <div class="data-section">
                <div class="section-header" style="position:relative;">
                    <h2><i class="fas fa-table"></i> Subscriptions</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadSubscriptions()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <a href="add_subscription.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Subscription
                        </a>
                        <?php if ($role === 'admin'): ?>
                        <button class="btn btn-info" onclick="openImportModal()">
                            <i class="fas fa-file-import"></i> Import CSV
                        </button>
                        <?php endif; ?>
                        <?php if ($role === 'admin'): ?>
                        <button class="btn btn-success" id="bulkPaidBtn" style="display:none;" onclick="bulkMarkPaid()">
                            <i class="fas fa-check"></i> Mark Paid (<span id="bulkPaidCount">0</span>)
                        </button>
                        <button class="btn btn-info" id="bulkRenewBtn" style="display:none;" onclick="bulkRenew()">
                            <i class="fas fa-redo"></i> Renew Selected (<span id="bulkRenewCount">0</span>)
                        </button>
                        <button class="btn btn-danger" id="bulkDeleteBtn" style="display:none;" onclick="bulkDelete()">
                            <i class="fas fa-trash"></i> Delete Selected (<span id="bulkCount">0</span>)
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-secondary" onclick="toggleColumnChooser()" style="position:relative;">
                            <i class="fas fa-columns"></i> Columns
                        </button>
                    </div>
                    <div class="column-chooser" id="columnChooser" style="display:none;"></div>
                </div>

                <!-- Pipeline Chevron Tabs -->
                <div class="pipeline-stages initially-hidden" id="pipelineStages"></div>

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
                            <label><i class="fas fa-user"></i> Customer</label>
                            <input type="text" id="filterCustomer" class="filter-input" placeholder="Search customer...">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-credit-card"></i> Payment Status</label>
                            <select id="filterPayment" class="filter-input">
                                <option value="">All</option>
                                <option value="Paid">Paid</option>
                                <option value="Unpaid">Unpaid</option>
                                <option value="Partial">Partial</option>
                                <option value="Refunded">Refunded</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-flag"></i> Priority</label>
                            <select id="filterPriority" class="filter-input">
                                <option value="">All</option>
                                <option value="Critical">Critical</option>
                                <option value="High">High</option>
                                <option value="Medium">Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-tags"></i> Product</label>
                            <select id="filterProduct" class="filter-input">
                                <option value="">All Products</option>
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
                        <?php if ($role === 'admin'): ?>
                        <div class="filter-group">
                            <label><i class="fas fa-user-plus"></i> Added By</label>
                            <select id="filterAddedBy" class="filter-input">
                                <option value="">All Users</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                </div>
                <div class="table-responsive">
                    <table id="subscriptionsTable" class="display table-full-width"></table>
                </div>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <?php if ($role === 'admin'): ?>
    <div class="modal-overlay" id="importModal">
        <div class="modal" onclick="event.stopPropagation()" style="max-width:720px;">
            <div class="modal-header">
                <h3><i class="fas fa-file-import"></i> Import Subscriptions</h3>
                <button class="close-btn" onclick="closeImportModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p style="color:var(--text-secondary);font-size:14px;margin-bottom:16px;">Upload a CSV file containing subscription data. Only <strong>customer_name</strong> is required &mdash; all other fields are optional.</p>

                <div style="margin-bottom:20px;">
                    <a href="?action=downloadSubscriptionTemplate" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
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
                                <td style="text-align:left;font-weight:600;color:#dc3545;">customer_name *</td>
                                <td>Text</td>
                                <td style="text-align:left;">Required</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">invoice_no</td>
                                <td>Text</td>
                                <td style="text-align:left;">Auto-generated if empty</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">invoice_date, starting_date, expiry_date</td>
                                <td>Date</td>
                                <td style="text-align:left;">YYYY-MM-DD format</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">selling_price, purchase_price, tax_amount, total_amount</td>
                                <td>Decimal</td>
                                <td style="text-align:left;">Numeric values</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">payment_status</td>
                                <td>Text</td>
                                <td style="text-align:left;">Paid / Unpaid / Partial</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">payment_method, payment_date</td>
                                <td>Text / Date</td>
                                <td style="text-align:left;">Payment info</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">priority</td>
                                <td>Text</td>
                                <td style="text-align:left;">Low / Medium / High / Critical</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">product_description, license_duration, user_qty</td>
                                <td>Mixed</td>
                                <td style="text-align:left;">Product details</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">supplier_name, supplier_email, supplier_phone</td>
                                <td>Text</td>
                                <td style="text-align:left;">Supplier info</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">contract_reference, remarks</td>
                                <td>Text</td>
                                <td style="text-align:left;">Extra notes</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">auto_renew</td>
                                <td>Integer</td>
                                <td style="text-align:left;">1 = yes, 0 = no</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-file-csv"></i> Select CSV File *</label>
                    <input type="file" id="csvFileInput" accept=".csv">
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

    <!-- Cancel Subscription Modal -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal" onclick="event.stopPropagation()" style="max-width:500px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#dc3545 0%,#c82333 100%);">
                <h3><i class="fas fa-ban"></i> Cancel Subscription</h3>
                <button class="close-btn" onclick="closeCancelModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cancelSl" value="">
                <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;margin-bottom:20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size:20px;color:#856404;"></i>
                    <div style="font-size:13px;color:#856404;">This action cannot be undone easily. The subscription will be marked as cancelled.</div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment-alt"></i> Reason for Cancellation *</label>
                    <textarea id="cancelReason" rows="3" placeholder="Enter reason for cancellation..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" id="cancelConfirmBtn" onclick="submitCancel()" style="background:#dc3545;border-color:#dc3545;">
                        <i class="fas fa-ban"></i> Cancel Subscription
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeCancelModal()">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Subscription Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" onclick="event.stopPropagation()" style="max-width:460px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#dc3545 0%,#c82333 100%);">
                <h3><i class="fas fa-trash-alt"></i> <span id="deleteTitle">Delete Subscription</span></h3>
                <button class="close-btn" onclick="closeDeleteModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deleteSl" value="">
                <input type="hidden" id="deleteType" value="single">
                <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;margin-bottom:20px;">
                    <i class="fas fa-exclamation-circle" style="font-size:20px;color:#721c24;"></i>
                    <div style="font-size:13px;color:#721c24;" id="deleteMsg">This action cannot be undone. The subscription and all related data will be permanently removed.</div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" id="deleteConfirmBtn" onclick="submitDelete()" style="background:#dc3545;border-color:#dc3545;">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Action Modal (Pause/Resume/Renew/Remind/BulkPaid/BulkRenew) -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal" onclick="event.stopPropagation()" style="max-width:460px;">
            <div class="modal-header" id="confirmHeader">
                <h3 id="confirmTitle"><i class="fas fa-question-circle"></i> Confirm</h3>
                <button class="close-btn" onclick="closeConfirmModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:4px;margin-bottom:20px;" id="confirmAlert">
                    <i id="confirmAlertIcon" class="fas fa-info-circle" style="font-size:20px;"></i>
                    <div style="font-size:13px;" id="confirmAlertMsg"></div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" id="confirmBtn">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
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
        var subscriptionsTable;
        var subscriptionsData = [];
        var globalCurrency = 'INR';
        var isAdmin = <?php echo $role === 'admin' ? 'true' : 'false'; ?>;
        var isSalesperson = <?php echo $role === 'salesperson' ? 'true' : 'false'; ?>;
        var activePipelineStatus = null;

        $(document).ready(function() {
            loadFinancialSummary();
            loadSubscriptions();
        });

        // ── Financial Summary ────────────────────────────────────────────────
        function loadFinancialSummary() {
            $.ajax({
                url: '?action=getFinancialSummary',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var d = response.data;
                        var c = response.currency || 'INR';
                        document.getElementById('statTotal').textContent = d.total;
                        document.getElementById('statActive').textContent = d.active;
                        document.getElementById('statExpiring').textContent = d.expiring_soon;
                        document.getElementById('statExpiringToday').textContent = d.expiring_today || 0;
                        document.getElementById('statExpired').textContent = d.expired;
                        document.getElementById('statRevenue').textContent = c + ' ' + formatNumber(d.total_revenue);
                        document.getElementById('statUnpaid').textContent = c + ' ' + formatNumber(d.unpaid_amount);
                        document.getElementById('statPaused').textContent = d.paused || 0;
                        document.getElementById('statCancelled').textContent = d.cancelled || 0;

                        // Remove skeleton class
                        document.querySelectorAll('.skeleton-card').forEach(function(el) {
                            el.classList.remove('skeleton-card');
                        });
                    }
                },
                error: function() {
                    document.getElementById('statTotal').textContent = '-';
                    document.getElementById('statActive').textContent = '-';
                    document.getElementById('statExpiring').textContent = '-';
                    document.getElementById('statExpiringToday').textContent = '-';
                    document.getElementById('statExpired').textContent = '-';
                    document.getElementById('statRevenue').textContent = '-';
                    document.getElementById('statUnpaid').textContent = '-';
                    document.getElementById('statPaused').textContent = '-';
                    document.getElementById('statCancelled').textContent = '-';
                }
            });
        }

        function formatNumber(n) {
            var val = parseFloat(n || 0);
            if (val % 1 === 0) return val.toLocaleString('en-IN');
            return val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
        }

        // ── Load Subscriptions ───────────────────────────────────────────────
        function loadSubscriptions() {
            $.ajax({
                url: '?action=getSubscriptions',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        subscriptionsData = response.data;
                        globalCurrency = response.currency || 'INR';
                        $('#filtersSection').show();
                        $('#pipelineStages').show();
                        populateDynamicFilters(response.data);
                        initializeDataTable(response.data);
                        renderPipeline();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to load subscriptions'
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

        // ── Populate Dynamic Filters ─────────────────────────────────────────
        function populateDynamicFilters(data) {
            // Products
            var cats = [];
            var catMap = {};
            data.forEach(function(r) {
                if (r.product_name && !catMap[r.product_name]) {
                    catMap[r.product_name] = true;
                    cats.push(r.product_name);
                }
            });
            var catSel = document.getElementById('filterProduct');
            var catVal = catSel.value;
            catSel.innerHTML = '<option value="">All Products</option>';
            cats.sort().forEach(function(c) {
                catSel.innerHTML += '<option value="' + c + '">' + c + '</option>';
            });
            catSel.value = catVal;

            // Added By (admin only)
            if (isAdmin) {
                var users = [];
                var userMap = {};
                data.forEach(function(r) {
                    if (r.added_by_name && !userMap[r.added_by_name]) {
                        userMap[r.added_by_name] = true;
                        users.push(r.added_by_name);
                    }
                });
                var addedBySel = document.getElementById('filterAddedBy');
                if (addedBySel) {
                    var addedByVal = addedBySel.value;
                    addedBySel.innerHTML = '<option value="">All Users</option>';
                    users.sort().forEach(function(u) {
                        addedBySel.innerHTML += '<option value="' + u + '">' + u + '</option>';
                    });
                    addedBySel.value = addedByVal;
                }
            }
        }

        // ── Initialize DataTable ─────────────────────────────────────────────
        function initializeDataTable(data) {
            if (subscriptionsTable) {
                subscriptionsTable.destroy();
                $('#subscriptionsTable').empty();
            }

            setTimeout(function() {
                var columns = [];

                <?php if ($role === 'admin'): ?>
                // Column 0: Checkbox
                columns.push({
                    data: null,
                    title: '<input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">',
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return '<input type="checkbox" class="row-checkbox" value="' + row.sl + '" onclick="updateBulkCount()">';
                    }
                });
                <?php endif; ?>

                // Column: SL
                columns.push({ data: 'sl', title: 'SL' });

                // Column: Customer
                columns.push({
                    data: 'customer_name',
                    title: 'Customer',
                    render: function(data, type, row) {
                        return '<a href="javascript:void(0)" class="customer-link" onclick="viewCustomer(\'' + escapeHtml(data).replace(/'/g, "\\'") + '\')">' + escapeHtml(data) + '</a>';
                    }
                });

                // Column: Phone
                columns.push({ data: 'customer_phone', title: 'Phone', defaultContent: '-' });

                // Column: Product
                columns.push({
                    data: 'product_name',
                    title: 'Product',
                    render: function(data, type, row) {
                        if (!data) return '-';
                        var bg = row.color_code || '#0078D4';
                        // Calculate contrasting text color
                        var r = parseInt(bg.substr(1,2), 16);
                        var g = parseInt(bg.substr(3,2), 16);
                        var b = parseInt(bg.substr(5,2), 16);
                        var textColor = (r*0.299 + g*0.587 + b*0.114) > 186 ? '#000' : '#fff';
                        return '<span class="cat-badge" style="background:' + bg + ';color:' + textColor + '">' + data + '</span>';
                    }
                });

                // Column: Expiry Date
                columns.push({ data: 'expiry_date', title: 'Expiry', defaultContent: '-' });

                // Column: Days Left
                columns.push({
                    data: 'days_left',
                    title: 'Days Left',
                    render: function(data) {
                        if (data === null || data === undefined) return '-';
                        var cls = 'days-ok';
                        if (data < 0) cls = 'days-negative';
                        else if (data <= 7) cls = 'days-critical';
                        else if (data <= 30) cls = 'days-warning';
                        return '<span class="' + cls + '">' + data + '</span>';
                    }
                });

                // Column: Status
                columns.push({
                    data: 'status_label',
                    title: 'Status',
                    render: function(data, type, row) {
                        return '<span class="status-badge ' + row.status_class + '">' + data + '</span>';
                    }
                });

                // Column: Payment Status
                columns.push({
                    data: 'payment_status',
                    title: 'Payment',
                    render: function(data) {
                        var cls = 'pay-unpaid';
                        if (data === 'Paid') cls = 'pay-paid';
                        else if (data === 'Partial') cls = 'pay-partial';
                        else if (data === 'Refunded') cls = 'pay-refunded';
                        return '<span class="status-badge ' + cls + '">' + data + '</span>';
                    }
                });

                // Column: Amount
                columns.push({
                    data: 'total_amount',
                    title: 'Amount',
                    render: function(data, type, row) {
                        var cur = row.currency_code || globalCurrency;
                        var val = parseFloat(data || 0);
                        var formatted = val % 1 === 0 ? val.toLocaleString('en-IN') : val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
                        return cur + ' ' + formatted;
                    }
                });

                // Column: Priority
                columns.push({
                    data: 'priority',
                    title: 'Priority',
                    render: function(data) {
                        var cls = 'prio-medium';
                        if (data === 'Critical') cls = 'prio-critical';
                        else if (data === 'High') cls = 'prio-high';
                        else if (data === 'Low') cls = 'prio-low';
                        return '<span class="status-badge ' + cls + '">' + data + '</span>';
                    }
                });

                // Column: Sales Person
                columns.push({ data: 'salesperson_name', title: 'Sales Person', defaultContent: '-' });

                <?php if ($role === 'admin'): ?>
                // Column: Added By (admin only)
                columns.push({ data: 'added_by_name', title: 'Added By', defaultContent: '-' });
                <?php endif; ?>

                // Column: Subscription Status
                columns.push({
                    data: 'subscription_status',
                    title: 'Sub Status',
                    render: function(d, type, row) {
                        var cls = d === 'active' ? 'status-active' : d === 'paused' ? 'status-warning' : 'status-danger';
                        var label = d.charAt(0).toUpperCase() + d.slice(1);
                        var html = '<span class="status-badge ' + cls + '">' + label + '</span>';
                        if (d === 'cancelled' && row.cancel_reason) {
                            html += '<br><small style="color:#888;font-size:10px;" title="' + escapeHtml(row.cancel_reason) + '">' + escapeHtml(row.cancel_reason.substring(0, 30)) + (row.cancel_reason.length > 30 ? '...' : '') + '</small>';
                        }
                        return html;
                    }
                });

                // Column: Actions
                columns.push({
                    data: null,
                    title: 'Actions',
                    orderable: false,
                    render: function(data, type, row) {
                        var html = '';
                        if (!isSalesperson) {
                            html += '<a href="add_subscription.php?edit=' + row.sl + '" class="action-icon edit-icon" title="Edit"><i class="fas fa-edit"></i></a>';
                        }
                        // Renew button
                        html += ' <button class="action-icon" onclick="renewSub(' + row.sl + ')" title="Renew Subscription"><i class="fas fa-redo" style="color:#28a745;"></i></button>';
                        // View Payments button
                        html += ' <button class="action-icon" onclick="viewPayments(' + row.sl + ', \'' + (row.customer_name || '').replace(/'/g, "\\'") + '\')" title="View Payments"><i class="fas fa-money-bill-wave" style="color:#ffc107;"></i></button>';
                        // Invoice PDF link
                        html += ' <a href="invoice.php?sl=' + row.sl + '" class="action-icon" title="Invoice PDF"><i class="fas fa-file-pdf" style="color:#dc3545;"></i></a>';
                        html += ' <button class="action-icon" onclick="sendReminder(' + row.sl + ')" title="Send Reminder"><i class="fas fa-paper-plane"></i></button>';
                        // docs
                        html += ' <button class="action-icon" onclick="showDocs(' + row.sl + ', \'' + (row.customer_name || '').replace(/'/g, "\\'") + '\')" title="Documents"><i class="fas fa-folder-open" style="color:#6c5ce7;"></i></button>';
                        // Payment status dropdown
                        html += ' <select class="payment-quick-select" onchange="updatePaymentStatus(' + row.sl + ', this.value)" style="font-size:12px;padding:2px 6px;border-radius:4px;border:1px solid #ccc;">';
                        ['Paid','Unpaid','Partial','Refunded'].forEach(function(s) {
                            html += '<option value="' + s + '"' + (row.payment_status === s ? ' selected' : '') + '>' + s + '</option>';
                        });
                        html += '</select>';
                        // Priority quick-select
                        html += ' <select class="priority-quick-select" onchange="updatePriority(' + row.sl + ', this.value)" style="font-size:12px;padding:2px 6px;border-radius:4px;border:1px solid #ccc;">';
                        ['Low','Medium','High','Critical'].forEach(function(p) {
                            html += '<option value="' + p + '"' + (row.priority === p ? ' selected' : '') + '>' + p + '</option>';
                        });
                        html += '</select>';
                        // pause/resume/cancel
                        if (row.subscription_status === 'active') {
                            html += ' <button class="action-icon" onclick="pauseSub(' + row.sl + ')" title="Pause"><i class="fas fa-pause-circle" style="color:#ff9800;"></i></button>';
                            html += ' <button class="action-icon" onclick="cancelSub(' + row.sl + ')" title="Cancel"><i class="fas fa-ban" style="color:#dc3545;"></i></button>';
                        } else if (row.subscription_status === 'paused') {
                            html += ' <button class="action-icon" onclick="resumeSub(' + row.sl + ')" title="Resume"><i class="fas fa-play-circle" style="color:#28a745;"></i></button>';
                            html += ' <button class="action-icon" onclick="cancelSub(' + row.sl + ')" title="Cancel"><i class="fas fa-ban" style="color:#dc3545;"></i></button>';
                        }
                        <?php if ($role === 'admin'): ?>
                        html += ' <button class="action-icon delete-icon" onclick="deleteSub(' + row.sl + ')" title="Delete"><i class="fas fa-trash"></i></button>';
                        <?php endif; ?>
                        return html;
                    }
                });

                // Determine export column indices (exclude checkbox col 0 and actions last col)
                var exportCols = [];
                var startIdx = isAdmin ? 1 : 0;
                var endIdx = columns.length - 2; // exclude actions column
                for (var i = startIdx; i <= endIdx; i++) {
                    exportCols.push(i);
                }

                subscriptionsTable = $('#subscriptionsTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: columns,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: exportCols }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: exportCols }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: exportCols }
                        }
                    ],
                    order: [[isAdmin ? 1 : 0, 'desc']],
                    createdRow: function(row, data) {
                        var ps = (data.payment_status || '').toLowerCase();
                        if (ps === 'paid') row.classList.add('row-paid');
                        else if (ps === 'unpaid') row.classList.add('row-unpaid');
                        else if (ps === 'partial') row.classList.add('row-partial');
                    }
                });

                // Apply custom filters on input/change
                $('#filterCustomer').on('keyup', function() {
                    applyFilters();
                });
                $('#filterPayment, #filterPriority, #filterProduct, #filterDateFrom, #filterDateTo').on('change', function() {
                    applyFilters();
                });
                <?php if ($role === 'admin'): ?>
                $('#filterAddedBy').on('change', function() {
                    applyFilters();
                });
                <?php endif; ?>

                // build column chooser
                buildColumnChooser();
            }, 100);
        }

        // ── Filter Logic ─────────────────────────────────────────────────────
        function applyFilters() {
            if (!subscriptionsTable) return;

            $.fn.dataTable.ext.search = [];

            var customerFilter = document.getElementById('filterCustomer').value.toLowerCase();
            var paymentFilter  = document.getElementById('filterPayment').value;
            var priorityFilter = document.getElementById('filterPriority').value;
            var productFilter = document.getElementById('filterProduct').value;
            var dateFrom       = document.getElementById('filterDateFrom').value;
            var dateTo         = document.getElementById('filterDateTo').value;
            var addedByFilter  = '';
            <?php if ($role === 'admin'): ?>
            addedByFilter = document.getElementById('filterAddedBy').value;
            <?php endif; ?>

            $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
                var row = subscriptionsData[dataIndex];
                if (!row) return true;

                // Customer filter
                if (customerFilter && row.customer_name.toLowerCase().indexOf(customerFilter) === -1) return false;

                // Payment filter
                if (paymentFilter && row.payment_status !== paymentFilter) return false;

                // Priority filter
                if (priorityFilter && row.priority !== priorityFilter) return false;

                // Product filter
                if (productFilter && row.product_name !== productFilter) return false;

                // Pipeline status filter (paused/cancelled from subscription_status)
                if (activePipelineStatus) {
                    if (activePipelineStatus === 'Paused') {
                        if (row.subscription_status !== 'paused') return false;
                    } else if (activePipelineStatus === 'Cancelled') {
                        if (row.subscription_status !== 'cancelled') return false;
                    } else {
                        if (row.subscription_status === 'paused' || row.subscription_status === 'cancelled') return false;
                        if (row.status_label !== activePipelineStatus) return false;
                    }
                }

                // Date range filter (expiry_date)
                if (dateFrom || dateTo) {
                    if (!row.expiry_date_raw) return false;
                    var expDate = new Date(row.expiry_date_raw);
                    if (dateFrom && expDate < new Date(dateFrom)) return false;
                    if (dateTo && expDate > new Date(dateTo + 'T23:59:59')) return false;
                }

                // Added By filter (admin only)
                if (addedByFilter && row.added_by_name !== addedByFilter) return false;

                return true;
            });

            subscriptionsTable.draw();
        }

        function clearFilters() {
            document.getElementById('filterCustomer').value = '';
            document.getElementById('filterPayment').value  = '';
            document.getElementById('filterPriority').value = '';
            document.getElementById('filterProduct').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value   = '';
            <?php if ($role === 'admin'): ?>
            document.getElementById('filterAddedBy').value  = '';
            <?php endif; ?>

            activePipelineStatus = null;
            renderPipeline();

            if (subscriptionsTable) {
                $.fn.dataTable.ext.search = [];
                subscriptionsTable.columns().search('').draw();
            }
        }

        // ── Pipeline Chevron ─────────────────────────────────────────────────
        var pipelineStatuses = [
            { name: 'Active',         color: '#28a745' },
            { name: 'Expiring Soon',  color: '#e67e00' },
            { name: 'Expiring Today', color: '#e65100' },
            { name: 'Expired',        color: '#dc3545' },
            { name: 'Paused',         color: '#ff9800' },
            { name: 'Cancelled',      color: '#6c757d' }
        ];

        function renderPipeline() {
            var container = document.getElementById('pipelineStages');
            if (!container) return;
            var total = subscriptionsData.length;

            // count per status (merge expiry status + subscription_status)
            var counts = {};
            subscriptionsData.forEach(function(r) {
                if (r.subscription_status === 'paused') {
                    counts['Paused'] = (counts['Paused'] || 0) + 1;
                } else if (r.subscription_status === 'cancelled') {
                    counts['Cancelled'] = (counts['Cancelled'] || 0) + 1;
                } else {
                    var s = r.status_label || 'Unknown';
                    counts[s] = (counts[s] || 0) + 1;
                }
            });

            var html = '';
            // "All" tab
            var allActive = !activePipelineStatus ? ' active' : '';
            html += '<div class="pipeline-stage' + allActive + '" onclick="filterByPipeline(null)" data-bg="#001f3f">' +
                '<span class="pipeline-stage-name">All</span>' +
                '<span class="pipeline-stage-count">(' + total + ')</span></div>';

            pipelineStatuses.forEach(function(s) {
                var count = counts[s.name] || 0;
                var isActive = activePipelineStatus === s.name ? ' active' : '';
                var emptyClass = count === 0 ? ' pipeline-empty' : '';
                html += '<div class="pipeline-stage' + isActive + emptyClass + '" data-bg="' + s.color + '" onclick="filterByPipeline(\'' + s.name.replace(/'/g, "\\'") + '\')">' +
                    '<span class="pipeline-stage-name">' + s.name + '</span>' +
                    '<span class="pipeline-stage-count">(' + count + ')</span></div>';
            });

            container.innerHTML = html;
            container.querySelectorAll('.pipeline-stage[data-bg]').forEach(function(el) {
                el.style.background = el.getAttribute('data-bg');
            });
        }

        function filterByPipeline(statusName) {
            activePipelineStatus = statusName;
            applyFilters();
            renderPipeline();
        }

        // ── CSV Import ───────────────────────────────────────────────────────
        <?php if ($role === 'admin'): ?>
        function openImportModal() {
            document.getElementById('csvFileInput').value = '';
            document.getElementById('importResult').style.display = 'none';
            document.getElementById('importResult').innerHTML = '';
            document.getElementById('importBtn').disabled = false;
            document.getElementById('importModal').classList.add('active');
        }

        function closeImportModal() {
            document.getElementById('importModal').classList.remove('active');
        }

        document.getElementById('importModal').addEventListener('click', function(e) {
            if (e.target === this) closeImportModal();
        });

        function submitImport() {
            var fileInput = document.getElementById('csvFileInput');
            if (!fileInput.files.length) {
                Swal.fire({ icon: 'warning', text: 'Please select a CSV file' });
                return;
            }

            var formData = new FormData();
            formData.append('action', 'importSubscriptions');
            formData.append('csv_file', fileInput.files[0]);

            var btn = document.getElementById('importBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

            $.ajax({
                url: 'subscriptions.php',
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
                        html += '<div style="color:#155724;"><strong>' + r.imported + '</strong> imported, <strong>' + r.skipped + '</strong> skipped</div>';
                        if (r.errors && r.errors.length > 0) {
                            html += '<div style="max-height:120px;overflow-y:auto;background:#fff3cd;padding:8px 12px;border-radius:4px;margin-top:10px;font-size:11px;color:#856404;border:1px solid #ffc107;">';
                            r.errors.forEach(function(e) { html += '<div>' + escapeHtml(e) + '</div>'; });
                            html += '</div>';
                        }
                        html += '</div>';
                        resDiv.innerHTML = html;
                        resDiv.style.display = '';
                        loadSubscriptions();
                    } else {
                        var html = '<div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;padding:14px 16px;font-size:13px;">';
                        html += '<div style="font-weight:600;color:#721c24;"><i class="fas fa-exclamation-circle"></i> ' + escapeHtml(r.message) + '</div>';
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

        // ── Bulk Selection (Admin) ───────────────────────────────────────────
        <?php if ($role === 'admin'): ?>
        function toggleSelectAll(masterCheckbox) {
            var checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(function(cb) {
                cb.checked = masterCheckbox.checked;
            });
            updateBulkCount();
        }

        function updateBulkCount() {
            var checked = document.querySelectorAll('.row-checkbox:checked');
            var count = checked.length;
            var show = count > 0 ? 'inline-flex' : 'none';

            document.getElementById('bulkDeleteBtn').style.display = show;
            document.getElementById('bulkCount').textContent = count;

            document.getElementById('bulkPaidBtn').style.display = show;
            document.getElementById('bulkPaidCount').textContent = count;

            document.getElementById('bulkRenewBtn').style.display = show;
            document.getElementById('bulkRenewCount').textContent = count;
        }

        function bulkDelete() {
            var checked = document.querySelectorAll('.row-checkbox:checked');
            if (checked.length === 0) return;

            var ids = [];
            checked.forEach(function(cb) {
                ids.push(cb.value);
            });

            document.getElementById('deleteSl').value = ids.join(',');
            document.getElementById('deleteType').value = 'bulk';
            document.getElementById('deleteTitle').textContent = 'Delete ' + ids.length + ' Subscription(s)';
            document.getElementById('deleteMsg').textContent = 'This action cannot be undone. All selected subscriptions and their related data will be permanently removed.';
            document.getElementById('deleteConfirmBtn').innerHTML = '<i class="fas fa-trash-alt"></i> Delete All';
            document.getElementById('deleteConfirmBtn').disabled = false;
            document.getElementById('deleteModal').classList.add('active');
        }
        <?php endif; ?>

        // ── Delete Single Subscription ──────────────────────────────────────
        function deleteSub(sl) {
            document.getElementById('deleteSl').value = sl;
            document.getElementById('deleteType').value = 'single';
            document.getElementById('deleteTitle').textContent = 'Delete Subscription';
            document.getElementById('deleteMsg').textContent = 'This action cannot be undone. The subscription and all related data will be permanently removed.';
            document.getElementById('deleteConfirmBtn').innerHTML = '<i class="fas fa-trash-alt"></i> Delete';
            document.getElementById('deleteConfirmBtn').disabled = false;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        document.getElementById('deleteModal').addEventListener('click', function(e) { if (e.target === this) closeDeleteModal(); });

        function submitDelete() {
            var type = document.getElementById('deleteType').value;
            var btn = document.getElementById('deleteConfirmBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

            var fd = new FormData();
            var url;
            if (type === 'bulk') {
                fd.append('ids', document.getElementById('deleteSl').value);
                url = '?action=bulkDeleteSubscriptions';
            } else {
                fd.append('sl', document.getElementById('deleteSl').value);
                url = '?action=deleteSubscription';
            }

            $.ajax({
                url: url, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
                success: function(r) {
                    closeDeleteModal();
                    if (r.success) {
                        Swal.fire({ icon: 'success', text: r.message, timer: 2000, showConfirmButton: false });
                        if (type === 'bulk') document.getElementById('bulkDeleteBtn').style.display = 'none';
                        setTimeout(function() { loadSubscriptions(); loadFinancialSummary(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message });
                    }
                },
                error: function(x, s, e) {
                    closeDeleteModal();
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + e });
                }
            });
        }

        // ── Update Payment Status ────────────────────────────────────────────
        function updatePaymentStatus(sl, newStatus) {
            Swal.fire({
                title: 'Updating...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            var formData = new FormData();
            formData.append('sl', sl);
            formData.append('payment_status', newStatus);

            $.ajax({
                url: '?action=updatePaymentStatus',
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
                        setTimeout(function() {
                            loadSubscriptions();
                            loadFinancialSummary();
                        }, 100);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                        setTimeout(function() { loadSubscriptions(); }, 100);
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

        // ── Send Manual Reminder ─────────────────────────────────────────────
        function sendReminder(sl) {
            showConfirmAction({
                title: '<i class="fas fa-envelope"></i> Send Reminder',
                msg: 'An email reminder will be sent to the customer for this subscription.',
                btnText: '<i class="fas fa-paper-plane"></i> Send Reminder',
                btnColor: '#0074D9',
                headerBg: 'linear-gradient(135deg,#0074D9 0%,#005bb5 100%)',
                alertBg: '#e3f2fd', alertBorder: '#90caf9', alertColor: '#0d47a1',
                icon: 'fas fa-envelope', loadingText: 'Sending...',
                onConfirm: function(btn) {
                    var fd = new FormData(); fd.append('sl', sl);
                    ajaxConfirmAction('?action=sendManualReminder', fd, btn);
                }
            });
        }

        // ── Renew Subscription ───────────────────────────────────────────────
        function renewSub(sl) {
            showConfirmAction({
                title: '<i class="fas fa-redo"></i> Renew Subscription',
                msg: 'This will create a new subscription copying all details with advanced dates and Unpaid status.',
                btnText: '<i class="fas fa-redo"></i> Renew',
                btnColor: '#28a745',
                headerBg: 'linear-gradient(135deg,#28a745 0%,#1e7e34 100%)',
                alertBg: '#d4edda', alertBorder: '#c3e6cb', alertColor: '#155724',
                icon: 'fas fa-redo', loadingText: 'Renewing...',
                onConfirm: function(btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Renewing...';
                    var fd = new FormData(); fd.append('sl', sl);
                    $.ajax({
                        url: '?action=renewSubscription', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
                        success: function(r) {
                            closeConfirmModal();
                            if (r.success) {
                                Swal.fire({
                                    icon: 'success', title: 'Renewed!',
                                    html: 'New invoice: <strong>' + r.new_invoice + '</strong>',
                                    showCancelButton: true, confirmButtonText: 'Edit Renewal', cancelButtonText: 'Stay Here'
                                }).then(function(res) {
                                    if (res.isConfirmed) window.location.href = 'add_subscription.php?edit=' + r.new_sl;
                                    else loadSubscriptions();
                                });
                            } else { Swal.fire({ icon: 'error', title: 'Error', text: r.message }); }
                        },
                        error: function(x, s, e) { closeConfirmModal(); Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + e }); }
                    });
                }
            });
        }

        // ── View Payments ────────────────────────────────────────────────────
        function viewPayments(sl, customerName) {
            Swal.fire({
                title: '',
                html: '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#0074D9;"></i><p style="margin-top:10px;color:#666;">Loading...</p></div>',
                width: 750,
                showConfirmButton: false,
                showCloseButton: true,
                padding: 0,
                customClass: { popup: 'swal-no-padding' },
                didOpen: function() {
                    $.ajax({
                        url: 'payments.php?action=getPaymentsBySubscription&sl=' + sl,
                        method: 'GET',
                        dataType: 'json',
                        success: function(r) {
                            var payments = (r.success && r.data) ? r.data : [];
                            var total = 0;
                            payments.forEach(function(p) { total += parseFloat(p.amount) || 0; });

                            var html = '';

                            // Branded header
                            html += '<div style="background:linear-gradient(135deg,var(--navy-primary,#001f3f) 0%,var(--navy-light,#003366) 100%);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;">';
                            html += '<i class="fas fa-money-bill-wave" style="font-size:20px;color:var(--navy-accent,#0074D9);"></i>';
                            html += '<div><div style="font-size:16px;font-weight:700;">' + escapeHtml(customerName) + '</div><div style="font-size:11px;opacity:.7;">Payment History</div></div>';
                            html += '</div>';

                            // Stats row
                            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid #e9ecef;">';
                            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:var(--navy-primary,#001f3f);">' + payments.length + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Payments</div></div>';
                            html += '<div style="padding:14px;text-align:center;"><div style="font-size:22px;font-weight:700;color:#28a745;">' + total.toFixed(3) + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Total Paid</div></div>';
                            html += '</div>';

                            if (payments.length === 0) {
                                html += '<div style="padding:50px 20px;text-align:center;color:#888;"><i class="fas fa-inbox" style="font-size:40px;color:#ddd;display:block;margin-bottom:14px;"></i>No payment records found</div>';
                            } else {
                                html += '<div style="padding:20px;">';
                                html += '<div class="about-table-wrapper" style="margin:0;border-radius:4px;overflow:hidden;border:1px solid #e0e0e0;">';
                                html += '<table class="about-roles-table" style="font-size:13px;margin:0;">';
                                html += '<thead><tr><th style="text-align:left;">Date</th><th>Method</th><th style="text-align:right;">Amount</th><th>Reference</th></tr></thead>';
                                html += '<tbody>';
                                payments.forEach(function(p) {
                                    html += '<tr>';
                                    html += '<td style="text-align:left;font-weight:600;">' + (p.payment_date || '-') + '</td>';
                                    html += '<td>' + (p.payment_method || '-') + '</td>';
                                    html += '<td style="text-align:right;font-weight:600;">' + parseFloat(p.amount).toFixed(3) + '</td>';
                                    html += '<td>' + (p.reference_no || '-') + '</td>';
                                    html += '</tr>';
                                });
                                html += '</tbody></table></div></div>';
                            }

                            // Bottom bar with print
                            html += '<div style="padding:14px 20px;border-top:1px solid #e9ecef;background:#f8f9fa;display:flex;align-items:center;justify-content:space-between;">';
                            html += '<span style="font-size:12px;color:#888;">Total: <strong style="color:var(--navy-primary,#001f3f);">' + total.toFixed(3) + '</strong></span>';
                            html += '<button onclick="printPaymentHistory(\'' + customerName.replace(/'/g, "\\'") + '\')" style="display:inline-flex;align-items:center;gap:8px;padding:8px 20px;background:var(--navy-primary,#001f3f);color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:13px;font-weight:600;transition:all .2s;" onmouseover="this.style.opacity=\'0.85\'" onmouseout="this.style.opacity=\'1\'"><i class="fas fa-print"></i> Print</button>';
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

        // ── Escape HTML helper ────────────────────────────────────────────
        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        // ── Reusable confirm modal helpers ───────────────────────────────────
        function showConfirmAction(opts) {
            var h = document.getElementById('confirmHeader');
            h.style.background = opts.headerBg || 'var(--navy-primary)';
            document.getElementById('confirmTitle').innerHTML = opts.title;

            var alert = document.getElementById('confirmAlert');
            alert.style.background = opts.alertBg || '#e3f2fd';
            alert.style.border = '1px solid ' + (opts.alertBorder || '#90caf9');
            document.getElementById('confirmAlertIcon').className = opts.icon || 'fas fa-info-circle';
            document.getElementById('confirmAlertIcon').style.color = opts.alertColor || '#0d47a1';
            document.getElementById('confirmAlertMsg').style.color = opts.alertColor || '#0d47a1';
            document.getElementById('confirmAlertMsg').textContent = opts.msg;

            var btn = document.getElementById('confirmBtn');
            btn.innerHTML = opts.btnText;
            btn.style.background = opts.btnColor || '#0074D9';
            btn.style.borderColor = opts.btnColor || '#0074D9';
            btn.disabled = false;
            btn.onclick = function() { opts.onConfirm(btn); };

            document.getElementById('confirmModal').classList.add('active');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }
        document.getElementById('confirmModal').addEventListener('click', function(e) { if (e.target === this) closeConfirmModal(); });

        // generic ajax for confirm actions
        function ajaxConfirmAction(url, fd, btn, refreshSummary) {
            var origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            $.ajax({
                url: url, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
                success: function(r) {
                    closeConfirmModal();
                    if (r.success) {
                        Swal.fire({ icon: 'success', text: r.message, timer: 1500, showConfirmButton: false });
                        setTimeout(function() { loadSubscriptions(); if (refreshSummary) loadFinancialSummary(); }, 100);
                    } else { Swal.fire({ icon: 'error', title: 'Error', text: r.message }); }
                },
                error: function(x, s, e) {
                    closeConfirmModal();
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + e });
                }
            });
        }

        // ── View Customer (from subscriptionsData) ──────────────────────────
        function viewCustomer(name) {
            var matches = subscriptionsData.filter(function(r) {
                return r.customer_name === name;
            });
            var totalAmt = 0;
            matches.forEach(function(r) { totalAmt += r.total_amount; });

            var html = '<div style="background:linear-gradient(135deg,#001f3f 0%,#003366 100%);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;">';
            html += '<i class="fas fa-user-circle" style="font-size:28px;color:#0074D9;"></i>';
            html += '<div><div style="font-size:16px;font-weight:700;">' + escapeHtml(name) + '</div><div style="font-size:11px;opacity:.7;">Customer Overview</div></div>';
            html += '</div>';

            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid #e9ecef;">';
            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:#001f3f;">' + matches.length + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Subscriptions</div></div>';
            html += '<div style="padding:14px;text-align:center;"><div style="font-size:22px;font-weight:700;color:#28a745;">' + globalCurrency + ' ' + formatNumber(totalAmt) + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Total Amount</div></div>';
            html += '</div>';

            if (matches.length > 0) {
                html += '<div style="padding:16px;">';
                html += '<div class="about-table-wrapper" style="margin:0;border-radius:4px;overflow:hidden;border:1px solid #e0e0e0;">';
                html += '<table class="about-roles-table" style="font-size:12px;margin:0;">';
                html += '<thead><tr><th>Invoice</th><th>Product</th><th>Expiry</th><th>Amount</th><th>Payment</th></tr></thead><tbody>';
                matches.forEach(function(r) {
                    html += '<tr>';
                    html += '<td>' + escapeHtml(r.invoice_no) + '</td>';
                    html += '<td>' + escapeHtml(r.product_name) + '</td>';
                    html += '<td>' + (r.expiry_date || '-') + '</td>';
                    html += '<td style="text-align:right;">' + (r.currency_code || globalCurrency) + ' ' + formatNumber(r.total_amount) + '</td>';
                    html += '<td><span class="status-badge pay-' + r.payment_status.toLowerCase() + '">' + r.payment_status + '</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div></div>';
            }

            Swal.fire({
                html: html,
                width: 650,
                showCloseButton: true,
                showConfirmButton: false,
                padding: 0,
                customClass: { popup: 'swal-no-padding' }
            });
        }

        // ── Update Priority ─────────────────────────────────────────────────
        function updatePriority(sl, value) {
            var fd = new FormData();
            fd.append('sl', sl);
            fd.append('priority', value);

            $.ajax({
                url: '?action=updatePriority',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(r) {
                    if (r.success) {
                        Swal.fire({ icon: 'success', text: r.message, timer: 1500, showConfirmButton: false });
                        setTimeout(function() { loadSubscriptions(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message });
                        setTimeout(function() { loadSubscriptions(); }, 100);
                    }
                },
                error: function(x, s, e) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + e });
                }
            });
        }

        // pause sub
        function pauseSub(sl) {
            showConfirmAction({
                title: '<i class="fas fa-pause-circle"></i> Pause Subscription',
                msg: 'This will put the subscription on hold. You can resume it later.',
                btnText: '<i class="fas fa-pause"></i> Pause',
                btnColor: '#ff9800',
                headerBg: 'linear-gradient(135deg,#ff9800 0%,#e68900 100%)',
                alertBg: '#fff3cd', alertBorder: '#ffc107', alertColor: '#856404',
                icon: 'fas fa-pause-circle', loadingText: 'Pausing...',
                onConfirm: function(btn) {
                    var fd = new FormData(); fd.append('sl', sl);
                    ajaxConfirmAction('?action=pauseSubscription', fd, btn, true);
                }
            });
        }

        // resume sub
        function resumeSub(sl) {
            showConfirmAction({
                title: '<i class="fas fa-play-circle"></i> Resume Subscription',
                msg: 'This will reactivate the paused subscription.',
                btnText: '<i class="fas fa-play"></i> Resume',
                btnColor: '#28a745',
                headerBg: 'linear-gradient(135deg,#28a745 0%,#1e7e34 100%)',
                alertBg: '#d4edda', alertBorder: '#c3e6cb', alertColor: '#155724',
                icon: 'fas fa-play-circle', loadingText: 'Resuming...',
                onConfirm: function(btn) {
                    var fd = new FormData(); fd.append('sl', sl);
                    ajaxConfirmAction('?action=resumeSubscription', fd, btn, true);
                }
            });
        }

        // cancel sub with reason
        function cancelSub(sl) {
            document.getElementById('cancelSl').value = sl;
            document.getElementById('cancelReason').value = '';
            document.getElementById('cancelConfirmBtn').disabled = false;
            document.getElementById('cancelConfirmBtn').innerHTML = '<i class="fas fa-ban"></i> Cancel Subscription';
            document.getElementById('cancelModal').classList.add('active');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }
        document.getElementById('cancelModal').addEventListener('click', function(e) { if (e.target === this) closeCancelModal(); });

        function submitCancel() {
            var reason = document.getElementById('cancelReason').value.trim();
            if (!reason) { Swal.fire({ icon: 'warning', text: 'Please provide a reason for cancellation' }); return; }

            var btn = document.getElementById('cancelConfirmBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';

            var fd = new FormData();
            fd.append('sl', document.getElementById('cancelSl').value);
            fd.append('cancel_reason', reason);

            $.ajax({
                url: '?action=cancelSubscription', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
                success: function(r) {
                    closeCancelModal();
                    if (r.success) {
                        Swal.fire({ icon: 'success', text: r.message, timer: 1500, showConfirmButton: false });
                        setTimeout(function() { loadSubscriptions(); loadFinancialSummary(); }, 100);
                    } else { Swal.fire({ icon: 'error', title: 'Error', text: r.message }); }
                },
                error: function(x, s, e) {
                    closeCancelModal();
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + e });
                }
            });
        }

        // ── Bulk Mark Paid (Admin) ──────────────────────────────────────────
        function bulkMarkPaid() {
            var checked = document.querySelectorAll('.row-checkbox:checked');
            if (checked.length === 0) return;
            var ids = [];
            checked.forEach(function(cb) { ids.push(cb.value); });

            showConfirmAction({
                title: '<i class="fas fa-check-circle"></i> Mark ' + ids.length + ' as Paid',
                msg: 'Payment date will be set to today for all selected subscriptions.',
                btnText: '<i class="fas fa-check"></i> Mark Paid',
                btnColor: '#28a745',
                headerBg: 'linear-gradient(135deg,#28a745 0%,#1e7e34 100%)',
                alertBg: '#d4edda', alertBorder: '#c3e6cb', alertColor: '#155724',
                icon: 'fas fa-check-circle', loadingText: 'Updating...',
                onConfirm: function(btn) {
                    var fd = new FormData(); fd.append('ids', ids.join(','));
                    ajaxConfirmAction('?action=bulkMarkPaid', fd, btn, true);
                }
            });
        }

        // ── Bulk Renew ──────────────────────────────────────────────────────
        function bulkRenew() {
            var checked = document.querySelectorAll('.row-checkbox:checked');
            if (checked.length === 0) return;
            var ids = [];
            checked.forEach(function(cb) { ids.push(cb.value); });

            showConfirmAction({
                title: '<i class="fas fa-redo"></i> Renew ' + ids.length + ' Subscription(s)',
                msg: 'New subscriptions will be created with advanced dates and Unpaid status.',
                btnText: '<i class="fas fa-redo"></i> Renew All',
                btnColor: '#0074D9',
                headerBg: 'linear-gradient(135deg,#0074D9 0%,#005bb5 100%)',
                alertBg: '#e3f2fd', alertBorder: '#90caf9', alertColor: '#0d47a1',
                icon: 'fas fa-redo', loadingText: 'Renewing...',
                onConfirm: function(btn) {
                    var fd = new FormData(); fd.append('ids', ids.join(','));
                    ajaxConfirmAction('?action=bulkRenew', fd, btn, true);
                }
            });
        }

        // ── Column Visibility Toggle ────────────────────────────────────────
        function toggleColumnChooser() {
            var el = document.getElementById('columnChooser');
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        }

        function buildColumnChooser() {
            if (!subscriptionsTable) return;
            var container = document.getElementById('columnChooser');
            container.innerHTML = '';
            subscriptionsTable.columns().every(function(idx) {
                var col = this;
                var title = $(col.header()).text().trim();
                if (!title || title === '' || idx === 0 && isAdmin) return; // skip checkbox col
                var label = document.createElement('label');
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = col.visible();
                cb.addEventListener('change', function() {
                    col.visible(this.checked);
                });
                label.appendChild(cb);
                label.appendChild(document.createTextNode(' ' + title));
                container.appendChild(label);
            });
        }

        // close column chooser on outside click
        document.addEventListener('click', function(e) {
            var chooser = document.getElementById('columnChooser');
            if (!chooser || chooser.style.display === 'none') return;
            if (!e.target.closest('.column-chooser') && !e.target.closest('[onclick*="toggleColumnChooser"]')) {
                chooser.style.display = 'none';
            }
        });

        function printPaymentHistory(customerName) {
            customerName = escapeHtml(customerName);
            var el = document.querySelector('.swal2-html-container');
            if (!el) return;
            var w = window.open('', '_blank', 'width=800,height=600');
            w.document.write('<!DOCTYPE html><html><head><title>Payments - ' + customerName + '</title><style>body{font-family:Arial,sans-serif;margin:20px;color:#333;}h2{color:#001f3f;margin-bottom:5px;}table{width:100%;border-collapse:collapse;font-size:13px;}th{background:#001f3f;color:#fff;padding:10px 12px;text-align:left;}td{padding:8px 12px;border-bottom:1px solid #e0e0e0;}tr:nth-child(even){background:#f8f9fa;}@media print{body{margin:10px;}}</style></head><body>');
            w.document.write('<h2>' + customerName + ' — Payment History</h2><p style="color:#666;font-size:13px;margin-bottom:15px;">Generated: ' + new Date().toLocaleDateString() + '</p>');
            var table = el.querySelector('.about-roles-table');
            if (table) w.document.write(table.outerHTML);
            w.document.write('</body></html>');
            w.document.close();
            w.focus();
            setTimeout(function() { w.print(); }, 300);
        }

        // ── Documents ───────────────────────────────────────────────────────
        function formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function getFileIcon(type) {
            if (!type) return 'fa-file';
            if (type.indexOf('pdf') !== -1) return 'fa-file-pdf';
            if (type.indexOf('word') !== -1 || type.indexOf('doc') !== -1) return 'fa-file-word';
            if (type.indexOf('sheet') !== -1 || type.indexOf('excel') !== -1 || type.indexOf('csv') !== -1) return 'fa-file-excel';
            if (type.indexOf('image') !== -1) return 'fa-file-image';
            if (type.indexOf('zip') !== -1) return 'fa-file-archive';
            if (type.indexOf('text') !== -1) return 'fa-file-alt';
            return 'fa-file';
        }

        var _docsSl = null;
        var _docsCustomer = '';

        function showDocs(sl, customerName) {
            _docsSl = sl;
            _docsCustomer = customerName || 'SL#' + sl;
            _renderDocsModal([], true);
            _fetchDocs(sl);
        }

        function _fetchDocs(sl) {
            $.ajax({
                url: '?action=getDocuments&subscription_sl=' + sl,
                method: 'GET',
                dataType: 'json',
                success: function(r) {
                    if (r.success) {
                        _renderDocsModal(r.data, false);
                    } else {
                        _renderDocsModal([], false, r.message);
                    }
                },
                error: function() {
                    _renderDocsModal([], false, 'Connection error');
                }
            });
        }

        function _renderDocsModal(docs, loading, errMsg) {
            var html = '';

            // header
            html += '<div style="background:linear-gradient(135deg,#001f3f 0%,#003366 100%);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;">';
            html += '<i class="fas fa-folder-open" style="font-size:20px;color:#6c5ce7;"></i>';
            html += '<div><div style="font-size:16px;font-weight:700;">' + escapeHtml(_docsCustomer) + '</div><div style="font-size:11px;opacity:.7;">Documents &amp; Contracts</div></div>';
            html += '</div>';

            if (loading) {
                html += '<div style="padding:40px;text-align:center;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#0074D9;"></i><p style="margin-top:10px;color:#666;">Loading documents...</p></div>';
            } else if (errMsg) {
                html += '<div style="padding:30px;text-align:center;color:#dc3545;"><i class="fas fa-exclamation-triangle" style="font-size:24px;display:block;margin-bottom:10px;"></i>' + escapeHtml(errMsg) + '</div>';
            } else {
                // count
                html += '<div style="padding:12px 20px;border-bottom:1px solid #e9ecef;background:#f8f9fa;">';
                html += '<span style="font-size:13px;color:#666;"><i class="fas fa-paperclip"></i> <strong>' + docs.length + '</strong> document' + (docs.length !== 1 ? 's' : '') + '</span>';
                html += '</div>';

                if (docs.length === 0) {
                    html += '<div style="padding:40px 20px;text-align:center;color:#888;"><i class="fas fa-folder-open" style="font-size:36px;color:#ddd;display:block;margin-bottom:12px;"></i>No documents uploaded yet</div>';
                } else {
                    html += '<div style="max-height:300px;overflow-y:auto;padding:12px 20px;">';
                    docs.forEach(function(d) {
                        var icon = getFileIcon(d.file_type);
                        html += '<div style="display:flex;align-items:center;gap:12px;padding:10px;border:1px solid #e9ecef;border-radius:6px;margin-bottom:8px;background:#fff;">';
                        html += '<i class="fas ' + icon + '" style="font-size:24px;color:#6c5ce7;flex-shrink:0;"></i>';
                        html += '<div style="flex:1;min-width:0;">';
                        html += '<div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + escapeHtml(d.original_name) + '">' + escapeHtml(d.original_name) + '</div>';
                        html += '<div style="font-size:11px;color:#888;">' + formatFileSize(d.file_size) + ' &bull; ' + (d.uploaded_by_name || 'Unknown') + ' &bull; ' + (d.created_at_fmt || '') + '</div>';
                        html += '</div>';
                        html += '<div style="display:flex;gap:6px;flex-shrink:0;">';
                        html += '<a href="?action=downloadDocument&document_id=' + d.document_id + '" class="action-icon" title="Download" style="color:#0074D9;font-size:16px;"><i class="fas fa-download"></i></a>';
                        if (isAdmin) {
                            html += ' <button class="action-icon" onclick="deleteDoc(' + d.document_id + ')" title="Delete" style="color:#dc3545;font-size:16px;border:none;background:none;cursor:pointer;"><i class="fas fa-trash"></i></button>';
                        }
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }
            }

            // upload form at bottom
            html += '<div style="padding:16px 20px;border-top:1px solid #e9ecef;background:#f8f9fa;">';
            html += '<div style="display:flex;gap:8px;align-items:center;">';
            html += '<input type="file" id="docFileInput" style="flex:1;font-size:13px;padding:6px;border:1px solid #ccc;border-radius:4px;" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt,.csv,.zip">';
            html += '<button onclick="uploadDoc()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#6c5ce7;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;font-weight:600;white-space:nowrap;" onmouseover="this.style.opacity=\'0.85\'" onmouseout="this.style.opacity=\'1\'"><i class="fas fa-upload"></i> Upload</button>';
            html += '</div>';
            html += '<div style="font-size:10px;color:#999;margin-top:6px;">Max 10MB. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, TXT, CSV, ZIP</div>';
            html += '</div>';

            // show/update swal
            if (loading) {
                Swal.fire({
                    html: html,
                    width: 620,
                    showCloseButton: true,
                    showConfirmButton: false,
                    padding: 0,
                    customClass: { popup: 'swal-no-padding' }
                });
            } else {
                Swal.update({ html: html });
            }
        }

        function uploadDoc() {
            var fileInput = document.getElementById('docFileInput');
            if (!fileInput || !fileInput.files.length) {
                Swal.fire({ icon: 'warning', text: 'Please select a file first', timer: 2000, showConfirmButton: false });
                return;
            }

            var fd = new FormData();
            fd.append('subscription_sl', _docsSl);
            fd.append('document', fileInput.files[0]);

            // disable upload btn
            var btn = fileInput.nextElementSibling;
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...'; }

            $.ajax({
                url: '?action=uploadDocument',
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
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            showDocs(_docsSl, _docsCustomer);
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Upload Failed', text: r.message }).then(function() {
                            showDocs(_docsSl, _docsCustomer);
                        });
                    }
                },
                error: function(x, s, e) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + e }).then(function() {
                        showDocs(_docsSl, _docsCustomer);
                    });
                }
            });
        }

        function deleteDoc(docId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Document?',
                text: 'This will permanently remove the file',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
                    var fd = new FormData();
                    fd.append('document_id', docId);
                    $.ajax({
                        url: '?action=deleteDocument',
                        method: 'POST',
                        data: fd,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(r) {
                            if (r.success) {
                                Swal.fire({ icon: 'success', text: r.message, timer: 1500, showConfirmButton: false }).then(function() {
                                    showDocs(_docsSl, _docsCustomer);
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: r.message }).then(function() {
                                    showDocs(_docsSl, _docsCustomer);
                                });
                            }
                        },
                        error: function(x, s, e) {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + e }).then(function() {
                                showDocs(_docsSl, _docsCustomer);
                            });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
