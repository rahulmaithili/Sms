<?php
/**
 * Developed by Rameez Scripts
 * WhatsApp: https://wa.me/923224083545 (For Custom Projects)
 * YouTube: https://www.youtube.com/@rameezimdad (Subscribe for more!)
 *
 * Google OAuth Callback Handler
 */

require_once 'config.php';

// Check if Google OAuth is enabled
$oauth_enabled = getSetting('google_oauth_enabled', '0');
if ($oauth_enabled !== '1') {
    header("Location: login.php");
    exit();
}

// Get OAuth credentials from database
$client_id = getSetting('google_client_id', '');
$client_secret = getSetting('google_client_secret', '');
$redirect_uri = getSetting('google_redirect_uri', '');

if (empty($client_id) || empty($client_secret) || empty($redirect_uri)) {
    header("Location: login.php?error=oauth_not_configured");
    exit();
}

// Check for error from Google
if (isset($_GET['error'])) {
    header("Location: login.php?error=oauth_denied");
    exit();
}

// Check for authorization code
if (!isset($_GET['code'])) {
    header("Location: login.php");
    exit();
}

$code = $_GET['code'];

// Exchange authorization code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'code' => $code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$token_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    error_log("Google OAuth token error: " . $token_response);
    header("Location: login.php?error=oauth_token_failed");
    exit();
}

$token_data = json_decode($token_response, true);

if (!isset($token_data['access_token'])) {
    error_log("Google OAuth: No access token in response");
    header("Location: login.php?error=oauth_token_failed");
    exit();
}

$access_token = $token_data['access_token'];

// Get user info from Google
$userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userinfo_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$userinfo_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    error_log("Google OAuth userinfo error: " . $userinfo_response);
    header("Location: login.php?error=oauth_userinfo_failed");
    exit();
}

$google_user = json_decode($userinfo_response, true);

if (!isset($google_user['id']) || !isset($google_user['email'])) {
    error_log("Google OAuth: Missing user data");
    header("Location: login.php?error=oauth_userinfo_failed");
    exit();
}

$google_id = $google_user['id'];
$google_email = $google_user['email'];
$google_name = isset($google_user['name']) ? $google_user['name'] : '';

try {
    $conn = getDBConnection();

    // First, check if user exists by google_id
    $stmt = $conn->prepare("SELECT user_id, username, full_name, role, is_active FROM users WHERE google_id = ?");
    $stmt->bind_param("s", $google_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Existing Google user - log them in
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user['is_active']) {
            header("Location: login.php?error=oauth_error");
            exit();
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['LAST_ACTIVITY'] = time();

        // Update last_login and login_count
        $login_update = $conn->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE user_id = ?");
        $login_update->bind_param("i", $user['user_id']);
        $login_update->execute();
        $login_update->close();

        logActivity($user['user_id'], $user['username'], 'Google Login', 'User logged in via Google OAuth');

        header("Location: dashboard.php");
        exit();
    }
    $stmt->close();

    // Check if user exists by email
    $stmt = $conn->prepare("SELECT user_id, username, full_name, role, is_active FROM users WHERE email = ?");
    $stmt->bind_param("s", $google_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Link Google account to existing user
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user['is_active']) {
            header("Location: login.php?error=oauth_error");
            exit();
        }

        $update_stmt = $conn->prepare("UPDATE users SET google_id = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $google_id, $user['user_id']);
        $update_stmt->execute();
        $update_stmt->close();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['LAST_ACTIVITY'] = time();

        // Update last_login and login_count
        $login_update = $conn->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE user_id = ?");
        $login_update->bind_param("i", $user['user_id']);
        $login_update->execute();
        $login_update->close();

        logActivity($user['user_id'], $user['username'], 'Google Login', 'Google account linked and logged in');

        header("Location: dashboard.php");
        exit();
    }
    $stmt->close();

    // Create new user from Google account
    // Generate a username from email (part before @)
    $base_username = preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $google_email)[0]);
    if (strlen($base_username) < 3) {
        $base_username = 'user_' . $base_username;
    }

    // Ensure unique username
    $username_candidate = $base_username;
    $counter = 1;
    while (true) {
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username_candidate);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_stmt->close();

        if ($check_result->num_rows == 0) {
            break;
        }
        $username_candidate = $base_username . '_' . $counter;
        $counter++;
    }

    // Create user with a random password (they'll login via Google)
    $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $role = 'user';
    $full_name_google = !empty($google_name) ? $google_name : ucfirst($username_candidate);

    $insert_stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, google_id, email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
    $insert_stmt->bind_param("ssssss", $username_candidate, $random_password, $full_name_google, $google_email, $role, $google_id);

    if ($insert_stmt->execute()) {
        $new_user_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['username'] = $username_candidate;
        $_SESSION['full_name'] = $full_name_google;
        $_SESSION['role'] = $role;
        $_SESSION['LAST_ACTIVITY'] = time();

        // Update last_login and login_count
        $login_update = $conn->prepare("UPDATE users SET last_login = NOW(), login_count = 1 WHERE user_id = ?");
        $login_update->bind_param("i", $new_user_id);
        $login_update->execute();
        $login_update->close();

        logActivity($new_user_id, $username_candidate, 'Google Signup', 'New user registered via Google OAuth');

        header("Location: dashboard.php");
        exit();
    } else {
        $insert_stmt->close();
        error_log("Google OAuth: Failed to create user");
        header("Location: login.php?error=oauth_create_failed");
        exit();
    }
} catch (Exception $e) {
    error_log("Google OAuth callback error: " . $e->getMessage());
    header("Location: login.php?error=oauth_error");
    exit();
}
