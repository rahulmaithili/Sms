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

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'products';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {

            case 'getProducts':
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT product_id, product_code, product_name, description, color_code, selling_price, purchase_price, download_url, is_active, display_order, created_at FROM products ORDER BY display_order ASC, product_name ASC");
                $stmt->execute();
                $result = $stmt->get_result();

                $products = [];
                while ($row = $result->fetch_assoc()) {
                    $products[] = [
                        'product_id'     => (int)$row['product_id'],
                        'product_code'   => $row['product_code'] ?? '',
                        'product_name'   => $row['product_name'],
                        'description'    => $row['description'] ?? '',
                        'color_code'     => $row['color_code'] ?? '#0078D4',
                        'selling_price'  => (float)($row['selling_price'] ?? 0),
                        'purchase_price' => (float)($row['purchase_price'] ?? 0),
                        'download_url'   => $row['download_url'] ?? '',
                        'is_active'      => (bool)$row['is_active'],
                        'display_order'  => (int)$row['display_order'],
                        'created_at'     => date('M d, Y', strtotime($row['created_at']))
                    ];
                }

                $stmt->close();
                $currency = getCurrency();
                echo json_encode(['success' => true, 'data' => $products, 'currency' => $currency]);
                exit();

            case 'addProduct':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $product_name   = isset($_POST['product_name'])  ? trim($_POST['product_name'])  : '';
                $product_code   = isset($_POST['product_code'])  ? trim($_POST['product_code'])  : '';
                $description    = isset($_POST['description'])     ? trim($_POST['description'])    : '';
                $color_code     = isset($_POST['color_code'])      ? trim($_POST['color_code'])     : '#0078D4';
                $display_order  = isset($_POST['display_order'])   ? intval($_POST['display_order']) : 0;
                $selling_price  = isset($_POST['selling_price'])   ? floatval($_POST['selling_price'])  : 0;
                $purchase_price = isset($_POST['purchase_price'])  ? floatval($_POST['purchase_price']) : 0;
                $download_url   = isset($_POST['download_url'])    ? trim($_POST['download_url'])   : '';

                if (empty($product_name)) {
                    echo json_encode(['success' => false, 'message' => 'Product name is required']);
                    exit();
                }

                // Auto-generate code slug if empty
                if (empty($product_code)) {
                    $product_code = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $product_name), '-'));
                }

                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color_code)) {
                    $color_code = '#0078D4';
                }

                $descVal = !empty($description) ? $description : null;
                $downloadVal = !empty($download_url) ? $download_url : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare("INSERT INTO products (product_code, product_name, description, color_code, display_order, selling_price, purchase_price, download_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssssidds", $product_code, $product_name, $descVal, $color_code, $display_order, $selling_price, $purchase_price, $downloadVal);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Product Created', "Created product: $product_name");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Product added successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Product name or code already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add product: ' . $conn->error]);
                    }
                }
                exit();

            case 'updateProduct':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $product_id     = isset($_POST['product_id'])     ? intval($_POST['product_id'])    : 0;
                $product_name   = isset($_POST['product_name'])   ? trim($_POST['product_name'])    : '';
                $product_code   = isset($_POST['product_code'])   ? trim($_POST['product_code'])    : '';
                $description    = isset($_POST['description'])      ? trim($_POST['description'])    : '';
                $color_code     = isset($_POST['color_code'])       ? trim($_POST['color_code'])     : '#0078D4';
                $display_order  = isset($_POST['display_order'])    ? intval($_POST['display_order']) : 0;
                $is_active      = isset($_POST['is_active'])        ? intval($_POST['is_active'])    : 1;
                $selling_price  = isset($_POST['selling_price'])    ? floatval($_POST['selling_price'])  : 0;
                $purchase_price = isset($_POST['purchase_price'])   ? floatval($_POST['purchase_price']) : 0;
                $download_url   = isset($_POST['download_url'])     ? trim($_POST['download_url'])   : '';

                if ($product_id <= 0 || empty($product_name)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid input']);
                    exit();
                }

                // Auto-generate code slug if empty
                if (empty($product_code)) {
                    $product_code = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $product_name), '-'));
                }

                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color_code)) {
                    $color_code = '#0078D4';
                }

                $descVal = !empty($description) ? $description : null;
                $downloadVal = !empty($download_url) ? $download_url : null;

                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE products SET product_code = ?, product_name = ?, description = ?, color_code = ?, display_order = ?, selling_price = ?, purchase_price = ?, download_url = ?, is_active = ? WHERE product_id = ?");
                $stmt->bind_param("sssssiddis", $product_code, $product_name, $descVal, $color_code, $display_order, $selling_price, $purchase_price, $downloadVal, $is_active, $product_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Product Updated', "Updated product: $product_name");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
                } else {
                    $errno = $conn->errno;
                    $stmt->close();
                    if ($errno === 1062) {
                        echo json_encode(['success' => false, 'message' => 'Product name or code already exists']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update product: ' . $conn->error]);
                    }
                }
                exit();

            case 'toggleActive':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $product_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
                $is_active   = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

                if ($product_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                    exit();
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("UPDATE products SET is_active = ? WHERE product_id = ?");
                $stmt->bind_param("ii", $is_active, $product_id);

                if ($stmt->execute()) {
                    $action_label = $is_active ? 'Product Activated' : 'Product Deactivated';
                    logActivity($user_id, $username, $action_label, "Changed active status for product ID $product_id");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => $is_active ? 'Product activated' : 'Product deactivated']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                exit();

            case 'deleteProduct':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $product_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                if ($product_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Fetch name before deletion for logging
                $stmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $deletedName = '';
                if ($result->num_rows > 0) {
                    $deletedName = $result->fetch_assoc()['product_name'];
                }
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
                $stmt->bind_param("i", $product_id);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'Product Deleted', "Deleted product: $deletedName");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
                }
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("products.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// If we reach here, render the HTML page
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
    <title>Products - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <style>
        .toggle { appearance: none; width: 44px; height: 24px; border-radius: 24px; background: #ccc; position: relative; cursor: pointer; transition: background .3s; border: none; outline: none; vertical-align: middle; }
        .toggle:checked { background: #0074D9; }
        .toggle::before { content: ""; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform .3s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
        .toggle:checked::before { transform: translateX(20px); }

        .color-picker-group { display: flex; align-items: center; gap: 10px; }
        .color-picker-group input[type="color"] { width: 44px; height: 38px; padding: 2px; border: 1px solid #ccc; border-radius: 6px; cursor: pointer; background: none; }
        .color-hex-display { flex: 1; font-family: monospace; letter-spacing: 1px; }

        /* Extension Integration Modal Tab Styling */
        .tab-btn.active-tab {
            color: #0074D9 !important;
            border-bottom: 3px solid #0074D9 !important;
        }
        .dark-mode .tab-btn.active-tab {
            color: #38bdf8 !important;
            border-bottom: 3px solid #38bdf8 !important;
        }
        .int-tab-content code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            color: #0f172a;
        }
        .dark-mode .int-tab-content code {
            background: #334155;
            color: #f8fafc;
        }
    </style>
</head>
<body>
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span>Products</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-box"></i> Products</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Products</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadProducts()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
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
                            <label><i class="fas fa-search"></i> Product Name</label>
                            <input type="text" id="filterName" class="filter-input" placeholder="Search name...">
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
                    <table id="productsTable" class="display table-full-width"></table>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <div class="modal-overlay" id="productModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-tag"></i> Add Product</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="productForm">
                    <input type="hidden" id="productId" name="product_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Product Name *</label>
                            <input type="text" id="formProductName" name="product_name" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-barcode"></i> Product Code (slug) *</label>
                            <input type="text" id="formProductCode" name="product_code" placeholder="e.g. cdcms-allocation-helper" required>
                            <div class="help-text">Unique text identifier for extensions.</div>
                        </div>

                        <div class="form-group span-2">
                            <label><i class="fas fa-align-left"></i> Description</label>
                            <input type="text" id="formDescription" name="description">
                        </div>

                        <div class="form-group span-2">
                            <label><i class="fas fa-download"></i> Extension / Product Download URL</label>
                            <input type="url" id="formDownloadUrl" name="download_url" placeholder="https://yourserver.com/files/extension.zip">
                            <div class="help-text">Direct link for the user to download the extension zip or digital product.</div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-palette"></i> Color Code</label>
                            <div class="color-picker-group">
                                <input type="color" id="formColorPicker" value="#0078D4" oninput="syncColorHex(this.value)">
                                <input type="text" id="formColorCode" name="color_code" class="color-hex-display" value="#0078D4" maxlength="7" placeholder="#0078D4" oninput="syncColorPicker(this.value)">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-sort-numeric-up"></i> Display Order</label>
                            <input type="number" id="formDisplayOrder" name="display_order" value="0" min="0">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Selling Price</label>
                            <input type="number" id="formSellingPrice" name="selling_price" step="0.001" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-shopping-cart"></i> Purchase Price</label>
                            <input type="number" id="formPurchasePrice" name="purchase_price" step="0.001" min="0" value="0">
                        </div>

                        <div class="form-group" id="activeGroup" style="display:none;">
                            <label><i class="fas fa-toggle-on"></i> Active Status</label>
                            <select id="formIsActive" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

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

    <!-- Extension Integration Modal -->
    <div class="modal-overlay" id="integrationModal" style="display:none; z-index: 1100;">
        <div class="modal" onclick="event.stopPropagation()" style="max-width: 800px; width: 90%;">
            <div class="modal-header">
                <h3><i class="fas fa-code"></i> Extension Integration Guide</h3>
                <button class="close-btn" onclick="closeIntegrationModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding-top: 10px;">
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
                    Integrate this product (<strong id="intProductName" style="color:#0074D9;"></strong>) with your Chrome Extension by adding the following files into your extension project directory.
                </p>
                
                <!-- Tab Headers -->
                <div class="tab-headers" style="display:flex; border-bottom: 2px solid var(--border-color); margin-bottom:15px; gap: 10px;">
                    <button class="tab-btn active-tab" onclick="switchIntTab('instructions')" id="tab-instructions" style="background:none; border:none; padding: 10px 16px; font-weight:600; cursor:pointer; color:var(--text-muted); border-bottom: 3px solid transparent;">1. Instructions</button>
                    <button class="tab-btn" onclick="switchIntTab('config')" id="tab-config" style="background:none; border:none; padding: 10px 16px; font-weight:600; cursor:pointer; color:var(--text-muted); border-bottom: 3px solid transparent;">2. config.js</button>
                    <button class="tab-btn" onclick="switchIntTab('html')" id="tab-html" style="background:none; border:none; padding: 10px 16px; font-weight:600; cursor:pointer; color:var(--text-muted); border-bottom: 3px solid transparent;">3. popup.html</button>
                    <button class="tab-btn" onclick="switchIntTab('js')" id="tab-js" style="background:none; border:none; padding: 10px 16px; font-weight:600; cursor:pointer; color:var(--text-muted); border-bottom: 3px solid transparent;">4. popup.js</button>
                </div>

                <!-- Tab 1: Instructions -->
                <div id="int-tab-instructions" class="int-tab-content">
                    <h4 style="margin-top:0;">Integration Steps:</h4>
                    <ol style="font-size:14px; line-height:1.6; padding-left:20px; color:#555;">
                        <li>Create a file named <code>config.js</code> in your extension directory. Copy and paste the configuration snippet from the <strong>config.js</strong> tab.</li>
                        <li>Create a file named <code>popup.html</code> and paste the HTML snippet from the <strong>popup.html</strong> tab. (It includes config.js dynamically).</li>
                        <li>Create a file named <code>popup.js</code> and paste the JavaScript helper from the <strong>popup.js</strong> tab.</li>
                        <li>Make sure your extension's <code>manifest.json</code> specifies <code>popup.html</code> as your default popup:
                            <pre style="background:#f4f6f8; padding:10px; border-radius:6px; font-size:12px; margin-top:5px; color:#333;">"action": {
  "default_popup": "popup.html"
}</pre>
                        </li>
                    </ol>
                </div>

                <!-- Tab 2: config.js -->
                <div id="int-tab-config" class="int-tab-content" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-size:12px; color:#666;">Copy into <code>config.js</code></span>
                        <button class="btn btn-secondary btn-sm" onclick="copySnippet('codeConfig')" style="padding: 4px 10px; font-size:12px;"><i class="fas fa-copy"></i> Copy Code</button>
                    </div>
                    <textarea id="codeConfig" readonly style="width:100%; height:250px; font-family:monospace; font-size:12px; background:#0f172a; color:#f8fafc; border-radius:6px; padding:12px; border:none; resize:none;"></textarea>
                </div>

                <!-- Tab 3: popup.html -->
                <div id="int-tab-html" class="int-tab-content" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-size:12px; color:#666;">Copy into <code>popup.html</code></span>
                        <button class="btn btn-secondary btn-sm" onclick="copySnippet('codeHtml')" style="padding: 4px 10px; font-size:12px;"><i class="fas fa-copy"></i> Copy Code</button>
                    </div>
                    <textarea id="codeHtml" readonly style="width:100%; height:250px; font-family:monospace; font-size:12px; background:#0f172a; color:#f8fafc; border-radius:6px; padding:12px; border:none; resize:none;"></textarea>
                </div>

                <!-- Tab 4: popup.js -->
                <div id="int-tab-js" class="int-tab-content" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-size:12px; color:#666;">Copy into <code>popup.js</code></span>
                        <button class="btn btn-secondary btn-sm" onclick="copySnippet('codeJs')" style="padding: 4px 10px; font-size:12px;"><i class="fas fa-copy"></i> Copy Code</button>
                    </div>
                    <textarea id="codeJs" readonly style="width:100%; height:250px; font-family:monospace; font-size:12px; background:#0f172a; color:#f8fafc; border-radius:6px; padding:12px; border:none; resize:none;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--border-color); text-align:right;">
                <button class="btn btn-secondary" onclick="closeIntegrationModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
    // Lazy-load export dependencies (PDF/Excel) on first use
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
        let productsTable;
        let isEditMode = false;
        let productsData = [];
        let currency = 'INR';

        $(document).ready(function() {
            loadProducts();
        });

        function loadProducts() {
            $.ajax({
                url: '?action=getProducts',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        productsData = response.data;
                        currency = response.currency || 'INR';
                        $('#filtersSection').show();
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to load products'
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
            if (productsTable) {
                productsTable.destroy();
                $('#productsTable').empty();
            }

            setTimeout(function() {
                productsTable = $('#productsTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        { data: 'product_id', title: 'ID' },
                        {
                            data: 'color_code',
                            title: 'Color',
                            orderable: false,
                            render: function(data) {
                                return '<span style="display:inline-block;width:20px;height:20px;background:' + data + ';border-radius:4px;border:1px solid rgba(0,0,0,.15)" title="' + data + '"></span>';
                            }
                        },
                        { data: 'product_name', title: 'Product Name' },
                        { data: 'description', title: 'Description', defaultContent: '-' },
                        {
                            data: 'selling_price',
                            title: 'Selling Price',
                            render: function(data) {
                                return currency + ' ' + parseFloat(data || 0).toFixed(3);
                            }
                        },
                        {
                            data: 'purchase_price',
                            title: 'Purchase Price',
                            render: function(data) {
                                return currency + ' ' + parseFloat(data || 0).toFixed(3);
                            }
                        },
                        { data: 'display_order', title: 'Order' },
                        {
                            data: 'is_active',
                            title: 'Active',
                            render: function(data, type, row) {
                                var checked = data ? ' checked="checked"' : '';
                                return '<input type="checkbox"' + checked + ' class="toggle" onchange="toggleActive(' + row.product_id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        { data: 'created_at', title: 'Created' },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                return '<button class="action-icon code-icon" onclick="showIntegrationCode(' + row.product_id + ')" title="Get Extension Integration Code" style="color:#0074D9;margin-right:4px;border:none;background:none;cursor:pointer;"><i class="fas fa-code"></i></button>' +
                                       '<button class="action-icon edit-icon" onclick=\'editProduct(' + JSON.stringify(row).replace(/'/g, "\\'") + ')\'><i class="fas fa-edit"></i></button>' +
                                       '<button class="action-icon delete-icon" onclick="deleteProduct(' + row.product_id + ')"><i class="fas fa-trash"></i></button>';
                            }
                        }
                    ],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: [0, 2, 3, 4, 5, 6, 8] }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: [0, 2, 3, 4, 5, 6, 8] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: [0, 2, 3, 4, 5, 6, 8] }
                        }
                    ],
                    order: [[6, 'asc'], [2, 'asc']]
                });

                // Apply custom filters on input/change
                $('#filterName').on('keyup', function() {
                    applyFilters();
                });
                $('#filterStatus').on('change', function() {
                    applyFilters();
                });
            }, 100);
        }

        function applyFilters() {
            if (!productsTable) return;

            $.fn.dataTable.ext.search = [];

            var nameFilter   = document.getElementById('filterName').value.toLowerCase();
            var statusFilter = document.getElementById('filterStatus').value;

            $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
                var row = productsData[dataIndex];
                if (!row) return true;

                // Name filter
                if (nameFilter && row.product_name.toLowerCase().indexOf(nameFilter) === -1) return false;

                // Status filter
                if (statusFilter === 'active'   && !row.is_active)  return false;
                if (statusFilter === 'inactive'  &&  row.is_active)  return false;

                return true;
            });

            productsTable.draw();
        }

        function clearFilters() {
            document.getElementById('filterName').value   = '';
            document.getElementById('filterStatus').value = '';

            if (productsTable) {
                $.fn.dataTable.ext.search = [];
                productsTable.columns().search('').draw();
            }
        }

        // ── Color picker helpers ──────────────────────────────────────────────
        function syncColorHex(value) {
            document.getElementById('formColorCode').value = value.toUpperCase();
        }

        function syncColorPicker(value) {
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                document.getElementById('formColorPicker').value = value;
            }
        }

        // ── Modal helpers ─────────────────────────────────────────────────────
        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-tag"></i> Add Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value       = '';
            document.getElementById('formProductCode').value  = '';
            document.getElementById('formColorPicker').value  = '#0078D4';
            document.getElementById('formColorCode').value    = '#0078D4';
            document.getElementById('formDisplayOrder').value = '0';
            document.getElementById('formSellingPrice').value = '0';
            document.getElementById('formPurchasePrice').value = '0';
            document.getElementById('formDownloadUrl').value  = '';
            document.getElementById('activeGroup').style.display = 'none';
            document.getElementById('productModal').classList.add('active');
        }

        function editProduct(cat) {
            isEditMode = true;
            document.getElementById('modalTitle').innerHTML    = '<i class="fas fa-edit"></i> Edit Product';
            document.getElementById('productId').value        = cat.product_id;
            document.getElementById('formProductName').value  = cat.product_name;
            document.getElementById('formProductCode').value  = cat.product_code || '';
            document.getElementById('formDescription').value   = cat.description || '';
            var color = cat.color_code || '#0078D4';
            document.getElementById('formColorPicker').value   = color;
            document.getElementById('formColorCode').value     = color.toUpperCase();
            document.getElementById('formDisplayOrder').value  = cat.display_order;
            document.getElementById('formSellingPrice').value  = cat.selling_price || 0;
            document.getElementById('formPurchasePrice').value = cat.purchase_price || 0;
            document.getElementById('formDownloadUrl').value   = cat.download_url || '';
            document.getElementById('formIsActive').value      = cat.is_active ? '1' : '0';
            document.getElementById('activeGroup').style.display = '';
            document.getElementById('productModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
            document.getElementById('productForm').reset();
        }

        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // ── Form submit ───────────────────────────────────────────────────────
        document.getElementById('productForm').addEventListener('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var action   = isEditMode ? 'updateProduct' : 'addProduct';

            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
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
                        setTimeout(function() { loadProducts(); }, 100);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Connection error: ' + error
                    });
                }
            });
        });

        // ── Toggle active ─────────────────────────────────────────────────────
        function toggleActive(productId, isActive) {
            var formData = new FormData();
            formData.append('id', productId);
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
                        setTimeout(function() { loadProducts(); }, 100);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                        setTimeout(function() { loadProducts(); }, 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Connection error: ' + error
                    });
                }
            });
        }

        // ── Delete product ───────────────────────────────────────────────────
        function deleteProduct(productId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Product?',
                text: 'This action cannot be undone',
                showCancelButton: true,
                confirmButtonColor: '#ea4335',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    var formData = new FormData();
                    formData.append('id', productId);

                    $.ajax({
                        url: '?action=deleteProduct',
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
                                setTimeout(function() { loadProducts(); }, 100);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Connection error: ' + error
                            });
                        }
                    });
                }
            });
        }

        // ============================================================
        // Chrome Extension Integration Modal Code
        // ============================================================
        function showIntegrationCode(productId) {
            const product = productsData.find(p => p.product_id === productId);
            const productName = product ? product.product_name : "Extension";
            const productCode = product ? product.product_code : "";
            document.getElementById('intProductName').innerText = productName;
            
            // Set values inside textarea snippets
            const apiUrl = "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/api.php'; ?>";
            
            // config.js template
            const configCode = `// Central configuration for this extension
const LICENSE_CONFIG = {
    API_URL: "${apiUrl}",
    PRODUCT_CODE: "${productCode}" // Unique text identifier for ${productName}
};`;

            // popup.html template
            const htmlCode = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { width: 300px; font-family: Arial, sans-serif; padding: 15px; margin: 0; background: #fafafa; }
        .card { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        h3 { margin-top: 0; color: #0074D9; }
        .form-group { margin-bottom: 12px; }
        label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px; color: #555; }
        input { width: 90%; padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px; }
        button { width: 98%; padding: 10px; background: #0074D9; color: #fff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 13px; }
        button:hover { background: #0056b3; }
        .error { color: red; font-size: 12px; margin-top: 8px; font-weight: bold; }
        .success { color: green; font-size: 12px; margin-top: 8px; font-weight: bold; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="card">
        <!-- 1. Activation Screen (If license not verified) -->
        <div id="activationScreen">
            <h3>Activate Extension</h3>
            <div class="form-group">
                <label>Enter License Key</label>
                <input type="text" id="licenseKeyInput" placeholder="SMS-XXXX-XXXX-XXXX-XXXX">
            </div>
            <button id="activateBtn">Activate Now</button>
            <div id="errorMessage" class="error hidden"></div>
        </div>

        <!-- 2. Main Tool Screen (Unlocked state) -->
        <div id="toolScreen" class="hidden">
            <h3>Extension Active 🎉</h3>
            <p style="font-size:13px; color:#444;">Your subscription is active and tool is fully unlocked.</p>
            <div id="licenseInfo" style="font-size:12px; background:#f0f7ff; padding:8px; border-radius:4px; border:1px solid #bcd; color:#333;"></div>
            <br>
            <!-- Your extension dashboard features go here -->
            <button id="deactivateBtn" style="background:#dc3545;">Reset License</button>
        </div>
    </div>
    <!-- Include central configuration file -->
    <script src="config.js"></script>
    <!-- Include licensing helper file -->
    <script src="popup.js"></script>
</body>
</html>`;

            // popup.js template (configured dynamically from config.js!)
            const jsCode = `// Licensing helper for Chrome Extensions
const CONFIG = LICENSE_CONFIG;

document.addEventListener('DOMContentLoaded', function() {
    checkLicense();

    document.getElementById('activateBtn').addEventListener('click', function() {
        const key = document.getElementById('licenseKeyInput').value.trim();
        if (!key) {
            showError("Please enter a license key.");
            return;
        }
        activateLicense(key);
    });

    document.getElementById('deactivateBtn').addEventListener('click', function() {
        chrome.storage.local.clear(function() {
            showActivationScreen();
        });
    });
});

function checkLicense() {
    chrome.storage.local.get(['license_key', 'license_active', 'expiry_date'], function(data) {
        if (data.license_active && data.license_key) {
            verifyWithServer(data.license_key, function(isValid, response) {
                if (isValid) {
                    showToolScreen(response.expiry_date, response.customer_name);
                } else {
                    showActivationScreen("License is expired or deactivated.");
                }
            });
        } else {
            showActivationScreen();
        }
    });
}

function verifyWithServer(key, callback) {
    const url = \`\${CONFIG.API_URL}?action=verify&license_key=\${encodeURIComponent(key)}&product_code=\${CONFIG.PRODUCT_CODE}\`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.valid) {
                callback(true, data);
            } else {
                callback(false, null);
            }
        })
        .catch(err => {
            console.error("API Connection Error:", err);
            // Optional: fallback to local memory in case network is down
            callback(true, { expiry_date: "Offline Backup" });
        });
}

function activateLicense(key) {
    showError("");
    verifyWithServer(key, function(isValid, response) {
        if (isValid) {
            chrome.storage.local.set({
                license_key: key,
                license_active: true,
                expiry_date: response.expiry_date
            }, function() {
                showToolScreen(response.expiry_date, response.customer_name);
            });
        } else {
            showError("Invalid, expired, or wrong product License Key.");
        }
    });
}

function showActivationScreen(errMsg = "") {
    document.getElementById('activationScreen').classList.remove('hidden');
    document.getElementById('toolScreen').classList.add('hidden');
    if (errMsg) showError(errMsg);
}

function showToolScreen(expiry, customerName) {
    document.getElementById('activationScreen').classList.add('hidden');
    document.getElementById('toolScreen').classList.remove('hidden');
    document.getElementById('licenseInfo').innerHTML = \`
        <strong>User:</strong> \${customerName || 'Subscriber'}<br>
        <strong>Expiry:</strong> \${expiry}
    \`;
}

function showError(msg) {
    const el = document.getElementById('errorMessage');
    if (msg) {
        el.textContent = msg;
        el.classList.remove('hidden');
    } else {
        el.classList.add('hidden');
    }
}`;

            document.getElementById('codeConfig').value = configCode;
            document.getElementById('codeHtml').value = htmlCode;
            document.getElementById('codeJs').value = jsCode;

            // Open Modal
            switchIntTab('instructions');
            document.getElementById('integrationModal').style.display = 'flex';
        }

        function closeIntegrationModal() {
            document.getElementById('integrationModal').style.display = 'none';
        }

        function switchIntTab(tabName) {
            // Hide all contents
            document.querySelectorAll('.int-tab-content').forEach(function(el) {
                el.style.display = 'none';
            });
            // Show current content
            document.getElementById('int-tab-' + tabName).style.display = 'block';

            // Deactivate all tab headers
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.classList.remove('active-tab');
            });
            // Activate current tab header
            document.getElementById('tab-' + tabName).classList.add('active-tab');
        }

        function copySnippet(elementId) {
            const copyText = document.getElementById(elementId);
            copyText.select();
            copyText.setSelectionRange(0, 99999); // mobile support
            navigator.clipboard.writeText(copyText.value);
            
            Swal.fire({
                icon: 'success',
                title: 'Code Copied!',
                text: 'Snippet copied to clipboard successfully.',
                timer: 1500,
                showConfirmButton: false
            });
        }
    </script>
</body>
</html>
