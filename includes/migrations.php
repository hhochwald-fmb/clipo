<?php
// Datenbank-Anpassungen für neue Clipo-Versionen.
// Alle Schritte sind so geschrieben, dass sie beliebig oft laufen dürfen.
// Neue Versionen hängen einfach weitere Schritte unten an.

function clipoRunMigrations(PDO $pdo) {
    $logs = [];

    // Benutzer-Spalten (ab 1.3)
    $userColumns = [
        'email' => "ALTER TABLE users ADD COLUMN email VARCHAR(255) AFTER username",
        'display_name' => "ALTER TABLE users ADD COLUMN display_name VARCHAR(100) AFTER password",
        'profile_image' => "ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) AFTER display_name",
        'reset_token' => "ALTER TABLE users ADD COLUMN reset_token VARCHAR(100) AFTER role",
        'reset_expiry' => "ALTER TABLE users ADD COLUMN reset_expiry DATETIME AFTER reset_token",
    ];
    foreach ($userColumns as $col => $sql) {
        if (!$pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($col))->fetch()) {
            $pdo->exec($sql);
            $logs[] = "Spalte users.$col hinzugefügt.";
        }
    }

    // Viewer-Rolle gibt es nicht mehr
    $n = $pdo->exec("UPDATE users SET role = 'manager' WHERE role = 'viewer'");
    if ($n) {
        $logs[] = "$n Viewer zu Managern umgewandelt.";
    }

    // Einstellungen
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    ) ENGINE=InnoDB");
    $defaults = [
        'primary_color' => '#f39200',
        'secondary_color' => '#4a5c66',
        'videos_per_page' => '12',
        'footer_email' => 'hochwald@lmz-bw.de',
        'public_access' => '0',
        'site_name' => 'Stadtmedienzentrum Stuttgart',
        'impressum_content' => '<h1>Impressum</h1><p>Bearbeite diesen Inhalt im Admin-Bereich.</p>',
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // Schlagwörter (ab 1.4)
    if (!$pdo->query("SHOW COLUMNS FROM videos LIKE 'tags'")->fetch()) {
        $pdo->exec("ALTER TABLE videos ADD COLUMN tags VARCHAR(255) AFTER description");
        $logs[] = "Spalte videos.tags hinzugefügt.";
    }

    // Schutz vor Passwort-Raten (ab 1.6.1)
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attempt_key VARCHAR(190) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL,
        INDEX idx_key (attempt_key, attempted_at),
        INDEX idx_ip (ip, attempted_at)
    ) ENGINE=InnoDB");

    // Upload-Ordner absichern (ab 1.6.1): keine Skripte ausführen, keine Dateiliste anzeigen.
    // Wird hier angelegt, weil ältere Updater nicht in uploads/ schreiben.
    $upload_dir = defined('UPLOAD_DIR') ? UPLOAD_DIR : dirname(__DIR__) . '/uploads/';
    if (is_dir($upload_dir) && is_writable($upload_dir)) {
        if (!file_exists($upload_dir . '.htaccess')) {
            $source = dirname(__DIR__) . '/uploads/.htaccess';
            $rules = is_file($source) ? file_get_contents($source)
                : "# Im Upload-Ordner niemals Skripte ausführen - nur Videos und Bilder ausliefern\n"
                . "<FilesMatch \"\\.(php\\d?|phtml|phar|pht|phps|cgi|pl|py|sh|shtml|html?|svg|js)$\">\n    Require all denied\n</FilesMatch>\n\n"
                . "# Dateien nie als etwas anderes interpretieren, als sie sind\n"
                . "<IfModule mod_headers.c>\n    Header set X-Content-Type-Options \"nosniff\"\n</IfModule>\n";
            file_put_contents($upload_dir . '.htaccess', $rules);
            $logs[] = "Schutzregeln für den Upload-Ordner angelegt.";
        }
        if (!file_exists($upload_dir . 'index.html')) {
            file_put_contents($upload_dir . 'index.html', '');
        }
    }

    // --- Neue Schritte hier anhängen ---

    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('db_version', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([CLIPO_VERSION]);

    return $logs;
}

// Wird bei jedem Seitenaufruf geprüft: Ist der Code neuer als die Datenbank,
// laufen die Migrationen einmal automatisch (z. B. direkt nach einem Online-Update).
function clipoAutoMigrate(PDO $pdo) {
    try {
        $row = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'db_version'")->fetch();
        if ($row && $row['setting_value'] === CLIPO_VERSION) {
            return;
        }
    } catch (PDOException $e) {
        // settings-Tabelle fehlt (sehr alte Installation) -> Migration legt sie an
    }
    try {
        clipoRunMigrations($pdo);
    } catch (PDOException $e) {
        error_log('Clipo: Automatische Datenbank-Migration fehlgeschlagen: ' . $e->getMessage());
    }
}
