<?php
/**
 * Developed by Mr.Rahul Scripts
 * Refund Policy Page
 */

require_once 'config.php';
ensureCustomPagesTable();

$branding = getSiteBranding();
$currency = getCurrency();

// Fetch content from database if edited by admin
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT page_title, page_content FROM custom_pages WHERE page_slug = 'refund-policy' AND is_active = 1");
$stmt->execute();
$res = $stmt->get_result();
$db_page = $res->fetch_assoc();
$stmt->close();

$title = $db_page['page_title'] ?? 'Refund Policy';
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
    <meta name="description" content="Refund Policy for <?php echo htmlspecialchars($branding['site_name']); ?> - Clear guidelines on digital downloads, script licenses, and refunds.">
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

        .content-body h2 {
            font-family: var(--font-title);
            color: var(--accent);
            font-size: 20px;
            margin: 28px 0 12px;
        }

        .content-body p, .content-body ul {
            color: #cbd5e1;
            font-size: 15px;
            margin-bottom: 16px;
        }

        .content-body ul {
            padding-left: 24px;
        }

        .content-body li {
            margin-bottom: 8px;
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
                <h1><i class="fas fa-undo-alt" style="color:var(--primary);"></i> <?php echo htmlspecialchars($title); ?></h1>
                <p>Last updated: <?php echo date('F d, Y'); ?> | Fair &amp; Transparent Refund Policy</p>
            </div>

            <div class="content-body">
                <?php if (!empty($content)): ?>
                    <?php echo nl2br(htmlspecialchars($content)); ?>
                <?php else: ?>
                    <h2>1. Digital Products Nature</h2>
                    <p>Due to the digital nature of our products (Google Apps Script files, PHP source code packages, dynamic automation templates, and instant license keys), downloadable digital goods cannot be physically returned once delivered.</p>

                    <h2>2. Eligible Refund Conditions</h2>
                    <p>We take pride in delivering top-quality code. A full refund or key replacement will be issued under the following specific circumstances:</p>
                    <ul>
                        <li><strong>Defective Code:</strong> The script or extension has a critical bug that our developer team is unable to fix within 7 business days of reporting.</li>
                        <li><strong>Duplicate Payment:</strong> You were charged twice for the same transaction due to a payment gateway error.</li>
                        <li><strong>Undelivered Item:</strong> You paid for a product but did not receive your ZIP package or activation key within 24 hours.</li>
                    </ul>

                    <h2>3. Non-Refundable Cases</h2>
                    <p>Refunds will NOT be granted in the following scenarios:</p>
                    <ul>
                        <li>Change of mind after downloading full unencrypted source code.</li>
                        <li>Incompatibility caused by third-party modifications, external API deprecations, or hosting environments not meeting minimum PHP/Apps Script requirements.</li>
                        <li>License key revocation due to violation of Terms of Service.</li>
                    </ul>

                    <h2>4. How to Request a Refund</h2>
                    <p>To initiate a refund request, please email <strong><?php echo htmlspecialchars($support_email); ?></strong> or message us on WhatsApp at <strong><?php echo htmlspecialchars($support_whatsapp); ?></strong> with your invoice number, payment transaction ID, and a brief explanation of the technical issue.</p>
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
