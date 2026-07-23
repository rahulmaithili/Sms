<?php
/**
 * Razorpay Payment Verification
 * Called via AJAX after Razorpay checkout completes on frontend
 * Verifies payment signature, inserts payment record, updates subscription status
 */

require_once 'config.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}
if (!checkSessionTimeout()) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit();
}

$role        = $_SESSION['role'];
$user_id     = $_SESSION['user_id'];
$username    = $_SESSION['username'];
$customer_id = $_SESSION['customer_id'] ?? null;

if ($role !== 'customer' || !$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$razorpay_order_id   = trim($input['razorpay_order_id']   ?? '');
$razorpay_payment_id = trim($input['razorpay_payment_id'] ?? '');
$razorpay_signature  = trim($input['razorpay_signature']  ?? '');
$subscription_sl     = intval($input['subscription_sl']   ?? 0);
$amount_paid         = floatval($input['amount']          ?? 0) / 100; // convert paise to INR

if (empty($razorpay_order_id) || empty($razorpay_payment_id) || empty($razorpay_signature) || $subscription_sl <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing payment verification data']);
    exit();
}

// ── Step 1: Verify Signature ─────────────────────────────────────────────────
$rzp = getRazorpaySettings();
if (!$rzp['enabled'] || empty($rzp['key_secret'])) {
    echo json_encode(['success' => false, 'message' => 'Payment gateway not configured']);
    exit();
}

// Razorpay signature = HMAC-SHA256(order_id + "|" + payment_id, key_secret)
$generated_signature = hash_hmac(
    'sha256',
    $razorpay_order_id . '|' . $razorpay_payment_id,
    $rzp['key_secret']
);

if (!hash_equals($generated_signature, $razorpay_signature)) {
    error_log("Razorpay signature mismatch! Order: $razorpay_order_id Payment: $razorpay_payment_id");
    echo json_encode(['success' => false, 'message' => 'Payment verification failed. Signature mismatch.']);
    exit();
}

// ── Step 2: Check subscription belongs to this customer ──────────────────────
$conn = getDBConnection();
$stmt = $conn->prepare(
    "SELECT s.sl, s.invoice_no, s.customer_name, s.total_amount, s.payment_status, s.product_key,
            p.product_name, c.email AS customer_email,
            COALESCE((SELECT SUM(amount) FROM payments WHERE subscription_sl = s.sl), 0) AS paid_so_far
     FROM subscriptions s
     LEFT JOIN products p ON s.product_id = p.product_id
     LEFT JOIN customers c ON s.customer_id = c.customer_id
     WHERE s.sl = ? AND s.customer_id = ?"
);
$stmt->bind_param("ii", $subscription_sl, $customer_id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) {
    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
    exit();
}

// ── Step 3: Check for duplicate payment (idempotency) ────────────────────────
$dup = $conn->prepare("SELECT payment_id FROM payments WHERE reference_no = ?");
$dup->bind_param("s", $razorpay_payment_id);
$dup->execute();
$already_exists = $dup->get_result()->fetch_assoc();
$dup->close();

if ($already_exists) {
    // Already recorded — return success (idempotent)
    echo json_encode(['success' => true, 'message' => 'Payment already recorded', 'already_paid' => true]);
    exit();
}

// ── Step 4: Insert payment record ────────────────────────────────────────────
$payment_date  = date('Y-m-d');
$payment_method = 'Razorpay';
$notes          = 'Razorpay Order: ' . $razorpay_order_id;

$ins = $conn->prepare(
    "INSERT INTO payments (subscription_sl, amount, payment_method, payment_date, reference_no, notes, added_by)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$ins->bind_param("idssssi", $subscription_sl, $amount_paid, $payment_method, $payment_date, $razorpay_payment_id, $notes, $user_id);

if (!$ins->execute()) {
    $ins->close();
    error_log("Razorpay: Failed to insert payment record. SL=$subscription_sl, PayID=$razorpay_payment_id");
    echo json_encode(['success' => false, 'message' => 'Payment recorded on Razorpay but failed to save in system. Please contact admin with Payment ID: ' . $razorpay_payment_id]);
    exit();
}
$ins->close();

// ── Step 5: Recalculate subscription payment_status ──────────────────────────
$sum_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE subscription_sl = ?");
$sum_stmt->bind_param("i", $subscription_sl);
$sum_stmt->execute();
$sum_total = (float)$sum_stmt->get_result()->fetch_assoc()['total'];
$sum_stmt->close();

$sub_total = (float)$sub['total_amount'];

if ($sum_total >= $sub_total) {
    $new_status = 'Paid';
} elseif ($sum_total > 0) {
    $new_status = 'Partial';
} else {
    $new_status = 'Unpaid';
}

// ── Step 6: Generate License Key for Digital Product (Auto Delivery) ─────────
$license_key = $sub['product_key'];
$auto_delivered = false;

if ($new_status === 'Paid' && empty($license_key)) {
    // Generate secure license key (e.g. SMS-XXXX-XXXX-XXXX-XXXX)
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $key = "";
    for ($i = 0; $i < 4; $i++) {
        $segment = "";
        for ($j = 0; $j < 4; $j++) {
            $segment .= $chars[rand(0, strlen($chars) - 1)];
        }
        $key .= $segment . ($i < 3 ? '-' : '');
    }
    $license_key = 'SMS-' . $key;

    $upd = $conn->prepare("UPDATE subscriptions SET payment_status = ?, payment_date = ?, product_key = ? WHERE sl = ?");
    $upd->bind_param("sssi", $new_status, $payment_date, $license_key, $subscription_sl);
    $upd->execute();
    $upd->close();
    $auto_delivered = true;
} else {
    $upd = $conn->prepare("UPDATE subscriptions SET payment_status = ?, payment_date = ? WHERE sl = ?");
    $upd->bind_param("ssi", $new_status, $payment_date, $subscription_sl);
    $upd->execute();
    $upd->close();
}

// ── Step 7: Send Auto Delivery Email if enabled ──────────────────────────────
$email_sent = false;
if ($auto_delivered && !empty($sub['customer_email'])) {
    $logoUrl = getSetting('company_logo_url', '') ?: getSetting('site_logo', 'https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEiGXxCe0WNNedmFqSWeF761f7Kshhc-NP5ChRQKz9fr97cO8VaarvD0KlCwqHojJVBWv-RAxfOqMI5rD4H78KnARyOc6QgwL1nRRFWf5xNQ1d9F9HfAoLPPGlTyP0GwNl4n-INMEsWLQ4Y7zJtz5bOdAnc2ePH9-uCRgshlo6BsS6gJEz6fhrxL-5U5O3sX/s160/channels4_profile.jpg');
    $siteName = getSetting('company_name', 'Subscription System');

    $emailBody = '<!DOCTYPE html>
    <html>
    <body style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e9ecef;">
            <div style="background: #001f3f; padding: 20px; text-align: center; color: #fff;">
                <img src="'.htmlspecialchars($logoUrl).'" alt="Logo" style="width: 50px; border-radius: 50%; display: block; margin: 0 auto 10px;">
                <h2>'.htmlspecialchars($siteName).'</h2>
            </div>
            <div style="padding: 30px;">
                <p>Dear <strong>'.htmlspecialchars($sub['customer_name']).'</strong>,</p>
                <p>Thank you for your payment of <strong>₹'.number_format($amount_paid, 2).'</strong> for Invoice <strong>'.htmlspecialchars($sub['invoice_no']).'</strong>.</p>
                <p>Your subscription is now fully active. Below is your digital product license key:</p>
                <div style="background: #f8f9fa; border: 1px dashed #0074D9; padding: 15px; border-radius: 6px; font-size: 18px; font-family: monospace; font-weight: bold; text-align: center; letter-spacing: 1px; color: #0074D9; margin: 20px 0;">
                    '.$license_key.'
                </div>
                <p style="font-size: 13px; color: #666;">Please keep this key safe. You can also view it anytime by logging into your Customer Portal.</p>
            </div>
            <div style="background: #f4f6f9; padding: 15px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e9ecef;">
                &copy; '.date('Y').' '.htmlspecialchars($siteName).'. All rights reserved.
            </div>
        </div>
    </body>
    </html>';

    $res = sendEmail($sub['customer_email'], 'Your Digital Product Key: ' . ($sub['product_name'] ?? 'License Key'), $emailBody);
    if (isset($res['success']) && $res['success']) {
        $email_sent = true;
    }
}

// ── Step 8: Log activity ─────────────────────────────────────────────────────
logActivity(
    $user_id,
    $username,
    'Razorpay Payment',
    "Paid ₹{$amount_paid} via Razorpay for subscription SL {$subscription_sl} ({$sub['invoice_no']} - {$sub['customer_name']}). "
    . "Razorpay Payment ID: {$razorpay_payment_id}. Status => {$new_status}" . ($auto_delivered ? " (License key generated and auto-delivered)" : "")
);

echo json_encode([
    'success'         => true,
    'message'         => 'Payment successful!' . ($auto_delivered ? ' Your digital key has been delivered.' : ''),
    'new_status'      => $new_status,
    'payment_id'      => $razorpay_payment_id,
    'amount_paid'     => '₹' . number_format($amount_paid, 2),
    'license_key'     => $license_key,
    'auto_delivered'  => $auto_delivered,
    'email_sent'      => $email_sent
]);
exit();
?>
