<?php
/**
 * Subscription Verification REST API
 * 
 * Used by external Chrome Extensions / Desktop Apps to verify license keys.
 * 
 * Endpoint: http://localhost/SubscriptionManagementSystem/api.php?action=verify&license_key=SMS-XXXX-XXXX-XXXX-XXXX
 */

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allows extensions from any origin to make calls
header('Access-Control-Allow-Methods: GET, POST');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== 'verify') {
    echo json_encode([
        'valid'   => false,
        'message' => 'Invalid API action. Use action=verify'
    ]);
    exit();
}

$license_key  = trim($_GET['license_key'] ?? $_POST['license_key'] ?? '');
$product_code = trim($_GET['product_code'] ?? $_POST['product_code'] ?? '');
$product_id   = intval($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

if (empty($license_key)) {
    echo json_encode([
        'valid'   => false,
        'message' => 'License key is required'
    ]);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Query to find the subscription with this product key
    $query = "SELECT s.sl, s.customer_name, s.invoice_no, s.expiry_date, s.payment_status, s.subscription_status,
                     p.product_name, p.product_code, p.product_id
              FROM subscriptions s
              LEFT JOIN products p ON s.product_id = p.product_id
              WHERE s.product_key = ?";
              
    if (!empty($product_code)) {
        $query .= " AND p.product_code = ?";
    } elseif ($product_id > 0) {
        $query .= " AND p.product_id = ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if (!empty($product_code)) {
        $stmt->bind_param("ss", $license_key, $product_code);
    } elseif ($product_id > 0) {
        $stmt->bind_param("si", $license_key, $product_id);
    } else {
        $stmt->bind_param("s", $license_key);
    }
    
    $stmt->execute();
    $sub = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sub) {
        echo json_encode([
            'valid'   => false,
            'message' => 'Invalid license key'
        ]);
        exit();
    }

    // ── Check Expiry ──
    $is_expired = false;
    if (!empty($sub['expiry_date'])) {
        $expiry = new DateTime($sub['expiry_date']);
        $now    = new DateTime();
        if ($now > $expiry) {
            $is_expired = true;
        }
    }

    // ── Validation Rules ──
    if ($sub['payment_status'] !== 'Paid') {
        echo json_encode([
            'valid'   => false,
            'status'  => 'unpaid',
            'message' => 'Subscription is unpaid'
        ]);
        exit();
    }

    if ($sub['subscription_status'] !== 'active') {
        echo json_encode([
            'valid'   => false,
            'status'  => $sub['subscription_status'],
            'message' => 'Subscription is ' . $sub['subscription_status']
        ]);
        exit();
    }

    if ($is_expired) {
        echo json_encode([
            'valid'   => false,
            'status'  => 'expired',
            'expiry_date' => $sub['expiry_date'],
            'message' => 'License key has expired on ' . $sub['expiry_date']
        ]);
        exit();
    }

    // ── License is Valid ──
    echo json_encode([
        'valid'             => true,
        'status'            => 'active',
        'product'           => $sub['product_name'] ?? 'N/A',
        'customer'          => $sub['customer_name'],
        'invoice_no'        => $sub['invoice_no'],
        'expiry_date'       => $sub['expiry_date'] ?? 'Lifetime',
        'message'           => 'License key is valid and active'
    ]);
    exit();

} catch (Exception $e) {
    error_log("API Verification Error: " . $e->getMessage());
    echo json_encode([
        'valid'   => false,
        'message' => 'Internal server error occurred'
    ]);
    exit();
}
?>
