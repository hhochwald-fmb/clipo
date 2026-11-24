<?php
require_once __DIR__ . '/config_loader.php';

if (!clipoLoadConfig()) {
    header("Location: setup.php");
    exit;
}

require_once __DIR__ . '/functions.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Clipo: Datenbank-Verbindung fehlgeschlagen: " . $e->getMessage());
    http_response_code(503);
    die("Datenbank-Verbindung fehlgeschlagen. Details stehen im Fehler-Log des Servers.");
}

// Konfiguration aus Umgebungsvariablen, aber Datenbank noch leer -> erst Setup ausführen
if (!file_exists(CLIPO_CONFIG_FILE) && !$pdo->query("SHOW TABLES LIKE 'users'")->fetch()) {
    header("Location: setup.php");
    exit;
}

// Nach einem Update: Datenbank automatisch auf den Stand des Codes bringen
require_once __DIR__ . '/migrations.php';
clipoAutoMigrate($pdo);
