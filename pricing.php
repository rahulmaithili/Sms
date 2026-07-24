<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!checkSessionTimeout()) { header("Location: login.php"); exit(); }

if ($_SESSION['role'] !== 'customer') { header("Location: dashboard.php"); exit(); }

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'pricing';

// get products
// get products
if (isset($_GET['action']) && $_GET['action'] === 'getProducts') {
    header('Content-Type: application/json');
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT product_id, product_name, description, color_code, selling_price FROM products WHERE is_active = 1 ORDER BY display_order ASC, product_name ASC");
    $stmt->execute();
    $res = $stmt->get_result();
    $products = [];
    while ($r = $res->fetch_assoc()) {
        $products[] = [
            'product_id' => (int)$r['product_id'],
            'product_name' => $r['product_name'],
            'description' => $r['description'] ?? '',
            'color_code' => $r['color_code'] ?? '#0078D4',
            'selling_price' => (float)$r['selling_price']
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'data' => $products, 'currency' => getCurrency()]);
    exit();
}

// create subscription
if (isset($_POST['action']) && $_POST['action'] === 'createSubscription') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
        echo json_encode(['success' => false, 'message' => 'No linked customer profile. Please contact support.']);
        exit();
    }
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product selected.']);
        exit();
    }
    
    try {
        $conn = getDBConnection();
        // Fetch product details
        $stmt = $conn->prepare("SELECT product_name, description, selling_price, purchase_price FROM products WHERE product_id = ? AND is_active = 1");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$prod) {
            echo json_encode(['success' => false, 'message' => 'Product not found or inactive.']);
            exit();
        }
        
        $customer_id    = $_SESSION['customer_id'];
        $customer_name  = $_SESSION['full_name'];
        $invoice_no     = generateInvoiceNo('INV');
        $invoice_date   = date('Y-m-d');
        $starting_date  = date('Y-m-d');
        
        // Default monthly subscription
        $expiry_date    = date('Y-m-d', strtotime('+30 days')); 
        $license_duration = 'Monthly';
        
        $product_name        = $prod['product_name'];
        $product_description = $prod['description'] ?? 'Premium subscription package';
        $selling_price       = floatval($prod['selling_price']);
        $purchase_price      = floatval($prod['purchase_price'] ?? 0);
        $tax_amount          = 0.0;
        $total_amount        = $selling_price;
        
        $payment_status      = 'Unpaid';
        $payment_method      = 'Razorpay';
        $product_key         = 'SMS-PENDING';
        $user_qty            = 1;
        $auto_renew          = 0;
        $priority            = 'Medium';
        
        $stmt = $conn->prepare("INSERT INTO subscriptions (customer_id, customer_name, invoice_no, invoice_date, product_id, product_key, user_qty, license_duration, starting_date, expiry_date, product_description, selling_price, purchase_price, tax_amount, total_amount, payment_status, payment_method, auto_renew, priority, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssisssssddddsssiii", 
            $customer_id, 
            $customer_name, 
            $invoice_no, 
            $invoice_date, 
            $product_id, 
            $product_key, 
            $user_qty, 
            $license_duration, 
            $starting_date, 
            $expiry_date, 
            $product_description, 
            $selling_price, 
            $purchase_price, 
            $tax_amount, 
            $total_amount, 
            $payment_status, 
            $payment_method, 
            $auto_renew, 
            $priority, 
            $user_id
        );
        
        if ($stmt->execute()) {
            $new_sl = $stmt->insert_id;
            $stmt->close();
            echo json_encode(['success' => true, 'sl' => $new_sl, 'invoice_no' => $invoice_no, 'total_amount' => $total_amount]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            if (isset($stmt)) $stmt->close();
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

$branding = getSiteBranding();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pricing - <?php echo htmlspecialchars($branding['site_name']); ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <style>
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; padding: 10px 0; }
        .pricing-card { border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); transition: transform .25s, box-shadow .25s; background: #fff; display: flex; flex-direction: column; }
        .pricing-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,0.15); }
        .pricing-header { padding: 28px 24px 18px; text-align: center; color: #fff; position: relative; }
        .pricing-header .product-icon { font-size: 36px; margin-bottom: 10px; opacity: .9; }
        .pricing-header h3 { margin: 0; font-size: 20px; font-weight: 700; }
        .pricing-price { text-align: center; padding: 20px 24px; }
        .pricing-price .amount { font-size: 32px; font-weight: 800; color: #001f3f; }
        .pricing-price .currency { font-size: 16px; font-weight: 600; color: #666; vertical-align: super; }
        .pricing-desc { padding: 0 24px 24px; flex: 1; color: #555; font-size: 14px; line-height: 1.6; text-align: center; }
        .pricing-desc p { margin: 0; }
        .pricing-footer { padding: 16px 24px; border-top: 1px solid #eee; text-align: center; }
        .pricing-badge { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; color: #fff; }
        .pricing-skeleton { height: 300px; border-radius: 14px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        .dark-mode .pricing-card { background: #1e293b; }
        .dark-mode .pricing-price .amount { color: #e2e8f0; }
        .dark-mode .pricing-desc { color: #94a3b8; }
        .dark-mode .pricing-footer { border-color: #334155; }
        .no-price { font-size: 16px; color: #999; font-style: italic; }
    </style>
</head>
<body>
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="breadcrumb">
                <a href="customer_portal.php"><i class="fas fa-home"></i> My Portal</a>
                <span class="breadcrumb-sep">/</span>
                <span>Pricing</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-tags"></i> Available Products & Pricing</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-box-open"></i> Our Products</h2>
                    <button class="btn btn-primary" onclick="loadProducts()"><i class="fas fa-sync"></i> Refresh</button>
                </div>

                <div class="pricing-grid" id="pricingGrid">
                    <div class="pricing-skeleton"></div>
                    <div class="pricing-skeleton"></div>
                    <div class="pricing-skeleton"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    var globalCurrency = 'INR';

    $(document).ready(function() { loadProducts(); });

    function loadProducts() {
        $.getJSON('?action=getProducts', function(r) {
            if (!r.success) {
                document.getElementById('pricingGrid').innerHTML = '<p style="color:#888;text-align:center;grid-column:1/-1;padding:40px;">Failed to load products</p>';
                return;
            }
            globalCurrency = r.currency || 'INR';
            var products = r.data;
            var grid = document.getElementById('pricingGrid');

            if (!products.length) {
                grid.innerHTML = '<p style="color:#888;text-align:center;grid-column:1/-1;padding:40px;">No products available yet</p>';
                return;
            }

            var icons = ['fa-laptop-code', 'fa-server', 'fa-bullhorn', 'fa-cloud', 'fa-headset', 'fa-shield-alt', 'fa-database', 'fa-cogs'];
            var html = '';

            products.forEach(function(p, i) {
                var icon = icons[i % icons.length];
                var color = p.color_code || '#0078D4';
                var price = p.selling_price > 0
                    ? '<span class="currency">' + globalCurrency + '</span> <span class="amount">' + formatNum(p.selling_price) + '</span>'
                    : '<span class="no-price">Contact for pricing</span>';

                var buttonHtml = p.selling_price > 0
                    ? '<button onclick="purchaseProduct(' + p.product_id + ', ' + p.selling_price + ')" class="btn" style="background:' + color + ';color:#fff;width:100%;border:none;border-radius:6px;padding:10px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;"><i class="fas fa-shopping-cart"></i> Buy Subscription</button>'
                    : '<button disabled class="btn" style="background:#666;color:#ccc;width:100%;border:none;border-radius:6px;padding:10px;font-weight:600;"><i class="fas fa-phone-alt"></i> Contact Sales</button>';

                html += '<div class="pricing-card">';
                html += '<div class="pricing-header" style="background:linear-gradient(135deg, ' + color + ' 0%, ' + adjustColor(color, -30) + ' 100%);">';
                html += '<div class="product-icon"><i class="fas ' + icon + '"></i></div>';
                html += '<h3>' + escHtml(p.product_name) + '</h3>';
                html += '</div>';
                html += '<div class="pricing-price">' + price + '</div>';
                html += '<div class="pricing-desc"><p>' + (p.description ? escHtml(p.description) : 'Premium subscription package') + '</p></div>';
                html += '<div class="pricing-footer">' + buttonHtml + '</div>';
                html += '</div>';
            });

            grid.innerHTML = html;
        });
    }

    // Purchase workflow
    function purchaseProduct(productId, sellingPrice) {
        Swal.fire({
            title: 'Create Subscription?',
            text: 'You are subscribing to this product for ' + globalCurrency + ' ' + formatNum(sellingPrice) + ' / month.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Subscribe & Pay',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0074D9'
        }).then(function(res) {
            if (res.isConfirmed) {
                Swal.fire({
                    title: 'Creating Order…',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: function() { Swal.showLoading(); }
                });

                // Create subscription row in database
                $.ajax({
                    url: 'pricing.php',
                    method: 'POST',
                    data: {
                        action: 'createSubscription',
                        product_id: productId
                    },
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            // Order created! Start Razorpay flow
                            startRazorpayPayment(response.sl, response.invoice_no, response.total_amount);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Order Failed', text: response.message || 'Could not register subscription.' });
                        }
                    },
                    error: function() {
                        Swal.close();
                        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Connection failed. Please try again.' });
                    }
                });
            }
        });
    }

    // Start Razorpay Payment Checkout
    function startRazorpayPayment(sl, invoiceNo, totalAmount) {
        Swal.fire({
            title: 'Connecting Gateway…',
            text: 'Loading Razorpay secure payment interface.',
            allowOutsideClick: false,
            didOpen: function() { Swal.showLoading(); }
        });

        $.ajax({
            url: 'razorpay_create_order.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ subscription_sl: sl }),
            dataType: 'json',
            success: function(r) {
                Swal.close();
                if (!r.success) {
                    Swal.fire({ icon: 'error', title: 'Payment Init Failed', text: r.message || 'Could not initiate payment.' });
                    return;
                }

                var options = {
                    key:         r.key_id,
                    amount:      r.amount,
                    currency:    r.currency || 'INR',
                    name:        '<?php echo addslashes(htmlspecialchars(getSetting("company_name", "Subscription System"))); ?>',
                    description: r.description,
                    order_id:    r.order_id,
                    theme:       { color: '#0074D9' },
                    prefill:     {
                        name:  '<?php echo addslashes(htmlspecialchars($full_name)); ?>',
                        email: '<?php echo addslashes(htmlspecialchars($_SESSION['username'])); ?>@example.com' // Fallback helper
                    },
                    handler: function(response) {
                        verifyRazorpayPayment(response, sl, r.amount);
                    },
                    modal: {
                        ondismiss: function() {
                            Swal.fire({ 
                                icon: 'info', 
                                title: 'Payment Incomplete', 
                                text: 'You cancelled the payment. You can complete this payment anytime from your Customer Portal Dashboard.', 
                                confirmButtonText: 'Go to Dashboard'
                            }).then(function() {
                                window.location.href = 'customer_portal.php';
                            });
                        }
                    }
                };

                var rzp = new Razorpay(options);
                rzp.on('payment.failed', function(response) {
                    Swal.fire({ icon: 'error', title: 'Payment Failed', text: response.error.description || 'Payment failed. Please try again.' });
                });
                rzp.open();
            },
            error: function() {
                Swal.close();
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not connect to payment gateway. Please try again.' });
            }
        });
    }

    // Verify payment from razorpay
    function verifyRazorpayPayment(response, sl, amount) {
        Swal.fire({
            title: 'Verifying Payment…',
            text: 'Confirming your license delivery. Please wait.',
            allowOutsideClick: false,
            didOpen: function() { Swal.showLoading(); }
        });

        $.ajax({
            url: 'razorpay_verify.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                razorpay_order_id:   response.razorpay_order_id,
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_signature:  response.razorpay_signature,
                subscription_sl:     sl,
                amount:              amount
            }),
            dataType: 'json',
            success: function(r) {
                Swal.close();
                if (r.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '🎉 Subscription Activated!',
                        html: 'Your payment was successful and license key has been generated.<br><br>Checking details...',
                        confirmButtonText: 'Go to My Portal'
                    }).then(function() {
                        window.location.href = 'customer_portal.php';
                    });
                } else {
                    Swal.fire({ icon: 'warning', title: 'Verification Issue', text: r.message, footer: 'Contact admin with your Payment ID if money was deducted.' });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({ icon: 'error', title: 'Verification Failed', text: 'Connection failed. Please check your Dashboard portal to confirm status.' });
            }
        });
    }

    function formatNum(n) {
        return parseFloat(n).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function adjustColor(hex, amt) {
        hex = hex.replace('#', '');
        var r = Math.max(0, Math.min(255, parseInt(hex.substring(0, 2), 16) + amt));
        var g = Math.max(0, Math.min(255, parseInt(hex.substring(2, 4), 16) + amt));
        var b = Math.max(0, Math.min(255, parseInt(hex.substring(4, 6), 16) + amt));
        return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }
    </script>
</body>
</html>
