<?php
require_once __DIR__ . '/session.php';
clipoStartSession();

// Session Timeout Logic (15 Minutes)
$timeout = 15 * 60; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=timeout");
    exit;
}
$_SESSION['last_activity'] = time();

// Rolle bei jedem Aufruf frisch aus der Datenbank holen: Gelöschte Benutzer
// fliegen sofort raus, geänderte Rollen gelten sofort.
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_role = $stmt->fetchColumn();
    if ($current_role === false) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
    $_SESSION['user_role'] = $current_role;
}

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
