<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$message = '';
$error = '';
$token = $_GET['token'] ?? '';
$user = null;

if (empty($token)) {
    header("Location: login.php");
    exit;
}

// Verify token
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $error = "Ungültiger oder abgelaufener Link.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    checkCsrf();
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';
    
    if (strlen($pass1) < 6) {
        $error = "Das Passwort muss mindestens 6 Zeichen lang sein.";
    } elseif ($pass1 !== $pass2) {
        $error = "Die Passwörter stimmen nicht überein.";
    } else {
        $hashed = password_hash($pass1, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
        $stmt->execute([$hashed, $user['id']]);
        
        $message = "Passwort erfolgreich geändert! Du kannst dich jetzt einloggen.";
        echo "<script>setTimeout(() => window.location.href='login.php?msg=reset_success', 3000);</script>";
    }
}

$page_title = 'Clipo - Passwort zurücksetzen';
include 'includes/header.php';
?>
    <div class="container" style="max-width: 500px; margin-top: 50px;">
        <div class="card">
            <h1>Neues Passwort setzen</h1>
            <?php if ($user): ?>
                <p style="margin-bottom: 25px; color: #64748b;">Hallo <strong><?php echo htmlspecialchars($user['username']); ?></strong>, bitte wähle ein neues Passwort.</p>

                <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label>Neues Passwort</label>
                        <input type="password" name="pass1" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Passwort wiederholen</label>
                        <input type="password" name="pass2" required minlength="6">
                    </div>
                    <button type="submit" class="btn">Passwort speichern</button>
                </form>
            <?php else: ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <a href="forgot_password.php" class="btn">Neuen Link anfordern</a>
            <?php endif; ?>
        </div>
    </div>
<?php include 'includes/footer.php'; ?>
