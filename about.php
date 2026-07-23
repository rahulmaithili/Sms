<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'about';

$branding = getSiteBranding();

// Pull company info from settings
$company_name    = getSetting('company_name',       'My Company');
$company_email   = getSetting('company_email',      '');
$company_website = getSetting('company_website',    '');
$company_phone   = getSetting('company_phone',      '');
$copyright_text  = getSetting('copyright_text',     '');
$company_logo    = getSetting('company_logo_url',   '') ?: getSetting('site_logo', '');
$default_logo    = 'https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEiGXxCe0WNNedmFqSWeF761f7Kshhc-NP5ChRQKz9fr97cO8VaarvD0KlCwqHojJVBWv-RAxfOqMI5rD4H78KnARyOc6QgwL1nRRFWf5xNQ1d9F9HfAoLPPGlTyP0GwNl4n-INMEsWLQ4Y7zJtz5bOdAnc2ePH9-uCRgshlo6BsS6gJEz6fhrxL-5U5O3sX/s160/channels4_profile.jpg';
$display_logo    = !empty($company_logo) ? $company_logo : $default_logo;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>About - <?php echo htmlspecialchars($branding['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
</head>
<body class="initially-hidden">
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                About App
            </div>

            <div class="header">
                <h1><i class="fas fa-info-circle"></i> About App</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="about-section">
                <div class="about-header">
                    <div class="about-logo">
                        <img src="<?php echo htmlspecialchars($branding['site_logo']); ?>" alt="App Logo">
                    </div>
                    <div class="about-title">
                        <h1><?php echo htmlspecialchars($branding['site_name']); ?></h1>
                        <p class="about-dev">Developed by <strong>Mohammad Rameez Imdad</strong> (Rameez Scripts)</p>
                    </div>
                </div>

                <!-- What is this App -->
                <div class="about-card">
                    <h2><i class="fas fa-question-circle"></i> What is this App?</h2>
                    <p>This is a full-featured Subscription Management System built with PHP and MySQL. It helps businesses track their subscriptions, manage customers and suppliers, handle payments, and monitor revenue from a single dashboard. Whether you're running a SaaS business, managing software licenses, or handling recurring services, this system keeps everything organized with role-based access so each team member sees exactly what they need.</p>
                </div>

                <!-- Core Features -->
                <div class="about-card">
                    <h2><i class="fas fa-clipboard-list"></i> Core Features</h2>
                    <ul class="about-features">
                        <li><i class="fas fa-chart-line"></i> Dashboard with real-time KPIs, revenue charts, and subscription analytics</li>
                        <li><i class="fas fa-file-contract"></i> Complete subscription management with add, edit, renew, pause, and cancel</li>
                        <li><i class="fas fa-money-bill-wave"></i> Payment tracking linked to subscriptions with partial payment support</li>
                        <li><i class="fas fa-address-book"></i> Customer database with contact details, notes, and full ledger reports</li>
                        <li><i class="fas fa-truck"></i> Supplier management to track vendors and product sources</li>
                        <li><i class="fas fa-box"></i> Product catalog with pricing, color codes, and display ordering</li>
                        <li><i class="fas fa-user-tie"></i> Sales person profiles with commission rates and performance tracking</li>
                        <li><i class="fas fa-columns"></i> Kanban board for visual drag-and-drop subscription workflow</li>
                        <li><i class="fas fa-calendar-alt"></i> Calendar view showing subscription start dates, expiry dates, and payments</li>
                        <li><i class="fas fa-file-invoice-dollar"></i> Invoice generation with PDF and print support</li>
                        <li><i class="fas fa-chart-bar"></i> Detailed reports covering revenue trends, profit margins, unpaid amounts, and commissions</li>
                        <li><i class="fas fa-file-import"></i> CSV import and export for bulk subscription management</li>
                    </ul>
                </div>

                <!-- Customer Portal -->
                <div class="about-card">
                    <h2><i class="fas fa-th-large"></i> Customer Portal</h2>
                    <p>Customers get their own dedicated portal where they can view their subscriptions, check payment history, and see pricing plans. It provides a clean self-service experience without exposing any admin functionality.</p>
                </div>

                <!-- Advanced Features -->
                <div class="about-card">
                    <h2><i class="fas fa-cogs"></i> Advanced Features</h2>
                    <ul class="about-features">
                        <li><i class="fas fa-robot"></i> AI Chat Assistant powered by Google Gemini for smart queries and data insights</li>
                        <li><i class="fas fa-puzzle-piece"></i> Custom fields that let you add dynamic fields to subscriptions without touching code</li>
                        <li><i class="fas fa-coins"></i> Multi-currency support with exchange rates and configurable defaults</li>
                        <li><i class="fas fa-percentage"></i> Flexible tax rate management with default tax auto-apply</li>
                        <li><i class="fas fa-credit-card"></i> Configurable payment methods with reports per method</li>
                        <li><i class="fas fa-clock"></i> Automated email reminders via cron for expiring subscriptions (30, 7, 1, and 0 days)</li>
                        <li><i class="fas fa-redo"></i> Auto-renewal tracking with subscription status management</li>
                        <li><i class="fas fa-tags"></i> Pricing page for customers to browse products and plans</li>
                        <li><i class="fas fa-magic"></i> Setup wizard for first-time onboarding and configuration</li>
                        <li><i class="fas fa-paperclip"></i> Document attachments on subscriptions with role-based download access</li>
                    </ul>
                </div>

                <!-- System & Security -->
                <div class="about-card">
                    <h2><i class="fas fa-shield-alt"></i> System & Security</h2>
                    <ul class="about-features">
                        <li><i class="fas fa-lock"></i> Secure authentication with password hashing and session timeout</li>
                        <li><i class="fab fa-google"></i> Google OAuth login integration</li>
                        <li><i class="fas fa-envelope"></i> SMTP email setup for notifications and password resets</li>
                        <li><i class="fas fa-bell"></i> In-app notifications with email delivery logs</li>
                        <li><i class="fas fa-history"></i> Full activity logs for complete audit trail of every action</li>
                        <li><i class="fas fa-desktop"></i> Active session management with the ability to terminate sessions</li>
                        <li><i class="fas fa-database"></i> One-click database backup and export</li>
                        <li><i class="fas fa-tools"></i> Maintenance mode to temporarily block access during updates</li>
                        <li><i class="fas fa-user-secret"></i> Admin impersonation to view the system as any user</li>
                        <li><i class="fas fa-palette"></i> Dark and light theme with per-user preference</li>
                        <li><i class="fas fa-language"></i> Google Translate integration for multi-language support</li>
                        <li><i class="fas fa-mobile-alt"></i> Fully responsive and mobile-optimized interface</li>
                    </ul>
                </div>

                <!-- User Roles & Permissions -->
                <div class="about-card">
                    <h2><i class="fas fa-users-cog"></i> User Roles & Permissions</h2>
                    <p class="mb-24">The system has four user roles. Each role has different access levels designed to match their responsibilities. Admins have full control, salespersons work within their own pipeline, regular users manage their own subscriptions, and customers access a self-service portal.</p>
                    <div class="about-table-wrapper">
                        <table class="about-roles-table">
                            <thead>
                                <tr>
                                    <th>Feature / Action</th>
                                    <th><span class="role-badge role-admin">Admin</span></th>
                                    <th><span class="role-badge role-sf">Salesperson</span></th>
                                    <th><span class="role-badge role-emp">User</span></th>
                                    <th><span class="role-badge role-dept">Customer</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Dashboard & Analytics</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Full</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Data</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Data</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>View Subscriptions</td>
                                    <td><i class="fas fa-check-circle text-success"></i> All</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Only</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Only</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Portal</td>
                                </tr>
                                <tr>
                                    <td>Add Subscription</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Edit Subscription</td>
                                    <td><i class="fas fa-check-circle text-success"></i> All</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Only</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Only</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Payments</td>
                                    <td><i class="fas fa-check-circle text-success"></i> All</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Kanban Board</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Calendar</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Reports</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Full</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Filtered</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Filtered</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Customers</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Suppliers</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Products</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Sales Persons</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Manage Users</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Payment Methods / Tax / Currencies</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Custom Fields</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>AI Chat Assistant</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Customer Portal</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                </tr>
                                <tr>
                                    <td>Pricing Page</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                </tr>
                                <tr>
                                    <td>My Profile / Change Password</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                </tr>
                                <tr>
                                    <td>Activity Logs</td>
                                    <td><i class="fas fa-check-circle text-success"></i> All Users</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Only</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Only</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Own Only</td>
                                </tr>
                                <tr>
                                    <td>System Settings / SMTP / OAuth</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Session Management</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Database Backup</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                                <tr>
                                    <td>Maintenance Mode</td>
                                    <td><i class="fas fa-check-circle text-success"></i> Yes</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                    <td><i class="fas fa-times-circle text-danger"></i> No</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- About Company (from Settings) -->
                <div class="about-card about-developer">
                    <h2><i class="fas fa-building"></i> About the Company</h2>
                    <div class="developer-info">
                        <img src="<?php echo htmlspecialchars($display_logo); ?>"
                             alt="<?php echo htmlspecialchars($company_name); ?>"
                             class="developer-avatar"
                             onerror="this.src='<?php echo $default_logo; ?>'">
                        <div class="developer-details">
                            <h3><?php echo htmlspecialchars($company_name); ?></h3>
                            <?php if (!empty($company_website)): ?>
                            <p class="developer-brand">
                                <a href="<?php echo htmlspecialchars($company_website); ?>" target="_blank" style="color:#0074D9;text-decoration:none;">
                                    <i class="fas fa-globe"></i> <?php echo htmlspecialchars($company_website); ?>
                                </a>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($copyright_text)): ?>
                            <p><?php echo htmlspecialchars($copyright_text); ?></p>
                            <?php endif; ?>
                            <div class="developer-links" style="flex-wrap:wrap;gap:10px;margin-top:12px;">
                                <?php if (!empty($company_email)): ?>
                                <a href="mailto:<?php echo htmlspecialchars($company_email); ?>" class="dev-link" style="background:#0074D9;">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($company_email); ?>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($company_phone)): ?>
                                <a href="tel:<?php echo htmlspecialchars($company_phone); ?>" class="dev-link" style="background:#28a745;">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($company_phone); ?>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($company_website)): ?>
                                <a href="<?php echo htmlspecialchars($company_website); ?>" target="_blank" class="dev-link" style="background:#6c757d;">
                                    <i class="fas fa-external-link-alt"></i> Visit Website
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="about-footer">
                    <p>Made with <i class="fas fa-heart text-danger"></i> by <strong><?php echo htmlspecialchars($company_name); ?></strong></p>
                    <?php if (!empty($copyright_text)): ?>
                    <p style="font-size:12px;opacity:.7;"><?php echo htmlspecialchars($copyright_text); ?></p>
                    <?php endif; ?>
                    <p class="about-version">Version 2.0.0</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    $(document).ready(function() {
        document.body.classList.remove('initially-hidden');
    });
    </script>
</body>
</html>
