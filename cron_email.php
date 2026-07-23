<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * Automated Email Notification Cron
 * Run via: php cron_email.php  OR  visit in browser with ?token=cron_secret_2026
 */

require_once 'config.php';

$is_cli = (php_sapi_name() === 'cli');

// Security: browser access requires token
if (!$is_cli) {
    $token = $_GET['token'] ?? '';
    $cron_token = getSetting('cron_token', 'cron_secret_2026');
    if ($token !== $cron_token) {
        http_response_code(403);
        die('Access denied. Use ?token=YOUR_TOKEN');
    }
}

// ============================================
// Email HTML Template Builder
// ============================================

function buildExpiryEmailHtml($sub, $days_left, $siteName, $logoUrl) {
    $statusLabel = $days_left < 0
        ? 'EXPIRED'
        : ($days_left == 0 ? 'EXPIRING TODAY' : "EXPIRING IN $days_left DAYS");

    $statusColor = $days_left < 0
        ? '#dc3545'
        : ($days_left <= 7 ? '#e65100' : ($days_left <= 30 ? '#ffc107' : '#28a745'));

    $statusTextColor = ($days_left > 7 && $days_left <= 30) ? '#333' : '#fff';

    $customerName = htmlspecialchars($sub['customer_name']);
    $invoiceNo    = htmlspecialchars($sub['invoice_no']);
    $productName = htmlspecialchars($sub['product_name'] ?? 'N/A');
    $expiryDate   = htmlspecialchars($sub['expiry_date']);
    $totalAmount  = number_format((float)$sub['total_amount'], 2);
    $paymentStatus = htmlspecialchars($sub['payment_status']);
    $autoRenew    = $sub['auto_renew'] ? 'Yes' : 'No';
    $priority     = htmlspecialchars($sub['priority'] ?? 'Medium');
    $daysDisplay  = $days_left < 0
        ? abs($days_left) . ' day(s) overdue'
        : ($days_left == 0 ? 'Today' : "$days_left day(s) remaining");
    $siteNameSafe = htmlspecialchars($siteName);
    $logoUrlSafe  = htmlspecialchars($logoUrl);

    $html = '<!DOCTYPE html>'
        . '<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>'
        . '<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:20px 0;">'
        . '<tr><td align="center">'
        . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;margin:0 auto;">'

        // ---- Navy Header ----
        . '<tr><td style="background:#001f3f;padding:24px 30px;text-align:center;border-radius:8px 8px 0 0;">'
        . '<img src="' . $logoUrlSafe . '" alt="Logo" style="width:48px;height:48px;border-radius:50%;margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;">'
        . '<h1 style="color:#ffffff;margin:0;font-size:20px;font-weight:700;">' . $siteNameSafe . '</h1>'
        . '<p style="color:#8eb4e0;margin:4px 0 0;font-size:13px;">Subscription Notification</p>'
        . '</td></tr>'

        // ---- Status Badge ----
        . '<tr><td style="background:#ffffff;padding:24px 30px 0;border-left:1px solid #e9ecef;border-right:1px solid #e9ecef;">'
        . '<div style="text-align:center;margin-bottom:20px;">'
        . '<span style="display:inline-block;background:' . $statusColor . ';color:' . $statusTextColor . ';padding:8px 24px;border-radius:20px;font-size:14px;font-weight:700;letter-spacing:0.5px;">'
        . $statusLabel
        . '</span>'
        . '</div>'

        // ---- Greeting ----
        . '<p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 16px;">A subscription requires your attention. Please review the details below:</p>'

        // ---- Details Table ----
        . '<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e9ecef;border-radius:6px;overflow:hidden;margin-bottom:20px;">'

        . '<tr style="background:#f8f9fa;">'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;width:40%;border-bottom:1px solid #e9ecef;">Customer</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $customerName . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Invoice No</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $invoiceNo . '</td>'
        . '</tr>'

        . '<tr style="background:#f8f9fa;">'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Product</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $productName . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Expiry Date</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;font-weight:700;border-bottom:1px solid #e9ecef;">' . $expiryDate . '</td>'
        . '</tr>'

        . '<tr style="background:#f8f9fa;">'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Days Left</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:' . $statusColor . ';font-weight:700;border-bottom:1px solid #e9ecef;">' . $daysDisplay . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Amount</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $totalAmount . '</td>'
        . '</tr>'

        . '<tr style="background:#f8f9fa;">'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Payment Status</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $paymentStatus . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Priority</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $priority . '</td>'
        . '</tr>'

        . '<tr style="background:#f8f9fa;">'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;">Auto Renew</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;">' . $autoRenew . '</td>'
        . '</tr>'

        . '</table>'
        . '</td></tr>'

        // ---- Footer ----
        . '<tr><td style="background:#f8f9fa;padding:16px 30px;text-align:center;border-radius:0 0 8px 8px;border:1px solid #e9ecef;border-top:none;">'
        . '<p style="color:#999;font-size:12px;margin:0 0 4px;">This is an automated message from <strong>' . $siteNameSafe . '</strong>. Please do not reply.</p>'
        . '<p style="color:#bbb;font-size:11px;margin:0;">Sent on ' . date('Y-m-d H:i:s') . '</p>'
        . '</td></tr>'

        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';

    return $html;
}

// ============================================
// Unpaid Invoice Email Template
// ============================================

function buildUnpaidReminderHtml($sub, $days_overdue, $siteName, $logoUrl) {
    $customerName = htmlspecialchars($sub['customer_name']);
    $invoiceNo    = htmlspecialchars($sub['invoice_no']);
    $productName  = htmlspecialchars($sub['product_name'] ?? 'N/A');
    $invoiceDate  = htmlspecialchars($sub['invoice_date']);
    $totalAmount  = number_format((float)$sub['total_amount'], 2);
    $siteNameSafe = htmlspecialchars($siteName);
    $logoUrlSafe  = htmlspecialchars($logoUrl);

    $badgeColor = $days_overdue > 60 ? '#dc3545' : ($days_overdue > 30 ? '#e65100' : '#ffc107');
    $badgeText  = $days_overdue > 60 ? '#fff' : ($days_overdue > 30 ? '#fff' : '#333');
    $statusLabel = $days_overdue . ' DAYS OVERDUE';

    $html = '<!DOCTYPE html>'
        . '<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>'
        . '<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:20px 0;">'
        . '<tr><td align="center">'
        . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;margin:0 auto;">'

        // header
        . '<tr><td style="background:#001f3f;padding:24px 30px;text-align:center;border-radius:8px 8px 0 0;">'
        . '<img src="' . $logoUrlSafe . '" alt="Logo" style="width:48px;height:48px;border-radius:50%;margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;">'
        . '<h1 style="color:#ffffff;margin:0;font-size:20px;font-weight:700;">' . $siteNameSafe . '</h1>'
        . '<p style="color:#8eb4e0;margin:4px 0 0;font-size:13px;">Payment Reminder</p>'
        . '</td></tr>'

        // badge
        . '<tr><td style="background:#ffffff;padding:24px 30px 0;border-left:1px solid #e9ecef;border-right:1px solid #e9ecef;">'
        . '<div style="text-align:center;margin-bottom:20px;">'
        . '<span style="display:inline-block;background:' . $badgeColor . ';color:' . $badgeText . ';padding:8px 24px;border-radius:20px;font-size:14px;font-weight:700;letter-spacing:0.5px;">'
        . $statusLabel
        . '</span>'
        . '</div>'

        // greeting
        . '<p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 16px;">The following invoice remains unpaid. Please arrange payment at your earliest convenience.</p>'

        // details table
        . '<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e9ecef;border-radius:6px;overflow:hidden;margin-bottom:20px;">'

        . '<tr style="background:#f8f9fa;">'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;width:40%;border-bottom:1px solid #e9ecef;">Customer</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $customerName . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Invoice No</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $invoiceNo . '</td>'
        . '</tr>'

        . '<tr style="background:#f8f9fa;">'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Product</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $productName . '</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Invoice Date</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#333;border-bottom:1px solid #e9ecef;">' . $invoiceDate . '</td>'
        . '</tr>'

        . '<tr style="background:#f8f9fa;">'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;border-bottom:1px solid #e9ecef;">Days Overdue</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:' . $badgeColor . ';font-weight:700;border-bottom:1px solid #e9ecef;">' . $days_overdue . ' day(s)</td>'
        . '</tr>'

        . '<tr>'
        . '<td style="padding:10px 14px;font-size:13px;color:#666;font-weight:600;">Amount Due</td>'
        . '<td style="padding:10px 14px;font-size:13px;color:#dc3545;font-weight:700;">' . $totalAmount . '</td>'
        . '</tr>'

        . '</table>'
        . '</td></tr>'

        // footer
        . '<tr><td style="background:#f8f9fa;padding:16px 30px;text-align:center;border-radius:0 0 8px 8px;border:1px solid #e9ecef;border-top:none;">'
        . '<p style="color:#999;font-size:12px;margin:0 0 4px;">This is an automated payment reminder from <strong>' . $siteNameSafe . '</strong>. Please do not reply.</p>'
        . '<p style="color:#bbb;font-size:11px;margin:0;">Sent on ' . date('Y-m-d H:i:s') . '</p>'
        . '</td></tr>'

        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';

    return $html;
}

// ============================================
// Output Helpers
// ============================================

function cronLog($message, $type = 'info') {
    global $is_cli, $html_logs;

    if ($is_cli) {
        $prefix = '[' . date('Y-m-d H:i:s') . ']';
        $tag = strtoupper($type);
        echo "$prefix [$tag] $message\n";
    } else {
        $iconMap = [
            'success' => 'fa-check-circle',
            'info'    => 'fa-info-circle',
            'error'   => 'fa-times-circle',
            'warning' => 'fa-exclamation-triangle'
        ];
        $icon = $iconMap[$type] ?? 'fa-info-circle';
        $cssClass = 'log-' . $type;
        $html_logs[] = '<div class="log-item ' . $cssClass . '"><i class="fas ' . $icon . '"></i> ' . htmlspecialchars($message) . '</div>';
    }
}

// ============================================
// Main Cron Logic
// ============================================

$html_logs = [];
$sent = 0;
$skipped = 0;
$failed = 0;
$total_checked = 0;

try {
    // 1. Check if auto email is enabled
    $auto_email_enabled = getSetting('auto_email_enabled', 'false');
    if ($auto_email_enabled !== 'true') {
        cronLog('Automated email notifications are disabled (auto_email_enabled != true). Exiting.', 'warning');

        if ($is_cli) {
            exit(0);
        }

        // Browser output for disabled state
        echo '<!DOCTYPE html><html><head><title>Cron Email Report</title>'
            . '<link rel="stylesheet" href="styles.css?v=7.0">'
            . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">'
            . '</head><body>'
            . '<div class="setup-wrapper"><div class="setup-container">'
            . '<h2><i class="fas fa-envelope"></i> Email Cron Report</h2>'
            . '<p class="subtitle">Automated subscription expiry notifications</p><hr>';
        foreach ($html_logs as $log) {
            echo $log;
        }
        echo '</div></div></body></html>';
        exit;
    }

    // 2. Set timezone
    date_default_timezone_set(getSetting('timezone', 'Asia/Kolkata'));

    // 3. Parse notification days
    $days_array = array_map('intval', explode(',', getSetting('notification_days_before', '30,15,7,3,1,0')));
    sort($days_array);

    cronLog('Cron started. Timezone: ' . date_default_timezone_get(), 'info');
    cronLog('Notification days: ' . implode(', ', $days_array), 'info');

    // 4. Get branding
    $branding = getSiteBranding();
    $siteName = $branding['site_name'];
    $logoUrl  = $branding['site_logo'];

    cronLog('Site: ' . $siteName, 'info');

    // 5. Query all subscriptions with expiry_date
    $conn = getDBConnection();

    $sql = "SELECT s.sl, s.customer_name, s.invoice_no, s.expiry_date, s.total_amount,
                   s.payment_status, s.auto_renew, s.priority, s.product_id,
                   u.email AS owner_email, u.full_name AS owner_name, u.user_id AS owner_id,
                   sp.email AS sp_email, sp.name AS sp_name,
                   p.product_name
            FROM subscriptions s
            JOIN users u ON s.added_by = u.user_id
            LEFT JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id
            LEFT JOIN products p ON s.product_id = p.product_id
            WHERE s.expiry_date IS NOT NULL";

    $result = $conn->query($sql);

    if (!$result) {
        cronLog('Database query failed: ' . $conn->error, 'error');
        throw new Exception('Database query failed: ' . $conn->error);
    }

    $subscriptions = [];
    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }
    $result->free();

    cronLog('Found ' . count($subscriptions) . ' subscription(s) with expiry dates.', 'info');

    // 6. Loop through each subscription
    foreach ($subscriptions as $row) {
        $total_checked++;

        try {
            // 6a. Compute days_left
            $today  = new DateTime(date('Y-m-d'));
            $expiry = new DateTime($row['expiry_date']);
            $diff   = (int)$today->diff($expiry)->format('%r%a');

            // 6b. Determine notification_type
            if ($diff >= 0) {
                $notification_type = 'expiry_reminder';
            } else {
                $notification_type = 'expired_alert';
            }

            // 6c. For expiry_reminder: check if diff is in days_array
            if ($notification_type === 'expiry_reminder') {
                if (!in_array($diff, $days_array)) {
                    $skipped++;
                    continue;
                }
            }

            // 6d. For expired_alert: only send if recently expired (1-7 days ago)
            if ($notification_type === 'expired_alert') {
                if ($diff < -7) {
                    $skipped++;
                    continue;
                }
            }

            // 7. Dedup check for owner
            $dedup_stmt = $conn->prepare(
                "SELECT log_id FROM notification_logs
                 WHERE subscription_sl = ? AND notification_type = ? AND days_before_expiry = ? AND DATE(sent_at) = CURDATE()
                 LIMIT 1"
            );
            $dedup_stmt->bind_param("isi", $row['sl'], $notification_type, $diff);
            $dedup_stmt->execute();
            $dedup_result = $dedup_stmt->get_result();
            $already_sent = ($dedup_result->num_rows > 0);
            $dedup_stmt->close();

            if ($already_sent) {
                cronLog('[SL#' . $row['sl'] . '] ' . $row['customer_name'] . ' - Already notified today (days=' . $diff . '). Skipped.', 'info');
                $skipped++;
                continue;
            }

            // 8. Build email HTML
            $emailHtml = buildExpiryEmailHtml($row, $diff, $siteName, $logoUrl);

            // Build subject line
            if ($diff < 0) {
                $subject = "[EXPIRED] " . $row['customer_name'] . " - Invoice " . $row['invoice_no'] . " expired " . abs($diff) . " day(s) ago";
            } elseif ($diff == 0) {
                $subject = "[EXPIRING TODAY] " . $row['customer_name'] . " - Invoice " . $row['invoice_no'];
            } else {
                $subject = "[Expiring in $diff days] " . $row['customer_name'] . " - Invoice " . $row['invoice_no'];
            }

            $bodyPreview = $row['customer_name'] . ' | ' . $row['invoice_no'] . ' | Expiry: ' . $row['expiry_date'] . ' | Days: ' . $diff;

            // 9. Send email to owner
            $owner_email = $row['owner_email'];
            if (!empty($owner_email)) {
                $emailResult = sendEmail($owner_email, $subject, $emailHtml);

                if ($emailResult['success']) {
                    // 10. Log notification
                    logNotification(
                        $row['sl'],
                        $owner_email,
                        ($row['owner_id'] == 1 ? 'admin' : 'user'),
                        $row['owner_name'],
                        $notification_type,
                        $diff,
                        $subject,
                        $bodyPreview,
                        'Sent',
                        null,
                        'system',
                        null
                    );

                    cronLog('[SL#' . $row['sl'] . '] ' . $row['customer_name'] . ' -> ' . $owner_email . ' (days=' . $diff . ') - Sent', 'success');
                    $sent++;
                } else {
                    // Log failed attempt
                    logNotification(
                        $row['sl'],
                        $owner_email,
                        ($row['owner_id'] == 1 ? 'admin' : 'user'),
                        $row['owner_name'],
                        $notification_type,
                        $diff,
                        $subject,
                        $bodyPreview,
                        'Failed',
                        $emailResult['message'],
                        'system',
                        null
                    );

                    cronLog('[SL#' . $row['sl'] . '] ' . $row['customer_name'] . ' -> ' . $owner_email . ' - FAILED: ' . $emailResult['message'], 'error');
                    $failed++;
                }
            } else {
                cronLog('[SL#' . $row['sl'] . '] ' . $row['customer_name'] . ' - Owner has no email. Skipped.', 'warning');
                $skipped++;
            }

            // 11. Send to salesperson if they have an email (with dedup)
            $sp_email = $row['sp_email'] ?? null;
            if (!empty($sp_email)) {
                // dedup check for salesperson
                $sp_dedup = $conn->prepare(
                    "SELECT log_id FROM notification_logs
                     WHERE subscription_sl = ? AND recipient_email = ? AND notification_type = ? AND DATE(sent_at) = CURDATE()
                     LIMIT 1"
                );
                $sp_dedup->bind_param("iss", $row['sl'], $sp_email, $notification_type);
                $sp_dedup->execute();
                $sp_already = ($sp_dedup->get_result()->num_rows > 0);
                $sp_dedup->close();
                if ($sp_already) {
                    cronLog('[SL#' . $row['sl'] . '] SP: ' . $sp_email . ' - Already sent today. Skipped.', 'info');
                    continue;
                }
                $spResult = sendEmail($sp_email, $subject, $emailHtml);

                if ($spResult['success']) {
                    logNotification(
                        $row['sl'],
                        $sp_email,
                        'salesperson',
                        $row['sp_name'],
                        $notification_type,
                        $diff,
                        $subject,
                        $bodyPreview,
                        'Sent',
                        null,
                        'system',
                        null
                    );

                    cronLog('[SL#' . $row['sl'] . '] ' . $row['customer_name'] . ' -> SP: ' . $sp_email . ' - Sent', 'success');
                    $sent++;
                } else {
                    logNotification(
                        $row['sl'],
                        $sp_email,
                        'salesperson',
                        $row['sp_name'],
                        $notification_type,
                        $diff,
                        $subject,
                        $bodyPreview,
                        'Failed',
                        $spResult['message'],
                        'system',
                        null
                    );

                    cronLog('[SL#' . $row['sl'] . '] ' . $row['customer_name'] . ' -> SP: ' . $sp_email . ' - FAILED: ' . $spResult['message'], 'error');
                    $failed++;
                }
            }

        } catch (Exception $innerEx) {
            cronLog('[SL#' . $row['sl'] . '] Error: ' . $innerEx->getMessage(), 'error');
            $failed++;
            continue;
        }
    }

    // ============================================
    // Unpaid Invoice Reminders
    // ============================================

    $unpaid_enabled = getSetting('unpaid_reminder_enabled', '0');
    $unpaid_days = intval(getSetting('unpaid_reminder_days', '30'));

    if ($unpaid_enabled === '1') {
        cronLog('--- Unpaid Invoice Reminders (>' . $unpaid_days . ' days overdue) ---', 'info');

        // active subs, unpaid, older than X days, no reminder in last 7 days
        $unpaid_sql = "SELECT s.sl, s.customer_name, s.invoice_no, s.invoice_date, s.total_amount,
                              s.payment_status, s.product_id, s.customer_id,
                              u.email AS owner_email, u.full_name AS owner_name, u.user_id AS owner_id,
                              sp.email AS sp_email, sp.name AS sp_name,
                              p.product_name,
                              c.email AS customer_email, c.contact_person,
                              DATEDIFF(CURDATE(), s.invoice_date) AS days_overdue
                       FROM subscriptions s
                       JOIN users u ON s.added_by = u.user_id
                       LEFT JOIN salespersons sp ON s.salesperson_id = sp.salesperson_id
                       LEFT JOIN products p ON s.product_id = p.product_id
                       LEFT JOIN customers c ON s.customer_id = c.customer_id
                       WHERE s.payment_status = 'Unpaid'
                         AND s.subscription_status = 'active'
                         AND s.invoice_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
                         AND s.sl NOT IN (
                             SELECT nl.subscription_sl FROM notification_logs nl
                             WHERE nl.notification_type = 'payment_reminder'
                               AND nl.status = 'Sent'
                               AND nl.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         )";

        $unpaid_stmt = $conn->prepare($unpaid_sql);
        $unpaid_stmt->bind_param("i", $unpaid_days);
        $unpaid_stmt->execute();
        $unpaid_result = $unpaid_stmt->get_result();

        $unpaid_subs = [];
        while ($ur = $unpaid_result->fetch_assoc()) {
            $unpaid_subs[] = $ur;
        }
        $unpaid_stmt->close();

        cronLog('Found ' . count($unpaid_subs) . ' unpaid subscription(s) overdue >' . $unpaid_days . ' days.', 'info');

        foreach ($unpaid_subs as $usub) {
            $total_checked++;

            try {
                $days_overdue = (int)$usub['days_overdue'];
                $subject = "Payment Reminder — Invoice " . $usub['invoice_no'];

                $emailHtml = buildUnpaidReminderHtml($usub, $days_overdue, $siteName, $logoUrl);
                $bodyPreview = $usub['customer_name'] . ' | ' . $usub['invoice_no'] . ' | Amt: ' . number_format((float)$usub['total_amount'], 2) . ' | ' . $days_overdue . ' days overdue';

                // recipients: customer email first, fallback to owner
                $recipients = [];

                $cust_email = $usub['customer_email'] ?? null;
                if (!empty($cust_email)) {
                    // enum has no 'customer' type, use 'user'
                    $recipients[] = ['email' => $cust_email, 'type' => 'user', 'name' => $usub['contact_person'] ?? $usub['customer_name']];
                }

                // always cc owner
                if (!empty($usub['owner_email'])) {
                    $recipients[] = ['email' => $usub['owner_email'], 'type' => ($usub['owner_id'] == 1 ? 'admin' : 'user'), 'name' => $usub['owner_name']];
                }

                // salesperson if exists
                if (!empty($usub['sp_email'])) {
                    $recipients[] = ['email' => $usub['sp_email'], 'type' => 'salesperson', 'name' => $usub['sp_name']];
                }

                if (empty($recipients)) {
                    cronLog('[SL#' . $usub['sl'] . '] ' . $usub['customer_name'] . ' - No email recipients. Skipped.', 'warning');
                    $skipped++;
                    continue;
                }

                foreach ($recipients as $rcpt) {
                    $res = sendEmail($rcpt['email'], $subject, $emailHtml);

                    if ($res['success']) {
                        logNotification(
                            $usub['sl'], $rcpt['email'], $rcpt['type'], $rcpt['name'],
                            'payment_reminder', $days_overdue, $subject, $bodyPreview,
                            'Sent', null, 'system', null
                        );
                        cronLog('[SL#' . $usub['sl'] . '] ' . $usub['customer_name'] . ' -> ' . $rcpt['email'] . ' (unpaid ' . $days_overdue . 'd) - Sent', 'success');
                        $sent++;
                    } else {
                        logNotification(
                            $usub['sl'], $rcpt['email'], $rcpt['type'], $rcpt['name'],
                            'payment_reminder', $days_overdue, $subject, $bodyPreview,
                            'Failed', $res['message'], 'system', null
                        );
                        cronLog('[SL#' . $usub['sl'] . '] ' . $usub['customer_name'] . ' -> ' . $rcpt['email'] . ' - FAILED: ' . $res['message'], 'error');
                        $failed++;
                    }
                }

            } catch (Exception $uEx) {
                cronLog('[SL#' . $usub['sl'] . '] Unpaid error: ' . $uEx->getMessage(), 'error');
                $failed++;
                continue;
            }
        }

    } else {
        cronLog('Unpaid invoice reminders are disabled.', 'info');
    }

} catch (Exception $e) {
    cronLog('Fatal error: ' . $e->getMessage(), 'error');
    $failed++;
}

// ============================================
// Summary
// ============================================

$summaryMsg = "Sent: $sent, Skipped: $skipped, Failed: $failed, Total Checked: $total_checked";
cronLog('--- SUMMARY: ' . $summaryMsg . ' ---', 'success');

// Log activity for audit trail
try {
    $ip_for_log = $is_cli ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $_SERVER['REMOTE_ADDR'] = $ip_for_log;
    logActivity(0, 'System', 'Cron Email', "Sent: $sent, Skipped: $skipped, Failed: $failed");
} catch (Exception $e) {
    // Activity logging failure is non-critical
    error_log("Cron email activity log error: " . $e->getMessage());
}

// ============================================
// Browser Output
// ============================================

if (!$is_cli) {
    echo '<!DOCTYPE html>';
    echo '<html lang="en"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">';
    echo '<title>Cron Email Report</title>';
    echo '<link rel="stylesheet" href="styles.css?v=7.0">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">';
    echo '</head><body>';
    echo '<div class="setup-wrapper"><div class="setup-container">';
    echo '<h2><i class="fas fa-envelope"></i> Email Cron Report</h2>';
    echo '<p class="subtitle">Automated subscription expiry notifications</p>';
    echo '<hr>';

    foreach ($html_logs as $log) {
        echo $log;
    }

    echo '<br>';
    echo '<div class="log-item log-success"><strong>Summary:</strong> Sent: ' . $sent . ' | Skipped: ' . $skipped . ' | Failed: ' . $failed . ' | Total Checked: ' . $total_checked . '</div>';
    echo '</div></div>';
    echo '</body></html>';
}
