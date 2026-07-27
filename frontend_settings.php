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
        setSetting('support_email_address',    trim($_POST['support_email_address']    ?? 'contact@rameezscripts.com'), $user_id);

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
$support_email_address  = getSetting('support_email_address', 'contact@rameezscripts.com');

$branding = getSiteBranding();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Frontend Controls - <?php echo htmlspecialchars($branding['site_name']); ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    
    <style>
        .settings-grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 992px) {
            .settings-grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .toggle-switch-card {
            background: var(--card-bg, #ffffff);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .toggle-switch-card:hover {
            border-color: var(--primary-color, #0074D9);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .toggle-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .toggle-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: rgba(0, 116, 217, 0.1);
            color: var(--primary-color, #0074D9);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
        }

        .toggle-title {
            font-weight: 600;
            font-size: 15px;
            color: var(--text-color, #333);
            margin-bottom: 2px;
        }

        .toggle-desc {
            font-size: 12px;
            color: #777;
        }

        /* Custom Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
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
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #10b981;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }
    </style>
</head>
<body class="dashboard-body">

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="navbar-left">
                <button id="sidebar-toggle" class="sidebar-toggle-btn"><i class="fas fa-bars"></i></button>
                <h2><i class="fas fa-desktop"></i> Website Frontend Controls</h2>
            </div>
            <div class="navbar-right">
                <a href="index.php" target="_blank" class="btn btn-outline btn-sm" style="margin-right:12px;">
                    <i class="fas fa-external-link-alt"></i> View Live Site
                </a>
                <div class="user-profile-menu">
                    <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
                </div>
            </div>
        </header>

        <div class="content-wrapper" style="padding: 24px;">
            
            <?php if ($message): ?>
            <div class="alert alert-success" style="background:#d1e7dd; color:#0f5132; padding:14px; border-radius:8px; margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger" style="background:#f8d7da; color:#842029; padding:14px; border-radius:8px; margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="frontend_settings.php">
                <input type="hidden" name="save_frontend_settings" value="1">

                <div class="settings-grid-layout">
                    <!-- Column 1: Section Enable/Disable Toggles -->
                    <div class="card" style="padding: 24px; border-radius: 12px; background: var(--card-bg, #fff);">
                        <h3 style="margin-bottom: 20px; font-size: 18px; color: var(--primary-color, #0074D9);">
                            <i class="fas fa-toggle-on"></i> Section Enable / Disable Controls
                        </h3>
                        <p style="font-size: 13px; color: #666; margin-bottom: 20px;">
                            Choose which sections to display or hide on your public website (<code>index.php</code>).
                        </p>

                        <!-- Toggle 1: Hero Orbit -->
                        <div class="toggle-switch-card">
                            <div class="toggle-info">
                                <div class="toggle-icon"><i class="fas fa-atom"></i></div>
                                <div>
                                    <div class="toggle-title">Hero Orbit Tech Graphic</div>
                                    <div class="toggle-desc">Show/Hide 360° rotating tech ecosystem graphic</div>
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
                    <div class="card" style="padding: 24px; border-radius: 12px; background: var(--card-bg, #fff);">
                        <h3 style="margin-bottom: 20px; font-size: 18px; color: var(--primary-color, #0074D9);">
                            <i class="fas fa-pen"></i> Homepage Content Customization
                        </h3>

                        <!-- Hero Badge -->
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Hero Badge Banner Text</label>
                            <input type="text" name="hero_badge_text" class="form-control" value="<?php echo htmlspecialchars($hero_badge_text); ?>" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
                        </div>

                        <!-- Hero Headline -->
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Hero Main Headline</label>
                            <input type="text" name="hero_headline" class="form-control" value="<?php echo htmlspecialchars($hero_headline); ?>" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
                        </div>

                        <!-- Hero Subtitle -->
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Hero Subtitle Description</label>
                            <textarea name="hero_subtitle" class="form-control" rows="4" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc; font-family:inherit;"><?php echo htmlspecialchars($hero_subtitle); ?></textarea>
                        </div>

                        <!-- WhatsApp Support Number -->
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;"><i class="fab fa-whatsapp" style="color:#10b981;"></i> WhatsApp Support Phone (with country code)</label>
                            <input type="text" name="support_whatsapp_number" class="form-control" value="<?php echo htmlspecialchars($support_whatsapp_number); ?>" placeholder="+923394100600" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
                        </div>

                        <!-- Support Email -->
                        <div class="form-group" style="margin-bottom: 24px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;"><i class="fas fa-envelope" style="color:#e11d48;"></i> Support Email Address</label>
                            <input type="email" name="support_email_address" class="form-control" value="<?php echo htmlspecialchars($support_email_address); ?>" placeholder="contact@rameezscripts.com" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
                        </div>

                        <!-- Submit Button -->
                        <div style="text-align: right; border-top: 1px solid #eee; padding-top: 20px;">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-weight: bold; background: #10b981; border: none; border-radius: 6px; color: #fff; cursor: pointer; font-size: 14px;">
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
