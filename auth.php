<?php
// auth.php — Authentication, Role-based Access Control & CSRF Protection

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensure user is authenticated
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Ensure user has specific role
 */
function require_role($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Generate or retrieve current CSRF token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate hidden input field for forms
 */
function csrf_field() {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify submitted CSRF token
 */
function csrf_verify($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    $session_token = $_SESSION['csrf_token'] ?? '';
    if (empty($session_token) || empty($token)) {
        return false;
    }
    return hash_equals($session_token, (string)$token);
}

/**
 * Guard POST requests against CSRF
 */
function require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify()) {
            http_response_code(403);
            die("403 Forbidden: Invalid or expired security token (CSRF). Please refresh and try again.");
        }
    }
}
