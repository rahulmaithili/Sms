<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */

require_once 'config.php';

// Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

$username  = $_SESSION['username'];
$role      = $_SESSION['role'];
$user_id   = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'customers';

// CSV template download (before JSON header)
if (isset($_GET['action']) && $_GET['action'] === 'downloadCustomerTemplate') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customers_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['company_name','contact_person','email','phone','address','city','country','notes']);
    fputcsv($out, ['ABC Corporation','John Smith','john@abc.com','04231234567','123 Main Street','Karachi','Pakistan','Key client']);
    fputcsv($out, ['XYZ Trading','Jane Doe','jane@xyz.com','04239876543','456 Market Road','Lahore','Pakistan','']);
    fclose($out);
    exit();
}

// save custom field values for a customer
function saveCustomFieldValues($conn, $entity_type, $entity_id, $post) {
    $stmt = $conn->prepare(
        "INSERT INTO custom_field_values (field_id, entity_type, entity_id, field_value)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE field_value=VALUES(field_value), updated_at=NOW()"
    );
    foreach ($post as $key => $val) {
        if (strpos($key, 'cf_') !== 0) continue;
        $fid = intval(substr($key, 3));
        if ($fid <= 0) continue;
        $fval = trim($val);
        $stmt->bind_param("isis", $fid, $entity_type, $entity_id, $fval);
        $stmt->execute();
    }
    $stmt->close();
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            // ── 1. getCustomers ──────────────────────────────────────────────
            case 'getCustomers':
                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "SELECT customer_id, company_name, contact_person, email, phone,
                            city, country, address, notes, is_active, created_at
                     FROM customers
                     ORDER BY company_name ASC"
                );
                $stmt->execute();
                $result = $stmt->get_result();

                $customers = [];
                while ($row = $result->fetch_assoc()) {
                    $customers[] = [
                        'customer_id'    => (int)$row['customer_id'],
                        'company_name'   => $row['company_name'],
                        'contact_person' => $row['contact_person'] ?? '',
                        'email'          => $row['email'] ?? '',
                        'phone'          => $row['phone'] ?? '',
                        'city'           => $row['city'] ?? '',
                        'country'        => $row['country'] ?? '',
                        'address'        => $row['address'] ?? '',
                        'notes'          => $row['notes'] ?? '',
                        'is_active'      => (bool)$row['is_active'],
                        'created_at'     => date('M d, Y', strtotime($row['created_at']))
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $customers]);
                exit();

            // ── 2. addCustomer ───────────────────────────────────────────────
            case 'addCustomer':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $company_name   = isset($_POST['company_name'])   ? trim($_POST['company_name'])   : '';
                $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
                $email          = isset($_POST['email'])          ? trim($_POST['email'])          : '';
                $phone          = isset($_POST['phone'])          ? trim($_POST['phone'])          : '';
                $address        = isset($_POST['address'])        ? trim($_POST['address'])        : '';
                $city           = isset($_POST['city'])           ? trim($_POST['city'])           : '';
                $country        = isset($_POST['country'])        ? trim($_POST['country'])        : '';
                $notes          = isset($_POST['notes'])          ? trim($_POST['notes'])          : '';

                if (empty($company_name)) {
                    echo json_encode(['success' => false, 'message' => 'Customer name is required']);
                    exit();
                }

                if (empty($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Contact number is required']);
                    exit();
                }

                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    exit();
                }

                // Nullable values
                $contactVal = !empty($contact_person) ? $contact_person : null;
                $emailVal   = !empty($email)          ? $email          : null;
                $phoneVal   = !empty($phone)          ? $phone          : null;
                $addressVal = !empty($address)        ? $address        : null;
                $cityVal    = !empty($city)           ? $city           : null;
                $countryVal = !empty($country)        ? $country        : null;
                $notesVal   = !empty($notes)          ? $notes          : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "INSERT INTO customers
                        (company_name, contact_person, email, phone, address, city, country, notes, is_active, added_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)"
                );
                $stmt->bind_param(
                    "ssssssssi",
                    $company_name, $contactVal, $emailVal, $phoneVal,
                    $addressVal, $cityVal, $countryVal, $notesVal, $user_id
                );

                if ($stmt->execute()) {
                    $new_cid = $conn->insert_id;
                    logActivity($user_id, $username, 'Customer Created', "Created customer: $company_name");
                    $stmt->close();

                    // save custom field values
                    saveCustomFieldValues($conn, 'customer', $new_cid, $_POST);

                    echo json_encode(['success' => true, 'message' => 'Customer added successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Customer already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add customer']);
                    }
                }
                exit();

            // ── 3. updateCustomer ────────────────────────────────────────────
            case 'updateCustomer':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $customer_id    = isset($_POST['customer_id'])    ? intval($_POST['customer_id'])    : 0;
                $company_name   = isset($_POST['company_name'])   ? trim($_POST['company_name'])     : '';
                $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person'])   : '';
                $email          = isset($_POST['email'])          ? trim($_POST['email'])             : '';
                $phone          = isset($_POST['phone'])          ? trim($_POST['phone'])             : '';
                $address        = isset($_POST['address'])        ? trim($_POST['address'])           : '';
                $city           = isset($_POST['city'])           ? trim($_POST['city'])              : '';
                $country        = isset($_POST['country'])        ? trim($_POST['country'])           : '';
                $notes          = isset($_POST['notes'])          ? trim($_POST['notes'])             : '';
                $is_active      = isset($_POST['is_active'])      ? intval($_POST['is_active'])       : 1;

                if ($customer_id <= 0 || empty($company_name)) {
                    echo json_encode(['success' => false, 'message' => 'Customer name is required']);
                    exit();
                }

                if (empty($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Contact number is required']);
                    exit();
                }

                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    exit();
                }

                $contactVal = !empty($contact_person) ? $contact_person : null;
                $emailVal   = !empty($email)          ? $email          : null;
                $phoneVal   = !empty($phone)          ? $phone          : null;
                $addressVal = !empty($address)        ? $address        : null;
                $cityVal    = !empty($city)           ? $city           : null;
                $countryVal = !empty($country)        ? $country        : null;
                $notesVal   = !empty($notes)          ? $notes          : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "UPDATE customers
                     SET company_name=?, contact_person=?, email=?, phone=?,
                         address=?, city=?, country=?, notes=?, is_active=?
                     WHERE customer_id=?"
                );
                $stmt->bind_param(
                    "ssssssssii",
                    $company_name, $contactVal, $emailVal, $phoneVal,
                    $addressVal, $cityVal, $countryVal, $notesVal,
                    $is_active, $customer_id
                );

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Customer Updated', "Updated customer: $company_name");
                    $stmt->close();

                    // save custom field values
                    saveCustomFieldValues($conn, 'customer', $customer_id, $_POST);

                    echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Customer name already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update customer']);
                    }
                }
                exit();

            // ── 4. toggleActive ──────────────────────────────────────────────
            case 'toggleActive':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $customer_id = isset($_POST['id'])        ? intval($_POST['id'])        : 0;
                $is_active   = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

                if ($customer_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE customers SET is_active=? WHERE customer_id=?");
                $stmt->bind_param("ii", $is_active, $customer_id);

                if ($stmt->execute()) {
                    $label = $is_active ? 'Customer Activated' : 'Customer Deactivated';
                    logActivity($user_id, $username, $label, "Changed active status for customer ID $customer_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_active ? 'Customer activated' : 'Customer deactivated']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                exit();

            // ── 5. deleteCustomer ────────────────────────────────────────────
            case 'deleteCustomer':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $customer_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                if ($customer_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch company name before deletion for log
                $stmt = $conn->prepare("SELECT company_name FROM customers WHERE customer_id=?");
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $deletedName = '';
                if ($result->num_rows > 0) {
                    $deletedName = $result->fetch_assoc()['company_name'];
                }
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id=?");
                $stmt->bind_param("i", $customer_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Customer Deleted', "Deleted customer: $deletedName");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete customer']);
                }
                exit();

            // ── 6. viewCustomerSubscriptions ─────────────────────────────────
            case 'viewCustomerSubscriptions':
                $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

                if ($customer_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare(
                    "SELECT s.sl, s.invoice_no, s.customer_name, p.product_name,
                            s.expiry_date, s.total_amount, s.payment_status
                     FROM subscriptions s
                     LEFT JOIN products p ON s.product_id = p.product_id
                     WHERE s.customer_id = ?
                     ORDER BY s.sl DESC"
                );
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $subscriptions = [];
                while ($row = $result->fetch_assoc()) {
                    $status = getSubscriptionStatus($row['expiry_date']);
                    $subscriptions[] = [
                        'sl'             => (int)$row['sl'],
                        'invoice_no'     => $row['invoice_no'] ?? '',
                        'customer_name'  => $row['customer_name'],
                        'product_name'   => $row['product_name'] ?? 'N/A',
                        'expiry_date'    => $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '-',
                        'total_amount'   => number_format((float)$row['total_amount'], 2),
                        'payment_status' => $row['payment_status'] ?? '',
                        'status_label'   => $status['label'],
                        'status_class'   => $status['status_class'] ?? $status['class']
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $subscriptions]);
                exit();

            case 'getCustomerLedger':
                $cid = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
                if ($cid <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid customer ID']); exit(); }

                $conn = getDBConnection();
                // get subs
                $stmt = $conn->prepare(
                    "SELECT s.sl, s.invoice_no, s.customer_name, s.invoice_date, s.expiry_date,
                            s.selling_price, s.total_amount, s.payment_status,
                            COALESCE(p2.product_name, s.product_description, 'N/A') AS product_name,
                            IFNULL((SELECT SUM(py.amount) FROM payments py WHERE py.subscription_sl = s.sl), 0) AS paid_amount
                     FROM subscriptions s
                     LEFT JOIN products p2 ON s.product_id = p2.product_id
                     WHERE s.customer_id = ?
                     ORDER BY s.sl DESC"
                );
                $stmt->bind_param("i", $cid);
                $stmt->execute();
                $res = $stmt->get_result();

                $subs = [];
                while ($r = $res->fetch_assoc()) {
                    $status = getSubscriptionStatus($r['expiry_date']);
                    $subs[] = [
                        'sl'             => (int)$r['sl'],
                        'invoice_no'     => $r['invoice_no'] ?? '',
                        'customer_name'  => $r['customer_name'],
                        'product_name'   => $r['product_name'],
                        'invoice_date'   => $r['invoice_date'] ? date('M d, Y', strtotime($r['invoice_date'])) : '-',
                        'expiry_date'    => $r['expiry_date'] ? date('M d, Y', strtotime($r['expiry_date'])) : '-',
                        'total_amount'   => round((float)$r['total_amount'], 3),
                        'paid_amount'    => round((float)$r['paid_amount'], 3),
                        'balance'        => round((float)$r['total_amount'] - (float)$r['paid_amount'], 3),
                        'payment_status' => $r['payment_status'] ?? 'Unpaid',
                        'status_label'   => $status['label']
                    ];
                }
                $stmt->close();

                // get payments grouped by sub
                $stmt2 = $conn->prepare(
                    "SELECT py.payment_id, py.subscription_sl, py.amount, py.payment_method,
                            py.payment_date, py.reference_no, py.notes, py.created_at,
                            u.full_name AS added_by_name
                     FROM payments py
                     LEFT JOIN users u ON py.added_by = u.user_id
                     WHERE py.subscription_sl IN (SELECT sl FROM subscriptions WHERE customer_id = ?)
                     ORDER BY py.payment_date DESC"
                );
                $stmt2->bind_param("i", $cid);
                $stmt2->execute();
                $pRes = $stmt2->get_result();

                $payments = [];
                while ($p = $pRes->fetch_assoc()) {
                    $payments[] = [
                        'payment_id'      => (int)$p['payment_id'],
                        'subscription_sl' => (int)$p['subscription_sl'],
                        'amount'          => round((float)$p['amount'], 3),
                        'payment_method'  => $p['payment_method'] ?? '',
                        'payment_date'    => $p['payment_date'] ? date('M d, Y', strtotime($p['payment_date'])) : '',
                        'reference_no'    => $p['reference_no'] ?? '',
                        'notes'           => $p['notes'] ?? '',
                        'added_by_name'   => $p['added_by_name'] ?? ''
                    ];
                }
                $stmt2->close();

                echo json_encode([
                    'success'  => true,
                    'subs'     => $subs,
                    'payments' => $payments,
                    'currency' => getCurrency()
                ]);
                exit();

            // bulk email to selected customers
            case 'sendBulkEmail':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }
                if ($role !== 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Admin only']);
                    exit();
                }

                $ids_raw = isset($_POST['customer_ids']) ? trim($_POST['customer_ids']) : '';
                $subject = isset($_POST['subject'])      ? trim($_POST['subject'])      : '';
                $body    = isset($_POST['body'])          ? trim($_POST['body'])         : '';

                if (empty($subject) || empty($body)) {
                    echo json_encode(['success' => false, 'message' => 'Subject and body are required']);
                    exit();
                }
                if (empty($ids_raw)) {
                    echo json_encode(['success' => false, 'message' => 'No customers selected']);
                    exit();
                }

                // sanitize ids
                $ids = array_filter(array_map('intval', explode(',', $ids_raw)), function($v) { return $v > 0; });
                if (empty($ids)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid customer IDs']);
                    exit();
                }

                $conn = getDBConnection();
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));

                $stmt = $conn->prepare("SELECT customer_id, company_name, contact_person, email FROM customers WHERE customer_id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $res = $stmt->get_result();

                $customers = [];
                while ($r = $res->fetch_assoc()) $customers[] = $r;
                $stmt->close();

                // get latest sub sl per customer for notification_logs FK
                $slStmt = $conn->prepare("SELECT customer_id, MAX(sl) as latest_sl FROM subscriptions WHERE customer_id IN ($placeholders) GROUP BY customer_id");
                $slStmt->bind_param($types, ...$ids);
                $slStmt->execute();
                $slRes = $slStmt->get_result();
                $slMap = [];
                while ($sr = $slRes->fetch_assoc()) $slMap[(int)$sr['customer_id']] = (int)$sr['latest_sl'];
                $slStmt->close();

                $sent = 0; $failed = 0;
                $bodyPreview = mb_substr(strip_tags($body), 0, 200);

                foreach ($customers as $c) {
                    if (empty($c['email'])) { $failed++; continue; }

                    $result = sendEmail($c['email'], $subject, $body);
                    $status = $result['success'] ? 'Sent' : 'Failed';
                    $errMsg = $result['success'] ? null : ($result['message'] ?? 'Send failed');

                    if ($result['success']) { $sent++; } else { $failed++; }

                    // log to notification_logs if customer has a subscription
                    $subSl = $slMap[(int)$c['customer_id']] ?? null;
                    if ($subSl) {
                        $recipName = $c['contact_person'] ?: $c['company_name'];
                        logNotification($subSl, $c['email'], 'user', $recipName, 'custom', 0, $subject, $bodyPreview, $status, $errMsg, 'manual', $user_id);
                    }
                }

                $total = count($customers);
                logActivity($user_id, $username, 'Bulk Email Sent', "Bulk Email Sent to $sent customers (failed: $failed, total: $total)");

                echo json_encode(['success' => true, 'sent' => $sent, 'failed' => $failed, 'total' => $total]);
                exit();

            // custom fields for customer entity
            case 'getCustomerCustomFields':
                $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
                $conn = getDBConnection();

                $stmt = $conn->prepare(
                    "SELECT field_id, field_name, field_label, field_type, field_options, is_required, display_order
                     FROM custom_fields
                     WHERE entity_type='customer' AND is_active=1
                     ORDER BY display_order ASC"
                );
                $stmt->execute();
                $res = $stmt->get_result();

                $fields = [];
                $fieldIds = [];
                while ($row = $res->fetch_assoc()) {
                    $fields[] = [
                        'field_id'      => (int)$row['field_id'],
                        'field_name'    => $row['field_name'],
                        'field_label'   => $row['field_label'],
                        'field_type'    => $row['field_type'],
                        'field_options' => $row['field_options'] ?? '',
                        'is_required'   => (bool)$row['is_required'],
                        'value'         => ''
                    ];
                    $fieldIds[] = (int)$row['field_id'];
                }
                $stmt->close();

                // load values if customer_id provided
                if ($customer_id > 0 && count($fieldIds) > 0) {
                    $placeholders = implode(',', array_fill(0, count($fieldIds), '?'));
                    $types = str_repeat('i', count($fieldIds)) . 'si';
                    $params = $fieldIds;
                    $params[] = 'customer';
                    $params[] = $customer_id;

                    $stmt = $conn->prepare(
                        "SELECT field_id, field_value FROM custom_field_values
                         WHERE field_id IN ($placeholders) AND entity_type=? AND entity_id=?"
                    );
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $vRes = $stmt->get_result();

                    // build lookup
                    $valMap = [];
                    while ($v = $vRes->fetch_assoc()) {
                        $valMap[(int)$v['field_id']] = $v['field_value'];
                    }
                    $stmt->close();

                    // merge values
                    foreach ($fields as &$f) {
                        if (isset($valMap[$f['field_id']])) {
                            $f['value'] = $valMap[$f['field_id']];
                        }
                    }
                    unset($f);
                }

                echo json_encode(['success' => true, 'data' => $fields]);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("customers.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// CSV import handler
if (isset($_POST['action']) && $_POST['action'] === 'importCustomers') {
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

    // read header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'Empty CSV file']);
        exit();
    }

    // normalize headers
    $header = array_map(function($h) { return strtolower(trim(str_replace(["\xEF\xBB\xBF", '"'], '', $h))); }, $header);
    $required = ['company_name'];
    foreach ($required as $r) {
        if (!in_array($r, $header)) {
            fclose($handle);
            echo json_encode(['success' => false, 'message' => "Missing required column: $r"]);
            exit();
        }
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare(
        "INSERT INTO customers (company_name, contact_person, email, phone, address, city, country, notes, added_by, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );

    $imported = 0; $skipped = 0; $errors = [];
    $lineNum = 1;

    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;
        if (count($row) < count($header)) {
            $row = array_pad($row, count($header), '');
        }
        $data = array_combine($header, array_slice($row, 0, count($header)));

        $cname = trim($data['company_name'] ?? '');
        if (empty($cname)) { $skipped++; continue; }

        $cp   = !empty($data['contact_person']) ? trim($data['contact_person']) : null;
        $em   = !empty($data['email'])          ? trim($data['email'])          : null;
        $ph   = !empty($data['phone'])          ? trim($data['phone'])          : null;
        $addr = !empty($data['address'])        ? trim($data['address'])        : null;
        $ct   = !empty($data['city'])           ? trim($data['city'])           : null;
        $co   = !empty($data['country'])        ? trim($data['country'])        : null;
        $nt   = !empty($data['notes'])          ? trim($data['notes'])          : null;

        if ($em && !filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row $lineNum: Invalid email '$em'";
            $skipped++;
            continue;
        }

        $stmt->bind_param("ssssssssi", $cname, $cp, $em, $ph, $addr, $ct, $co, $nt, $user_id);
        if ($stmt->execute()) {
            $imported++;
        } else {
            if ($conn->errno === 1062) {
                $errors[] = "Row $lineNum: '$cname' already exists";
            } else {
                $errors[] = "Row $lineNum: " . $stmt->error;
            }
            $skipped++;
        }
    }
    $stmt->close();
    fclose($handle);

    if ($imported > 0) {
        logActivity($user_id, $username, 'Customers Imported', "Imported $imported customers from CSV");
    }

    $msg = "$imported customer(s) imported successfully.";
    if ($skipped > 0) $msg .= " $skipped row(s) skipped.";

    echo json_encode(['success' => true, 'message' => $msg, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]);
    exit();
}

// If we reach here, render the HTML page
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
    <title>Customers - <?php echo htmlspecialchars($branding['site_name']); ?></title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <style>
        /* Toggle switch */
        .toggle { appearance: none; width: 44px; height: 24px; border-radius: 24px; background: #ccc; position: relative; cursor: pointer; transition: background .3s; border: none; outline: none; vertical-align: middle; }
        .toggle:checked { background: #0074D9; }
        .toggle::before { content: ""; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform .3s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
        .toggle:checked::before { transform: translateX(20px); }
        .swal-no-padding { padding: 0 !important; }
        .swal-no-padding .swal2-html-container { padding: 0 !important; margin: 0 !important; }
        .swal-no-padding .swal2-close { color: #fff !important; opacity: .8; z-index: 10; }
        .swal-no-padding .swal2-close:hover { opacity: 1; }

        /* Eye icon for view subscriptions */
        .action-icon.view-icon { color: #0074D9; }
        .action-icon.view-icon:hover { background: rgba(0,116,217,0.1); transform: scale(1.15); }

        /* Payment status badges */
        .payment-badge { padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; display: inline-block; }
        .payment-paid     { background: #d4edda; color: #155724; }
        .payment-unpaid   { background: #f8d7da; color: #721c24; }
        .payment-partial  { background: #fff3cd; color: #856404; }
        .payment-refunded { background: #e2e3e5; color: #383d41; }

        /* Subscription status badges inside Swal */
        .sub-status { padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-active         { background: #d4edda; color: #155724; }
        .status-expired        { background: #f8d7da; color: #721c24; }
        .status-expiring-soon  { background: #fff3cd; color: #856404; }
        .status-expiring-today { background: #ffe0b2; color: #e65100; }
        .status-unknown        { background: #e2e3e5; color: #383d41; }

        /* Subscriptions modal table inside Swal */
        .subs-table-wrapper { overflow-x: auto; margin-top: 10px; }
        .subs-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .subs-table th { background: #001f3f; color: #fff; padding: 10px 12px; text-align: left; font-weight: 600; white-space: nowrap; }
        .subs-table td { padding: 10px 12px; border-bottom: 1px solid #eee; color: #555; white-space: nowrap; }
        .subs-table tbody tr:hover { background: #f9f9f9; }
        .subs-table .no-data td { text-align: center; color: #999; font-style: italic; padding: 20px; }

        /* initially-hidden — prevents flash of unstyled content */
        .initially-hidden { visibility: hidden; }
    </style>
</head>
<body class="initially-hidden">
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span>Customers</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-address-book"></i> Customers</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Customers</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-info" id="bulkEmailBtn" style="display:none;" onclick="openBulkEmailModal()">
                            <i class="fas fa-envelope"></i> Email Selected (<span id="bulkEmailCount">0</span>)
                        </button>
                        <button class="btn btn-primary" onclick="loadCustomers()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Customer
                        </button>
                        <?php if ($role === 'admin'): ?>
                        <button class="btn btn-info" onclick="openImportModal()">
                            <i class="fas fa-file-import"></i> Import CSV
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

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
                            <label><i class="fas fa-user"></i> Customer Name</label>
                            <input type="text" id="filterCompany" class="filter-input" placeholder="Search customer...">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-city"></i> City</label>
                            <input type="text" id="filterCity" class="filter-input" placeholder="Search city...">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-toggle-on"></i> Status</label>
                            <select id="filterStatus" class="filter-input">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                </div>
                <div class="table-responsive">
                    <table id="customersTable" class="display table-full-width"></table>
                </div>
            </div>

        </div>
    </div>

    <!-- Customer Modal -->
    <div class="modal-overlay" id="customerModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add Customer</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="customerForm">
                    <input type="hidden" id="customerId" name="customer_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Customer Name *</label>
                            <input type="text" id="formCompanyName" name="company_name" required placeholder="Enter customer name">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Contact Person</label>
                            <input type="text" id="formContactPerson" name="contact_person" placeholder="Enter contact person name">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="formEmail" name="email" placeholder="Enter email address">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Contact Number *</label>
                            <input type="text" id="formPhone" name="phone" required placeholder="Enter contact number">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-city"></i> City</label>
                            <input type="text" id="formCity" name="city" placeholder="Enter city">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-globe"></i> Country</label>
                            <input type="text" id="formCountry" name="country" placeholder="Enter country">
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea id="formAddress" name="address" rows="2" placeholder="Enter full address"></textarea>
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label><i class="fas fa-sticky-note"></i> Notes</label>
                            <textarea id="formNotes" name="notes" rows="2" placeholder="Any additional notes..."></textarea>
                        </div>

                        <div class="form-group" id="activeGroup" style="display:none;">
                            <label><i class="fas fa-toggle-on"></i> Active Status</label>
                            <select id="formIsActive" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Custom Fields -->
                    <div id="customFieldsContainer" class="form-grid" style="margin-top:0;"></div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Email Modal -->
    <div class="modal-overlay" id="bulkEmailModal">
        <div class="modal" onclick="event.stopPropagation()" style="max-width:700px;">
            <div class="modal-header">
                <h3><i class="fas fa-envelope"></i> Send Bulk Email</h3>
                <button class="close-btn" onclick="closeBulkEmailModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Recipients</label>
                    <div id="emailRecipients" style="background:#f5f5f5;padding:10px;border-radius:4px;max-height:100px;overflow-y:auto;font-size:13px;"></div>
                </div>
                <div class="form-group">
                    <label>Subject *</label>
                    <input type="text" id="emailSubject" required>
                </div>
                <div class="form-group">
                    <label>Message *</label>
                    <textarea id="emailBody" rows="8" style="font-family:inherit;" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeBulkEmailModal()">Cancel</button>
                <button class="btn btn-primary" onclick="sendBulkEmail()"><i class="fas fa-paper-plane"></i> Send</button>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div class="modal-overlay" id="importModal">
        <div class="modal" onclick="event.stopPropagation()" style="max-width:660px;">
            <div class="modal-header">
                <h3><i class="fas fa-file-import"></i> Import Customers</h3>
                <button class="close-btn" onclick="closeImportModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p style="color:var(--text-secondary);font-size:14px;margin-bottom:16px;">Upload a CSV file with customer data. Only <strong>company_name</strong> is required &mdash; all other fields are optional. Duplicate company names will be skipped.</p>

                <div style="margin-bottom:20px;">
                    <a href="?action=downloadCustomerTemplate" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
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
                                <td style="text-align:left;font-weight:600;color:#dc3545;">company_name *</td>
                                <td>Text</td>
                                <td style="text-align:left;">Required, must be unique</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">contact_person</td>
                                <td>Text</td>
                                <td style="text-align:left;">Primary contact name</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">email</td>
                                <td>Text</td>
                                <td style="text-align:left;">Contact email address</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">phone</td>
                                <td>Text</td>
                                <td style="text-align:left;">Phone number</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">address</td>
                                <td>Text</td>
                                <td style="text-align:left;">Street address</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">city</td>
                                <td>Text</td>
                                <td style="text-align:left;">City name</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;">country</td>
                                <td>Text</td>
                                <td style="text-align:left;">Country name</td>
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

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
    // Lazy-load PDF/Excel export dependencies on first use
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
        let customersTable;
        let isEditMode    = false;
        let customersData = [];
        var _brandName = <?php echo json_encode($branding['site_name']); ?>;
        var _brandLogo = <?php echo json_encode($branding['site_logo']); ?>;
        var _brandCopy = <?php echo json_encode($branding['copyright_text']); ?>;

        // Reveal body once sidebar/theme JS has run
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.remove('initially-hidden');
            loadCustomers();
        });

        // ── Load & render table ───────────────────────────────────────────────
        function loadCustomers() {
            $.ajax({
                url: '?action=getCustomers',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        customersData = response.data;
                        $('#filtersSection').show().removeClass('initially-hidden');
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to load customers'
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

        function initializeDataTable(data) {
            if (customersTable) {
                customersTable.destroy();
                $('#customersTable').empty();
            }

            setTimeout(function() {
                customersTable = $('#customersTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        {
                            data: null,
                            title: '<input type="checkbox" id="selectAllCust">',
                            orderable: false,
                            searchable: false,
                            width: '30px',
                            render: function(data, type, row) {
                                return '<input type="checkbox" class="cust-check" value="' + row.customer_id + '">';
                            }
                        },
                        { data: 'customer_id',    title: 'ID',             width: '50px' },
                        { data: 'company_name',   title: 'Customer Name' },
                        { data: 'contact_person', title: 'Contact Person', defaultContent: '-' },
                        {
                            data: 'email',
                            title: 'Email',
                            defaultContent: '-',
                            render: function(data) {
                                if (!data) return '-';
                                return '<a href="mailto:' + data + '" style="color:#0074D9;text-decoration:none;">' + data + '</a>';
                            }
                        },
                        { data: 'phone', title: 'Phone', defaultContent: '-' },
                        { data: 'city',  title: 'City',  defaultContent: '-' },
                        {
                            data: 'is_active',
                            title: 'Active',
                            render: function(data, type, row) {
                                var checked = data ? 'checked="checked"' : '';
                                return '<input type="checkbox" ' + checked + ' class="toggle" onchange="toggleActive(' + row.customer_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        { data: 'created_at', title: 'Created' },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                var rowJson = JSON.stringify(row).replace(/'/g, "\\'");
                                return '<button class="action-icon" title="Ledger" onclick="openLedger(' + row.customer_id + ', \'' + row.company_name.replace(/'/g, "\\'") + '\')" style="color:#7c3aed;"><i class="fas fa-book"></i></button> ' +
                                        '<button class="action-icon edit-icon" title="Edit" onclick=\'editCustomer(' + rowJson + ')\'>' +
                                            '<i class="fas fa-edit"></i>' +
                                        '</button> ' +
                                        '<button class="action-icon delete-icon" title="Delete" onclick="deleteCustomer(' + row.customer_id + ')">' +
                                            '<i class="fas fa-trash"></i>' +
                                        '</button>';
                            }
                        }
                    ],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: [1, 2, 3, 4, 5, 6, 8] }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: [1, 2, 3, 4, 5, 6, 8] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: [1, 2, 3, 4, 5, 6, 8] }
                        }
                    ],
                    order: [[2, 'asc']]
                });

                // select all checkbox
                $('#customersTable').off('change', '#selectAllCust').on('change', '#selectAllCust', function() {
                    var checked = this.checked;
                    $('#customersTable .cust-check').prop('checked', checked);
                    updateBulkEmailBtn();
                });

                // individual checkbox
                $('#customersTable').off('change', '.cust-check').on('change', '.cust-check', function() {
                    updateBulkEmailBtn();
                    // uncheck header if not all selected
                    var all = $('#customersTable .cust-check').length;
                    var sel = $('#customersTable .cust-check:checked').length;
                    $('#selectAllCust').prop('checked', all > 0 && sel === all);
                });

                // Custom filters
                $('#filterCompany').on('keyup', applyFilters);
                $('#filterCity').on('keyup', applyFilters);
                $('#filterStatus').on('change', applyFilters);
            }, 100);
        }

        // ── Custom filter logic ───────────────────────────────────────────────
        function applyFilters() {
            if (!customersTable) return;

            $.fn.dataTable.ext.search = [];

            var companyFilter = document.getElementById('filterCompany').value.toLowerCase();
            var cityFilter    = document.getElementById('filterCity').value.toLowerCase();
            var statusFilter  = document.getElementById('filterStatus').value;

            $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
                var row = customersData[dataIndex];
                if (!row) return true;

                if (companyFilter && row.company_name.toLowerCase().indexOf(companyFilter) === -1) return false;
                if (cityFilter    && (row.city || '').toLowerCase().indexOf(cityFilter) === -1)    return false;
                if (statusFilter === 'active'   && !row.is_active) return false;
                if (statusFilter === 'inactive' &&  row.is_active) return false;

                return true;
            });

            customersTable.draw();
        }

        function clearFilters() {
            document.getElementById('filterCompany').value = '';
            document.getElementById('filterCity').value    = '';
            document.getElementById('filterStatus').value  = '';

            if (customersTable) {
                $.fn.dataTable.ext.search = [];
                customersTable.columns().search('').draw();
            }
        }

        // ── Modal helpers ─────────────────────────────────────────────────────
        // load custom fields into form
        function loadCustomFields(customerId) {
            var url = '?action=getCustomerCustomFields' + (customerId ? '&customer_id=' + customerId : '');
            $.getJSON(url, function(r) {
                if (!r.success || !r.data.length) {
                    document.getElementById('customFieldsContainer').innerHTML = '';
                    return;
                }
                var html = '';
                r.data.forEach(function(f) {
                    html += '<div class="form-group">';
                    html += '<label><i class="fas fa-puzzle-piece"></i> ' + escapeHtml(f.field_label) + (f.is_required ? ' *' : '') + '</label>';
                    var val = f.value || '';
                    var req = f.is_required ? ' required' : '';
                    if (f.field_type === 'select') {
                        html += '<select name="cf_' + f.field_id + '"' + req + '>';
                        html += '<option value="">-- Select --</option>';
                        (f.field_options || '').split(',').forEach(function(opt) {
                            opt = opt.trim();
                            if (!opt) return;
                            html += '<option value="' + escapeHtml(opt) + '"' + (val === opt ? ' selected' : '') + '>' + escapeHtml(opt) + '</option>';
                        });
                        html += '</select>';
                    } else if (f.field_type === 'textarea') {
                        html += '<textarea name="cf_' + f.field_id + '" rows="2"' + req + '>' + escapeHtml(val) + '</textarea>';
                    } else {
                        html += '<input type="' + f.field_type + '" name="cf_' + f.field_id + '" value="' + escapeHtml(val) + '"' + req + '>';
                    }
                    html += '</div>';
                });
                document.getElementById('customFieldsContainer').innerHTML = html;
            });
        }

        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add Customer';
            document.getElementById('customerForm').reset();
            document.getElementById('customerId').value = '';
            document.getElementById('activeGroup').style.display = 'none';
            document.getElementById('customerModal').classList.add('active');
            loadCustomFields(0);
        }

        function editCustomer(cust) {
            isEditMode = true;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Customer';
            document.getElementById('customerId').value          = cust.customer_id;
            document.getElementById('formCompanyName').value     = cust.company_name;
            document.getElementById('formContactPerson').value   = cust.contact_person || '';
            document.getElementById('formEmail').value           = cust.email           || '';
            document.getElementById('formPhone').value           = cust.phone           || '';
            document.getElementById('formCity').value            = cust.city            || '';
            document.getElementById('formCountry').value         = cust.country         || '';
            document.getElementById('formAddress').value         = cust.address         || '';
            document.getElementById('formNotes').value           = cust.notes           || '';
            document.getElementById('formIsActive').value        = cust.is_active ? '1' : '0';
            document.getElementById('activeGroup').style.display = '';
            document.getElementById('customerModal').classList.add('active');
            loadCustomFields(cust.customer_id);
        }

        function closeModal() {
            document.getElementById('customerModal').classList.remove('active');
            document.getElementById('customerForm').reset();
            document.getElementById('customFieldsContainer').innerHTML = '';
        }

        document.getElementById('customerModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // ── Form submit ───────────────────────────────────────────────────────
        document.getElementById('customerForm').addEventListener('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var action   = isEditMode ? 'updateCustomer' : 'addCustomer';

            Swal.fire({
                title: 'Processing...',
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
                            timer: 2000,
                            showConfirmButton: false
                        });
                        closeModal();
                        setTimeout(function() { loadCustomers(); }, 100);
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

        // ── Toggle active ─────────────────────────────────────────────────────
        function toggleActive(customerId, isActive) {
            var formData = new FormData();
            formData.append('id',        customerId);
            formData.append('is_active', isActive);

            $.ajax({
                url: '?action=toggleActive',
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
                        setTimeout(function() { loadCustomers(); }, 100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        setTimeout(function() { loadCustomers(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        }

        // ── Delete customer ───────────────────────────────────────────────────
        function deleteCustomer(customerId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Customer?',
                text: 'This action cannot be undone. All linked data may also be affected.',
                showCancelButton: true,
                confirmButtonColor: '#ea4335',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    var formData = new FormData();
                    formData.append('id', customerId);

                    $.ajax({
                        url: '?action=deleteCustomer',
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
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                setTimeout(function() { loadCustomers(); }, 100);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                        }
                    });
                }
            });
        }

        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(s));
            return d.innerHTML;
        }

        // ── CSV Import ───────────────────────────────────────────────────────
        function openImportModal() {
            document.getElementById('csvFileInput').value = '';
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
            var fileInput = document.getElementById('csvFileInput');
            if (!fileInput.files.length) {
                Swal.fire({ icon: 'warning', text: 'Please select a CSV file' });
                return;
            }

            var formData = new FormData();
            formData.append('action', 'importCustomers');
            formData.append('csv_file', fileInput.files[0]);

            var btn = document.getElementById('importBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

            $.ajax({
                url: 'customers.php',
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
                        loadCustomers();
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

        // ── Customer Ledger ──────────────────────────────────────────────────
        var _ledgerCustId = 0, _ledgerCustName = '';

        function openLedger(custId, custName) {
            _ledgerCustId = custId;
            _ledgerCustName = custName;
            Swal.fire({
                title: '', html: '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#0074D9;"></i><p style="margin-top:10px;color:#666;">Loading ledger...</p></div>',
                width: 960, showConfirmButton: false, showCloseButton: true, padding: 0,
                customClass: { popup: 'swal-no-padding' },
                didOpen: function() { loadLedger(); }
            });
        }

        function loadLedger() {
            $.ajax({
                url: '?action=getCustomerLedger&customer_id=' + _ledgerCustId,
                dataType: 'json',
                success: function(r) {
                    if (!r.success) { Swal.update({ html: '<p style="color:#dc3545;padding:20px;">Failed to load.</p>' }); return; }
                    renderLedger(r.subs, r.payments, r.currency);
                },
                error: function() { Swal.update({ html: '<p style="color:#dc3545;padding:20px;">Connection error.</p>' }); }
            });
        }

        function renderLedger(subs, payments, cur) {
            var safeName = escapeHtml(_ledgerCustName);
            var totPurchase = 0, totPaid = 0;
            subs.forEach(function(s) { totPurchase += s.total_amount; totPaid += s.paid_amount; });
            var totBalance = totPurchase - totPaid;
            var balColor = totBalance > 0 ? '#dc3545' : '#28a745';

            var html = '';
            // header
            html += '<div style="background:linear-gradient(135deg,#001f3f 0%,#003366 100%);color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;">';
            html += '<i class="fas fa-book" style="font-size:20px;color:#7c3aed;"></i>';
            html += '<div><div style="font-size:16px;font-weight:700;">' + safeName + '</div><div style="font-size:11px;opacity:.7;">Customer Ledger</div></div>';
            html += '</div>';

            // 3 stat cards
            html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-bottom:1px solid #e9ecef;">';
            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:#0074D9;">' + cur + ' ' + totPurchase.toFixed(0) + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Total Purchase</div></div>';
            html += '<div style="padding:14px;text-align:center;border-right:1px solid #e9ecef;"><div style="font-size:22px;font-weight:700;color:#28a745;">' + cur + ' ' + totPaid.toFixed(0) + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Total Paid</div></div>';
            html += '<div style="padding:14px;text-align:center;"><div style="font-size:22px;font-weight:700;color:' + balColor + ';">' + cur + ' ' + totBalance.toFixed(0) + '</div><div style="font-size:11px;color:#888;margin-top:2px;">Balance Due</div></div>';
            html += '</div>';

            // tabs
            html += '<div style="display:flex;border-bottom:2px solid #e9ecef;" id="ledgerTabs">';
            html += '<button onclick="switchLedgerTab(\'subs\')" id="tabSubs" style="flex:1;padding:10px;border:none;background:#fff;cursor:pointer;font-size:13px;font-weight:600;color:#001f3f;border-bottom:2px solid #0074D9;margin-bottom:-2px;">Subscriptions (' + subs.length + ')</button>';
            html += '<button onclick="switchLedgerTab(\'pays\')" id="tabPays" style="flex:1;padding:10px;border:none;background:#fff;cursor:pointer;font-size:13px;font-weight:600;color:#888;border-bottom:2px solid transparent;margin-bottom:-2px;">Payments (' + payments.length + ')</button>';
            html += '<button onclick="switchLedgerTab(\'add\')" id="tabAdd" style="flex:1;padding:10px;border:none;background:#fff;cursor:pointer;font-size:13px;font-weight:600;color:#888;border-bottom:2px solid transparent;margin-bottom:-2px;"><i class="fas fa-plus-circle"></i> Record Payment</button>';
            html += '</div>';

            // ── Subscriptions tab
            html += '<div id="ledgerSubs" style="padding:14px;max-height:300px;overflow-y:auto;">';
            if (subs.length === 0) {
                html += '<div style="text-align:center;color:#888;padding:40px;"><i class="fas fa-inbox" style="font-size:36px;color:#ddd;display:block;margin-bottom:10px;"></i>No subscriptions</div>';
            } else {
                html += '<div class="about-table-wrapper" style="margin:0;border-radius:4px;overflow:hidden;border:1px solid #e0e0e0;">';
                html += '<table class="about-roles-table" style="font-size:12px;margin:0;">';
                html += '<thead><tr><th style="text-align:left;">Invoice</th><th>Product</th><th>Date</th><th>Expiry</th><th style="text-align:right;">Amount</th><th style="text-align:right;">Paid</th><th style="text-align:right;">Balance</th><th>Status</th></tr></thead>';
                html += '<tbody>';
                var payColors = {'Paid':'#28a745','Unpaid':'#dc3545','Partial':'#e67e00','Refunded':'#0074D9'};
                subs.forEach(function(s) {
                    var bc = s.balance > 0 ? '#dc3545' : '#28a745';
                    var pyc = payColors[s.payment_status] || '#888';
                    html += '<tr>';
                    html += '<td style="text-align:left;font-weight:600;">' + escapeHtml(s.invoice_no) + '</td>';
                    html += '<td>' + escapeHtml(s.product_name) + '</td>';
                    html += '<td>' + s.invoice_date + '</td>';
                    html += '<td>' + s.expiry_date + '</td>';
                    html += '<td style="text-align:right;">' + s.total_amount.toFixed(0) + '</td>';
                    html += '<td style="text-align:right;color:#28a745;font-weight:600;">' + s.paid_amount.toFixed(0) + '</td>';
                    html += '<td style="text-align:right;color:' + bc + ';font-weight:700;">' + s.balance.toFixed(0) + '</td>';
                    html += '<td><span class="role-badge" style="background:' + pyc + ';color:#fff;">' + escapeHtml(s.payment_status) + '</span></td>';
                    html += '</tr>';
                });
                // total row
                html += '<tr style="background:#f0f4f8;font-weight:700;border-top:2px solid #001f3f;">';
                html += '<td colspan="4" style="text-align:left;">TOTAL</td>';
                html += '<td style="text-align:right;">' + totPurchase.toFixed(0) + '</td>';
                html += '<td style="text-align:right;color:#28a745;">' + totPaid.toFixed(0) + '</td>';
                html += '<td style="text-align:right;color:' + balColor + ';">' + totBalance.toFixed(0) + '</td>';
                html += '<td></td></tr>';
                html += '</tbody></table></div>';
            }
            html += '</div>';

            // ── Payments tab (hidden)
            html += '<div id="ledgerPays" style="padding:14px;max-height:300px;overflow-y:auto;display:none;">';
            if (payments.length === 0) {
                html += '<div style="text-align:center;color:#888;padding:40px;"><i class="fas fa-inbox" style="font-size:36px;color:#ddd;display:block;margin-bottom:10px;"></i>No payments recorded</div>';
            } else {
                html += '<div class="about-table-wrapper" style="margin:0;border-radius:4px;overflow:hidden;border:1px solid #e0e0e0;">';
                html += '<table class="about-roles-table" style="font-size:12px;margin:0;">';
                html += '<thead><tr><th>Date</th><th style="text-align:right;">Amount</th><th>Method</th><th>Reference</th><th>Invoice</th><th>Notes</th><th>By</th></tr></thead>';
                html += '<tbody>';
                // build invoice lookup
                var invMap = {};
                subs.forEach(function(s) { invMap[s.sl] = s.invoice_no; });
                payments.forEach(function(p) {
                    html += '<tr>';
                    html += '<td>' + escapeHtml(p.payment_date) + '</td>';
                    html += '<td style="text-align:right;font-weight:700;color:#28a745;">' + p.amount.toFixed(0) + '</td>';
                    html += '<td>' + escapeHtml(p.payment_method || '-') + '</td>';
                    html += '<td>' + escapeHtml(p.reference_no || '-') + '</td>';
                    html += '<td style="font-weight:600;">' + escapeHtml(invMap[p.subscription_sl] || '#' + p.subscription_sl) + '</td>';
                    html += '<td>' + escapeHtml(p.notes || '-') + '</td>';
                    html += '<td>' + escapeHtml(p.added_by_name || '-') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            }
            html += '</div>';

            // ── Record Payment tab (hidden)
            html += '<div id="ledgerAdd" style="padding:20px;display:none;">';
            if (subs.length === 0) {
                html += '<div style="text-align:center;color:#888;padding:30px;">No subscriptions to pay against</div>';
            } else {
                html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">';
                // subscription select
                html += '<div style="grid-column:1/-1;"><label style="font-weight:600;display:block;margin-bottom:4px;">Subscription</label>';
                html += '<select id="ledgerPaySl" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;">';
                subs.forEach(function(s) {
                    if (s.balance > 0) {
                        html += '<option value="' + s.sl + '">' + escapeHtml(s.invoice_no) + ' — ' + escapeHtml(s.product_name) + ' (Balance: ' + cur + ' ' + s.balance.toFixed(0) + ')</option>';
                    }
                });
                if (subs.every(function(s){return s.balance<=0;})) {
                    html += '<option value="">All paid — no balance due</option>';
                }
                html += '</select></div>';
                // amount
                html += '<div><label style="font-weight:600;display:block;margin-bottom:4px;">Amount *</label>';
                html += '<input type="number" id="ledgerPayAmt" step="0.001" min="0" placeholder="0.00" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;"></div>';
                // date
                html += '<div><label style="font-weight:600;display:block;margin-bottom:4px;">Date *</label>';
                html += '<input type="date" id="ledgerPayDate" value="' + new Date().toISOString().split('T')[0] + '" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;"></div>';
                // method
                html += '<div><label style="font-weight:600;display:block;margin-bottom:4px;">Method</label>';
                html += '<select id="ledgerPayMethod" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;">';
                html += '<option value="">Select</option><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option><option value="Credit Card">Credit Card</option><option value="Online">Online</option><option value="Cheque">Cheque</option><option value="Other">Other</option>';
                html += '</select></div>';
                // reference
                html += '<div><label style="font-weight:600;display:block;margin-bottom:4px;">Reference No</label>';
                html += '<input type="text" id="ledgerPayRef" placeholder="TXN-001" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;"></div>';
                // notes
                html += '<div><label style="font-weight:600;display:block;margin-bottom:4px;">Notes</label>';
                html += '<input type="text" id="ledgerPayNotes" placeholder="Optional" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;"></div>';
                // submit
                html += '<div style="grid-column:1/-1;text-align:right;margin-top:4px;">';
                html += '<button onclick="submitLedgerPayment()" style="padding:10px 28px;background:#28a745;color:#fff;border:none;border-radius:4px;font-size:14px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save Payment</button>';
                html += '</div></div>';
            }
            html += '</div>';

            // footer
            html += '<div style="padding:12px 20px;border-top:1px solid #e9ecef;background:#f8f9fa;display:flex;align-items:center;justify-content:space-between;">';
            html += '<span style="font-size:12px;color:#888;">Balance: <strong style="color:' + balColor + ';">' + cur + ' ' + totBalance.toFixed(0) + '</strong></span>';
            html += '<div style="display:flex;gap:8px;">';
            html += '<button onclick="thermalPrintLedger()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#e67e00;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:12px;font-weight:600;" title="Thermal / Receipt Print"><i class="fas fa-receipt"></i> Thermal</button>';
            html += '<button onclick="printLedger()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#001f3f;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:12px;font-weight:600;"><i class="fas fa-print"></i> A4 Print</button>';
            html += '</div></div>';

            Swal.update({ html: html });
        }

        function switchLedgerTab(tab) {
            document.getElementById('ledgerSubs').style.display = tab === 'subs' ? '' : 'none';
            document.getElementById('ledgerPays').style.display = tab === 'pays' ? '' : 'none';
            document.getElementById('ledgerAdd').style.display  = tab === 'add'  ? '' : 'none';
            var tabs = { subs: 'tabSubs', pays: 'tabPays', add: 'tabAdd' };
            Object.keys(tabs).forEach(function(k) {
                var el = document.getElementById(tabs[k]);
                el.style.color = k === tab ? '#001f3f' : '#888';
                el.style.borderBottomColor = k === tab ? '#0074D9' : 'transparent';
            });
        }

        function submitLedgerPayment() {
            var sl = document.getElementById('ledgerPaySl').value;
            var amt = document.getElementById('ledgerPayAmt').value;
            var dt = document.getElementById('ledgerPayDate').value;
            var method = document.getElementById('ledgerPayMethod').value;
            var ref = document.getElementById('ledgerPayRef').value;
            var notes = document.getElementById('ledgerPayNotes').value;

            if (!sl) { Swal.showValidationMessage('No subscription selected'); return; }
            if (!amt || parseFloat(amt) <= 0) { Swal.showValidationMessage('Enter a valid amount'); return; }
            if (!dt) { Swal.showValidationMessage('Select a date'); return; }

            $.ajax({
                url: 'payments.php?action=addPayment',
                method: 'POST',
                data: { subscription_sl: sl, amount: amt, payment_date: dt, payment_method: method, reference_no: ref, notes: notes },
                dataType: 'json',
                beforeSend: function() {
                    Swal.update({ html: '<div style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#0074D9;"></i><p style="margin-top:10px;color:#666;">Saving payment...</p></div>' });
                },
                success: function(r) {
                    if (r.success) {
                        // reload ledger
                        loadLedger();
                        // brief toast
                        var toast = document.createElement('div');
                        toast.innerHTML = '<i class="fas fa-check-circle"></i> Payment saved!';
                        toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#28a745;color:#fff;padding:12px 20px;border-radius:4px;font-size:13px;font-weight:600;z-index:99999;display:flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);';
                        document.body.appendChild(toast);
                        setTimeout(function() { toast.remove(); }, 2500);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message });
                    }
                },
                error: function() { Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error' }); }
            });
        }

        function printLedger() {
            var el = document.querySelector('.swal2-html-container');
            if (!el) return;
            var w = window.open('', '_blank', 'width=900,height=600');
            var s = '';
            s += '<!DOCTYPE html><html><head><title>Ledger - ' + escapeHtml(_ledgerCustName) + '</title>';
            s += '<style>';
            s += 'body{font-family:Arial,sans-serif;margin:20px;color:#333;}';
            s += '.header{display:flex;align-items:center;gap:14px;margin-bottom:15px;padding-bottom:12px;border-bottom:2px solid #001f3f;}';
            s += '.header img{width:50px;height:50px;border-radius:50%;object-fit:cover;}';
            s += '.header h1{font-size:18px;color:#001f3f;margin:0;}.header p{margin:0;font-size:12px;color:#666;}';
            s += '.cust-name{font-size:15px;font-weight:700;color:#001f3f;margin:10px 0 4px;}';
            s += '.stats{display:flex;gap:30px;margin:8px 0 14px;font-size:13px;}';
            s += '.stats span{font-weight:700;}';
            s += 'h3{font-size:13px;color:#001f3f;margin:18px 0 6px;border-bottom:1px solid #ccc;padding-bottom:4px;}';
            s += 'table{width:100%;border-collapse:collapse;font-size:11px;}';
            s += 'th{background:#001f3f;color:#fff;padding:6px 8px;text-align:left;}';
            s += 'td{padding:5px 8px;border-bottom:1px solid #e0e0e0;}';
            s += 'tr:nth-child(even){background:#f8f9fa;}';
            s += '.footer{margin-top:20px;padding-top:10px;border-top:1px solid #ccc;font-size:10px;color:#888;text-align:center;}';
            s += '@media print{body{margin:10px;}}';
            s += '</style></head><body>';

            // branded header
            s += '<div class="header">';
            s += '<img src="' + escapeHtml(_brandLogo) + '" onerror="this.style.display=\'none\'">';
            s += '<div><h1>' + escapeHtml(_brandName) + '</h1><p>Customer Ledger</p></div>';
            s += '</div>';

            s += '<div class="cust-name">' + escapeHtml(_ledgerCustName) + '</div>';

            // stats
            var statsEl = el.querySelector('[style*="grid-template-columns: repeat(3"]');
            if (statsEl) s += statsEl.outerHTML;

            // subs table
            var subsT = document.getElementById('ledgerSubs');
            if (subsT) { var t = subsT.querySelector('.about-roles-table'); if (t) { s += '<h3>Subscriptions</h3>' + t.outerHTML; } }

            // payments table
            var paysT = document.getElementById('ledgerPays');
            if (paysT) { var t2 = paysT.querySelector('.about-roles-table'); if (t2) { s += '<h3>Payments</h3>' + t2.outerHTML; } }

            s += '<div class="footer">' + _brandCopy + ' &mdash; Generated: ' + new Date().toLocaleDateString() + '</div>';
            s += '</body></html>';

            w.document.write(s);
            w.document.close(); w.focus();
            setTimeout(function() { w.print(); }, 300);
        }

        // ── Bulk Email ───────────────────────────────────────────────────
        function updateBulkEmailBtn() {
            var count = $('#customersTable .cust-check:checked').length;
            $('#bulkEmailCount').text(count);
            $('#bulkEmailBtn').toggle(count > 0);
        }

        function getSelectedCustomers() {
            var selected = [];
            $('#customersTable .cust-check:checked').each(function() {
                var id = parseInt(this.value);
                var cust = customersData.find(function(c) { return c.customer_id === id; });
                if (cust) selected.push(cust);
            });
            return selected;
        }

        function openBulkEmailModal() {
            var selected = getSelectedCustomers();
            if (selected.length === 0) return;

            // show recipients
            var recipHtml = selected.map(function(c) {
                var name = escapeHtml(c.company_name);
                var email = c.email ? ' &lt;' + escapeHtml(c.email) + '&gt;' : ' <span style="color:#dc3545;">(no email)</span>';
                return '<div>' + name + email + '</div>';
            }).join('');
            document.getElementById('emailRecipients').innerHTML = recipHtml;
            document.getElementById('emailSubject').value = '';
            document.getElementById('emailBody').value = '';
            document.getElementById('bulkEmailModal').classList.add('active');
        }

        function closeBulkEmailModal() {
            document.getElementById('bulkEmailModal').classList.remove('active');
        }

        document.getElementById('bulkEmailModal').addEventListener('click', function(e) {
            if (e.target === this) closeBulkEmailModal();
        });

        function sendBulkEmail() {
            var subject = document.getElementById('emailSubject').value.trim();
            var body = document.getElementById('emailBody').value.trim();

            if (!subject) { Swal.fire({ icon: 'warning', text: 'Subject is required' }); return; }
            if (!body) { Swal.fire({ icon: 'warning', text: 'Message body is required' }); return; }

            var selected = getSelectedCustomers();
            var ids = selected.map(function(c) { return c.customer_id; }).join(',');

            // no email filter check
            var noEmail = selected.filter(function(c) { return !c.email; });
            if (noEmail.length === selected.length) {
                Swal.fire({ icon: 'error', title: 'No Emails', text: 'None of the selected customers have email addresses.' });
                return;
            }

            closeBulkEmailModal();

            Swal.fire({
                title: 'Sending Emails...',
                html: 'Sending to ' + selected.length + ' customer(s)...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            var formData = new FormData();
            formData.append('customer_ids', ids);
            formData.append('subject', subject);
            formData.append('body', body);

            $.ajax({
                url: '?action=sendBulkEmail',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(r) {
                    if (r.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Bulk Email Complete',
                            html: '<div style="font-size:14px;text-align:left;padding:0 10px;">' +
                                '<p><strong>' + r.sent + '</strong> sent successfully</p>' +
                                (r.failed > 0 ? '<p style="color:#dc3545;"><strong>' + r.failed + '</strong> failed</p>' : '') +
                                '<p style="color:#888;font-size:12px;">Total recipients: ' + r.total + '</p></div>'
                        });
                        // uncheck all
                        $('#customersTable .cust-check, #selectAllCust').prop('checked', false);
                        updateBulkEmailBtn();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: r.message });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Connection error: ' + error });
                }
            });
        }

        function thermalPrintLedger() {
            var el = document.querySelector('.swal2-html-container');
            if (!el) return;
            var w = window.open('', '_blank', 'width=350,height=600');
            var s = '';
            s += '<!DOCTYPE html><html><head><title>Receipt</title>';
            s += '<style>';
            s += '@page{size:80mm auto;margin:0;}';
            s += 'body{font-family:"Courier New",monospace;width:72mm;margin:4mm auto;color:#000;font-size:11px;line-height:1.4;}';
            s += '.center{text-align:center;}';
            s += '.logo{width:40px;height:40px;border-radius:50%;margin:0 auto 4px;display:block;}';
            s += '.brand{font-size:14px;font-weight:700;margin:2px 0;}';
            s += '.sub{font-size:9px;color:#666;}';
            s += '.line{border-top:1px dashed #000;margin:6px 0;}';
            s += '.bold{font-weight:700;}';
            s += '.row{display:flex;justify-content:space-between;}';
            s += '.row-right{text-align:right;}';
            s += 'table{width:100%;border-collapse:collapse;font-size:10px;margin:4px 0;}';
            s += 'th{text-align:left;font-size:9px;border-bottom:1px solid #000;padding:2px 0;}';
            s += 'td{padding:2px 0;border-bottom:1px dotted #ccc;}';
            s += 'td:last-child,th:last-child{text-align:right;}';
            s += '.footer{text-align:center;font-size:8px;color:#666;margin-top:8px;}';
            s += '@media print{body{margin:0 auto;}}';
            s += '</style></head><body>';

            // header
            s += '<div class="center">';
            s += '<img src="' + escapeHtml(_brandLogo) + '" class="logo" onerror="this.style.display=\'none\'">';
            s += '<div class="brand">' + escapeHtml(_brandName) + '</div>';
            s += '<div class="sub">CUSTOMER LEDGER</div>';
            s += '</div>';
            s += '<div class="line"></div>';

            // customer
            s += '<div class="bold">' + escapeHtml(_ledgerCustName) + '</div>';
            s += '<div class="sub">Date: ' + new Date().toLocaleDateString() + '</div>';
            s += '<div class="line"></div>';

            // get data from subs table
            var subsT = document.getElementById('ledgerSubs');
            var totPurchase = 0, totPaid = 0;
            if (subsT) {
                var rows = subsT.querySelectorAll('.about-roles-table tbody tr');
                s += '<div class="bold" style="font-size:10px;margin-bottom:2px;">SUBSCRIPTIONS</div>';
                s += '<table><thead><tr><th>Invoice</th><th>Amount</th><th>Paid</th><th>Bal</th></tr></thead><tbody>';
                rows.forEach(function(tr) {
                    var tds = tr.querySelectorAll('td');
                    if (tds.length < 7) return;
                    var inv = tds[0].textContent.trim();
                    var amt = tds[4].textContent.trim();
                    var paid = tds[5].textContent.trim();
                    var bal = tds[6].textContent.trim();
                    // check if TOTAL row
                    if (inv === 'TOTAL') {
                        s += '<tr style="border-top:1px solid #000;font-weight:700;"><td>TOTAL</td><td>' + amt + '</td><td>' + paid + '</td><td>' + bal + '</td></tr>';
                    } else {
                        s += '<tr><td>' + escapeHtml(inv) + '</td><td>' + amt + '</td><td>' + paid + '</td><td>' + bal + '</td></tr>';
                    }
                });
                s += '</tbody></table>';
            }

            s += '<div class="line"></div>';

            // payments
            var paysT = document.getElementById('ledgerPays');
            if (paysT) {
                var pRows = paysT.querySelectorAll('.about-roles-table tbody tr');
                if (pRows.length > 0) {
                    s += '<div class="bold" style="font-size:10px;margin-bottom:2px;">PAYMENTS</div>';
                    s += '<table><thead><tr><th>Date</th><th>Method</th><th>Amt</th></tr></thead><tbody>';
                    pRows.forEach(function(tr) {
                        var tds = tr.querySelectorAll('td');
                        if (tds.length < 3) return;
                        s += '<tr><td>' + tds[0].textContent.trim() + '</td><td>' + tds[2].textContent.trim() + '</td><td>' + tds[1].textContent.trim() + '</td></tr>';
                    });
                    s += '</tbody></table>';
                }
            }

            s += '<div class="line"></div>';

            // summary from stats
            var statsEl = el.querySelector('[style*="grid-template-columns: repeat(3"]');
            if (statsEl) {
                var statDivs = statsEl.querySelectorAll('[style*="text-align:center"]');
                if (statDivs.length >= 3) {
                    var purchase = statDivs[0].querySelector('div').textContent.trim();
                    var paid = statDivs[1].querySelector('div').textContent.trim();
                    var balance = statDivs[2].querySelector('div').textContent.trim();
                    s += '<div class="row"><span>Total Purchase:</span><span class="bold">' + purchase + '</span></div>';
                    s += '<div class="row"><span>Total Paid:</span><span class="bold">' + paid + '</span></div>';
                    s += '<div class="line" style="border-top:1px solid #000;"></div>';
                    s += '<div class="row" style="font-size:13px;"><span class="bold">BALANCE DUE:</span><span class="bold">' + balance + '</span></div>';
                }
            }

            s += '<div class="line"></div>';
            s += '<div class="footer">' + _brandCopy + '</div>';
            s += '<div class="footer">Thank you for your business!</div>';

            s += '</body></html>';
            w.document.write(s);
            w.document.close(); w.focus();
            setTimeout(function() { w.print(); }, 300);
        }

    </script>
</body>
</html>
