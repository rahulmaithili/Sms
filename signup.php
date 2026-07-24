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
        $username         = isset($_POST['username']) ? $_POST['username'] : '';
        $email            = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password         = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Basic customer fields
        $company_name     = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
        $contact_person   = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
        $phone            = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $city             = isset($_POST['city']) ? trim($_POST['city']) : '';
        $country          = isset($_POST['country']) ? trim($_POST['country']) : '';
        $address          = isset($_POST['address']) ? trim($_POST['address']) : '';
        $notes            = isset($_POST['notes']) ? trim($_POST['notes']) : '';

        // Custom fields (Customer entity)
        $cf_industry       = isset($_POST['cf_industry']) ? trim($_POST['cf_industry']) : '';
        $cf_company_size   = isset($_POST['cf_company_size']) ? trim($_POST['cf_company_size']) : '';
        $cf_tax_id         = isset($_POST['cf_tax_id']) ? trim($_POST['cf_tax_id']) : '';
        $cf_website        = isset($_POST['cf_website']) ? trim($_POST['cf_website']) : '';
        $cf_notes_internal = isset($_POST['cf_notes_internal']) ? trim($_POST['cf_notes_internal']) : '';

        // Input validation
        $username = validateUsername($username);
        $password = validatePassword($password);

        if ($username === false) {
            $error = 'Invalid username. Use 3-50 alphanumeric characters or underscores.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (empty($company_name)) {
            $error = 'Customer / Company Name is required.';
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

                        // Start Transaction to guarantee both tables insert successfully
                        $conn->begin_transaction();

                        // 1. Create entry in customers table
                        // We use user_id = 1 (System Admin) as the creator for auto-signed up customers
                        $added_by = 1; 
                        $cust_stmt = $conn->prepare("INSERT INTO customers (company_name, contact_person, email, phone, city, country, address, notes, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $cust_stmt->bind_param("ssssssssi", $company_name, $contact_person, $email, $phone, $city, $country, $address, $notes, $added_by);
                        
                        if ($cust_stmt->execute()) {
                            $new_customer_id = $cust_stmt->insert_id;
                            $cust_stmt->close();

                            // 1.2 Insert custom field values (matched by field IDs in custom_fields table)
                            $custom_fields_data = [
                                1 => $cf_industry,
                                2 => $cf_company_size,
                                3 => $cf_tax_id,
                                4 => $cf_website,
                                5 => $cf_notes_internal
                            ];

                            $val_stmt = $conn->prepare("INSERT INTO custom_field_values (field_id, entity_type, entity_id, field_value) VALUES (?, 'customer', ?, ?)");
                            foreach ($custom_fields_data as $fid => $fval) {
                                if ($fval !== '') {
                                    $val_stmt->bind_param("iis", $fid, $new_customer_id, $fval);
                                    $val_stmt->execute();
                                }
                            }
                            $val_stmt->close();

                            // 2. Hash password and create user linked to this customer
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $role = 'customer'; // Default role is customer

                            $stmt = $conn->prepare("INSERT INTO users (username, full_name, password, email, role, customer_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                            $stmt->bind_param("sssssi", $username, $company_name, $hashed_password, $email, $role, $new_customer_id);

                            if ($stmt->execute()) {
                                $new_user_id = $stmt->insert_id;
                                $stmt->close();

                                // Commit all inserts
                                $conn->commit();

                                // Log the signup
                                logActivity($new_user_id, $username, 'Signup', 'New customer registered, profile and custom fields linked');

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
                                $conn->rollback();
                                $error = 'Registration failed. Please try again.';
                                $stmt->close();
                            }
                        } else {
                            $conn->rollback();
                            $error = 'Failed to create customer profile. Please try again.';
                            $cust_stmt->close();
                        }
                    }
                }
            } catch (Exception $e) {
                if (isset($conn)) { $conn->rollback(); }
                if (strpos($e->getMessage(), "doesn't exist") !== false) {
                    $error = 'Database not set up. Please run <a href="setup.php" class="login-link">setup.php</a> first.';
                } else {
                    $error = 'An error occurred: ' . $e->getMessage();
                    error_log("Signup error: " . $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign Up - <?php echo htmlspecialchars($branding['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <style>
        .login-box {
            max-width: 850px;
            width: 95%;
            padding: 40px;
            margin: 40px auto;
        }
        .signup-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            text-align: left;
        }
        .span-2 {
            grid-column: span 2;
        }
        .section-title {
            grid-column: span 2;
            font-size: 15px;
            font-weight: 700;
            margin-top: 15px;
            padding-bottom: 6px;
            border-bottom: 2px solid #0074D9;
            color: #0074D9;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .dark-mode .section-title {
            border-bottom-color: #38bdf8;
            color: #38bdf8;
        }
        .form-group label {
            font-weight: 600;
            font-size: 13px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: #fff;
            color: #333;
            font-size: 14px;
            transition: all 0.3s;
        }
        .dark-mode .form-group input, .dark-mode .form-group select, .dark-mode .form-group textarea {
            background: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #0074D9;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 116, 217, 0.15);
        }
        @media (max-width: 768px) {
            .signup-grid {
                grid-template-columns: 1fr;
            }
            .span-2 {
                grid-column: span 1;
            }
            .login-box {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <img src="<?php echo htmlspecialchars($branding['site_logo']); ?>" alt="Logo" class="login-logo">
            <h2>Create Your Business Account</h2>
            <p style="color:#666; margin-top:5px; margin-bottom:25px; font-size:14px;">Fill in your details below to get instant access to premium extensions & tools</p>

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

                <div class="signup-grid">
                    
                    <!-- SECTION 1: Login Account details -->
                    <div class="section-title">
                        <i class="fas fa-user-lock"></i> Account Credentials
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username *</label>
                        <input type="text" name="username" required autofocus autocomplete="username" minlength="3" maxlength="50" placeholder="Choose a username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address *</label>
                        <input type="email" name="email" required autocomplete="email" maxlength="100" placeholder="Enter email address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password *</label>
                        <input type="password" name="password" required autocomplete="new-password" minlength="6" placeholder="Minimum 6 characters">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm Password *</label>
                        <input type="password" name="confirm_password" required autocomplete="new-password" minlength="6" placeholder="Confirm your password">
                    </div>

                    <!-- SECTION 2: General Profile Details -->
                    <div class="section-title">
                        <i class="fas fa-building"></i> Customer &amp; Billing Info
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Customer / Company Name *</label>
                        <input type="text" name="company_name" required placeholder="Enter customer name" value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user-tie"></i> Contact Person</label>
                        <input type="text" name="contact_person" placeholder="Enter contact person name" value="<?php echo isset($_POST['contact_person']) ? htmlspecialchars($_POST['contact_person']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Contact Number *</label>
                        <input type="text" name="phone" required placeholder="Enter contact number" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Website</label>
                        <input type="url" name="cf_website" placeholder="https://example.com" value="<?php echo isset($_POST['cf_website']) ? htmlspecialchars($_POST['cf_website']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-city"></i> City</label>
                        <input type="text" name="city" placeholder="Enter city" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-globe-americas"></i> Country</label>
                        <input type="text" name="country" placeholder="Enter country" value="<?php echo isset($_POST['country']) ? htmlspecialchars($_POST['country']) : ''; ?>">
                    </div>

                    <div class="form-group span-2">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea name="address" rows="2" placeholder="Enter full address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>

                    <!-- SECTION 3: Business profile (Custom Fields) -->
                    <div class="section-title">
                        <i class="fas fa-briefcase"></i> Additional Business Profile
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-industry"></i> Industry</label>
                        <select name="cf_industry">
                            <option value="">-- Select Industry --</option>
                            <option value="IT" <?php echo (isset($_POST['cf_industry']) && $_POST['cf_industry'] === 'IT') ? 'selected' : ''; ?>>IT &amp; Software</option>
                            <option value="Healthcare" <?php echo (isset($_POST['cf_industry']) && $_POST['cf_industry'] === 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                            <option value="Finance" <?php echo (isset($_POST['cf_industry']) && $_POST['cf_industry'] === 'Finance') ? 'selected' : ''; ?>>Finance &amp; Banking</option>
                            <option value="Education" <?php echo (isset($_POST['cf_industry']) && $_POST['cf_industry'] === 'Education') ? 'selected' : ''; ?>>Education</option>
                            <option value="Retail" <?php echo (isset($_POST['cf_industry']) && $_POST['cf_industry'] === 'Retail') ? 'selected' : ''; ?>>Retail &amp; E-commerce</option>
                            <option value="Manufacturing" <?php echo (isset($_POST['cf_industry']) && $_POST['cf_industry'] === 'Manufacturing') ? 'selected' : ''; ?>>Manufacturing</option>
                            <option value="Other" <?php echo (isset($_POST['cf_industry']) && $_POST['cf_industry'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Company Size</label>
                        <select name="cf_company_size">
                            <option value="">-- Select Size --</option>
                            <option value="1-10" <?php echo (isset($_POST['cf_company_size']) && $_POST['cf_company_size'] === '1-10') ? 'selected' : ''; ?>>1-10 Employees</option>
                            <option value="11-50" <?php echo (isset($_POST['cf_company_size']) && $_POST['cf_company_size'] === '11-50') ? 'selected' : ''; ?>>11-50 Employees</option>
                            <option value="51-200" <?php echo (isset($_POST['cf_company_size']) && $_POST['cf_company_size'] === '51-200') ? 'selected' : ''; ?>>51-200 Employees</option>
                            <option value="201-500" <?php echo (isset($_POST['cf_company_size']) && $_POST['cf_company_size'] === '201-500') ? 'selected' : ''; ?>>201-500 Employees</option>
                            <option value="500+" <?php echo (isset($_POST['cf_company_size']) && $_POST['cf_company_size'] === '500+') ? 'selected' : ''; ?>>500+ Employees</option>
                        </select>
                    </div>

                    <div class="form-group span-2">
                        <label><i class="fas fa-percent"></i> Tax ID / VAT No</label>
                        <input type="text" name="cf_tax_id" placeholder="Enter Tax ID or VAT Registration Number" value="<?php echo isset($_POST['cf_tax_id']) ? htmlspecialchars($_POST['cf_tax_id']) : ''; ?>">
                    </div>

                    <div class="form-group span-2">
                        <label><i class="fas fa-sticky-note"></i> Public Notes / Special Instructions</label>
                        <textarea name="notes" rows="2" placeholder="Any additional notes or instructions for billing..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>

                    <div class="form-group span-2">
                        <label><i class="fas fa-file-invoice"></i> Internal Notes (Private to Admin)</label>
                        <textarea name="cf_notes_internal" rows="2" placeholder="Enter any private remarks or internal details..."><?php echo isset($_POST['cf_notes_internal']) ? htmlspecialchars($_POST['cf_notes_internal']) : ''; ?></textarea>
                    </div>

                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 30px; padding: 12px; font-size:16px;">
                    <i class="fas fa-user-plus"></i> Complete Onboarding &amp; Register
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
