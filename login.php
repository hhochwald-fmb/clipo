<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: index.php"); exit;
        } else { $error = "Ungültig."; }
    }
}
$page_title = 'Clipo - Login';
include 'includes/header.php';
?>
    <div class="container" style="display: flex; align-items: center; gap: 60px; min-height: 70vh;">
        <!-- Login Card -->
        <div class="card" style="flex: 0 0 400px;">
            <h1>Anmelden</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'timeout'): ?>
                <div class="alert alert-danger">Sitzung abgelaufen. Bitte erneut anmelden.</div>
            <?php endif; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'reset_success'): ?>
                <div class="alert alert-success">Passwort wurde geändert. Bitte anmleden.</div>
            <?php endif; ?>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-group"><label>Benutzername</label><input type="text" name="username" required></div>
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label>Passwort</label>
                        <a href="forgot_password.php" style="font-size: 0.8rem; color: #64748b; margin-bottom: 5px;">Vergessen?</a>
                    </div>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Einloggen</button>
            </form>
        </div>

        <!-- Teaser Text -->
        <div class="teaser-content" style="flex: 1;">
            <h2 style="font-size: 2.8rem; margin-bottom: 20px; line-height: 1.2;">Videos für Bildung. <br><span style="color: var(--primary-color);">Sicher & Lokal.</span></h2>
            
            <div id="text-slider" style="height: 80px; position: relative; margin-bottom: 30px;">
                <p class="slide active" style="font-size: 1.1rem; color: #64748b; max-width: 550px; line-height: 1.5; position: absolute; top: 0; left: 0; transition: all 0.8s ease; opacity: 1; transform: translateY(0);">
                    Schluss mit <strong>Youtube-Fehlern</strong> (Error 153) und Datenschutzbedenken. 
                    Clipo liefert echte MP4-Direktlinks für <strong>Moodle & H5P</strong> – ohne Tracker und Werbung.
                </p>
                <p class="slide" style="font-size: 1.1rem; color: #64748b; max-width: 550px; line-height: 1.5; position: absolute; top: 0; left: 0; transition: all 0.8s ease; opacity: 0; transform: translateY(20px);">
                    Clipo ist die datenschutzkonforme Plattform für deine Videos. Hoste deine Inhalte auf dem eigenen Server – souverän, schnell und ohne Drittanbieter.
                </p>
            </div>

            <style>
                .slide.active { opacity: 1 !important; transform: translateY(0) !important; }
                .slide.hidden { opacity: 0 !important; transform: translateY(-20px) !important; }
            </style>

            <script>
                const slides = document.querySelectorAll('.slide');
                let currentSlide = 0;
                setInterval(() => {
                    slides[currentSlide].classList.remove('active');
                    slides[currentSlide].classList.add('hidden');
                    currentSlide = (currentSlide + 1) % slides.length;
                    slides[currentSlide].classList.remove('hidden');
                    slides[currentSlide].classList.add('active');
                }, 6000);
            </script>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div style="background: white; padding: 20px; border-radius: 15px; box-shadow: var(--shadow-sm);">
                    <div style="font-size: 24px; margin-bottom: 10px;">🔒</div>
                    <h4 style="margin-bottom: 5px;">DSGVO konform</h4>
                    <p style="font-size: 0.9rem; color: #64748b;">Vollständige Kontrolle über alle Daten auf dem eigenen Server.</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 15px; box-shadow: var(--shadow-sm);">
                    <div style="font-size: 24px; margin-bottom: 10px;">🚀</div>
                    <h4 style="margin-bottom: 5px;">Moodle & H5P</h4>
                    <p style="font-size: 0.9rem; color: #64748b;">Direktlinks für die nahtlose Einbindung in deine Lernplattform.</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 15px; box-shadow: var(--shadow-sm);">
                    <div style="font-size: 24px; margin-bottom: 10px;">📱</div>
                    <h4 style="margin-bottom: 5px;">Universal</h4>
                    <p style="font-size: 0.9rem; color: #64748b;">Optimiert für alle Endgeräte und schnelles Streaming.</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 15px; box-shadow: var(--shadow-sm);">
                    <div style="font-size: 24px; margin-bottom: 10px;">✨</div>
                    <h4 style="margin-bottom: 5px;">Einfach</h4>
                    <p style="font-size: 0.9rem; color: #64748b;">Hochladen, kopieren, einbauen. So einfach war Videohosting noch nie.</p>
                </div>
            </div>

            <div style="margin-top: 40px; padding: 25px; background: var(--primary-gradient); border-radius: 20px; color: white; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="margin: 0; font-size: 1.4rem;">Dein eigenes Clipo?</h3>
                    <p style="margin: 5px 0 0; opacity: 0.9; font-size: 0.95rem;">Lade das Paket herunter und installiere es auf deinem Server.</p>
                </div>
                <a href="clipo_release.zip" class="btn" style="background: white; color: var(--primary-color); border: none; font-weight: 700;">Jetzt laden</a>
            </div>
        </div>
    </div>
<?php include 'includes/footer.php'; ?>
