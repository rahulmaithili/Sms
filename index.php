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

        .hero h1 {
            font-family: var(--title-font);
            font-size: 54px;
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
            animation: orbit-anti-rotate 40s linear infinite;
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
            animation: orbit-anti-rotate 40s linear infinite;
        }

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

        /* Portfolio Section */
        .portfolio-sec {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
        }

        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .portfolio-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .portfolio-card:hover {
            transform: translateY(-5px);
            border-color: var(--border-hover);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .portfolio-img-wrap {
            height: 180px;
            background: radial-gradient(circle, var(--primary-glow) 0%, var(--bg-darker) 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            border-bottom: 1px solid var(--border-color);
        }

        .portfolio-img-wrap i {
            font-size: 64px;
            color: var(--accent);
        }

        .portfolio-episode-tag {
            position: absolute;
            top: 16px;
            right: 16px;
            background: var(--primary);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 4px;
        }

        .portfolio-info {
            padding: 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .portfolio-info h3 {
            font-family: var(--title-font);
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 12px;
            line-height: 1.4;
            min-height: 48px;
        }

        .portfolio-plans {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
        }

        .portfolio-plans span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .portfolio-plans span i {
            color: var(--green-glow);
        }

        .portfolio-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 24px;
        }

        .portfolio-tag {
            font-size: 10px;
            font-weight: 700;
            background: rgba(255,255,255,0.05);
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            color: var(--accent);
        }

        .portfolio-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        /* Testimonials Section */
        .reviews-sec {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
            background: rgba(18,5,5,0.2);
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
            min-width: 340px;
            max-width: 380px;
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
            margin-bottom: 12px;
            display: flex;
            gap: 2px;
        }

        .review-card h4.country-tag {
            font-size: 11px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .review-text {
            font-size: 14px;
            color: var(--text-main);
            margin-bottom: 24px;
            line-height: 1.6;
            min-height: 120px;
        }

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 12px;
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
        }

        .reviewer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            background: var(--bg-darker);
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            color: var(--accent);
        }

        .reviewer-meta h4 {
            font-size: 13px;
            font-weight: 700;
        }

        .reviewer-meta p {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .reviewer-meta p i {
            color: var(--green-glow);
            font-size: 10px;
        }

        /* Payments section */
        .payments-sec {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
        }

        .payments-trust-row {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }

        .trust-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            background: rgba(225,29,72,0.05);
            border: 1px solid var(--border-color);
            padding: 8px 18px;
            border-radius: 30px;
        }

        .trust-badge i {
            color: var(--primary);
        }

        .payments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            justify-content: center;
        }

        .payment-method-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }

        .payment-method-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }

        .payment-method-card i {
            font-size: 28px;
            color: var(--accent);
        }

        .payment-method-card span {
            font-size: 13px;
            font-weight: 700;
        }

        /* Support Section */
        .support-sec {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
            background: rgba(18,5,5,0.3);
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

        /* FAQ Section */
        .faq-sec {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
        }

        .faq-list {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 16px;
        }

        .faq-item h3 {
            font-family: var(--title-font);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .faq-item h3 i {
            color: var(--primary);
        }

        .faq-item p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
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

        /* Footer Locations Section */
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

        /* Detailed Footer */
        footer.site-footer {
            border-top: 1px solid var(--border-color);
            padding: 80px 0 40px;
            background: #060101;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr repeat(4, 1fr);
            gap: 40px;
            margin-bottom: 60px;
        }

        .footer-brand h3 {
            font-family: var(--title-font);
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 16px;
        }

        .footer-brand p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .footer-col h4 {
            font-family: var(--title-font);
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 12px;
        }

        .footer-col ul li a {
            font-size: 13px;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-col ul li a:hover {
            color: var(--primary);
            padding-left: 4px;
        }

        .footer-contact-info li {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-contact-info li i {
            color: var(--primary);
        }

        .footer-bottom {
            border-top: 1px solid var(--border-color);
            padding-top: 40px;
            text-align: center;
        }

        .footer-bottom p.copyright {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 15px;
        }

        .footer-bottom p.legal-note {
            font-size: 11px;
            color: var(--text-muted);
            opacity: 0.6;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
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
                font-size: 40px;
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
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 30px;
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
                <li><a href="#portfolio">Portfolio</a></li>
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
                <span class="hero-badge"><i class="fas fa-circle"></i> Building Since 2022 · 400+ Projects Shipped to 50+ Countries</span>
                <h1>Hire a Google Apps Script Developer</h1>
                <p>If your team is wasting hours on manual spreadsheet work or disconnected systems — we can fix that. We build Google Sheets automations, Apps Script add-ons, and custom PHP web applications. 400+ projects shipped across 50+ countries, with 6 months of post-delivery support included.</p>
                
                <div class="hero-btn-group">
                    <a href="https://wa.me/923394100600" class="btn btn-success"><i class="fab fa-whatsapp"></i> Get a Free Quote on WhatsApp</a>
                    <a href="#portfolio" class="btn btn-outline">Apps Script Projects</a>
                    <a href="#products" class="btn btn-outline">PHP MySQL Projects</a>
                </div>

                <div class="hero-stats-row">
                    <div class="hero-stat-item"><i class="fab fa-youtube"></i> 27,400+ YouTube Subscribers</div>
                    <div class="hero-stat-item"><i class="fas fa-star"></i> 4.9/5 Client Rating</div>
                    <div class="hero-stat-item"><i class="fas fa-headset"></i> 6 Months Free Support</div>
                    <div class="hero-stat-item"><i class="fas fa-bolt"></i> 24h Free Quote</div>
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

    <!-- Portfolio / Popular Apps Script Templates Section -->
    <section class="portfolio-sec" id="portfolio">
        <div class="container">
            <div class="section-header">
                <h2>Popular Apps Script Templates &amp; Source Code</h2>
                <p>These are our most downloaded scripts — each one has a full video walkthrough on YouTube.</p>
            </div>

            <div class="portfolio-grid">
                <!-- Project 1 -->
                <div class="portfolio-card">
                    <div class="portfolio-img-wrap">
                        <i class="fas fa-columns"></i>
                        <span class="portfolio-episode-tag">E14</span>
                    </div>
                    <div class="portfolio-info">
                        <div>
                            <h3>Build a Complete Dynamic CRUD Web Dashboard using Google Sheets + Apps Script + React | E14</h3>
                            <div class="portfolio-plans">
                                <span><i class="fas fa-check"></i> Premium Plan</span>
                                <span><i class="fas fa-check"></i> VIP Premium Plan</span>
                            </div>
                            <div class="portfolio-tags">
                                <span class="portfolio-tag">Google Apps Script</span>
                                <span class="portfolio-tag">Dashboard</span>
                            </div>
                        </div>
                        <div class="portfolio-btns">
                            <a href="login.php" class="btn btn-outline btn-sm">Watch Preview</a>
                            <a href="https://wa.me/923394100600" class="btn btn-success btn-sm"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                        </div>
                    </div>
                </div>

                <!-- Project 2 -->
                <div class="portfolio-card">
                    <div class="portfolio-img-wrap">
                        <i class="fas fa-users-cog"></i>
                        <span class="portfolio-episode-tag">E10</span>
                    </div>
                    <div class="portfolio-info">
                        <div>
                            <h3>How to Build Complete HR System with Google Apps Script - Attendance, Payroll &amp; Dashboard | E10</h3>
                            <div class="portfolio-plans">
                                <span><i class="fas fa-check"></i> Premium Plan</span>
                                <span><i class="fas fa-check"></i> VIP Premium Plan</span>
                            </div>
                            <div class="portfolio-tags">
                                <span class="portfolio-tag">Google Apps Script</span>
                                <span class="portfolio-tag">Attendance</span>
                            </div>
                        </div>
                        <div class="portfolio-btns">
                            <a href="login.php" class="btn btn-outline btn-sm">Watch Preview</a>
                            <a href="https://wa.me/923394100600" class="btn btn-success btn-sm"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                        </div>
                    </div>
                </div>

                <!-- Project 3 -->
                <div class="portfolio-card">
                    <div class="portfolio-img-wrap">
                        <i class="fas fa-brain"></i>
                        <span class="portfolio-episode-tag">F9</span>
                    </div>
                    <div class="portfolio-info">
                        <div>
                            <h3>Build Any App in Seconds Using Gemini API + Google Apps Script (No Code)</h3>
                            <div class="portfolio-plans">
                                <span><i class="fas fa-check"></i> Premium Plan</span>
                                <span><i class="fas fa-check"></i> VIP Premium Plan</span>
                            </div>
                            <div class="portfolio-tags">
                                <span class="portfolio-tag">Dashboard</span>
                                <span class="portfolio-tag">Google Apps Script</span>
                            </div>
                        </div>
                        <div class="portfolio-btns">
                            <a href="login.php" class="btn btn-outline btn-sm">Watch Preview</a>
                            <a href="https://wa.me/923394100600" class="btn btn-success btn-sm"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align:center;">
                <a href="#products" class="btn btn-primary" style="padding:14px 32px;"><i class="fas fa-folder-open"></i> Browse All Apps Script Files</a>
            </div>
        </div>
    </section>

    <!-- Support Contacts section -->
    <section class="support-sec" id="support">
        <div class="container">
            <div class="section-header">
                <h2>Direct Developer Support</h2>
                <p>Real humans, real answers — before you buy, while we build, and long after delivery. Any timezone, any day of the week.</p>
            </div>

            <div class="support-grid">
                <!-- WhatsApp Card -->
                <div class="support-card whatsapp">
                    <div class="support-icon-wrap"><i class="fab fa-whatsapp"></i></div>
                    <h3>WhatsApp</h3>
                    <p>The fastest way to reach us. Quotes, project questions, bug reports, or plan activation — message anytime, from any timezone.</p>
                    <span class="support-badge">Avg reply under 2 hours</span>
                    <a href="https://wa.me/923394100600" class="btn btn-success"><i class="fab fa-whatsapp"></i> Chat on WhatsApp</a>
                </div>

                <!-- Email Card -->
                <div class="support-card email">
                    <div class="support-icon-wrap"><i class="fas fa-envelope"></i></div>
                    <h3>Email</h3>
                    <p>Prefer writing it all down? Send your requirements, screenshots, or files — you get a detailed reply with clear next steps.</p>
                    <span class="support-badge">Replies within a few hours</span>
                    <a href="mailto:contact@rameezscripts.com" class="btn btn-primary" style="background:#dc3545; box-shadow:none;"><i class="fas fa-paper-plane"></i> contact@rameezscripts.com</a>
                </div>

                <!-- Help Desk Card -->
                <div class="support-card chat">
                    <div class="support-icon-wrap"><i class="fas fa-headset"></i></div>
                    <h3>Support Chat &amp; Help Desk</h3>
                    <p>Open a ticket as a guest and chat with us right on the page — you get a private tracking link, plus email and browser alerts on every reply.</p>
                    <span class="support-badge">Tracked until resolved</span>
                    <a href="login.php" class="btn btn-outline"><i class="fas fa-comment-dots"></i> Start Support Chat</a>
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
                <h2>Client Reviews &amp; Testimonials</h2>
                <p>Unedited feedback from actual projects — Google Sheets automations, PHP web apps, and custom builds.</p>
            </div>

            <div class="testimonials-wrapper">
                <!-- Review 1 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <h4 class="country-tag">India</h4>
                        <p class="review-text">"Rameez automated our entire school fee collection using Google Sheets and Apps Script. It saves us 20+ hours every week. The support response time is fast and the code quality is solid. Would hire again."</p>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">RS</div>
                        <div class="reviewer-meta">
                            <h4>Rajesh Sharma</h4>
                            <p><img src="https://flagcdn.com/w20/in.png" alt="India"> School Director, Mumbai, India <i class="fas fa-check-circle" title="Verified Client"></i></p>
                        </div>
                    </div>
                </div>

                <!-- Review 2 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <h4 class="country-tag">USA</h4>
                        <p class="review-text">"I hired Rameez to build a complete PHP &amp; MySQL inventory system with role-based access. Delivered on time, clean code, solid security. Our team across 3 states relies on it daily. Professional work, start to finish."</p>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">MJ</div>
                        <div class="reviewer-meta">
                            <h4>Michael Johnson</h4>
                            <p><img src="https://flagcdn.com/w20/us.png" alt="USA"> Operations Manager, Texas, USA <i class="fas fa-check-circle" title="Verified Client"></i></p>
                        </div>
                    </div>
                </div>

                <!-- Review 3 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <h4 class="country-tag">Canada</h4>
                        <p class="review-text">"We needed a Google Sheets dashboard to track real-time sales across our 5 stores in Canada. Rameez built it with clean charts and automated email reports. Worth every dollar."</p>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">DL</div>
                        <div class="reviewer-meta">
                            <h4>David Laurent</h4>
                            <p><img src="https://flagcdn.com/w20/ca.png" alt="Canada"> Retail Owner, Toronto, Canada <i class="fas fa-check-circle" title="Verified Client"></i></p>
                        </div>
                    </div>
                </div>

                <!-- Review 4 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <h4 class="country-tag">UAE</h4>
                        <p class="review-text">"Our company in Dubai needed a secure PHP admin panel with payment tracking and audit logs. Rameez delivered exactly what we needed — fast, secure, and clean. The support response time is quick — no waiting around."</p>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">AA</div>
                        <div class="reviewer-meta">
                            <h4>Ahmed Al Khouri</h4>
                            <p><img src="https://flagcdn.com/w20/ae.png" alt="UAE"> CEO, Digital Solutions, Dubai, UAE <i class="fas fa-check-circle" title="Verified Client"></i></p>
                        </div>
                    </div>
                </div>

                <!-- Review 5 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <h4 class="country-tag">France</h4>
                        <p class="review-text">"I discovered Rameez Scripts on YouTube and was impressed by the tutorials. Got a custom Apps Script solution for our restaurant chain's booking system. The quality speaks for itself. Merci beaucoup!"</p>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">PD</div>
                        <div class="reviewer-meta">
                            <h4>Pierre Dubois</h4>
                            <p><img src="https://flagcdn.com/w20/fr.png" alt="France"> Restaurant Owner, Paris, France <i class="fas fa-check-circle" title="Verified Client"></i></p>
                        </div>
                    </div>
                </div>

                <!-- Review 6 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <h4 class="country-tag">Pakistan</h4>
                        <p class="review-text">"From a simple Google Sheets template to a full PHP web app with secure login — Rameez handled everything. Clean code, good-looking UI, and solid after-delivery support. I have already referred two friends."</p>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">ZN</div>
                        <div class="reviewer-meta">
                            <h4>Zainab Noor</h4>
                            <p><img src="https://flagcdn.com/w20/pk.png" alt="Pakistan"> Startup Founder, Islamabad, Pakistan <i class="fas fa-check-circle" title="Verified Client"></i></p>
                        </div>
                    </div>
                </div>

                <!-- Review 7 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <h4 class="country-tag">Qatar</h4>
                        <p class="review-text">"I have been following Rameez from Qatar for some time and previously purchased scripts from his platform. After testing him with two custom PHP projects, I can confidently say he exceeded my expectations on both occasions. Not only did he deliver exactly what was requested, but he also added thoughtful enhancements that elevated my initial concept into fully functional, well-structured PHP platforms. His technical expertise, attention to detail, and proactive approach truly stood out. Thank you, Rameez."</p>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">BM</div>
                        <div class="reviewer-meta">
                            <h4>BG MEGA</h4>
                            <p><img src="https://flagcdn.com/w20/qa.png" alt="Qatar"> Client, Qatar <i class="fas fa-check-circle" title="Verified Client"></i></p>
                        </div>
                    </div>
                </div>

                <!-- Review 8 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <h4 class="country-tag">UK</h4>
                        <p class="review-text">"We run a logistics company in London and needed a warehouse management system built on PHP &amp; MySQL. Rameez delivered a fully responsive dashboard with barcode scanning, stock alerts, and role-based access — all within two weeks. Solid work and fair pricing."</p>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">JW</div>
                        <div class="reviewer-meta">
                            <h4>James W.</h4>
                            <p><img src="https://flagcdn.com/w20/gb.png" alt="UK"> Logistics Manager, London, UK <i class="fas fa-check-circle" title="Verified Client"></i></p>
                        </div>
                    </div>
                </div>

                <!-- Review 9 -->
                <div class="review-card">
                    <div>
                        <div class="stars-row">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <h4 class="country-tag">Nigeria</h4>
                        <p class="review-text">"I needed a Google Sheets automation to manage student grades and generate report cards automatically. Rameez built an Apps Script solution with email notifications and PDF exports. It handles 500+ students without slowing down. Completely changed how we handle grading."</p>
                    </div>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">FK</div>
                        <div class="reviewer-meta">
                            <h4>Fatima K.</h4>
                            <p><img src="https://flagcdn.com/w20/ng.png" alt="Nigeria"> School Administrator, Lagos, Nigeria <i class="fas fa-check-circle" title="Verified Client"></i></p>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:center; align-items:center; gap:20px; flex-wrap:wrap; margin-top:20px;">
                <span style="font-size:16px; font-weight:700; color:#fff;"><i class="fas fa-star" style="color:#fbbf24;"></i> 4.9 <span style="font-weight:400; color:var(--text-muted); font-size:14px;">A few of many. See more on <a href="https://youtube.com/@rameezimdad" style="color:var(--primary); text-decoration:none;" target="_blank">YouTube →</a></span></span>
                <a href="login.php" class="btn btn-outline btn-sm"><i class="fas fa-pen-fancy"></i> Write Your Review</a>
            </div>
        </div>
    </section>

    <!-- Payments Section -->
    <section class="payments-sec" id="payments">
        <div class="container">
            <div class="section-header">
                <h2>Pay However Works For You</h2>
                <p>We work with clients globally, so we accept pretty much every payment method. Pick whatever is easiest on your end.</p>
            </div>

            <div class="payments-trust-row">
                <span class="trust-badge"><i class="fas fa-shield-alt"></i> Secure Checkout</span>
                <span class="trust-badge"><i class="fas fa-lock"></i> SSL Encrypted</span>
                <span class="trust-badge"><i class="fas fa-user-shield"></i> Buyer Protected</span>
                <span class="trust-badge"><i class="fas fa-undo-alt"></i> Refund Policy Available</span>
            </div>

            <div class="payments-grid">
                <div class="payment-method-card"><i class="fab fa-paypal" style="color:#003087;"></i><span>PayPal</span></div>
                <div class="payment-method-card"><i class="fab fa-cc-visa" style="color:#1A1F71;"></i><span>Visa Card</span></div>
                <div class="payment-method-card"><i class="fab fa-cc-mastercard" style="color:#EB001B;"></i><span>Mastercard</span></div>
                <div class="payment-method-card"><i class="fab fa-cc-amex" style="color:#007CC3;"></i><span>Amex Card</span></div>
                <div class="payment-method-card"><i class="fab fa-google-pay" style="color:#fff;"></i><span>Google Pay</span></div>
                <div class="payment-method-card"><i class="fas fa-wallet" style="color:#002E6E;"></i><span>Paytm</span></div>
                <div class="payment-method-card"><i class="fas fa-university" style="color:#f39c12;"></i><span>UPI</span></div>
                <div class="payment-method-card"><i class="fas fa-money-bill-wave" style="color:#27ae60;"></i><span>Western Union</span></div>
                <div class="payment-method-card"><i class="fab fa-bitcoin" style="color:#f2a900;"></i><span>Crypto Payment</span></div>
                <div class="payment-method-card"><i class="fas fa-coins" style="color:#f3ba2f;"></i><span>Binance</span></div>
                <div class="payment-method-card"><i class="fas fa-exchange-alt" style="color:#00b9ff;"></i><span>Wise</span></div>
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

    <!-- FAQ Section -->
    <section class="faq-sec" id="faq">
        <div class="container">
            <div class="section-header">
                <h2>Help Center &amp; FAQ</h2>
                <p>Before you message us — here are the questions most clients ask first.</p>
            </div>

            <div class="faq-list">
                <div class="faq-item">
                    <h3><i class="fas fa-question-circle"></i> What Google Sheets automation services do you offer?</h3>
                    <p>We build customized Google Apps Script utilities, automatic report generators, data sync tunnels connecting other platforms, dynamic dashboard cards, and workflow trackers.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question-circle"></i> How much does a custom Google Sheets or Apps Script project cost?</h3>
                    <p>Pricing depends on structure, size, and complexity. Standard automation tasks start at low rates, while complex systems with integrations are quoted on custom scale.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question-circle"></i> Do you build PHP web applications as well?</h3>
                    <p>Yes, we build complete custom responsive PHP/MySQL dashboards, secure portals, payment gateway verifiers, API servers, and billing databases.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question-circle"></i> What is your typical project delivery timeline?</h3>
                    <p>Simple tools are delivered in 2-5 days. Complex projects or full portals take 1-3 weeks. We align timelines during the initial quote phase.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question-circle"></i> Do you offer support after project delivery?</h3>
                    <p>Every custom script/app comes with 6 months of free post-delivery support covering bug fixes, patches, and deployment assistance.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question-circle"></i> Can I see examples of your past work?</h3>
                    <p>Yes! Check our YouTube channel "Rameez Scripts" where we show full video walkthroughs and live demos of major source code templates.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Global office locations Section -->
    <section class="footer-presence">
        <div class="container">
            <h3 class="presence-title">Global Presence &amp; Registry</h3>
            <div class="presence-grid">
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

    <!-- Detailed Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <h3><?php echo htmlspecialchars($branding['site_name']); ?></h3>
                    <p>We build Google Sheets automations, Apps Script tools, and PHP web apps. 400+ projects shipped to clients in 50+ countries since 2022.</p>
                </div>
                
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#portfolio">Apps Script Files</a></li>
                        <li><a href="#products">Free Files</a></li>
                        <li><a href="login.php">PHP &amp; MySQL</a></li>
                        <li><a href="#products">Products</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Services</h4>
                    <ul>
                        <li><a href="https://wa.me/923394100600">Google Sheets &amp; Apps Script</a></li>
                        <li><a href="https://wa.me/923394100600">PHP &amp; MySQL Development</a></li>
                        <li><a href="login.php">Security &amp; Payments</a></li>
                        <li><a href="login.php">Custom Web Applications</a></li>
                        <li><a href="https://wa.me/923394100600">WhatsApp Automation</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Contact Details</h4>
                    <ul class="footer-contact-info">
                        <li><i class="fas fa-map-marker-alt"></i> Pakistan · Canada · India</li>
                        <li><i class="fab fa-whatsapp"></i> +923394100600</li>
                        <li><i class="fas fa-envelope"></i> contact@rameezscripts.com</li>
                        <li><i class="fab fa-youtube"></i> <a href="https://youtube.com/@rameezimdad" target="_blank" style="color:var(--text-muted); text-decoration:none;">YouTube Channel</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Legal &amp; Trust</h4>
                    <ul>
                        <li><a href="login.php">Privacy Policy</a></li>
                        <li><a href="login.php">Terms of Service</a></li>
                        <li><a href="login.php">Refund Policy</a></li>
                        <li><a href="login.php">Report an Issue</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p class="copyright">&copy; 2022-<?php echo date('Y'); ?> Rameez Scripts. All Rights Reserved.</p>
                <p class="legal-note">
                    Rameez Scripts is a registered software development business owned by Mohammad Rameez Imdad, operated from Pakistan, Canada and India. All transactions are processed over secure, encrypted connections.
                    <br>
                    <span style="opacity: 0.8; font-weight: bold; display: block; margin-top: 8px;">Page last updated: July 2026</span>
                </p>
            </div>
        </div>
    </footer>

    <!-- Floating support badge -->
    <div class="floating-support-badge">
        <a href="https://wa.me/923394100600" target="_blank" class="btn btn-success" style="padding: 12px 20px; border-radius: 50px; font-size:13px; box-shadow: 0 4px 15px rgba(16,185,129,0.4);"><i class="fab fa-whatsapp" style="font-size:16px;"></i> WhatsApp Support</a>
    </div>

</body>
</html>
