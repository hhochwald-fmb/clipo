<?php
require_once __DIR__ . '/includes/session.php';
clipoStartSession();
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
session_destroy();
header("Location: login.php");
exit;
