<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAdmin();

$current_version = defined('DB_VERSION') ? DB_VERSION : '1.0';
$target_version = '1.5'; // Updated to 1.5

$logs = [];
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_update'])) {
    try {
        // Add user columns if not exists
        try { $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) AFTER username"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(100) AFTER password"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) AFTER display_name"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(100) AFTER role"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_expiry DATETIME AFTER reset_token"); } catch(Exception $e) {}

        $logs[] = "Datenbank-Schema: User-Tabelle um Email und Reset-Token erweitert.";

        // Step 1: Migration for viewers
        $pdo->exec("UPDATE users SET role = 'manager' WHERE role = 'viewer'");
        $logs[] = "Migration: Viewer-Rollen zu Managern umgewandelt.";

        // Step 2: Ensure settings table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT
        ) ENGINE=InnoDB");
        $logs[] = "Prüfung: Settings-Tabelle bereit.";

        // Step 2: Seed default settings
        $defaults = [
            'primary_color' => '#f39200',
            'secondary_color' => '#4a5c66',
            'videos_per_page' => '12',
            'footer_email' => 'hochwald@lmz-bw.de',
            'public_access' => '0',
            'site_name' => 'Stadtmedienzentrum Stuttgart',
            'impressum_content' => '<h1>Impressum</h1><p>Bearbeite diesen Inhalt im Admin-Bereich.</p>'
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $logs[] = "Prüfung: Standard-Einstellungen initialisiert.";

        // Step 3: Check for 'tags' column in 'videos'
        $stmt = $pdo->query("SHOW COLUMNS FROM videos LIKE 'tags'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE videos ADD COLUMN tags VARCHAR(255) AFTER description");
            $logs[] = "Update: Spalte 'tags' erfolgreich zur Tabelle 'videos' hinzugefügt.";
        } else {
            $logs[] = "Status: Spalte 'tags' bereits vorhanden.";
        }

        // Step 4: Update Version in config.php
        if (version_compare($current_version, $target_version, '<')) {
            $config_path = __DIR__ . '/config.php';
            if (file_exists($config_path)) {
                $config_content = file_get_contents($config_path);
                // Handle both quoted and unquoted version numbers in config.php
                $config_content = preg_replace("/define\('DB_VERSION', (.*)\);/", "define('DB_VERSION', '$target_version');", $config_content);
                file_put_contents($config_path, $config_content);
                $logs[] = "System: Versionsnummer in config.php auf v$target_version aktualisiert.";
            }
        }

        $success = true;
        
    } catch (PDOException $e) {
        $error = "Datenbank-Fehler: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clipo System Update</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .update-box { max-width: 600px; margin: 40px auto; }
        .progress-container { background: #e2e8f0; border-radius: 10px; height: 10px; margin: 20px 0; overflow: hidden; display: none; }
        .progress-bar { background: var(--primary-color); height: 100%; width: 0%; transition: width 0.5s ease; }
        .log-area { font-family: monospace; background: #1e293b; color: #34d399; padding: 20px; border-radius: 10px; font-size: 13px; margin-top: 20px; display: none; max-height: 200px; overflow-y: auto; }
        .log-entry { margin-bottom: 5px; border-left: 2px solid #34d399; padding-left: 10px; }
        .loader { width: 48px; height: 48px; border: 5px solid #FFF; border-bottom-color: var(--primary-color); border-radius: 50%; display: inline-block; box-sizing: border-box; animation: rotation 1s linear infinite; margin-bottom: 20px; }
        @keyframes rotation { 0% { transform: rotate(0deg) } 100% { transform: rotate(360deg) } }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="index.php" class="logo">Clipo<span>.</span></a>
        </div>
    </header>

    <div class="container">
        <div class="update-box card">
            <h1>System Update</h1>
            <p>Aktuelle Version: <strong>v<?php echo $current_version; ?></strong> &rarr; Ziel: <strong>v<?php echo $target_version; ?></strong></p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <div id="start-view" style="text-align: center; padding: 40px 0;">
                    <p style="margin-bottom: 30px;">Das System prüft alle Datenbank-Tabellen und skripte auf Aktualität.</p>
                    <form method="POST" onsubmit="startUpdate();">
                        <input type="hidden" name="start_update" value="1">
                        <button type="submit" class="btn">Update jetzt starten</button>
                    </form>
                </div>
                
                <div id="update-view" style="display: none; text-align: center; padding: 40px 0;">
                    <span class="loader"></span>
                    <h3>Update wird durchgeführt...</h3>
                    <div class="progress-container"><div class="progress-bar" id="progressBar"></div></div>
                </div>
            <?php else: ?>
                <div class="alert alert-success">Update erfolgreich abgeschlossen!</div>
                <div class="log-area" style="display: block;">
                    <?php foreach ($logs as $log): ?>
                        <div class="log-entry"><?php echo htmlspecialchars($log); ?></div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 30px; text-align: center;">
                    <a href="index.php" class="btn">Zurück zur Übersicht</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function startUpdate() {
            document.getElementById('start-view').style.display = 'none';
            document.getElementById('update-view').style.display = 'block';
            document.querySelector('.progress-container').style.display = 'block';
            
            let progress = 0;
            let bar = document.getElementById('progressBar');
            let interval = setInterval(() => {
                progress += Math.random() * 10;
                if (progress > 100) {
                    progress = 100;
                    clearInterval(interval);
                }
                bar.style.width = progress + '%';
            }, 150);
        }
    </script>
</body>
</html>
