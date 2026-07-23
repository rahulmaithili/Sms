<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * System Settings Page - Admin Only
 */

require_once 'config.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

$username  = $_SESSION['username'];
$role      = isset($_SESSION['role'])      ? $_SESSION['role']      : 'user';
$user_id   = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'settings';

// Admin-only page
if ($role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// ============================================================
// AJAX HANDLERS
// ============================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    // --------------------------------------------------------
    // getSettings
    // --------------------------------------------------------
    if ($_POST['action'] === 'getSettings') {
        try {
            echo json_encode([
                'success' => true,
                'data'    => [
                    'company_name'               => getSetting('company_name',               'Rameez Scripts'),
                    'company_email'              => getSetting('company_email',              'admin@company.com'),
                    'copyright_text'             => getSetting('copyright_text',             ''),
                    'company_logo_url'           => getSetting('company_logo_url',           '') ?: getSetting('site_logo', ''),
                    'currency'                   => getSetting('currency',                   'INR'),
                    'tax_percentage'             => getSetting('tax_percentage',             '0'),
                    'notification_days_before'   => getSetting('notification_days_before',   '30,15,7,3,1,0'),
                    'auto_email_enabled'         => getSetting('auto_email_enabled',         'true'),
                    'email_frequency'            => getSetting('email_frequency',            'daily'),
                    'date_format'                => getSetting('date_format',                'MM/DD/YYYY'),
                    'timezone'                   => getSetting('timezone',                   'Asia/Kolkata'),
                    'allow_user_profile_uploads' => getSetting('allow_user_profile_uploads', '1'),
                    'show_forgot_password'       => getSetting('show_forgot_password',       '1'),
                    'maintenance_mode'           => getSetting('maintenance_mode',           '0'),
                    'default_language'           => getSetting('default_language',           'en'),
                    'gemini_api_key'             => getSetting('gemini_api_key',             ''),
                    // Company contact
                    'company_website'            => getSetting('company_website',            ''),
                    'company_phone'              => getSetting('company_phone',              ''),
                    // Razorpay
                    'razorpay_enabled'           => getSetting('razorpay_enabled',           '0'),
                    'razorpay_key_id'            => getSetting('razorpay_key_id',            ''),
                    'razorpay_key_secret'        => getSetting('razorpay_key_secret',        ''),
                    'razorpay_mode'              => getSetting('razorpay_mode',              'test'),
                ]
            ]);
        } catch (Exception $e) {
            error_log("settings.php getSettings error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to load settings: ' . $e->getMessage()]);
        }
        exit();
    }

    // --------------------------------------------------------
    // saveSettings
    // --------------------------------------------------------
    if ($_POST['action'] === 'saveSettings') {
        try {
            // Collect & sanitize
            $company_name             = trim($_POST['company_name']             ?? '');
            $company_email            = trim($_POST['company_email']            ?? '');
            $copyright_text           = trim($_POST['copyright_text']           ?? '');
            $currency                 = trim($_POST['currency']                 ?? 'INR');
            $tax_percentage           = trim($_POST['tax_percentage']           ?? '0');
            $notification_days_before = trim($_POST['notification_days_before'] ?? '30,15,7,3,1,0');
            $auto_email_enabled       = trim($_POST['auto_email_enabled']       ?? 'false');
            $email_frequency          = trim($_POST['email_frequency']          ?? 'daily');
            $date_format              = trim($_POST['date_format']              ?? 'MM/DD/YYYY');
            $timezone                 = trim($_POST['timezone']                 ?? 'Asia/Kolkata');
            $allow_user_uploads       = trim($_POST['allow_user_profile_uploads'] ?? '0');
            $show_forgot_password     = trim($_POST['show_forgot_password']     ?? '0');
            $maintenance_mode         = trim($_POST['maintenance_mode']         ?? '0');
            $default_language         = trim($_POST['default_language']         ?? 'en');
            $gemini_api_key           = trim($_POST['gemini_api_key']           ?? '');

            // Validations
            if (!empty($company_email) && !filter_var($company_email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid company email address format.']);
                exit();
            }

            if (!is_numeric($tax_percentage) || (float)$tax_percentage < 0 || (float)$tax_percentage > 100) {
                echo json_encode(['success' => false, 'message' => 'Tax percentage must be a number between 0 and 100.']);
                exit();
            }

            if (!in_array($email_frequency, ['daily', 'weekly'], true)) {
                echo json_encode(['success' => false, 'message' => 'Email frequency must be "daily" or "weekly".']);
                exit();
            }

            if (!in_array($auto_email_enabled, ['true', 'false'], true)) {
                $auto_email_enabled = 'false';
            }

            // Validate notification_days_before: comma-separated integers
            $days_parts = array_map('trim', explode(',', $notification_days_before));
            foreach ($days_parts as $d) {
                if ($d !== '' && !ctype_digit($d)) {
                    echo json_encode(['success' => false, 'message' => 'Notification days must be comma-separated numbers (e.g. 30,15,7,3,1,0).']);
                    exit();
                }
            }
            // Reconstruct clean value
            $notification_days_before = implode(',', array_filter(array_map('intval', $days_parts), function($v) { return $v >= 0; }));

            // Allowed currency codes
            $allowed_currencies = ['INR','USD','EUR','GBP','AED','SAR','BHD','PKR'];
            if (!in_array($currency, $allowed_currencies, true)) {
                $currency = 'INR';
            }

            // Allowed date formats
            $allowed_formats = ['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD'];
            if (!in_array($date_format, $allowed_formats, true)) {
                $date_format = 'MM/DD/YYYY';
            }

            // Allowed languages
            $allowed_langs = ['en','hi','ar','ur','fr','es'];
            if (!in_array($default_language, $allowed_langs, true)) {
                $default_language = 'en';
            }

            // Boolean toggles must be '0' or '1'
            $allow_user_uploads   = $allow_user_uploads   === '1' ? '1' : '0';
            $show_forgot_password = $show_forgot_password === '1' ? '1' : '0';
            $maintenance_mode     = $maintenance_mode     === '1' ? '1' : '0';

            // Timezone: validate against PHP list
            $valid_timezones = DateTimeZone::listIdentifiers();
            if (!in_array($timezone, $valid_timezones, true)) {
                $timezone = 'Asia/Kolkata';
            }

            // Persist all settings with updated_by audit trail
            setSetting('company_name',               $company_name,             $user_id);
            setSetting('company_email',              $company_email,            $user_id);
            setSetting('copyright_text',             $copyright_text,           $user_id);
            setSetting('currency',                   $currency,                 $user_id);
            setSetting('tax_percentage',             $tax_percentage,           $user_id);
            setSetting('notification_days_before',   $notification_days_before, $user_id);
            setSetting('auto_email_enabled',         $auto_email_enabled,       $user_id);
            setSetting('email_frequency',            $email_frequency,          $user_id);
            setSetting('date_format',                $date_format,              $user_id);
            setSetting('timezone',                   $timezone,                 $user_id);
            setSetting('allow_user_profile_uploads', $allow_user_uploads,       $user_id);
            setSetting('show_forgot_password',       $show_forgot_password,     $user_id);
            setSetting('maintenance_mode',           $maintenance_mode,         $user_id);
            setSetting('default_language',           $default_language,         $user_id);
            setSetting('gemini_api_key',             $gemini_api_key,           $user_id);
            setSetting('company_website',            trim($_POST['company_website'] ?? ''), $user_id);
            setSetting('company_phone',              trim($_POST['company_phone']   ?? ''), $user_id);

            // Legacy / backward-compat sync
            setSetting('site_name', $company_name, $user_id);

            // Audit log
            logActivity($user_id, $username, 'Settings Updated', 'System settings updated');

            echo json_encode(['success' => true, 'message' => 'Settings saved successfully.']);
        } catch (Exception $e) {
            error_log("settings.php saveSettings error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $e->getMessage()]);
        }
        exit();
    }

    // --------------------------------------------------------
    // uploadCompanyLogo
    // --------------------------------------------------------
    if ($_POST['action'] === 'uploadCompanyLogo') {
        try {
            if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
                $upload_err = $_FILES['logo_file']['error'] ?? 99;
                echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error (code ' . $upload_err . ').']);
                exit();
            }

            $file = $_FILES['logo_file'];

            // Max 2 MB
            if ($file['size'] > 2 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File size must be less than 2MB.']);
                exit();
            }

            // Validate MIME type
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $finfo     = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_mimes, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP, SVG.']);
                exit();
            }

            // Validate extension
            $extension        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts     = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            if (!in_array($extension, $allowed_exts, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file extension.']);
                exit();
            }

            // Ensure upload directory exists
            $upload_dir = __DIR__ . '/uploads/branding/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $filename     = 'site_logo_' . time() . '.' . $extension;
            $filepath_abs = $upload_dir . $filename;
            $filepath_rel = 'uploads/branding/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $filepath_abs)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
                exit();
            }

            // Delete old local logo if it was a local path
            $old_logo = getSetting('company_logo_url', '') ?: getSetting('site_logo', '');
            if (!empty($old_logo) && strpos($old_logo, 'uploads/') === 0) {
                $old_abs = __DIR__ . '/' . $old_logo;
                if (file_exists($old_abs)) {
                    @unlink($old_abs);
                }
            }

            // Persist
            setSetting('company_logo_url', $filepath_rel, $user_id);
            setSetting('site_logo',        $filepath_rel, $user_id);

            logActivity($user_id, $username, 'Logo Updated', 'Company logo uploaded: ' . $filename);

            echo json_encode([
                'success'   => true,
                'message'   => 'Logo uploaded successfully.',
                'logo_path' => $filepath_rel
            ]);
        } catch (Exception $e) {
            error_log("settings.php uploadCompanyLogo error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
        }
        exit();
    }

    // --------------------------------------------------------
    // saveRazorpaySettings
    // --------------------------------------------------------
    if ($_POST['action'] === 'saveRazorpaySettings') {
        try {
            $rzp_enabled    = isset($_POST['razorpay_enabled'])    && $_POST['razorpay_enabled'] === '1' ? '1' : '0';
            $rzp_key_id     = trim($_POST['razorpay_key_id']     ?? '');
            $rzp_key_secret = trim($_POST['razorpay_key_secret'] ?? '');
            $rzp_mode       = in_array(trim($_POST['razorpay_mode'] ?? 'test'), ['test','live'], true)
                              ? trim($_POST['razorpay_mode']) : 'test';

            if ($rzp_enabled === '1') {
                if (empty($rzp_key_id)) {
                    echo json_encode(['success' => false, 'message' => 'Razorpay Key ID is required when gateway is enabled.']);
                    exit();
                }
                if (empty($rzp_key_secret)) {
                    echo json_encode(['success' => false, 'message' => 'Razorpay Key Secret is required when gateway is enabled.']);
                    exit();
                }
                // Basic format check
                if (!str_starts_with($rzp_key_id, 'rzp_')) {
                    echo json_encode(['success' => false, 'message' => 'Invalid Razorpay Key ID format. It should start with "rzp_".']);
                    exit();
                }
            }

            setSetting('razorpay_enabled',    $rzp_enabled,    $user_id);
            setSetting('razorpay_key_id',     $rzp_key_id,     $user_id);
            setSetting('razorpay_key_secret', $rzp_key_secret, $user_id);
            setSetting('razorpay_mode',       $rzp_mode,       $user_id);

            logActivity($user_id, $username, 'Razorpay Settings Updated',
                'Razorpay gateway ' . ($rzp_enabled === '1' ? 'enabled' : 'disabled') . ', mode: ' . $rzp_mode);

            echo json_encode(['success' => true, 'message' => 'Razorpay settings saved successfully.']);
        } catch (Exception $e) {
            error_log("settings.php saveRazorpaySettings error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to save Razorpay settings: ' . $e->getMessage()]);
        }
        exit();
    }

    // Unknown action
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}
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
    <title>Site Settings - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <style>
        /* Settings-specific: Logo preview */
        .logo-preview-wrap { display:flex;flex-direction:column;align-items:flex-start;gap:10px;margin-top:8px; }
        .logo-preview { width:100px;height:100px;object-fit:contain;border:2px dashed #ced4da;border-radius:4px;background:#f8f9fa;padding:4px; }
        .logo-preview.hidden { display:none; }
        .logo-upload-btn { display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:var(--navy-accent,#0074D9);color:#fff;border:none;border-radius:3px;font-size:14px;font-weight:600;cursor:pointer;transition:all .3s; }
        .logo-upload-btn:hover { background:#005bb5;transform:translateY(-2px); }
        .logo-upload-status { font-size:12px;color:#7a8fa6; }

        /* Skeleton */
        .skeleton-card-settings { background:#fff;border-radius:12px;padding:30px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,31,63,.06); }

        /* Maintenance warning */
        .maintenance-warning { display:none;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:10px 14px;margin-top:10px;font-size:13px;color:#856404;align-items:center;gap:8px; }
        .maintenance-warning.visible { display:flex; }
        .maintenance-warning i { font-size:16px;color:#e67e00; }

        /* Save button */
        .save-all-wrap { text-align:center;margin-top:20px; }
        .btn-save-all { display:inline-flex;align-items:center;gap:10px;padding:14px 40px;background:linear-gradient(135deg,var(--navy-accent,#0074D9) 0%,#005bb5 100%);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:all .3s;width:100%;justify-content:center;touch-action:manipulation; }
        .btn-save-all:hover { opacity:.9;transform:translateY(-2px); }
        .btn-save-all:active { transform:scale(.98); }
        .btn-save-all:disabled { opacity:.65;cursor:not-allowed; }
    </style>
</head>
<body>
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span>System</span>
                <span class="breadcrumb-sep">/</span>
                <span>Site Settings</span>
            </div>

            <!-- Page Header -->
            <div class="header">
                <h1><i class="fas fa-cogs"></i> Site Settings</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <!-- ── Skeleton state (shown while loading) ──────────── -->
            <div id="skeletonWrap">
                <div class="skeleton-card-settings">
                    <div class="skeleton skeleton-text-large skeleton-w-50 skeleton-mb-md"></div>
                    <div class="skeleton skeleton-text skeleton-w-70"></div>
                </div>
                <div class="skeleton-card-settings">
                    <div class="skeleton skeleton-text-large skeleton-w-50 skeleton-mb-md"></div>
                    <div class="skeleton skeleton-text skeleton-w-70"></div>
                </div>
            </div>

            <!-- ── Actual settings form (hidden until loaded) ────── -->
            <div id="settingsWrap" style="display:none;">

                <!-- ───────────────────────────────────────────── -->
                <!-- Card 1: Company Information (full width)      -->
                <!-- ───────────────────────────────────────────── -->
                <div class="settings-mega-card mb-24">
                    <div class="settings-card-header">
                        <div class="settings-card-icon icon-gradient-navy">
                            <i class="fas fa-building"></i>
                        </div>
                        <div>
                            <h3 class="settings-card-title">Company Information</h3>
                            <p class="settings-card-subtitle">Manage your company details and branding</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Company Name</label>
                                <input type="text" id="companyName" placeholder="Rameez Scripts" maxlength="150">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Company Email</label>
                                <input type="email" id="companyEmail" placeholder="admin@company.com" maxlength="150">
                            </div>
                            <div class="form-group span-2">
                                <label><i class="fas fa-copyright"></i> Copyright Text</label>
                                <input type="text" id="copyrightText" placeholder="&copy; 2026 My Company. All rights reserved." maxlength="255">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-globe"></i> Website URL</label>
                                <input type="url" id="companyWebsite" placeholder="https://www.mycompany.com" maxlength="255">
                                <div class="help-text">Shown on the About page</div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="text" id="companyPhone" placeholder="+91 98765 43210" maxlength="50">
                                <div class="help-text">Shown on the About page</div>
                            </div>
                            <div class="form-group span-2">
                                <label><i class="fas fa-image"></i> Company Logo</label>
                                <div class="logo-preview-wrap">
                                    <img id="logoPreview" src="" alt="Company Logo" class="logo-preview hidden">
                                    <label class="logo-upload-btn" for="logoFile">
                                        <i class="fas fa-upload"></i> Choose Logo
                                    </label>
                                    <input type="file" id="logoFile" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" style="display:none;">
                                    <span class="logo-upload-status" id="logoUploadStatus">JPG, PNG, GIF, WEBP, SVG — max 2MB</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ───────────────────────────────────────────── -->
                <!-- Cards 2 & 3: 2x2 grid                        -->
                <!-- ───────────────────────────────────────────── -->
                <div class="settings-grid-2x2 mb-24">

                    <!-- Card 2: Currency & Finance -->
                    <div class="settings-mega-card">
                        <div class="settings-card-header">
                            <div class="settings-card-icon icon-gradient-navy">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div>
                                <h3 class="settings-card-title">Currency &amp; Finance</h3>
                                <p class="settings-card-subtitle">Default currency and tax settings</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="form-group">
                                <label><i class="fas fa-money-bill-wave"></i> Currency</label>
                                <select id="currency">
                                    <option value="INR">INR &#x20B9; — Indian Rupee</option>
                                    <option value="USD">USD $ — US Dollar</option>
                                    <option value="EUR">EUR &euro; — Euro</option>
                                    <option value="GBP">GBP &pound; — British Pound</option>
                                    <option value="AED">AED &#x62F;.&#x625; — UAE Dirham</option>
                                    <option value="SAR">SAR &#xFDAC; — Saudi Riyal</option>
                                    <option value="BHD">BHD .&#x62F;.&#x628; — Bahraini Dinar</option>
                                    <option value="PKR">PKR Rs — Pakistani Rupee</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-percent"></i> Tax Percentage</label>
                                <input type="number" id="taxPercentage" min="0" max="100" step="0.01" placeholder="0">
                                <div class="help-text">Enter a value between 0 and 100.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Date & Time -->
                    <div class="settings-mega-card">
                        <div class="settings-card-header">
                            <div class="settings-card-icon icon-gradient-navy">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h3 class="settings-card-title">Date &amp; Time</h3>
                                <p class="settings-card-subtitle">Display format and timezone preferences</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Date Format</label>
                                <select id="dateFormat">
                                    <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                                    <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                                    <option value="YYYY-MM-DD">YYYY-MM-DD (ISO 8601)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-globe"></i> Timezone</label>
                                <select id="timezone">
                                    <option value="Asia/Kolkata">Asia/Kolkata (IST, UTC+5:30)</option>
                                    <option value="Asia/Karachi">Asia/Karachi (PKT, UTC+5:00)</option>
                                    <option value="Asia/Dhaka">Asia/Dhaka (BST, UTC+6:00)</option>
                                    <option value="Asia/Dubai">Asia/Dubai (GST, UTC+4:00)</option>
                                    <option value="Asia/Riyadh">Asia/Riyadh (AST, UTC+3:00)</option>
                                    <option value="Asia/Bahrain">Asia/Bahrain (AST, UTC+3:00)</option>
                                    <option value="Asia/Colombo">Asia/Colombo (SLST, UTC+5:30)</option>
                                    <option value="Asia/Singapore">Asia/Singapore (SGT, UTC+8:00)</option>
                                    <option value="Asia/Tokyo">Asia/Tokyo (JST, UTC+9:00)</option>
                                    <option value="Asia/Shanghai">Asia/Shanghai (CST, UTC+8:00)</option>
                                    <option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur (MYT, UTC+8:00)</option>
                                    <option value="Asia/Bangkok">Asia/Bangkok (ICT, UTC+7:00)</option>
                                    <option value="Europe/London">Europe/London (GMT/BST)</option>
                                    <option value="Europe/Paris">Europe/Paris (CET/CEST, UTC+1/+2)</option>
                                    <option value="Europe/Berlin">Europe/Berlin (CET/CEST, UTC+1/+2)</option>
                                    <option value="Europe/Moscow">Europe/Moscow (MSK, UTC+3:00)</option>
                                    <option value="America/New_York">America/New_York (ET, UTC-5/-4)</option>
                                    <option value="America/Chicago">America/Chicago (CT, UTC-6/-5)</option>
                                    <option value="America/Denver">America/Denver (MT, UTC-7/-6)</option>
                                    <option value="America/Los_Angeles">America/Los_Angeles (PT, UTC-8/-7)</option>
                                    <option value="America/Toronto">America/Toronto (ET, UTC-5/-4)</option>
                                    <option value="America/Sao_Paulo">America/Sao_Paulo (BRT, UTC-3:00)</option>
                                    <option value="Africa/Cairo">Africa/Cairo (EET, UTC+2:00)</option>
                                    <option value="Africa/Lagos">Africa/Lagos (WAT, UTC+1:00)</option>
                                    <option value="Africa/Johannesburg">Africa/Johannesburg (SAST, UTC+2:00)</option>
                                    <option value="Australia/Sydney">Australia/Sydney (AEDT/AEST)</option>
                                    <option value="Australia/Melbourne">Australia/Melbourne (AEDT/AEST)</option>
                                    <option value="Pacific/Auckland">Pacific/Auckland (NZDT/NZST)</option>
                                    <option value="UTC">UTC (Coordinated Universal Time)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                </div><!-- /.settings-grid-2x2 -->

                <!-- ───────────────────────────────────────────── -->
                <!-- Card 4: Notification Settings (full width)    -->
                <!-- ───────────────────────────────────────────── -->
                <div class="settings-mega-card mb-24">
                    <div class="settings-card-header">
                        <div class="settings-card-icon icon-gradient-navy">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div>
                            <h3 class="settings-card-title">Notification Settings</h3>
                            <p class="settings-card-subtitle">Configure automated email notification preferences</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label><i class="fas fa-calendar-check"></i> Notification Days Before Expiry</label>
                                <input type="text" id="notificationDays" placeholder="30,15,7,3,1,0">
                                <div class="help-text">Comma-separated days before expiry to trigger email alerts (e.g. 30,15,7,3,1,0).</div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-envelope-open-text"></i> Email Frequency</label>
                                <select id="emailFrequency">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                </select>
                            </div>
                        </div>
                        <div class="control-group">
                            <div class="control-group-header">
                                <div class="control-icon"><i class="fas fa-robot"></i></div>
                                <div class="control-info">
                                    <div class="control-title">Auto Email Enabled</div>
                                    <div class="control-desc">Automatically send subscription expiry email alerts</div>
                                </div>
                            </div>
                            <div class="control-toggle-wrapper">
                                <div class="toggle-switch-large">
                                    <input type="checkbox" id="autoEmailEnabled" class="toggle-input-large">
                                    <label for="autoEmailEnabled" class="toggle-label-large">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="toggle-status" id="autoEmailToggleStatus">
                                    <span class="status-dot status-disabled"></span>
                                    <span class="status-text" id="autoEmailLabel">Disabled</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ───────────────────────────────────────────── -->
                <!-- Card 5: System Behavior (full width)          -->
                <!-- ───────────────────────────────────────────── -->
                <div class="settings-mega-card mb-24">
                    <div class="settings-card-header">
                        <div class="settings-card-icon icon-gradient-navy">
                            <i class="fas fa-sliders-h"></i>
                        </div>
                        <div>
                            <h3 class="settings-card-title">System Behavior</h3>
                            <p class="settings-card-subtitle">Application-wide behavior controls</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <div class="control-group">
                            <div class="control-group-header">
                                <div class="control-icon"><i class="fas fa-user-circle"></i></div>
                                <div class="control-info">
                                    <div class="control-title">Allow Profile Uploads</div>
                                    <div class="control-desc">Users can upload their own profile pictures</div>
                                </div>
                            </div>
                            <div class="control-toggle-wrapper">
                                <div class="toggle-switch-large">
                                    <input type="checkbox" id="allowUserUploads" class="toggle-input-large">
                                    <label for="allowUserUploads" class="toggle-label-large">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="toggle-status" id="allowUploadsToggleStatus">
                                    <span class="status-dot status-disabled"></span>
                                    <span class="status-text" id="allowUploadsLabel">Disabled</span>
                                </div>
                            </div>
                        </div>
                        <div class="control-group">
                            <div class="control-group-header">
                                <div class="control-icon"><i class="fas fa-key"></i></div>
                                <div class="control-info">
                                    <div class="control-title">Show Forgot Password</div>
                                    <div class="control-desc">Display the "Forgot Password" link on the login page</div>
                                </div>
                            </div>
                            <div class="control-toggle-wrapper">
                                <div class="toggle-switch-large">
                                    <input type="checkbox" id="showForgotPassword" class="toggle-input-large">
                                    <label for="showForgotPassword" class="toggle-label-large">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="toggle-status" id="forgotPwToggleStatus">
                                    <span class="status-dot status-disabled"></span>
                                    <span class="status-text" id="forgotPwLabel">Disabled</span>
                                </div>
                            </div>
                        </div>
                        <div class="control-group">
                            <div class="control-group-header">
                                <div class="control-icon"><i class="fas fa-tools" style="color:#e67e00;"></i></div>
                                <div class="control-info">
                                    <div class="control-title">Maintenance Mode</div>
                                    <div class="control-desc">When enabled, non-admin users cannot log in</div>
                                </div>
                            </div>
                            <div class="control-toggle-wrapper">
                                <div class="toggle-switch-large">
                                    <input type="checkbox" id="maintenanceMode" class="toggle-input-large" onchange="toggleMaintenanceWarning(this.checked)">
                                    <label for="maintenanceMode" class="toggle-label-large">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="toggle-status" id="maintenanceToggleStatus">
                                    <span class="status-dot status-disabled"></span>
                                    <span class="status-text" id="maintenanceLabel">Disabled</span>
                                </div>
                            </div>
                        </div>
                        <div class="maintenance-warning" id="maintenanceWarning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span><strong>Warning:</strong> Maintenance Mode is ON. Regular users will be locked out.</span>
                        </div>
                        <div class="form-group" style="margin-top:16px;">
                            <label><i class="fas fa-language"></i> Default Language</label>
                            <select id="defaultLanguage">
                                <option value="en">English</option>
                                <option value="hi">Hindi</option>
                                <option value="ar">Arabic</option>
                                <option value="ur">Urdu</option>
                                <option value="fr">French</option>
                                <option value="es">Spanish</option>
                            </select>
                            <div class="help-text">System-wide default language for the interface.</div>
                        </div>
                    </div>
                </div>

                <!-- AI Chat Settings -->
                <div class="settings-mega-card mb-24">
                    <div class="settings-card-header">
                        <div class="settings-card-icon icon-gradient-navy">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div>
                            <h3 class="settings-card-title">AI Chat Settings</h3>
                            <p class="settings-card-subtitle">Configure Google Gemini AI for the chat assistant</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label><i class="fas fa-key"></i> Google Gemini API Key</label>
                                <input type="text" id="settGeminiApiKey" placeholder="AIzaSy..." style="font-size:16px;">
                                <div class="help-text">Get your API key from <a href="https://aistudio.google.com/apikey" target="_blank" style="color:#0074D9;">Google AI Studio</a></div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- ── Razorpay Payment Gateway ─────────────────── -->
                <div class="settings-mega-card mb-24">
                    <div class="settings-card-header">
                        <div class="settings-card-icon icon-gradient-navy">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div>
                            <h3 class="settings-card-title">Razorpay Payment Gateway</h3>
                            <p class="settings-card-subtitle">Allow customers to pay online via UPI, Card, NetBanking &amp; Wallets</p>
                        </div>
                    </div>
                    <div class="settings-card-body">
                        <div class="form-grid">
                            <!-- Enable Toggle -->
                            <div class="form-group span-2" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                                <div>
                                    <label style="margin-bottom:2px;"><i class="fas fa-toggle-on"></i> Enable Razorpay</label>
                                    <div class="help-text">Turn on to show "Pay Now" button to customers on their portal</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="razorpayEnabled">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <!-- Mode -->
                            <div class="form-group">
                                <label><i class="fas fa-flask"></i> Mode</label>
                                <select id="razorpayMode">
                                    <option value="test">🧪 Test Mode (No real money)</option>
                                    <option value="live">🚀 Live Mode (Real payments)</option>
                                </select>
                                <div class="help-text">Use Test mode while testing. Switch to Live only when ready.</div>
                            </div>
                            <!-- Key ID -->
                            <div class="form-group">
                                <label><i class="fas fa-key"></i> Key ID</label>
                                <input type="text" id="razorpayKeyId" placeholder="rzp_test_XXXXXXXXXX" autocomplete="off">
                                <div class="help-text">Starts with <code>rzp_test_</code> (test) or <code>rzp_live_</code> (live)</div>
                            </div>
                            <!-- Key Secret -->
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Key Secret</label>
                                <input type="password" id="razorpayKeySecret" placeholder="••••••••••••••••••" autocomplete="new-password">
                                <div class="help-text">Never share this. It is stored securely.</div>
                            </div>
                            <!-- Info box -->
                            <div class="form-group span-2">
                                <div style="background:#e8f4fd;border:1px solid #b8daff;border-radius:6px;padding:12px 16px;font-size:13px;color:#004085;display:flex;gap:10px;align-items:flex-start;">
                                    <i class="fas fa-info-circle" style="margin-top:2px;color:#0074D9;"></i>
                                    <div>
                                        Get your API keys from <a href="https://dashboard.razorpay.com/app/keys" target="_blank" style="color:#0056b3;font-weight:600;">Razorpay Dashboard → Settings → API Keys</a>.
                                        <br>Free account at <a href="https://razorpay.com" target="_blank" style="color:#0056b3;">razorpay.com</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Save button for Razorpay (separate from main save) -->
                        <div style="margin-top:16px;">
                            <button class="btn btn-primary" id="saveRazorpayBtn" onclick="saveRazorpaySettings()" style="display:inline-flex;align-items:center;gap:8px;padding:10px 24px;background:#0074D9;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;">
                                <i class="fas fa-save"></i> Save Razorpay Settings
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Save All Button ──────────────────────────── -->
                <div class="save-all-wrap">
                    <button class="btn-save-all" id="saveAllBtn" onclick="saveSettings()">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                </div>


            </div><!-- /#settingsWrap -->

        </div><!-- /.main-content -->
    </div><!-- /.app-container -->

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    // ============================================================
    // Helpers
    // ============================================================
    function setToggle(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        el.checked = (value === true || value === '1' || value === 'true' || value === 1);
    }
    function getToggle(id) {
        const el = document.getElementById(id);
        return (el && el.checked) ? '1' : '0';
    }
    function setVal(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value || '';
    }
    function getVal(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    // ============================================================
    // Load Settings
    // ============================================================
    function loadSettings() {
        $.ajax({
            url: window.location.pathname,
            method: 'POST',
            data: { action: 'getSettings' },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load settings.' });
                    return;
                }

                const d = response.data;

                // Card 1: Company Information
                setVal('companyName',     d.company_name);
                setVal('companyEmail',    d.company_email);
                setVal('copyrightText',   d.copyright_text);
                setVal('companyWebsite',  d.company_website || '');
                setVal('companyPhone',    d.company_phone   || '');

                // Logo
                const logoPreview = document.getElementById('logoPreview');
                if (d.company_logo_url) {
                    logoPreview.src = d.company_logo_url;
                    logoPreview.classList.remove('hidden');
                }

                // Card 2: Currency & Finance
                setVal('currency',      d.currency);
                setVal('taxPercentage', d.tax_percentage);

                // Card 3: Notifications
                setVal('notificationDays', d.notification_days_before);
                setToggle('autoEmailEnabled', d.auto_email_enabled === 'true' || d.auto_email_enabled === true);
                updateAutoEmailLabel(d.auto_email_enabled === 'true' || d.auto_email_enabled === true);
                setVal('emailFrequency', d.email_frequency);

                // Card 4: Date & Time
                setVal('dateFormat', d.date_format);
                setVal('timezone',   d.timezone);

                // Card 5: System Behavior
                setToggle('allowUserUploads',   d.allow_user_profile_uploads);
                updateToggleLabel('allowUserUploads', 'allowUploadsLabel');
                setToggle('showForgotPassword', d.show_forgot_password);
                updateToggleLabel('showForgotPassword', 'forgotPwLabel');
                setToggle('maintenanceMode',    d.maintenance_mode);
                updateToggleLabel('maintenanceMode', 'maintenanceLabel');
                toggleMaintenanceWarning(d.maintenance_mode === '1' || d.maintenance_mode === 1);
                setVal('defaultLanguage', d.default_language);
                document.getElementById('settGeminiApiKey').value = d.gemini_api_key || '';

                // Razorpay Settings
                setToggle('razorpayEnabled', d.razorpay_enabled);
                setVal('razorpayMode',      d.razorpay_mode      || 'test');
                setVal('razorpayKeyId',     d.razorpay_key_id    || '');
                // Never prefill secret — only show placeholder
                document.getElementById('razorpayKeySecret').placeholder = d.razorpay_key_secret ? '(saved — enter new to change)' : '••••••••••••••••••';

                // Show form, hide skeleton
                document.getElementById('skeletonWrap').style.display = 'none';
                document.getElementById('settingsWrap').style.display  = 'block';
            },
            error: function(xhr, status, error) {
                console.error('loadSettings AJAX error:', error);
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not load settings. Please refresh the page.' });
            }
        });
    }

    // ============================================================
    // Save Settings
    // ============================================================
    function saveSettings() {
        const btn = document.getElementById('saveAllBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

        const autoEmail = document.getElementById('autoEmailEnabled').checked ? 'true' : 'false';

        $.ajax({
            url: window.location.pathname,
            method: 'POST',
            data: {
                action:                    'saveSettings',
                company_name:              getVal('companyName'),
                company_email:             getVal('companyEmail'),
                copyright_text:            getVal('copyrightText'),
                currency:                  getVal('currency'),
                tax_percentage:            getVal('taxPercentage'),
                notification_days_before:  getVal('notificationDays'),
                auto_email_enabled:        autoEmail,
                email_frequency:           getVal('emailFrequency'),
                date_format:               getVal('dateFormat'),
                timezone:                  getVal('timezone'),
                allow_user_profile_uploads: getToggle('allowUserUploads'),
                show_forgot_password:       getToggle('showForgotPassword'),
                maintenance_mode:           getToggle('maintenanceMode'),
                default_language:           getVal('defaultLanguage'),
                gemini_api_key:            document.getElementById('settGeminiApiKey').value,
                company_website:           getVal('companyWebsite'),
                company_phone:             getVal('companyPhone')
            },
            dataType: 'json',
            success: function(response) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save All Settings';

                if (response.success) {
                    // clear language cache so new language takes effect
                    var newLang = getVal('defaultLanguage');
                    localStorage.removeItem('lang_reverted_to_english');
                    localStorage.setItem('admin_default_language', newLang);
                    // clear old googtrans cookies
                    document.cookie = 'googtrans=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT';
                    document.cookie = 'googtrans=;path=/;domain=' + window.location.hostname + ';expires=Thu, 01 Jan 1970 00:00:00 GMT';
                    // set new language cookie
                    if (newLang && newLang !== 'en') {
                        document.cookie = 'googtrans=/en/' + newLang + ';path=/';
                        document.cookie = 'googtrans=/en/' + newLang + ';path=/;domain=' + window.location.hostname;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: response.message,
                        timer: 2200,
                        showConfirmButton: false
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Validation Error', text: response.message });
                }
            },
            error: function(xhr, status, error) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save All Settings';
                console.error('saveSettings AJAX error:', error);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to save settings. Please try again.' });
            }
        });
    }

    // ============================================================
    // Save Razorpay Settings
    // ============================================================
    function saveRazorpaySettings() {
        const btn = document.getElementById('saveRazorpayBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

        const secret = document.getElementById('razorpayKeySecret').value.trim();

        const data = {
            action:            'saveRazorpaySettings',
            razorpay_enabled:  getToggle('razorpayEnabled'),
            razorpay_mode:     getVal('razorpayMode'),
            razorpay_key_id:   getVal('razorpayKeyId'),
            razorpay_key_secret: secret
        };

        $.ajax({
            url: window.location.pathname,
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Razorpay Settings';
                if (response.success) {
                    Swal.fire({ icon: 'success', title: 'Saved!', text: response.message, timer: 2000, showConfirmButton: false });
                    document.getElementById('razorpayKeySecret').value = '';
                    document.getElementById('razorpayKeySecret').placeholder = '(saved — enter new to change)';
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                }
            },
            error: function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Razorpay Settings';
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to save Razorpay settings. Please try again.' });
            }
        });
    }

    // ============================================================
    // Maintenance mode warning toggle

    // ============================================================
    function toggleMaintenanceWarning(enabled) {
        const warn = document.getElementById('maintenanceWarning');
        if (enabled) {
            warn.classList.add('visible');
        } else {
            warn.classList.remove('visible');
        }
    }

    // ============================================================
    // Toggle status updater (text + dot color, matching SMTP pattern)
    // ============================================================
    function updateToggleStatus(checkboxId, statusWrapperId, labelId) {
        var cb = document.getElementById(checkboxId);
        if (!cb) return;
        var isOn = cb.checked;

        // Update label text
        var lbl = document.getElementById(labelId);
        if (lbl) lbl.textContent = isOn ? 'Enabled' : 'Disabled';

        // Update status dot
        var wrapper = document.getElementById(statusWrapperId);
        if (wrapper) {
            var dot = wrapper.querySelector('.status-dot');
            if (dot) {
                dot.classList.remove('status-enabled', 'status-disabled');
                dot.classList.add(isOn ? 'status-enabled' : 'status-disabled');
            }
        }
    }

    // Map: checkboxId -> { statusId, labelId }
    var toggleMap = {
        'allowUserUploads':   { status: 'allowUploadsToggleStatus',   label: 'allowUploadsLabel' },
        'showForgotPassword': { status: 'forgotPwToggleStatus',       label: 'forgotPwLabel' },
        'maintenanceMode':    { status: 'maintenanceToggleStatus',    label: 'maintenanceLabel' },
        'autoEmailEnabled':   { status: 'autoEmailToggleStatus',      label: 'autoEmailLabel' }
    };

    // Attach change listeners for all toggles
    Object.keys(toggleMap).forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', function() {
                updateToggleStatus(id, toggleMap[id].status, toggleMap[id].label);
            });
        }
    });

    // Convenience wrapper for load-time calls
    function updateToggleLabel(checkboxId, labelId) {
        var m = toggleMap[checkboxId];
        if (m) updateToggleStatus(checkboxId, m.status, m.label);
    }
    function updateAutoEmailLabel(checked) {
        updateToggleStatus('autoEmailEnabled', 'autoEmailToggleStatus', 'autoEmailLabel');
    }

    // ============================================================
    // Logo Upload
    // ============================================================
    document.getElementById('logoFile').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;

        // Validate size client-side first
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({ icon: 'error', title: 'File Too Large', text: 'Please choose a file smaller than 2MB.' });
            this.value = '';
            return;
        }

        const statusEl = document.getElementById('logoUploadStatus');
        const preview  = document.getElementById('logoPreview');

        // Instant preview via FileReader
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);

        // Upload to server
        statusEl.textContent = 'Uploading…';
        statusEl.style.color = '#0074D9';

        const formData = new FormData();
        formData.append('action',    'uploadCompanyLogo');
        formData.append('logo_file', file);

        $.ajax({
            url: window.location.pathname,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    statusEl.textContent = 'Logo uploaded successfully!';
                    statusEl.style.color = '#28a745';
                    preview.src = response.logo_path + '?t=' + Date.now();
                    preview.classList.remove('hidden');
                } else {
                    statusEl.textContent = 'Upload failed: ' + response.message;
                    statusEl.style.color = '#dc3545';
                    Swal.fire({ icon: 'error', title: 'Upload Failed', text: response.message });
                }
            },
            error: function(xhr, status, error) {
                statusEl.textContent = 'Upload error. Please try again.';
                statusEl.style.color = '#dc3545';
                console.error('Logo upload error:', error);
                Swal.fire({ icon: 'error', title: 'Upload Error', text: 'Could not upload logo. Please try again.' });
            }
        });
    });

    // ============================================================
    // System Behavior grid — mobile responsive fix
    // ============================================================
    (function() {
        var sysBehaviorGrid = document.querySelector('.settings-card.full-width .settings-card-body > div');
        if (!sysBehaviorGrid) return;
        function checkWidth() {
            if (window.innerWidth <= 767) {
                sysBehaviorGrid.style.gridTemplateColumns = '1fr';
            } else {
                sysBehaviorGrid.style.gridTemplateColumns = '1fr 1fr';
            }
        }
        checkWidth();
        window.addEventListener('resize', checkWidth);
    })();

    // ============================================================
    // Init
    // ============================================================
    $(document).ready(function() {
        loadSettings();
    });
    </script>
</body>
</html>
