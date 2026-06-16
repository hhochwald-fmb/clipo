<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $display_name = $_POST['display_name'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Handle Image Upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== 4) { // 4 = No file uploaded
        if ($_FILES['profile_image']['error'] === 0) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['profile_image']['tmp_name']);
            finfo_close($finfo);

            // getimagesize() als zusätzliche Sicherung: bestätigt echtes Bildformat
            $imginfo = @getimagesize($_FILES['profile_image']['tmp_name']);

            if (!in_array($ext, $allowed_ext, true) || !in_array($mime, $allowed_mimes, true) || $imginfo === false) {
                $error = "Ungültiges Format. Nur JPG, PNG, GIF erlaubt.";
            } else {
                $new_image_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], UPLOAD_DIR . $new_image_name)) {
                    if ($user['profile_image'] && file_exists(UPLOAD_DIR . $user['profile_image'])) {
                        unlink(UPLOAD_DIR . $user['profile_image']);
                    }
                    $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$new_image_name, $user_id]);
                    $user['profile_image'] = $new_image_name;
                    $message = "Profilbild wurde aktualisiert.";
                } else { $error = "Fehler beim Verschieben der Datei. Prüfe die Ordnerrechte."; }
            }
        } else { $error = "Upload-Fehler: Code " . $_FILES['profile_image']['error']; }
    }

    // Update Profile Info
    $email = $_POST['email'] ?? '';
    $stmt = $pdo->prepare("UPDATE users SET display_name = ?, email = ? WHERE id = ?");
    $stmt->execute([$display_name, $email, $user_id]);
    $user['display_name'] = $display_name;
    $user['email'] = $email;
    $_SESSION['display_name'] = $display_name;

    // Update Password
    if (!empty($new_password)) {
        if ($new_password === $confirm_password) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
            $message = "Profil erfolgreich aktualisiert.";
        } else {
            $error = "Passwörter stimmen nicht überein.";
        }
    } else {
        if (empty($message)) {
            $message = "Daten wurden aktualisiert.";
        }
    }
}

$page_title = 'Clipo - Mein Profil';
include 'includes/header.php';
?>

<div class="container" style="max-width: 600px;">
    <h1>Mein Profil</h1>

    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div style="text-align: center; margin-bottom: 30px;">
                <div style="width: 120px; height: 120px; margin: 0 auto 15px; border-radius: 50%; background: #eee; overflow: hidden; border: 3px solid var(--primary-color);">
                    <?php if ($user['profile_image']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <div style="line-height: 120px; font-size: 50px; color: #ccc;">👤</div>
                    <?php endif; ?>
                </div>
                <input type="file" name="profile_image" id="profile_image" style="display: none;" onchange="this.form.submit()">
                <label for="profile_image" class="btn btn-secondary" style="display: inline-flex; cursor: pointer;">Bild ändern</label>
            </div>

            <div class="form-group">
                <label>Benutzername (Login)</label>
                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="background: #f1f5f9;">
            </div>

            <div class="form-group">
                <label>Klarname (Display Name)</label>
                <input type="text" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>" placeholder="Wie sollen wir dich nennen?">
            </div>

            <div class="form-group">
                <label>E-Mail Adresse</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="lehrer@schule.de">
                <p style="font-size: 0.8rem; color: #64748b; margin-top: 5px;">Wichtig für die "Passwort vergessen" Funktion.</p>
            </div>

            <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
            <h3>Passwort ändern</h3>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">Leer lassen, wenn keine Änderung gewünscht ist.</p>

            <div class="form-group">
                <label>Neues Passwort</label>
                <input type="password" name="new_password">
            </div>

            <div class="form-group">
                <label>Passwort bestätigen</label>
                <input type="password" name="confirm_password">
            </div>

            <button type="submit" class="btn" style="width: 100%;">Speichern</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
