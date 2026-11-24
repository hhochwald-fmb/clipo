<?php
// Online-Update für Clipo (nach dem Vorbild von EasyWetter):
//  - "Nach Updates suchen" fragt das neueste GitHub-Release ab
//  - "Installieren" lädt es herunter (Release-Asset clipo-*.zip oder das Quellcode-ZIP des Tags)
//  - alternativ kann ein ZIP von Hand hochgeladen werden
//  - vorher wird ein Backup der Programmdateien in storage/backups angelegt
//  - config.php, uploads/ und storage/ werden nie überschrieben
//  - Datenbank-Migrationen laufen beim nächsten Seitenaufruf automatisch

define('CLIPO_ROOT', dirname(__DIR__));
define('CLIPO_DEFAULT_REPO', 'hhochwald-fmb/clipo');
define('CLIPO_UPDATE_MAX_ZIP', 40 * 1024 * 1024);

function clipoUpdateRepo() {
    $r = getSetting('update_repo', '') ?: CLIPO_DEFAULT_REPO;
    return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $r) ? $r : '';
}

function clipoIsGitCheckout() {
    return is_dir(CLIPO_ROOT . '/.git');
}

function clipoStorageDir($sub) {
    $d = CLIPO_ROOT . '/storage/' . $sub;
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    if (!is_dir($d) || !is_writable($d)) {
        throw new RuntimeException("Der Ordner storage/$sub ist nicht beschreibbar. Bitte Schreibrechte setzen (chmod 775).");
    }
    return $d;
}

function clipoHttpGet($url, $saveTo = null) {
    $allowedHosts = ['api.github.com', 'github.com', 'codeload.github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'];
    if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array(parse_url($url, PHP_URL_HOST), $allowedHosts, true)) {
        throw new RuntimeException('Unerlaubte Update-Adresse.');
    }
    $ua = 'Clipo-Updater/' . CLIPO_VERSION;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $fh = $saveTo ? fopen($saveTo, 'wb') : null;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json, application/octet-stream'],
        ]);
        if ($fh) {
            curl_setopt($ch, CURLOPT_FILE, $fh);
        } else {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($fh) {
            fclose($fh);
        }
        if ($res === false || $code >= 400) {
            if ($saveTo) {
                @unlink($saveTo);
            }
            if ($code === 404) {
                throw new RuntimeException('Nicht gefunden (HTTP 404) – gibt es im Repository schon ein veröffentlichtes Release?');
            }
            if ($code === 403) {
                throw new RuntimeException('GitHub hat die Anfrage abgelehnt (HTTP 403, evtl. zu viele Anfragen). Bitte später erneut versuchen.');
            }
            throw new RuntimeException('Verbindung fehlgeschlagen (' . ($err ?: 'HTTP ' . $code) . ').');
        }
        return $fh ? '' : (string) $res;
    }

    if (!ini_get('allow_url_fopen')) {
        throw new RuntimeException('Der Server kann keine Updates herunterladen (weder cURL noch allow_url_fopen verfügbar). Bitte das ZIP von Hand hochladen.');
    }
    $ctx = stream_context_create(['http' => [
        'header' => "User-Agent: $ua\r\nAccept: application/vnd.github+json\r\n",
        'timeout' => 120,
        'follow_location' => 1,
    ]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) {
        throw new RuntimeException('Download fehlgeschlagen.');
    }
    if ($saveTo) {
        file_put_contents($saveTo, $data);
        return '';
    }
    return $data;
}

/** @return array{version:string, name:string, notes:string, zip_url:string, newer:bool} */
function clipoCheckLatest() {
    $repo = clipoUpdateRepo();
    if ($repo === '') {
        throw new RuntimeException('Kein gültiges Update-Repository eingestellt.');
    }
    $json = json_decode(clipoHttpGet("https://api.github.com/repos/$repo/releases/latest"), true);
    if (!is_array($json) || empty($json['tag_name'])) {
        throw new RuntimeException('Keine Release-Informationen gefunden.');
    }
    $version = ltrim((string) $json['tag_name'], 'vV');

    // Bevorzugt ein angehängtes Paket clipo-x.y.z.zip, sonst das automatische Quellcode-ZIP des Tags
    $zip = (string) ($json['zipball_url'] ?? '');
    foreach ($json['assets'] ?? [] as $a) {
        if (preg_match('/^clipo-.*\.zip$/i', (string) ($a['name'] ?? ''))) {
            $zip = (string) $a['browser_download_url'];
            break;
        }
    }
    return [
        'version' => $version,
        'name' => (string) ($json['name'] ?? ''),
        'notes' => mb_substr((string) ($json['body'] ?? ''), 0, 4000),
        'zip_url' => $zip,
        'newer' => version_compare($version, CLIPO_VERSION, '>'),
    ];
}

function clipoDownloadLatest() {
    $info = clipoCheckLatest();
    if (!$info['newer']) {
        throw new RuntimeException('Es ist bereits die neueste Version installiert.');
    }
    if ($info['zip_url'] === '') {
        throw new RuntimeException('Das Release enthält kein Download-Paket.');
    }
    $target = clipoStorageDir('tmp') . '/update-' . bin2hex(random_bytes(4)) . '.zip';
    clipoHttpGet($info['zip_url'], $target);
    return $target;
}

/** Darf ein Update diese Datei (relativer Pfad) schreiben? */
function clipoUpdateAllowed($rel) {
    if ($rel === '' || strpos($rel, '..') !== false || strpos($rel, '\\') !== false || $rel[0] === '/' || strpos($rel, "\0") !== false) {
        return false;
    }
    // Programmseiten im Hauptordner – aber nie die Konfiguration
    if (preg_match('#^[A-Za-z0-9_-]+\.php$#', $rel)) {
        return $rel !== 'config.php';
    }
    $files = ['VERSION', 'README.md', 'INSTALLATION.md', 'ENTWICKLUNG.md', 'CHANGELOG.md', 'LICENSE',
        '.htaccess', '.gitignore', '.dockerignore', 'Dockerfile', 'docker-compose.yml', 'storage/.htaccess',
        'uploads/.htaccess', 'uploads/index.html'];
    if (in_array($rel, $files, true)) {
        return true;
    }
    foreach (['includes/', 'assets/'] as $dir) {
        if (strpos($rel, $dir) === 0) {
            return true;
        }
    }
    return false;
}

/** Prüft und installiert ein Update-ZIP. Gibt die neue Version zurück. */
function clipoApplyUpdateZip($zipPath) {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Die PHP-Erweiterung "zip" fehlt auf dem Server.');
    }
    if (!is_file($zipPath) || filesize($zipPath) > CLIPO_UPDATE_MAX_ZIP) {
        throw new RuntimeException('Update-Datei fehlt oder ist zu groß.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Die Datei ist kein gültiges ZIP-Archiv.');
    }
    try {
        // Wurzel des Pakets finden (Ordner mit VERSION und includes/db.php).
        // Beim Quellcode-ZIP von GitHub ist das z. B. "hhochwald-fmb-clipo-1a2b3c4/".
        $prefix = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string) $zip->getNameIndex($i);
            if (preg_match('#^(.*/)?VERSION$#', $n, $m)) {
                $p = isset($m[1]) ? $m[1] : '';
                if ($zip->locateName($p . 'includes/db.php') !== false && ($prefix === null || strlen($p) < strlen($prefix))) {
                    $prefix = $p;
                }
            }
        }
        if ($prefix === null) {
            throw new RuntimeException('Das ZIP ist kein Clipo-Paket (VERSION oder includes/ fehlen).');
        }
        $newVersion = trim((string) $zip->getFromName($prefix . 'VERSION'));
        if (!preg_match('/^\d+\.\d+(\.\d+)?([.-][0-9A-Za-z.-]+)?$/', $newVersion)) {
            throw new RuntimeException('Ungültige Versionsangabe im Paket.');
        }
        if (version_compare($newVersion, CLIPO_VERSION, '<')) {
            throw new RuntimeException("Das Paket ($newVersion) ist älter als die installierte Version (" . CLIPO_VERSION . ').');
        }

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string) $zip->getNameIndex($i);
            if (strpos($n, $prefix) !== 0 || substr($n, -1) === '/') {
                continue;
            }
            $rel = substr($n, strlen($prefix));
            if (clipoUpdateAllowed($rel)) {
                $entries[$i] = $rel;
            }
        }

        // Vollständigkeit prüfen, damit ein kaputtes Paket die Seite nicht lahmlegt
        $required = [
            'includes/db.php' => 'PDO',
            'includes/config_loader.php' => 'CLIPO_VERSION',
            'includes/auth.php' => 'function requireAdmin',
            'includes/updater.php' => 'function clipoApplyUpdateZip',
            'index.php' => 'includes/header.php',
            'admin.php' => 'requireAdmin',
            'setup.php' => 'CREATE TABLE',
        ];
        foreach ($required as $req => $marker) {
            $content = $zip->getFromName($prefix . $req);
            if ($content === false || strpos($content, $marker) === false) {
                throw new RuntimeException("Das Paket ist unvollständig oder beschädigt ($req fehlt) – nichts wurde verändert.");
            }
        }
        if (count($entries) < 15) {
            throw new RuntimeException('Das Paket enthält zu wenige Dateien – ist es wirklich ein vollständiges Clipo-Release?');
        }

        // Schreibrechte prüfen, bevor irgendetwas verändert wird
        foreach ($entries as $rel) {
            $target = CLIPO_ROOT . '/' . $rel;
            $dir = dirname($target);
            while (!is_dir($dir)) {
                $dir = dirname($dir);
            }
            if ((file_exists($target) && !is_writable($target)) || !is_writable($dir)) {
                throw new RuntimeException("Keine Schreibrechte für $rel – Update abgebrochen, nichts wurde verändert.");
            }
        }

        clipoBackupFiles(array_values($entries));

        foreach ($entries as $i => $rel) {
            $target = CLIPO_ROOT . '/' . $rel;
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0775, true);
            }
            $data = $zip->getFromIndex($i);
            if ($data === false || file_put_contents($target, $data, LOCK_EX) === false) {
                throw new RuntimeException("Fehler beim Schreiben von $rel. Ein Backup liegt in storage/backups.");
            }
        }
    } finally {
        $zip->close();
    }
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    return $newVersion;
}

function clipoBackupFiles(array $relPaths) {
    $file = clipoStorageDir('backups') . '/backup-' . CLIPO_VERSION . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Backup konnte nicht angelegt werden – Update abgebrochen.');
    }
    foreach ($relPaths as $rel) {
        if (is_file(CLIPO_ROOT . '/' . $rel)) {
            $zip->addFile(CLIPO_ROOT . '/' . $rel, $rel);
        }
    }
    if (!$zip->close()) {
        throw new RuntimeException('Backup konnte nicht gespeichert werden – Update abgebrochen.');
    }
}

function clipoBackups() {
    $files = glob(CLIPO_ROOT . '/storage/backups/backup-*.zip') ?: [];
    usort($files, function ($a, $b) { return filemtime($b) - filemtime($a); });
    return array_map(function ($f) {
        return ['name' => basename($f), 'size' => filesize($f)];
    }, $files);
}
