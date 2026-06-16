<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$id = (int)($_GET['v'] ?? 0);
$stmt = $pdo->prepare("SELECT v.*, u.username as uploader FROM videos v LEFT JOIN users u ON v.user_id = u.id WHERE v.id = ?");
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video) die("Video nicht gefunden.");

$page_title = 'Clipo - ' . htmlspecialchars($video['title']);
include 'includes/header.php';

$direct_link = $base_url . "/uploads/" . $video['filename'];
?>
    <div class="container">
        <div class="card" style="max-width: 1000px; padding: 0; overflow: hidden;">
            <div style="background: #000; display: flex; align-items: center; justify-content: center;">
                <video controls autoplay style="width: 100%; max-height: 80vh;">
                    <source src="uploads/<?php echo htmlspecialchars($video['filename']); ?>" type="video/mp4">
                </video>
            </div>
            <div style="padding: 30px;">
                <h1><?php echo htmlspecialchars($video['title']); ?></h1>
                <div class="video-meta" style="margin-bottom: 20px;">
                    <span>Hochgeladen von: <strong><?php echo htmlspecialchars($video['uploader'] ?? 'Unbekannt'); ?></strong></span> | 
                    <span>Am: <?php echo date('d.m.Y H:i', strtotime($video['upload_date'])); ?></span>
                </div>
                
                <div style="background: #fdfdfd; border: 1px solid #eee; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                    <h3 style="margin-top: 0;">Beschreibung</h3>
                    <p><?php echo nl2br(htmlspecialchars($video['description'])); ?></p>
                </div>

                <div class="card" style="background: #f8f9fa; border: 1px solid #dee2e6; margin-bottom: 0; max-width: 100%;">
                    <h3 style="margin-top: 0; font-size: 18px;">Direktlink für Moodle / H5P</h3>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="directLink" value="<?php echo htmlspecialchars($direct_link); ?>" readonly style="background: #fff;">
                        <button class="btn" onclick="copyLink()">Kopieren</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyLink() {
            var copyText = document.getElementById("directLink");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            alert("Link kopiert: " + copyText.value);
        }
    </script>

<?php include 'includes/footer.php'; ?>
