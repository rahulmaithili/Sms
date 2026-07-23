<?php
require_once 'config.php';

// allow switchBack before admin check
if (isset($_GET['action']) && $_GET['action'] === 'switchBack' && isset($_SESSION['admin_original'])) {
    header('Content-Type: application/json');
    $orig = $_SESSION['admin_original'];
    $_SESSION['user_id'] = $orig['user_id'];
    $_SESSION['username'] = $orig['username'];
    $_SESSION['full_name'] = $orig['full_name'];
    $_SESSION['role'] = $orig['role'];
    $_SESSION['salesperson_id'] = $orig['salesperson_id'];
    $_SESSION['LAST_ACTIVITY'] = time();
    unset($_SESSION['admin_original']);
    logActivity($orig['user_id'], $orig['username'], 'Switch Back', 'Admin returned to own account');
    echo json_encode(['success' => true, 'message' => 'Switched back to admin']);
    exit();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Check session timeout
if (!checkSessionTimeout()) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
$current_page = 'users';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {
            case 'getUsers':
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT u.user_id, u.username, u.full_name, u.email, u.phone, u.role, u.department, u.salesperson_id, sp.name AS salesperson_name, u.customer_id, c.company_name AS customer_name, u.last_login, u.login_count, u.is_active, u.created_at FROM users u LEFT JOIN salespersons sp ON u.salesperson_id = sp.salesperson_id LEFT JOIN customers c ON u.customer_id = c.customer_id ORDER BY u.user_id DESC");
                $stmt->execute();
                $result = $stmt->get_result();

                $users = [];
                while ($row = $result->fetch_assoc()) {
                    $users[] = [
                        'id' => $row['user_id'],
                        'username' => $row['username'],
                        'full_name' => $row['full_name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'] ?? '',
                        'role' => $row['role'],
                        'department' => $row['department'] ?? '',
                        'salesperson_id' => $row['salesperson_id'] ? (int)$row['salesperson_id'] : null,
                        'salesperson_name' => $row['salesperson_name'] ?? '',
                        'customer_id' => $row['customer_id'] ? (int)$row['customer_id'] : null,
                        'customer_name' => $row['customer_name'] ?? '',
                        'last_login' => $row['last_login'] ? date('M d, Y H:i', strtotime($row['last_login'])) : 'Never',
                        'last_login_raw' => $row['last_login'] ?? '',
                        'login_count' => (int)$row['login_count'],
                        'is_active' => (bool)$row['is_active'],
                        'created_at' => date('M d, Y', strtotime($row['created_at']))
                    ];
                }

                $stmt->close();

                echo json_encode(['success' => true, 'data' => $users]);
                exit();

            case 'getSalespersons':
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT sp.salesperson_id, sp.name, sp.email, u.user_id AS linked_user_id
                    FROM salespersons sp LEFT JOIN users u ON u.salesperson_id = sp.salesperson_id
                    WHERE sp.is_active = 1 ORDER BY sp.name ASC");
                $stmt->execute();
                $result = $stmt->get_result();
                $sps = [];
                while ($row = $result->fetch_assoc()) {
                    $sps[] = [
                        'salesperson_id' => (int)$row['salesperson_id'],
                        'name' => $row['name'],
                        'email' => $row['email'] ?? '',
                        'linked_user_id' => $row['linked_user_id'] ? (int)$row['linked_user_id'] : null
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $sps]);
                exit();

            case 'getCustomersList':
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT c.customer_id, c.company_name, c.email, u.user_id AS linked_user_id
                    FROM customers c LEFT JOIN users u ON u.customer_id = c.customer_id
                    WHERE c.is_active = 1 ORDER BY c.company_name ASC");
                $stmt->execute();
                $result = $stmt->get_result();
                $custs = [];
                while ($row = $result->fetch_assoc()) {
                    $custs[] = [
                        'customer_id' => (int)$row['customer_id'],
                        'company_name' => $row['company_name'],
                        'email' => $row['email'] ?? '',
                        'linked_user_id' => $row['linked_user_id'] ? (int)$row['linked_user_id'] : null
                    ];
                }
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $custs]);
                exit();

            case 'addUser':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $newUsername = isset($_POST['username']) ? trim($_POST['username']) : '';
                $fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
                $password = isset($_POST['password']) ? $_POST['password'] : '';
                $newRole = isset($_POST['role']) ? $_POST['role'] : 'customer';
                $department = isset($_POST['department']) ? trim($_POST['department']) : '';

                if (empty($newUsername) || empty($fullName) || empty($email) || empty($password)) {
                    echo json_encode(['success' => false, 'message' => 'Username, full name, email and password are required']);
                    exit();
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    exit();
                }

                if (!in_array($newRole, ['admin', 'salesperson', 'customer'])) {
                    $newRole = 'customer';
                }

                $spIdVal = null;
                if ($newRole === 'salesperson' && isset($_POST['salesperson_id']) && $_POST['salesperson_id'] !== '') {
                    $spIdVal = intval($_POST['salesperson_id']);
                }
                $custIdVal = null;
                if ($newRole === 'customer' && isset($_POST['customer_id']) && $_POST['customer_id'] !== '') {
                    $custIdVal = intval($_POST['customer_id']);
                }

                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->bind_param("s", $newUsername);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Username already exists']);
                    exit();
                }
                $stmt->close();

                // check duplicate email
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Email already in use']);
                    exit();
                }
                $stmt->close();

                // check salesperson not already linked to another user
                if ($spIdVal) {
                    $chk = $conn->prepare("SELECT user_id FROM users WHERE salesperson_id = ?");
                    $chk->bind_param("i", $spIdVal); $chk->execute();
                    if ($chk->get_result()->num_rows > 0) {
                        $chk->close();
                        echo json_encode(['success' => false, 'message' => 'This salesperson is already linked to another user']); exit();
                    }
                    $chk->close();
                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $phoneVal = !empty($phone) ? $phone : null;
                $deptVal = !empty($department) ? $department : null;

                $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, phone, password, role, department, salesperson_id, customer_id, email_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
                $stmt->bind_param("sssssssii", $newUsername, $fullName, $email, $phoneVal, $hashedPassword, $newRole, $deptVal, $spIdVal, $custIdVal);

                if ($stmt->execute()) {
                    $roleLabel = ucfirst($newRole);
                    logActivity($user_id, $username, 'User Created', "Created user: $newUsername ($roleLabel)");
                    try { createNotificationForAdmins('User Created', 'Admin "' . htmlspecialchars($username) . '" created user "' . htmlspecialchars($newUsername) . '" (' . $roleLabel . ').', 'info', 'users.php'); } catch (Exception $e) {}

                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'User added successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to add user']);
                }
                exit();

            case 'updateUser':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $userId = isset($_POST['id']) ? intval($_POST['id']) : 0;
                $newUsername = isset($_POST['username']) ? trim($_POST['username']) : '';
                $fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
                $newRole = isset($_POST['role']) ? $_POST['role'] : 'customer';
                $department = isset($_POST['department']) ? trim($_POST['department']) : '';
                $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
                $password = isset($_POST['password']) ? $_POST['password'] : '';

                if ($userId <= 0 || empty($newUsername) || empty($fullName) || empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid input']);
                    exit();
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    exit();
                }

                if (!in_array($newRole, ['admin', 'salesperson', 'customer'])) {
                    $newRole = 'customer';
                }

                $spIdVal = null;
                if ($newRole === 'salesperson' && isset($_POST['salesperson_id']) && $_POST['salesperson_id'] !== '') {
                    $spIdVal = intval($_POST['salesperson_id']);
                }
                $custIdVal = null;
                if ($newRole === 'customer' && isset($_POST['customer_id']) && $_POST['customer_id'] !== '') {
                    $custIdVal = intval($_POST['customer_id']);
                }

                $conn = getDBConnection();

                // check customer not already linked
                if ($custIdVal) {
                    $chk = $conn->prepare("SELECT user_id FROM users WHERE customer_id = ? AND user_id != ?");
                    $chk->bind_param("ii", $custIdVal, $userId); $chk->execute();
                    if ($chk->get_result()->num_rows > 0) {
                        $chk->close();
                        echo json_encode(['success' => false, 'message' => 'This customer is already linked to another user']); exit();
                    }
                    $chk->close();
                }

                // check salesperson not already linked to another user
                if ($spIdVal) {
                    $chk = $conn->prepare("SELECT user_id FROM users WHERE salesperson_id = ? AND user_id != ?");
                    $chk->bind_param("ii", $spIdVal, $userId); $chk->execute();
                    if ($chk->get_result()->num_rows > 0) {
                        $chk->close();
                        echo json_encode(['success' => false, 'message' => 'This salesperson is already linked to another user']); exit();
                    }
                    $chk->close();
                }

                // Get old role for change detection
                $old_role_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
                $old_role_stmt->bind_param("i", $userId);
                $old_role_stmt->execute();
                $old_role_result = $old_role_stmt->get_result();
                $old_role = $old_role_result->num_rows > 0 ? $old_role_result->fetch_assoc()['role'] : '';
                $old_role_stmt->close();

                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
                $stmt->bind_param("si", $newUsername, $userId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Username already exists']);
                    exit();
                }
                $stmt->close();

                // check duplicate email
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->bind_param("si", $email, $userId);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Email already in use']);
                    exit();
                }
                $stmt->close();

                $phoneVal = !empty($phone) ? $phone : null;
                $deptVal = !empty($department) ? $department : null;

                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, password = ?, role = ?, department = ?, salesperson_id = ?, customer_id = ?, is_active = ? WHERE user_id = ?");
                    $stmt->bind_param("sssssssiiii", $newUsername, $fullName, $email, $phoneVal, $hashedPassword, $newRole, $deptVal, $spIdVal, $custIdVal, $is_active, $userId);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, role = ?, department = ?, salesperson_id = ?, customer_id = ?, is_active = ? WHERE user_id = ?");
                    $stmt->bind_param("ssssssiiii", $newUsername, $fullName, $email, $phoneVal, $newRole, $deptVal, $spIdVal, $custIdVal, $is_active, $userId);
                }

                if ($stmt->execute()) {
                    $details = !empty($password) ? "Updated user: $newUsername (password changed)" : "Updated user: $newUsername";
                    logActivity($user_id, $username, 'User Updated', $details);

                    try {
                        if ($old_role !== $newRole) {
                            createNotification($userId, 'Role Changed', 'Your role has been changed from ' . ucfirst($old_role) . ' to ' . ucfirst($newRole) . '.', 'warning', 'account.php');
                        }
                    } catch (Exception $e) {}

                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update user']);
                }
                exit();

            case 'toggleActive':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $userId = isset($_POST['id']) ? intval($_POST['id']) : 0;
                $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

                if ($userId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                    exit();
                }

                $conn = getDBConnection();

                // Protect default admin
                $check_stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
                $check_stmt->bind_param("i", $userId);
                $check_stmt->execute();
                $target = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();

                if ($target && $target['username'] === 'admin' && !$is_active) {
                    echo json_encode(['success' => false, 'message' => 'Cannot deactivate the default admin account']);
                    exit();
                }

                $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
                $stmt->bind_param("ii", $is_active, $userId);

                if ($stmt->execute()) {
                    $action_label = $is_active ? 'User Activated' : 'User Deactivated';
                    logActivity($user_id, $username, $action_label, "Changed active status for user ID $userId");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => ($is_active ? 'User activated' : 'User deactivated')]);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                exit();

            case 'deleteUser':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }

                $userId = isset($_POST['id']) ? intval($_POST['id']) : 0;

                if ($userId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                    exit();
                }

                if ($userId == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
                    exit();
                }

                $conn = getDBConnection();

                // Get username before deleting
                $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $deletedUsername = '';
                if ($result->num_rows > 0) {
                    $deletedUsername = $result->fetch_assoc()['username'];
                }
                $stmt->close();

                // Protect default admin
                if ($deletedUsername === 'admin') {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete the default admin account']);
                    exit();
                }

                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);

                if ($stmt->execute()) {
                    logActivity($user_id, $username, 'User Deleted', "Deleted user: $deletedUsername");
                    $stmt->close();
                    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                } else {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
                }
                exit();

            case 'loginAs':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']); exit();
                }
                $targetId = isset($_POST['id']) ? intval($_POST['id']) : 0;
                if ($targetId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid user ID']); exit();
                }
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT user_id, username, full_name, role, salesperson_id, is_active FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $targetId);
                $stmt->execute();
                $target = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$target) {
                    echo json_encode(['success' => false, 'message' => 'User not found']); exit();
                }
                if (!$target['is_active']) {
                    echo json_encode(['success' => false, 'message' => 'User is inactive']); exit();
                }

                // save admin session so we can switch back
                $_SESSION['admin_original'] = [
                    'user_id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'full_name' => $_SESSION['full_name'],
                    'role' => $_SESSION['role'],
                    'salesperson_id' => $_SESSION['salesperson_id'] ?? null
                ];

                // switch session to target user
                $_SESSION['user_id'] = (int)$target['user_id'];
                $_SESSION['username'] = $target['username'];
                $_SESSION['full_name'] = $target['full_name'];
                $_SESSION['role'] = $target['role'];
                $_SESSION['salesperson_id'] = $target['salesperson_id'] ? (int)$target['salesperson_id'] : null;
                $_SESSION['LAST_ACTIVITY'] = time();

                logActivity($user_id, $username, 'Login As', "Admin switched to user: {$target['username']} ({$target['role']})");
                echo json_encode(['success' => true, 'message' => 'Switched to ' . $target['full_name']]);
                exit();

            case 'switchBack':
                if (!isset($_SESSION['admin_original'])) {
                    echo json_encode(['success' => false, 'message' => 'No admin session to restore']); exit();
                }
                $orig = $_SESSION['admin_original'];
                $_SESSION['user_id'] = $orig['user_id'];
                $_SESSION['username'] = $orig['username'];
                $_SESSION['full_name'] = $orig['full_name'];
                $_SESSION['role'] = $orig['role'];
                $_SESSION['salesperson_id'] = $orig['salesperson_id'];
                $_SESSION['LAST_ACTIVITY'] = time();
                unset($_SESSION['admin_original']);

                logActivity($orig['user_id'], $orig['username'], 'Switch Back', 'Admin returned to own account');
                echo json_encode(['success' => true, 'message' => 'Switched back to admin']);
                exit();

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        error_log("Users.php error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// If we reach here, render the HTML page
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
    <title>User Management - Dashboard System</title>

    <!-- CDN Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="styles.css?v=7.0">
    <style>
        .toggle { appearance: none; width: 44px; height: 24px; border-radius: 24px; background: #ccc; position: relative; cursor: pointer; transition: background .3s; border: none; outline: none; vertical-align: middle; }
        .toggle:checked { background: #0074D9; }
        .toggle::before { content: ""; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform .3s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
        .toggle:checked::before { transform: translateX(20px); }
    </style>
</head>
<body>
    <?php include 'mobile-menu.php'; ?>

    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span>Users</span>
            </div>
            <div class="header">
                <h1><i class="fas fa-users"></i> User Management</h1>
                <?php include 'notifications_bell.php'; ?>
            </div>

            <div class="data-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Users</h2>
                    <div class="btn-group-inline">
                        <button class="btn btn-primary" onclick="loadUsers()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-success" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add User
                        </button>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filters-section initially-hidden" id="filtersSection">
                    <div class="filters-header">
                        <h3><i class="fas fa-filter"></i> Filters</h3>
                        <button class="btn btn-secondary btn-sm" onclick="clearFilters()">
                            <i class="fas fa-times-circle"></i> Clear All
                        </button>
                    </div>
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Date From</label>
                            <input type="date" id="filterDateFrom" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Date To</label>
                            <input type="date" id="filterDateTo" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-user-tag"></i> Role</label>
                            <select id="filterRole" class="filter-input">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="salesperson">Salesperson</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-building"></i> Department</label>
                            <select id="filterDepartment" class="filter-input">
                                <option value="">All Departments</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-toggle-on"></i> Status</label>
                            <select id="filterStatus" class="filter-input">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all columns
                </div>
                <div class="table-responsive">
                    <table id="usersTable" class="display table-full-width"></table>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add User</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId" name="id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Full Name *</label>
                            <input type="text" id="fullName" name="full_name" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Username *</label>
                            <input type="text" id="formUsername" name="username" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone</label>
                            <input type="text" id="phone" name="phone">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password <span id="passwordHint" class="initially-hidden">(Leave empty to keep current)</span></label>
                            <input type="password" id="password" name="password">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user-tag"></i> Role *</label>
                            <select id="formRole" name="role" required onchange="toggleSPDropdown(); toggleCustomerDropdown();">
                                <option value="admin">Admin</option>
                                <option value="salesperson">Salesperson</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>

                        <div class="form-group" id="spGroup" style="display:none;">
                            <label><i class="fas fa-user-tie"></i> Link to Salesperson *</label>
                            <select id="formSalespersonId" name="salesperson_id">
                                <option value="">-- Select Salesperson --</option>
                            </select>
                        </div>

                        <div class="form-group" id="custGroup" style="display:none;">
                            <label><i class="fas fa-address-book"></i> Link to Customer *</label>
                            <select id="formCustomerId" name="customer_id">
                                <option value="">-- Select Customer --</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Department</label>
                            <input type="text" id="department" name="department">
                        </div>

                        <div class="form-group" id="activeGroup" style="display:none;">
                            <label><i class="fas fa-toggle-on"></i> Active Status</label>
                            <select id="formIsActive" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
    // Lazy-load export dependencies (PDF/Excel) on first use
    function loadExportDeps(callback) {
        if (window.pdfMake) { callback(); return; }
        var urls = [
            'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js'
        ];
        var loaded = 0;
        function loadNext() {
            if (loaded >= urls.length) { callback(); return; }
            var s = document.createElement('script');
            s.src = urls[loaded];
            s.onload = function() { loaded++; loadNext(); };
            document.head.appendChild(s);
        }
        loadNext();
    }
    </script>

    <script>
        let usersTable;
        let isEditMode = false;
        let usersData = [];

        $(document).ready(function() {
            loadUsers();
        });

        function loadUsers() {
            $.ajax({
                url: '?action=getUsers',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        usersData = response.data;
                        $('#filtersSection').show();
                        populateDepartmentFilter(response.data);
                        initializeDataTable(response.data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to load users'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Could not connect to server. Please check console for details.'
                    });
                }
            });
        }

        function populateDepartmentFilter(data) {
            const depts = [...new Set(data.map(u => u.department).filter(d => d))];
            const sel = document.getElementById('filterDepartment');
            sel.innerHTML = '<option value="">All Departments</option>';
            depts.sort().forEach(d => {
                sel.innerHTML += '<option value="' + d + '">' + d + '</option>';
            });
        }

        function initializeDataTable(data) {
            if (usersTable) {
                usersTable.destroy();
                $('#usersTable').empty();
            }

            setTimeout(() => {
                usersTable = $('#usersTable').DataTable({
                    data: data,
                    destroy: true,
                    columns: [
                        { data: 'id', title: 'ID' },
                        { data: 'full_name', title: 'Full Name' },
                        { data: 'username', title: 'Username' },
                        { data: 'email', title: 'Email' },
                        {
                            data: 'role',
                            title: 'Role',
                            render: function(data) {
                                var cls = data === 'admin' ? 'status-admin' : data === 'salesperson' ? 'status-warning' : data === 'customer' ? 'status-info' : 'status-user';
                                var label = data.charAt(0).toUpperCase() + data.slice(1);
                                return '<span class="status-badge ' + cls + '">' + label + '</span>';
                            }
                        },
                        { data: 'department', title: 'Department', defaultContent: '-' },
                        { data: null, title: 'Linked To', defaultContent: '-', render: function(d,t,row) {
                            if (row.salesperson_name) return '<i class="fas fa-user-tie"></i> ' + row.salesperson_name;
                            if (row.customer_name) return '<i class="fas fa-address-book"></i> ' + row.customer_name;
                            return '-';
                        }},
                        { data: 'last_login', title: 'Last Login' },
                        {
                            data: 'is_active',
                            title: 'Active',
                            render: function(data, type, row) {
                                const checked = data ? 'checked' : '';
                                const disabled = row.username === 'admin' ? 'disabled' : '';
                                return '<input type="checkbox" ' + checked + ' ' + disabled + ' class="toggle" onchange="toggleActive(' + row.id + ', this.checked ? 1 : 0)">';
                            }
                        },
                        {
                            data: null,
                            title: 'Actions',
                            orderable: false,
                            render: function(data, type, row) {
                                var isSelf = row.id === <?php echo $user_id; ?>;
                                var html = '<button class="action-icon edit-icon" onclick=\'editUser(' + JSON.stringify(row).replace(/'/g, "\\'") + ')\'><i class="fas fa-edit"></i></button>';
                                if (!isSelf && row.is_active) {
                                    html += '<button class="action-icon" onclick="loginAs(' + row.id + ', \'' + (row.full_name||'').replace(/'/g,"\\'") + '\')" title="Login as this user" style="color:#0074D9;"><i class="fas fa-sign-in-alt"></i></button>';
                                }
                                if (row.username !== 'admin') {
                                    html += '<button class="action-icon delete-icon" onclick="deleteUser(' + row.id + ')"><i class="fas fa-trash"></i></button>';
                                }
                                return html;
                            }
                        }
                    ],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    responsive: true,
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: { columns: [0,1,2,3,4,5,6,7] }
                        },
                        {
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            action: function(e, dt, node, config) {
                                loadExportDeps(function() {
                                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(dt.button(node), e, dt, node, config);
                                });
                            },
                            exportOptions: { columns: [0,1,2,3,4,5,6,7] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: { columns: [0,1,2,3,4,5,6,7] }
                        }
                    ],
                    order: [[0, 'desc']]
                });

                // Apply filters on change
                $('#filterDateFrom, #filterDateTo, #filterRole, #filterDepartment, #filterStatus').on('change', function() {
                    applyFilters();
                });
            }, 100);
        }

        function applyFilters() {
            if (!usersTable) return;

            $.fn.dataTable.ext.search = [];

            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            const role = document.getElementById('filterRole').value;
            const department = document.getElementById('filterDepartment').value;
            const status = document.getElementById('filterStatus').value;

            $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex) {
                const row = usersData[dataIndex];
                if (!row) return true;

                // Date filter
                if (dateFrom || dateTo) {
                    const recordDate = new Date(row.created_at);
                    if (dateFrom && recordDate < new Date(dateFrom)) return false;
                    if (dateTo && recordDate > new Date(dateTo + 'T23:59:59')) return false;
                }

                // Role filter
                if (role && row.role !== role) return false;

                // Department filter
                if (department && row.department !== department) return false;

                // Status filter
                if (status === 'active' && !row.is_active) return false;
                if (status === 'inactive' && row.is_active) return false;

                return true;
            });

            usersTable.draw();
        }

        function clearFilters() {
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            document.getElementById('filterRole').value = '';
            document.getElementById('filterDepartment').value = '';
            document.getElementById('filterStatus').value = '';

            if (usersTable) {
                $.fn.dataTable.ext.search = [];
                usersTable.columns().search('').draw();
            }
        }

        var salespersonsList = [];
        var customersList = [];

        function loadSalespersonsList() {
            $.getJSON('?action=getSalespersons', function(r) {
                if (r.success) salespersonsList = r.data || [];
            });
        }
        function loadCustomersList() {
            $.getJSON('?action=getCustomersList', function(r) {
                if (r.success) customersList = r.data || [];
            });
        }
        loadSalespersonsList();
        loadCustomersList();

        function toggleSPDropdown(editingUserId) {
            var role = document.getElementById('formRole').value;
            var grp = document.getElementById('spGroup');
            var sel = document.getElementById('formSalespersonId');
            if (role === 'salesperson') {
                grp.style.display = '';
                // rebuild options
                var html = '<option value="">-- Select Salesperson --</option>';
                salespersonsList.forEach(function(sp) {
                    var taken = sp.linked_user_id && sp.linked_user_id !== editingUserId;
                    html += '<option value="' + sp.salesperson_id + '"' + (taken ? ' disabled' : '') + '>'
                         + sp.name + (sp.email ? ' (' + sp.email + ')' : '') + (taken ? ' [linked]' : '')
                         + '</option>';
                });
                sel.innerHTML = html;
            } else {
                grp.style.display = 'none';
                sel.value = '';
            }
        }

        function toggleCustomerDropdown(editingUserId) {
            var role = document.getElementById('formRole').value;
            var grp = document.getElementById('custGroup');
            var sel = document.getElementById('formCustomerId');
            if (role === 'customer') {
                grp.style.display = '';
                var html = '<option value="">-- Select Customer --</option>';
                customersList.forEach(function(c) {
                    var taken = c.linked_user_id && c.linked_user_id !== editingUserId;
                    html += '<option value="' + c.customer_id + '"' + (taken ? ' disabled' : '') + '>'
                         + c.company_name + (c.email ? ' (' + c.email + ')' : '') + (taken ? ' [linked]' : '')
                         + '</option>';
                });
                sel.innerHTML = html;
            } else {
                grp.style.display = 'none';
                sel.value = '';
            }
        }

        function openAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add User';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('passwordHint').style.display = 'none';
            document.getElementById('password').required = true;
            document.getElementById('activeGroup').style.display = 'none';
            document.getElementById('spGroup').style.display = 'none';
            document.getElementById('custGroup').style.display = 'none';
            document.getElementById('userModal').classList.add('active');
        }

        function editUser(user) {
            isEditMode = true;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit User';
            document.getElementById('userId').value = user.id;
            document.getElementById('fullName').value = user.full_name;
            document.getElementById('formUsername').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('formRole').value = user.role;
            document.getElementById('department').value = user.department || '';
            document.getElementById('formIsActive').value = user.is_active ? '1' : '0';
            document.getElementById('password').value = '';
            document.getElementById('passwordHint').style.display = 'inline';
            document.getElementById('password').required = false;
            document.getElementById('activeGroup').style.display = '';
            toggleSPDropdown(user.id);
            toggleCustomerDropdown(user.id);
            if (user.salesperson_id) {
                document.getElementById('formSalespersonId').value = user.salesperson_id;
            }
            if (user.customer_id) {
                document.getElementById('formCustomerId').value = user.customer_id;
            }
            document.getElementById('userModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('userModal').classList.remove('active');
            document.getElementById('userForm').reset();
        }

        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('userForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const action = isEditMode ? 'updateUser' : 'addUser';

            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: '?action=' + action,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        closeModal();
                        setTimeout(() => loadUsers(), 100);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Connection error: ' + error
                    });
                }
            });
        });

        function toggleActive(userId, isActive) {
            const formData = new FormData();
            formData.append('id', userId);
            formData.append('is_active', isActive);

            $.ajax({
                url: '?action=toggleActive',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        setTimeout(() => loadUsers(), 100);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                        setTimeout(() => loadUsers(), 100);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Connection error: ' + error
                    });
                }
            });
        }

        function deleteUser(userId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete User?',
                text: 'This action cannot be undone',
                showCancelButton: true,
                confirmButtonColor: '#ea4335',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', userId);

                    $.ajax({
                        url: '?action=deleteUser',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                setTimeout(() => loadUsers(), 100);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Connection error: ' + error
                            });
                        }
                    });
                }
            });
        }

        function loginAs(userId, name) {
            Swal.fire({
                icon: 'question',
                title: 'Login as ' + name + '?',
                text: 'You will be switched to this user\'s session. You can switch back anytime.',
                showCancelButton: true,
                confirmButtonText: 'Switch',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    var fd = new FormData();
                    fd.append('id', userId);
                    $.ajax({
                        url: '?action=loginAs',
                        method: 'POST',
                        data: fd,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(r) {
                            if (r.success) {
                                Swal.fire({ icon: 'success', text: r.message, timer: 1500, showConfirmButton: false });
                                setTimeout(function() { window.location.href = 'dashboard.php'; }, 1500);
                            } else {
                                Swal.fire({ icon: 'error', text: r.message });
                            }
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
