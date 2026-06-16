<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireManager();

// --- AJAX File Upload Handler ---
if (isset($_GET['ajax']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    checkCsrf();

    if (!isset($_FILES['video'])) {
        echo json_encode(['error' => 'Keine Datei empfangen. Wahrscheinlich ist post_max_size zu klein.']); exit;
    }

    if ($_FILES['video']['error'] !== 0) {
        $errCodes = [
            1 => 'Datei zu groß (upload_max_filesize in php.ini)',
            2 => 'Datei zu groß (MAX_FILE_SIZE in HTML)',
            3 => 'Upload nur teilweise abgeschlossen.',
            4 => 'Keine Datei hochgeladen.',
            6 => 'Temporärer Ordner fehlt.',
            7 => 'Festplatte voll oder Schreibfehler.',
            8 => 'Eine PHP-Erweiterung hat den Upload gestoppt.'
        ];
        $msg = $errCodes[$_FILES['video']['error']] ?? 'Unbekannter PHP-Fehler: ' . $_FILES['video']['error'];
        echo json_encode(['error' => $msg]); exit;
    }

    $filename = $_FILES['video']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== 'mp4') {
        echo json_encode(['error' => 'Nur MP4 erlaubt.']); exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['video']['tmp_name']);
    finfo_close($finfo);
    $allowed_mimes = ['video/mp4', 'video/x-m4v', 'application/mp4'];
    if (!in_array($mime, $allowed_mimes, true)) {
        echo json_encode(['error' => 'Datei ist kein gültiges MP4-Video (MIME: ' . htmlspecialchars($mime) . ').']); exit;
    }

    $new_filename = uniqid() . '.mp4';
    if (move_uploaded_file($_FILES['video']['tmp_name'], UPLOAD_DIR . $new_filename)) {
        echo json_encode(['success' => true, 'filename' => $new_filename, 'original_name' => $filename]); exit;
    } else {
        echo json_encode(['error' => 'Datei konnte nicht gespeichert werden. Bitte Schreibrechte für Ordner "uploads" prüfen (chmod 775/777).']); exit;
    }
}

$error = '';
$edit_mode = false;
$video_data = ['title' => '', 'description' => '', 'id' => '', 'filename' => ''];

if (isset($_GET['edit'])) {
    $v_id = (int)$_GET['edit'];
    if (!canEditVideo($v_id)) {
        header("Location: index.php?msg=denied");
        exit;
    }
    $edit_mode = true;
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
    $stmt->execute([$v_id]);
    $video_data = $stmt->fetch();
    if (!$video_data) { header("Location: index.php"); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    checkCsrf();
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $tags = $_POST['tags'] ?? '';
    $filename = $_POST['temp_filename'] ?? '';
    $orig_name = $_POST['orig_name'] ?? '';

    if ($edit_mode) {
        if (!canEditVideo((int)$_POST['id'])) {
            die("Unbefugter Zugriff.");
        }
        $stmt = $pdo->prepare("UPDATE videos SET title = ?, description = ?, tags = ? WHERE id = ?");
        $stmt->execute([$title, $description, $tags, (int)$_POST['id']]);
        header("Location: index.php?msg=updated"); exit;
    } else {
        if ($filename) {
            $filesize = file_exists(UPLOAD_DIR . $filename) ? filesize(UPLOAD_DIR . $filename) : 0;
            $stmt = $pdo->prepare("INSERT INTO videos (filename, original_name, title, description, tags, user_id, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$filename, $orig_name, $title ?: $orig_name, $description, $tags, $_SESSION['user_id'], $filesize]);
            header("Location: index.php?msg=uploaded"); exit;
        } else {
            $error = "Bitte lade zuerst ein Video hoch.";
        }
    }
}

$page_title = $edit_mode ? 'Clipo - Bearbeiten' : 'Clipo - Upload';
include 'includes/header.php';
?>
    <div class="container" style="max-width: 800px;">
        <div class="card">
            <h1><?php echo $edit_mode ? 'Video bearbeiten' : 'Neues Video hochladen'; ?></h1>
            <?php if ($error): ?><div class="alert alert-danger" id="error-box"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST" id="uploadForm">
                <?php echo csrfField(); ?>
                <?php if ($edit_mode): ?> <input type="hidden" name="id" value="<?php echo $video_data['id']; ?>"> <?php endif; ?>
                
                <?php if (!$edit_mode): ?>
                    <div class="form-group">
                        <label>1. Video Datei auswählen</label>
                        <div class="upload-dropzone" id="dropzone">
                            <div class="dropzone-content" id="dropzone-default">
                                <div style="font-size: 48px; margin-bottom: 10px;">🎥</div>
                                <h3 style="margin-bottom: 5px;">Video hierher ziehen</h3>
                                <p style="margin-bottom: 15px;">oder</p>
                                <div class="dropzone-btn">Datei auswählen</div>
                            </div>
                            
                            <!-- Progress View -->
                            <div id="upload-progress" style="display: none; padding: 20px;">
                                <h4 id="progress-status">Lade hoch...</h4>
                                <div style="background: #e2e8f0; height: 12px; border-radius: 6px; margin: 15px 0; overflow: hidden;">
                                    <div id="progress-bar" style="background: var(--primary-gradient); height: 100%; width: 0%; transition: width 0.3s;"></div>
                                </div>
                                <p id="file-link-info" style="font-size: 0.85rem; color: var(--primary-color); font-weight: 600;"></p>
                            </div>

                            <input type="file" name="video" id="video-input" accept="video/mp4" style="display: none;">
                        </div>
                        <input type="hidden" name="temp_filename" id="temp_filename">
                        <input type="hidden" name="orig_name" id="orig_name">
                    </div>
                <?php endif; ?>

                <div class="form-group"><label><?php echo (!$edit_mode ? '2. ' : ''); ?>Titel</label><input type="text" name="title" id="title-field" value="<?php echo htmlspecialchars($video_data['title']); ?>" placeholder="Titel des Videos"></div>
                <div class="form-group"><label>Schlagwörter</label><input type="text" name="tags" value="<?php echo htmlspecialchars($video_data['tags'] ?? ''); ?>" placeholder="Bildung, Tutorial, SMZ"></div>
                <div class="form-group"><label>Beschreibung</label><textarea name="description" rows="3"><?php echo htmlspecialchars($video_data['description']); ?></textarea></div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn" id="submit-btn" style="flex: 1;" <?php echo !$edit_mode ? 'disabled' : ''; ?>><?php echo $edit_mode ? 'Änderungen speichern' : 'Eintrag fertigstellen'; ?></button>
                    <a href="index.php" class="btn btn-secondary" style="flex: 1;">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const dropzone = document.getElementById('dropzone');
        const input = document.getElementById('video-input');
        const progressView = document.getElementById('upload-progress');
        const defaultView = document.getElementById('dropzone-default');
        const progressBar = document.getElementById('progress-bar');
        const progressStatus = document.getElementById('progress-status');
        const submitBtn = document.getElementById('submit-btn');
        const tempInput = document.getElementById('temp_filename');
        const origInput = document.getElementById('orig_name');
        const titleField = document.getElementById('title-field');

        if (dropzone) {
            dropzone.addEventListener('click', () => { if(!tempInput.value) input.click(); });
            dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
            ['dragleave', 'dragend'].forEach(type => dropzone.addEventListener(type, () => dropzone.classList.remove('dragover')));
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
                if (e.dataTransfer.files.length && !tempInput.value) {
                    handleUpload(e.dataTransfer.files[0]);
                }
            });
            input.addEventListener('change', () => {
                if (input.files.length) handleUpload(input.files[0]);
            });
        }

        function handleUpload(file) {
            if (file.type !== 'video/mp4') {
                alert("Nur MP4 Dateien erlaubt.");
                return;
            }

            defaultView.style.display = 'none';
            progressView.style.display = 'block';
            
            const formData = new FormData();
            formData.append('video', file);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload.php?ajax=1', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + '%';
                    progressStatus.innerText = `Lade hoch... ${Math.round(percent)}%`;
                }
            };

            xhr.onload = () => {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    progressStatus.innerText = "✅ Upload abgeschlossen!";
                    progressBar.style.background = "#10b981";
                    tempInput.value = res.filename;
                    origInput.value = res.original_name;
                    if(!titleField.value) titleField.value = res.original_name.replace('.mp4', '');
                    submitBtn.disabled = false;
                    document.getElementById('file-link-info').innerText = "Datei: " + res.original_name;
                } else {
                    alert("Fehler: " + (res.error || "Unbekannter Fehler"));
                    location.reload();
                }
            };

            xhr.onerror = () => {
                alert("Netzwerkfehler beim Upload.");
                location.reload();
            };

            xhr.send(formData);
        }
    </script>
<?php include 'includes/footer.php'; ?>
