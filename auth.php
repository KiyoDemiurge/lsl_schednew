
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function requireLogin() {
    if (empty($_SESSION['user'])) {
        header('Location: index.php?page=login');
        exit;
    }
}

function requireAdmin() {
    requireLogin();

    if ($_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        exit('Access denied. Admin permission is required.');
    }
}

function logAction($conn, $action, $details = '') {
    $userId = $_SESSION['user']['id'] ?? null;
    $stmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)"
    );
    $stmt->bind_param("iss", $userId, $action, $details);
    $stmt->execute();
}

function flash($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function csrfToken() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf() {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid form token. Refresh the page and try again.');
    }
}
