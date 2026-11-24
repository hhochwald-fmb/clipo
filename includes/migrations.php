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
