<?php
/**
 * Developed by Mr.Rahul Scripts
 *
 * Custom Website Pages Manager - Admin Panel
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
$current_page = 'pages';

// Admin-only page
if ($role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Ensure database table exists
ensureCustomPagesTable();

$message = '';
$error = '';

// Handle Page Save / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_page'])) {
    $page_id     = isset($_POST['page_id']) ? (int)$_POST['page_id'] : 0;
    $page_slug   = trim($_POST['page_slug'] ?? '');
    $page_title  = trim($_POST['page_title'] ?? '');
    $page_content= trim($_POST['page_content'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if (empty($page_slug) || empty($page_title)) {
        $error = 'Page Slug and Page Title are required.';
    } else {
        try {
            $conn = getDBConnection();
            if ($page_id > 0) {
                $stmt = $conn->prepare("UPDATE custom_pages SET page_slug = ?, page_title = ?, page_content = ?, is_active = ? WHERE page_id = ?");
                $stmt->bind_param("sssii", $page_slug, $page_title, $page_content, $is_active, $page_id);
                $stmt->execute();
                logActivity($user_id, $username, 'Updated Custom Page', "Updated page ID: {$page_id} ({$page_title})");
                $message = 'Page updated successfully!';
            } else {
                $stmt = $conn->prepare("INSERT INTO custom_pages (page_slug, page_title, page_content, is_active) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $page_slug, $page_title, $page_content, $is_active);
                $stmt->execute();
                logActivity($user_id, $username, 'Created Custom Page', "Created new page: {$page_title}");
                $message = 'New custom page created successfully!';
            }
        } catch (Exception $e) {
            $error = 'Error saving page: ' . $e->getMessage();
        }
    }
}

// Handle Delete Page
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("DELETE FROM custom_pages WHERE page_id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        logActivity($user_id, $username, 'Deleted Custom Page', "Deleted page ID: {$del_id}");
        $message = 'Page deleted successfully.';
    } catch (Exception $e) {
        $error = 'Failed to delete page: ' . $e->getMessage();
    }
}

// Fetch all pages
$pages = [];
try {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM custom_pages ORDER BY page_id ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pages[] = $row;
        }
    }
} catch (Exception $e) {}

// Edit mode check
$edit_page = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($pages as $p) {
        if ((int)$p['page_id'] === $edit_id) {
            $edit_page = $p;
            break;
        }
    }
}

$branding = getSiteBranding();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Website Pages Manager - <?php echo htmlspecialchars($branding['site_name']); ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">

    <style>
        .pages-grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 20px;
        }

        @media (max-width: 992px) {
            .pages-grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .page-list-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .page-list-item:hover {
            border-color: var(--navy-accent, #0074D9);
        }

        .page-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .page-badge.inactive {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
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
                <span>Pages Manager</span>
            </div>

            <!-- Page Header -->
            <div class="header" style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h1><i class="fas fa-file-alt"></i> Website Pages Manager</h1>
                    <p style="font-size:13px; opacity:0.8; margin-top:4px;">Manage custom legal &amp; policy pages (Privacy Policy, Terms of Service, Refund Policy, Report Issue)</p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <a href="pages.php" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px;">
                        <i class="fas fa-plus-circle"></i> Create New Page
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

            <div class="pages-grid-layout">
                <!-- Column 1: List of existing pages -->
                <div class="data-section" style="margin-bottom:0;">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> All Custom Pages</h2>
                    </div>
                    
                    <?php if (empty($pages)): ?>
                        <p style="color:var(--text-muted); padding:20px 0;">No pages found. Create one using the form on the right.</p>
                    <?php else: ?>
                        <?php foreach ($pages as $p): ?>
                            <div class="page-list-item">
                                <div>
                                    <div style="font-weight:700; font-size:15px; margin-bottom:4px;">
                                        <?php echo htmlspecialchars($p['page_title']); ?>
                                        <span class="page-badge <?php echo $p['is_active'] ? '' : 'inactive'; ?>" style="margin-left:8px;">
                                            <?php echo $p['is_active'] ? 'Active' : 'Disabled'; ?>
                                        </span>
                                    </div>
                                    <div style="font-size:12px; opacity:0.7;">
                                        URL: <code>page.php?slug=<?php echo htmlspecialchars($p['page_slug']); ?></code>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <a href="page.php?slug=<?php echo urlencode($p['page_slug']); ?>" target="_blank" class="btn btn-secondary btn-sm" title="View Page" style="padding:6px 12px; font-size:12px;">
                                        <i class="fas fa-external-link-alt"></i> View
                                    </a>
                                    <a href="pages.php?edit=<?php echo $p['page_id']; ?>" class="btn btn-outline btn-sm" title="Edit Page" style="padding:6px 12px; font-size:12px;">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="pages.php?action=delete&id=<?php echo $p['page_id']; ?>" onclick="return confirm('Are you sure you want to delete this page?');" class="btn btn-sm" style="padding:6px 12px; font-size:12px; background:#ef4444; color:#fff;" title="Delete Page">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Column 2: Create / Edit Page Form -->
                <div class="data-section" style="margin-bottom:0;">
                    <div class="section-header">
                        <h2>
                            <i class="fas fa-edit"></i> <?php echo $edit_page ? 'Edit Page: ' . htmlspecialchars($edit_page['page_title']) : 'Create New Custom Page'; ?>
                        </h2>
                    </div>

                    <form method="POST" action="pages.php">
                        <input type="hidden" name="save_page" value="1">
                        <?php if ($edit_page): ?>
                            <input type="hidden" name="page_id" value="<?php echo $edit_page['page_id']; ?>">
                        <?php endif; ?>

                        <div class="form-group mb-20">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Page Title</label>
                            <input type="text" name="page_title" class="form-control filter-input" value="<?php echo htmlspecialchars($edit_page['page_title'] ?? ''); ?>" placeholder="e.g. Privacy Policy" required style="width:100%; padding:10px; border-radius:6px;">
                        </div>

                        <div class="form-group mb-20">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">URL Slug (Unique ID)</label>
                            <input type="text" name="page_slug" class="form-control filter-input" value="<?php echo htmlspecialchars($edit_page['page_slug'] ?? ''); ?>" placeholder="e.g. privacy-policy" required style="width:100%; padding:10px; border-radius:6px;">
                        </div>

                        <div class="form-group mb-20">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:6px;">Page HTML / Text Content</label>
                            <textarea name="page_content" class="form-control filter-input" rows="12" style="width:100%; padding:10px; border-radius:6px; font-family:monospace; line-height:1.5;" placeholder="<h1>Page Title</h1><p>Write your HTML content here...</p>"><?php echo htmlspecialchars($edit_page['page_content'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group mb-24" style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo (!isset($edit_page) || $edit_page['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active" style="font-weight:600; font-size:14px; cursor:pointer;">Enable / Publish Page on Website</label>
                        </div>

                        <div style="text-align:right;">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-weight: bold; background: #10b981; border: none; border-radius: 6px; color: #fff; cursor: pointer; font-size: 14px;">
                                <i class="fas fa-save"></i> <?php echo $edit_page ? 'Update Page' : 'Publish Page'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
