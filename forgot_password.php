<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = "Bitte E-Mail Adresse eingeben.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
            $stmt->execute([$token, $expiry, $user['id']]);
            
            $reset_link = null;
            if (defined('APP_URL') && APP_URL) {
                $reset_link = rtrim(APP_URL, '/') . "/reset_password.php?token=" . urlencode($token);
            } else {
                // Fallback: nur erlauben, wenn Host-Header ein gültiger Hostname ist (kein injizierter Müll)
                $host = $_SERVER['HTTP_HOST'] ?? '';
                if (preg_match('/^[a-zA-Z0-9.\-]+(:[0-9]+)?$/', $host)) {
                    $base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $reset_link = $scheme . "://" . $host . $base_path . "/reset_password.php?token=" . urlencode($token);
                } else {
                    $error = "Konfigurationsfehler: APP_URL in config.php setzen.";
                }
            }

            if ($reset_link) {
                // Send Email
                $to = $email;
                $from = getSetting('footer_email', 'noreply@clipo.de');
                $subject = "Passwort zuruecksetzen - Clipo"; // ASCII only for subjects is safer
                $msg  = "Hallo " . $user['username'] . ",\n\n";
                $msg .= "Klicke auf den folgenden Link, um dein Passwort zurueckzusetzen:\n\n";
                $msg .= $reset_link . "\n\n";
                $msg .= "Dieser Link ist 1 Stunde gueltig.";

                $headers  = "From: " . $from . "\r\n";
                $headers .= "Reply-To: " . $from . "\r\n";
                $headers .= "Content-Type: text/plain; charset=utf-8\r\n";

                // The 5th parameter '-f' is crucial for many hosters like Mittwald
                if (mail($to, $subject, $msg, $headers, "-f " . $from)) {
                    $message = "Ein Link zum Zuruecksetzen wurde an deine E-Mail gesendet.";
                } else {
                    // Fallback for local dev or misconfigured mail()
                    $error = "E-Mail konnte nicht gesendet werden. (Lokales Setup?)\nLink: " . $reset_link;
                }
            }
        } else {
            // Standard security: dont reveal if email exists
            $message = "Sollte die Adresse im System sein, wurde ein Link versendet.";
        }
    }
}

$page_title = 'Clipo - Passwort vergessen';
include 'includes/header.php';
?>
    <div class="container" style="max-width: 500px; margin-top: 50px;">
        <div class="card">
            <h1>Passwort vergessen</h1>
            <p style="margin-bottom: 25px; color: #64748b;">Gib deine E-Mail Adresse ein. Wir senden dir einen Link zum Zurücksetzen.</p>

            <?php if ($message): ?><div class="alert alert-success"><?php echo nl2br(htmlspecialchars($message)); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger" style="white-space: pre-wrap;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>E-Mail Adresse</label>
                    <input type="email" name="email" required placeholder="name@beispiel.de">
                </div>
                <button type="submit" class="btn">Link anfordern</button>
                <div style="margin-top: 20px; text-align: center;">
                    <a href="login.php" style="color: #64748b; font-size: 0.9rem;">Zurück zum Login</a>
                </div>
            </form>
        </div>
    </div>
<?php include 'includes/footer.php'; ?>
