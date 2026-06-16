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

function csrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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
