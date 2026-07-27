<?php
/**
 * Developed by Mr.Rahul Scripts
 * Report an Issue Page
 */

require_once 'config.php';
ensureCustomPagesTable();

$branding = getSiteBranding();
$currency = getCurrency();

// Fetch content from database if edited by admin
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT page_title, page_content FROM custom_pages WHERE page_slug = 'report-issue' AND is_active = 1");
$stmt->execute();
$res = $stmt->get_result();
$db_page = $res->fetch_assoc();
$stmt->close();

$title = $db_page['page_title'] ?? 'Report an Issue';
$content = $db_page['page_content'] ?? '';

// Support contact details
$support_email = getSetting('support_email', 'contact@Mr.RahulScripts.com');
$support_whatsapp = getSetting('support_whatsapp', '+923394100600');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - <?php echo htmlspecialchars($branding['site_name']); ?></title>
    <meta name="description" content="Report an Issue or Bug to <?php echo htmlspecialchars($branding['site_name']); ?> developer support team.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #120505;
            --bg-card: #1c0809;
            --primary: #e11d48;
            --primary-glow: rgba(225, 29, 72, 0.35);
            --accent: #fb7185;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #2e1012;
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-title: 'Outfit', sans-serif;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: var(--font-main);
            line-height: 1.7;
            padding-top: 90px;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(225, 29, 72, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(225, 29, 72, 0.05) 0%, transparent 40%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 80px;
            background: rgba(18, 5, 5, 0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            z-index: 1000;
            display: flex;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .nav-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
            font-family: var(--font-title);
            font-size: 20px;
            font-weight: 800;
        }

        .logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--primary);
        }

        .page-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 45px 40px;
            margin: 40px auto 60px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            flex: 1;
        }

        .page-header {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 24px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-family: var(--font-title);
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
        }

        .page-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .btn-back:hover {
            color: #fff;
        }

        .contact-card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }

        .contact-c {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            padding: 24px;
            border-radius: 14px;
            text-align: center;
        }

        .contact-c i {
            font-size: 36px;
            margin-bottom: 12px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            margin-top: 14px;
        }

        footer {
            border-top: 1px solid var(--border-color);
            padding: 30px 0;
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
            background: rgba(18, 5, 5, 0.9);
        }

        @media (max-width: 768px) {
            .page-box { padding: 25px 20px; }
            .page-header h1 { font-size: 26px; }
            .contact-card-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="container nav-wrap">
            <a href="index.php" class="logo">
                <img src="<?php echo htmlspecialchars($branding['site_logo']); ?>" alt="Logo">
                <span><?php echo htmlspecialchars($branding['site_name']); ?></span>
            </a>
            <div>
                <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-box">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Homepage</a>
            <div class="page-header">
                <h1><i class="fas fa-bug" style="color:var(--primary);"></i> <?php echo htmlspecialchars($title); ?></h1>
                <p>Report technical issues, license validation errors, or request bug fixes directly from developer support.</p>
            </div>

            <div class="content-body">
                <?php if (!empty($content)): ?>
                    <?php echo nl2br(htmlspecialchars($content)); ?>
                <?php else: ?>
                    <p>Found a bug or experiencing a technical issue with your script, extension, or license key? Contact developer support directly for immediate assistance:</p>

                    <div class="contact-card-grid">
                        <div class="contact-c">
                            <i class="fab fa-whatsapp" style="color:#10b981;"></i>
                            <h3 style="color:#fff; font-size:18px;">Instant WhatsApp Support</h3>
                            <p style="font-size:13px; color:var(--text-muted); margin-top:6px;">Average response time under 2 hours.</p>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $support_whatsapp); ?>?text=<?php echo urlencode('Hi Mr.Rahul, I want to report a technical issue.'); ?>" target="_blank" class="btn-action" style="background:#10b981; color:#fff;">
                                <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                            </a>
                        </div>

                        <div class="contact-c">
                            <i class="fas fa-envelope" style="color:#ef4444;"></i>
                            <h3 style="color:#fff; font-size:18px;">Email Developer Support</h3>
                            <p style="font-size:13px; color:var(--text-muted); margin-top:6px;">Send log tracebacks or error screenshots.</p>
                            <a href="mailto:<?php echo htmlspecialchars($support_email); ?>?subject=Bug Report" class="btn-action" style="background:#ef4444; color:#fff;">
                                <i class="fas fa-paper-plane"></i> Email Developer
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p><?php echo $branding['copyright_text']; ?></p>
        </div>
    </footer>

</body>
</html>
