<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAdmin();
require_once 'includes/updater.php';

$active_tab = $_GET['tab'] ?? 'users';
$message = '';
$error = '';

// Meldungen aus einer Weiterleitung (z. B. nach einem Update)
if (!empty($_SESSION['flash'])) {
    if ($_SESSION['flash']['type'] === 'success') {
        $message = $_SESSION['flash']['text'];
    } else {
        $error = $_SESSION['flash']['text'];
    }
    unset($_SESSION['flash']);
}

// ---------- Online-Updates ----------
$latest = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_update'])) {
    checkCsrf();
    $active_tab = 'updates';
    try {
        $latest = clipoCheckLatest();
    } catch (Throwable $e) {
        $error = 'Update-Prüfung fehlgeschlagen: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_update_repo'])) {
    checkCsrf();
    $active_tab = 'updates';
    $repo = trim($_POST['update_repo'] ?? '');
    if ($repo !== '' && !preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
        $error = 'Repository bitte im Format „besitzer/name“ angeben.';
    } else {
        setSetting('update_repo', $repo);
        $message = 'Update-Repository gespeichert.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['install_update']) || isset($_POST['upload_update']))) {
    checkCsrf();
    $zipPath = null;
    $isDownload = isset($_POST['install_update']);
    try {
        if ($isDownload) {
            $zipPath = clipoDownloadLatest();
        } else {
            $f = $_FILES['package'] ?? null;
            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                throw new RuntimeException('Bitte eine Update-Datei (ZIP) auswählen. Der Server erlaubt Uploads bis ' . ini_get('upload_max_filesize') . '.');
            }
            $zipPath = $f['tmp_name'];
        }
        $version = clipoApplyUpdateZip($zipPath);
        $_SESSION['flash'] = ['type' => 'success', 'text' => "Update auf Version $version installiert. Datenbank-Änderungen wurden automatisch angewendet."];
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Update fehlgeschlagen: ' . $e->getMessage()];
    } finally {
        if ($isDownload && $zipPath) {
            @unlink($zipPath);
        }
    }
    // Der nächste Aufruf läuft mit dem neuen Code und führt die Migrationen automatisch aus
    header('Location: admin.php?tab=updates');
    exit;
}

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    checkCsrf();
    if (isset($_POST['primary_color'])) setSetting('primary_color', clipoValidColor($_POST['primary_color'], '#f39200'));
    if (isset($_POST['secondary_color'])) setSetting('secondary_color', clipoValidColor($_POST['secondary_color'], '#4a5c66'));
    if (isset($_POST['videos_per_page'])) setSetting('videos_per_page', (string) min(200, max(1, (int) $_POST['videos_per_page'])));
    if (isset($_POST['footer_email'])) {
        if ($_POST['footer_email'] === '' || filter_var($_POST['footer_email'], FILTER_VALIDATE_EMAIL)) {
            setSetting('footer_email', $_POST['footer_email']);
        } else {
            $error = "Die Kontakt-E-Mail ist ungültig und wurde nicht gespeichert.";
        }
    }
    if (isset($_POST['app_url'])) {
        $url = rtrim(trim($_POST['app_url']), '/');
        if ($url === '' || preg_match('#^https?://[A-Za-z0-9.\-]+(:[0-9]+)?(/[A-Za-z0-9._~\-/]*)?$#', $url)) {
            setSetting('app_url', $url);
        } else {
            $error = "Die Adresse der Instanz ist ungültig (Beispiel: https://clipo.meine-schule.de).";
        }
    }
    if (isset($_POST['site_name'])) setSetting('site_name', $_POST['site_name']);
    if (isset($_POST['public_access'])) setSetting('public_access', $_POST['public_access'] === '1' ? '1' : '0');
    if (isset($_POST['impressum_content'])) setSetting('impressum_content', sanitizeHtml($_POST['impressum_content']));
    if (!$error) {
        $message = "Einstellungen wurden gespeichert.";
    }
}

// Adresse dieser Instanz einmalig merken (für Links in E-Mails). Der Admin ist angemeldet,
// die Adresse seiner Anfrage ist also vertrauenswürdig.
if (!(defined('APP_URL') && APP_URL) && getSetting('app_url', '') === '') {
    setSetting('app_url', clipoRequestBaseUrl());
}

// Handle System Cleaning (Orphans)
$orphans = [];
if (is_dir(UPLOAD_DIR)) {
    $all_files = array_diff(scandir(UPLOAD_DIR), array('.', '..', '.DS_Store', 'index.php', 'index.html', '.htaccess', '.gitkeep'));
    $stmt = $pdo->query("SELECT filename FROM videos");
    $db_files = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Filter: Ignore avatars and hidden stuff
    foreach ($all_files as $file) {
        if (strpos($file, 'avatar_') === 0) continue; // Skip avatars
        if (!in_array($file, $db_files)) {
            $orphans[] = $file;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clean_orphans'])) {
    checkCsrf();
    $deleted_count = 0;
    foreach ($orphans as $orphan) {
        $path = UPLOAD_DIR . $orphan;
        if (is_file($path)) {
            unlink($path);
            $deleted_count++;
        }
    }
    $message = "$deleted_count verwaiste Dateien wurden gelöscht.";
    $orphans = []; // Reset after delete
    $active_tab = 'maintenance';
}

// Reset Colors (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_colors'])) {
    checkCsrf();
    setSetting('primary_color', '#f39200');
    setSetting('secondary_color', '#4a5c66');
    $message = "Farben wurden auf Standard zurückgesetzt.";
}

// Handle User Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    checkCsrf();
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'manager';
    if ($username && strlen($password) < 8) {
        $error = "Das Passwort muss mindestens 8 Zeichen lang sein.";
    } elseif ($username && $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashed, $role]);
            $message = "Benutzer $username wurde angelegt.";
        } catch (PDOException $e) {
            $error = $e->getCode() == 23000 ? "Der Benutzername $username ist schon vergeben." : "Benutzer konnte nicht angelegt werden.";
            error_log('Clipo: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    checkCsrf();
    $id = (int)$_POST['delete_user'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Benutzer wurde gelöscht.";
    }
}

// Fetch Users
$stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY username ASC");
$users = $stmt->fetchAll();

// Video Stats
$stmt = $pdo->query("SELECT COUNT(*) as count, SUM(file_size) as total_size FROM videos");
$video_stats = $stmt->fetch();

$server_info = [
    'Gesamtanzahl Videos' => $video_stats['count'] . ' Videos',
    'Speicherplatz (Videos)' => formatBytes($video_stats['total_size'] ?? 0),
    '---' => '---',
    'PHP Version' => PHP_VERSION,
    'Upload Max Filesize' => ini_get('upload_max_filesize'),
    'Post Max Size' => ini_get('post_max_size'),
    'Memory Limit' => ini_get('memory_limit'),
    'Max Execution Time' => ini_get('max_execution_time') . 's',
    'Display Errors' => ini_get('display_errors'),
];

$page_title = 'Clipo - Admin Bereich';
include 'includes/header.php';
?>
    <style>
        .admin-nav { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .admin-nav a { padding: 10px 20px; text-decoration: none; color: #666; font-weight: 600; border-radius: 8px; transition: all 0.2s; }
        .admin-nav a.active { background: var(--primary-color); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table th, .info-table td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
        .info-table th { color: #64748b; font-size: 0.9rem; }
        
        /* Local RTE Styles */
        .rte-toolbar { background: #f8fafc; border: 2px solid #e2e8f0; border-bottom: none; border-radius: 12px 12px 0 0; padding: 10px; display: flex; gap: 5px; }
        .rte-btn { padding: 5px 10px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }
        .rte-btn:hover { background: #f1f5f9; }
        #editor { min-height: 300px; border: 2px solid #e2e8f0; border-radius: 0 0 12px 12px; padding: 20px; background: white; overflow-y: auto; }
        #editor:focus { outline: none; border-color: var(--primary-color); }
    </style>

    <div class="container" style="max-width: 1000px;">
        <h1>Admin Bereich</h1>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="admin-nav">
            <a href="?tab=users" class="<?php echo $active_tab == 'users' ? 'active' : ''; ?>">Benutzer</a>
            <a href="?tab=design" class="<?php echo $active_tab == 'design' ? 'active' : ''; ?>">Design & CI</a>
            <a href="?tab=content" class="<?php echo $active_tab == 'content' ? 'active' : ''; ?>">Inhalt</a>
            <a href="?tab=system" class="<?php echo $active_tab == 'system' ? 'active' : ''; ?>">System-Info</a>
            <a href="?tab=maintenance" class="<?php echo $active_tab == 'maintenance' ? 'active' : ''; ?>">Wartung</a>
            <a href="?tab=updates" class="<?php echo $active_tab == 'updates' ? 'active' : ''; ?>">Updates</a>
        </div>

        <!-- Users Tab -->
        <div class="tab-content <?php echo $active_tab == 'users' ? 'active' : ''; ?>">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                <div class="card">
                    <h3>Neuer Benutzer</h3>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="add_user" value="1">
                        <div class="form-group"><label>Benutzername</label><input type="text" name="username" required></div>
                        <div class="form-group"><label>E-Mail Adresse</label><input type="email" name="email" placeholder="lehrer@schule.de"></div>
                        <div class="form-group"><label>Passwort</label><input type="password" name="password" required></div>
                        <div class="form-group"><label>Rolle</label>
                            <select name="role">
                            <option value="manager">Manager</option>
                            <option value="admin">Administrator</option>
                        </select>
                        </div>
                        <button type="submit" class="btn">Anlegen</button>
                    </form>
                </div>
                <div class="card">
                    <h3>Benutzerliste</h3>
                    <table class="info-table">
                        <thead><tr><th>User</th><th>E-Mail</th><th>Rolle</th><th>Aktion</th></tr></thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                <td><span style="font-size: 0.85rem; color: #64748b;"><?php echo htmlspecialchars($u['email'] ?? '-'); ?></span></td>
                                <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span></td>
                                <td><?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Sicher?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="delete_user" value="<?php echo $u['id']; ?>">
                                        <button type="submit" style="background:none; border:none; color:red; font-size: 0.8rem; cursor:pointer; padding:0;">Löschen</button>
                                    </form>
                                <?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Design Tab -->
        <div class="tab-content <?php echo $active_tab == 'design' ? 'active' : ''; ?>">
            <div class="card">
                <h3>Design Anpassung</h3>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="update_settings" value="1">

                    <div class="form-group">
                        <label>Öffentlicher Zugriff</label>
                        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 10px;">Darf die Videoübersicht ohne Anmeldung gesehen werden?</p>
                        <div style="display: flex; gap: 20px;">
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; color: inherit;">
                                <input type="radio" name="public_access" value="1" <?php echo getSetting('public_access') == '1' ? 'checked' : ''; ?>> Ja
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; color: inherit;">
                                <input type="radio" name="public_access" value="0" <?php echo getSetting('public_access') == '0' ? 'checked' : ''; ?>> Nein (Login Pflicht)
                            </label>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label>Name der Instanz (z.B. Schulname)</label>
                        <input type="text" name="site_name" value="<?php echo htmlspecialchars(getSetting('site_name')); ?>" placeholder="Meine Schule">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px;">
                        <div class="form-group">
                            <label>Primärfarbe</label>
                            <input type="color" name="primary_color" value="<?php echo clipoValidColor(getSetting('primary_color'), '#f39200'); ?>" style="height: 50px;">
                        </div>
                        <div class="form-group">
                            <label>Sekundärfarbe</label>
                            <input type="color" name="secondary_color" value="<?php echo clipoValidColor(getSetting('secondary_color'), '#4a5c66'); ?>" style="height: 50px;">
                        </div>
                        <div class="form-group">
                            <label>Videos pro Seite</label>
                            <select name="videos_per_page">
                                <?php 
                                $current = getSetting('videos_per_page', '12');
                                $options = [3, 6, 9, 12, 15, 18, 21, 24, 30, 36, 45, 60, 90];
                                foreach ($options as $opt) {
                                    $sel = ($current == $opt) ? 'selected' : '';
                                    echo "<option value='$opt' $sel>$opt Videos</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn">Speichern</button>
                    </div>
                </form>
                <form method="POST" style="margin-top: 10px;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="reset_colors" value="1">
                    <button type="submit" class="btn btn-secondary">Reset</button>
                </form>
            </div>
        </div>

        <!-- Content Tab -->
        <div class="tab-content <?php echo $active_tab == 'content' ? 'active' : ''; ?>">
            <div class="card">
                <h3>Inhalt & Kontakt</h3>
                <form method="POST" id="contentForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="update_settings" value="1">
                    <div class="form-group">
                        <label>Kontakt E-Mail</label>
                        <input type="email" name="footer_email" value="<?php echo htmlspecialchars(getSetting('footer_email')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Adresse dieser Clipo-Instanz</label>
                        <?php if (defined('APP_URL') && APP_URL): ?>
                            <input type="text" value="<?php echo htmlspecialchars(APP_URL); ?>" disabled style="background: #f1f5f9;">
                            <p style="font-size: 0.85rem; color: #64748b; margin-top: 5px;">Wird in der config.php festgelegt (APP_URL).</p>
                        <?php else: ?>
                            <input type="url" name="app_url" value="<?php echo htmlspecialchars(getSetting('app_url')); ?>" placeholder="https://clipo.meine-schule.de">
                            <p style="font-size: 0.85rem; color: #64748b; margin-top: 5px;">Wird für die Links in „Passwort vergessen“-Mails verwendet. Beim ersten Öffnen des Admin-Bereichs automatisch eingetragen.</p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Impressum (RTE)</label>
                        <div class="rte-toolbar">
                            <button type="button" class="rte-btn" onclick="execCmd('bold')"><b>B</b></button>
                            <button type="button" class="rte-btn" onclick="execCmd('italic')"><i>I</i></button>
                            <button type="button" class="rte-btn" onclick="execCmd('underline')"><u>U</u></button>
                            <button type="button" class="rte-btn" onclick="execCmd('insertUnorderedList')">• Liste</button>
                            <button type="button" class="rte-btn" onclick="execCmd('formatBlock', 'h2')">H2</button>
                            <button type="button" class="rte-btn" onclick="execCmd('formatBlock', 'p')">P</button>
                            <button type="button" class="rte-btn" onclick="toggleSource()" id="sourceBtn" style="margin-left: auto; background: #eee; color: #333;">&lt;/&gt; Code</button>
                        </div>
                        <div id="editor" contenteditable="true" style="min-height: 300px; padding: 20px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px; background: white; overflow-y: auto;"><?php echo sanitizeHtml(getSetting('impressum_content')); ?></div>
                        <textarea id="sourceEditor" style="display: none; width: 100%; min-height: 300px; padding: 20px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px; font-family: monospace; font-size: 13px;"></textarea>
                        <input type="hidden" name="impressum_content" id="impressum_input">
                    </div>
                    <button type="submit" class="btn">Speichern</button>
                </form>
            </div>
        </div>

        <!-- System Tab -->
        <div class="tab-content <?php echo $active_tab == 'system' ? 'active' : ''; ?>">
            <div class="card">
                <h3>Server Info</h3>
                <table class="info-table">
                    <?php foreach ($server_info as $label => $val): ?>
                    <tr><td><strong><?php echo $label; ?></strong></td><td><?php echo $val; ?></td></tr>
                    <?php endforeach; ?>
                </table>
                
                <div style="margin-top: 30px; background: #fdf2f2; border-radius: 12px; padding: 20px; border: 1px solid #fee2e2;">
                    <h3 style="color: #991b1b; margin-top: 0;">Empfohlene Einstellungen</h3>
                    <p style="font-size: 0.95rem; margin-bottom: 10px;">Damit große Video-Uploads reibungslos funktionieren, empfehlen wir folgende Werte in deiner <code>php.ini</code>:</p>
                    <ul style="font-size: 0.9rem; padding-left: 20px; color: #475569;">
                        <li style="margin-bottom: 5px;"><strong>upload_max_filesize:</strong> min. 500M (je nach Videogröße)</li>
                        <li style="margin-bottom: 5px;"><strong>post_max_size:</strong> Sollte etwas größer sein als das Upload-Limit (z.B. 510M)</li>
                        <li style="margin-bottom: 5px;"><strong>memory_limit:</strong> 256M oder höher</li>
                        <li style="margin-bottom: 5px;"><strong>max_execution_time:</strong> 300 oder höher</li>
                    </ul>
                </div>

                <div style="margin-top: 30px; border: 2px dashed #e2e8f0; border-radius: 12px; padding: 30px; text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 10px;">⚙️</div>
                    <h3 style="margin-top: 0;">Datenbank & System</h3>
                    <p style="font-size: 0.95rem; color: #64748b; margin-bottom: 20px;">Neue Clipo-Versionen installierst du im Reiter <a href="?tab=updates">Updates</a>. Die Datenbank wird danach automatisch angepasst – von Hand nur nötig, wenn dabei etwas schiefging.</p>
                    <a href="update.php" class="btn btn-secondary">Datenbank-Migration von Hand ausführen</a>
                </div>
            </div>
        </div>

        <!-- Maintenance Tab -->
        <div class="tab-content <?php echo $active_tab == 'maintenance' ? 'active' : ''; ?>">
            <div class="card">
                <h2>System-Wartung & Bereinigung</h2>
                <p style="color: #64748b; margin-bottom: 25px;">
                    Hier kannst du verwaiste Dateien finden, die auf dem Server liegen, aber nicht mehr in der Datenbank verknüpft sind (z.B. durch abgebrochene Uploads).
                </p>

                <?php if (empty($orphans)): ?>
                    <div style="background: #f0fdf4; color: #16a34a; padding: 20px; border-radius: 12px; border: 1px solid #bbf7d0; display: flex; align-items: center; gap: 15px;">
                        <span style="font-size: 24px;">✨</span>
                        <strong>Alles sauber!</strong> Es wurden keine verwaisten Dateien in deinem Upload-Ordner gefunden.
                    </div>
                <?php else: ?>
                    <div style="background: #fff7ed; color: #c2410c; padding: 20px; border-radius: 12px; border: 1px solid #fed7aa; margin-bottom: 25px;">
                        <span style="font-size: 24px;">🧹</span>
                        <strong>Achtung:</strong> Es wurden <?php echo count($orphans); ?> Datei(en) im Upload-Ordner gefunden, die zu keinem Video-Eintrag gehören.
                    </div>

                    <table class="info-table">
                        <thead>
                            <tr><th>Dateiname</th><th>Größe</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orphans as $orphan): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($orphan); ?></code></td>
                                    <td><?php echo round(filesize(UPLOAD_DIR . $orphan) / 1024 / 1024, 2); ?> MB</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <form method="POST" style="margin-top: 30px;" onsubmit="return confirm('Möchtest du diese verwaisten Dateien wirklich dauerhaft vom Server löschen?')">
                        <?php echo csrfField(); ?>
                        <button type="submit" name="clean_orphans" class="btn btn-danger">System jetzt bereinigen</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Updates Tab -->
        <?php
        $update_repo = clipoUpdateRepo();
        $backups = clipoBackups();
        $zip_ok = class_exists('ZipArchive');
        ?>
        <div class="tab-content <?php echo $active_tab == 'updates' ? 'active' : ''; ?>">
            <div style="display: grid; grid-template-columns: 3fr 2fr; gap: 30px;">
                <div class="card">
                    <h2 style="margin-top: 0;">Update einspielen</h2>
                    <p style="color: #64748b;">Installierte Version: <strong>v<?php echo htmlspecialchars(CLIPO_VERSION); ?></strong></p>

                    <?php if (clipoIsGitCheckout()): ?>
                        <div class="alert alert-info" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;">
                            Diese Installation ist ein Git-Checkout. Aktualisiere sie am besten auf der Kommandozeile mit <code>git pull</code>
                            (bei Docker danach <code>docker compose up -d --build</code>).
                        </div>
                    <?php endif; ?>

                    <?php if (!$zip_ok): ?>
                        <div class="alert alert-danger">Die PHP-Erweiterung „zip“ fehlt auf dem Server – Online-Updates sind nicht möglich. Bitte die Dateien per FTP einspielen.</div>
                    <?php endif; ?>

                    <p>Online-Updates aus <code><?php echo htmlspecialchars($update_repo ?: '–'); ?></code></p>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <button type="submit" name="check_update" value="1" class="btn btn-secondary">🔄 Nach Updates suchen</button>
                    </form>

                    <?php if ($latest): ?>
                        <?php if ($latest['newer']): ?>
                            <div class="alert alert-success" style="margin-top: 20px;">
                                <strong>Version <?php echo htmlspecialchars($latest['version']); ?> ist verfügbar.</strong>
                                <?php if ($latest['name']): ?><div style="margin-top: 4px;"><?php echo htmlspecialchars($latest['name']); ?></div><?php endif; ?>
                                <?php if ($latest['notes']): ?>
                                    <pre style="white-space: pre-wrap; font-size: 0.85rem; margin: 10px 0; font-family: inherit;"><?php echo htmlspecialchars($latest['notes']); ?></pre>
                                <?php endif; ?>
                                <?php if ($zip_ok): ?>
                                    <form method="POST" style="margin-top: 10px;" onsubmit="return clipoStartUpdate(this, 'Update auf Version <?php echo htmlspecialchars($latest['version'], ENT_QUOTES); ?> jetzt installieren? Vorher wird automatisch ein Backup der Programmdateien angelegt.');">
                                        <?php echo csrfField(); ?>
                                        <button type="submit" name="install_update" value="1" class="btn">⬇️ Jetzt installieren</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success" style="margin-top: 20px;">
                                ✅ Du hast die neueste Version (v<?php echo htmlspecialchars($latest['version']); ?>).
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

                    <h3>Update-Paket hochladen (ZIP)</h3>
                    <form method="POST" enctype="multipart/form-data" onsubmit="return clipoStartUpdate(this, 'Update-Paket jetzt installieren? Vorher wird automatisch ein Backup der Programmdateien angelegt.');">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <input type="file" name="package" accept=".zip" required>
                            <p style="font-size: 0.85rem; color: #64748b; margin-top: 8px;">
                                Ein Clipo-Release als ZIP (z. B. von GitHub „Source code (zip)“).
                                Konfiguration, Datenbank und Videos bleiben erhalten. Max. Upload-Größe des Servers: <?php echo htmlspecialchars(ini_get('upload_max_filesize')); ?>
                            </p>
                        </div>
                        <button type="submit" name="upload_update" value="1" class="btn" <?php echo $zip_ok ? '' : 'disabled'; ?>>⬆️ Hochladen &amp; installieren</button>
                    </form>

                    <?php if ($backups): ?>
                        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                        <h3>Backups</h3>
                        <ul style="font-size: 0.9rem; color: #64748b; padding-left: 20px;">
                            <?php foreach (array_slice($backups, 0, 5) as $b): ?>
                                <li><?php echo htmlspecialchars($b['name']); ?> (<?php echo formatBytes($b['size'], 0); ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                        <p style="font-size: 0.85rem; color: #64748b;">Liegen im Ordner <code>storage/backups/</code>.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3 style="margin-top: 0;">Einstellungen</h3>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label>GitHub-Repository für Online-Updates</label>
                            <input type="text" name="update_repo" value="<?php echo htmlspecialchars(getSetting('update_repo', '')); ?>" placeholder="<?php echo CLIPO_DEFAULT_REPO; ?>">
                            <p style="font-size: 0.85rem; color: #64748b; margin-top: 8px;">
                                Leer lassen = offizielles Repository (<?php echo CLIPO_DEFAULT_REPO; ?>). Nur ändern, wenn du eine eigene Variante pflegst.
                            </p>
                        </div>
                        <button type="submit" name="save_update_repo" value="1" class="btn">Speichern</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function clipoStartUpdate(form, question) {
            if (!confirm(question)) return false;
            var btn = form.querySelector('button[type=submit]');
            // Button-Wert trotzdem mitschicken, obwohl er gleich deaktiviert wird
            var hidden = document.createElement('input');
            hidden.type = 'hidden'; hidden.name = btn.name; hidden.value = btn.value;
            form.appendChild(hidden);
            btn.disabled = true;
            btn.textContent = 'Update läuft … bitte warten';
            return true;
        }

        function execCmd(command, value = null) {
            document.execCommand(command, false, value);
        }

        let isSource = false;
        const editor = document.getElementById('editor');
        const sourceEditor = document.getElementById('sourceEditor');
        const sourceBtn = document.getElementById('sourceBtn');

        function toggleSource() {
            if (!isSource) {
                sourceEditor.value = editor.innerHTML;
                editor.style.display = 'none';
                sourceEditor.style.display = 'block';
                sourceBtn.style.background = 'var(--primary-color)';
                sourceBtn.style.color = 'white';
            } else {
                editor.innerHTML = sourceEditor.value;
                sourceEditor.style.display = 'none';
                editor.style.display = 'block';
                sourceBtn.style.background = '#eee';
                sourceBtn.style.color = '#333';
            }
            isSource = !isSource;
        }

        document.getElementById('contentForm').onsubmit = function() {
            if (isSource) {
                document.getElementById('impressum_input').value = sourceEditor.value;
            } else {
                document.getElementById('impressum_input').value = editor.innerHTML;
            }
        };
    </script>

<?php include 'includes/footer.php'; ?>
