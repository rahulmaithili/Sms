<?php
/**
 * Developed by Mr.Rahul Scripts
 *
 * Frontend Website Settings & Section Toggle Controls - Admin Only
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
$current_page = 'frontend_settings';

// Admin-only page
if ($role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$error = '';

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_frontend_settings'])) {
    try {
        // Toggle Controls (1 = Enabled, 0 = Disabled)
        setSetting('show_hero_orbit',          isset($_POST['show_hero_orbit'])          ? '1' : '0', $user_id);
        setSetting('show_portfolio_section',   isset($_POST['show_portfolio_section'])   ? '1' : '0', $user_id);
        setSetting('show_support_section',     isset($_POST['show_support_section'])     ? '1' : '0', $user_id);
        setSetting('show_products_section',    isset($_POST['show_products_section'])    ? '1' : '0', $user_id);
        setSetting('show_reviews_section',     isset($_POST['show_reviews_section'])     ? '1' : '0', $user_id);
        setSetting('show_payments_section',    isset($_POST['show_payments_section'])    ? '1' : '0', $user_id);
        setSetting('show_membership_section',  isset($_POST['show_membership_section'])  ? '1' : '0', $user_id);
        setSetting('show_faq_section',         isset($_POST['show_faq_section'])         ? '1' : '0', $user_id);
        setSetting('show_presence_section',    isset($_POST['show_presence_section'])    ? '1' : '0', $user_id);
        setSetting('show_floating_whatsapp',   isset($_POST['show_floating_whatsapp'])   ? '1' : '0', $user_id);

        // Editable Content Fields
        setSetting('hero_badge_text',          trim($_POST['hero_badge_text']          ?? 'Building Since 2022 · 400+ Projects Shipped to 50+ Countries'), $user_id);
        setSetting('hero_headline',            trim($_POST['hero_headline']            ?? 'Hire a Google Apps Script Developer'), $user_id);
        setSetting('hero_subtitle',            trim($_POST['hero_subtitle']            ?? 'If your team is wasting hours on manual spreadsheet work or disconnected systems — we can fix that. We build Google Sheets automations, Apps Script add-ons, and custom PHP web applications. 400+ projects shipped across 50+ countries, with 6 months of post-delivery support included.'), $user_id);
        setSetting('support_whatsapp_number',  trim($_POST['support_whatsapp_number']  ?? '+923394100600'), $user_id);
        setSetting('support_email_address',    trim($_POST['support_email_address']    ?? 'contact@Mr.RahulScripts.com'), $user_id);

        logActivity($user_id, $username, 'Frontend Settings Saved', 'Updated website layout sections enable/disable controls');
        $message = 'Frontend website settings saved successfully!';
    } catch (Exception $e) {
        $error = 'Failed to save settings: ' . $e->getMessage();
    }
}

// Read current settings
$show_hero_orbit        = getSetting('show_hero_orbit', '1');
$show_portfolio_section = getSetting('show_portfolio_section', '1');
$show_support_section   = getSetting('show_support_section', '1');
$show_products_section  = getSetting('show_products_section', '1');
$show_reviews_section   = getSetting('show_reviews_section', '1');
$show_payments_section  = getSetting('show_payments_section', '1');
$show_membership_section= getSetting('show_membership_section', '1');
$show_faq_section       = getSetting('show_faq_section', '1');
$show_presence_section  = getSetting('show_presence_section', '1');
$show_floating_whatsapp = getSetting('show_floating_whatsapp', '1');

$hero_badge_text        = getSetting('hero_badge_text', 'Building Since 2022 · 400+ Projects Shipped to 50+ Countries');
$hero_headline          = getSetting('hero_headline', 'Hire a Google Apps Script Developer');
$hero_subtitle          = getSetting('hero_subtitle', 'If your team is wasting hours on manual spreadsheet work or disconnected systems — we can fix that. We build Google Sheets automations, Apps Script add-ons, and custom PHP web applications. 400+ projects shipped across 50+ countries, with 6 months of post-delivery support included.');
$support_whatsapp_number= getSetting('support_whatsapp_number', '+923394100600');
$support_email_address  = getSetting('support_email_address', 'contact@Mr.RahulScripts.com');

$branding = getSiteBranding();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Website Frontend Controls - <?php echo htmlspecialchars($branding['site_name']); ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <style>
        .settings-grid-layout {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            margin-top: 20px;
        }

        @media (max-width: 992px) {
            .settings-grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .toggle-switch-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            transition: all 0.3s ease;
        }

        .toggle-switch-card:hover {
            border-color: var(--navy-accent, #0074D9);
            transform: translateY(-2px);
        }

        .toggle-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .toggle-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(0, 116, 217, 0.12);
            color: var(--navy-accent, #0074D9);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .toggle-title {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .toggle-desc {
            font-size: 12px;
            opacity: 0.7;
        }

        /* Custom Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        input:checked + .slider {
            background-color: #10b981;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }
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
                <span>Frontend Controls</span>
            </div>

            <!-- Page Header -->
            <div class="header" style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h1><i class="fas fa-desktop"></i> Website Frontend Controls</h1>
                    <p style="font-size:13px; opacity:0.8; margin-top:4px;">Enable/Disable website sections and customize main landing page content</p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <a href="index.php" target="_blank" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px;">
                        <i class="fas fa-external-link-alt"></i> Live Site Preview
                    </a>
                    <?php include 'notifications_bell.php'; ?>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success mb-24" style="padding:14px 18px; border-radius:8px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-check-circle" style="font-size:18px;"></i> <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger mb-24" style="padding:14px 18px; border-radius:8px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-exclamation-triangle" style="font-size:18px;"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="frontend_settings.php">
                <input type="hidden" name="save_frontend_settings" value="1">

                <div class="settings-grid-layout">
                    <!-- Column 1: Section Enable/Disable Toggles -->
                    <div class="data-section" style="margin-bottom:0;">
                        <div class="section-header">
                            <h2><i class="fas fa-toggle-on"></i> Homepage Section Visibility</h2>
                        </div>
                        <p style="font-size: 13px; opacity:0.75; margin-bottom: 20px;">
                            Toggle ON/OFF sections to control what is displayed on your main website (<code>index.php</code>).
                        </p>

                        <!-- Toggle 1: Hero Orbit -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-atom"></i></div>
                                <div>
                                    <div class="toggle-title">Hero Orbit Tech Graphic</div>
                                    <div class="toggle-desc">Show/Hide 360° rotating technology ecosystem graphic</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_hero_orbit" value="1" <?php echo $show_hero_orbit === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 2: Portfolio -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-folder-open"></i></div>
                                <div>
                                    <div class="toggle-title">Apps Script Portfolio &amp; Templates</div>
                                    <div class="toggle-desc">Show/Hide featured video walkthrough templates</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_portfolio_section" value="1" <?php echo $show_portfolio_section === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 3: Support Channels -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-headset"></i></div>
                                <div>
                                    <div class="toggle-title">Direct Developer Support Section</div>
                                    <div class="toggle-desc">WhatsApp, Email &amp; Help Desk card blocks</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_support_section" value="1" <?php echo $show_support_section === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 4: Product Showcase -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-box"></i></div>
                                <div>
                                    <div class="toggle-title">Product Showcase &amp; Catalog</div>
                                    <div class="toggle-desc">Display active tools and extensions from database</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_products_section" value="1" <?php echo $show_products_section === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 5: Client Reviews & Scroller -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-star"></i></div>
                                <div>
                                    <div class="toggle-title">Client Reviews &amp; Moving Slider</div>
                                    <div class="toggle-desc">Show/Hide auto-scrolling testimonials carousel</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_reviews_section" value="1" <?php echo $show_reviews_section === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 6: Payments Section -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-wallet"></i></div>
                                <div>
                                    <div class="toggle-title">Payment Methods &amp; Trust Badges</div>
                                    <div class="toggle-desc">PayPal, Visa, UPI, Crypto, Wise icons list</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_payments_section" value="1" <?php echo $show_payments_section === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 7: Membership Plans -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-tags"></i></div>
                                <div>
                                    <div class="toggle-title">Membership Plans Section</div>
                                    <div class="toggle-desc">Weekly, Premium, and VIP Subscription cards</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_membership_section" value="1" <?php echo $show_membership_section === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 8: FAQ -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-question-circle"></i></div>
                                <div>
                                    <div class="toggle-title">Help Center &amp; FAQ Accordion</div>
                                    <div class="toggle-desc">Show/Hide frequently asked questions block</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_faq_section" value="1" <?php echo $show_faq_section === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 9: Office Locations Footer -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-globe"></i></div>
                                <div>
                                    <div class="toggle-title">Global Presence &amp; Office Nodes</div>
                                    <div class="toggle-desc">India, Pakistan &amp; Canada office cards</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_presence_section" value="1" <?php echo $show_presence_section === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 10: Floating WhatsApp -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fab fa-whatsapp"></i></div>
                                <div>
                                    <div class="toggle-title">Floating WhatsApp Button</div>
                                    <div class="toggle-desc">Show/Hide bottom-right floating WhatsApp button</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_floating_whatsapp" value="1" <?php echo $show_floating_whatsapp === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Column 2: Content Text Editing -->
                    <div class="data-section" style="margin-bottom:0;">
                        <div class="section-header">
                            <h2><i class="fas fa-edit"></i> Landing Page Content</h2>
                        </div>

                        <!-- Hero Badge -->
                        <div class="form-group mb-20">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Hero Badge Banner Text</label>
                            <input type="text" name="hero_badge_text" class="form-control filter-input" value="<?php echo htmlspecialchars($hero_badge_text); ?>" required style="width:100%; padding:10px; border-radius:6px;">
                        </div>

                        <!-- Hero Headline -->
                        <div class="form-group mb-20">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Hero Main Headline</label>
                            <input type="text" name="hero_headline" class="form-control filter-input" value="<?php echo htmlspecialchars($hero_headline); ?>" required style="width:100%; padding:10px; border-radius:6px;">
                        </div>

                        <!-- Hero Subtitle -->
                        <div class="form-group mb-20">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Hero Subtitle Description</label>
                            <textarea name="hero_subtitle" class="form-control filter-input" rows="5" style="width:100%; padding:10px; border-radius:6px; font-family:inherit; line-height:1.5;"><?php echo htmlspecialchars($hero_subtitle); ?></textarea>
                        </div>

                        <!-- WhatsApp Support Number -->
                        <div class="form-group mb-20">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;"><i class="fab fa-whatsapp" style="color:#10b981;"></i> WhatsApp Support Phone (with country code)</label>
                            <input type="text" name="support_whatsapp_number" class="form-control filter-input" value="<?php echo htmlspecialchars($support_whatsapp_number); ?>" placeholder="+923394100600" style="width:100%; padding:10px; border-radius:6px;">
                        </div>

                        <!-- Support Email -->
                        <div class="form-group mb-24">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;"><i class="fas fa-envelope" style="color:#e11d48;"></i> Support Email Address</label>
                            <input type="email" name="support_email_address" class="form-control filter-input" value="<?php echo htmlspecialchars($support_email_address); ?>" placeholder="contact@Mr.RahulScripts.com" style="width:100%; padding:10px; border-radius:6px;">
                        </div>

                        <!-- Submit Button -->
                        <div style="border-top: 1px solid var(--border-color, #eee); padding-top: 20px; text-align: right;">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-weight: bold; background: #10b981; border: none; border-radius: 6px; color: #fff; cursor: pointer; font-size: 14px;">
                                <i class="fas fa-save"></i> Save Frontend Controls
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
