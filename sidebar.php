<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * Shared Sidebar Component
 * Include this file in all dashboard pages
 *
 * Required variables before including:
 * - $username: Current logged-in username
 * - $role: Current user role (Admin/User)
 * - $current_page: Current page identifier (dashboard/users/logs/account/settings)
 * - $user_id: Current user ID
 */

if (!isset($username) || !isset($role) || !isset($current_page) || !isset($user_id)) {
    die('Sidebar requires $username, $role, $current_page, and $user_id variables');
}

// Get user's profile image, fallback to default logo
$profile_image = getProfileImage($user_id);
$default_logo = "https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEiGXxCe0WNNedmFqSWeF761f7Kshhc-NP5ChRQKz9fr97cO8VaarvD0KlCwqHojJVBWv-RAxfOqMI5rD4H78KnARyOc6QgwL1nRRFWf5xNQ1d9F9HfAoLPPGlTyP0GwNl4n-INMEsWLQ4Y7zJtz5bOdAnc2ePH9-uCRgshlo6BsS6gJEz6fhrxL-5U5O3sX/s160/channels4_profile.jpg";
$image_src = $profile_image ? $profile_image : $default_logo;

// Check if any submenu item is active
$account_submenu_active = in_array($current_page, ['account', 'logs']);
$system_submenu_active = in_array($current_page, ['settings', 'oauth_setup', 'smtp_setup', 'sessions', 'backup', 'notification_logs']);
// Get user's custom theme
$user_theme = getUserTheme($user_id);
// Get default language for Google Translate
$default_language = getDefaultLanguage();

// count expired/expiring subs for badge
$_sb_expired = 0;
$_sb_expiring = 0;
if ($role !== 'customer') {
    try {
        $conn = getDBConnection();
        $sp_id_sb = $_SESSION['salesperson_id'] ?? null;
        // expired: active subs past expiry
        $q = "SELECT COUNT(*) AS cnt FROM subscriptions WHERE subscription_status='active' AND expiry_date IS NOT NULL AND expiry_date < CURDATE()";
        if ($role === 'salesperson' && $sp_id_sb) { $q .= " AND salesperson_id = ?"; $st = $conn->prepare($q); $st->bind_param("i", $sp_id_sb); }
        elseif ($role !== 'admin') { $q .= " AND added_by = ?"; $st = $conn->prepare($q); $st->bind_param("i", $user_id); }
        else { $st = $conn->prepare($q); }
        $st->execute(); $_sb_expired = (int)$st->get_result()->fetch_assoc()['cnt']; $st->close();
        // expiring soon: 0-30 days
        $q2 = "SELECT COUNT(*) AS cnt FROM subscriptions WHERE subscription_status='active' AND expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        if ($role === 'salesperson' && $sp_id_sb) { $q2 .= " AND salesperson_id = ?"; $st2 = $conn->prepare($q2); $st2->bind_param("i", $sp_id_sb); }
        elseif ($role !== 'admin') { $q2 .= " AND added_by = ?"; $st2 = $conn->prepare($q2); $st2->bind_param("i", $user_id); }
        else { $st2 = $conn->prepare($q2); }
        $st2->execute(); $_sb_expiring = (int)$st2->get_result()->fetch_assoc()['cnt']; $st2->close();
    } catch (Exception $e) { /* silent */ }
}
$_sb_total_alerts = $_sb_expired + $_sb_expiring;

// Output custom theme CSS
echo generateUserThemeCSS($user_id);
?>
<?php if (isset($_SESSION['admin_original'])): ?>
<div style="position:fixed;top:0;left:0;right:0;z-index:10002;background:#ff9800;color:#fff;text-align:center;padding:6px 12px;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:10px;">
    <i class="fas fa-user-secret"></i> Viewing as <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong> (<?php echo htmlspecialchars($_SESSION['role']); ?>)
    <button onclick="switchBackToAdmin()" style="background:#fff;color:#ff9800;border:none;padding:3px 12px;border-radius:4px;cursor:pointer;font-weight:600;font-size:12px;">
        <i class="fas fa-undo"></i> Switch Back
    </button>
</div>
<style>.sidebar{top:34px !important;height:calc(100vh - 34px) !important;}.main-content{margin-top:34px !important;}</style>
<?php endif; ?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-title">
            <i class="fas fa-tachometer-alt"></i>
            <span class="sidebar-title-text">Dashboard</span>
        </div>
        <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
            <i class="fas fa-chevron-left" id="sidebarToggleIcon"></i>
        </button>
    </div>
    <div class="sidebar-logo-section">
        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="Profile" class="sidebar-logo" onerror="this.src='<?php echo $default_logo; ?>'">
    </div>
    <div class="sidebar-menu-section">
        <ul class="sidebar-menu">
            <?php if ($role === 'customer'): ?>
            <li data-tooltip="My Portal"><a href="customer_portal.php" class="<?php echo $current_page === 'customer_portal' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i><span>My Portal</span></a></li>
            <li data-tooltip="Pricing"><a href="pricing.php" class="<?php echo $current_page === 'pricing' ? 'active' : ''; ?>"><i class="fas fa-tags"></i><span>Pricing</span></a></li>
            <li data-tooltip="My Account"><a href="account.php" class="<?php echo $current_page === 'account' ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i><span>My Account</span></a></li>
            <li data-tooltip="About App"><a href="about.php" class="<?php echo $current_page === 'about' ? 'active' : ''; ?>"><i class="fas fa-info-circle"></i><span>About App</span></a></li>
            <?php else: ?>
            <li data-tooltip="Dashboard">
                <a href="dashboard.php" class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li data-tooltip="Subscriptions">
                <a href="subscriptions.php" class="<?php echo $current_page === 'subscriptions' ? 'active' : ''; ?>" style="position:relative;">
                    <i class="fas fa-file-contract"></i>
                    <span>Subscriptions</span>
                    <?php if ($_sb_total_alerts > 0): ?>
                    <span class="sidebar-badge<?php echo $_sb_expired > 0 ? ' badge-danger' : ' badge-warning'; ?>" title="<?php echo $_sb_expired; ?> expired, <?php echo $_sb_expiring; ?> expiring soon"><?php echo $_sb_total_alerts; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li data-tooltip="Kanban Board">
                <a href="kanban.php" class="<?php echo $current_page === 'kanban' ? 'active' : ''; ?>">
                    <i class="fas fa-columns"></i>
                    <span>Kanban Board</span>
                </a>
            </li>
            <li data-tooltip="Calendar">
                <a href="calendar.php" class="<?php echo $current_page === 'calendar' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>
            </li>
            <li data-tooltip="Add Subscription">
                <a href="add_subscription.php" class="<?php echo $current_page === 'add_subscription' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Subscription</span>
                </a>
            </li>
            <li data-tooltip="Payments">
                <a href="payments.php" class="<?php echo $current_page === 'payments' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payments</span>
                </a>
            </li>
            <?php if ($role === 'admin'): ?>
            <li data-tooltip="Customers">
                <a href="customers.php" class="<?php echo $current_page === 'customers' ? 'active' : ''; ?>">
                    <i class="fas fa-address-book"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li data-tooltip="Suppliers">
                <a href="suppliers.php" class="<?php echo $current_page === 'suppliers' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i>
                    <span>Suppliers</span>
                </a>
            </li>
            <li data-tooltip="Products">
                <a href="products.php" class="<?php echo $current_page === 'products' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>
            <li data-tooltip="Sales Persons">
                <a href="salespersons.php" class="<?php echo $current_page === 'salespersons' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i>
                    <span>Sales Persons</span>
                </a>
            </li>
            <?php endif; ?>
            <li data-tooltip="Reports">
                <a href="reports.php" class="<?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php if ($role === 'admin'): ?>
            <li data-tooltip="Users">
                <a href="users.php" class="<?php echo $current_page === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li data-tooltip="Payment Methods">
                <a href="dropdown.php" class="<?php echo $current_page === 'dropdown' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment Methods</span>
                </a>
            </li>
            <li data-tooltip="Tax Rates">
                <a href="tax_rates.php" class="<?php echo $current_page === 'tax_rates' ? 'active' : ''; ?>">
                    <i class="fas fa-percentage"></i>
                    <span>Tax Rates</span>
                </a>
            </li>
            <li data-tooltip="Currencies">
                <a href="currencies.php" class="<?php echo $current_page === 'currencies' ? 'active' : ''; ?>">
                    <i class="fas fa-coins"></i>
                    <span>Currencies</span>
                </a>
            </li>
            <li data-tooltip="AI Chat">
                <a href="ai_chat.php" class="<?php echo $current_page === 'ai_chat' ? 'active' : ''; ?>">
                    <i class="fas fa-robot"></i>
                    <span>AI Chat</span>
                </a>
            </li>
            <li data-tooltip="Custom Fields">
                <a href="custom_fields.php" class="<?php echo $current_page === 'custom_fields' ? 'active' : ''; ?>">
                    <i class="fas fa-puzzle-piece"></i>
                    <span>Custom Fields</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="has-submenu" data-tooltip="My Account">
                <a href="#" class="submenu-toggle <?php echo $account_submenu_active ? 'active' : ''; ?>" data-submenu="account-submenu">
                    <i class="fas fa-user-circle"></i>
                    <span>My Account</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="sidebar-submenu" id="account-submenu">
                    <li data-tooltip="My Profile">
                        <a href="account.php" class="<?php echo $current_page === 'account' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li data-tooltip="Activity Logs">
                        <a href="logs.php" class="<?php echo $current_page === 'logs' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span>Activity Logs</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php if ($role === 'admin'): ?>
            <li class="has-submenu" data-tooltip="System">
                <a href="#" class="submenu-toggle <?php echo $system_submenu_active ? 'active' : ''; ?>" data-submenu="system-submenu">
                    <i class="fas fa-server"></i>
                    <span>System</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="sidebar-submenu" id="system-submenu">
                    <li data-tooltip="Site Settings">
                        <a href="settings.php" class="<?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                            <i class="fas fa-cog"></i>
                            <span>Site Settings</span>
                        </a>
                    </li>
                    <li data-tooltip="OAuth Setup">
                        <a href="oauth_setup.php" class="<?php echo $current_page === 'oauth_setup' ? 'active' : ''; ?>">
                            <i class="fab fa-google"></i>
                            <span>OAuth Setup</span>
                        </a>
                    </li>
                    <li data-tooltip="SMTP Setup">
                        <a href="smtp_setup.php" class="<?php echo $current_page === 'smtp_setup' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope"></i>
                            <span>SMTP Setup</span>
                        </a>
                    </li>
                    <li data-tooltip="Sessions">
                        <a href="sessions.php" class="<?php echo $current_page === 'sessions' ? 'active' : ''; ?>">
                            <i class="fas fa-desktop"></i>
                            <span>Sessions</span>
                        </a>
                    </li>
                    <li data-tooltip="Backup">
                        <a href="backup.php" class="<?php echo $current_page === 'backup' ? 'active' : ''; ?>">
                            <i class="fas fa-database"></i>
                            <span>Backup</span>
                        </a>
                    </li>
                    <li data-tooltip="Notification Logs">
                        <a href="notification_logs.php" class="<?php echo $current_page === 'notification_logs' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope-open-text"></i>
                            <span>Notification Logs</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
            <li data-tooltip="About App">
                <a href="about.php" class="<?php echo $current_page === 'about' ? 'active' : ''; ?>">
                    <i class="fas fa-info-circle"></i>
                    <span>About App</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <div id="google_translate_element" class="notranslate" style="display:none;"></div>
    <div class="sidebar-theme">
        <button onclick="toggleTheme()">
            <i class="fas fa-moon" id="themeIcon"></i>
            <span id="themeText">Dark Mode</span>
        </button>
    </div>
    <?php if (isset($_SESSION['admin_original'])): ?>
    <div class="sidebar-logout" style="margin-bottom:0;">
        <button onclick="switchBackToAdmin()" style="background:#28a745;">
            <i class="fas fa-undo"></i>
            <span>Back to Admin</span>
        </button>
    </div>
    <?php endif; ?>
    <div class="sidebar-logout" id="pwaInstallWrap" style="display:none;">
        <button onclick="pwaInstall()" style="background:#0074D9;">
            <i class="fas fa-download"></i>
            <span>Install App</span>
        </button>
    </div>
    <div class="sidebar-logout">
        <button onclick="window.location.href='logout.php'">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </button>
    </div>
</div>

<!-- Theme Toggle JavaScript -->
<script>
/**
 * Theme Toggle Functionality
 * Handles light/dark mode switching with localStorage persistence
 * Also respects user's saved theme preference from database
 */
function initTheme() {
    const savedTheme = localStorage.getItem('theme');
    const userThemeMode = <?php echo json_encode($user_theme['theme_mode']); ?>;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    // Priority: localStorage > database setting > system preference
    let isDark = false;
    if (savedTheme) {
        isDark = savedTheme === 'dark';
    } else if (userThemeMode) {
        isDark = userThemeMode === 'dark';
        // Save to localStorage so it persists
        localStorage.setItem('theme', userThemeMode);
    } else {
        isDark = prefersDark;
    }

    if (isDark) {
        document.body.classList.add('dark-mode');
        updateThemeButton(true);
    } else {
        document.body.classList.remove('dark-mode');
        updateThemeButton(false);
    }
}

function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateThemeButton(isDark);
}

function updateThemeButton(isDark) {
    const icon = document.getElementById('themeIcon');
    const text = document.getElementById('themeText');

    if (icon && text) {
        if (isDark) {
            icon.className = 'fas fa-sun';
            text.textContent = 'Light Mode';
        } else {
            icon.className = 'fas fa-moon';
            text.textContent = 'Dark Mode';
        }
    }
}

// switch back to admin
<?php if (isset($_SESSION['admin_original'])): ?>
function switchBackToAdmin() {
    $.post('users.php?action=switchBack', function(r) {
        if (r.success) window.location.href = 'users.php';
        else alert(r.message);
    }, 'json');
}
<?php endif; ?>

// Initialize theme on page load
initTheme();

/**
 * Sidebar Collapse Functionality
 * Handles sidebar expand/collapse with localStorage persistence
 */
function initSidebar() {
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        document.getElementById('sidebar').classList.add('collapsed');
        updateSidebarIcon(true);
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const isCollapsed = sidebar.classList.toggle('collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed);
    updateSidebarIcon(isCollapsed);
}

function updateSidebarIcon(isCollapsed) {
    const icon = document.getElementById('sidebarToggleIcon');
    if (icon) {
        icon.className = isCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
    }
}

// Initialize sidebar on page load
initSidebar();

// Listen for system theme changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
    if (!localStorage.getItem('theme')) {
        if (e.matches) {
            document.body.classList.add('dark-mode');
            updateThemeButton(true);
        } else {
            document.body.classList.remove('dark-mode');
            updateThemeButton(false);
        }
    }
});
</script>

<!-- Sidebar Submenu JavaScript -->
<script>
/**
 * Sidebar Submenu Toggle Functionality
 * Handles expanding/collapsing submenu items
 */
document.addEventListener('DOMContentLoaded', function() {
    const submenuToggles = document.querySelectorAll('.submenu-toggle');

    submenuToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();

            const submenuId = this.getAttribute('data-submenu');
            const submenu = document.getElementById(submenuId);

            if (!submenu) return;

            // Toggle open class on parent link
            this.classList.toggle('open');

            // Toggle open class on submenu
            submenu.classList.toggle('open');
        });
    });

    // Auto-open submenu if current page is a submenu item
    <?php if ($account_submenu_active): ?>
    const accountToggle = document.querySelector('[data-submenu="account-submenu"]');
    const accountSubmenu = document.getElementById('account-submenu');
    if (accountToggle && accountSubmenu) {
        accountToggle.classList.add('open');
        accountSubmenu.classList.add('open');
    }
    <?php endif; ?>

    <?php if ($system_submenu_active): ?>
    const systemToggle = document.querySelector('[data-submenu="system-submenu"]');
    const systemSubmenu = document.getElementById('system-submenu');
    if (systemToggle && systemSubmenu) {
        systemToggle.classList.add('open');
        systemSubmenu.classList.add('open');
    }
    <?php endif; ?>

});
</script>

<!-- Google Translate Integration -->
<script>
/**
 * Google Translate Functionality
 * Auto-translates page based on admin's default language setting
 */
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        autoDisplay: false,
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE
    }, 'google_translate_element');
}

// Set default language from admin setting
(function() {
    const defaultLang = '<?php echo htmlspecialchars($default_language); ?>';
    const prevLang = localStorage.getItem('admin_default_language') || '';
    const isReverted = localStorage.getItem('lang_reverted_to_english');

    // detect admin changed language — clear old cache
    if (prevLang && prevLang !== defaultLang) {
        localStorage.removeItem('lang_reverted_to_english');
        document.cookie = 'googtrans=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT';
        document.cookie = 'googtrans=;path=/;domain=' + window.location.hostname + ';expires=Thu, 01 Jan 1970 00:00:00 GMT';
        localStorage.setItem('admin_default_language', defaultLang);
        if (defaultLang && defaultLang !== 'en') {
            document.cookie = 'googtrans=/en/' + defaultLang + ';path=/';
            document.cookie = 'googtrans=/en/' + defaultLang + ';path=/;domain=' + window.location.hostname;
        }
        location.reload();
        return;
    }
    localStorage.setItem('admin_default_language', defaultLang);

    if (isReverted === 'true') {
        return;
    }

    if (defaultLang && defaultLang !== 'en') {
        const currentTrans = document.cookie.split(';').find(c => c.trim().startsWith('googtrans='));
        const expected = 'googtrans=/en/' + defaultLang;
        if (!currentTrans || currentTrans.trim().indexOf(expected) === -1) {
            document.cookie = 'googtrans=/en/' + defaultLang + ';path=/';
            document.cookie = 'googtrans=/en/' + defaultLang + ';path=/;domain=' + window.location.hostname;
            location.reload();
            return;
        }
    }
})();

function revertToEnglish() {
    // Clear googtrans cookies
    document.cookie = 'googtrans=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT';
    document.cookie = 'googtrans=;path=/;domain=' + window.location.hostname + ';expires=Thu, 01 Jan 1970 00:00:00 GMT';
    // Remember user chose English
    localStorage.setItem('lang_reverted_to_english', 'true');
    localStorage.setItem('admin_default_language', '<?php echo htmlspecialchars($default_language); ?>');
    location.reload();
}
</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

<!-- PWA: manifest + service worker + install -->
<script>
(function() {
    // inject manifest link into <head>
    if (!document.querySelector('link[rel="manifest"]')) {
        var m = document.createElement('link');
        m.rel = 'manifest';
        m.href = 'manifest.php';
        document.head.appendChild(m);
    }
    // inject theme-color meta
    if (!document.querySelector('meta[name="theme-color"]')) {
        var tc = document.createElement('meta');
        tc.name = 'theme-color';
        tc.content = '#001f3f';
        document.head.appendChild(tc);
    }
    // inject apple-touch-icon
    if (!document.querySelector('link[rel="apple-touch-icon"]')) {
        var ai = document.createElement('link');
        ai.rel = 'apple-touch-icon';
        ai.href = <?php echo json_encode($image_src); ?>;
        document.head.appendChild(ai);
    }
    // inject favicon to prevent 404
    if (!document.querySelector('link[rel="icon"]')) {
        var fi = document.createElement('link');
        fi.rel = 'icon';
        fi.type = 'image/png';
        fi.href = <?php echo json_encode($image_src); ?>;
        document.head.appendChild(fi);
    }

    // register service worker (force update)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js', { updateViaCache: 'none' }).then(function(reg) {
            reg.update();
        }).catch(function() {});
    }

    // install prompt
    var _deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        _deferredPrompt = e;
        var wrap = document.getElementById('pwaInstallWrap');
        if (wrap) wrap.style.display = '';
    });

    window.pwaInstall = function() {
        if (!_deferredPrompt) return;
        _deferredPrompt.prompt();
        _deferredPrompt.userChoice.then(function(result) {
            _deferredPrompt = null;
            var wrap = document.getElementById('pwaInstallWrap');
            if (wrap) wrap.style.display = 'none';
        });
    };

    // hide button if already installed
    window.addEventListener('appinstalled', function() {
        var wrap = document.getElementById('pwaInstallWrap');
        if (wrap) wrap.style.display = 'none';
        _deferredPrompt = null;
    });
})();
</script>
