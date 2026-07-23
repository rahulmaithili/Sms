<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * Database Setup - Drops all tables and recreates fresh with demo data
 */

// Don't use config.php here to avoid session/header issues during setup
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

define('DB_HOST', 'localhost');
define('DB_NAME', 'subscription');
define('DB_USER', 'root');
define('DB_PASS', '');

session_start();
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
    <title>Database Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
</head>
<body>
    <div class="setup-wrapper">
    <div class="setup-container">
        <h2><i class="fas fa-database"></i> Database Setup</h2>
        <p class="subtitle">Dropping all tables and recreating fresh with demo data...</p>
        <hr>

        <?php
        // Create connection
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Connection failed: ' . $conn->connect_error . '</div>';
            die();
        }

        $conn->set_charset("utf8mb4");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Database connection successful</div>';

        // ============================================
        // STEP 1: Drop all tables in reverse dependency order
        // ============================================
        echo '<br><div class="log-item log-info"><i class="fas fa-trash-alt"></i> <strong>Dropping All Existing Tables...</strong></div>';

        $tablesToDrop = [
            'customer_feedback', 'currencies',
            'custom_field_values', 'custom_fields', 'documents', 'tax_rates',
            'refunds', 'payments', 'notification_logs', 'subscriptions', 'suppliers', 'customers',
            'notifications', 'user_sessions', 'login_attempts', 'remember_tokens',
            'password_resets', 'email_verifications', 'activity_logs', 'system_settings',
            'users', 'salespersons', 'products'
        ];

        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tablesToDrop as $table) {
            if ($conn->query("DROP TABLE IF EXISTS `$table`") === TRUE) {
                echo '<div class="log-item log-info"><i class="fas fa-minus-circle"></i> Dropped table "' . $table . '"</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error dropping "' . $table . '": ' . $conn->error . '</div>';
            }
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");

        // ============================================
        // STEP 2: Create users table with new schema
        // ============================================
        echo '<br><div class="log-item log-info"><i class="fas fa-users"></i> <strong>Creating Users Table...</strong></div>';

        $users_sql = "CREATE TABLE users (
            user_id INT(11) AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL DEFAULT '',
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            role ENUM('admin','salesperson','customer') NOT NULL DEFAULT 'customer',
            department VARCHAR(50) DEFAULT NULL,
            salesperson_id INT DEFAULT NULL,
            customer_id INT DEFAULT NULL,
            profile_image VARCHAR(255) DEFAULT NULL,
            last_login DATETIME DEFAULT NULL,
            login_count INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            theme_primary VARCHAR(20) DEFAULT '#001f3f',
            theme_secondary VARCHAR(20) DEFAULT '#003366',
            theme_accent VARCHAR(20) DEFAULT '#0074D9',
            theme_mode VARCHAR(10) DEFAULT 'light',
            google_id VARCHAR(255) DEFAULT NULL,
            email_verified TINYINT(1) DEFAULT 0,
            INDEX idx_username (username),
            INDEX idx_role (role),
            INDEX idx_email (email),
            INDEX idx_is_active (is_active),
            INDEX idx_salesperson_id (salesperson_id),
            INDEX idx_customer_id (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($users_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "users" created successfully</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error creating users table: ' . $conn->error . '</div>';
        }

        // ============================================
        // STEP 3: Create all supporting tables
        // ============================================
        echo '<br><div class="log-item log-info"><i class="fas fa-table"></i> <strong>Creating Supporting Tables...</strong></div>';

        // Activity Logs
        $activity_logs_sql = "CREATE TABLE activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(50) NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45) NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_username (username),
            INDEX idx_action (action),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($activity_logs_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "activity_logs" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // System Settings
        $settings_sql = "CREATE TABLE system_settings (
            setting_id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) NOT NULL UNIQUE,
            setting_value VARCHAR(500) NOT NULL DEFAULT '',
            setting_description VARCHAR(255) DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key),
            CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($settings_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "system_settings" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Products
        $products_sql = "CREATE TABLE products (
            product_id INT AUTO_INCREMENT PRIMARY KEY,
            product_name VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT NULL,
            color_code VARCHAR(7) NOT NULL DEFAULT '#0078D4',
            selling_price DECIMAL(12,3) NOT NULL DEFAULT 0.000,
            purchase_price DECIMAL(12,3) NOT NULL DEFAULT 0.000,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            display_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_product_name (product_name),
            INDEX idx_is_active (is_active),
            INDEX idx_display_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($products_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "products" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // SalesPersons
        $salespersons_sql = "CREATE TABLE salespersons (
            salesperson_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            department VARCHAR(50) DEFAULT NULL,
            commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($salespersons_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "salespersons" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // link users.salesperson_id -> salespersons
        $conn->query("ALTER TABLE users ADD CONSTRAINT fk_user_salesperson FOREIGN KEY (salesperson_id) REFERENCES salespersons(salesperson_id) ON DELETE SET NULL");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> FK users.salesperson_id linked</div>';

        // link users.customer_id -> customers (added after customers table)
        // deferred — see after customers table creation

        // Subscriptions
        // Customers
        echo '<br><div class="log-item log-info"><i class="fas fa-address-book"></i> <strong>Creating Customers Table...</strong></div>';

        $customers_sql = "CREATE TABLE customers (
            customer_id INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(150) NOT NULL,
            contact_person VARCHAR(100) DEFAULT NULL,
            email VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            city VARCHAR(50) DEFAULT NULL,
            country VARCHAR(50) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            added_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_name (company_name),
            INDEX idx_is_active (is_active),
            CONSTRAINT fk_cust_added_by FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($customers_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "customers" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // link users.customer_id -> customers
        $conn->query("ALTER TABLE users ADD CONSTRAINT fk_user_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> FK users.customer_id linked</div>';

        // Suppliers
        echo '<br><div class="log-item log-info"><i class="fas fa-truck"></i> <strong>Creating Suppliers Table...</strong></div>';

        $suppliers_sql = "CREATE TABLE suppliers (
            supplier_id INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(150) NOT NULL,
            contact_person VARCHAR(100) DEFAULT NULL,
            email VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            city VARCHAR(50) DEFAULT NULL,
            country VARCHAR(50) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            added_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_name (company_name),
            INDEX idx_is_active (is_active),
            CONSTRAINT fk_supp_added_by FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($suppliers_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "suppliers" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        echo '<br><div class="log-item log-info"><i class="fas fa-file-contract"></i> <strong>Creating Subscriptions Table...</strong></div>';

        $subscriptions_sql = "CREATE TABLE subscriptions (
            sl INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT DEFAULT NULL,
            customer_name VARCHAR(150) NOT NULL,
            invoice_no VARCHAR(50) NOT NULL UNIQUE,
            renewal_invoice VARCHAR(50) DEFAULT NULL,
            product_id INT DEFAULT NULL,
            invoice_date DATE NOT NULL,
            product_key VARCHAR(100) DEFAULT NULL,
            user_qty INT NOT NULL DEFAULT 1,
            license_duration VARCHAR(20) DEFAULT NULL,
            starting_date DATE DEFAULT NULL,
            expiry_date DATE DEFAULT NULL,
            product_description TEXT DEFAULT NULL,
            selling_price DECIMAL(12,3) NOT NULL DEFAULT 0.000,
            purchase_price DECIMAL(12,3) NOT NULL DEFAULT 0.000,
            tax_amount DECIMAL(12,3) NOT NULL DEFAULT 0.000,
            total_amount DECIMAL(12,3) NOT NULL DEFAULT 0.000,
            currency_code VARCHAR(3) DEFAULT NULL,
            payment_status ENUM('Paid','Unpaid','Partial','Refunded') NOT NULL DEFAULT 'Unpaid',
            payment_method VARCHAR(50) DEFAULT NULL,
            payment_date DATE DEFAULT NULL,
            auto_renew TINYINT(1) NOT NULL DEFAULT 0,
            priority ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
            supplier_name VARCHAR(150) DEFAULT NULL,
            supplier_email VARCHAR(100) DEFAULT NULL,
            supplier_phone VARCHAR(20) DEFAULT NULL,
            supplier_id INT DEFAULT NULL,
            contract_reference VARCHAR(100) DEFAULT NULL,
            attachment_url VARCHAR(500) DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            subscription_status ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',
            paused_at DATETIME DEFAULT NULL,
            resumed_at DATETIME DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            cancel_reason TEXT DEFAULT NULL,
            added_by INT NOT NULL,
            salesperson_id INT DEFAULT NULL,
            added_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT DEFAULT NULL,
            INDEX idx_customer_name (customer_name),
            INDEX idx_invoice_no (invoice_no),
            INDEX idx_invoice_date (invoice_date),
            INDEX idx_expiry_date (expiry_date),
            INDEX idx_payment_status (payment_status),
            INDEX idx_added_by (added_by),
            INDEX idx_customer_id (customer_id),
            INDEX idx_product_id (product_id),
            INDEX idx_supplier_id (supplier_id),
            INDEX idx_salesperson_id (salesperson_id),
            INDEX idx_subscription_status (subscription_status),
            CONSTRAINT fk_sub_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL,
            CONSTRAINT fk_sub_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
            CONSTRAINT fk_sub_added_by FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE RESTRICT,
            CONSTRAINT fk_sub_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL,
            CONSTRAINT fk_sub_salesperson FOREIGN KEY (salesperson_id) REFERENCES salespersons(salesperson_id) ON DELETE SET NULL,
            CONSTRAINT fk_sub_updated_by FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($subscriptions_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "subscriptions" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Notification Logs
        $notification_logs_sql = "CREATE TABLE notification_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            subscription_sl INT NOT NULL,
            recipient_email VARCHAR(100) NOT NULL,
            recipient_type ENUM('admin','user','salesperson','supplier') NOT NULL,
            recipient_name VARCHAR(100) DEFAULT NULL,
            notification_type ENUM('expiry_reminder','expired_alert','payment_reminder','renewal_notice','manual_reminder','welcome','custom') NOT NULL,
            days_before_expiry INT DEFAULT NULL,
            subject VARCHAR(255) NOT NULL,
            body_preview TEXT DEFAULT NULL,
            status ENUM('Sent','Failed','Pending','Bounced') NOT NULL DEFAULT 'Pending',
            error_message VARCHAR(500) DEFAULT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            triggered_by ENUM('system','manual') NOT NULL DEFAULT 'system',
            triggered_by_user INT DEFAULT NULL,
            INDEX idx_subscription_sl (subscription_sl),
            INDEX idx_status (status),
            INDEX idx_sent_at (sent_at),
            INDEX idx_notification_type (notification_type),
            CONSTRAINT fk_nl_subscription FOREIGN KEY (subscription_sl) REFERENCES subscriptions(sl) ON DELETE CASCADE,
            CONSTRAINT fk_nl_triggered_by FOREIGN KEY (triggered_by_user) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($notification_logs_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "notification_logs" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Payments
        echo '<br><div class="log-item log-info"><i class="fas fa-money-bill-wave"></i> <strong>Creating Payments Table...</strong></div>';

        $payments_sql = "CREATE TABLE payments (
            payment_id INT AUTO_INCREMENT PRIMARY KEY,
            subscription_sl INT NOT NULL,
            amount DECIMAL(12,3) NOT NULL DEFAULT 0.000,
            payment_method VARCHAR(50) DEFAULT NULL,
            payment_date DATE NOT NULL,
            reference_no VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            added_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_subscription (subscription_sl),
            INDEX idx_payment_date (payment_date),
            CONSTRAINT fk_pay_subscription FOREIGN KEY (subscription_sl) REFERENCES subscriptions(sl) ON DELETE CASCADE,
            CONSTRAINT fk_pay_added_by FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($payments_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "payments" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Refunds
        $refunds_sql = "CREATE TABLE refunds (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($refunds_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "refunds" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Tax Rates
        $tax_sql = "CREATE TABLE tax_rates (
            tax_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($tax_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "tax_rates" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Documents
        $docs_sql = "CREATE TABLE documents (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($docs_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "documents" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Custom Fields
        $cf_sql = "CREATE TABLE custom_fields (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($cf_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "custom_fields" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Custom Field Values
        $cfv_sql = "CREATE TABLE custom_field_values (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($cfv_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "custom_field_values" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Currencies
        $curr_sql = "CREATE TABLE currencies (
            currency_id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(3) NOT NULL UNIQUE,
            name VARCHAR(50) NOT NULL,
            symbol VARCHAR(5) NOT NULL DEFAULT '',
            exchange_rate DECIMAL(12,6) NOT NULL DEFAULT 1.000000,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_code (code),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($curr_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "currencies" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Customer Feedback / NPS
        $fb_sql = "CREATE TABLE customer_feedback (
            feedback_id INT AUTO_INCREMENT PRIMARY KEY,
            subscription_sl INT DEFAULT NULL,
            customer_id INT DEFAULT NULL,
            rating TINYINT NOT NULL,
            comment TEXT DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_id (customer_id),
            INDEX idx_subscription_sl (subscription_sl),
            INDEX idx_rating (rating)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($fb_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "customer_feedback" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Email Verifications
        $ev_sql = "CREATE TABLE email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(100) NOT NULL,
            otp_code VARCHAR(6) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            INDEX idx_user_id (user_id),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($ev_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "email_verifications" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Password Resets
        $pr_sql = "CREATE TABLE password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            otp_code VARCHAR(6) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($pr_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "password_resets" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Remember Tokens
        $rt_sql = "CREATE TABLE remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_token (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($rt_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "remember_tokens" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Login Attempts
        echo '<br><div class="log-item log-info"><i class="fas fa-shield-alt"></i> <strong>Setting up Security Tables...</strong></div>';

        $login_attempts_sql = "CREATE TABLE login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_attempt_time (attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($login_attempts_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "login_attempts" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // User Sessions
        $user_sessions_sql = "CREATE TABLE user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(128) NOT NULL UNIQUE,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            force_logout TINYINT(1) DEFAULT 0,
            INDEX idx_user_id (user_id),
            INDEX idx_session_id (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($user_sessions_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "user_sessions" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // Notifications
        echo '<br><div class="log-item log-info"><i class="fas fa-bell"></i> <strong>Setting up Notifications...</strong></div>';

        $notifications_sql = "CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info','success','warning','danger') DEFAULT 'info',
            is_read TINYINT(1) DEFAULT 0,
            link VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_user_read (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($notifications_sql) === TRUE) {
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Table "notifications" created</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $conn->error . '</div>';
        }

        // ============================================
        // STEP 4: Insert demo/seed users
        // ============================================
        echo '<br><div class="log-item log-info"><i class="fas fa-user-plus"></i> <strong>Creating Demo Users...</strong></div>';

        $demo_users = [
            [
                'username' => 'admin',
                'password' => 'admin123',
                'full_name' => 'Admin User',
                'email' => 'admin@demo.com',
                'phone' => '03001000000',
                'role' => 'admin',
                'department' => 'IT',
                'salesperson_id' => null
            ],
            [
                'username' => 'sales1',
                'password' => 'sales123',
                'full_name' => 'Salesperson 1',
                'email' => 'sales1@demo.com',
                'phone' => '03001000003',
                'role' => 'salesperson',
                'department' => 'Sales',
                'salesperson_id' => null
            ],
            [
                'username' => 'customer1',
                'password' => 'cust123',
                'full_name' => 'Company 1 Portal',
                'email' => 'portal@company1.demo.com',
                'phone' => '03001000004',
                'role' => 'customer',
                'department' => '',
                'salesperson_id' => null
            ]
        ];

        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role, department, salesperson_id, email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");

        foreach ($demo_users as $user) {
            $hashed = password_hash($user['password'], PASSWORD_DEFAULT);
            $sp_id = $user['salesperson_id'];
            $stmt->bind_param("sssssssi", $user['username'], $hashed, $user['full_name'], $user['email'], $user['phone'], $user['role'], $user['department'], $sp_id);
            if ($stmt->execute()) {
                $role_label = ucfirst($user['role']);
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Demo user "' . $user['username'] . '" created (' . $role_label . ')</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error creating user "' . $user['username'] . '": ' . $stmt->error . '</div>';
            }
        }
        $stmt->close();

        // Show credentials
        echo '<div class="credentials-box">';
        echo '<strong><i class="fas fa-key"></i> Demo Login Credentials:</strong><br><br>';
        echo '<table style="width:100%;border-collapse:collapse;">';
        echo '<tr style="border-bottom:1px solid rgba(0,0,0,0.1);"><th style="text-align:left;padding:5px;">Username</th><th style="text-align:left;padding:5px;">Password</th><th style="text-align:left;padding:5px;">Role</th><th style="text-align:left;padding:5px;">Department</th></tr>';
        foreach ($demo_users as $user) {
            echo '<tr style="border-bottom:1px solid rgba(0,0,0,0.05);"><td style="padding:5px;"><strong>' . $user['username'] . '</strong></td><td style="padding:5px;">' . $user['password'] . '</td><td style="padding:5px;">' . ucfirst($user['role']) . '</td><td style="padding:5px;">' . $user['department'] . '</td></tr>';
        }
        echo '</table><br>';
        echo '<em class="text-warning"><i class="fas fa-exclamation-triangle"></i> Please change passwords after first login!</em>';
        echo '</div>';

        // ============================================
        // STEP 5: Insert default system settings
        // ============================================
        echo '<br><div class="log-item log-info"><i class="fas fa-paint-brush"></i> <strong>Setting up Default Settings...</strong></div>';

        $logo_url = 'https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEiGXxCe0WNNedmFqSWeF761f7Kshhc-NP5ChRQKz9fr97cO8VaarvD0KlCwqHojJVBWv-RAxfOqMI5rD4H78KnARyOc6QgwL1nRRFWf5xNQ1d9F9HfAoLPPGlTyP0GwNl4n-INMEsWLQ4Y7zJtz5bOdAnc2ePH9-uCRgshlo6BsS6gJEz6fhrxL-5U5O3sX/s160/channels4_profile.jpg';

        $all_defaults = [
            // Business settings
            ['currency',                  'INR',              'System default currency'],
            ['company_name',              'My Company',        'Used in email headers and reports'],
            ['company_email',             'admin@company.com','Default sender identity for notifications'],
            ['company_logo_url',          $logo_url,          'Logo for emails and login page'],
            ['notification_days_before',  '30,15,7,3,1,0',   'Comma-separated days before expiry to trigger alerts'],
            ['auto_email_enabled',        'true',             'Master toggle for automated email notifications'],
            ['email_frequency',           'daily',            'How often auto-emails run (daily/weekly)'],
            ['tax_percentage',            '0',                'Default tax rate if applicable'],
            ['date_format',               'MM/DD/YYYY',       'Display date format preference'],
            ['timezone',                  'Asia/Kolkata',     'System timezone for date calculations'],
            // Legacy branding (backward compat for getSiteBranding)
            ['site_name',                 'My Company',        'Legacy: mapped from company_name'],
            ['site_logo',                 $logo_url,          'Legacy: mapped from company_logo_url'],
            ['copyright_text',            '&copy; ' . date('Y') . ' My Company. All rights reserved.', 'Footer copyright text'],
            // System behavior
            ['allow_user_profile_uploads','1',                'Allow users to upload profile pictures'],
            ['show_forgot_password',      '1',                'Show forgot password link on login'],
            ['maintenance_mode',          '0',                'Enable maintenance mode'],
            ['default_language',          'en',               'Default UI language'],
            // SMTP
            ['smtp_enabled',              '0',                'Enable SMTP email sending'],
            ['smtp_host',                 '',                 'SMTP server hostname'],
            ['smtp_port',                 '587',              'SMTP server port'],
            ['smtp_username',             '',                 'SMTP authentication username'],
            ['smtp_password',             '',                 'SMTP authentication password'],
            ['smtp_from_email',           '',                 'From email address'],
            ['smtp_from_name',            'My Company',        'From display name'],
            ['smtp_encryption',           'tls',              'SMTP encryption type (tls/ssl)'],
            ['email_verification_enabled','0',                'Require email verification on signup'],
            // Google OAuth
            ['google_oauth_enabled',      '0',                'Enable Google OAuth login'],
            ['google_client_id',          '',                 'Google OAuth client ID'],
            ['google_client_secret',      '',                 'Google OAuth client secret'],
            ['google_redirect_uri',       '',                 'Google OAuth redirect URI'],
            // AI Chat (Gemini)
            ['gemini_api_key',            '',                 'Google Gemini API key for AI Chat assistant'],
        ];

        $stmt_setting = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
        foreach ($all_defaults as $row) {
            $stmt_setting->bind_param("sss", $row[0], $row[1], $row[2]);
            if ($stmt_setting->execute()) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Setting "' . $row[0] . '" created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error creating setting "' . $row[0] . '": ' . $stmt_setting->error . '</div>';
            }
        }
        $stmt_setting->close();

        // ============================================
        // STEP 6: Seed demo products
        // ============================================
        echo '<br><div class="log-item log-info"><i class="fas fa-tags"></i> <strong>Seeding Demo Products...</strong></div>';

        $demo_products = [
            ['Software',  'Software subscriptions and licenses',  '#0078D4', 1, 10000.000, 8000.000],
            ['Hardware',  'Hardware and equipment purchases',      '#107C10', 2, 50000.000, 40000.000],
            ['Marketing', 'Marketing tools and services',          '#FF8C00', 3, 8000.000,  6000.000],
            ['Cloud',     'Cloud infrastructure services',         '#0099BC', 4, 25000.000, 20000.000],
            ['Support',   'Support and maintenance contracts',     '#5C2D91', 5, 15000.000, 12000.000],
        ];

        $stmt_prod = $conn->prepare("INSERT INTO products (product_name, description, color_code, display_order, selling_price, purchase_price) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($demo_products as $prod) {
            $stmt_prod->bind_param("sssidd", $prod[0], $prod[1], $prod[2], $prod[3], $prod[4], $prod[5]);
            if ($stmt_prod->execute()) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Product "' . $prod[0] . '" created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $stmt_prod->error . '</div>';
            }
        }
        $stmt_prod->close();

        // ============================================
        // STEP 7: Seed demo salespersons
        // ============================================
        echo '<br><div class="log-item log-info"><i class="fas fa-user-tie"></i> <strong>Seeding Demo Sales Persons...</strong></div>';

        $demo_salespersons = [
            ['Salesperson 1', 'salesperson1@demo.com', '03001010001', 'Sales Dept',    5.00],
            ['Salesperson 2', 'salesperson2@demo.com', '03001010002', 'Enterprise',    7.50],
            ['Salesperson 3', 'salesperson3@demo.com', '03001010003', 'SMB Dept',      6.00],
        ];

        $stmt_sp = $conn->prepare("INSERT INTO salespersons (name, email, phone, department, commission_rate) VALUES (?, ?, ?, ?, ?)");
        foreach ($demo_salespersons as $sp) {
            $stmt_sp->bind_param("ssssd", $sp[0], $sp[1], $sp[2], $sp[3], $sp[4]);
            if ($stmt_sp->execute()) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> SalesPerson "' . $sp[0] . '" created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $stmt_sp->error . '</div>';
            }
        }
        $stmt_sp->close();

        // link sales1 user to first salesperson
        $conn->query("UPDATE users SET salesperson_id = 1 WHERE username = 'sales1'");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Linked sales1 user to Salesperson 1</div>';

        // seed tax rates
        $conn->query("INSERT INTO tax_rates (name, rate, is_default, is_active) VALUES
            ('No Tax', 0.00, 1, 1),
            ('GST 5%', 5.00, 0, 1),
            ('GST 12%', 12.00, 0, 1),
            ('GST 18%', 18.00, 0, 1),
            ('VAT 15%', 15.00, 0, 1)");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Demo tax rates created</div>';

        // seed currencies
        $conn->query("INSERT INTO currencies (code, name, symbol, exchange_rate, is_default) VALUES
            ('INR', 'Indian Rupee', '₹', 1.000000, 1),
            ('USD', 'US Dollar', '\$', 0.011900, 0),
            ('EUR', 'Euro', '€', 0.010900, 0),
            ('GBP', 'British Pound', '£', 0.009400, 0),
            ('AED', 'UAE Dirham', 'د.إ', 0.043700, 0),
            ('SAR', 'Saudi Riyal', '﷼', 0.044600, 0),
            ('PKR', 'Pakistani Rupee', 'Rs', 3.310000, 0)");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Demo currencies created</div>';

        // seed onboarding flag
        $conn->query("INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES ('onboarding_complete', '0', 'Whether first-time setup wizard has been completed') ON DUPLICATE KEY UPDATE setting_value = setting_value");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Onboarding setting created</div>';

        // seed custom fields
        echo '<br><div class="log-item log-info"><i class="fas fa-puzzle-piece"></i> <strong>Seeding Custom Fields...</strong></div>';

        $conn->query("INSERT INTO custom_fields (entity_type, field_name, field_label, field_type, field_options, is_required, display_order, is_active) VALUES
            ('customer', 'industry', 'Industry', 'select', 'IT,Healthcare,Finance,Education,Retail,Manufacturing,Other', 0, 1, 1),
            ('customer', 'company_size', 'Company Size', 'select', '1-10,11-50,51-200,201-500,500+', 0, 2, 1),
            ('customer', 'tax_id', 'Tax ID / VAT No', 'text', NULL, 0, 3, 1),
            ('customer', 'website', 'Website', 'text', NULL, 0, 4, 1),
            ('customer', 'notes_internal', 'Internal Notes', 'textarea', NULL, 0, 5, 1),
            ('subscription', 'license_type', 'License Type', 'select', 'Single User,Multi User,Enterprise,Trial,NFR', 0, 1, 1),
            ('subscription', 'support_level', 'Support Level', 'select', 'Basic,Standard,Premium,24/7', 0, 2, 1),
            ('subscription', 'po_number', 'PO Number', 'text', NULL, 0, 3, 1),
            ('subscription', 'deployment', 'Deployment Type', 'select', 'Cloud,On-Premise,Hybrid', 0, 4, 1)
        ");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> 9 custom fields created (5 customer + 4 subscription)</div>';

        // ============================================
        // STEP 8: Seed demo data
        // ============================================
        // Seed demo customers
        echo '<br><div class="log-item log-info"><i class="fas fa-address-book"></i> <strong>Seeding Demo Customers...</strong></div>';

        $demo_customers = [
            ['Company 1', 'Contact 1', 'company1@demo.com', '04230000001', 'House 1, Street 1, Demo City', 'Demo City', 'Demo Country', 1],
            ['Company 2', 'Contact 2', 'company2@demo.com', '04230000002', 'House 2, Street 2, Demo City', 'Demo City', 'Demo Country', 1],
            ['Company 3', 'Contact 3', 'company3@demo.com', '04230000003', 'House 3, Street 3, Demo City', 'Demo City', 'Demo Country', 1],
            ['Company 4', 'Contact 4', 'company4@demo.com', '04230000004', 'House 4, Street 4, Demo City', 'Demo City', 'Demo Country', 1],
            ['Company 5', 'Contact 5', 'company5@demo.com', '04230000005', 'House 5, Street 5, Demo City', 'Demo City', 'Demo Country', 1],
            ['Company 6', 'Contact 6', 'company6@demo.com', '04230000006', 'House 6, Street 6, Demo City', 'Demo City', 'Demo Country', 1],
        ];

        $stmt_cust = $conn->prepare("INSERT INTO customers (company_name, contact_person, email, phone, address, city, country, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($demo_customers as $c) {
            $stmt_cust->bind_param("sssssssi", $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $c[7]);
            if ($stmt_cust->execute()) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Customer "' . $c[0] . '" created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $stmt_cust->error . '</div>';
            }
        }
        $stmt_cust->close();

        // link customer1 user to Company 1
        $conn->query("UPDATE users SET customer_id = 1 WHERE username = 'customer1'");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Linked customer1 user to Company 1</div>';

        // seed custom field values for demo customers
        $conn->query("INSERT INTO custom_field_values (field_id, entity_type, entity_id, field_value) VALUES
            (1, 'customer', 1, 'IT'),
            (2, 'customer', 1, '51-200'),
            (3, 'customer', 1, 'TAX-001-2026'),
            (4, 'customer', 1, 'https://company1.demo.com'),
            (1, 'customer', 2, 'Healthcare'),
            (2, 'customer', 2, '201-500'),
            (3, 'customer', 2, 'TAX-002-2026'),
            (1, 'customer', 3, 'Finance'),
            (2, 'customer', 3, '11-50'),
            (1, 'customer', 4, 'Education'),
            (2, 'customer', 4, '500+'),
            (4, 'customer', 4, 'https://company4.demo.com'),
            (1, 'customer', 5, 'Retail'),
            (1, 'customer', 6, 'Manufacturing'),
            (2, 'customer', 6, '201-500')
        ");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Demo custom field values added for customers</div>';

        // Seed demo suppliers
        echo '<br><div class="log-item log-info"><i class="fas fa-truck"></i> <strong>Seeding Demo Suppliers...</strong></div>';

        $demo_suppliers = [
            ['Supplier 1', 'Contact 1', 'supplier1@demo.com', '04230100001', 'House 1, Street 10, Demo City', 'Demo City', 'Demo Country', 1],
            ['Supplier 2', 'Contact 2', 'supplier2@demo.com', '04230100002', 'House 2, Street 20, Demo City', 'Demo City', 'Demo Country', 1],
            ['Supplier 3', 'Contact 3', 'supplier3@demo.com', '04230100003', 'House 3, Street 30, Demo City', 'Demo City', 'Demo Country', 1],
            ['Supplier 4', 'Contact 4', 'supplier4@demo.com', '04230100004', 'House 4, Street 40, Demo City', 'Demo City', 'Demo Country', 1],
            ['Supplier 5', 'Contact 5', 'supplier5@demo.com', '04230100005', 'House 5, Street 50, Demo City', 'Demo City', 'Demo Country', 1],
            ['Supplier 6', 'Contact 6', 'supplier6@demo.com', '04230100006', 'House 6, Street 60, Demo City', 'Demo City', 'Demo Country', 1],
        ];

        $stmt_supp = $conn->prepare("INSERT INTO suppliers (company_name, contact_person, email, phone, address, city, country, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($demo_suppliers as $su) {
            $stmt_supp->bind_param("sssssssi", $su[0], $su[1], $su[2], $su[3], $su[4], $su[5], $su[6], $su[7]);
            if ($stmt_supp->execute()) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Supplier "' . $su[0] . '" created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $stmt_supp->error . '</div>';
            }
        }
        $stmt_supp->close();

        echo '<br><div class="log-item log-info"><i class="fas fa-file-contract"></i> <strong>Seeding Demo Subscriptions...</strong></div>';

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        // customer_id, customer_name, invoice_no, product_id, invoice_date, product_key, user_qty, license_duration, starting_date, expiry_date, product_description, selling_price, purchase_price, tax_amount, total_amount, payment_status, payment_method, payment_date, auto_renew, priority, supplier_name, supplier_email, supplier_phone, supplier_id, added_by, salesperson_id
        // added_by: 1=admin, 2=sales1
        $demo_subs = [
            [1, 'Company 1', 'INV-2025-001', 1, '2025-03-01', 'KEY-001-DEMO', 5, '1 Year',   '2025-03-01', $yesterday,                          'Product 1 - Annual software license',     10000.000, 8000.000, 0.000, 10000.000, 'Paid',     'Bank Transfer', $yesterday,  0, 'High',     'Supplier 1', 'supplier1@demo.com', '04230100001', 1, 1, 1],
            [2, 'Company 2', 'INV-2025-002', 4, '2025-03-02', 'KEY-002-DEMO', 10, '1 Year',   '2025-03-02', $today,                               'Product 2 - Cloud hosting service',       25000.000, 20000.000, 0.000, 25000.000, 'Unpaid',   null,            null,         0, 'Critical', 'Supplier 2', 'supplier2@demo.com', '04230100002', 2, 1, 2],
            [3, 'Company 3', 'INV-2025-003', 3, '2025-03-01', null,            1, '6 Months', '2025-03-01', date('Y-m-d', strtotime('+8 days')),  'Product 3 - Marketing platform access',    8000.000,  6000.000, 0.000, 8000.000,  'Partial',  'Credit Card',   null,         0, 'Medium',   'Supplier 3', 'supplier3@demo.com', null,          3, 1, 3],
            [4, 'Company 4', 'INV-2026-001', 5, '2026-01-15', null,            3, '1 Year',   '2026-01-15', '2026-06-15',                        'Product 4 - Annual support contract',     15000.000, 12000.000, 0.000, 15000.000, 'Paid',     'Bank Transfer', '2026-01-20', 1, 'Low',      'Supplier 4', 'supplier4@demo.com', '04230100004', 4, 1, 1],
            [5, 'Company 5', 'INV-2026-002', 2, '2026-02-01', null,            20, '1 Year',  '2026-02-01', '2026-09-30',                        'Product 5 - Hardware equipment purchase',  50000.000, 40000.000, 0.000, 50000.000, 'Paid',     'Cheque',        '2026-02-05', 0, 'Medium',   'Supplier 5', 'supplier5@demo.com', '04230100005', 5, 2, 2],
            [6, 'Company 6', 'INV-2026-003', 1, '2026-01-01', null,            15, '1 Year',  '2026-01-01', '2026-12-31',                        'Product 6 - Enterprise software suite',   30000.000, 25000.000, 0.000, 30000.000, 'Unpaid',   null,            null,         0, 'High',     'Supplier 6', 'supplier6@demo.com', null,          6, 1, null],
            [null, 'Company 7', 'INV-2026-004', 4, '2026-02-15', null,          5, '1 Year',  '2026-02-15', date('Y-m-d', strtotime('+2 days')), 'Product 7 - Cloud development tools',     18000.000, 14000.000, 0.000, 18000.000, 'Partial',  'Online',        null,         1, 'Critical', 'Supplier 2', 'supplier2@demo.com', '04230100002', null, 2, 3],
            [null, 'Company 8', 'INV-2024-007', 5, '2024-01-01', null,          2, '1 Year',  '2024-01-01', '2025-12-31',                        'Product 8 - Legacy support contract',      5000.000,  4000.000, 0.000, 5000.000,  'Refunded', 'Cash',          '2024-01-05', 0, 'Low',      'Supplier 4', 'supplier4@demo.com', null,          null, 1, 1],
            // 20 more subs
            [1, 'Company 1', 'INV-2026-005', 1, '2026-03-01', 'KEY-009-DEMO', 10, '1 Year',   '2026-03-01', '2027-02-28',                        'Enterprise ERP license - 10 seats',        35000.000, 28000.000, 0.000, 35000.000, 'Paid',     'Bank Transfer', '2026-03-05', 1, 'High',     'Supplier 1', 'supplier1@demo.com', '04230100001', 1, 1, 2],
            [2, 'Company 2', 'INV-2026-006', 5, '2026-03-10', null,            8, '6 Months', '2026-03-10', '2026-09-10',                        'Premium helpdesk access',                  12000.000, 9500.000,  0.000, 12000.000, 'Unpaid',   null,            null,         0, 'Medium',   'Supplier 4', 'supplier4@demo.com', '04230100004', 4, 1, 1],
            [3, 'Company 3', 'INV-2026-007', 4, '2026-02-20', 'KEY-011-DEMO', 25, '1 Year',   '2026-02-20', '2027-02-20',                        'Multi-tenant cloud hosting',               45000.000, 36000.000, 0.000, 45000.000, 'Paid',     'Online',        '2026-02-25', 1, 'Critical', 'Supplier 2', 'supplier2@demo.com', '04230100002', 2, 2, 3],
            [4, 'Company 4', 'INV-2026-008', 3, '2025-10-15', null,            3, '6 Months', '2025-10-15', date('Y-m-d', strtotime('+5 days')), 'Social media management toolkit',          6000.000,  4500.000,  0.000, 6000.000,  'Partial',  'Credit Card',   null,         0, 'High',     'Supplier 3', 'supplier3@demo.com', '04230100003', 3, 1, 2],
            [5, 'Company 5', 'INV-2026-009', 2, '2026-01-20', null,           50, '2 Years',  '2026-01-20', '2028-01-20',                        'Office network equipment purchase',        85000.000, 68000.000, 0.000, 85000.000, 'Paid',     'Cheque',        '2026-01-25', 0, 'Low',      'Supplier 5', 'supplier5@demo.com', '04230100005', 5, 2, 1],
            [6, 'Company 6', 'INV-2026-010', 1, '2026-04-01', null,           12, '1 Year',   '2026-04-01', '2027-04-01',                        'CRM platform license - full suite',        22000.000, 17000.000, 0.000, 22000.000, 'Unpaid',   null,            null,         0, 'Medium',   'Supplier 1', 'supplier1@demo.com', '04230100001', 1, 1, null],
            [1, 'Company 1', 'INV-2025-004', 4, '2025-01-15', 'KEY-015-DEMO',  5, '6 Months', '2025-01-15', '2025-07-15',                        'Dev environment cloud hosting',            18000.000, 14000.000, 0.000, 18000.000, 'Paid',     'Bank Transfer', '2025-01-20', 0, 'Low',      'Supplier 2', 'supplier2@demo.com', '04230100002', 2, 1, 3],
            [2, 'Company 2', 'INV-2025-005', 5, '2025-02-01', null,            2, '6 Months', '2025-02-01', '2025-08-01',                        'Basic maintenance plan',                    7000.000,  5500.000, 0.000, 7000.000,  'Refunded', 'Online',        '2025-02-05', 0, 'Low',      'Supplier 4', 'supplier4@demo.com', '04230100004', 4, 2, 2],
            [3, 'Company 3', 'INV-2026-011', 2, '2026-03-15', null,          100, '2 Years',  '2026-03-15', '2028-03-15',                        'Server infrastructure purchase',          120000.000, 95000.000, 0.000,120000.000, 'Paid',     'Bank Transfer', '2026-03-20', 1, 'Critical', 'Supplier 5', 'supplier5@demo.com', '04230100005', 5, 1, 1],
            [4, 'Company 4', 'INV-2026-012', 1, '2026-04-05', 'KEY-018-DEMO',  7, '1 Year',   '2026-04-05', '2027-04-05',                        'Project management tool license',          14000.000, 11000.000, 0.000, 14000.000, 'Unpaid',   null,            null,         1, 'Medium',   'Supplier 1', 'supplier1@demo.com', '04230100001', 1, 2, 3],
            [5, 'Company 5', 'INV-2026-013', 3, '2026-02-10', null,            6, '1 Year',   '2026-02-10', '2027-02-10',                        'SEO analytics platform access',            9500.000,  7500.000,  0.000, 9500.000,  'Partial',  'Credit Card',   null,         0, 'High',     'Supplier 3', 'supplier3@demo.com', '04230100003', 3, 1, 2],
            [6, 'Company 6', 'INV-2026-014', 4, '2026-01-05', 'KEY-020-DEMO', 15, '1 Year',   '2026-01-05', '2027-01-05',                        'Backup and disaster recovery service',    28000.000, 22000.000, 0.000, 28000.000, 'Paid',     'Online',        '2026-01-10', 1, 'High',     'Supplier 2', 'supplier2@demo.com', '04230100002', 2, 1, 1],
            [1, 'Company 1', 'INV-2025-006', 5, '2025-04-01', null,           10, '6 Months', '2025-04-01', '2025-10-01',                        'Extended warranty plan',                    8500.000,  6800.000, 0.000, 8500.000,  'Paid',     'Cash',          '2025-04-05', 0, 'Medium',   'Supplier 4', 'supplier4@demo.com', '04230100004', 4, 1, null],
            [2, 'Company 2', 'INV-2026-015', 2, '2025-11-01', null,           30, '6 Months', '2025-11-01', date('Y-m-d', strtotime('+3 days')), 'Medical equipment lease',                 65000.000, 52000.000, 0.000, 65000.000, 'Unpaid',   null,            null,         0, 'Critical', 'Supplier 5', 'supplier5@demo.com', '04230100005', 5, 2, 1],
            [3, 'Company 3', 'INV-2026-016', 1, '2026-03-20', 'KEY-023-DEMO', 20, '1 Year',   '2026-03-20', '2027-03-20',                        'Accounting suite license',                16000.000, 12500.000, 0.000, 16000.000, 'Paid',     'Bank Transfer', '2026-03-25', 1, 'Medium',   'Supplier 1', 'supplier1@demo.com', '04230100001', 1, 1, 3],
            [null, 'Company 7', 'INV-2026-017', 3, '2026-02-28', null,         4, '1 Year',   '2026-02-28', '2027-02-28',                        'Email campaign manager',                   7500.000,  5800.000, 0.000, 7500.000,  'Partial',  'Online',        null,         0, 'Low',      'Supplier 3', 'supplier3@demo.com', '04230100003', 3, 2, 2],
            [5, 'Company 5', 'INV-2025-008', 4, '2025-03-10', null,            8, '6 Months', '2025-03-10', '2025-09-10',                        'Staging environment hosting',              11000.000, 8500.000,  0.000, 11000.000, 'Refunded', 'Bank Transfer', '2025-03-15', 0, 'Low',      'Supplier 2', 'supplier2@demo.com', '04230100002', 2, 1, 1],
            [6, 'Company 6', 'INV-2026-018', 5, '2026-04-08', null,           50, '2 Years',  '2026-04-08', '2028-04-08',                        'Dedicated account manager service',       40000.000, 32000.000, 0.000, 40000.000, 'Paid',     'Bank Transfer', '2026-04-10', 1, 'Critical', 'Supplier 6', 'supplier6@demo.com', '04230100006', 6, 1, 2],
            [null, 'Company 8', 'INV-2026-019', 1, '2026-04-01', null,         3, '1 Year',   '2026-04-01', '2027-04-01',                        'Inventory management software',            11500.000, 9000.000,  0.000, 11500.000, 'Unpaid',   null,            null,         0, 'Medium',   'Supplier 1', 'supplier1@demo.com', '04230100001', null, 2, null],
            [4, 'Company 4', 'INV-2026-020', 2, '2026-03-25', 'KEY-028-DEMO', 15, '1 Year',   '2026-03-25', '2027-03-25',                        'Classroom AV equipment',                  55000.000, 44000.000, 0.000, 55000.000, 'Paid',     'Cheque',        '2026-03-28', 0, 'High',     'Supplier 5', 'supplier5@demo.com', '04230100005', 5, 1, 3],
        ];

        $stmt_sub = $conn->prepare("INSERT INTO subscriptions
            (customer_id, customer_name, invoice_no, product_id, invoice_date, product_key, user_qty,
             license_duration, starting_date, expiry_date, product_description,
             selling_price, purchase_price, tax_amount, total_amount,
             payment_status, payment_method, payment_date, auto_renew, priority,
             supplier_name, supplier_email, supplier_phone, supplier_id, added_by, salesperson_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($demo_subs as $s) {
            // nullable FK cols need string type to pass NULL properly
            $cust_id = $s[0];  $supp_id = $s[23];  $sp_id = $s[25];
            $stmt_sub->bind_param("sssississssddddsssisssssss",
                $cust_id, $s[1], $s[2], $s[3], $s[4], $s[5], $s[6],
                $s[7], $s[8], $s[9], $s[10],
                $s[11], $s[12], $s[13], $s[14],
                $s[15], $s[16], $s[17], $s[18], $s[19],
                $s[20], $s[21], $s[22], $supp_id, $s[24], $sp_id);
            if ($stmt_sub->execute()) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Subscription "' . $s[2] . '" (' . $s[1] . ') created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $stmt_sub->error . '</div>';
            }
        }
        $stmt_sub->close();

        // seed custom field values for subscriptions
        // field_ids: 6=license_type, 7=support_level, 8=po_number, 9=deployment
        $conn->query("INSERT INTO custom_field_values (field_id, entity_type, entity_id, field_value) VALUES
            (6, 'subscription', 1, 'Multi User'),
            (7, 'subscription', 1, 'Premium'),
            (8, 'subscription', 1, 'PO-2025-0001'),
            (9, 'subscription', 1, 'Cloud'),
            (6, 'subscription', 2, 'Enterprise'),
            (7, 'subscription', 2, '24/7'),
            (9, 'subscription', 2, 'Cloud'),
            (6, 'subscription', 3, 'Single User'),
            (7, 'subscription', 3, 'Basic'),
            (9, 'subscription', 3, 'On-Premise'),
            (6, 'subscription', 4, 'Multi User'),
            (7, 'subscription', 4, 'Standard'),
            (8, 'subscription', 4, 'PO-2026-0010'),
            (9, 'subscription', 4, 'Hybrid'),
            (6, 'subscription', 5, 'Enterprise'),
            (7, 'subscription', 5, 'Premium'),
            (8, 'subscription', 5, 'PO-2026-0020'),
            (6, 'subscription', 6, 'Enterprise'),
            (7, 'subscription', 6, '24/7'),
            (9, 'subscription', 6, 'Cloud'),
            (6, 'subscription', 7, 'Multi User'),
            (7, 'subscription', 7, 'Standard'),
            (6, 'subscription', 8, 'Single User'),
            (7, 'subscription', 8, 'Basic'),
            (9, 'subscription', 8, 'On-Premise')
        ");
        echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Demo custom field values added for subscriptions</div>';

        // Seed demo payments
        echo '<br><div class="log-item log-info"><i class="fas fa-money-bill-wave"></i> <strong>Seeding Demo Payments...</strong></div>';

        $demo_payments = [
            [1, 10000.000, 'Bank Transfer', $yesterday,  'TXN-001', 'Full payment for Product 1',    1],
            [3, 5000.000,  'Credit Card',   '2025-03-05', 'TXN-002', 'Partial payment for Product 3', 1],
            [4, 15000.000, 'Bank Transfer', '2026-01-20', 'TXN-003', 'Full payment for Product 4',    1],
            [5, 50000.000, 'Cheque',        '2026-02-05', 'TXN-004', 'Full payment for Product 5',    2],
            [7, 10000.000, 'Online',        '2026-02-20', 'TXN-005', 'Partial payment for Product 7', 2],
        ];

        $stmt_pay = $conn->prepare("INSERT INTO payments (subscription_sl, amount, payment_method, payment_date, reference_no, notes, added_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($demo_payments as $p) {
            $stmt_pay->bind_param("idssssi", $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]);
            if ($stmt_pay->execute()) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Payment for subscription #' . $p[0] . ' created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Error: ' . $stmt_pay->error . '</div>';
            }
        }
        $stmt_pay->close();

        // ============================================
        // STEP 9: Create upload directories
        // ============================================
        echo '<br><div class="log-item log-info"><i class="fas fa-folder"></i> <strong>Creating Upload Directories...</strong></div>';

        $upload_base = __DIR__ . '/uploads';
        $upload_profiles = __DIR__ . '/uploads/profiles';
        $upload_branding = __DIR__ . '/uploads/branding';

        if (!file_exists($upload_base)) {
            if (mkdir($upload_base, 0755, true)) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Directory "uploads/" created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Failed to create "uploads/" directory</div>';
            }
        } else {
            echo '<div class="log-item log-info"><i class="fas fa-info-circle"></i> Directory "uploads/" already exists</div>';
        }

        if (!file_exists($upload_profiles)) {
            if (mkdir($upload_profiles, 0755, true)) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Directory "uploads/profiles/" created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Failed to create "uploads/profiles/" directory</div>';
            }
        } else {
            echo '<div class="log-item log-info"><i class="fas fa-info-circle"></i> Directory "uploads/profiles/" already exists</div>';
        }

        // Create branding subdirectory
        if (!file_exists($upload_branding)) {
            if (mkdir($upload_branding, 0755, true)) {
                echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Directory "uploads/branding/" created</div>';
            } else {
                echo '<div class="log-item log-error"><i class="fas fa-times-circle"></i> Failed to create "uploads/branding/" directory</div>';
            }
        } else {
            echo '<div class="log-item log-info"><i class="fas fa-info-circle"></i> Directory "uploads/branding/" already exists</div>';
        }

        // Test write permissions
        $test_file = $upload_profiles . '/test.txt';
        if (file_put_contents($test_file, 'test') !== false) {
            unlink($test_file);
            echo '<div class="log-item log-success"><i class="fas fa-check-circle"></i> Upload directory is writable (permissions OK)</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-exclamation-triangle"></i> Warning: Upload directory may not be writable. Check permissions.</div>';
        }

        // Create .htaccess for security
        $htaccess_content = "# Protect uploads directory\n";
        $htaccess_content .= "<Files ~ \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|htm|shtml|sh|cgi)$\">\n";
        $htaccess_content .= "    deny from all\n";
        $htaccess_content .= "</Files>\n";
        $htaccess_content .= "\n# Allow image files only\n";
        $htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">\n";
        $htaccess_content .= "    allow from all\n";
        $htaccess_content .= "</FilesMatch>\n";

        $htaccess_file = $upload_base . '/.htaccess';
        if (file_put_contents($htaccess_file, $htaccess_content) !== false) {
            echo '<div class="log-item log-success"><i class="fas fa-shield-alt"></i> Security .htaccess created in uploads/</div>';
        } else {
            echo '<div class="log-item log-error"><i class="fas fa-exclamation-triangle"></i> Warning: Could not create security .htaccess file</div>';
        }

        echo '<br><div class="log-item log-success">';
        echo '<i class="fas fa-check-circle"></i> <strong>Setup completed successfully! All tables created with demo data.</strong>';
        echo '</div>';

        $conn->close();

        // clear old session so login.php shows login form
        session_unset();
        session_destroy();
        ?>

        <a href="login.php" class="btn">
            <i class="fas fa-sign-in-alt"></i> Go to Login Page
        </a>
    </div>

    <!-- Theme Toggle Button -->
    <button class="login-theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>
    </div>

    <script>
    // Theme Toggle for Setup Page
    function initTheme() {
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.body.classList.add('dark-mode');
            updateThemeIcon(true);
        }
    }

    function toggleTheme() {
        const isDark = document.body.classList.toggle('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateThemeIcon(isDark);
    }

    function updateThemeIcon(isDark) {
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    initTheme();
    </script>
</body>
</html>
