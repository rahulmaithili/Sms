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
    header("Location: dashboard.php");
    exit();
}

// Fetch active products to display in the pricing section
$products = [];
try {
    $conn = getDBConnection();
    $result = $conn->query("SELECT product_id, product_name, description, color_code, selling_price FROM products WHERE is_active = 1 ORDER BY display_order ASC, product_name ASC");
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
    <title><?php echo htmlspecialchars($branding['site_name']); ?> - Premium Extensions & Tools</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0074D9;
            --primary-dark: #005bb5;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --font-family: 'Outfit', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-dark);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Container */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Header / Navbar */
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s;
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
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
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #fff;
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
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-main);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: #fff;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 14px rgba(0, 116, 217, 0.4);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            padding: 180px 0 100px;
            position: relative;
            text-align: center;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(0, 116, 217, 0.15) 0%, transparent 70%);
            z-index: -1;
            filter: blur(40px);
        }

        .hero h1 {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -1px;
            max-width: 800px;
            margin: 0 auto 20px;
            background: linear-gradient(180deg, #fff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 18px;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto 40px;
        }

        /* Features Section */
        .features {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-header h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 16px;
            max-width: 500px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }

        .feature-icon {
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Products & Pricing Section */
        .pricing {
            padding: 100px 0;
            border-top: 1px solid var(--border-color);
            background: rgba(30, 41, 59, 0.2);
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 360px));
            justify-content: center;
            gap: 40px;
        }

        .pricing-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
            position: relative;
        }

        .pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .pricing-header {
            padding: 32px 30px 24px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .pricing-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 15px;
        }

        .pricing-card h3 {
            font-size: 22px;
            font-weight: 700;
        }

        .pricing-price {
            padding: 30px;
            text-align: center;
            background: rgba(15, 23, 42, 0.2);
        }

        .pricing-price .amount {
            font-size: 48px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -1px;
        }

        .pricing-price .curr {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-muted);
            vertical-align: super;
            margin-right: 4px;
        }

        .pricing-body {
            padding: 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .pricing-features {
            list-style: none;
            margin-bottom: 30px;
        }

        .pricing-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .pricing-features li i {
            color: #10b981;
        }

        .btn-pricing {
            width: 100%;
            padding: 14px;
            font-size: 15px;
        }

        /* FAQ Section */
        .faq {
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
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .faq-item h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .faq-item h3 i {
            color: var(--primary);
        }

        .faq-item p {
            color: var(--text-muted);
            font-size: 14px;
            padding-left: 26px;
        }

        /* Footer */
        footer {
            border-top: 1px solid var(--border-color);
            padding: 60px 0 40px;
            background: #090d16;
            text-align: center;
        }

        footer p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .footer-logo {
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                height: 70px;
            }
            .nav-links {
                display: none; /* simple hidden on mobile, fallback menu */
            }
            .hero {
                padding: 140px 0 70px;
            }
            .hero h1 {
                font-size: 38px;
            }
            .hero p {
                font-size: 15px;
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
                <li><a href="#features">Features</a></li>
                <li><a href="#products">Products</a></li>
                <li><a href="#faq">FAQ</a></li>
            </ul>

            <div class="nav-btns">
                <a href="login.php" class="btn btn-outline">Log In</a>
                <a href="signup.php" class="btn btn-primary">Sign Up</a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Supercharge Your Digital Workflow</h1>
            <p>Get instant access to premium browser extensions, automation scripts, and developer tools. Buy, download, and activate license keys automatically in seconds.</p>
            <div style="display:flex; justify-content:center; gap:16px;">
                <a href="#products" class="btn btn-primary" style="padding:14px 32px; font-size:15px;">Browse Products</a>
                <a href="signup.php" class="btn btn-outline" style="padding:14px 32px; font-size:15px;">Create Free Account</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Why Choose Our Tools?</h2>
                <p>Fully automated licensing system built for high-performance extensions.</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                    <h3>Instant Delivery</h3>
                    <p>No waiting. Once your payment via Razorpay succeeds, your license key and download file are delivered instantly.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Secure Checkouts</h3>
                    <p>Protected by Razorpay signature verification. Pay securely using UPI, Credit Cards, NetBanking, or wallets.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-sync-alt"></i></div>
                    <h3>Automatic Updates</h3>
                    <p>Enjoy lifetime automatic updates on all extensions. We constantly patch bugs and release new features.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Products & Pricing Section -->
    <section class="pricing" id="products">
        <div class="container">
            <div class="section-header">
                <h2>Our Premium Products</h2>
                <p>Choose the product you need and register to get instant activation.</p>
            </div>

            <div class="pricing-grid">
                <?php if (empty($products)): ?>
                    <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-box-open" style="font-size:48px; margin-bottom:15px; display:block;"></i>
                        <p>No active products listed. Check back soon!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $prod): 
                        $bg = $prod['color_code'] ?? '#0078D4';
                        $r = hexdec(substr($bg, 1, 2)); 
                        $g = hexdec(substr($bg, 3, 2)); 
                        $b = hexdec(substr($bg, 5, 2));
                        $tc = ($r*0.299 + $g*0.587 + $b*0.114) > 186 ? '#000' : '#fff';
                    ?>
                        <div class="pricing-card">
                            <div class="pricing-header" style="border-top: 4px solid <?php echo $bg; ?>;">
                                <span class="pricing-badge" style="background: <?php echo $bg; ?>; color: <?php echo $tc; ?>;">
                                    POPULAR
                                </span>
                                <h3><?php echo htmlspecialchars($prod['product_name']); ?></h3>
                            </div>
                            <div class="pricing-price">
                                <span class="curr"><?php echo htmlspecialchars($currency); ?></span>
                                <span class="amount"><?php echo number_format((float)$prod['selling_price'], 2); ?></span>
                            </div>
                            <div class="pricing-body">
                                <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; text-align:center; min-height:40px;">
                                    <?php echo htmlspecialchars($prod['description'] ?? ''); ?>
                                </p>
                                <ul class="pricing-features">
                                    <li><i class="fas fa-check-circle"></i> Instant File Download (.zip)</li>
                                    <li><i class="fas fa-check-circle"></i> Auto-generated License Key</li>
                                    <li><i class="fas fa-check-circle"></i> 1-Device Activation</li>
                                    <li><i class="fas fa-check-circle"></i> Unlimited Lifetime Updates</li>
                                </ul>
                                <a href="signup.php" class="btn btn-primary btn-pricing" style="background: <?php echo $bg; ?>; color: <?php echo $tc; ?>; box-shadow: none;">
                                    Register &amp; Purchase
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq" id="faq">
        <div class="container">
            <div class="section-header">
                <h2>Frequently Asked Questions</h2>
                <p>Have questions? We have answers.</p>
            </div>

            <div class="faq-list">
                <div class="faq-item">
                    <h3><i class="fas fa-question-circle"></i> How will I get my license key?</h3>
                    <p>After a successful payment on the checkout popup, the system redirects you, generates your license key, and displays it in your Customer Portal. We also mail it to your registered email.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question-circle"></i> Can I use the extension on multiple devices?</h3>
                    <p>Most standard products are restricted to 1 active key validation per account. If you need multi-device access, you can purchase additional quantities in your dashboard.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question-circle"></i> Do you support refunds?</h3>
                    <p>Since these are digital files/extensions, refunds are issued strictly according to our terms. Please contact support via portal feedback if you face any configuration issue.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p><?php echo $branding['copyright_text']; ?></p>
        </div>
    </footer>

</body>
</html>
