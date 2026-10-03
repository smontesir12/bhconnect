<?php
require_once __DIR__ . '/../config/db.php'; // Make sure this is require_once

if (session_status() === PHP_SESSION_NONE) session_start();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE . "/index.php");
        exit;
    }
}
function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        die("Access denied.");
    }
}
function currentUser() {
    return [
        'id'        => $_SESSION['user_id']  ?? null,
        'username'  => $_SESSION['username'] ?? null,
        'role'      => $_SESSION['role']     ?? null,
        'tenant_id' => $_SESSION['tenant_id']?? null,
    ];
}
?>