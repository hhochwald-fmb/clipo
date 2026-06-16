<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (getSetting('public_access') !== '1') {
    requireLogin();
}

$page_title = 'Clipo - Übersicht';
include 'includes/header.php';

// --- Pagination & Search Configuration ---
$limit = (int)getSetting('videos_per_page', 12);
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;
$search = $_GET['q'] ?? '';
$sort = $_GET['s'] ?? 'newest';
$mine = isset($_GET['mine']) && $_GET['mine'] == '1';

// Build Query
$query_parts = ["1=1"];
$params = [];

if ($search) {
    $query_parts[] = "(v.title LIKE ? OR v.description LIKE ? OR v.tags LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($mine && isLoggedIn()) {
    $query_parts[] = "v.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

$where_clause = implode(" AND ", $query_parts);

// Sorting Logic
switch ($sort) {
    case 'oldest': $order = "v.upload_date ASC"; break;
    case 'alpha': $order = "v.title ASC"; break;
    case 'alpha_desc': $order = "v.title DESC"; break;
    case 'newest':
    default: $order = "v.upload_date DESC"; break;
}

// Get total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM videos v LEFT JOIN users u ON v.user_id = u.id WHERE $where_clause");
$count_stmt->execute($params);
$total_videos = $count_stmt->fetchColumn();
$total_pages = ceil($total_videos / $limit);

// Fetch videos for current page
$stmt = $pdo->prepare("SELECT v.*, u.username as uploader FROM videos v LEFT JOIN users u ON v.user_id = u.id WHERE $where_clause ORDER BY $order LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$videos = $stmt->fetchAll();

// --- Handle deletion (POST only, CSRF-protected) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isManager()) {
    checkCsrf();
    $id = (int)$_POST['delete'];
    if (canEditVideo($id)) {
        $stmt = $pdo->prepare("SELECT filename FROM videos WHERE id = ?"); $stmt->execute([$id]);
        $video = $stmt->fetch();
        if ($video) {
            $filepath = UPLOAD_DIR . $video['filename'];
            if (file_exists($filepath)) unlink($filepath);
            $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$id]);
            header("Location: index.php?msg=deleted"); exit;
        }
    } else {
        header("Location: index.php?msg=denied"); exit;
    }
}
?>

    <div class="container">
        <div style="margin-bottom: 25px; padding: 12px 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; border-left: 5px solid var(--primary-color); display: flex; align-items: center; justify-content: space-between; gap: 15px;">
            <div style="font-size: 0.9rem; color: #475569; line-height: 1.4;">
                <strong style="color: var(--secondary-color);">🎯 Mission Clipo:</strong> Datensicheres Selbsthosting für Moodle &amp; H5P – ohne Youtube-Hürden und Tracker.
            </div>
            <a href="help.php" style="font-size: 0.85rem; color: var(--primary-color); font-weight: 600; text-decoration: none; white-space: nowrap;">Mehr erfahren &rarr;</a>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Video Übersicht</h1>
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success" style="margin-bottom: 0;">Video wurde gelöscht.</div>
            <?php endif; ?>
        </div>

        <!-- Search & Sort Bar -->
        <form class="search-bar" method="GET">
            <input type="text" name="q" placeholder="Suchen..." value="<?php echo htmlspecialchars($search); ?>" style="flex-grow: 1;">
            
            <select name="s" onchange="this.form.submit()" style="width: auto; height: 50px;">
                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Neueste zuerst</option>
                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Älteste zuerst</option>
                <option value="alpha" <?php echo $sort == 'alpha' ? 'selected' : ''; ?>>Alphabetisch (A-Z)</option>
                <option value="alpha_desc" <?php echo $sort == 'alpha_desc' ? 'selected' : ''; ?>>Alphabetisch (Z-A)</option>
            </select>

            <?php if (isLoggedIn()): ?>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; white-space: nowrap; font-size: 0.9rem; font-weight: 600; color: var(--secondary-color); background: #f1f5f9; padding: 0 15px; border-radius: 12px; height: 50px;">
                    <input type="checkbox" name="mine" value="1" <?php echo $mine ? 'checked' : ''; ?> onchange="this.form.submit()" style="width: 18px; height: 18px; cursor: pointer;">
                    Nur meine Videos
                </label>
            <?php endif; ?>

            <button type="submit" class="btn">Suchen</button>
            <?php if ($search || $mine): ?>
                <a href="index.php" class="btn btn-secondary">Zurücksetzen</a>
            <?php endif; ?>
        </form>

        <?php if (empty($videos)): ?>
            <div class="card" style="text-align: center; padding: 50px;">
                <h3>Keine Videos gefunden.</h3>
                <?php if ($search): ?>
                    <p>Versuche es mit einem anderen Suchbegriff.</p>
                <?php else: ?>
                    <?php if (isManager()): ?>
                        <a href="upload.php" class="btn" style="margin-top: 20px;">Video hochladen</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="video-grid">
                <?php foreach ($videos as $video): ?>
                    <div class="video-card">
                        <div class="video-thumb" onclick="openPlayer('<?php echo htmlspecialchars($video['filename']); ?>', '<?php echo htmlspecialchars(addslashes($video['title'])); ?>')">
                            <video muted onmouseover="this.play()" onmouseout="this.pause(); this.currentTime = 0;">
                                <source src="uploads/<?php echo htmlspecialchars($video['filename']); ?>" type="video/mp4">
                            </video>
                        </div>
                        <div class="video-info">
                            <div class="video-title"><?php echo htmlspecialchars($video['title']); ?></div>
                            <div class="video-meta">
                                <span>Von: <?php echo htmlspecialchars($video['uploader'] ?? 'Unbekannt'); ?></span> | 
                                <span><?php echo date('d.m.Y', strtotime($video['upload_date'])); ?></span>
                            </div>
                            
                            <!-- Tags Display -->
                            <div style="margin-bottom: 10px;">
                                <?php 
                                $tags = explode(',', $video['tags'] ?? '');
                                foreach ($tags as $tag) {
                                    $tag = trim($tag);
                                    if ($tag) echo '<span class="tag-badge">#' . htmlspecialchars($tag) . '</span>';
                                }
                                ?>
                            </div>

                            <p style="font-size: 14px; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #64748b;">
                                <?php echo nl2br(htmlspecialchars($video['description'])); ?>
                            </p>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="watch.php?v=<?php echo $video['id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px;">Ansehen</a>
                                <button onclick="copyToClipboard('<?php echo $base_url . '/uploads/' . $video['filename']; ?>')" class="btn" style="padding: 6px 12px; font-size: 13px; background: #333;">Link</button>
                                <?php if (isManager() && canEditVideo($video['id'])): ?>
                                    <a href="upload.php?edit=<?php echo $video['id']; ?>" class="btn" style="padding: 6px 12px; font-size: 13px; background-color: #3d86ad;">Edit</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Sicher löschen?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="delete" value="<?php echo $video['id']; ?>">
                                        <button type="submit" class="btn" style="padding: 6px 12px; font-size: 13px; background-color: #e74c3c;">Löschen</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?p=<?php echo $page - 1; ?><?php echo $search ? '&q='.urlencode($search) : ''; ?>&s=<?php echo $sort; ?>">&laquo; Zurück</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?p=<?php echo $i; ?><?php echo $search ? '&q='.urlencode($search) : ''; ?>&s=<?php echo $sort; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?p=<?php echo $page + 1; ?><?php echo $search ? '&q='.urlencode($search) : ''; ?>&s=<?php echo $sort; ?>">Weiter &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Mini Player Modal -->
    <div id="videoModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closePlayer()">&times;</span>
            <div id="modalTitle" style="padding: 15px; font-weight: 700; font-size: 20px;"></div>
            <video id="modalVideo" controls autoplay style="width: 100%; max-height: 70vh;">
                <source id="modalVideoSource" src="" type="video/mp4">
            </video>
        </div>
    </div>

    <script>
        function openPlayer(filename, title) {
            const modal = document.getElementById('videoModal');
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalVideoSource').src = 'uploads/' + filename;
            document.getElementById('modalVideo').load();
            modal.style.display = 'flex';
        }
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => alert("Link kopiert!"), () => alert("Fehler beim Kopieren."));
        }
        function closePlayer() {
            document.getElementById('modalVideo').pause();
            document.getElementById('videoModal').style.display = 'none';
        }
        window.onclick = (e) => { if (e.target == document.getElementById('videoModal')) closePlayer(); }
    </script>

<?php include 'includes/footer.php'; ?>
