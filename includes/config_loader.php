<?php
// Lädt die Konfiguration: zuerst config.php (klassische Webserver-Installation),
// sonst Umgebungsvariablen (Docker, siehe docker-compose.yml).
// So überlebt die Konfiguration einen Neubau des Containers.

define('CLIPO_CONFIG_FILE', dirname(__DIR__) . '/config.php');
define('CLIPO_VERSION', '1.5');

function clipoLoadConfig() {
    if (file_exists(CLIPO_CONFIG_FILE)) {
        require_once CLIPO_CONFIG_FILE;
        return true;
    }

    if (getenv('DB_HOST') === false) {
        return false;
    }

    define('DB_HOST', getenv('DB_HOST'));
    define('DB_NAME', getenv('DB_NAME') ?: 'clipo_db');
    define('DB_USER', getenv('DB_USER') ?: 'clipo_user');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('APP_NAME', 'Clipo');
    define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
    define('DB_VERSION', CLIPO_VERSION);
    if (getenv('APP_URL')) {
        define('APP_URL', getenv('APP_URL'));
    }
    return true;
}
