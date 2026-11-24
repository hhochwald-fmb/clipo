<?php
// Startet die Session mit sicheren Cookie-Einstellungen:
// HttpOnly (kein Zugriff per JavaScript), SameSite=Lax (Schutz vor fremden Formularen),
// Secure, sobald die Seite per HTTPS aufgerufen wird.
function clipoStartSession() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
