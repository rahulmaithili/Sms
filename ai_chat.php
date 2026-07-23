<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * AI Chat Assistant - Admin Only
 */

require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!checkSessionTimeout()) { header("Location: login.php"); exit(); }

$username  = $_SESSION['username'];
$role      = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_id   = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'ai_chat';

// admin only
if ($role !== 'admin') { header("Location: dashboard.php"); exit(); }

// AJAX handlers
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {

        case 'askAI':
            $question = isset($_POST['question']) ? trim($_POST['question']) : '';
            if (empty($question)) {
                echo json_encode(['success' => false, 'message' => 'Please enter a question']);
                exit();
            }

            $api_key = getSetting('gemini_api_key', '');
            if (empty($api_key)) {
                echo json_encode(['success' => false, 'message' => 'Gemini API key not configured. Go to Settings > AI Chat Settings.']);
                exit();
            }

            // get conversation history from POST
            $history = isset($_POST['history']) ? json_decode($_POST['history'], true) : [];
            if (!is_array($history)) $history = [];

            // gather DB context
            $context = gatherDbContext();

            // build Gemini request
            $system_prompt = "You are an elite AI analyst for a Subscription Management System. You have READ-ONLY access to the database. Answer questions about subscriptions, payments, refunds, customers, suppliers, products, salespersons, users, tax rates, custom fields, and documents based on the data provided.\n\nTHINKING PROCESS (mandatory for every answer):\nBefore giving your final answer, you MUST follow this internal chain-of-thought process:\n1. UNDERSTAND — Restate the user's question in your own words to make sure you get it right\n2. GATHER — Identify which data points from the context are relevant\n3. ANALYZE — Do the math, find patterns, cross-reference numbers\n4. CHALLENGE — Ask yourself counter-questions: 'Is this really accurate?', 'Am I missing something?', 'What if I'm wrong?', 'Does this number make sense when I sanity-check it against other data?'\n5. VERIFY — Double-check your calculations, re-read the data, fix any errors you find\n6. ANSWER — Only after steps 1-5, give the final polished answer\n\nShow your thinking briefly at the top like:\n**Analysis:** [1-2 lines about what you checked and cross-verified]\nThen give the clean answer below it.\n\nIMPORTANT RULES:\n- Only answer based on the data provided below\n- Format currency values with the system currency\n- Use tables/lists for structured data when helpful\n- Be concise but thorough\n- If asked to modify data, explain you have read-only access\n- If you're not confident in an answer, say so — never make up data\n- Cross-verify totals: e.g., if you say 'total revenue is X', check it matches the SUBSCRIPTION OVERVIEW stats\n- When presenting numbers, sanity-check: does the count match? does paid + unpaid + partial = total?\n- Today's date is " . date('Y-m-d') . "\n\nFORECASTING & PREDICTIONS:\nWhen asked about predictions, forecasts, next month, next quarter, or next year:\n- Analyze MONTHLY REVENUE TREND data to identify growth/decline patterns and project future revenue\n- Use UPCOMING EXPIRIES to predict which subscriptions will need renewal and potential revenue loss if not renewed\n- Calculate renewal rate from historical data (renewed vs expired)\n- Estimate future revenue = current active recurring revenue +/- trend adjustment\n- Factor in auto_renew flags — subscriptions with auto_renew=Yes are likely to continue\n- Predict cash flow: upcoming payments due, expected collections based on payment history\n- For customer predictions: identify at-risk customers (multiple unpaid, expiring soon) vs growth customers (increasing spend)\n- For salesperson predictions: project commission payouts based on pipeline\n- Always show your calculation method and assumptions clearly\n- Present predictions in clear tables with optimistic/realistic/pessimistic scenarios when appropriate\n- Warn about any data limitations (e.g., 'only 6 months of data available for trend analysis')\n\nDATABASE CONTEXT:\n" . $context;

            // build contents array with history
            $contents = [];

            // add history
            foreach ($history as $msg) {
                $contents[] = [
                    'role' => $msg['role'] === 'user' ? 'user' : 'model',
                    'parts' => [['text' => $msg['text']]]
                ];
            }

            // add current question
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $question]]
            ];

            $payload = [
                'system_instruction' => ['parts' => [['text' => $system_prompt]]],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 2048
                ]
            ];

            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($api_key);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                echo json_encode(['success' => false, 'message' => 'Connection error: ' . $curl_error]);
                exit();
            }

            $data = json_decode($response, true);

            if ($http_code !== 200 || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $err_msg = $data['error']['message'] ?? 'Unknown API error (HTTP ' . $http_code . ')';
                echo json_encode(['success' => false, 'message' => 'Gemini API error: ' . $err_msg]);
                exit();
            }

            $answer = $data['candidates'][0]['content']['parts'][0]['text'];

            logActivity($user_id, $username, 'AI Chat', 'Asked: ' . substr($question, 0, 100));

            echo json_encode(['success' => true, 'answer' => $answer]);
            exit();

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
}

// DB context builder - gathers summary data from all tables
function gatherDbContext() {
    $conn = getDBConnection();
    $currency = getCurrency();
    $ctx = "Currency: $currency\n\n";

    // sub stats
    $r = $conn->query("SELECT COUNT(*) AS total,
        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date > CURDATE() AND DATEDIFF(expiry_date,CURDATE())>30 THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date > CURDATE() AND DATEDIFF(expiry_date,CURDATE())<=30 THEN 1 ELSE 0 END) AS expiring_soon,
        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date = CURDATE() THEN 1 ELSE 0 END) AS expiring_today,
        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
        SUM(total_amount) AS total_revenue,
        SUM((selling_price - tax_amount) - purchase_price) AS total_profit,
        SUM(CASE WHEN payment_status='Paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(CASE WHEN payment_status='Unpaid' THEN 1 ELSE 0 END) AS unpaid_count,
        SUM(CASE WHEN payment_status='Partial' THEN 1 ELSE 0 END) AS partial_count,
        SUM(CASE WHEN payment_status='Unpaid' THEN total_amount ELSE 0 END) AS unpaid_amount
        FROM subscriptions");
    $stats = $r->fetch_assoc();
    $ctx .= "SUBSCRIPTION OVERVIEW:\n";
    $ctx .= "Total: {$stats['total']}, Active: {$stats['active']}, Expiring Soon (<=30d): {$stats['expiring_soon']}, Expiring Today: {$stats['expiring_today']}, Expired: {$stats['expired']}\n";
    $ctx .= "Revenue: {$currency} " . round($stats['total_revenue'] ?? 0, 3) . ", Profit: {$currency} " . round($stats['total_profit'] ?? 0, 3) . "\n";
    $ctx .= "Payment Status: Paid={$stats['paid_count']}, Unpaid={$stats['unpaid_count']}, Partial={$stats['partial_count']}\n";
    $ctx .= "Unpaid Amount: {$currency} " . round($stats['unpaid_amount'] ?? 0, 3) . "\n\n";

    // all subs detail
    $r = $conn->query("SELECT s.sl, s.customer_name, s.invoice_no, s.invoice_date, s.expiry_date,
        s.total_amount, s.selling_price, s.purchase_price, s.tax_amount, s.payment_status, s.priority,
        s.auto_renew, s.license_duration, s.user_qty,
        p.product_name, sp.name AS salesperson_name, s.supplier_name
        FROM subscriptions s
        LEFT JOIN products p ON s.product_id = p.product_id
        LEFT JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id
        ORDER BY s.sl DESC LIMIT 200");
    $ctx .= "ALL SUBSCRIPTIONS (latest 200):\n";
    $ctx .= "SL | Customer | Invoice | Product | InvDate | ExpiryDate | Amount | Profit | PayStatus | Priority | AutoRenew | Salesperson | Supplier\n";
    while ($row = $r->fetch_assoc()) {
        $profit = round(($row['selling_price'] - $row['tax_amount']) - $row['purchase_price'], 3);
        $ctx .= "{$row['sl']} | {$row['customer_name']} | {$row['invoice_no']} | " . ($row['product_name'] ?? 'N/A') . " | {$row['invoice_date']} | {$row['expiry_date']} | {$row['total_amount']} | {$profit} | {$row['payment_status']} | {$row['priority']} | " . ($row['auto_renew'] ? 'Yes' : 'No') . " | " . ($row['salesperson_name'] ?? 'N/A') . " | " . ($row['supplier_name'] ?? 'N/A') . "\n";
    }

    // customers
    $r = $conn->query("SELECT customer_id, company_name, contact_person, email, phone, city, country, is_active FROM customers ORDER BY company_name LIMIT 100");
    $ctx .= "\nCUSTOMERS:\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "#{$row['customer_id']} {$row['company_name']} | Contact: " . ($row['contact_person'] ?? 'N/A') . " | {$row['email']} | {$row['phone']} | {$row['city']}, {$row['country']} | Active: " . ($row['is_active'] ? 'Yes' : 'No') . "\n";
    }

    // suppliers
    $r = $conn->query("SELECT supplier_id, company_name, contact_person, email, phone, city, country, is_active FROM suppliers ORDER BY company_name LIMIT 100");
    $ctx .= "\nSUPPLIERS:\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "#{$row['supplier_id']} {$row['company_name']} | Contact: " . ($row['contact_person'] ?? 'N/A') . " | {$row['email']} | {$row['phone']} | {$row['city']}, {$row['country']} | Active: " . ($row['is_active'] ? 'Yes' : 'No') . "\n";
    }

    // products
    $r = $conn->query("SELECT product_id, product_name, description, is_active FROM products ORDER BY display_order");
    $ctx .= "\nPRODUCTS:\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "#{$row['product_id']} {$row['product_name']} - " . ($row['description'] ?? '') . " | Active: " . ($row['is_active'] ? 'Yes' : 'No') . "\n";
    }

    // salespersons with stats
    $r = $conn->query("SELECT sp.salesperson_id, sp.name, sp.email, sp.department, sp.commission_rate, sp.is_active,
        COUNT(s.sl) AS deals, COALESCE(SUM(s.total_amount),0) AS revenue,
        COALESCE(SUM((s.selling_price - s.tax_amount) - s.purchase_price),0) AS profit
        FROM salespersons sp
        LEFT JOIN subscriptions s ON sp.salesperson_id = s.salesperson_id
        GROUP BY sp.salesperson_id ORDER BY sp.name");
    $ctx .= "\nSALESPERSONS:\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "#{$row['salesperson_id']} {$row['name']} | Dept: {$row['department']} | Rate: {$row['commission_rate']}% | Deals: {$row['deals']} | Revenue: " . round($row['revenue'], 3) . " | Profit: " . round($row['profit'], 3) . " | Active: " . ($row['is_active'] ? 'Yes' : 'No') . "\n";
    }

    // users
    $r = $conn->query("SELECT user_id, username, full_name, email, role, department, is_active, last_login, login_count FROM users ORDER BY user_id");
    $ctx .= "\nUSERS:\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "#{$row['user_id']} {$row['username']} ({$row['full_name']}) | {$row['email']} | Role: {$row['role']} | Dept: " . ($row['department'] ?? 'N/A') . " | Active: " . ($row['is_active'] ? 'Yes' : 'No') . " | Last Login: " . ($row['last_login'] ?? 'Never') . " | Logins: {$row['login_count']}\n";
    }

    // payments
    $r = $conn->query("SELECT p.payment_id, p.subscription_sl, p.amount, p.payment_method, p.payment_date, p.reference_no,
        s.invoice_no, s.customer_name
        FROM payments p JOIN subscriptions s ON p.subscription_sl = s.sl
        ORDER BY p.payment_id DESC LIMIT 100");
    $ctx .= "\nPAYMENTS (latest 100):\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "#{$row['payment_id']} SL#{$row['subscription_sl']} ({$row['invoice_no']} - {$row['customer_name']}) | {$currency} {$row['amount']} | Method: " . ($row['payment_method'] ?? 'N/A') . " | Date: {$row['payment_date']} | Ref: " . ($row['reference_no'] ?? 'N/A') . "\n";
    }

    // monthly revenue trend (last 24 months)
    $r = $conn->query("SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month,
        COUNT(*) AS deals, SUM(total_amount) AS revenue,
        SUM((selling_price - tax_amount) - purchase_price) AS profit,
        SUM(CASE WHEN payment_status='Paid' THEN 1 ELSE 0 END) AS paid,
        SUM(CASE WHEN payment_status='Unpaid' THEN 1 ELSE 0 END) AS unpaid
        FROM subscriptions
        WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
        GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY month ASC");
    $ctx .= "\nMONTHLY REVENUE TREND (last 24 months):\n";
    $ctx .= "Month | Deals | Revenue | Profit | Paid | Unpaid\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "{$row['month']} | {$row['deals']} | " . round($row['revenue'] ?? 0, 3) . " | " . round($row['profit'] ?? 0, 3) . " | {$row['paid']} | {$row['unpaid']}\n";
    }

    // upcoming expiries (next 90 days)
    $r = $conn->query("SELECT s.sl, s.customer_name, s.invoice_no, s.expiry_date, s.total_amount,
        s.payment_status, s.auto_renew, s.license_duration, p.product_name,
        DATEDIFF(s.expiry_date, CURDATE()) AS days_left
        FROM subscriptions s
        LEFT JOIN products p ON s.product_id = p.product_id
        WHERE s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        ORDER BY s.expiry_date ASC");
    $ctx .= "\nUPCOMING EXPIRIES (next 90 days):\n";
    $ctx .= "SL | Customer | Invoice | Product | ExpiryDate | DaysLeft | Amount | PayStatus | AutoRenew | Duration\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "{$row['sl']} | {$row['customer_name']} | {$row['invoice_no']} | " . ($row['product_name'] ?? 'N/A') . " | {$row['expiry_date']} | {$row['days_left']}d | {$row['total_amount']} | {$row['payment_status']} | " . ($row['auto_renew'] ? 'Yes' : 'No') . " | " . ($row['license_duration'] ?? 'N/A') . "\n";
    }

    // renewal history — how many expired subs got renewed
    $r = $conn->query("SELECT
        COUNT(*) AS total_expired,
        SUM(CASE WHEN renewal_invoice IS NOT NULL AND renewal_invoice != '' THEN 1 ELSE 0 END) AS renewed,
        SUM(CASE WHEN renewal_invoice IS NULL OR renewal_invoice = '' THEN 1 ELSE 0 END) AS not_renewed
        FROM subscriptions WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()");
    $ren = $r->fetch_assoc();
    $renewal_rate = ($ren['total_expired'] > 0) ? round(($ren['renewed'] / $ren['total_expired']) * 100, 1) : 0;
    $ctx .= "\nRENEWAL HISTORY:\n";
    $ctx .= "Total Expired: {$ren['total_expired']}, Renewed: {$ren['renewed']}, Not Renewed: {$ren['not_renewed']}, Renewal Rate: {$renewal_rate}%\n";

    // revenue by product (for product-level forecasting)
    $r = $conn->query("SELECT COALESCE(p.product_name,'Uncategorized') AS product,
        COUNT(s.sl) AS deals, SUM(s.total_amount) AS revenue,
        SUM((s.selling_price - s.tax_amount) - s.purchase_price) AS profit,
        AVG(s.total_amount) AS avg_deal_size
        FROM subscriptions s LEFT JOIN products p ON s.product_id = p.product_id
        GROUP BY s.product_id ORDER BY revenue DESC");
    $ctx .= "\nREVENUE BY PRODUCT:\n";
    $ctx .= "Product | Deals | Revenue | Profit | Avg Deal Size\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "{$row['product']} | {$row['deals']} | " . round($row['revenue'] ?? 0, 3) . " | " . round($row['profit'] ?? 0, 3) . " | " . round($row['avg_deal_size'] ?? 0, 3) . "\n";
    }

    // customer revenue ranking
    $r = $conn->query("SELECT customer_name, COUNT(*) AS deals, SUM(total_amount) AS revenue,
        SUM((selling_price - tax_amount) - purchase_price) AS profit,
        MIN(invoice_date) AS first_deal, MAX(invoice_date) AS last_deal
        FROM subscriptions GROUP BY customer_name ORDER BY revenue DESC LIMIT 20");
    $ctx .= "\nTOP CUSTOMERS BY REVENUE:\n";
    $ctx .= "Customer | Deals | Revenue | Profit | FirstDeal | LastDeal\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "{$row['customer_name']} | {$row['deals']} | " . round($row['revenue'] ?? 0, 3) . " | " . round($row['profit'] ?? 0, 3) . " | {$row['first_deal']} | {$row['last_deal']}\n";
    }

    // recent activity
    $r = $conn->query("SELECT username, action, details, timestamp FROM activity_logs ORDER BY id DESC LIMIT 30");
    $ctx .= "\nRECENT ACTIVITY (last 30):\n";
    while ($row = $r->fetch_assoc()) {
        $ctx .= "[{$row['timestamp']}] {$row['username']}: {$row['action']} - " . substr($row['details'] ?? '', 0, 120) . "\n";
    }

    // paused/cancelled subscriptions
    $r = $conn->query("SELECT sl, customer_name, invoice_no, subscription_status, cancel_reason, paused_at, cancelled_at FROM subscriptions WHERE subscription_status IN ('paused','cancelled') ORDER BY sl DESC");
    if ($r && $r->num_rows > 0) {
        $ctx .= "\nPAUSED/CANCELLED SUBSCRIPTIONS:\n";
        $ctx .= "SL | Customer | Invoice | Status | Reason | Date\n";
        while ($row = $r->fetch_assoc()) {
            $date = $row['subscription_status'] === 'cancelled' ? ($row['cancelled_at'] ?? '') : ($row['paused_at'] ?? '');
            $ctx .= "{$row['sl']} | {$row['customer_name']} | {$row['invoice_no']} | {$row['subscription_status']} | " . ($row['cancel_reason'] ?? '-') . " | $date\n";
        }
    }

    // refunds
    $r = $conn->query("SELECT r.refund_id, r.amount, r.reason, r.created_at, p.payment_id, s.invoice_no, s.customer_name, u.full_name AS refunded_by
        FROM refunds r JOIN payments p ON r.payment_id = p.payment_id JOIN subscriptions s ON r.subscription_sl = s.sl LEFT JOIN users u ON r.refunded_by = u.user_id
        ORDER BY r.refund_id DESC LIMIT 50");
    if ($r && $r->num_rows > 0) {
        $ctx .= "\nREFUNDS (latest 50):\n";
        $ctx .= "ID | Invoice | Customer | Amount | Reason | RefundedBy | Date\n";
        while ($row = $r->fetch_assoc()) {
            $ctx .= "#{$row['refund_id']} | {$row['invoice_no']} | {$row['customer_name']} | {$currency} {$row['amount']} | " . ($row['reason'] ?? '-') . " | " . ($row['refunded_by'] ?? '-') . " | {$row['created_at']}\n";
        }
    }

    // tax rates
    $r = $conn->query("SELECT name, rate, is_default, is_active FROM tax_rates ORDER BY name");
    if ($r && $r->num_rows > 0) {
        $ctx .= "\nTAX RATES:\n";
        while ($row = $r->fetch_assoc()) {
            $ctx .= "{$row['name']} ({$row['rate']}%)" . ($row['is_default'] ? ' [DEFAULT]' : '') . ($row['is_active'] ? '' : ' [INACTIVE]') . "\n";
        }
    }

    // custom fields definitions + values summary
    $r = $conn->query("SELECT cf.field_id, cf.entity_type, cf.field_label, cf.field_type, COUNT(cfv.value_id) AS usage_count
        FROM custom_fields cf LEFT JOIN custom_field_values cfv ON cf.field_id = cfv.field_id
        WHERE cf.is_active = 1 GROUP BY cf.field_id ORDER BY cf.entity_type, cf.display_order");
    if ($r && $r->num_rows > 0) {
        $ctx .= "\nCUSTOM FIELDS:\n";
        while ($row = $r->fetch_assoc()) {
            $ctx .= "[{$row['entity_type']}] {$row['field_label']} ({$row['field_type']}) — {$row['usage_count']} values stored\n";
        }
    }

    // custom field values for customers
    $r = $conn->query("SELECT c.company_name, cf.field_label, cfv.field_value
        FROM custom_field_values cfv
        JOIN custom_fields cf ON cfv.field_id = cf.field_id
        JOIN customers c ON cfv.entity_id = c.customer_id
        WHERE cfv.entity_type = 'customer' ORDER BY c.company_name, cf.display_order");
    if ($r && $r->num_rows > 0) {
        $ctx .= "\nCUSTOMER CUSTOM FIELD VALUES:\n";
        while ($row = $r->fetch_assoc()) {
            $ctx .= "{$row['company_name']} → {$row['field_label']}: {$row['field_value']}\n";
        }
    }

    // custom field values for subscriptions
    $r = $conn->query("SELECT s.invoice_no, s.customer_name, cf.field_label, cfv.field_value
        FROM custom_field_values cfv
        JOIN custom_fields cf ON cfv.field_id = cf.field_id
        JOIN subscriptions s ON cfv.entity_id = s.sl
        WHERE cfv.entity_type = 'subscription' ORDER BY s.sl, cf.display_order");
    if ($r && $r->num_rows > 0) {
        $ctx .= "\nSUBSCRIPTION CUSTOM FIELD VALUES:\n";
        while ($row = $r->fetch_assoc()) {
            $ctx .= "{$row['invoice_no']} ({$row['customer_name']}) → {$row['field_label']}: {$row['field_value']}\n";
        }
    }

    // document counts per subscription
    $r = $conn->query("SELECT s.invoice_no, s.customer_name, COUNT(d.document_id) AS doc_count, SUM(d.file_size) AS total_size
        FROM documents d JOIN subscriptions s ON d.subscription_sl = s.sl
        GROUP BY d.subscription_sl ORDER BY doc_count DESC");
    if ($r && $r->num_rows > 0) {
        $ctx .= "\nDOCUMENTS PER SUBSCRIPTION:\n";
        while ($row = $r->fetch_assoc()) {
            $size = round(($row['total_size'] ?? 0) / 1024, 1);
            $ctx .= "{$row['invoice_no']} ({$row['customer_name']}): {$row['doc_count']} files ({$size} KB)\n";
        }
    }

    return $ctx;
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
    <title>AI Chat - <?php echo htmlspecialchars($branding['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <style>
    /* chat container */
    .chat-container { display:flex; flex-direction:column; height:calc(100vh - 160px); min-height:400px; background:#e5ddd5; border-radius:8px; overflow:hidden; position:relative; }
    .dark-mode .chat-container { background:#0b141a; }

    /* chat header bar */
    .chat-header { background:#001f3f; color:#fff; padding:12px 20px; display:flex; align-items:center; gap:12px; flex-shrink:0; }
    .chat-header-avatar { width:40px; height:40px; border-radius:50%; background:#0074D9; display:flex; align-items:center; justify-content:center; font-size:18px; }
    .chat-header-info h3 { margin:0; font-size:15px; font-weight:600; }
    .chat-header-info p { margin:0; font-size:11px; opacity:.7; }

    /* messages area */
    .chat-messages { flex:1; overflow-y:auto; padding:16px 20px; display:flex; flex-direction:column; gap:8px; }

    /* message bubbles */
    .chat-msg { max-width:80%; padding:8px 14px; border-radius:8px; font-size:14px; line-height:1.5; position:relative; word-wrap:break-word; }
    .chat-msg.user { align-self:flex-end; background:#dcf8c6; color:#111; border-bottom-right-radius:2px; }
    .dark-mode .chat-msg.user { background:#005c4b; color:#e9edef; }
    .chat-msg.ai { align-self:flex-start; background:#fff; color:#111; border-bottom-left-radius:2px; box-shadow:0 1px 1px rgba(0,0,0,.1); }
    .dark-mode .chat-msg.ai { background:#1f2c34; color:#e9edef; }
    .chat-msg .msg-time { font-size:10px; opacity:.5; margin-top:4px; text-align:right; }
    .chat-msg.ai .msg-content { white-space:pre-wrap; }
    .chat-msg.ai .msg-content table { border-collapse:collapse; margin:8px 0; font-size:13px; width:100%; }
    .chat-msg.ai .msg-content table th, .chat-msg.ai .msg-content table td { border:1px solid #ddd; padding:4px 8px; text-align:left; }
    .chat-msg.ai .msg-content table th { background:#f0f0f0; font-weight:600; }
    .dark-mode .chat-msg.ai .msg-content table th { background:#2a3942; }
    .dark-mode .chat-msg.ai .msg-content table th, .dark-mode .chat-msg.ai .msg-content table td { border-color:#3b4a54; }
    .chat-msg.ai .msg-content code { background:#f0f0f0; padding:1px 4px; border-radius:3px; font-size:13px; }
    .dark-mode .chat-msg.ai .msg-content code { background:#2a3942; }
    .chat-msg.ai .msg-content strong { color:#001f3f; }
    .dark-mode .chat-msg.ai .msg-content strong { color:#53bdeb; }

    /* typing indicator */
    .typing-indicator { align-self:flex-start; background:#fff; padding:10px 16px; border-radius:8px; display:none; }
    .dark-mode .typing-indicator { background:#1f2c34; }
    .typing-dots { display:flex; gap:4px; }
    .typing-dots span { width:8px; height:8px; background:#90949c; border-radius:50%; animation:typingBounce 1.4s infinite; }
    .typing-dots span:nth-child(2) { animation-delay:.2s; }
    .typing-dots span:nth-child(3) { animation-delay:.4s; }
    @keyframes typingBounce { 0%,60%,100%{transform:translateY(0);} 30%{transform:translateY(-6px);} }

    /* input area */
    .chat-input-area { background:#f0f0f0; padding:10px 16px; display:flex; align-items:center; gap:10px; flex-shrink:0; border-top:1px solid #ddd; }
    .dark-mode .chat-input-area { background:#1f2c34; border-top-color:#2a3942; }
    .chat-input-area input { flex:1; padding:10px 16px; border:none; border-radius:24px; font-size:15px; background:#fff; outline:none; }
    .dark-mode .chat-input-area input { background:#2a3942; color:#e9edef; }
    .chat-input-area input::placeholder { color:#999; }
    .chat-send-btn { width:44px; height:44px; border-radius:50%; border:none; background:#0074D9; color:#fff; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .2s; flex-shrink:0; }
    .chat-send-btn:hover { background:#005bb5; }
    .chat-send-btn:disabled { background:#999; cursor:not-allowed; }

    /* welcome message */
    .chat-welcome { text-align:center; padding:40px 20px; }
    .chat-welcome i { font-size:48px; color:#0074D9; opacity:.4; margin-bottom:16px; }
    .dark-mode .chat-welcome h3 { color:#aaa; }
    .chat-welcome h3 { color:#555; font-size:16px; margin:0 0 8px; }
    .chat-welcome p { color:#999; font-size:13px; margin:0; }

    /* suggestion chips */
    .chat-suggestions { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; margin-top:16px; }
    .chip { padding:6px 14px; border-radius:16px; background:#e8f4fd; color:#0074D9; font-size:12px; cursor:pointer; border:1px solid #b8daff; transition:all .2s; }
    .chip:hover { background:#0074D9; color:#fff; }
    .dark-mode .chip { background:#1a3a4a; border-color:#2a4a5a; color:#53bdeb; }
    .dark-mode .chip:hover { background:#0074D9; color:#fff; }

    /* error bubble */
    .chat-msg.error { align-self:center; background:#fef2f2; color:#991b1b; border:1px solid #fecaca; max-width:90%; text-align:center; font-size:13px; }
    .dark-mode .chat-msg.error { background:#3b1c1c; color:#fca5a5; border-color:#5c2a2a; }

    /* clear btn */
    .chat-clear-btn { background:none; border:none; color:#fff; opacity:.6; cursor:pointer; font-size:14px; margin-left:auto; }
    .chat-clear-btn:hover { opacity:1; }

    /* pdf download btn */
    .msg-actions { display:flex; align-items:center; gap:8px; margin-top:8px; padding-top:6px; border-top:1px solid rgba(0,0,0,.06); flex-wrap:wrap; }
    .dark-mode .msg-actions { border-top-color:rgba(255,255,255,.08); }
    .msg-pdf-btn { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:14px; border:1px solid #0074D9; background:transparent; color:#0074D9; font-size:11px; font-weight:600; cursor:pointer; transition:all .2s; }
    .msg-pdf-btn:hover { background:#0074D9; color:#fff; }
    .dark-mode .msg-pdf-btn { border-color:#53bdeb; color:#53bdeb; }
    .dark-mode .msg-pdf-btn:hover { background:#53bdeb; color:#1f2c34; }
    .msg-copy-btn { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:14px; border:1px solid #999; background:transparent; color:#666; font-size:11px; font-weight:600; cursor:pointer; transition:all .2s; }
    .msg-copy-btn:hover { background:#666; color:#fff; }
    .dark-mode .msg-copy-btn { border-color:#777; color:#aaa; }
    .dark-mode .msg-copy-btn:hover { background:#777; color:#fff; }

    /* follow-up suggestions after AI reply */
    .reply-suggestions { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .reply-chip { padding:4px 12px; border-radius:14px; background:#e8f4fd; color:#0074D9; font-size:11px; cursor:pointer; border:1px solid #b8daff; transition:all .2s; white-space:nowrap; }
    .reply-chip:hover { background:#0074D9; color:#fff; }
    .dark-mode .reply-chip { background:#1a3a4a; border-color:#2a4a5a; color:#53bdeb; }
    .dark-mode .reply-chip:hover { background:#0074D9; color:#fff; }
    </style>
</head>
<body>
    <?php include 'mobile-menu.php'; ?>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-content" style="padding-bottom:0;">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span>AI Chat</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-robot"></i> AI Chat Assistant</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="chat-container">
                <!-- Header -->
                <div class="chat-header">
                    <div class="chat-header-avatar"><i class="fas fa-robot"></i></div>
                    <div class="chat-header-info">
                        <h3>AI Assistant</h3>
                        <p>Knows your subscriptions, payments, customers & more</p>
                    </div>
                    <button class="chat-clear-btn" onclick="clearChat()" title="Clear Chat">
                        <i class="fas fa-trash-alt"></i> Clear
                    </button>
                </div>

                <!-- Messages -->
                <div class="chat-messages" id="chatMessages">
                    <div class="chat-welcome" id="welcomeMsg">
                        <i class="fas fa-robot"></i>
                        <h3>Hi! I'm your AI Assistant</h3>
                        <p>Ask me anything about your subscriptions, payments, customers, revenue, or any data in your system.</p>
                        <div class="chat-suggestions">
                            <span class="chip" onclick="askSuggestion(this)">Predict next month's revenue</span>
                            <span class="chip" onclick="askSuggestion(this)">What subscriptions expire next 30 days?</span>
                            <span class="chip" onclick="askSuggestion(this)">Forecast next quarter revenue & profit</span>
                            <span class="chip" onclick="askSuggestion(this)">Which customers are at risk of churning?</span>
                            <span class="chip" onclick="askSuggestion(this)">Show total unpaid amount</span>
                            <span class="chip" onclick="askSuggestion(this)">Which salesperson has the highest revenue?</span>
                            <span class="chip" onclick="askSuggestion(this)">Predict yearly revenue based on trends</span>
                            <span class="chip" onclick="askSuggestion(this)">Revenue vs profit summary</span>
                        </div>
                    </div>

                    <!-- Typing indicator -->
                    <div class="typing-indicator" id="typingIndicator">
                        <div class="typing-dots">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="chat-input-area">
                    <input type="text" id="chatInput" placeholder="Type a message..." autocomplete="off"
                           onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendMessage();}">
                    <button class="chat-send-btn" id="sendBtn" onclick="sendMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script>
    var chatHistory = [];

    function sendMessage() {
        var input = document.getElementById('chatInput');
        var question = input.value.trim();
        if (!question) return;

        // hide welcome
        var welcome = document.getElementById('welcomeMsg');
        if (welcome) welcome.style.display = 'none';

        // add user bubble
        addBubble(question, 'user');
        input.value = '';

        // capture history BEFORE pushing current msg (backend adds it separately)
        var historyToSend = JSON.stringify(chatHistory.slice(-20));
        chatHistory.push({ role: 'user', text: question });

        // show typing
        var typing = document.getElementById('typingIndicator');
        typing.style.display = 'block';
        scrollToBottom();

        // disable send
        document.getElementById('sendBtn').disabled = true;

        // send to backend
        $.ajax({
            url: '?',
            method: 'POST',
            data: {
                action: 'askAI',
                question: question,
                history: historyToSend
            },
            dataType: 'json',
            timeout: 35000,
            success: function(r) {
                typing.style.display = 'none';
                document.getElementById('sendBtn').disabled = false;

                if (r.success) {
                    addBubble(r.answer, 'ai');
                    chatHistory.push({ role: 'model', text: r.answer });
                } else {
                    addBubble(r.message || 'Something went wrong', 'error');
                }
                scrollToBottom();
            },
            error: function(xhr, status, err) {
                typing.style.display = 'none';
                document.getElementById('sendBtn').disabled = false;
                addBubble('Connection error. Please try again.', 'error');
                scrollToBottom();
            }
        });
    }

    var msgCounter = 0;

    function addBubble(text, type) {
        var messages = document.getElementById('chatMessages');
        var typing = document.getElementById('typingIndicator');
        var div = document.createElement('div');
        div.className = 'chat-msg ' + type;

        var now = new Date();
        var time = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');

        if (type === 'ai') {
            msgCounter++;
            var msgId = 'ai-msg-' + msgCounter;
            div.setAttribute('data-raw', text);
            div.id = msgId;

            var html = '<div class="msg-content">' + formatAiText(text) + '</div>';
            // action buttons
            html += '<div class="msg-actions">';
            html += '<button class="msg-pdf-btn" onclick="downloadPdf(\'' + msgId + '\')"><i class="fas fa-file-pdf"></i> Download PDF</button>';
            html += '<button class="msg-copy-btn" onclick="copyMsg(\'' + msgId + '\')"><i class="fas fa-copy"></i> Copy</button>';
            html += '</div>';
            // follow-up suggestions
            var suggestions = getFollowUpSuggestions(text);
            if (suggestions.length > 0) {
                html += '<div class="reply-suggestions">';
                suggestions.forEach(function(s) {
                    html += '<span class="reply-chip" onclick="askSuggestion(this)">' + escHtml(s) + '</span>';
                });
                html += '</div>';
            }
            html += '<div class="msg-time">' + time + '</div>';
            div.innerHTML = html;
        } else if (type === 'error') {
            div.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + escHtml(text);
        } else {
            div.innerHTML = escHtml(text) + '<div class="msg-time">' + time + '</div>';
        }

        messages.insertBefore(div, typing);
    }

    // basic markdown-like formatting
    function formatAiText(text) {
        var s = escHtml(text);
        // bold **text**
        s = s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // inline code `code`
        s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
        // headers
        s = s.replace(/^### (.+)$/gm, '<strong style="font-size:15px;">$1</strong>');
        s = s.replace(/^## (.+)$/gm, '<strong style="font-size:16px;">$1</strong>');
        // bullet lists
        s = s.replace(/^\* (.+)$/gm, '&bull; $1');
        s = s.replace(/^- (.+)$/gm, '&bull; $1');
        // markdown tables
        s = formatTables(s);
        return s;
    }

    function formatTables(text) {
        var lines = text.split('\n');
        var result = [];
        var tableLines = [];
        var inTable = false;

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (line.match(/^\|.*\|$/)) {
                // skip separator rows like |---|---|
                if (line.match(/^\|[\s\-:|]+\|$/)) { inTable = true; continue; }
                tableLines.push(line);
                inTable = true;
            } else {
                if (inTable && tableLines.length > 0) {
                    result.push(buildHtmlTable(tableLines));
                    tableLines = [];
                    inTable = false;
                }
                result.push(line);
            }
        }
        if (tableLines.length > 0) result.push(buildHtmlTable(tableLines));
        return result.join('\n');
    }

    function buildHtmlTable(lines) {
        var html = '<table>';
        lines.forEach(function(line, idx) {
            var cells = line.split('|').filter(function(c) { return c.trim() !== ''; });
            var tag = idx === 0 ? 'th' : 'td';
            html += '<tr>';
            cells.forEach(function(c) { html += '<' + tag + '>' + c.trim() + '</' + tag + '>'; });
            html += '</tr>';
        });
        html += '</table>';
        return html;
    }

    function askSuggestion(el) {
        document.getElementById('chatInput').value = el.textContent;
        sendMessage();
    }

    function clearChat() {
        chatHistory = [];
        var messages = document.getElementById('chatMessages');
        var typing = document.getElementById('typingIndicator');
        var welcome = document.getElementById('welcomeMsg');
        // remove all bubbles
        var bubbles = messages.querySelectorAll('.chat-msg');
        bubbles.forEach(function(b) { b.remove(); });
        // show welcome
        if (welcome) welcome.style.display = 'block';
    }

    function scrollToBottom() {
        var el = document.getElementById('chatMessages');
        setTimeout(function() { el.scrollTop = el.scrollHeight; }, 50);
    }

    // PDF download
    function downloadPdf(msgId) {
        var el = document.getElementById(msgId);
        if (!el) return;
        var rawText = el.getAttribute('data-raw') || '';

        // find the user question before this AI reply
        var question = '';
        var prev = el.previousElementSibling;
        while (prev) {
            if (prev.classList.contains('chat-msg') && prev.classList.contains('user')) {
                question = prev.textContent.replace(/\d{2}:\d{2}$/, '').trim();
                break;
            }
            prev = prev.previousElementSibling;
        }

        var now = new Date();
        var dateStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
        var timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');

        // parse content into pdfmake content array
        var content = [];
        // header
        content.push({ text: 'AI Analysis Report', style: 'header' });
        content.push({ text: 'Generated: ' + dateStr + ' at ' + timeStr, style: 'subheader' });
        if (question) {
            content.push({ text: '\n' });
            content.push({ text: 'Question:', style: 'label' });
            content.push({ text: question, style: 'question' });
        }
        content.push({ text: '\n' });
        content.push({ canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 1, lineColor: '#0074D9' }] });
        content.push({ text: '\n' });

        // parse AI response into structured content
        var lines = rawText.split('\n');
        var tableBuffer = [];
        var inTable = false;

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];

            // markdown table row
            if (line.trim().match(/^\|.*\|$/)) {
                if (line.trim().match(/^\|[\s\-:|]+\|$/)) { inTable = true; continue; }
                tableBuffer.push(line);
                inTable = true;
                continue;
            }

            // flush table
            if (inTable && tableBuffer.length > 0) {
                content.push(buildPdfTable(tableBuffer));
                content.push({ text: '\n' });
                tableBuffer = [];
                inTable = false;
            }

            // headings
            if (line.match(/^## /)) {
                content.push({ text: line.replace(/^##\s*/, ''), style: 'h2', margin: [0, 8, 0, 4] });
            } else if (line.match(/^### /)) {
                content.push({ text: line.replace(/^###\s*/, ''), style: 'h3', margin: [0, 6, 0, 3] });
            } else if (line.match(/^\*\*Analysis:\*\*/)) {
                content.push({ text: line.replace(/\*\*/g, ''), style: 'analysis', margin: [0, 0, 0, 6] });
            } else if (line.match(/^[\*\-] /)) {
                content.push({ text: '  \u2022  ' + line.replace(/^[\*\-]\s*/, '').replace(/\*\*/g, ''), margin: [10, 1, 0, 1], fontSize: 10 });
            } else if (line.trim() === '') {
                content.push({ text: '\n' });
            } else {
                content.push({ text: line.replace(/\*\*/g, ''), fontSize: 10, margin: [0, 1, 0, 1] });
            }
        }
        if (tableBuffer.length > 0) { content.push(buildPdfTable(tableBuffer)); }

        // footer
        content.push({ text: '\n' });
        content.push({ canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 0.5, lineColor: '#ccc' }] });
        content.push({ text: '<?php echo htmlspecialchars($branding['site_name']); ?> — AI Assistant Report', style: 'footer' });

        var docDef = {
            content: content,
            styles: {
                header: { fontSize: 18, bold: true, color: '#001f3f', margin: [0, 0, 0, 4] },
                subheader: { fontSize: 10, color: '#7a8fa6', margin: [0, 0, 0, 8] },
                label: { fontSize: 9, color: '#7a8fa6', bold: true, margin: [0, 0, 0, 2] },
                question: { fontSize: 11, color: '#001f3f', italics: true, margin: [0, 0, 0, 4] },
                h2: { fontSize: 14, bold: true, color: '#001f3f' },
                h3: { fontSize: 12, bold: true, color: '#003366' },
                analysis: { fontSize: 10, italics: true, color: '#555', background: '#f8f9fa' },
                tableHeader: { fontSize: 9, bold: true, color: '#fff', fillColor: '#001f3f' },
                tableCell: { fontSize: 9 },
                footer: { fontSize: 8, color: '#aaa', alignment: 'center', margin: [0, 8, 0, 0] }
            },
            defaultStyle: { fontSize: 10, color: '#333' },
            pageMargins: [40, 40, 40, 40]
        };

        pdfMake.createPdf(docDef).download('AI_Report_' + dateStr + '_' + timeStr.replace(':', '') + '.pdf');
    }

    function buildPdfTable(lines) {
        var widths = [];
        var body = [];
        lines.forEach(function(line, idx) {
            var cells = line.split('|').filter(function(c) { return c.trim() !== ''; });
            if (idx === 0) { widths = cells.map(function() { return '*'; }); }
            var row = cells.map(function(c) {
                return { text: c.trim().replace(/\*\*/g, ''), style: idx === 0 ? 'tableHeader' : 'tableCell' };
            });
            body.push(row);
        });
        // pad rows to same column count
        var maxCols = Math.max.apply(null, body.map(function(r) { return r.length; }));
        body.forEach(function(row) { while (row.length < maxCols) row.push({ text: '' }); });
        widths = body[0].map(function() { return '*'; });

        return {
            table: { headerRows: 1, widths: widths, body: body },
            layout: {
                hLineWidth: function() { return 0.5; },
                vLineWidth: function() { return 0.5; },
                hLineColor: function() { return '#ddd'; },
                vLineColor: function() { return '#ddd'; },
                fillColor: function(i) { return i === 0 ? '#001f3f' : (i % 2 === 0 ? '#f8f9fa' : null); }
            },
            margin: [0, 4, 0, 4]
        };
    }

    // copy to clipboard
    function copyMsg(msgId) {
        var el = document.getElementById(msgId);
        if (!el) return;
        var raw = el.getAttribute('data-raw') || '';
        navigator.clipboard.writeText(raw).then(function() {
            var btn = el.querySelector('.msg-copy-btn');
            if (btn) { btn.innerHTML = '<i class="fas fa-check"></i> Copied!'; setTimeout(function() { btn.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 2000); }
        });
    }

    // smart follow-up suggestions based on AI reply content
    function getFollowUpSuggestions(text) {
        var t = text.toLowerCase();
        var suggestions = [];

        // revenue/profit related
        if (t.indexOf('revenue') !== -1 || t.indexOf('profit') !== -1) {
            suggestions.push('Break this down by product');
            suggestions.push('Compare with last quarter');
            suggestions.push('Predict next month revenue');
        }
        // expiry related
        if (t.indexOf('expir') !== -1 || t.indexOf('renewal') !== -1) {
            suggestions.push('Which ones have auto-renew?');
            suggestions.push('Show unpaid expiring subs');
            suggestions.push('Predict renewal rate');
        }
        // customer related
        if (t.indexOf('customer') !== -1 || t.indexOf('client') !== -1) {
            suggestions.push('Show top 5 customers by revenue');
            suggestions.push('Which customers are at risk?');
            suggestions.push('Customer payment history');
        }
        // payment related
        if (t.indexOf('payment') !== -1 || t.indexOf('unpaid') !== -1 || t.indexOf('paid') !== -1) {
            suggestions.push('Show overdue payments');
            suggestions.push('Payment collection forecast');
            suggestions.push('Break down by payment method');
        }
        // salesperson related
        if (t.indexOf('salesperson') !== -1 || t.indexOf('commission') !== -1 || t.indexOf('sales') !== -1) {
            suggestions.push('Commission payout forecast');
            suggestions.push('Compare salesperson performance');
            suggestions.push('Who closed the most deals?');
        }
        // prediction related
        if (t.indexOf('predict') !== -1 || t.indexOf('forecast') !== -1 || t.indexOf('trend') !== -1) {
            suggestions.push('Show optimistic vs pessimistic scenario');
            suggestions.push('What are the biggest risks?');
            suggestions.push('Yearly projection');
        }
        // supplier related
        if (t.indexOf('supplier') !== -1 || t.indexOf('vendor') !== -1) {
            suggestions.push('Supplier cost breakdown');
            suggestions.push('Which supplier has most deals?');
        }

        // if nothing matched, show generic
        if (suggestions.length === 0) {
            suggestions.push('Tell me more');
            suggestions.push('Show this as a table');
            suggestions.push('What should I focus on?');
        }

        // max 4 suggestions
        return suggestions.slice(0, 4);
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // focus input on load
    document.getElementById('chatInput').focus();
    </script>
</body>
</html>
