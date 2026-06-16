<?php
require_once 'db.php';
require_once 'auth.php';

$primary_color = getSetting('primary_color', '#f39200');
$secondary_color = getSetting('secondary_color', '#4a5c66');
$footer_email = getSetting('footer_email', 'hochwald@lmz-bw.de');

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . "://" . $host . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

// Get User Meta for Greeting
$header_user = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT display_name, profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $header_user = $stmt->fetch();
    $greeting_name = $header_user['display_name'] ?: $_SESSION['username'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Clipo'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: <?php echo $primary_color; ?>;
            --primary-gradient: linear-gradient(135deg, <?php echo $primary_color; ?> 0%, <?php echo lighten($primary_color, 20); ?> 100%);
            --secondary-color: <?php echo $secondary_color; ?>;
        }
        .user-nav-item { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; }
        .avatar-mini { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; background: #eee; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="index.php" class="logo">Clipo<span>.</span></a>
                <?php $site_name = getSetting('site_name'); if ($site_name): ?>
                    <span style="font-size: 0.85rem; color: #64748b; font-weight: 500; border-left: 1px solid #e2e8f0; padding-left: 10px;"><?php echo htmlspecialchars($site_name); ?></span>
                <?php endif; ?>
            </div>
            <nav style="display: flex; align-items: center; justify-content: flex-end; gap: 30px; flex-grow: 1;">
                <?php if (isLoggedIn()): ?>
                    <!-- Group 1: User Info (Pushed to the right) -->
                    <div style="display: flex; align-items: center; gap: 12px; margin-left: auto;">
                        <span style="font-size: 0.9rem; color: #64748b;">Hallo, <strong><?php echo htmlspecialchars($greeting_name); ?></strong>!</span>
                        <a href="profile.php" class="user-nav-item">
                            <?php if ($header_user['profile_image']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($header_user['profile_image']); ?>" class="avatar-mini">
                            <?php else: ?>
                                <div class="avatar-mini" style="text-align:center; line-height:32px;">👤</div>
                            <?php endif; ?>
                        </a>
                    </div>

                    <!-- Group 2: Action Buttons -->
                    <div style="display: flex; align-items: center; gap: 10px; border-left: 1px solid #e2e8f0; padding-left: 20px;">
                        <a href="help.php" class="btn btn-secondary" style="background-color: #64748b;">Hilfe</a>
                        
                        <?php if (isManager()): ?>
                            <a href="upload.php" class="btn btn-secondary">Upload</a>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                            <a href="admin.php" class="btn btn-secondary">Admin</a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn">Abmelden</a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn">Anmelden</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
<?php
// Helper for CSS gradient
function lighten($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = min(255, (int)($r + (255 - $r) * ($percent / 100)));
    $g = min(255, (int)($g + (255 - $g) * ($percent / 100)));
    $b = min(255, (int)($b + (255 - $b) * ($percent / 100)));
    return "#" . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}
?>
