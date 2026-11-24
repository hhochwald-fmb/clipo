<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $email = $_POST['email'] ?? '';
    
    $throttle_key = 'reset:' . mb_strtolower($email);
    if (empty($email)) {
        $error = "Bitte E-Mail Adresse eingeben.";
    } elseif (clipoThrottled($throttle_key, 3, 10, 60)) {
        // Gleiche Meldung wie sonst - kein Hinweis, ob die Adresse existiert
        $message = "Sollte die Adresse im System sein, wurde ein Link versendet.";
    } else {
        clipoRecordAttempt($throttle_key);
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Link nur mit der hinterlegten Adresse der Instanz bauen, nie aus dem Host-Header
        // der Anfrage - sonst könnte ein Angreifer den Link auf seine eigene Domain umbiegen.
        $base = clipoBaseUrl(true);
        if ($user && !$base) {
            error_log("Clipo: Passwort-Reset nicht möglich - Adresse der Instanz unbekannt. Einmal als Admin den Admin-Bereich öffnen oder APP_URL in config.php setzen.");
        }

        if ($user && $base) {
            $token = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = NOW() + INTERVAL 1 HOUR WHERE id = ?");
            $stmt->execute([$token, $user['id']]);

            $reset_link = $base . "/reset_password.php?token=" . urlencode($token);

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
                if (!mail($to, $subject, $msg, $headers, "-f " . $from)) {
                    // Link nie im Browser anzeigen - sonst kann jeder fremde Konten übernehmen.
                    // Lokal (Docker) steht er im Log: docker compose logs -f app
                    error_log("Clipo: Reset-Mail an " . $to . " fehlgeschlagen. Link: " . $reset_link);
                }
            }
        }
        // Immer dieselbe Meldung - verrät nicht, ob die Adresse existiert
        $message = "Sollte die Adresse im System sein, wurde ein Link versendet.";
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
