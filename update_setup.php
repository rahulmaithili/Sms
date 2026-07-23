<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * Safe DB Update - runs on existing databases, never drops/deletes data
 */
require_once 'config.php';

// admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$logs = [];

// helper
function runSafe($conn, $sql, $desc, &$logs) {
    if ($conn->query($sql)) {
        $logs[] = ['success', $desc];
    } else {
        if (strpos($conn->error, 'Duplicate') !== false || strpos($conn->error, 'already exists') !== false) {
            $logs[] = ['skip', "$desc (already exists)"];
        } else {
            $logs[] = ['error', "$desc: " . $conn->error];
        }
    }
}

// 1. Add salesperson_id to users
$r = $conn->query("SHOW COLUMNS FROM users LIKE 'salesperson_id'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "ALTER TABLE users ADD COLUMN salesperson_id INT DEFAULT NULL AFTER department", "Added salesperson_id to users", $logs);
    runSafe($conn, "ALTER TABLE users ADD INDEX idx_salesperson_id (salesperson_id)", "Added index on users.salesperson_id", $logs);
    runSafe($conn, "ALTER TABLE users ADD CONSTRAINT fk_user_salesperson FOREIGN KEY (salesperson_id) REFERENCES salespersons(salesperson_id) ON DELETE SET NULL", "Added FK users.salesperson_id", $logs);
} else {
    $logs[] = ['skip', 'users.salesperson_id already exists'];
}

// 2. Expand role ENUM to include 'salesperson'
$r = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($r) {
    $col = $r->fetch_assoc();
    if ($col && strpos($col['Type'], 'salesperson') === false) {
        runSafe($conn, "ALTER TABLE users MODIFY COLUMN role ENUM('admin','salesperson','customer') NOT NULL DEFAULT 'customer'", "Added salesperson to role ENUM", $logs);
    } else {
        $logs[] = ['skip', 'role ENUM already has salesperson'];
    }
}

// 3. Add gemini_api_key setting if missing
$r = $conn->query("SELECT setting_id FROM system_settings WHERE setting_key = 'gemini_api_key'");
if ($r && $r->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES ('gemini_api_key', '', 'Google Gemini API key for AI Chat')");
    $stmt->execute();
    $stmt->close();
    $logs[] = ['success', 'Added gemini_api_key setting'];
} else {
    $logs[] = ['skip', 'gemini_api_key setting already exists'];
}

// 4. Product pricing columns
$r = $conn->query("SHOW COLUMNS FROM products LIKE 'selling_price'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "ALTER TABLE products ADD COLUMN selling_price DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER color_code", "Added selling_price to products", $logs);
    runSafe($conn, "ALTER TABLE products ADD COLUMN purchase_price DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER selling_price", "Added purchase_price to products", $logs);
} else {
    $logs[] = ['skip', 'products pricing columns already exist'];
}

// 5. Subscription pause/cancel columns
$r = $conn->query("SHOW COLUMNS FROM subscriptions LIKE 'subscription_status'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "ALTER TABLE subscriptions ADD COLUMN subscription_status ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active' AFTER remarks", "Added subscription_status", $logs);
    runSafe($conn, "ALTER TABLE subscriptions ADD COLUMN paused_at DATETIME DEFAULT NULL AFTER subscription_status", "Added paused_at", $logs);
    runSafe($conn, "ALTER TABLE subscriptions ADD COLUMN resumed_at DATETIME DEFAULT NULL AFTER paused_at", "Added resumed_at", $logs);
    runSafe($conn, "ALTER TABLE subscriptions ADD COLUMN cancelled_at DATETIME DEFAULT NULL AFTER resumed_at", "Added cancelled_at", $logs);
    runSafe($conn, "ALTER TABLE subscriptions ADD COLUMN cancel_reason TEXT DEFAULT NULL AFTER cancelled_at", "Added cancel_reason", $logs);
    runSafe($conn, "ALTER TABLE subscriptions ADD INDEX idx_subscription_status (subscription_status)", "Added index on subscription_status", $logs);
} else {
    $logs[] = ['skip', 'subscription pause/cancel columns already exist'];
}

// 6. Refunds table
$r = $conn->query("SHOW TABLES LIKE 'refunds'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "CREATE TABLE refunds (
        refund_id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL,
        subscription_sl INT NOT NULL,
        amount DECIMAL(12,3) NOT NULL DEFAULT 0.000,
        reason TEXT DEFAULT NULL,
        refunded_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payment_id (payment_id),
        INDEX idx_subscription_sl (subscription_sl),
        CONSTRAINT fk_refund_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE,
        CONSTRAINT fk_refund_sub FOREIGN KEY (subscription_sl) REFERENCES subscriptions(sl) ON DELETE CASCADE,
        CONSTRAINT fk_refund_user FOREIGN KEY (refunded_by) REFERENCES users(user_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Created refunds table", $logs);
} else {
    $logs[] = ['skip', 'refunds table already exists'];
}

// 7. Customer role + customer_id in users
$r = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($r) {
    $col = $r->fetch_assoc();
    if ($col && strpos($col['Type'], 'customer') === false) {
        runSafe($conn, "ALTER TABLE users MODIFY COLUMN role ENUM('admin','salesperson','customer') NOT NULL DEFAULT 'customer'", "Added customer to role ENUM", $logs);
    } else {
        $logs[] = ['skip', 'role ENUM already has customer'];
    }
}

$r = $conn->query("SHOW COLUMNS FROM users LIKE 'customer_id'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "ALTER TABLE users ADD COLUMN customer_id INT DEFAULT NULL AFTER salesperson_id", "Added customer_id to users", $logs);
    runSafe($conn, "ALTER TABLE users ADD INDEX idx_customer_id (customer_id)", "Added index on users.customer_id", $logs);
    runSafe($conn, "ALTER TABLE users ADD CONSTRAINT fk_user_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL", "Added FK users.customer_id", $logs);
} else {
    $logs[] = ['skip', 'users.customer_id already exists'];
}

// 8. Unpaid reminder settings
$reminder_settings = [
    ['unpaid_reminder_enabled', '0', 'Enable unpaid invoice email reminders'],
    ['unpaid_reminder_days', '30', 'Days after invoice to send unpaid reminder'],
];
foreach ($reminder_settings as $s) {
    $r = $conn->query("SELECT setting_id FROM system_settings WHERE setting_key = '{$s[0]}'");
    if ($r && $r->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $s[0], $s[1], $s[2]);
        $stmt->execute(); $stmt->close();
        $logs[] = ['success', "Added {$s[0]} setting"];
    } else {
        $logs[] = ['skip', "{$s[0]} setting already exists"];
    }
}

// 9. Tax rates table
$r = $conn->query("SHOW TABLES LIKE 'tax_rates'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "CREATE TABLE tax_rates (
        tax_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Created tax_rates table", $logs);
    $conn->query("INSERT INTO tax_rates (name, rate, is_default) VALUES ('No Tax', 0.00, 1), ('GST 5%', 5.00, 0), ('GST 12%', 12.00, 0), ('GST 18%', 18.00, 0), ('VAT 15%', 15.00, 0)");
    $logs[] = ['success', 'Seeded default tax rates'];
} else {
    $logs[] = ['skip', 'tax_rates table already exists'];
}

// 10. Documents table
$r = $conn->query("SHOW TABLES LIKE 'documents'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "CREATE TABLE documents (
        document_id INT AUTO_INCREMENT PRIMARY KEY,
        subscription_sl INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_size INT NOT NULL DEFAULT 0,
        file_type VARCHAR(100) DEFAULT NULL,
        uploaded_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_subscription_sl (subscription_sl),
        CONSTRAINT fk_doc_sub FOREIGN KEY (subscription_sl) REFERENCES subscriptions(sl) ON DELETE CASCADE,
        CONSTRAINT fk_doc_user FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Created documents table", $logs);
} else {
    $logs[] = ['skip', 'documents table already exists'];
}

// 11. Custom fields tables
$r = $conn->query("SHOW TABLES LIKE 'custom_fields'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "CREATE TABLE custom_fields (
        field_id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type ENUM('customer','subscription') NOT NULL,
        field_name VARCHAR(50) NOT NULL,
        field_label VARCHAR(100) NOT NULL,
        field_type ENUM('text','number','date','select','textarea') NOT NULL DEFAULT 'text',
        field_options TEXT DEFAULT NULL,
        is_required TINYINT(1) NOT NULL DEFAULT 0,
        display_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_entity_type (entity_type),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Created custom_fields table", $logs);
    runSafe($conn, "CREATE TABLE custom_field_values (
        value_id INT AUTO_INCREMENT PRIMARY KEY,
        field_id INT NOT NULL,
        entity_type ENUM('customer','subscription') NOT NULL,
        entity_id INT NOT NULL,
        field_value TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_field_entity (field_id, entity_type, entity_id),
        INDEX idx_entity (entity_type, entity_id),
        CONSTRAINT fk_cfv_field FOREIGN KEY (field_id) REFERENCES custom_fields(field_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Created custom_field_values table", $logs);
} else {
    $logs[] = ['skip', 'custom_fields tables already exist'];
}

// 12. Currencies table
$r = $conn->query("SHOW TABLES LIKE 'currencies'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "CREATE TABLE currencies (
        currency_id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(3) NOT NULL UNIQUE, name VARCHAR(50) NOT NULL,
        symbol VARCHAR(5) NOT NULL DEFAULT '', exchange_rate DECIMAL(12,6) NOT NULL DEFAULT 1.000000,
        is_default TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_code (code), INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Created currencies table", $logs);
    $conn->query("INSERT INTO currencies (code,name,symbol,exchange_rate,is_default) VALUES ('INR','Indian Rupee','₹',1.000000,1),('USD','US Dollar','\$',0.011900,0),('EUR','Euro','€',0.010900,0),('GBP','British Pound','£',0.009400,0),('AED','UAE Dirham','د.إ',0.043700,0)");
    $logs[] = ['success', 'Seeded default currencies'];
} else { $logs[] = ['skip', 'currencies table already exists']; }

// 13. currency_code on subscriptions
$r = $conn->query("SHOW COLUMNS FROM subscriptions LIKE 'currency_code'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "ALTER TABLE subscriptions ADD COLUMN currency_code VARCHAR(3) DEFAULT NULL AFTER total_amount", "Added currency_code to subscriptions", $logs);
} else { $logs[] = ['skip', 'subscriptions.currency_code already exists']; }

// 14. Customer feedback table
$r = $conn->query("SHOW TABLES LIKE 'customer_feedback'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "CREATE TABLE customer_feedback (
        feedback_id INT AUTO_INCREMENT PRIMARY KEY, subscription_sl INT DEFAULT NULL, customer_id INT DEFAULT NULL,
        rating TINYINT NOT NULL, comment TEXT DEFAULT NULL, created_by INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_customer_id (customer_id), INDEX idx_subscription_sl (subscription_sl), INDEX idx_rating (rating)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Created customer_feedback table", $logs);
} else { $logs[] = ['skip', 'customer_feedback table already exists']; }

// 15. Onboarding setting
$r = $conn->query("SELECT setting_id FROM system_settings WHERE setting_key = 'onboarding_complete'");
if ($r && $r->num_rows === 0) {
    $conn->query("INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES ('onboarding_complete', '0', 'Whether first-time setup wizard has been completed')");
    $logs[] = ['success', 'Added onboarding_complete setting'];
} else { $logs[] = ['skip', 'onboarding_complete setting already exists']; }

// 16. Add download_url column to products
$r = $conn->query("SHOW COLUMNS FROM products LIKE 'download_url'");
if ($r && $r->num_rows === 0) {
    runSafe($conn, "ALTER TABLE products ADD COLUMN download_url VARCHAR(500) DEFAULT NULL AFTER purchase_price", "Added download_url to products table", $logs);
} else {
    $logs[] = ['skip', 'products.download_url already exists'];
}

logActivity($_SESSION['user_id'], $_SESSION['username'], 'DB Update', 'Ran update_setup.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Update</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
</head>
<body>
    <div class="setup-wrapper">
    <div class="setup-container">
        <h2><i class="fas fa-database"></i> Database Update</h2>
        <p class="subtitle">Safe update — no data is deleted</p>
        <hr>
        <?php foreach ($logs as $log): ?>
            <?php if ($log[0] === 'success'): ?>
                <div class="log-item log-success"><i class="fas fa-check-circle"></i> <?php echo $log[1]; ?></div>
            <?php elseif ($log[0] === 'skip'): ?>
                <div class="log-item log-info"><i class="fas fa-info-circle"></i> <?php echo $log[1]; ?></div>
            <?php else: ?>
                <div class="log-item log-error"><i class="fas fa-times-circle"></i> <?php echo $log[1]; ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
        <br>
        <div class="log-item log-success"><i class="fas fa-check-circle"></i> <strong>Update complete!</strong></div>
        <br>
        <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    </div>
</body>
</html>
