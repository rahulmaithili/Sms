<?php
/**
 * Secure Proxy Download for Digital Products & Extensions
 * 
 * Verifies customer session, subscription status, and payment status,
 * then downloads the latest ZIP file directly from the GitHub Repository API.
 * Never exposes the raw GitHub URL or authentication tokens to the frontend.
 * 
 * Usage: download.php?sl=SUBSCRIPTION_SL
 */

require_once 'config.php';

// 1. Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    http_response_code(403);
    die("Access denied. Please log in to your Customer Portal.");
}

$user_id     = $_SESSION['user_id'];
$customer_id = $_SESSION['customer_id'] ?? null;
$sub_sl      = intval($_GET['sl'] ?? 0);

if ($sub_sl <= 0 || !$customer_id) {
    http_response_code(400);
    die("Invalid request parameters.");
}

try {
    $conn = getDBConnection();

    // 2. Fetch subscription details and check active status
    $stmt = $conn->prepare(
        "SELECT s.sl, s.invoice_no, s.payment_status, s.subscription_status, s.expiry_date,
                p.product_name, p.download_url
         FROM subscriptions s
         LEFT JOIN products p ON s.product_id = p.product_id
         WHERE s.sl = ? AND s.customer_id = ?"
    );
    $stmt->bind_param("ii", $sub_sl, $customer_id);
    $stmt->execute();
    $sub = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sub) {
        http_response_code(404);
        die("Subscription record not found.");
    }

    // 3. Validation: Only fully paid and active subscriptions can download
    if ($sub['payment_status'] !== 'Paid') {
        http_response_code(402);
        die("Payment required. Please make sure the subscription is fully paid.");
    }

    if ($sub['subscription_status'] !== 'active') {
        http_response_code(403);
        die("This subscription is currently paused or cancelled.");
    }

    // Expiry check
    if (!empty($sub['expiry_date'])) {
        $expiry = new DateTime($sub['expiry_date']);
        $now    = new DateTime();
        if ($now > $expiry) {
            http_response_code(403);
            die("This subscription expired on " . htmlspecialchars($sub['expiry_date']) . ". Please renew to download.");
        }
    }

    $download_url = trim($sub['download_url'] ?? '');

    if (empty($download_url)) {
        http_response_code(404);
        die("No download file associated with this product. Please contact support.");
    }

    // 4. Check if it's a GitHub URL
    if (stripos($download_url, 'github.com') !== false) {
        
        // Parse GitHub owner and repo from URL
        $url = rtrim($download_url, '/');
        if (str_ends_with($url, '.git')) {
            $url = substr($url, 0, -4);
        }
        
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', trim($path, '/'));
        
        if (count($parts) < 2) {
            http_response_code(500);
            die("Invalid GitHub repository URL configured.");
        }

        $owner = $parts[0];
        $repo  = $parts[1];

        // Construct GitHub zipball download endpoint (default branch zip)
        $github_api_url = "https://api.github.com/repos/{$owner}/{$repo}/zipball";

        // Fetch GitHub token from settings
        $token = getSetting('github_token', '');

        // 5. Initialize cURL to fetch the file securely
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $github_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects (from api.github.com to codeload.github.com)
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        // GitHub API requires a User-Agent header
        $headers = [
            'User-Agent: SubscriptionManagementSystem-App'
        ];

        // Attach authorization header if token is set (for private repos)
        if (!empty($token)) {
            $headers[] = 'Authorization: token ' . $token;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($http_code !== 200 || empty($response)) {
            http_response_code($http_code ?: 500);
            error_log("GitHub download failed for {$owner}/{$repo}. HTTP Status: {$http_code}");
            die("Failed to retrieve the file from GitHub. (HTTP Status: {$http_code}). Please verify repository settings.");
        }

        // 6. Send ZIP headers and stream file download to browser
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '', $repo) . "-latest.zip";
        
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false);
        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: " . strlen($response));
        
        echo $response;
        exit();

    } else {
        // Fallback: If it's a standard direct server link or upload path
        if (strpos($download_url, 'http') === 0) {
            // External direct HTTP URL redirect
            header("Location: " . $download_url);
            exit();
        } else {
            // Local file path
            $filepath = __DIR__ . '/' . ltrim($download_url, '/');
            if (file_exists($filepath)) {
                $filename = basename($filepath);
                header("Content-Type: application/octet-stream");
                header("Content-Disposition: attachment; filename=\"{$filename}\"");
                header("Content-Length: " . filesize($filepath));
                readfile($filepath);
                exit();
            } else {
                http_response_code(404);
                die("The requested product file was not found on our server.");
            }
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Secure download crash: " . $e->getMessage());
    die("An internal server error occurred while processing your download.");
}
?>
