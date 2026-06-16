<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session Timeout Logic (15 Minutes)
$timeout = 15 * 60; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=timeout");
    exit;
}
$_SESSION['last_activity'] = time();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isManager() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'manager' || $_SESSION['user_role'] === 'admin');
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die("Zugriff verweigert: Nur Administratoren.");
    }
}

function requireManager() {
    requireLogin();
    if (!isManager()) {
        die("Zugriff verweigert: Nur Manager oder Administratoren.");
    }
}
