<?php
/**
 * Razorpay Create Order
 * Called via AJAX from customer_portal.php
 * Creates a Razorpay order and returns order_id to frontend
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

// Only customers can use this
$role        = $_SESSION['role'];
$user_id     = $_SESSION['user_id'];
$customer_id = $_SESSION['customer_id'] ?? null;

if ($role !== 'customer' || !$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$sl    = isset($input['subscription_sl']) ? intval($input['subscription_sl']) : 0;

if ($sl <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid subscription']);
    exit();
}

// Get Razorpay settings
$rzp = getRazorpaySettings();
if (!$rzp['enabled']) {
    echo json_encode(['success' => false, 'message' => 'Razorpay payment gateway is not enabled. Please contact admin.']);
    exit();
}
if (empty($rzp['key_id']) || empty($rzp['key_secret'])) {
    echo json_encode(['success' => false, 'message' => 'Payment gateway not configured. Please contact admin.']);
    exit();
}

// Verify subscription belongs to this customer and is unpaid/partial
$conn = getDBConnection();
$stmt = $conn->prepare(
    "SELECT s.sl, s.invoice_no, s.customer_name, s.total_amount, s.payment_status,
            COALESCE(SUM(p.amount), 0) AS paid_so_far
     FROM subscriptions s
     LEFT JOIN payments p ON p.subscription_sl = s.sl
     WHERE s.sl = ? AND s.customer_id = ?
     GROUP BY s.sl"
);
$stmt->bind_param("ii", $sl, $customer_id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) {
    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
    exit();
}

if ($sub['payment_status'] === 'Paid') {
    echo json_encode(['success' => false, 'message' => 'This subscription is already paid']);
    exit();
}

// Calculate remaining amount
$remaining = round((float)$sub['total_amount'] - (float)$sub['paid_so_far'], 2);
if ($remaining <= 0) {
    echo json_encode(['success' => false, 'message' => 'No amount due for this subscription']);
    exit();
}

// Razorpay amount is in paise (1 INR = 100 paise)
$amount_paise = (int)round($remaining * 100);

// Create Razorpay Order via cURL (no SDK needed)
$order_data = [
    'amount'          => $amount_paise,
    'currency'        => 'INR',
    'receipt'         => 'rcpt_' . $sl . '_' . time(),
    'notes'           => [
        'subscription_sl' => (string)$sl,
        'invoice_no'      => $sub['invoice_no'] ?? '',
        'customer_name'   => $sub['customer_name'],
    ]
];

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($order_data),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_USERPWD        => $rzp['key_id'] . ':' . $rzp['key_secret'],
    CURLOPT_TIMEOUT        => 30,
]);

$response    = curl_exec($ch);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log("Razorpay cURL error: " . $curl_error);
    echo json_encode(['success' => false, 'message' => 'Could not connect to payment gateway. Please try again.']);
    exit();
}

$order = json_decode($response, true);

if ($http_code !== 200 || empty($order['id'])) {
    $err = $order['error']['description'] ?? 'Unknown error';
    error_log("Razorpay order creation failed (HTTP $http_code): " . $response);
    echo json_encode(['success' => false, 'message' => 'Payment gateway error: ' . $err]);
    exit();
}

// Return order details to frontend
echo json_encode([
    'success'         => true,
    'order_id'        => $order['id'],
    'amount'          => $amount_paise,
    'amount_display'  => '₹' . number_format($remaining, 2),
    'currency'        => 'INR',
    'key_id'          => $rzp['key_id'],        // public key — safe to send
    'subscription_sl' => $sl,
    'invoice_no'      => $sub['invoice_no'] ?? 'N/A',
    'customer_name'   => $sub['customer_name'],
    'description'     => 'Payment for ' . ($sub['invoice_no'] ?? 'Invoice') . ' - ' . $sub['customer_name'],
]);
exit();
?>
