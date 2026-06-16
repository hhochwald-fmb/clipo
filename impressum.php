<?php
require_once 'includes/db.php';
$page_title = 'Clipo - Impressum';
include 'includes/header.php';
?>
<div class="container">
    <div class="card">
        <?php echo sanitizeHtml(getSetting('impressum_content', '<h1>Impressum</h1><p>Inhalt noch nicht gesetzt.</p>')); ?>
        <div style="margin-top: 30px;">
            <a href="index.php" class="btn">Zurück zur Übersicht</a>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
