<?php
function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    return $stmt->execute([$key, $value]);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function canEditVideo($video_id) {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return false;
    if ($_SESSION['user_role'] === 'admin') return true;

    $stmt = $pdo->prepare("SELECT user_id FROM videos WHERE id = ?");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();
    return $video && ($video['user_id'] == $_SESSION['user_id']);
}

// Basis-Adresse der Instanz (z. B. https://clipo.schule.de/videos), ohne Schrägstrich am Ende.
// Reihenfolge: APP_URL aus der Konfiguration, dann die gespeicherte Adresse (wird beim Setup
// und im Admin-Bereich festgehalten), zuletzt aus der Anfrage abgeleitet.
// $trusted_only = true: nie aus der Anfrage ableiten (für Links in E-Mails, sonst kann ein
// Angreifer per gefälschtem Host-Header Reset-Links auf seine eigene Domain umbiegen).
function clipoBaseUrl($trusted_only = false) {
    if (defined('APP_URL') && APP_URL) {
        return rtrim(APP_URL, '/');
    }
    $saved = getSetting('app_url', '');
    if ($saved) {
        return rtrim($saved, '/');
    }
    return $trusted_only ? null : clipoRequestBaseUrl();
}

function clipoRequestBaseUrl() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-]+(:[0-9]+)?$/', $host)) {
        $host = 'localhost';
    }
    // SCRIPT_NAME statt PHP_SELF: PHP_SELF enthält angehängte Pfade (index.php/<schadcode>)
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $path;
}

// Nur einfache Dateinamen direkt im Upload-Ordner zulassen (kein ../, keine Unterordner)
function clipoSafeUploadName($name) {
    return is_string($name) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) && strpos($name, '..') === false;
}

function clipoValidColor($value, $default) {
    return (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) ? $value : $default;
}

// Schutz vor Passwort-Raten: Fehlversuche pro Schlüssel (z. B. Benutzername) und IP zählen
function clipoThrottled($key, $max_per_key = 5, $max_per_ip = 20, $minutes = 15) {
    global $pdo;
    try {
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY");
        $stmt = $pdo->prepare("SELECT
                SUM(attempt_key = ?) AS by_key,
                SUM(ip = ?) AS by_ip
            FROM login_attempts WHERE attempted_at > NOW() - INTERVAL $minutes MINUTE AND (attempt_key = ? OR ip = ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt->execute([$key, $ip, $key, $ip]);
        $row = $stmt->fetch();
        return ((int) $row['by_key'] >= $max_per_key) || ((int) $row['by_ip'] >= $max_per_ip);
    } catch (PDOException $e) {
        return false;
    }
}

function clipoRecordAttempt($key) {
    global $pdo;
    try {
        $pdo->prepare("INSERT INTO login_attempts (attempt_key, ip, attempted_at) VALUES (?, ?, NOW())")
            ->execute([mb_substr($key, 0, 190), $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (PDOException $e) {
    }
}

function clipoClearAttempts($key) {
    global $pdo;
    try {
        $pdo->prepare("DELETE FROM login_attempts WHERE attempt_key = ?")->execute([$key]);
    } catch (PDOException $e) {
    }
}

function csrfToken() {
    require_once __DIR__ . '/session.php';
    clipoStartSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function sanitizeHtml($html) {
    if ($html === null || $html === '') return '';

    $allowed_tags = ['p','br','strong','b','em','i','u','h1','h2','h3','h4','h5','h6',
                     'ul','ol','li','blockquote','pre','code','hr','span','div','a'];
    $allowed_attrs = ['href','title']; // nur auf <a>

    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    // Wrapper sichert UTF-8, body-extraction filtert <html>/<body> raus
    $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>',
                   LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//*') as $node) {
        $tag = strtolower($node->nodeName);
        if (!in_array($tag, $allowed_tags, true)) {
            // Tag entfernen, Kinder behalten
            while ($node->firstChild) {
                $node->parentNode->insertBefore($node->firstChild, $node);
            }
            $node->parentNode->removeChild($node);
            continue;
        }
        // Attribute prüfen
        if ($node->hasAttributes()) {
            $remove = [];
            foreach ($node->attributes as $attr) {
                $name = strtolower($attr->nodeName);
                $val  = $attr->nodeValue;
                if (!in_array($name, $allowed_attrs, true)) {
                    $remove[] = $attr->nodeName;
                    continue;
                }
                if ($name === 'href') {
                    $trim = trim($val);
                    // nur http(s), mailto: und relative URLs zulassen
                    if (!preg_match('#^(https?://|mailto:|/|\#|[a-z0-9._-]+(\?|$))#i', $trim)) {
                        $remove[] = $attr->nodeName;
                    }
                }
            }
            foreach ($remove as $r) $node->removeAttribute($r);
        }
    }

    $out = '';
    $wrapper = $doc->getElementsByTagName('div')->item(0);
    if ($wrapper) {
        foreach ($wrapper->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
    }
    return $out;
}

function checkCsrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_GET['ajax']))) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF-Token ungültig. Bitte Seite neu laden.']);
        } else {
            die('CSRF-Token ungültig. Bitte Seite neu laden.');
        }
        exit;
    }
}
