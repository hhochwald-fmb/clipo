<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_file = __DIR__ . '/config.php';

// Default values
$db_host = 'localhost';
$db_name = 'clipo';
$db_user = 'root';
$db_pass = 'root';
$admin_user = 'admin';
$message = '';
$error = '';

// Docker: Zugangsdaten kommen aus den Umgebungsvariablen (docker-compose.yml),
// config.php wird dann nicht geschrieben - sie ginge beim Neubau des Containers verloren.
$use_env = !file_exists($config_file) && getenv('DB_HOST') !== false;
if ($use_env) {
    $db_host = getenv('DB_HOST');
    $db_name = getenv('DB_NAME') ?: $db_name;
    $db_user = getenv('DB_USER') ?: $db_user;
    $db_pass = getenv('DB_PASS') ?: '';
}

if (file_exists($config_file)) {
    // Try to load existing config
    $content = file_get_contents($config_file);
    if (preg_match("/define\('DB_HOST', '(.*?)'\);/", $content, $matches)) $db_host = $matches[1];
    if (preg_match("/define\('DB_NAME', '(.*?)'\);/", $content, $matches)) $db_name = $matches[1];
    if (preg_match("/define\('DB_USER', '(.*?)'\);/", $content, $matches)) $db_user = $matches[1];
    if (preg_match("/define\('DB_PASS', '(.*?)'\);/", $content, $matches)) $db_pass = $matches[1];
    $message = "Hinweis: Bestehende Konfiguration geladen.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? $db_host;
    $db_name = $_POST['db_name'] ?? $db_name;
    $db_user = $_POST['db_user'] ?? $db_user;
    $db_pass = $_POST['db_pass'] ?? $db_pass;
    $admin_user = $_POST['admin_user'] ?? $admin_user;
    $admin_pass = $_POST['admin_pass'] ?? '';

    if (empty($db_name) || empty($db_user) || empty($admin_pass)) {
        $error = "Alle Felder müssen ausgefüllt werden.";
    } else {
        try {
            // Try connecting to DB
            $dsn = "mysql:host=$db_host;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");

            // Create Tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(255),
                password VARCHAR(255) NOT NULL,
                display_name VARCHAR(100),
                profile_image VARCHAR(255),
                role ENUM('admin', 'manager') DEFAULT 'manager',
                reset_token VARCHAR(100),
                reset_expiry DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");

            $pdo->exec("CREATE TABLE IF NOT EXISTS videos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                tags VARCHAR(255),
                upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_id INT,
                file_size BIGINT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");

            // Create settings table
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB");

            // Seed default settings
            $defaults = [
                'primary_color' => '#f39200',
                'secondary_color' => '#4a5c66',
                'videos_per_page' => '12',
                'footer_email' => 'hochwald@lmz-bw.de',
                'public_access' => '0',
                'site_name' => 'Name deiner Instanz',
                'impressum_content' => '<h1>Impressum</h1><p>Bearbeite diesen Inhalt im Admin-Bereich.</p>'
            ];
            foreach ($defaults as $key => $val) {
                $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
            }

            // Update user password if admin already exists, or create new
            $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$admin_user]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE username = ?");
                $stmt->execute([$hashed_pass, $admin_user]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
                $stmt->execute([$admin_user, $hashed_pass]);
            }

            // Create uploads folder if not exists
            $uploads_dir = __DIR__ . '/uploads';
            if (!file_exists($uploads_dir)) {
                if (!mkdir($uploads_dir, 0775, true)) {
                    $error = "Konnte Ordner 'uploads/' nicht erstellen. Bitte erstelle ihn manuell per FTP und gib ihm Schreibrechte (775/777).";
                }
            }

            // Write config.php (nicht bei Docker, dort gelten die Umgebungsvariablen)
            $config_content = "<?php\n";
            $config_content .= "define('DB_HOST', " . var_export($db_host, true) . ");\n";
            $config_content .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
            $config_content .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
            $config_content .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n";
            $config_content .= "define('APP_NAME', 'Clipo');\n";
            $config_content .= "define('UPLOAD_DIR', __DIR__ . '/uploads/');\n";
            $config_content .= "define('DB_VERSION', '1.5');\n";

            if (!$use_env && !file_put_contents($config_file, $config_content)) {
                $error = "Konnte config.php nicht schreiben. Bitte prüfe die Schreibrechte im Clipo-Hauptverzeichnis.";
            }

            if (!$error) {
                $message = "Installation erfolgreich! <a href='index.php'>Zur App</a>";
            }
            
        } catch (PDOException $e) {
            $error = "Datenbank-Fehler: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clipo - Setup</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <a href="#" class="logo">Clipo<span>.</span></a>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <h1>Clipo Setup</h1>
            <p>Bitte geben Sie Ihre Datenbank-Informationen an.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label>MySQL Host</label>
                        <input type="text" name="db_host" value="<?php echo htmlspecialchars($db_host); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Datenbank Name</label>
                        <input type="text" name="db_name" value="<?php echo htmlspecialchars($db_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>DB Benutzer</label>
                        <input type="text" name="db_user" value="<?php echo htmlspecialchars($db_user); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>DB Passwort</label>
                        <input type="password" name="db_pass" value="<?php echo htmlspecialchars($db_pass); ?>">
                    </div>
                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                    <h3>Administrator Konto</h3>
                    <div class="form-group">
                        <label>Admin Benutzername</label>
                        <input type="text" name="admin_user" value="<?php echo htmlspecialchars($admin_user); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Admin Passwort</label>
                        <input type="password" name="admin_pass" required>
                    </div>
                    <button type="submit" class="btn">Installieren / Aktualisieren</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 Clipo - Projekt des <a href="https://www.smz-stuttgart.de" target="_blank">SMZ Stuttgart</a></p>
        <p>Kontakt: <a href="mailto:hochwald@lmz-bw.de">hochwald@lmz-bw.de</a> | <a href="impressum.php">Impressum</a></p>
    </footer>
</body>
</html>
