<?php
// Kopiere diese Datei nach config.php und trage deine Zugangsdaten ein.
// Die echte config.php darf NICHT ins Repository / Release-ZIP.
define('DB_HOST', 'localhost');
define('DB_NAME', 'clipo_db');
define('DB_USER', 'clipo_user');
define('DB_PASS', 'CHANGE_ME');
define('APP_NAME', 'Clipo');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('DB_VERSION', '1.5');

// Production: fixe Basis-URL für Reset-Mails etc. (verhindert Host-Header-Injection).
// Lokal optional weglassen — dann wird $_SERVER['HTTP_HOST'] benutzt.
// define('APP_URL', 'https://clipo.deine-schule.de');
