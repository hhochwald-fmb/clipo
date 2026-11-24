<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAdmin();

// Die Migrationen laufen nach einem Update normalerweise automatisch (includes/migrations.php).
// Diese Seite erlaubt, sie bei Bedarf von Hand anzustoßen.
$current_version = getSetting('db_version', '1.5');
$target_version = CLIPO_VERSION;

$logs = [];
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_update'])) {
    checkCsrf();
    try {
        $logs = clipoRunMigrations($pdo);
        if (!$logs) {
            $logs[] = "Die Datenbank war bereits aktuell.";
        }
        $logs[] = "Datenbank-Stand: v" . CLIPO_VERSION;
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
            <p>Aktuelle Version: <strong>v<?php echo htmlspecialchars($current_version); ?></strong> &rarr; Ziel: <strong>v<?php echo htmlspecialchars($target_version); ?></strong></p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <div id="start-view" style="text-align: center; padding: 40px 0;">
                    <p style="margin-bottom: 30px;">Das System prüft alle Datenbank-Tabellen und skripte auf Aktualität.</p>
                    <form method="POST" onsubmit="startUpdate();">
                        <?php echo csrfField(); ?>
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
