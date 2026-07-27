<?php
/**
 * Developed by Mr.Rahul Scripts
 *
 * Public Custom Page Viewer (Privacy Policy, Terms of Service, Refund Policy, Report an Issue)
 */

require_once 'config.php';

// Ensure table and defaults exist
ensureCustomPagesTable();

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : 'privacy-policy';

$page = null;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT page_slug, page_title, page_content, updated_at FROM custom_pages WHERE page_slug = ? AND is_active = 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $page = $result->fetch_assoc();
    }
} catch (Exception $e) {
    // Graceful fallback
}

if (!$page) {
    header("HTTP/1.0 404 Not Found");
    $page = [
        'page_title' => 'Page Not Found',
        'page_content' => '<h1>404 - Page Not Found</h1><p>The requested page could not be found or has been disabled by the administrator.</p><p><a href="index.php">← Back to Homepage</a></p>'
    ];
}

$branding = getSiteBranding();
$support_whatsapp_number = getSetting('support_whatsapp_number', '+923394100600');
$support_whatsapp_clean = preg_replace('/[^0-9]/', '', $support_whatsapp_number);
$support_email_address = getSetting('support_email_address', 'contact@Mr.RahulScripts.com');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['page_title']); ?> - <?php echo htmlspecialchars($branding['site_name']); ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #120505;
            --bg-card: #1c0809;
            --bg-darker: #0a0202;
            --primary: #e11d48;
            --accent: #fb7185;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(225, 29, 72, 0.15);
            --title-font: 'Outfit', sans-serif;
            --body-font: 'Plus Jakarta Sans', sans-serif;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: var(--body-font);
            line-height: 1.7;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Navbar */
        header {
            background: rgba(10, 2, 2, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 16px 0;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none !important;
        }

        .logo-img { width: 36px; height: 36px; border-radius: 50%; object-fit: contain; }
        .logo-text { font-family: var(--title-font); font-size: 20px; font-weight: 800; color: #fff; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: #fff; }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); text-decoration: none; }

        /* Page Content Wrap */
        .page-body {
            flex: 1;
            padding: 60px 0;
        }

        .page-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .page-card h1 {
            font-family: var(--title-font);
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-content-render p {
            margin-bottom: 18px;
            color: #cbd5e1;
            font-size: 15px;
        }

        .page-content-render h2, .page-content-render h3 {
            font-family: var(--title-font);
            color: #fff;
            margin: 28px 0 14px;
        }

        .page-content-render ul, .page-content-render ol {
            margin: 0 0 20px 24px;
            color: #cbd5e1;
        }

        .page-content-render li { margin-bottom: 8px; }

        /* Footer */
        footer {
            background: var(--bg-darker);
            border-top: 1px solid var(--border-color);
            padding: 40px 0;
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
            margin-top: auto;
        }
    </style>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo-section">
                <img src="<?php echo htmlspecialchars($branding['site_logo']); ?>" alt="Logo" class="logo-img">
                <span class="logo-text"><?php echo htmlspecialchars($branding['site_name']); ?></span>
            </a>
            <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </header>

    <main class="page-body">
        <div class="container">
            <div class="page-card">
                <div class="page-content-render">
                    <?php echo $page['page_content']; ?>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($branding['site_name']); ?>. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</html>
