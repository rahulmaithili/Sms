<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * First-Time Onboarding Wizard - Admin Only
 */

require_once 'config.php';

// auth guard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? $username;
$role      = $_SESSION['role'] ?? 'user';
$current_page = 'wizard';

// admin only
if ($role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// already done? go to dashboard
if (getSetting('onboarding_complete', '1') === '1') {
    header("Location: dashboard.php");
    exit();
}

// AJAX handlers
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    // save step settings
    if ($_POST['action'] === 'saveStep') {
        try {
            $step = intval($_POST['step'] ?? 0);
            $conn = getDBConnection();

            if ($step === 1) {
                $name  = trim($_POST['company_name'] ?? '');
                $email = trim($_POST['company_email'] ?? '');

                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Company name is required.']);
                    exit();
                }
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
                    exit();
                }

                setSetting('company_name', $name, $user_id);
                setSetting('company_email', $email, $user_id);

                // handle logo upload
                $logo_url = '';
                if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['logo_file'];
                    if ($file['size'] > 2 * 1024 * 1024) {
                        echo json_encode(['success' => false, 'message' => 'Logo must be less than 2MB.']);
                        exit();
                    }
                    $allowed_mimes = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    if (!in_array($mime, $allowed_mimes, true)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP, SVG.']);
                        exit();
                    }
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $upload_dir = __DIR__ . '/uploads/branding/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = 'site_logo_' . time() . '.' . $ext;
                    $filepath_rel = 'uploads/branding/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        // delete old local logo
                        $old = getSetting('company_logo_url', '') ?: getSetting('site_logo', '');
                        if (!empty($old) && strpos($old, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $old)) {
                            @unlink(__DIR__ . '/' . $old);
                        }
                        setSetting('company_logo_url', $filepath_rel, $user_id);
                        setSetting('site_logo', $filepath_rel, $user_id);
                        $logo_url = $filepath_rel;
                    }
                }

                logActivity($user_id, $username, 'Wizard', 'Step 1: Company info saved');
                echo json_encode(['success' => true, 'logo_url' => $logo_url]);
                exit();
            }

            if ($step === 2) {
                $currency = trim($_POST['currency'] ?? '');
                $tax_id   = intval($_POST['tax_id'] ?? 0);

                if (empty($currency)) {
                    echo json_encode(['success' => false, 'message' => 'Please select a currency.']);
                    exit();
                }

                setSetting('currency', $currency, $user_id);

                // set default currency in currencies table
                $conn->query("UPDATE currencies SET is_default = 0");
                $stmt = $conn->prepare("UPDATE currencies SET is_default = 1 WHERE code = ?");
                $stmt->bind_param("s", $currency);
                $stmt->execute();
                $stmt->close();

                // set default tax
                if ($tax_id > 0) {
                    $conn->query("UPDATE tax_rates SET is_default = 0");
                    $stmt = $conn->prepare("UPDATE tax_rates SET is_default = 1 WHERE tax_id = ?");
                    $stmt->bind_param("i", $tax_id);
                    $stmt->execute();
                    $stmt->close();

                    // also save rate to settings
                    $stmt = $conn->prepare("SELECT rate FROM tax_rates WHERE tax_id = ?");
                    $stmt->bind_param("i", $tax_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        setSetting('tax_percentage', $row['rate'], $user_id);
                    }
                    $stmt->close();
                }

                logActivity($user_id, $username, 'Wizard', 'Step 2: Currency & tax saved');
                echo json_encode(['success' => true]);
                exit();
            }

            if ($step === 3) {
                $skip = ($_POST['skip'] ?? '0') === '1';
                if ($skip) {
                    logActivity($user_id, $username, 'Wizard', 'Step 3: Skipped product creation');
                    echo json_encode(['success' => true, 'skipped' => true]);
                    exit();
                }

                $pname  = trim($_POST['product_name'] ?? '');
                $pdesc  = trim($_POST['description'] ?? '');
                $sell   = floatval($_POST['selling_price'] ?? 0);
                $buy    = floatval($_POST['purchase_price'] ?? 0);

                if (empty($pname)) {
                    echo json_encode(['success' => false, 'message' => 'Product name is required.']);
                    exit();
                }
                if ($sell <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Selling price must be greater than 0.']);
                    exit();
                }

                // check duplicate
                $stmt = $conn->prepare("SELECT product_id FROM products WHERE product_name = ?");
                $stmt->bind_param("s", $pname);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'A product with this name already exists.']);
                    exit();
                }
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO products (product_name, description, selling_price, purchase_price) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssdd", $pname, $pdesc, $sell, $buy);
                $stmt->execute();
                $pid = $stmt->insert_id;
                $stmt->close();

                logActivity($user_id, $username, 'Wizard', "Step 3: Product '$pname' created (ID: $pid)");
                echo json_encode(['success' => true, 'product_id' => $pid]);
                exit();
            }

            echo json_encode(['success' => false, 'message' => 'Invalid step.']);
            exit();
        } catch (Exception $e) {
            error_log("Wizard saveStep error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
            exit();
        }
    }

    // complete wizard
    if ($_POST['action'] === 'completeWizard') {
        try {
            setSetting('onboarding_complete', '1', $user_id);
            logActivity($user_id, $username, 'Wizard', 'Onboarding wizard completed');
            echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
            exit();
        } catch (Exception $e) {
            error_log("Wizard complete error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not complete wizard.']);
            exit();
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

// fetch dropdown data
$conn = getDBConnection();
$currencies = [];
$stmt = $conn->prepare("SELECT currency_id, code, name, symbol FROM currencies WHERE is_active = 1 ORDER BY code");
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $currencies[] = $r;
$stmt->close();

$taxRates = [];
$stmt = $conn->prepare("SELECT tax_id, name, rate FROM tax_rates WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $taxRates[] = $r;
$stmt->close();

// current settings
$cur_company  = htmlspecialchars(getSetting('company_name', ''));
$cur_email    = htmlspecialchars(getSetting('company_email', ''));
$cur_logo     = htmlspecialchars(getSetting('company_logo_url', '') ?: getSetting('site_logo', ''));
$cur_currency = getSetting('currency', 'INR');
$logo_url = 'https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEiGXxCe0WNNedmFqSWeF761f7Kshhc-NP5ChRQKz9fr97cO8VaarvD0KlCwqHojJVBWv-RAxfOqMI5rD4H78KnARyOc6QgwL1nRRFWf5xNQ1d9F9HfAoLPPGlTyP0GwNl4n-INMEsWLQ4Y7zJtz5bOdAnc2ePH9-uCRgshlo6BsS6gJEz6fhrxL-5U5O3sX/s160/channels4_profile.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- Developed by Rameez Scripts -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Setup Wizard - Subscription Management</title>
<link rel="icon" type="image/png" href="<?php echo $logo_url; ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<style>
/* Developed by Rameez Scripts */
:root {
    --navy-primary: #001f3f;
    --navy-dark: #001529;
    --navy-light: #003366;
    --navy-accent: #0074D9;
    --bg-primary: #f5f5f5;
    --bg-card: #ffffff;
    --text-primary: #333;
    --text-secondary: #555;
    --text-muted: #999;
    --border-color: #e0e0e0;
    --input-bg: #fff;
    --input-border: #d0d0d0;
    --success: #28a745;
    --shadow-color: rgba(0,0,0,.1);
}

body.dark-mode {
    --bg-primary: #1a1a2e;
    --bg-card: #1e1e3f;
    --text-primary: #e8eaf6;
    --text-secondary: #b0b3c5;
    --text-muted: #8a8d9f;
    --border-color: #2d2d5a;
    --input-bg: #252550;
    --input-border: #3d3d7a;
    --shadow-color: rgba(0,0,0,.3);
    --navy-primary: #0a1628;
    --navy-dark: #060d16;
    --navy-light: #1a3a5c;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--bg-primary);
    color: var(--text-primary);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    touch-action: manipulation;
}

.wizard-container {
    max-width: 640px;
    width: 100%;
    background: var(--bg-card);
    border-radius: 16px;
    box-shadow: 0 8px 40px var(--shadow-color);
    overflow: hidden;
}

.wizard-header {
    background: linear-gradient(135deg, #001f3f, #003366);
    color: #fff;
    text-align: center;
    padding: 30px 20px;
}
.wizard-logo { width: 60px; height: 60px; border-radius: 50%; margin-bottom: 12px; object-fit: cover; }
.wizard-header h2 { font-size: 20px; margin-bottom: 6px; font-weight: 700; }
.wizard-header p { font-size: 14px; opacity: .8; }

.wizard-progress { display: flex; border-bottom: 1px solid var(--border-color); background: var(--bg-card); }
.wizard-step {
    flex: 1;
    text-align: center;
    padding: 16px 4px;
    font-size: 12px;
    color: var(--text-muted);
    position: relative;
    transition: all .2s;
    cursor: default;
    user-select: none;
}
.wizard-step.active { color: var(--navy-accent); font-weight: 700; }
.wizard-step.done { color: var(--success); }
.wizard-step span {
    display: inline-flex;
    width: 28px; height: 28px;
    border-radius: 50%;
    background: var(--border-color);
    align-items: center;
    justify-content: center;
    margin-right: 4px;
    font-weight: 700;
    font-size: 13px;
    vertical-align: middle;
    transition: all .2s;
}
.wizard-step.active span { background: var(--navy-accent); color: #fff; }
.wizard-step.done span { background: var(--success); color: #fff; }

.wizard-body { padding: 30px; }
.wizard-panel { display: none; }
.wizard-panel.active { display: block; animation: fadeIn .3s; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

.wizard-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 28px; gap: 12px; }

.wiz-field { margin-bottom: 18px; }
.wiz-field label { display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
.wiz-field input,
.wiz-field select,
.wiz-field textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--input-border);
    border-radius: 8px;
    font-size: 15px;
    background: var(--input-bg);
    color: var(--text-primary);
    transition: border-color .2s;
    outline: none;
}
.wiz-field input:focus,
.wiz-field select:focus,
.wiz-field textarea:focus { border-color: var(--navy-accent); }
.wiz-field textarea { resize: vertical; min-height: 60px; }
.wiz-field .field-hint { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

.btn-wiz {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-wiz:disabled { opacity: .6; cursor: not-allowed; }
.btn-next { background: var(--navy-accent); color: #fff; }
.btn-next:hover:not(:disabled) { background: #005fb3; }
.btn-back { background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); }
.btn-back:hover { background: var(--border-color); color: var(--text-primary); }
.btn-skip { background: transparent; color: var(--text-muted); font-size: 13px; }
.btn-skip:hover { color: var(--text-primary); text-decoration: underline; }
.btn-finish { background: var(--success); color: #fff; padding: 14px 32px; font-size: 16px; border-radius: 10px; }
.btn-finish:hover:not(:disabled) { background: #218838; }

/* step 4 done */
.done-icon { font-size: 64px; color: var(--success); text-align: center; margin-bottom: 16px; }
.done-title { text-align: center; font-size: 22px; font-weight: 700; margin-bottom: 8px; }
.done-sub { text-align: center; font-size: 14px; color: var(--text-muted); margin-bottom: 24px; }
.done-summary { background: var(--bg-primary); border-radius: 10px; padding: 16px; margin-bottom: 24px; }
.done-summary .sum-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border-color); font-size: 13px; }
.done-summary .sum-row:last-child { border-bottom: none; }
.done-summary .sum-label { color: var(--text-muted); }
.done-summary .sum-value { font-weight: 600; color: var(--text-primary); }

/* loading popup */
.loading-ov { position: fixed; inset: 0; display: flex; justify-content: center; align-items: center; z-index: 10001; }
.loading-popup { background: var(--bg-card); padding: 30px 40px; border-radius: 4px; box-shadow: 0 4px 24px rgba(0,0,0,0.18); display: flex; flex-direction: column; align-items: center; min-width: 240px; }
.loading-progress { width: 200px; height: 6px; border-radius: 2px; background: var(--border-color); overflow: hidden; margin-bottom: 16px; }
.loading-progress-bar { width: 100%; height: 100%; background: var(--navy-accent); border-radius: 2px; animation: progressIndeterminate 1.5s ease-in-out infinite; transform-origin: left; }
@keyframes progressIndeterminate { 0% { transform: translateX(-100%) scaleX(0.4); } 50% { transform: translateX(20%) scaleX(0.5); } 100% { transform: translateX(100%) scaleX(0.4); } }
.loading-txt { font-size: 15px; color: var(--text-secondary); font-weight: 500; }

/* logo upload */
.logo-upload-area {
    border: 2px dashed var(--input-border);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: var(--input-bg);
}
.logo-upload-area:hover { border-color: var(--navy-accent); background: rgba(0,116,217,.04); }
.logo-upload-area.has-file { border-style: solid; border-color: var(--success); }
.logo-preview { max-width: 100px; max-height: 100px; border-radius: 8px; object-fit: cover; margin-bottom: 8px; display: none; }
.logo-preview.visible { display: inline-block; }
.logo-upload-text { font-size: 13px; color: var(--text-muted); }
.logo-upload-text i { font-size: 24px; color: var(--navy-accent); display: block; margin-bottom: 6px; }
.logo-upload-hint { font-size: 11px; color: var(--text-muted); margin-top: 6px; }
.logo-remove { display: inline-flex; align-items: center; gap: 4px; margin-top: 8px; font-size: 12px; color: #dc3545; cursor: pointer; background: none; border: none; }
.logo-remove:hover { text-decoration: underline; }

/* row layout for price fields */
.wiz-row { display: flex; gap: 16px; }
.wiz-row .wiz-field { flex: 1; }

@media (max-width: 480px) {
    .wizard-body { padding: 20px 16px; }
    .wizard-header { padding: 24px 16px; }
    .wizard-step { font-size: 11px; padding: 12px 2px; }
    .wizard-step span { width: 24px; height: 24px; font-size: 11px; }
    .wiz-row { flex-direction: column; gap: 0; }
    .btn-wiz { padding: 10px 16px; font-size: 13px; }
}
</style>
</head>
<body>

<div class="wizard-container">
    <div class="wizard-header">
        <img src="<?php echo $logo_url; ?>" class="wizard-logo" alt="Logo">
        <h2>Welcome! Let's set up your system</h2>
        <p>Complete these 4 quick steps to get started</p>
    </div>

    <div class="wizard-progress">
        <div class="wizard-step active" data-step="1"><span>1</span> Company</div>
        <div class="wizard-step" data-step="2"><span>2</span> Currency</div>
        <div class="wizard-step" data-step="3"><span>3</span> Product</div>
        <div class="wizard-step" data-step="4"><span>4</span> Done</div>
    </div>

    <div class="wizard-body" id="wizBody"></div>
</div>

<div id="loadWrap"></div>

<script>
/** Developed by Rameez Scripts */

// data from PHP
const currencies = <?php echo json_encode($currencies); ?>;
const taxRates   = <?php echo json_encode($taxRates); ?>;
const prefill = {
    company_name:  '<?php echo addslashes($cur_company); ?>',
    company_email: '<?php echo addslashes($cur_email); ?>',
    company_logo:  '<?php echo addslashes($cur_logo); ?>',
    currency:      '<?php echo addslashes($cur_currency); ?>'
};

let step = 1;
let saved = { company: '', currency: '', tax: '', product: '' };
let loading = false;

function showLoad(msg) {
    loading = true;
    $('#loadWrap').html('<div class="loading-ov"><div class="loading-popup"><div class="loading-progress"><div class="loading-progress-bar"></div></div><div class="loading-txt">' + msg + '</div></div></div>');
}
function hideLoad() {
    loading = false;
    $('#loadWrap').html('');
}

function renderStep() {
    const $body = $('#wizBody');

    // update progress
    $('.wizard-step').each(function() {
        const s = parseInt($(this).data('step'));
        $(this).removeClass('active done');
        if (s < step) $(this).addClass('done').find('span').html('<i class="fas fa-check" style="font-size:12px"></i>');
        else if (s === step) $(this).addClass('active');
    });

    if (step === 1) {
        var hasLogo = !!prefill.company_logo;
        var logoSrc = prefill.company_logo || '';
        $body.html(`
            <div class="wizard-panel active">
                <h3 style="margin-bottom:20px;font-size:17px"><i class="fas fa-building" style="color:var(--navy-accent);margin-right:8px"></i>Company Information</h3>
                <div class="wiz-field">
                    <label>Company Name *</label>
                    <input type="text" id="wCompanyName" value="${prefill.company_name}" placeholder="Your Company Name">
                </div>
                <div class="wiz-field">
                    <label>Company Email</label>
                    <input type="email" id="wCompanyEmail" value="${prefill.company_email}" placeholder="admin@company.com">
                </div>
                <div class="wiz-field">
                    <label>Company Logo</label>
                    <div class="logo-upload-area ${hasLogo ? 'has-file' : ''}" id="logoDropArea" onclick="document.getElementById('wLogoFile').click()">
                        <img src="${logoSrc}" class="logo-preview ${hasLogo ? 'visible' : ''}" id="logoPreview" alt="Logo">
                        <div class="logo-upload-text" id="logoText" ${hasLogo ? 'style="display:none"' : ''}>
                            <i class="fas fa-cloud-upload-alt"></i>
                            Click to upload logo
                        </div>
                        <input type="file" id="wLogoFile" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" style="display:none" onchange="previewLogo(this)">
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div class="logo-upload-hint">JPG, PNG, GIF, WEBP, SVG — max 2MB</div>
                        <button type="button" class="logo-remove" id="logoRemoveBtn" onclick="event.stopPropagation();removeLogo()" ${hasLogo ? '' : 'style="display:none"'}><i class="fas fa-times"></i> Remove</button>
                    </div>
                </div>
                <div class="wizard-actions">
                    <div></div>
                    <button class="btn-wiz btn-next" onclick="saveStep1()">Next <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        `);

        // drag & drop
        var area = document.getElementById('logoDropArea');
        ['dragenter','dragover'].forEach(function(ev) {
            area.addEventListener(ev, function(e) { e.preventDefault(); area.style.borderColor = 'var(--navy-accent)'; });
        });
        ['dragleave','drop'].forEach(function(ev) {
            area.addEventListener(ev, function(e) { e.preventDefault(); area.style.borderColor = ''; });
        });
        area.addEventListener('drop', function(e) {
            var files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('wLogoFile').files = files;
                previewLogo(document.getElementById('wLogoFile'));
            }
        });
    }
    else if (step === 2) {
        let currOpts = currencies.map(c => `<option value="${c.code}" ${c.code === prefill.currency ? 'selected' : ''}>${c.code} - ${c.name} ${c.symbol ? '(' + c.symbol + ')' : ''}</option>`).join('');
        let taxOpts = '<option value="0">-- No Tax --</option>' + taxRates.map(t => `<option value="${t.tax_id}">${t.name} (${parseFloat(t.rate).toFixed(2)}%)</option>`).join('');

        $body.html(`
            <div class="wizard-panel active">
                <h3 style="margin-bottom:20px;font-size:17px"><i class="fas fa-coins" style="color:var(--navy-accent);margin-right:8px"></i>Currency & Tax</h3>
                <div class="wiz-field">
                    <label>Default Currency *</label>
                    <select id="wCurrency">${currOpts}</select>
                </div>
                <div class="wiz-field">
                    <label>Default Tax Rate</label>
                    <select id="wTaxRate">${taxOpts}</select>
                    <div class="field-hint">You can add more tax rates later in Settings</div>
                </div>
                <div class="wizard-actions">
                    <button class="btn-wiz btn-back" onclick="goBack()"><i class="fas fa-arrow-left"></i> Back</button>
                    <button class="btn-wiz btn-next" onclick="saveStep2()">Next <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        `);
    }
    else if (step === 3) {
        $body.html(`
            <div class="wizard-panel active">
                <h3 style="margin-bottom:20px;font-size:17px"><i class="fas fa-box" style="color:var(--navy-accent);margin-right:8px"></i>Create Your First Product</h3>
                <div class="wiz-field">
                    <label>Product Name *</label>
                    <input type="text" id="wProductName" placeholder="e.g. Basic Plan">
                </div>
                <div class="wiz-field">
                    <label>Description</label>
                    <textarea id="wProductDesc" placeholder="Brief description of this product"></textarea>
                </div>
                <div class="wiz-row">
                    <div class="wiz-field">
                        <label>Selling Price *</label>
                        <input type="number" id="wSellPrice" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="wiz-field">
                        <label>Purchase Price</label>
                        <input type="number" id="wBuyPrice" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="wizard-actions">
                    <button class="btn-wiz btn-back" onclick="goBack()"><i class="fas fa-arrow-left"></i> Back</button>
                    <div style="display:flex;gap:10px;align-items:center">
                        <button class="btn-wiz btn-skip" onclick="skipStep3()">I'll add later</button>
                        <button class="btn-wiz btn-next" onclick="saveStep3()">Next <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>
        `);
    }
    else if (step === 4) {
        $body.html(`
            <div class="wizard-panel active">
                <div class="done-icon"><i class="fas fa-check-circle"></i></div>
                <div class="done-title">All Done!</div>
                <div class="done-sub">Your system is ready to use. Here's a summary:</div>
                <div class="done-summary">
                    <div class="sum-row"><span class="sum-label">Company</span><span class="sum-value">${saved.company || '—'}</span></div>
                    <div class="sum-row"><span class="sum-label">Currency</span><span class="sum-value">${saved.currency || '—'}</span></div>
                    <div class="sum-row"><span class="sum-label">Tax Rate</span><span class="sum-value">${saved.tax || 'None'}</span></div>
                    <div class="sum-row"><span class="sum-label">First Product</span><span class="sum-value">${saved.product || 'Skipped'}</span></div>
                </div>
                <div style="text-align:center">
                    <button class="btn-wiz btn-finish" onclick="finishWizard()"><i class="fas fa-rocket"></i> Go to Dashboard</button>
                </div>
            </div>
        `);
    }
}

function goBack() {
    if (step > 1) { step--; renderStep(); }
}

function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
        Swal.fire('Too Large', 'Logo must be less than 2MB.', 'warning');
        input.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
        var prev = document.getElementById('logoPreview');
        prev.src = e.target.result;
        prev.classList.add('visible');
        document.getElementById('logoText').style.display = 'none';
        document.getElementById('logoDropArea').classList.add('has-file');
        document.getElementById('logoRemoveBtn').style.display = '';
    };
    reader.readAsDataURL(file);
}

function removeLogo() {
    document.getElementById('wLogoFile').value = '';
    document.getElementById('logoPreview').classList.remove('visible');
    document.getElementById('logoPreview').src = '';
    document.getElementById('logoText').style.display = '';
    document.getElementById('logoDropArea').classList.remove('has-file');
    document.getElementById('logoRemoveBtn').style.display = 'none';
    prefill.company_logo = '';
}

function saveStep1() {
    const name  = $.trim($('#wCompanyName').val());
    const email = $.trim($('#wCompanyEmail').val());
    const fileInput = document.getElementById('wLogoFile');

    if (!name) return Swal.fire('Required', 'Company name is required.', 'warning');

    var fd = new FormData();
    fd.append('action', 'saveStep');
    fd.append('step', 1);
    fd.append('company_name', name);
    fd.append('company_email', email);
    if (fileInput && fileInput.files.length > 0) {
        fd.append('logo_file', fileInput.files[0]);
    }

    showLoad('Saving company info…');
    $.ajax({
        url: window.location.pathname,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(r) {
            hideLoad();
            if (r.success) {
                saved.company = name;
                prefill.company_name = name;
                prefill.company_email = email;
                if (r.logo_url) prefill.company_logo = r.logo_url;
                step = 2;
                renderStep();
            } else {
                Swal.fire('Error', r.message || 'Failed to save.', 'error');
            }
        },
        error: function() {
            hideLoad();
            Swal.fire('Error', 'Server error. Please try again.', 'error');
        }
    });
}

function saveStep2() {
    const currency = $('#wCurrency').val();
    const taxId    = $('#wTaxRate').val();

    if (!currency) return Swal.fire('Required', 'Please select a currency.', 'warning');

    showLoad('Saving currency & tax…');
    $.post(window.location.pathname, { action: 'saveStep', step: 2, currency: currency, tax_id: taxId }, function(r) {
        hideLoad();
        if (r.success) {
            const cObj = currencies.find(c => c.code === currency);
            saved.currency = cObj ? cObj.code + ' (' + cObj.name + ')' : currency;
            const tSel = $('#wTaxRate option:selected').text();
            saved.tax = taxId > 0 ? tSel : 'None';
            step = 3;
            renderStep();
        } else {
            Swal.fire('Error', r.message || 'Failed to save.', 'error');
        }
    }, 'json').fail(function() {
        hideLoad();
        Swal.fire('Error', 'Server error. Please try again.', 'error');
    });
}

function saveStep3() {
    const name = $.trim($('#wProductName').val());
    const desc = $.trim($('#wProductDesc').val());
    const sell = parseFloat($('#wSellPrice').val()) || 0;
    const buy  = parseFloat($('#wBuyPrice').val()) || 0;

    if (!name) return Swal.fire('Required', 'Product name is required.', 'warning');
    if (sell <= 0) return Swal.fire('Required', 'Selling price must be greater than 0.', 'warning');

    showLoad('Creating product…');
    $.post(window.location.pathname, { action: 'saveStep', step: 3, product_name: name, description: desc, selling_price: sell, purchase_price: buy }, function(r) {
        hideLoad();
        if (r.success) {
            saved.product = name;
            step = 4;
            renderStep();
        } else {
            Swal.fire('Error', r.message || 'Failed to create product.', 'error');
        }
    }, 'json').fail(function() {
        hideLoad();
        Swal.fire('Error', 'Server error. Please try again.', 'error');
    });
}

function skipStep3() {
    showLoad('Skipping…');
    $.post(window.location.pathname, { action: 'saveStep', step: 3, skip: '1' }, function(r) {
        hideLoad();
        saved.product = 'Skipped';
        step = 4;
        renderStep();
    }, 'json').fail(function() {
        hideLoad();
        step = 4;
        renderStep();
    });
}

function finishWizard() {
    showLoad('Finishing setup…');
    $.post(window.location.pathname, { action: 'completeWizard' }, function(r) {
        hideLoad();
        if (r.success) {
            Swal.fire({ icon: 'success', title: 'Setup Complete!', text: 'Redirecting to dashboard…', timer: 1500, showConfirmButton: false }).then(function() {
                window.location.href = r.redirect || 'dashboard.php';
            });
        } else {
            Swal.fire('Error', r.message || 'Failed to complete wizard.', 'error');
        }
    }, 'json').fail(function() {
        hideLoad();
        Swal.fire('Error', 'Server error. Please try again.', 'error');
    });
}

// dark mode from localStorage
if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');

// init
$(function() { renderStep(); });
</script>
</body>
</html>
