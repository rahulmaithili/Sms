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
    "SELECT sl, invoice_no, customer_name, total_amount, payment_status,
            COALESCE((SELECT SUM(amount) FROM payments WHERE subscription_sl = s.sl), 0) AS paid_so_far
     FROM subscriptions s
     WHERE sl = ? AND customer_id = ?"
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
$ins->bind_param("idssssI", $subscription_sl, $amount_paid, $payment_method, $payment_date, $razorpay_payment_id, $notes, $user_id);

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

$upd = $conn->prepare("UPDATE subscriptions SET payment_status = ?, payment_date = ? WHERE sl = ?");
$upd->bind_param("ssi", $new_status, $payment_date, $subscription_sl);
$upd->execute();
$upd->close();

// ── Step 6: Log activity ─────────────────────────────────────────────────────
logActivity(
    $user_id,
    $username,
    'Razorpay Payment',
    "Paid ₹{$amount_paid} via Razorpay for subscription SL {$subscription_sl} ({$sub['invoice_no']} - {$sub['customer_name']}). "
    . "Razorpay Payment ID: {$razorpay_payment_id}. Status => {$new_status}"
);

echo json_encode([
    'success'         => true,
    'message'         => 'Payment successful! Your subscription status is now: ' . $new_status,
    'new_status'      => $new_status,
    'payment_id'      => $razorpay_payment_id,
    'amount_paid'     => '₹' . number_format($amount_paid, 2),
]);
exit();
?>
