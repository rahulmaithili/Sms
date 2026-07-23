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
$current_page = 'add_subscription';

$edit_mode = isset($_GET['edit']) && intval($_GET['edit']) > 0;
$edit_sl = $edit_mode ? intval($_GET['edit']) : 0;

// salesperson can only edit own subs
if ($role === 'salesperson' && $edit_mode) {
    $conn = getDBConnection();
    $chk = $conn->prepare("SELECT salesperson_id FROM subscriptions WHERE sl = ?");
    $chk->bind_param("i", $edit_sl);
    $chk->execute();
    $owner = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$owner || (int)($owner['salesperson_id'] ?? 0) !== $sp_id) {
        header("Location: subscriptions.php");
        exit();
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            case 'addSubscription':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }
                $customer_id        = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? intval($_POST['customer_id']) : null;
                $customer_name      = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
                $renewal_invoice    = isset($_POST['renewal_invoice']) ? trim($_POST['renewal_invoice']) : '';
                $invoice_date       = isset($_POST['invoice_date']) ? trim($_POST['invoice_date']) : '';
                $product_id         = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? intval($_POST['product_id']) : null;
                $product_key        = isset($_POST['product_key']) ? trim($_POST['product_key']) : '';
                $user_qty           = isset($_POST['user_qty']) && $_POST['user_qty'] !== '' ? intval($_POST['user_qty']) : 1;
                $license_duration   = isset($_POST['license_duration']) ? trim($_POST['license_duration']) : '';
                $starting_date      = isset($_POST['starting_date']) ? trim($_POST['starting_date']) : '';
                $expiry_date        = isset($_POST['expiry_date']) ? trim($_POST['expiry_date']) : '';
                $product_description = isset($_POST['product_description']) ? trim($_POST['product_description']) : '';
                $selling_price      = isset($_POST['selling_price']) && $_POST['selling_price'] !== '' ? floatval($_POST['selling_price']) : 0;
                $purchase_price     = isset($_POST['purchase_price']) && $_POST['purchase_price'] !== '' ? floatval($_POST['purchase_price']) : 0;
                $tax_amount         = isset($_POST['tax_amount']) && $_POST['tax_amount'] !== '' ? floatval($_POST['tax_amount']) : 0;
                $total_amount       = isset($_POST['total_amount']) && $_POST['total_amount'] !== '' ? floatval($_POST['total_amount']) : 0;
                $payment_status     = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'Unpaid';
                $payment_method     = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
                $payment_date       = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : '';
                $auto_renew         = isset($_POST['auto_renew']) ? intval($_POST['auto_renew']) : 0;
                $priority           = isset($_POST['priority']) ? trim($_POST['priority']) : 'Medium';
                $salesperson_id     = isset($_POST['salesperson_id']) && $_POST['salesperson_id'] !== '' ? intval($_POST['salesperson_id']) : null;
                if ($role === 'salesperson' && $sp_id) $salesperson_id = $sp_id;
                $supplier_id        = isset($_POST['supplier_id']) && $_POST['supplier_id'] !== '' ? intval($_POST['supplier_id']) : null;
                $supplier_name      = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
                $supplier_email     = isset($_POST['supplier_email']) ? trim($_POST['supplier_email']) : '';
                $supplier_phone     = isset($_POST['supplier_phone']) ? trim($_POST['supplier_phone']) : '';
                $contract_reference = isset($_POST['contract_reference']) ? trim($_POST['contract_reference']) : '';
                $remarks            = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
                $currency_code      = isset($_POST['currency_code']) ? trim($_POST['currency_code']) : null;

                // Validation
                if (empty($customer_name)) {
                    echo json_encode(['success' => false, 'message' => 'Customer name is required']);
                    exit();
                }
                if (empty($invoice_date)) {
                    echo json_encode(['success' => false, 'message' => 'Invoice date is required']);
                    exit();
                }

                // file upload
                $attachment_url = null;
                if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['attachment_file'];
                    $allowed = ['application/pdf','image/jpeg','image/png','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    if (!in_array($mime, $allowed)) {
                        echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit();
                    }
                    if ($file['size'] > 5 * 1024 * 1024) {
                        echo json_encode(['success'=>false,'message'=>'File too large (max 5MB)']); exit();
                    }
                    $upload_dir = __DIR__ . '/uploads/attachments/';
                    if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = 'attach_' . time() . '_' . random_int(1000,9999) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        $attachment_url = 'uploads/attachments/' . $filename;
                    }
                }

                // Handle nullable fields
                $renewal_invoice    = !empty($renewal_invoice) ? $renewal_invoice : null;
                $product_key        = !empty($product_key) ? $product_key : null;
                $license_duration   = !empty($license_duration) ? $license_duration : null;
                $starting_date      = !empty($starting_date) ? $starting_date : null;
                $expiry_date        = !empty($expiry_date) ? $expiry_date : null;
                $product_description = !empty($product_description) ? $product_description : null;
                $payment_method     = !empty($payment_method) ? $payment_method : null;
                $payment_date       = !empty($payment_date) ? $payment_date : null;
                $supplier_name      = !empty($supplier_name) ? $supplier_name : null;
                $supplier_email     = !empty($supplier_email) ? $supplier_email : null;
                $supplier_phone     = !empty($supplier_phone) ? $supplier_phone : null;
                $contract_reference = !empty($contract_reference) ? $contract_reference : null;
                $attachment_url     = !empty($attachment_url) ? $attachment_url : null;
                $remarks            = !empty($remarks) ? $remarks : null;
                $currency_code      = !empty($currency_code) ? $currency_code : null;

                $conn = getDBConnection();

                // Auto-create customer if not selected from CRM but name provided
                if ($customer_id === null && !empty($customer_name)) {
                    // Check if customer already exists by name
                    $chk = $conn->prepare("SELECT customer_id FROM customers WHERE company_name = ? LIMIT 1");
                    $chk->bind_param("s", $customer_name);
                    $chk->execute();
                    $existing = $chk->get_result()->fetch_assoc();
                    $chk->close();

                    if ($existing) {
                        $customer_id = (int)$existing['customer_id'];
                    } else {
                        // Create new customer
                        $ins_cust = $conn->prepare("INSERT INTO customers (company_name, added_by, is_active) VALUES (?, ?, 1)");
                        $ins_cust->bind_param("si", $customer_name, $user_id);
                        if ($ins_cust->execute()) {
                            $customer_id = $conn->insert_id;
                            logActivity($user_id, $username, 'Customer Auto-Created', "Auto-created customer: $customer_name");
                        }
                        $ins_cust->close();
                    }
                }

                // Auto-create supplier if not selected but name provided
                if ($supplier_id === null && !empty($supplier_name)) {
                    $chk = $conn->prepare("SELECT supplier_id FROM suppliers WHERE company_name = ? LIMIT 1");
                    $chk->bind_param("s", $supplier_name);
                    $chk->execute();
                    $existing = $chk->get_result()->fetch_assoc();
                    $chk->close();

                    if ($existing) {
                        $supplier_id = (int)$existing['supplier_id'];
                    } else {
                        $ins_supp = $conn->prepare("INSERT INTO suppliers (company_name, email, phone, added_by, is_active) VALUES (?, ?, ?, ?, 1)");
                        $supp_email_val = !empty($supplier_email) ? $supplier_email : null;
                        $supp_phone_val = !empty($supplier_phone) ? $supplier_phone : null;
                        $ins_supp->bind_param("sssi", $supplier_name, $supp_email_val, $supp_phone_val, $user_id);
                        if ($ins_supp->execute()) {
                            $supplier_id = $conn->insert_id;
                            logActivity($user_id, $username, 'Supplier Auto-Created', "Auto-created supplier: $supplier_name");
                        }
                        $ins_supp->close();
                    }
                }

                // auto-gen customer ID
                $invoice_no = generateInvoiceNo('CID');

                $stmt = $conn->prepare("INSERT INTO subscriptions (customer_id, customer_name, invoice_no, renewal_invoice, invoice_date, product_id, product_key, user_qty, license_duration, starting_date, expiry_date, product_description, selling_price, purchase_price, tax_amount, total_amount, payment_status, payment_method, payment_date, auto_renew, priority, salesperson_id, supplier_name, supplier_email, supplier_phone, supplier_id, contract_reference, attachment_url, remarks, currency_code, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "issssisissssddddsssisisssissssi",
                    $customer_id,        // i 1
                    $customer_name,      // s 2
                    $invoice_no,         // s 3
                    $renewal_invoice,    // s 4
                    $invoice_date,       // s 5
                    $product_id,         // i 6
                    $product_key,        // s 7
                    $user_qty,           // i 8
                    $license_duration,   // s 9
                    $starting_date,      // s 10
                    $expiry_date,        // s 11
                    $product_description,// s 12
                    $selling_price,      // d 13
                    $purchase_price,     // d 14
                    $tax_amount,         // d 15
                    $total_amount,       // d 16
                    $payment_status,     // s 17
                    $payment_method,     // s 18
                    $payment_date,       // s 19
                    $auto_renew,         // i 20
                    $priority,           // s 21
                    $salesperson_id,     // i 22
                    $supplier_name,      // s 23
                    $supplier_email,     // s 24
                    $supplier_phone,     // s 25
                    $supplier_id,        // i 26
                    $contract_reference, // s 27
                    $attachment_url,     // s 28
                    $remarks,            // s 29
                    $currency_code,      // s 30
                    $user_id             // i 31
                );

                if ($stmt->execute()) {
                    $new_sl = $stmt->insert_id;
                    $stmt->close();
                    // save custom fields
                    foreach ($_POST as $k => $v) {
                        if (strpos($k, 'cf_') === 0) {
                            $fid = intval(substr($k, 3)); $fv = trim($v);
                            $cfs = $conn->prepare("INSERT INTO custom_field_values (field_id, entity_type, entity_id, field_value) VALUES (?, 'subscription', ?, ?) ON DUPLICATE KEY UPDATE field_value = ?");
                            $cfs->bind_param("iiss", $fid, $new_sl, $fv, $fv); $cfs->execute(); $cfs->close();
                        }
                    }
                    logActivity($user_id, $username, 'Subscription Created', "Added subscription: $invoice_no for $customer_name");
                    createNotificationForAdmins('New Subscription', "Subscription $invoice_no created by $username", 'info', 'subscriptions.php');
                    echo json_encode(['success' => true, 'message' => 'Subscription added successfully (ID: ' . $invoice_no . ')', 'sl' => $new_sl, 'invoice_no' => $invoice_no]);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Duplicate ID generated, please try again']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add subscription']);
                    }
                }
                exit();

            case 'updateSubscription':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $sl = isset($_POST['sl']) ? intval($_POST['sl']) : 0;
                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
                    exit();
                }

                // RBAC: ownership check
                if ($role !== 'admin') {
                    $conn = getDBConnection();
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
                    } elseif ((int)$owner['added_by'] !== (int)$user_id) {
                        echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
                    }
                }

                $customer_id        = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? intval($_POST['customer_id']) : null;
                $customer_name      = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
                $renewal_invoice    = isset($_POST['renewal_invoice']) ? trim($_POST['renewal_invoice']) : '';
                $invoice_date       = isset($_POST['invoice_date']) ? trim($_POST['invoice_date']) : '';
                $product_id         = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? intval($_POST['product_id']) : null;
                $product_key        = isset($_POST['product_key']) ? trim($_POST['product_key']) : '';
                $user_qty           = isset($_POST['user_qty']) && $_POST['user_qty'] !== '' ? intval($_POST['user_qty']) : 1;
                $license_duration   = isset($_POST['license_duration']) ? trim($_POST['license_duration']) : '';
                $starting_date      = isset($_POST['starting_date']) ? trim($_POST['starting_date']) : '';
                $expiry_date        = isset($_POST['expiry_date']) ? trim($_POST['expiry_date']) : '';
                $product_description = isset($_POST['product_description']) ? trim($_POST['product_description']) : '';
                $selling_price      = isset($_POST['selling_price']) && $_POST['selling_price'] !== '' ? floatval($_POST['selling_price']) : 0;
                $purchase_price     = isset($_POST['purchase_price']) && $_POST['purchase_price'] !== '' ? floatval($_POST['purchase_price']) : 0;
                $tax_amount         = isset($_POST['tax_amount']) && $_POST['tax_amount'] !== '' ? floatval($_POST['tax_amount']) : 0;
                $total_amount       = isset($_POST['total_amount']) && $_POST['total_amount'] !== '' ? floatval($_POST['total_amount']) : 0;
                $payment_status     = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'Unpaid';
                $payment_method     = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
                $payment_date       = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : '';
                $auto_renew         = isset($_POST['auto_renew']) ? intval($_POST['auto_renew']) : 0;
                $priority           = isset($_POST['priority']) ? trim($_POST['priority']) : 'Medium';
                $salesperson_id     = isset($_POST['salesperson_id']) && $_POST['salesperson_id'] !== '' ? intval($_POST['salesperson_id']) : null;
                if ($role === 'salesperson' && $sp_id) $salesperson_id = $sp_id;
                $supplier_id        = isset($_POST['supplier_id']) && $_POST['supplier_id'] !== '' ? intval($_POST['supplier_id']) : null;
                $supplier_name      = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
                $supplier_email     = isset($_POST['supplier_email']) ? trim($_POST['supplier_email']) : '';
                $supplier_phone     = isset($_POST['supplier_phone']) ? trim($_POST['supplier_phone']) : '';
                $contract_reference = isset($_POST['contract_reference']) ? trim($_POST['contract_reference']) : '';
                $remarks            = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
                $currency_code      = isset($_POST['currency_code']) ? trim($_POST['currency_code']) : null;

                // Validation
                if (empty($customer_name)) {
                    echo json_encode(['success' => false, 'message' => 'Customer name is required']);
                    exit();
                }
                if (empty($invoice_date)) {
                    echo json_encode(['success' => false, 'message' => 'Invoice date is required']);
                    exit();
                }

                $conn = getDBConnection();

                // file upload - get old attachment first
                $attachment_url = null;
                $old_attach = null;
                $old_stmt = $conn->prepare("SELECT attachment_url FROM subscriptions WHERE sl = ?");
                $old_stmt->bind_param("i", $sl);
                $old_stmt->execute();
                $old_res = $old_stmt->get_result()->fetch_assoc();
                $old_stmt->close();
                if ($old_res) $old_attach = $old_res['attachment_url'];

                if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['attachment_file'];
                    $allowed = ['application/pdf','image/jpeg','image/png','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    if (!in_array($mime, $allowed)) {
                        echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit();
                    }
                    if ($file['size'] > 5 * 1024 * 1024) {
                        echo json_encode(['success'=>false,'message'=>'File too large (max 5MB)']); exit();
                    }
                    $upload_dir = __DIR__ . '/uploads/attachments/';
                    if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = 'attach_' . time() . '_' . random_int(1000,9999) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        $attachment_url = 'uploads/attachments/' . $filename;
                        // del old file
                        if ($old_attach && file_exists(__DIR__ . '/' . $old_attach)) {
                            unlink(__DIR__ . '/' . $old_attach);
                        }
                    }
                } else {
                    // keep existing attachment
                    $attachment_url = $old_attach;
                }

                // Handle nullable fields
                $renewal_invoice    = !empty($renewal_invoice) ? $renewal_invoice : null;
                $product_key        = !empty($product_key) ? $product_key : null;
                $license_duration   = !empty($license_duration) ? $license_duration : null;
                $starting_date      = !empty($starting_date) ? $starting_date : null;
                $expiry_date        = !empty($expiry_date) ? $expiry_date : null;
                $product_description = !empty($product_description) ? $product_description : null;
                $payment_method     = !empty($payment_method) ? $payment_method : null;
                $payment_date       = !empty($payment_date) ? $payment_date : null;
                $supplier_name      = !empty($supplier_name) ? $supplier_name : null;
                $supplier_email     = !empty($supplier_email) ? $supplier_email : null;
                $supplier_phone     = !empty($supplier_phone) ? $supplier_phone : null;
                $contract_reference = !empty($contract_reference) ? $contract_reference : null;
                $attachment_url     = !empty($attachment_url) ? $attachment_url : null;
                $remarks            = !empty($remarks) ? $remarks : null;
                $currency_code      = !empty($currency_code) ? $currency_code : null;

                // Auto-create supplier if not selected but name provided
                if ($supplier_id === null && !empty($supplier_name)) {
                    $chk = $conn->prepare("SELECT supplier_id FROM suppliers WHERE company_name = ? LIMIT 1");
                    $chk->bind_param("s", $supplier_name);
                    $chk->execute();
                    $existing = $chk->get_result()->fetch_assoc();
                    $chk->close();

                    if ($existing) {
                        $supplier_id = (int)$existing['supplier_id'];
                    } else {
                        $ins_supp = $conn->prepare("INSERT INTO suppliers (company_name, email, phone, added_by, is_active) VALUES (?, ?, ?, ?, 1)");
                        $supp_email_val = !empty($supplier_email) ? $supplier_email : null;
                        $supp_phone_val = !empty($supplier_phone) ? $supplier_phone : null;
                        $ins_supp->bind_param("sssi", $supplier_name, $supp_email_val, $supp_phone_val, $user_id);
                        if ($ins_supp->execute()) {
                            $supplier_id = $conn->insert_id;
                            logActivity($user_id, $username, 'Supplier Auto-Created', "Auto-created supplier: $supplier_name");
                        }
                        $ins_supp->close();
                    }
                }

                $stmt = $conn->prepare("UPDATE subscriptions SET customer_id = ?, customer_name = ?, renewal_invoice = ?, invoice_date = ?, product_id = ?, product_key = ?, user_qty = ?, license_duration = ?, starting_date = ?, expiry_date = ?, product_description = ?, selling_price = ?, purchase_price = ?, tax_amount = ?, total_amount = ?, payment_status = ?, payment_method = ?, payment_date = ?, auto_renew = ?, priority = ?, salesperson_id = ?, supplier_name = ?, supplier_email = ?, supplier_phone = ?, supplier_id = ?, contract_reference = ?, attachment_url = ?, remarks = ?, currency_code = ?, updated_by = ? WHERE sl = ?");
                $stmt->bind_param(
                    "isssisissssddddsssisisssissssii",
                    $customer_id,        // i 1
                    $customer_name,      // s 2
                    $renewal_invoice,    // s 3
                    $invoice_date,       // s 4
                    $product_id,         // i 5
                    $product_key,        // s 6
                    $user_qty,           // i 7
                    $license_duration,   // s 8
                    $starting_date,      // s 9
                    $expiry_date,        // s 10
                    $product_description,// s 11
                    $selling_price,      // d 12
                    $purchase_price,     // d 13
                    $tax_amount,         // d 14
                    $total_amount,       // d 15
                    $payment_status,     // s 16
                    $payment_method,     // s 17
                    $payment_date,       // s 18
                    $auto_renew,         // i 19
                    $priority,           // s 20
                    $salesperson_id,     // i 21
                    $supplier_name,      // s 22
                    $supplier_email,     // s 23
                    $supplier_phone,     // s 24
                    $supplier_id,        // i 25
                    $contract_reference, // s 26
                    $attachment_url,     // s 27
                    $remarks,            // s 28
                    $currency_code,      // s 29
                    $user_id,            // i 30
                    $sl                  // i 31 (WHERE clause)
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    // save custom fields
                    foreach ($_POST as $k => $v) {
                        if (strpos($k, 'cf_') === 0) {
                            $fid = intval(substr($k, 3)); $fv = trim($v);
                            $cfs = $conn->prepare("INSERT INTO custom_field_values (field_id, entity_type, entity_id, field_value) VALUES (?, 'subscription', ?, ?) ON DUPLICATE KEY UPDATE field_value = ?");
                            $cfs->bind_param("iiss", $fid, $sl, $fv, $fv); $cfs->execute(); $cfs->close();
                        }
                    }
                    logActivity($user_id, $username, 'Subscription Updated', "Updated subscription SL#$sl");
                    echo json_encode(['success' => true, 'message' => 'Subscription updated successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Duplicate entry detected']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update subscription']);
                    }
                }
                exit();

            case 'getSubscription':
                $sl = isset($_GET['sl']) ? intval($_GET['sl']) : 0;
                if ($sl <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE sl = ?");
                $stmt->bind_param("i", $sl);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
                    exit();
                }

                $record = $result->fetch_assoc();
                $stmt->close();

                // RBAC: user can only fetch own records
                if ($role !== 'admin' && (int)$record['added_by'] !== (int)$user_id) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit();
                }

                echo json_encode(['success' => true, 'data' => $record]);
                exit();

            case 'getFormDropdowns':
                $conn = getDBConnection();

                // Get active products
                $catStmt = $conn->prepare("SELECT product_id, product_name, selling_price, purchase_price FROM products WHERE is_active = 1 ORDER BY display_order ASC, product_name ASC");
                $catStmt->execute();
                $catResult = $catStmt->get_result();
                $products = [];
                while ($row = $catResult->fetch_assoc()) {
                    $products[] = $row;
                }
                $catStmt->close();

                // Get active salespersons
                $spStmt = $conn->prepare("SELECT salesperson_id, name, commission_rate FROM salespersons WHERE is_active = 1 ORDER BY name ASC");
                $spStmt->execute();
                $spResult = $spStmt->get_result();
                $salespersons = [];
                while ($row = $spResult->fetch_assoc()) {
                    $salespersons[] = $row;
                }
                $spStmt->close();

                // Get active customers from CRM + fallback from subscriptions
                $customers = [];
                $custs = $conn->query("SELECT customer_id, company_name FROM customers WHERE is_active=1 ORDER BY company_name ASC");
                if ($custs) {
                    while ($r = $custs->fetch_assoc()) $customers[] = $r;
                }
                // also pull unique customer names from subscriptions
                $existingNames = array_map(function($c) { return strtolower($c['company_name']); }, $customers);
                $subCusts = $conn->query("SELECT DISTINCT customer_id, customer_name FROM subscriptions WHERE customer_name IS NOT NULL AND customer_name != '' ORDER BY customer_name ASC");
                if ($subCusts) {
                    while ($r = $subCusts->fetch_assoc()) {
                        if (!in_array(strtolower($r['customer_name']), $existingNames)) {
                            $customers[] = ['customer_id' => (int)$r['customer_id'], 'company_name' => $r['customer_name']];
                            $existingNames[] = strtolower($r['customer_name']);
                        }
                    }
                }

                // Get active suppliers (with email + phone for auto-fill)
                $supps = $conn->query("SELECT supplier_id, company_name, email, phone FROM suppliers WHERE is_active=1 ORDER BY company_name ASC");
                $suppliers = [];
                if ($supps) {
                    while ($r = $supps->fetch_assoc()) $suppliers[] = $r;
                }

                // Get payment methods from dropdown_options
                $pmethods = [];
                $pmRes = $conn->query("SELECT option_value FROM dropdown_options WHERE dropdown_type='payment_method' AND is_active=1 ORDER BY display_order ASC, option_value ASC");
                if ($pmRes) {
                    while ($r = $pmRes->fetch_assoc()) $pmethods[] = $r['option_value'];
                }

                // Get active tax rates
                $taxRates = [];
                $trStmt = $conn->prepare("SELECT tax_id, name, rate, is_default FROM tax_rates WHERE is_active = 1 ORDER BY name ASC");
                $trStmt->execute();
                $trResult = $trStmt->get_result();
                while ($r = $trResult->fetch_assoc()) {
                    $taxRates[] = [
                        'tax_id' => (int)$r['tax_id'],
                        'name' => $r['name'],
                        'rate' => (float)$r['rate'],
                        'is_default' => (int)$r['is_default']
                    ];
                }
                $trStmt->close();

                // currencies
                $currencies = [];
                $crStmt = $conn->prepare("SELECT currency_id, code, name, symbol, exchange_rate, is_default FROM currencies WHERE is_active = 1 ORDER BY is_default DESC, code ASC");
                $crStmt->execute();
                $crResult = $crStmt->get_result();
                while ($r = $crResult->fetch_assoc()) { $currencies[] = $r; }
                $crStmt->close();

                // custom fields for subscription
                $cfStmt = $conn->prepare("SELECT field_id, field_name, field_label, field_type, field_options, is_required, display_order FROM custom_fields WHERE entity_type = 'subscription' AND is_active = 1 ORDER BY display_order ASC");
                $cfStmt->execute();
                $cfResult = $cfStmt->get_result();
                $customFields = [];
                while ($r = $cfResult->fetch_assoc()) {
                    $customFields[] = [
                        'field_id' => (int)$r['field_id'],
                        'field_name' => $r['field_name'],
                        'field_label' => $r['field_label'],
                        'field_type' => $r['field_type'],
                        'field_options' => $r['field_options'] ?? '',
                        'is_required' => (bool)$r['is_required']
                    ];
                }
                $cfStmt->close();

                echo json_encode(['success' => true, 'products' => $products, 'salespersons' => $salespersons, 'customers' => $customers, 'suppliers' => $suppliers, 'payment_methods' => $pmethods, 'tax_rates' => $taxRates, 'currencies' => $currencies, 'custom_fields' => $customFields]);
                exit();

            case 'getSubCustomFieldValues':
                $sl = isset($_GET['sl']) ? intval($_GET['sl']) : 0;
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT field_id, field_value FROM custom_field_values WHERE entity_type = 'subscription' AND entity_id = ?");
                $stmt->bind_param("i", $sl);
                $stmt->execute();
                $res = $stmt->get_result();
                $vals = [];
                while ($r = $res->fetch_assoc()) $vals[$r['field_id']] = $r['field_value'];
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $vals]);
                exit();

            case 'checkDuplicate':
                $invoice_no = isset($_GET['invoice_no']) ? trim($_GET['invoice_no']) : '';
                $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
                $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
                $starting_date = isset($_GET['starting_date']) ? trim($_GET['starting_date']) : '';
                $expiry_date = isset($_GET['expiry_date']) ? trim($_GET['expiry_date']) : '';
                $exclude_sl = isset($_GET['exclude_sl']) ? intval($_GET['exclude_sl']) : 0;

                $conn = getDBConnection();
                $warnings = [];

                // check invoice_no dup
                if (!empty($invoice_no)) {
                    $sql = "SELECT sl, customer_name FROM subscriptions WHERE invoice_no = ?";
                    if ($exclude_sl > 0) $sql .= " AND sl != ?";
                    $stmt = $conn->prepare($sql);
                    if ($exclude_sl > 0) {
                        $stmt->bind_param("si", $invoice_no, $exclude_sl);
                    } else {
                        $stmt->bind_param("s", $invoice_no);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $dup = $result->fetch_assoc();
                        $warnings[] = "Invoice \"$invoice_no\" already exists (SL#" . $dup['sl'] . " - " . $dup['customer_name'] . ")";
                    }
                    $stmt->close();
                }

                // check overlapping dates for same customer + product
                if ($customer_id > 0 && $product_id > 0 && !empty($starting_date) && !empty($expiry_date)) {
                    $sql = "SELECT sl, invoice_no FROM subscriptions WHERE customer_id = ? AND product_id = ? AND starting_date <= ? AND expiry_date >= ?";
                    if ($exclude_sl > 0) $sql .= " AND sl != ?";
                    $stmt = $conn->prepare($sql);
                    if ($exclude_sl > 0) {
                        $stmt->bind_param("iissi", $customer_id, $product_id, $expiry_date, $starting_date, $exclude_sl);
                    } else {
                        $stmt->bind_param("iiss", $customer_id, $product_id, $expiry_date, $starting_date);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $dup = $result->fetch_assoc();
                        $warnings[] = "Overlapping subscription found: " . $dup['invoice_no'] . " (SL#" . $dup['sl'] . ") for same customer + product";
                    }
                    $stmt->close();
                }

                echo json_encode(['success' => true, 'warnings' => $warnings]);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("add_subscription.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
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
    <title><?php echo $edit_mode ? 'Edit' : 'Add'; ?> Subscription - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <style>
        .mb-30 { margin-bottom: 30px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; font-size: 14px; }
        .form-group label i { margin-right: 6px; color: var(--navy-primary, #001f3f); }
        .form-group input,
        .form-group select,
        .form-group textarea { width: 100%; padding: 14px 16px; border: 1px solid #d0d0d0; border-radius: 2px; font-size: 16px !important; transition: all 0.3s; font-family: inherit; touch-action: manipulation; }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { outline: none; border-color: var(--navy-accent, #0074D9); box-shadow: 0 0 0 2px rgba(0,116,217,0.1); }
        .form-group input[readonly] { background: #f5f5f5; color: #888; cursor: not-allowed; }
        .form-group .required-star { color: #ea4335; margin-left: 2px; }

        .checkbox-group { display: flex; align-items: center; gap: 10px; padding: 14px 0; }
        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: var(--navy-accent, #0074D9); }
        .checkbox-group label { margin-bottom: 0; cursor: pointer; }

        .form-actions { display: flex; gap: 10px; margin-top: 30px; justify-content: center; flex-wrap: wrap; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; align-items: stretch; }
        }

        /* Searchable Dropdown */
        .searchable-dropdown { position: relative; width: 100%; }
        .searchable-dropdown-input { width: 100%; padding: 10px 36px 10px 12px; border: 2px solid #ced4da; border-radius: 3px; font-size: 16px !important; transition: all 0.3s; font-family: inherit; background: white; cursor: pointer; touch-action: manipulation; box-sizing: border-box; }
        .searchable-dropdown-input:focus { outline: none; border-color: var(--navy-accent, #0074D9); box-shadow: 0 0 0 3px rgba(0,116,217,0.15); }
        .searchable-dropdown-arrow { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #666; font-size: 12px; transition: transform 0.3s; }
        .searchable-dropdown-arrow.open { transform: translateY(-50%) rotate(180deg); }
        .searchable-dropdown-list { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 2px solid #0074D9; border-top: none; border-radius: 0 0 3px 3px; max-height: 250px; overflow-y: auto; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .searchable-dropdown-item { padding: 10px 14px; cursor: pointer; font-size: 14px; transition: background 0.2s; border-bottom: 1px solid #f0f0f0; }
        .searchable-dropdown-item:hover { background: rgba(0,116,217,0.1); }
        .searchable-dropdown-item.selected { background: #001f3f; color: white; }
        .searchable-dropdown-item.no-results { color: #999; font-style: italic; cursor: default; }
        .searchable-dropdown-item.no-results:hover { background: transparent; }
        .searchable-dropdown-item.create-new { color: #0074D9; font-weight: 600; border-top: 2px solid #e0e6ef; }
        .searchable-dropdown-item.create-new:hover { background: rgba(0,116,217,0.1); }
        .searchable-dropdown-item.create-new i { margin-right: 6px; }
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
                <span><?php echo $edit_mode ? 'Edit Subscription' : 'Add Subscription'; ?></span>
            </div>
            <div class="header">
                <h1><i class="fas fa-plus-circle"></i> <?php echo $edit_mode ? 'Edit' : 'Add'; ?> Subscription</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <form id="subscriptionForm" autocomplete="off">

                <!-- Section 1: Invoice Information -->
                <div class="data-section mb-30">
                    <div class="section-header">
                        <h2><i class="fas fa-file-invoice"></i> Invoice Information</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-address-book"></i> Customer <span class="required-star">*</span></label>
                            <input type="hidden" id="customer_id" name="customer_id" value="">
                            <input type="hidden" id="customer_name" name="customer_name" value="">
                            <div class="searchable-dropdown" id="customerDropdown">
                                <input type="text" class="searchable-dropdown-input" id="customerSearch" placeholder="Search or type new customer..." autocomplete="off">
                                <span class="searchable-dropdown-arrow" id="customerArrow"><i class="fas fa-chevron-down"></i></span>
                                <div class="searchable-dropdown-list" id="customerList" style="display:none;"></div>
                            </div>
                            <div class="help-text" style="font-size:12px;color:#888;margin-top:4px;">Select from CRM or type a new customer name — it will be auto-created</div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-id-badge"></i> Customer ID</label>
                            <input type="text" id="invoice_no" readonly maxlength="50" value="<?php echo $edit_mode ? '' : 'Auto-generated on save'; ?>" style="<?php echo $edit_mode ? '' : 'color:#888;font-style:italic;'; ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-redo"></i> Renewal Invoice</label>
                            <input type="text" id="renewal_invoice" name="renewal_invoice" maxlength="50" placeholder="Previous invoice this renews">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Invoice Date <span class="required-star">*</span></label>
                            <input type="date" id="invoice_date" name="invoice_date" required>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Product Information -->
                <div class="data-section mb-30">
                    <div class="section-header">
                        <h2><i class="fas fa-box"></i> Product Information</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-tags"></i> Product</label>
                            <input type="hidden" id="product_id" name="product_id" value="">
                            <div class="searchable-dropdown" id="productDropdown">
                                <input type="text" class="searchable-dropdown-input" id="productSearch" placeholder="Search product..." autocomplete="off">
                                <span class="searchable-dropdown-arrow" id="productArrow"><i class="fas fa-chevron-down"></i></span>
                                <div class="searchable-dropdown-list" id="productList" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Product Key</label>
                            <input type="text" id="product_key" name="product_key" maxlength="255" placeholder="Enter product key">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-users"></i> User Qty</label>
                            <input type="number" id="user_qty" name="user_qty" min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> License Duration</label>
                            <select id="license_duration" name="license_duration">
                                <option value="">-- Select Duration --</option>
                                <option value="1 Month">1 Month</option>
                                <option value="2 Months">2 Months</option>
                                <option value="3 Months">3 Months</option>
                                <option value="6 Months">6 Months</option>
                                <option value="1 Year">1 Year</option>
                                <option value="2 Years">2 Years</option>
                                <option value="3 Years">3 Years</option>
                                <option value="5 Years">5 Years</option>
                                <option value="Lifetime">Lifetime</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-plus"></i> Starting Date</label>
                            <input type="date" id="starting_date" name="starting_date">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-times"></i> Expiry Date</label>
                            <input type="date" id="expiry_date" name="expiry_date">
                        </div>
                        <div class="form-group full-width">
                            <label><i class="fas fa-align-left"></i> Product Description</label>
                            <textarea id="product_description" name="product_description" rows="3" placeholder="Enter product description"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Financial Information -->
                <div class="data-section mb-30">
                    <div class="section-header">
                        <h2><i class="fas fa-money-bill-wave"></i> Financial Information</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Currency</label>
                            <select id="currency_code" name="currency_code">
                                <option value="">-- System Default --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Selling Price</label>
                            <input type="number" id="selling_price" name="selling_price" step="0.001" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-shopping-cart"></i> Purchase Price</label>
                            <input type="number" id="purchase_price" name="purchase_price" step="0.001" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-percent"></i> Tax Rate</label>
                            <select id="tax_rate_select" onchange="applyTaxRate()">
                                <option value="" data-rate="0">Manual Entry</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-percent"></i> Tax %</label>
                            <input type="number" id="tax_pct" step="0.01" value="<?php echo htmlspecialchars(getSetting('tax_percentage', '0')); ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-receipt"></i> Tax Amount</label>
                            <input type="number" id="tax_amount" name="tax_amount" step="0.001" readonly value="0">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Total Amount</label>
                            <input type="number" id="total_amount" name="total_amount" step="0.001" readonly value="0">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-credit-card"></i> Payment Status</label>
                            <select id="payment_status" name="payment_status">
                                <option value="Paid">Paid</option>
                                <option value="Unpaid" selected>Unpaid</option>
                                <option value="Partial">Partial</option>
                                <option value="Refunded">Refunded</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-wallet"></i> Payment Method</label>
                            <input type="hidden" id="payment_method" name="payment_method" value="">
                            <div class="searchable-dropdown" id="paymentMethodDropdown">
                                <input type="text" class="searchable-dropdown-input" id="paymentMethodSearch" placeholder="Search payment method..." autocomplete="off">
                                <span class="searchable-dropdown-arrow" id="paymentMethodArrow"><i class="fas fa-chevron-down"></i></span>
                                <div class="searchable-dropdown-list" id="paymentMethodList" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-check"></i> Payment Date</label>
                            <input type="date" id="payment_date" name="payment_date">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-sync-alt"></i> Auto Renew</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="auto_renew">
                                <label for="auto_renew">Enable automatic renewal</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Sales & Priority -->
                <div class="data-section mb-30">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-bar"></i> Sales & Priority</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-flag"></i> Priority</label>
                            <select id="priority" name="priority">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user-tie"></i> Sales Person</label>
                            <input type="hidden" id="salesperson_id" name="salesperson_id" value="">
                            <div class="searchable-dropdown" id="salespersonDropdown">
                                <input type="text" class="searchable-dropdown-input" id="salespersonSearch" placeholder="Search sales person..." autocomplete="off">
                                <span class="searchable-dropdown-arrow" id="salespersonArrow"><i class="fas fa-chevron-down"></i></span>
                                <div class="searchable-dropdown-list" id="salespersonList" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Supplier Information -->
                <div class="data-section mb-30">
                    <div class="section-header">
                        <h2><i class="fas fa-truck"></i> Supplier Information</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-truck"></i> Supplier</label>
                            <input type="hidden" id="supplier_id" name="supplier_id" value="">
                            <input type="hidden" id="supplier_name" name="supplier_name" value="">
                            <div class="searchable-dropdown" id="supplierDropdown">
                                <input type="text" class="searchable-dropdown-input" id="supplierSearch" placeholder="Search or type new supplier..." autocomplete="off">
                                <span class="searchable-dropdown-arrow" id="supplierArrow"><i class="fas fa-chevron-down"></i></span>
                                <div class="searchable-dropdown-list" id="supplierList" style="display:none;"></div>
                            </div>
                            <div class="help-text" style="font-size:12px;color:#888;margin-top:4px;">Select from list or type a new supplier — it will be auto-created</div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Supplier Email</label>
                            <input type="email" id="supplier_email" name="supplier_email" maxlength="255" placeholder="Auto-filled on selection">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Supplier Phone</label>
                            <input type="text" id="supplier_phone" name="supplier_phone" maxlength="30" placeholder="Auto-filled on selection">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-file-contract"></i> Contract Reference</label>
                            <input type="text" id="contract_reference" name="contract_reference" maxlength="100" placeholder="Enter contract reference">
                        </div>
                    </div>
                </div>

                <!-- Section 6: Attachment & Notes -->
                <div class="data-section mb-30">
                    <div class="section-header">
                        <h2><i class="fas fa-paperclip"></i> Attachment & Notes</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label><i class="fas fa-paperclip"></i> Attachment</label>
                            <input type="file" id="attachment_file" name="attachment_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small style="color:#888;font-size:11px;">PDF, JPG, PNG, DOC (Max 5MB)</small>
                            <div id="currentAttachment" style="display:none; margin-top:6px;">
                                <a href="#" id="attachmentLink" target="_blank" style="color:#0074D9; font-size:12px;"><i class="fas fa-paperclip"></i> <span id="attachmentName"></span></a>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="clearAttachment()" style="margin-left:8px;font-size:11px;"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label><i class="fas fa-sticky-note"></i> Remarks</label>
                            <textarea id="remarks" name="remarks" rows="3" placeholder="Enter any additional remarks"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Custom Fields -->
                <div class="form-section" id="customFieldsSection" style="display:none;">
                    <h3><i class="fas fa-puzzle-piece"></i> Additional Fields</h3>
                    <div class="form-grid" id="subCustomFieldsContainer"></div>
                </div>

                <!-- Submit Button -->
                <div class="form-actions" style="text-align:center;">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> <?php echo $edit_mode ? 'Update' : 'Save'; ?> Subscription
                    </button>
                    <a href="subscriptions.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    /**
     * Tax auto-calculation
     */
    var taxRatesData = [];

    function recalcTax() {
        var selling = parseFloat(document.getElementById('selling_price').value) || 0;
        var taxPct = parseFloat(document.getElementById('tax_pct').value) || 0;
        var taxAmt = selling * taxPct / 100;
        var total = selling - taxAmt;
        document.getElementById('tax_amount').value = taxAmt.toFixed(3);
        document.getElementById('total_amount').value = total.toFixed(3);
    }

    // apply rate from dropdown
    function applyTaxRate() {
        var sel = document.getElementById('tax_rate_select');
        var opt = sel.options[sel.selectedIndex];
        var rate = parseFloat(opt.getAttribute('data-rate')) || 0;
        document.getElementById('tax_pct').value = rate.toFixed(2);
        recalcTax();
    }

    // populate tax rate dropdown
    function populateTaxRates(rates) {
        taxRatesData = rates || [];
        var sel = document.getElementById('tax_rate_select');
        sel.innerHTML = '<option value="" data-rate="0">Manual Entry</option>';
        var defaultId = '';
        rates.forEach(function(r) {
            var opt = document.createElement('option');
            opt.value = r.tax_id;
            opt.setAttribute('data-rate', r.rate);
            opt.textContent = r.name + ' (' + parseFloat(r.rate).toFixed(2) + '%)';
            sel.appendChild(opt);
            if (r.is_default) defaultId = String(r.tax_id);
        });
        // pre-select default if not edit mode
        <?php if (!$edit_mode): ?>
        if (defaultId) {
            sel.value = defaultId;
            applyTaxRate();
        }
        <?php endif; ?>
    }

    document.getElementById('selling_price').addEventListener('input', recalcTax);
    document.getElementById('tax_pct').addEventListener('input', function() {
        // manual pct change = reset dropdown to Manual Entry
        document.getElementById('tax_rate_select').value = '';
        recalcTax();
    });

    // Initial calculation on page load
    recalcTax();

    // auto-calc expiry from starting_date + license_duration
    function calcExpiry() {
        var start = document.getElementById('starting_date').value;
        var dur = document.getElementById('license_duration').value;
        if (!start || !dur || dur === 'Lifetime') return;

        var d = new Date(start);
        var map = {
            '1 Month': [0,1], '2 Months': [0,2], '3 Months': [0,3], '6 Months': [0,6],
            '1 Year': [1,0], '2 Years': [2,0], '3 Years': [3,0], '5 Years': [5,0]
        };
        var m = map[dur];
        if (!m) return;

        d.setFullYear(d.getFullYear() + m[0]);
        d.setMonth(d.getMonth() + m[1]);
        // subtract 1 day so "1 Year" from 2026-01-01 = 2026-12-31
        d.setDate(d.getDate() - 1);

        var yyyy = d.getFullYear();
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        document.getElementById('expiry_date').value = yyyy + '-' + mm + '-' + dd;
    }

    document.getElementById('starting_date').addEventListener('change', calcExpiry);
    document.getElementById('license_duration').addEventListener('change', calcExpiry);

    /**
     * Searchable Dropdown Engine (vanilla JS)
     */
    var dropdownData = { customers: [], products: [], salespersons: [] };
    var productsFullData = [];

    function initSearchableDropdown(config) {
        var searchInput = document.getElementById(config.searchId);
        var hiddenInput = document.getElementById(config.hiddenId);
        var listEl = document.getElementById(config.listId);
        var arrowEl = document.getElementById(config.arrowId);
        var isOpen = false;
        var options = [];
        var selectedValue = '';
        var selectedLabel = '';

        function setOptions(opts) { options = opts; }
        function getOptions() { return options; }

        function renderList(filter) {
            var filtered = options.filter(function(o) {
                return o.label.toLowerCase().indexOf((filter || '').toLowerCase()) !== -1;
            });
            var html = '<div class="searchable-dropdown-item' + (!selectedValue ? ' selected' : '') + '" data-value="">' + (config.placeholder || 'Select...') + '</div>';
            filtered.forEach(function(o) {
                html += '<div class="searchable-dropdown-item' + (selectedValue == o.value ? ' selected' : '') + '" data-value="' + o.value + '" data-label="' + o.label.replace(/"/g, '&quot;') + '">' + o.label + '</div>';
            });
            if (config.allowCreate && filter && filter.length > 0 && !filtered.some(function(o) { return o.label.toLowerCase() === filter.toLowerCase(); })) {
                html += '<div class="searchable-dropdown-item create-new" data-value="__create__" data-label="' + filter.replace(/"/g, '&quot;') + '"><i class="fas fa-plus-circle"></i> Create: "' + filter + '"</div>';
            }
            if (filtered.length === 0 && !config.allowCreate) {
                html += '<div class="searchable-dropdown-item no-results">No results found</div>';
            }
            listEl.innerHTML = html;
        }

        function open() {
            isOpen = true;
            listEl.style.display = 'block';
            arrowEl.classList.add('open');
            renderList(searchInput.value === selectedLabel ? '' : searchInput.value);
        }

        function close() {
            isOpen = false;
            listEl.style.display = 'none';
            arrowEl.classList.remove('open');
            searchInput.value = selectedLabel;
        }

        function selectOption(value, label) {
            selectedValue = value;
            selectedLabel = label;
            hiddenInput.value = value;
            searchInput.value = label;
            if (config.onSelect) config.onSelect(value, label);
            close();
        }

        searchInput.addEventListener('click', function() {
            if (isOpen) { close(); } else { searchInput.value = ''; open(); searchInput.focus(); }
        });

        searchInput.addEventListener('input', function() {
            if (!isOpen) open();
            renderList(this.value);
        });

        listEl.addEventListener('click', function(e) {
            var item = e.target.closest('.searchable-dropdown-item');
            if (!item || item.classList.contains('no-results')) return;
            var val = item.getAttribute('data-value');
            var lbl = item.getAttribute('data-label') || item.textContent;
            if (val === '__create__') {
                selectOption('', lbl);
                if (config.onCreateNew) config.onCreateNew(lbl);
            } else {
                selectOption(val, val === '' ? '' : lbl);
            }
        });

        document.addEventListener('mousedown', function(e) {
            if (!searchInput.parentElement.contains(e.target)) { close(); }
        });

        return {
            setOptions: setOptions,
            getOptions: getOptions,
            setValue: function(val, lbl) { selectOption(val || '', lbl || ''); },
            getValue: function() { return selectedValue; },
            getLabel: function() { return selectedLabel; }
        };
    }

    // Initialize the 3 searchable dropdowns
    var customerDD = initSearchableDropdown({
        searchId: 'customerSearch', hiddenId: 'customer_id', listId: 'customerList', arrowId: 'customerArrow',
        placeholder: '-- Select Customer --', allowCreate: true,
        onSelect: function(val, lbl) {
            document.getElementById('customer_name').value = lbl;
        },
        onCreateNew: function(name) {
            document.getElementById('customer_name').value = name;
        }
    });

    var productDD = initSearchableDropdown({
        searchId: 'productSearch', hiddenId: 'product_id', listId: 'productList', arrowId: 'productArrow',
        placeholder: '-- Select Product --', allowCreate: false,
        onSelect: function(val) {
            if (!val || !productsFullData.length) return;
            var p = productsFullData.find(function(x) { return String(x.product_id) === String(val); });
            if (p && parseFloat(p.selling_price) > 0) {
                document.getElementById('selling_price').value = p.selling_price;
                document.getElementById('purchase_price').value = p.purchase_price;
                recalcTax();
            }
        }
    });

    var salespersonDD = initSearchableDropdown({
        searchId: 'salespersonSearch', hiddenId: 'salesperson_id', listId: 'salespersonList', arrowId: 'salespersonArrow',
        placeholder: '-- Select Sales Person --', allowCreate: false
    });

    var paymentMethodDD = initSearchableDropdown({
        searchId: 'paymentMethodSearch', hiddenId: 'payment_method', listId: 'paymentMethodList', arrowId: 'paymentMethodArrow',
        placeholder: '-- Select Payment Method --', allowCreate: false
    });

    // Supplier dropdown data for auto-fill
    var suppliersFullData = [];

    var supplierDD = initSearchableDropdown({
        searchId: 'supplierSearch', hiddenId: 'supplier_id', listId: 'supplierList', arrowId: 'supplierArrow',
        placeholder: '-- Select or type new Supplier --', allowCreate: true,
        onSelect: function(val, lbl) {
            document.getElementById('supplier_name').value = lbl;
            // Auto-fill email and phone from supplier data
            if (val && suppliersFullData.length > 0) {
                var match = suppliersFullData.find(function(s) { return String(s.supplier_id) === String(val); });
                if (match) {
                    document.getElementById('supplier_email').value = match.email || '';
                    document.getElementById('supplier_phone').value = match.phone || '';
                }
            }
        },
        onCreateNew: function(name) {
            document.getElementById('supplier_name').value = name;
            document.getElementById('supplier_email').value = '';
            document.getElementById('supplier_phone').value = '';
        }
    });

    /**
     * Dropdown population on page load
     */
    $(document).ready(function() {
        $.ajax({
            url: '?action=getFormDropdowns',
            method: 'GET',
            dataType: 'json',
            success: function(r) {
                if (!r.success) return;

                // Populate product dropdown + store full data for price auto-fill
                productsFullData = r.products;
                var catOpts = r.products.map(function(c) { return { value: String(c.product_id), label: c.product_name }; });
                productDD.setOptions(catOpts);

                // Populate salesperson dropdown
                var spOpts = r.salespersons.map(function(s) { return { value: String(s.salesperson_id), label: s.name + ' (' + s.commission_rate + '%)' }; });
                salespersonDD.setOptions(spOpts);
                <?php if ($role === 'salesperson' && $sp_id): ?>
                // auto-set & lock own salesperson
                var _mySpId = '<?php echo $sp_id; ?>';
                var _mySpOpt = spOpts.find(function(o) { return o.value === _mySpId; });
                if (_mySpOpt) { salespersonDD.setValue(_mySpId, _mySpOpt.label); }
                document.getElementById('salespersonSearch').readOnly = true;
                document.getElementById('salespersonSearch').style.background = '#f0f0f0';
                <?php endif; ?>

                // Populate customer dropdown
                if (r.customers) {
                    var custOpts = r.customers.map(function(c) { return { value: String(c.customer_id), label: c.company_name }; });
                    customerDD.setOptions(custOpts);
                }

                // Populate supplier dropdown
                if (r.suppliers) {
                    suppliersFullData = r.suppliers;
                    var suppOpts = r.suppliers.map(function(s) { return { value: String(s.supplier_id), label: s.company_name }; });
                    supplierDD.setOptions(suppOpts);
                }

                // Populate payment method dropdown
                if (r.payment_methods) {
                    var pmOpts = r.payment_methods.map(function(m) { return { value: m, label: m }; });
                    paymentMethodDD.setOptions(pmOpts);
                }

                // Populate tax rates dropdown
                if (r.tax_rates) {
                    populateTaxRates(r.tax_rates);
                }

                // Populate currencies dropdown
                if (r.currencies) {
                    var curSel = document.getElementById('currency_code');
                    curSel.innerHTML = '<option value="">-- System Default --</option>';
                    r.currencies.forEach(function(c) {
                        var opt = document.createElement('option');
                        opt.value = c.code;
                        opt.textContent = c.code + ' - ' + c.name + (c.symbol ? ' (' + c.symbol + ')' : '');
                        if (c.is_default == 1) opt.setAttribute('data-default', '1');
                        curSel.appendChild(opt);
                    });
                    // pre-select default currency
                    var defCur = r.currencies.find(function(c) { return c.is_default == 1; });
                    if (defCur) curSel.value = defCur.code;
                }

                // render custom fields
                if (r.custom_fields && r.custom_fields.length > 0) {
                    renderSubCustomFields(r.custom_fields);
                }

                // If edit mode, load existing data after dropdowns are populated
                <?php if ($edit_mode): ?>
                loadEditData();
                <?php endif; ?>
            },
            error: function(x, s, e) {
                console.error('Failed to load dropdowns:', e);
            }
        });
    });

    /**
     * Edit mode data loading
     */
    // render custom fields for subscription
    function renderSubCustomFields(fields) {
        var container = document.getElementById('subCustomFieldsContainer');
        var section = document.getElementById('customFieldsSection');
        if (!fields || !fields.length) { section.style.display = 'none'; return; }
        section.style.display = '';
        var html = '';
        fields.forEach(function(f) {
            html += '<div class="form-group">';
            html += '<label>' + f.field_label + (f.is_required ? ' *' : '') + '</label>';
            if (f.field_type === 'select') {
                html += '<select id="cf_' + f.field_id + '" name="cf_' + f.field_id + '" ' + (f.is_required ? 'required' : '') + '>';
                html += '<option value="">-- Select --</option>';
                (f.field_options || '').split(',').forEach(function(opt) {
                    opt = opt.trim();
                    html += '<option value="' + opt + '">' + opt + '</option>';
                });
                html += '</select>';
            } else if (f.field_type === 'textarea') {
                html += '<textarea id="cf_' + f.field_id + '" name="cf_' + f.field_id + '" rows="2" ' + (f.is_required ? 'required' : '') + '></textarea>';
            } else {
                html += '<input type="' + f.field_type + '" id="cf_' + f.field_id + '" name="cf_' + f.field_id + '" ' + (f.is_required ? 'required' : '') + '>';
            }
            html += '</div>';
        });
        container.innerHTML = html;
    }

    // load custom field values in edit mode
    function loadSubCustomFieldValues(sl) {
        $.getJSON('?action=getSubCustomFieldValues&sl=' + sl, function(r) {
            if (!r.success || !r.data) return;
            for (var fid in r.data) {
                var el = document.getElementById('cf_' + fid);
                if (el) el.value = r.data[fid];
            }
        });
    }

    function loadEditData() {
        $.ajax({
            url: '?action=getSubscription&sl=<?php echo $edit_sl; ?>',
            method: 'GET',
            dataType: 'json',
            success: function(r) {
                if (!r.success) {
                    Swal.fire({icon:'error', title:'Error', text: r.message}).then(function() {
                        window.location.href = 'subscriptions.php';
                    });
                    return;
                }
                var d = r.data;

                // Set searchable dropdowns
                customerDD.setValue(d.customer_id ? String(d.customer_id) : '', d.customer_name || '');
                document.getElementById('customer_name').value = d.customer_name || '';
                productDD.setValue(d.product_id ? String(d.product_id) : '', '');
                // Find product label from options
                var catOpts = productDD.getOptions();
                var catMatch = catOpts.find(function(o) { return o.value == d.product_id; });
                if (catMatch) productDD.setValue(String(d.product_id), catMatch.label);

                var idEl = document.getElementById('invoice_no');
                idEl.value = d.invoice_no || '';
                idEl.style.color = '#333';
                idEl.style.fontStyle = 'normal';
                document.getElementById('renewal_invoice').value = d.renewal_invoice || '';
                document.getElementById('invoice_date').value = d.invoice_date || '';
                document.getElementById('product_key').value = d.product_key || '';
                document.getElementById('user_qty').value = d.user_qty || 1;
                document.getElementById('license_duration').value = d.license_duration || '';
                document.getElementById('starting_date').value = d.starting_date || '';
                document.getElementById('expiry_date').value = d.expiry_date || '';
                document.getElementById('product_description').value = d.product_description || '';
                document.getElementById('selling_price').value = d.selling_price || 0;
                document.getElementById('purchase_price').value = d.purchase_price || 0;

                // Back-calculate tax_pct from tax_amount and selling_price
                var sp = parseFloat(d.selling_price) || 0;
                var ta = parseFloat(d.tax_amount) || 0;
                var pct = sp > 0 ? (ta / sp * 100) : 0;
                document.getElementById('tax_pct').value = pct.toFixed(2);
                document.getElementById('tax_amount').value = d.tax_amount || 0;
                document.getElementById('total_amount').value = d.total_amount || 0;

                // match tax rate dropdown by rate
                var sel = document.getElementById('tax_rate_select');
                var matched = false;
                for (var i = 0; i < sel.options.length; i++) {
                    var optRate = parseFloat(sel.options[i].getAttribute('data-rate')) || 0;
                    if (Math.abs(optRate - pct) < 0.01 && sel.options[i].value !== '') {
                        sel.selectedIndex = i;
                        matched = true;
                        break;
                    }
                }
                if (!matched) sel.value = '';

                document.getElementById('payment_status').value = d.payment_status || 'Unpaid';
                // Set payment method searchable dropdown
                if (d.payment_method) {
                    paymentMethodDD.setValue(d.payment_method, d.payment_method);
                } else {
                    paymentMethodDD.setValue('', '');
                }
                document.getElementById('payment_date').value = d.payment_date || '';
                document.getElementById('auto_renew').checked = d.auto_renew == 1;
                document.getElementById('priority').value = d.priority || 'Medium';
                // Set salesperson searchable dropdown
                var spOpts = salespersonDD.getOptions();
                var spMatch = spOpts.find(function(o) { return o.value == d.salesperson_id; });
                if (spMatch) salespersonDD.setValue(String(d.salesperson_id), spMatch.label);
                else salespersonDD.setValue('', '');
                // Set supplier searchable dropdown
                if (d.supplier_id) {
                    var suppOpts = supplierDD.getOptions();
                    var suppMatch = suppOpts.find(function(o) { return o.value == d.supplier_id; });
                    if (suppMatch) supplierDD.setValue(String(d.supplier_id), suppMatch.label);
                    else supplierDD.setValue('', d.supplier_name || '');
                } else if (d.supplier_name) {
                    supplierDD.setValue('', d.supplier_name);
                }
                document.getElementById('supplier_name').value = d.supplier_name || '';
                document.getElementById('supplier_email').value = d.supplier_email || '';
                document.getElementById('supplier_phone').value = d.supplier_phone || '';
                document.getElementById('contract_reference').value = d.contract_reference || '';
                if (d.attachment_url) {
                    document.getElementById('currentAttachment').style.display = 'block';
                    document.getElementById('attachmentLink').href = d.attachment_url;
                    document.getElementById('attachmentName').textContent = d.attachment_url.split('/').pop();
                } else {
                    document.getElementById('currentAttachment').style.display = 'none';
                }
                document.getElementById('remarks').value = d.remarks || '';

                // set currency
                if (d.currency_code) {
                    document.getElementById('currency_code').value = d.currency_code;
                }

                // load custom field values
                loadSubCustomFieldValues(<?php echo $edit_sl; ?>);
            },
            error: function(x, s, e) {
                Swal.fire({icon:'error', title:'Error', text:'Failed to load subscription data: ' + e});
            }
        });
    }

    // clear file attachment
    function clearAttachment() {
        document.getElementById('attachment_file').value = '';
        document.getElementById('currentAttachment').style.display = 'none';
    }

    // invoice_no blur - quick dup check
    var editingSl = <?php echo $edit_mode ? $edit_sl : 0; ?>;
    document.getElementById('invoice_no').addEventListener('blur', function() {
        var inv = this.value.trim();
        if (!inv || inv === 'Auto-generated on save') { var w = document.getElementById('invoiceDupWarn'); if(w) w.remove(); return; }
        $.ajax({
            url: '?action=checkDuplicate&invoice_no=' + encodeURIComponent(inv) + '&customer_id=0&product_id=0&starting_date=&expiry_date=&exclude_sl=' + editingSl,
            method: 'GET', dataType: 'json',
            success: function(r) {
                var existing = document.getElementById('invoiceDupWarn');
                if (existing) existing.remove();
                if (r.success && r.warnings.length > 0) {
                    var warn = document.createElement('div');
                    warn.id = 'invoiceDupWarn';
                    warn.style.cssText = 'color:#e74c3c;font-size:12px;margin-top:4px;';
                    warn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + r.warnings[0];
                    document.getElementById('invoice_no').parentNode.appendChild(warn);
                }
            }
        });
    });

    /**
     * Actual save AJAX
     */
    function doSubmit(formData, action) {
        Swal.fire({
            title: 'Saving...',
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
            success: function(r) {
                if (r.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: r.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function() {
                        window.location.href = 'subscriptions.php';
                    });
                } else {
                    Swal.fire({icon:'error', title:'Error', text: r.message});
                }
            },
            error: function(x, s, e) {
                Swal.fire({icon:'error', title:'Error', text:'Connection error: ' + e});
            }
        });
    }

    /**
     * Form submission with dup check
     */
    document.getElementById('subscriptionForm').addEventListener('submit', function(e) {
        e.preventDefault();

        var action = '<?php echo $edit_mode ? "updateSubscription" : "addSubscription"; ?>';
        var formData = new FormData(this);
        formData.append('auto_renew', document.getElementById('auto_renew').checked ? '1' : '0');
        <?php if ($edit_mode): ?>
        formData.append('sl', <?php echo $edit_sl; ?>);
        <?php endif; ?>

        // check duplicates before submit
        var checkParams = new URLSearchParams({
            invoice_no: document.getElementById('invoice_no').value,
            customer_id: document.getElementById('customer_id').value,
            product_id: document.getElementById('product_id').value,
            starting_date: document.getElementById('starting_date').value,
            expiry_date: document.getElementById('expiry_date').value,
            exclude_sl: editingSl || 0
        });

        $.ajax({
            url: '?action=checkDuplicate&' + checkParams.toString(),
            method: 'GET',
            dataType: 'json',
            success: function(r) {
                if (r.success && r.warnings.length > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Duplicate Warning',
                        html: '<ul style="text-align:left;">' + r.warnings.map(function(w){ return '<li>' + w + '</li>'; }).join('') + '</ul>',
                        showCancelButton: true,
                        confirmButtonText: 'Continue Anyway',
                        cancelButtonText: 'Cancel'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            doSubmit(formData, action);
                        }
                    });
                } else {
                    doSubmit(formData, action);
                }
            },
            error: function() {
                doSubmit(formData, action); // on error, proceed anyway
            }
        });
    });
    </script>
</body>
</html>
