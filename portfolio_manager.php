<?php
/**
 * Developed by Mr.Rahul Scripts
 *
 * Frontend Portfolio & Product Preview Manager - Admin Panel
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
$current_page = 'portfolio_manager';

// Admin-only page
if ($role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Ensure database tables exist
ensurePortfolioTable();

$message = '';
$error = '';

// Handle Portfolio Save / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_portfolio_item'])) {
    $portfolio_id   = isset($_POST['portfolio_id']) ? (int)$_POST['portfolio_id'] : 0;
    $episode_tag    = trim($_POST['episode_tag'] ?? 'E1');
    $title          = trim($_POST['title'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $preview_url    = trim($_POST['preview_url'] ?? '');
    $plans_included = trim($_POST['plans_included'] ?? 'Premium Plan, VIP Premium Plan');
    $tags           = trim($_POST['tags'] ?? 'Google Apps Script, Dashboard');
    $display_order  = (int)($_POST['display_order'] ?? 0);
    $is_active      = isset($_POST['is_active']) ? 1 : 0;

    if (empty($title)) {
        $error = 'Portfolio Title is required.';
    } else {
        try {
            $conn = getDBConnection();
            if ($portfolio_id > 0) {
                $stmt = $conn->prepare("UPDATE portfolio_items SET episode_tag = ?, title = ?, description = ?, preview_url = ?, plans_included = ?, tags = ?, display_order = ?, is_active = ? WHERE portfolio_id = ?");
                $stmt->bind_param("ssssssiii", $episode_tag, $title, $description, $preview_url, $plans_included, $tags, $display_order, $is_active, $portfolio_id);
                $stmt->execute();
                logActivity($user_id, $username, 'Updated Portfolio Item', "Updated project ID: {$portfolio_id} ({$title})");
                $message = 'Portfolio project updated successfully!';
            } else {
                $stmt = $conn->prepare("INSERT INTO portfolio_items (episode_tag, title, description, preview_url, plans_included, tags, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssii", $episode_tag, $title, $description, $preview_url, $plans_included, $tags, $display_order, $is_active);
                $stmt->execute();
                logActivity($user_id, $username, 'Created Portfolio Item', "Created project: {$title}");
                $message = 'New portfolio project added successfully!';
            }
        } catch (Exception $e) {
            $error = 'Error saving portfolio item: ' . $e->getMessage();
        }
    }
}

// Handle Delete Portfolio Item
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("DELETE FROM portfolio_items WHERE portfolio_id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        logActivity($user_id, $username, 'Deleted Portfolio Item', "Deleted portfolio ID: {$del_id}");
        $message = 'Portfolio project deleted successfully.';
    } catch (Exception $e) {
        $error = 'Failed to delete portfolio item: ' . $e->getMessage();
    }
}

// Handle Product Preview URL Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product_previews'])) {
    try {
        $conn = getDBConnection();
        if (isset($_POST['product_preview_url']) && is_array($_POST['product_preview_url'])) {
            $stmt = $conn->prepare("UPDATE products SET preview_url = ? WHERE product_id = ?");
            foreach ($_POST['product_preview_url'] as $p_id => $p_url) {
                $p_id_int = (int)$p_id;
                $p_url_str = trim($p_url);
                $stmt->bind_param("si", $p_url_str, $p_id_int);
                $stmt->execute();
            }
            $message = 'Product preview links updated successfully!';
        }
    } catch (Exception $e) {
        $error = 'Failed to update product previews: ' . $e->getMessage();
    }
}

// Fetch all Portfolio Items
$portfolio_items = [];
try {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM portfolio_items ORDER BY display_order ASC, portfolio_id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $portfolio_items[] = $row;
        }
    }
} catch (Exception $e) {}

// Edit Mode for Portfolio Item
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($portfolio_items as $item) {
        if ((int)$item['portfolio_id'] === $edit_id) {
            $edit_item = $item;
            break;
        }
    }
}

// Fetch all Products for Product Preview URL editing
$products_list = [];
try {
    $conn = getDBConnection();
    $res = $conn->query("SELECT product_id, product_name, product_code, preview_url, selling_price FROM products WHERE is_active = 1 ORDER BY display_order ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $products_list[] = $r;
        }
    }
} catch (Exception $e) {}

$branding = getSiteBranding();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Portfolio &amp; Previews Manager - <?php echo htmlspecialchars($branding['site_name']); ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <style>
        .portfolio-grid-layout {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            margin-top: 20px;
        }

        @media (max-width: 992px) {
            .portfolio-grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .portfolio-list-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 14px;
            transition: all 0.3s ease;
        }

        .portfolio-list-card:hover {
            border-color: var(--navy-accent, #0074D9);
        }

        .episode-badge {
            display: inline-block;
            background: rgba(225, 29, 72, 0.15);
            color: #e11d48;
            font-weight: 800;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 4px;
            margin-right: 8px;
        }

        .tag-pill {
            display: inline-block;
            background: rgba(0, 116, 217, 0.1);
            color: var(--navy-accent, #0074D9);
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            margin-right: 4px;
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
                <span>Frontend</span>
                <span class="breadcrumb-sep">/</span>
                <span>Portfolio &amp; Previews</span>
            </div>

            <!-- Page Header -->
            <div class="header" style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h1><i class="fas fa-play-circle"></i> Portfolio &amp; Preview Links Manager</h1>
                    <p style="font-size:13px; opacity:0.8; margin-top:4px;">Manage YouTube video preview links and Apps Script portfolio walkthrough items displayed on your website</p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <a href="portfolio_manager.php" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px;">
                        <i class="fas fa-plus-circle"></i> Add Portfolio Project
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

            <div class="portfolio-grid-layout">
                <!-- Column 1: Existing Portfolio List & Products Preview Links -->
                <div>
                    <!-- Portfolio Projects Section -->
                    <div class="data-section mb-24">
                        <div class="section-header">
                            <h2><i class="fas fa-folder-open"></i> Apps Script Portfolio Items</h2>
                        </div>
                        <p style="font-size:12px; opacity:0.75; margin-bottom:16px;">These project walkthroughs are displayed in the "Popular Apps Script Templates &amp; Source Code" section on your homepage.</p>

                        <?php if (empty($portfolio_items)): ?>
                            <p style="color:var(--text-muted); padding:20px 0;">No portfolio items found. Add one using the form on the right.</p>
                        <?php else: ?>
                            <?php foreach ($portfolio_items as $item): ?>
                                <div class="portfolio-list-card">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                                        <div>
                                            <span class="episode-badge"><?php echo htmlspecialchars($item['episode_tag']); ?></span>
                                            <strong style="font-size:14px; color:#fff;"><?php echo htmlspecialchars($item['title']); ?></strong>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <?php if ($item['preview_url']): ?>
                                                <a href="<?php echo htmlspecialchars($item['preview_url']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding:4px 10px; font-size:11px;" title="Watch Preview">
                                                    <i class="fab fa-youtube" style="color:#ef4444;"></i> Preview
                                                </a>
                                            <?php endif; ?>
                                            <a href="portfolio_manager.php?edit=<?php echo $item['portfolio_id']; ?>" class="btn btn-outline btn-sm" style="padding:4px 10px; font-size:11px;" title="Edit Project">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="portfolio_manager.php?action=delete&id=<?php echo $item['portfolio_id']; ?>" onclick="return confirm('Delete this portfolio project?');" class="btn btn-sm" style="padding:4px 10px; font-size:11px; background:#ef4444; color:#fff;" title="Delete Project">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <p style="font-size:12px; opacity:0.8; margin-bottom:8px; line-height:1.4;"><?php echo htmlspecialchars($item['description'] ?? ''); ?></p>

                                    <div style="font-size:11px; opacity:0.7;">
                                        <strong>Tags:</strong> <?php echo htmlspecialchars($item['tags']); ?>
                                        <span style="margin:0 6px;">•</span>
                                        <strong>Order:</strong> <?php echo (int)$item['display_order']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Products Catalog Preview Links Quick Editor -->
                    <div class="data-section">
                        <div class="section-header">
                            <h2><i class="fas fa-box-open"></i> Product Showcase Preview Video Links</h2>
                        </div>
                        <p style="font-size:12px; opacity:0.75; margin-bottom:16px;">Set video preview links (e.g. YouTube demo links) for each product displayed in the Product Showcase catalog.</p>

                        <form method="POST" action="portfolio_manager.php">
                            <input type="hidden" name="update_product_previews" value="1">
                            
                            <?php if (empty($products_list)): ?>
                                <p style="color:var(--text-muted); padding:10px 0;">No active products found in catalog.</p>
                            <?php else: ?>
                                <?php foreach ($products_list as $prod): ?>
                                    <div style="margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid var(--border-color, #eee);">
                                        <label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">
                                            <i class="fas fa-laptop-code" style="color:var(--navy-accent, #0074D9);"></i> <?php echo htmlspecialchars($prod['product_name']); ?> <code>(<?php echo htmlspecialchars($prod['product_code']); ?>)</code>
                                        </label>
                                        <input type="url" name="product_preview_url[<?php echo $prod['product_id']; ?>]" class="form-control filter-input" value="<?php echo htmlspecialchars($prod['preview_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=..." style="width:100%; padding:8px 12px; border-radius:6px; font-size:13px;">
                                    </div>
                                <?php endforeach; ?>
                                <div style="text-align:right; margin-top:16px;">
                                    <button type="submit" class="btn btn-primary btn-sm" style="padding:8px 24px; background:#10b981; border:none; border-radius:6px; color:#fff; font-weight:700; cursor:pointer;">
                                        <i class="fas fa-save"></i> Save Product Preview Links
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Column 2: Create / Edit Portfolio Item Form -->
                <div class="data-section" style="margin-bottom:0;">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-edit"></i> <?php echo $edit_item ? 'Edit Project: ' . htmlspecialchars($edit_item['episode_tag']) : 'Add Portfolio Project'; ?>
                        </h2>
                    </div>

                    <form method="POST" action="portfolio_manager.php">
                        <input type="hidden" name="save_portfolio_item" value="1">
                        <?php if ($edit_item): ?>
                            <input type="hidden" name="portfolio_id" value="<?php echo $edit_item['portfolio_id']; ?>">
                        <?php endif; ?>

                        <div class="form-group mb-16">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">Episode Tag (e.g. E14, E10, F9)</label>
                            <input type="text" name="episode_tag" class="form-control filter-input" value="<?php echo htmlspecialchars($edit_item['episode_tag'] ?? 'E14'); ?>" placeholder="E14" required style="width:100%; padding:9px; border-radius:6px;">
                        </div>

                        <div class="form-group mb-16">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">Project Title</label>
                            <input type="text" name="title" class="form-control filter-input" value="<?php echo htmlspecialchars($edit_item['title'] ?? ''); ?>" placeholder="Build a Complete Dynamic CRUD Web Dashboard..." required style="width:100%; padding:9px; border-radius:6px;">
                        </div>

                        <div class="form-group mb-16">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;"><i class="fab fa-youtube" style="color:#ef4444;"></i> YouTube Walkthrough / Preview Video Link</label>
                            <input type="url" name="preview_url" class="form-control filter-input" value="<?php echo htmlspecialchars($edit_item['preview_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=..." style="width:100%; padding:9px; border-radius:6px;">
                        </div>

                        <div class="form-group mb-16">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">Short Description</label>
                            <textarea name="description" class="form-control filter-input" rows="4" style="width:100%; padding:9px; border-radius:6px; font-family:inherit; line-height:1.4;"><?php echo htmlspecialchars($edit_item['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group mb-16">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">Plans Included (Comma separated)</label>
                            <input type="text" name="plans_included" class="form-control filter-input" value="<?php echo htmlspecialchars($edit_item['plans_included'] ?? 'Premium Plan, VIP Premium Plan'); ?>" style="width:100%; padding:9px; border-radius:6px;">
                        </div>

                        <div class="form-group mb-16">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">Tags (Comma separated)</label>
                            <input type="text" name="tags" class="form-control filter-input" value="<?php echo htmlspecialchars($edit_item['tags'] ?? 'Google Apps Script, Dashboard'); ?>" style="width:100%; padding:9px; border-radius:6px;">
                        </div>

                        <div class="form-group mb-20" style="display:flex; gap:16px;">
                            <div style="flex:1;">
                                <label style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">Display Order</label>
                                <input type="number" name="display_order" class="form-control filter-input" value="<?php echo (int)($edit_item['display_order'] ?? 1); ?>" style="width:100%; padding:9px; border-radius:6px;">
                            </div>
                            <div style="flex:1; display:flex; align-items:center; margin-top:20px;">
                                <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo (!isset($edit_item) || $edit_item['is_active']) ? 'checked' : ''; ?>>
                                <label for="is_active" style="font-weight:600; font-size:13px; margin-left:8px; cursor:pointer;">Active / Visible</label>
                            </div>
                        </div>

                        <div style="text-align:right;">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-weight: bold; background: #10b981; border: none; border-radius: 6px; color: #fff; cursor: pointer; font-size: 14px;">
                                <i class="fas fa-save"></i> <?php echo $edit_item ? 'Update Project' : 'Publish Project'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
