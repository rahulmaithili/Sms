<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 */

require_once 'config.php';

// Check maintenance mode - block signup
if (isMaintenanceMode()) {
    header("Location: maintenance.php");
    exit();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$csrf_token = generateCSRFToken();

// Check if Google OAuth is enabled
$google_oauth_enabled = false;
$google_login_url = '';
try {
    $oauth_enabled = getSetting('google_oauth_enabled', '0');
    if ($oauth_enabled === '1') {
        $g_client_id = getSetting('google_client_id', '');
        $g_redirect_uri = getSetting('google_redirect_uri', '');
        if (!empty($g_client_id) && !empty($g_redirect_uri)) {
            $google_oauth_enabled = true;
            $google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $g_client_id,
                'redirect_uri' => $g_redirect_uri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'access_type' => 'online',
                'prompt' => 'select_account'
            ]);
        }
    }
} catch (Exception $e) {
    // OAuth not available, silently skip
}

// Get site branding
$branding = getSiteBranding();

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
        $country = isset($_POST['country']) ? trim($_POST['country']) : '';

        // Input validation
        $username = validateUsername($username);
        $password = validatePassword($password);

        if ($username === false) {
            $error = 'Invalid username. Use 3-50 alphanumeric characters or underscores.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (empty($company_name)) {
            $error = 'Full Name / Company Name is required.';
        } elseif (empty($phone)) {
            $error = 'Contact Number is required.';
        } elseif ($password === false) {
            $error = 'Password must be between 6 and 255 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $conn = getDBConnection();

                // Check if username already exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $error = 'Username already taken. Please choose another.';
                    $stmt->close();
                } else {
                    $stmt->close();

                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $error = 'Email already registered. Please use another.';
                        $stmt->close();
                    } else {
                        $stmt->close();

                        // 1. Create entry in customers table
                        // We use user_id = 1 (System Admin) as the creator for auto-signed up customers
                        $added_by = 1; 
                        $cust_stmt = $conn->prepare("INSERT INTO customers (company_name, contact_person, email, phone, city, country, added_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $cust_stmt->bind_param("ssssssi", $company_name, $company_name, $email, $phone, $city, $country, $added_by);
                        
                        if ($cust_stmt->execute()) {
                            $new_customer_id = $cust_stmt->insert_id;
                            $cust_stmt->close();

                            // 2. Hash password and create user linked to this customer
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $role = 'customer'; // Default role is customer

                            $stmt = $conn->prepare("INSERT INTO users (username, full_name, password, email, role, customer_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                            $stmt->bind_param("sssssi", $username, $company_name, $hashed_password, $email, $role, $new_customer_id);

                            if ($stmt->execute()) {
                                $new_user_id = $stmt->insert_id;
                                $stmt->close();

                                // Log the signup
                                logActivity($new_user_id, $username, 'Signup', 'New customer registered and profile linked');

                                // Notify admins about new registration
                                try { createNotificationForAdmins('New User Registered', 'Customer "' . htmlspecialchars($company_name) . '" has signed up.', 'info', 'users.php'); } catch (Exception $e) {}

                                // Check if email verification is enabled
                                $verification_enabled = getSetting('email_verification_enabled', '0');
                                $smtp_enabled = getSetting('smtp_enabled', '0');

                                if ($verification_enabled === '1' && $smtp_enabled === '1') {
                                    // Send OTP email for verification
                                    $otp = createEmailVerification($new_user_id, $email);
                                    $emailBody = getOTPEmailTemplate($otp, 'verify');
                                    sendEmail($email, 'Verify Your Email - ' . $branding['site_name'], $emailBody);

                                    header("Location: verify_otp.php?email=" . urlencode($email));
                                    exit();
                                } else {
                                    // Auto-verify if email verification is disabled
                                    $verify_stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE user_id = ?");
                                    $verify_stmt->bind_param("i", $new_user_id);
                                    $verify_stmt->execute();
                                    $verify_stmt->close();

                                    // AUTO-LOGIN customer immediately
                                    session_regenerate_id(true);
                                    $_SESSION['user_id'] = $new_user_id;
                                    $_SESSION['username'] = $username;
                                    $_SESSION['full_name'] = $company_name;
                                    $_SESSION['role'] = 'customer';
                                    $_SESSION['customer_id'] = $new_customer_id;
                                    $_SESSION['LAST_ACTIVITY'] = time();

                                    // Update last_login
                                    $login_update = $conn->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE user_id = ?");
                                    $login_update->bind_param("i", $new_user_id);
                                    $login_update->execute();
                                    $login_update->close();

                                    // Redirect straight to Customer Portal
                                    header("Location: customer_portal.php");
                                    exit();
                                }
                            } else {
                                $error = 'Registration failed. Please try again.';
                                $stmt->close();
                            }
                        } else {
                            $error = 'Failed to create customer profile. Please try again.';
                            $cust_stmt->close();
                        }
                    }
                }
            } catch (Exception $e) {
                if (strpos($e->getMessage(), "doesn't exist") !== false) {
                    $error = 'Database not set up. Please run <a href="setup.php" class="login-link">setup.php</a> first.';
                } else {
                    $error = 'An error occurred. Please contact administrator.';
                    error_log("Signup error: " . $e->getMessage());
                }
            }
        }
    }
}
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
    <title>Sign Up - <?php echo htmlspecialchars($branding['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <img src="<?php echo htmlspecialchars($branding['site_logo']); ?>" alt="Logo" class="login-logo">
            <h2>Create Account</h2>

            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username *</label>
                    <input type="text" name="username" required autofocus autocomplete="username" minlength="3" maxlength="50" placeholder="Choose a username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Full Name / Company Name *</label>
                    <input type="text" name="company_name" required placeholder="Your full name or company name" value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address *</label>
                    <input type="email" name="email" required autocomplete="email" maxlength="100" placeholder="Enter your email address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Contact Number *</label>
                    <input type="text" name="phone" required placeholder="Enter your phone or mobile number" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>

                <div style="display:flex; gap:12px;">
                    <div class="form-group" style="flex:1;">
                        <label><i class="fas fa-city"></i> City</label>
                        <input type="text" name="city" placeholder="e.g. Delhi" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label><i class="fas fa-globe"></i> Country</label>
                        <input type="text" name="country" placeholder="e.g. India" value="<?php echo isset($_POST['country']) ? htmlspecialchars($_POST['country']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password *</label>
                    <input type="password" name="password" required autocomplete="new-password" minlength="6" placeholder="Minimum 6 characters">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password *</label>
                    <input type="password" name="confirm_password" required autocomplete="new-password" minlength="6" placeholder="Confirm your password">
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Sign Up
                </button>
            </form>

            <?php if ($google_oauth_enabled): ?>
            <div class="oauth-divider">
                <hr>
                <span>or continue with</span>
                <hr>
            </div>
            <a href="<?php echo htmlspecialchars($google_login_url); ?>" class="google-oauth-btn">
                <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                Sign up with Google
            </a>
            <?php endif; ?>

            <div class="login-footer">
                <p class="login-footer-link">Already have an account? <a href="login.php" class="login-link">Login here</a></p>
                <p><?php echo $branding['copyright_text']; ?></p>
            </div>
        </div>

        <!-- Theme Toggle Button -->
        <button class="login-theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
    </div>

    <script>
    // Theme Toggle for Signup Page
    function initTheme() {
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.body.classList.add('dark-mode');
            updateThemeIcon(true);
        }
    }

    function toggleTheme() {
        const isDark = document.body.classList.toggle('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateThemeIcon(isDark);
    }

    function updateThemeIcon(isDark) {
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    initTheme();
    </script>
</body>
</html>
