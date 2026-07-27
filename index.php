<?php
/**
 * Public Landing Page & Homepage
 * 
 * Showcases products, pricing, features dynamically from the database.
 * Redirects already logged-in users directly to their dashboard.
 */

require_once 'config.php';

// Check maintenance mode
if (isMaintenanceMode()) {
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: dashboard.php");
        exit();
    }
    header("Location: maintenance.php");
    exit();
}

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'customer') {
        header("Location: customer_portal.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

// Fetch active products to display in the catalog
$products = [];
try {
    $conn = getDBConnection();
    $result = $conn->query("SELECT product_id, product_code, product_name, description, color_code, selling_price FROM products WHERE is_active = 1 ORDER BY display_order ASC, product_name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
} catch (Exception $e) {
    // Database connection might not be configured yet, continue gracefully
}

$branding = getSiteBranding();
$currency = getCurrency();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($branding['site_name']); ?> - Professional Developer Tools</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #120505;
            --bg-darker: #0b0202;
            --bg-card: #1c0a0a;
            --bg-card-hover: #260f0f;
            --border-color: #3d1717;
            --border-hover: #5c2424;
            --primary: #e11d48;
            --primary-glow: rgba(225, 29, 72, 0.15);
            --accent: #fda4af;
            --text-main: #f8fafc;
            --text-muted: #fda4af90;
            --green-glow: #10b981;
            --font-family: 'Outfit', sans-serif;
            --title-font: 'Plus Jakarta Sans', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-dark);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Navbar */
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(18, 5, 5, 0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-main);
        }

        .logo-img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            box-shadow: 0 0 10px var(--primary-glow);
        }

        .logo-text {
            font-family: var(--title-font);
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 30%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-links a:hover {
            color: #fff;
            text-shadow: 0 0 8px var(--primary-glow);
        }

        .nav-btns {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 24px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            gap: 8px;
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.02);
        }

        .btn-outline:hover {
            background: rgba(225, 29, 72, 0.08);
            border-color: var(--primary);
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 15px rgba(225, 29, 72, 0.4);
        }

        .btn-primary:hover {
            background: #f43f5e;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(225, 29, 72, 0.6);
        }

        .btn-success {
            background: var(--green-glow);
            color: #fff;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            background: #10b981eb;
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            padding: 180px 0 100px;
            position: relative;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 40px;
            align-items: center;
        }

        .hero-content {
            text-align: left;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            background: rgba(225, 29, 72, 0.1);
            border: 1px solid rgba(225, 29, 72, 0.2);
            color: var(--primary);
            font-size: 13px;
            font-weight: 700;
            border-radius: 30px;
            margin-bottom: 24px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .hero-badge i {
            font-size: 10px;
        }

        .hero h1 {
            font-family: var(--title-font);
            font-size: 56px;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -1.5px;
            margin-bottom: 24px;
            background: linear-gradient(135deg, #fff 40%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 17px;
            color: var(--text-muted);
            margin-bottom: 40px;
            max-width: 600px;
            line-height: 1.7;
        }

        .hero-btn-group {
            display: flex;
            gap: 16px;
            margin-bottom: 40px;
        }

        .hero-stats-row {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            border-top: 1px solid var(--border-color);
            padding-top: 24px;
        }

        .hero-stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-main);
            font-weight: 500;
        }

        .hero-stat-item i {
            color: var(--green-glow);
        }

        /* Dynamic Orbit Graphic */
        .hero-graphic-wrap {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 400px;
        }

        .orbit-container {
            position: relative;
            width: 320px;
            height: 320px;
            border: 1px dashed rgba(225, 29, 72, 0.2);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: orbit-rotate 40s linear infinite;
        }

        .center-logo-hub {
            position: absolute;
            width: 130px;
            height: 130px;
            background: radial-gradient(circle, var(--bg-card) 0%, var(--bg-darker) 100%);
            border: 3px solid var(--primary);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 40px var(--primary-glow);
            z-index: 10;
            animation: orbit-anti-rotate 40s linear infinite; /* keep text upright */
        }

        .center-logo-hub img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            margin-bottom: 6px;
        }

        .center-logo-hub span {
            font-family: var(--title-font);
            font-size: 12px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.5px;
            text-align: center;
            line-height: 1.2;
        }

        .orbit-node {
            position: absolute;
            width: 44px;
            height: 44px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            color: var(--accent);
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            animation: orbit-anti-rotate 40s linear infinite; /* keep icons upright */
        }

        /* Position orbit nodes around the circle */
        .node-1 { top: -22px; left: calc(50% - 22px); }
        .node-2 { right: -22px; top: calc(50% - 22px); }
        .node-3 { bottom: -22px; left: calc(50% - 22px); }
        .node-4 { left: -22px; top: calc(50% - 22px); }
        .node-5 { top: 40px; left: 40px; }
        .node-6 { bottom: 40px; right: 40px; }

        @keyframes orbit-rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes orbit-anti-rotate {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }

        /* Support Section (Contact Cards) */
        .support-sec {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
            background: rgba(18,5,5,0.3);
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-header h2 {
            font-family: var(--title-font);
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        .support-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
        }

        .support-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .support-card:hover {
            transform: translateY(-5px);
            border-color: var(--border-hover);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .support-icon-wrap {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(225, 29, 72, 0.05);
            border: 1px solid rgba(225, 29, 72, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 24px;
            font-size: 26px;
            color: var(--primary);
        }

        .support-card.whatsapp .support-icon-wrap {
            color: var(--green-glow);
            background: rgba(16, 185, 129, 0.05);
            border-color: rgba(16, 185, 129, 0.1);
        }

        .support-card h3 {
            font-family: var(--title-font);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .support-card p {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 24px;
            min-height: 48px;
        }

        .support-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .support-card.whatsapp .support-badge {
            background: rgba(16, 185, 129, 0.1);
            color: var(--green-glow);
        }

        .support-card.email .support-badge {
            background: rgba(225, 29, 72, 0.1);
            color: var(--primary);
        }

        .support-card.chat .support-badge {
            background: rgba(225, 29, 72, 0.1);
            color: var(--primary);
        }

        .support-card .btn {
            width: 100%;
        }

        /* Products Showcase Section */
        .showcase {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 360px));
            gap: 30px;
            justify-content: center;
        }

        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-8px);
            border-color: var(--border-hover);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .product-header-banner {
            height: 160px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .product-header-banner i {
            font-size: 56px;
            color: #fff;
            text-shadow: 0 0 20px rgba(255,255,255,0.4);
        }

        .product-price-tag {
            position: absolute;
            bottom: 12px;
            right: 16px;
            padding: 6px 12px;
            background: var(--bg-darker);
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            border: 1px solid var(--border-color);
            color: #fff;
        }

        .product-info-wrap {
            padding: 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-info-wrap h3 {
            font-family: var(--title-font);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .product-info-wrap p {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 20px;
            line-height: 1.6;
            min-height: 58px;
        }

        .product-features-list {
            list-style: none;
            margin-bottom: 24px;
        }

        .product-features-list li {
            font-size: 13px;
            color: var(--text-main);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .product-features-list li i {
            color: var(--primary);
            font-size: 11px;
        }

        .product-card-btns {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 10px;
        }

        /* Reviews / Testimonials Section */
        .reviews-sec {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
            background: rgba(18,5,5,0.2);
            position: relative;
        }

        .testimonials-wrapper {
            display: flex;
            gap: 24px;
            overflow-x: auto;
            padding: 10px 0 30px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) transparent;
        }

        .testimonials-wrapper::-webkit-scrollbar {
            height: 6px;
        }

        .testimonials-wrapper::-webkit-scrollbar-thumb {
            background-color: var(--primary);
            border-radius: 3px;
        }

        .review-card {
            min-width: 320px;
            max-width: 360px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .review-card:hover {
            border-color: var(--primary);
        }

        .stars-row {
            color: #fbbf24;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            gap: 2px;
        }

        .review-text {
            font-size: 14px;
            color: var(--text-main);
            margin-bottom: 24px;
            font-style: italic;
            line-height: 1.6;
        }

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .reviewer-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
        }

        .reviewer-meta h4 {
            font-size: 14px;
            font-weight: 700;
        }

        .reviewer-meta p {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .reviewer-meta p img {
            width: 16px;
            height: 11px;
            object-fit: cover;
        }

        /* Membership pricing plans */
        .membership-sec {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
        }

        .membership-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 340px));
            gap: 30px;
            justify-content: center;
        }

        .membership-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px 30px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: all 0.3s ease;
        }

        .membership-card.premium {
            border-color: var(--primary);
            box-shadow: 0 0 20px var(--primary-glow);
        }

        .membership-card.premium::before {
            content: 'MOST POPULAR';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }

        .membership-card h3 {
            font-family: var(--title-font);
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .membership-price {
            font-size: 38px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 24px;
        }

        .membership-price span {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-muted);
        }

        .membership-features {
            list-style: none;
            text-align: left;
            margin-bottom: 30px;
        }

        .membership-features li {
            font-size: 13px;
            color: var(--text-main);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .membership-features li i {
            color: var(--green-glow);
        }

        /* Office Footer Locations Section */
        .footer-presence {
            padding: 60px 0;
            border-top: 1px solid var(--border-color);
            background: var(--bg-darker);
        }

        .presence-title {
            text-align: center;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 30px;
        }

        .presence-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .presence-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .presence-flag {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .presence-flag img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .presence-meta h4 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .presence-meta p {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .presence-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(225, 29, 72, 0.08);
            border: 1px solid rgba(225, 29, 72, 0.15);
            color: var(--primary);
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
        }

        /* General Footer */
        footer {
            border-top: 1px solid var(--border-color);
            padding: 40px 0;
            background: #060101;
            text-align: center;
        }

        footer p {
            color: var(--text-muted);
            font-size: 13px;
        }

        /* Floating support badge */
        .floating-support-badge {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999;
        }

        /* Responsive design */
        @media (max-width: 992px) {
            .navbar {
                height: 70px;
            }
            .nav-links {
                display: none;
            }
            .hero {
                padding: 130px 0 60px;
            }
            .hero-grid {
                grid-template-columns: 1fr;
                gap: 50px;
                text-align: center;
            }
            .hero-content {
                text-align: center;
            }
            .hero h1 {
                font-size: 42px;
            }
            .hero p {
                margin: 0 auto 30px;
            }
            .hero-btn-group {
                justify-content: center;
            }
            .hero-stats-row {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <div class="container navbar">
            <a href="index.php" class="logo-section">
                <img src="<?php echo htmlspecialchars($branding['site_logo']); ?>" alt="Logo" class="logo-img">
                <span class="logo-text"><?php echo htmlspecialchars($branding['site_name']); ?></span>
            </a>
            
            <ul class="nav-links">
                <li><a href="#support">Support</a></li>
                <li><a href="#products">Products</a></li>
                <li><a href="#reviews">Testimonials</a></li>
                <li><a href="#membership">Membership</a></li>
            </ul>

            <div class="nav-btns">
                <a href="login.php" class="btn btn-outline"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="signup.php" class="btn btn-primary">Sign Up</a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-content">
                <span class="hero-badge"><i class="fas fa-circle"></i> Direct Developer Support</span>
                <h1>Professional Custom Tools &amp; Scripts</h1>
                <p>If your team is wasting hours on manual work or disconnected systems, we build custom solutions. Get instant access to premium browser extensions, Google Sheets automations, and custom PHP web portals, all secured with dynamic license activations.</p>
                
                <div class="hero-btn-group">
                    <a href="https://wa.me/923224083545" class="btn btn-success"><i class="fab fa-whatsapp"></i> Get Quote on WhatsApp</a>
                    <a href="#products" class="btn btn-outline">Browse Products</a>
                </div>

                <div class="hero-stats-row">
                    <div class="hero-stat-item"><i class="fas fa-users"></i> 10,000+ Downloads</div>
                    <div class="hero-stat-item"><i class="fas fa-star"></i> 4.9/5 Rating</div>
                    <div class="hero-stat-item"><i class="fas fa-headset"></i> 6 Months Support</div>
                </div>
            </div>

            <!-- Dynamic Rotating CSS Orbit Graphic -->
            <div class="hero-graphic-wrap">
                <div class="orbit-container">
                    <div class="center-logo-hub">
                        <img src="<?php echo htmlspecialchars($branding['site_logo']); ?>" alt="Logo">
                        <span><?php echo htmlspecialchars($branding['site_name']); ?></span>
                    </div>

                    <!-- Surrounding orbiting tech nodes -->
                    <div class="orbit-node node-1" title="Python"><i class="fab fa-python" style="color:#3776AB;"></i></div>
                    <div class="orbit-node node-2" title="JavaScript"><i class="fab fa-js" style="color:#F7DF1E;"></i></div>
                    <div class="orbit-node node-3" title="Google Sheets"><i class="fas fa-table" style="color:#0F9D58;"></i></div>
                    <div class="orbit-node node-4" title="HTML5"><i class="fab fa-html5" style="color:#E34F26;"></i></div>
                    <div class="orbit-node node-5" title="React"><i class="fab fa-react" style="color:#61DAFB;"></i></div>
                    <div class="orbit-node node-6" title="PHP"><i class="fab fa-php" style="color:#777BB4;"></i></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Support Contacts section -->
    <section class="support-sec" id="support">
        <div class="container">
            <div class="section-header">
                <h2>Direct Channels</h2>
                <p>Reach out to the developers directly. No bots, real support 7 days a week.</p>
            </div>

            <div class="support-grid">
                <!-- WhatsApp Card -->
                <div class="support-card whatsapp">
                    <div class="support-icon-wrap"><i class="fab fa-whatsapp"></i></div>
                    <h3>WhatsApp Support</h3>
                    <p>The fastest way to reach us. Perfect for project questions, custom templates, or license activation help.</p>
                    <span class="support-badge">Avg reply under 2 hours</span>
                    <a href="https://wa.me/923224083545" class="btn btn-success"><i class="fab fa-whatsapp"></i> Chat on WhatsApp</a>
                </div>

                <!-- Email Card -->
                <div class="support-card email">
                    <div class="support-icon-wrap"><i class="fas fa-envelope"></i></div>
                    <h3>Email Support</h3>
                    <p>Prefer writing it all down? Send us your requirements, screenshots, or questions directly to our inbox.</p>
                    <span class="support-badge">Replies within a day</span>
                    <a href="mailto:contact@rameezscripts.com" class="btn btn-primary" style="background:#dc3545; box-shadow:none;"><i class="fas fa-paper-plane"></i> contact@rameezscripts.com</a>
                </div>

                <!-- Help Desk Card -->
                <div class="support-card chat">
                    <div class="support-icon-wrap"><i class="fas fa-headset"></i></div>
                    <h3>Help Desk portal</h3>
                    <p>Register an account to open official tickets. Track bug reports, get assistance, and check project milestones.</p>
                    <span class="support-badge">Tracked until resolved</span>
                    <a href="login.php" class="btn btn-outline"><i class="fas fa-external-link-alt"></i> Access Portal Logins</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Dynamic Products Catalog Section -->
    <section class="showcase" id="products">
        <div class="container">
            <div class="section-header">
                <h2>Product Showcase &amp; Extensions</h2>
                <p>Select any extension or template to get instant billing, auto-license generation, and dynamic downloads.</p>
            </div>

            <div class="product-grid">
                <?php if (empty($products)): ?>
                    <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 50px 0;">
                        <i class="fas fa-box-open" style="font-size:48px; margin-bottom:15px; display:block;"></i>
                        <p>No active products catalog listed. Login to Admin Panel to register products!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $prod): 
                        $color = $prod['color_code'] ?? '#e11d48';
                    ?>
                        <div class="product-card">
                            <div class="product-header-banner" style="background: radial-gradient(circle, <?php echo $color; ?>dd 0%, var(--bg-card) 100%);">
                                <i class="fas fa-laptop-code"></i>
                                <span class="product-price-tag"><?php echo htmlspecialchars($currency) . ' ' . number_format((float)$prod['selling_price'], 2); ?></span>
                            </div>
                            <div class="product-info-wrap">
                                <div>
                                    <h3><?php echo htmlspecialchars($prod['product_name']); ?></h3>
                                    <p><?php echo htmlspecialchars($prod['description'] ?? 'Premium browser automation utility script configured with secure licensing.'); ?></p>
                                    
                                    <ul class="product-features-list">
                                        <li><i class="fas fa-check"></i> Instant activation key delivery</li>
                                        <li><i class="fas fa-check"></i> Direct secure ZIP package download</li>
                                        <li><i class="fas fa-check"></i> Configured with: <code><?php echo htmlspecialchars($prod['product_code']); ?></code></li>
                                    </ul>
                                </div>
                                <div class="product-card-btns">
                                    <a href="login.php" class="btn btn-outline">Preview</a>
                                    <a href="signup.php" class="btn btn-primary" style="background:<?php echo $color; ?>; box-shadow:none;">Buy Now</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Slider Section -->
    <section class="reviews-sec" id="reviews">
        <div class="container">
            <div class="section-header">
                <h2>What Clients Say</h2>
                <p>Feedback directly from our international buyers and project clients.</p>
            </div>

            <div class="testimonials-wrapper">
                <!-- Review 1 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="review-text">"Automated our entire billing process using Google Sheets and Apps Script. It saves us 20+ hours every week. The support response time is fast and the code quality is extremely solid."</p>
                    </div>
                    <div class="reviewer-info">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&h=100&q=80" alt="Avatar" class="reviewer-avatar">
                        <div class="reviewer-meta">
                            <h4>Rajesh Sharma</h4>
                            <p>School Director, Mumbai</p>
                        </div>
                    </div>
                </div>

                <!-- Review 2 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="review-text">"Outstanding Chrome extension utility! Signature verification worked smoothly, and our users could activate keys without issues. Clean interfaces and reliable delivery."</p>
                    </div>
                    <div class="reviewer-info">
                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80" alt="Avatar" class="reviewer-avatar">
                        <div class="reviewer-meta">
                            <h4>Fatima K.</h4>
                            <p>Operations Head, Lagos</p>
                        </div>
                    </div>
                </div>

                <!-- Review 3 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="review-text">"Hired him to build a custom PHP/MySQL inventory dashboard. Delivered on time, responsive support, clean logic, and secure. Highly recommended for custom developments!"</p>
                    </div>
                    <div class="reviewer-info">
                        <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&h=100&q=80" alt="Avatar" class="reviewer-avatar">
                        <div class="reviewer-meta">
                            <h4>Michael Johnson</h4>
                            <p>Warehouse Owner, Toronto</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Membership section -->
    <section class="membership-sec" id="membership">
        <div class="container">
            <div class="section-header">
                <h2>Membership Plans</h2>
                <p>One plan. Every script. Full access to source code library.</p>
            </div>

            <div class="membership-grid">
                <!-- Plan 1 -->
                <div class="membership-card">
                    <div>
                        <h3>Weekly Plan</h3>
                        <div class="membership-price">$15 <span>/ 7 days</span></div>
                        <ul class="membership-features">
                            <li><i class="fas fa-check-circle"></i> Full Source Access</li>
                            <li><i class="fas fa-check-circle"></i> Dynamic ZIP downloads</li>
                            <li><i class="fas fa-check-circle"></i> 1-device active license</li>
                            <li><i class="fas fa-check-circle"></i> Standard WhatsApp support</li>
                        </ul>
                    </div>
                    <a href="signup.php" class="btn btn-outline">Join Plan</a>
                </div>

                <!-- Plan 2 -->
                <div class="membership-card premium">
                    <div>
                        <h3>Premium Plan</h3>
                        <div class="membership-price">$80 <span>/ 180 days</span></div>
                        <ul class="membership-features">
                            <li><i class="fas fa-check-circle"></i> Full Source Access</li>
                            <li><i class="fas fa-check-circle"></i> Dynamic ZIP downloads</li>
                            <li><i class="fas fa-check-circle"></i> 3-device active license</li>
                            <li><i class="fas fa-check-circle"></i> Priority developer support</li>
                            <li><i class="fas fa-check-circle"></i> 6 Months free updates</li>
                        </ul>
                    </div>
                    <a href="signup.php" class="btn btn-primary">Join Plan</a>
                </div>

                <!-- Plan 3 -->
                <div class="membership-card">
                    <div>
                        <h3>VIP Premium Plan</h3>
                        <div class="membership-price">$100 <span>/ 365 days</span></div>
                        <ul class="membership-features">
                            <li><i class="fas fa-check-circle"></i> Full Source Access</li>
                            <li><i class="fas fa-check-circle"></i> Dynamic ZIP downloads</li>
                            <li><i class="fas fa-check-circle"></i> Unlimited device keys</li>
                            <li><i class="fas fa-check-circle"></i> 1-on-1 Zoom support call</li>
                            <li><i class="fas fa-check-circle"></i> Lifetime free updates</li>
                        </ul>
                    </div>
                    <a href="signup.php" class="btn btn-outline">Join Plan</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Global office locations footer section -->
    <section class="footer-presence">
        <div class="container">
            <h3 class="presence-title">Global Presence &amp; Registry</h3>
            <div class="presence-grid">
                <!-- India Card -->
                <div class="presence-card">
                    <div class="presence-flag">
                        <img src="https://flagcdn.com/w40/in.png" alt="India">
                    </div>
                    <div class="presence-meta">
                        <h4>India Hub</h4>
                        <p>MSME Registered Software Entity</p>
                        <span class="presence-badge">Operating Node</span>
                    </div>
                </div>

                <!-- Pakistan Card -->
                <div class="presence-card">
                    <div class="presence-flag">
                        <img src="https://flagcdn.com/w40/pk.png" alt="Pakistan">
                    </div>
                    <div class="presence-meta">
                        <h4>Pakistan Office</h4>
                        <p>PSEB Registered Export Unit</p>
                        <span class="presence-badge">Operations Hub</span>
                    </div>
                </div>

                <!-- Canada Card -->
                <div class="presence-card">
                    <div class="presence-flag">
                        <img src="https://flagcdn.com/w40/ca.png" alt="Canada">
                    </div>
                    <div class="presence-meta">
                        <h4>Canada Node</h4>
                        <p>Import &amp; Global Compliance</p>
                        <span class="presence-badge">Worldwide Delivery</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- General Footer -->
    <footer>
        <div class="container">
            <p><?php echo $branding['copyright_text']; ?></p>
        </div>
    </footer>

    <!-- Floating support badge -->
    <div class="floating-support-badge">
        <a href="https://wa.me/923224083545" target="_blank" class="btn btn-success" style="padding: 12px 20px; border-radius: 50px; font-size:13px; box-shadow: 0 4px 15px rgba(16,185,129,0.4);"><i class="fab fa-whatsapp" style="font-size:16px;"></i> WhatsApp Support</a>
    </div>

</body>
</html>
