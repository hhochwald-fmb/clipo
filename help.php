<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$page_title = 'Clipo - Hilfe & Tipps';
include 'includes/header.php';
?>

<div class="container" style="max-width: 900px;">
    <h1>Hilfe & Tipps</h1>

    <div class="card" style="margin-bottom: 30px; background: var(--primary-gradient); color: white;">
        <h2 style="color: white; margin-top: 0;">🎯 Warum Clipo? Die Mission</h2>
        <p style="opacity: 0.9; line-height: 1.6;">
            Clipo wurde entwickelt, um die Hürden beim Videohosting im Bildungsbereich zu beseitigen. 
            Egal ob Youtube-Fehler (wie der Error 153), Datenschutzbedenken oder die wählerische Natur von H5P – 
            Clipo ist die Antwort für alle, die eine einfache, eigene Lösung suchen.
        </p>
        <details style="margin-top: 15px; cursor: pointer;">
            <summary style="font-weight: 500; opacity: 0.9;">Mehr erfahren über die Entstehung...</summary>
            <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin-top: 10px; font-size: 0.95rem;">
                <p style="margin-bottom: 15px;"><strong>Das Problem:</strong> H5P in Moodle akzeptiert oft keine externen Cloud-Links. YouTube wiederum ist aus Datenschutzgründen kritisch und schaltet unerwünschte Werbung.</p>
                <p style="margin-bottom: 15px;"><strong>Die Idee:</strong> Ein eigenes, schlankes System, das echte MP4-Direktlinks liefert. Keine Tracker, keine Umwege über FTP-Uploads und keine kryptischen Pfade.</p>
                <p><strong>Die Lösung:</strong> Mit Clipo hostest du deine Videos selbst, behältst die volle Kontrolle und integrierst deine Inhalte nahtlos in Moodle, H5P und Co.</p>
            </div>
        </details>
    </div>

    <div class="card" style="margin-bottom: 30px;">
        <h2>🎥 Das ideale Videoformat</h2>
        <p>Um die beste Erfahrung in <strong>Moodle, H5P</strong> oder auf Webseiten zu gewährleisten, nutzt Clipo ausschließlich das <strong>MP4-Format</strong> mit dem <strong>H.264 Video-Codec</strong>.</p>
        
        <h3 style="margin-top: 25px;">Warum H.264?</h3>
        <ul>
            <li><strong>Kompatibilität:</strong> Funktioniert in jedem modernen Browser (Chrome, Safari, Firefox, Edge).</li>
            <li><strong>Streaming:</strong> Das Video kann bereits abgespielt werden, während es noch lädt ("Progressive Download").</li>
            <li><strong>Effizienz:</strong> Sehr gute Qualität bei geringer Dateigröße.</li>
        </ul>
    </div>

    <div class="card" style="margin-bottom: 30px;">
        <h2>⚖️ Videos optimieren (kleiner machen)</h2>
        <p>Ein Video sollte so klein wie möglich sein, damit es auch bei langsamen Internetverbindungen (z.B. in der Schule) sofort startet. Hier sind Tipps für die Optimierung:</p>
        
        <h3 style="margin-top: 25px;">Handbrake (Empfohlen)</h3>
        <p>Mit dem kostenlosen Tool <a href="https://handbrake.fr/" target="_blank">Handbrake</a> kannst du Videos extrem effizient verkleinern:</p>
        <ol style="margin-left: 20px; margin-top: 10px;">
            <li>Video in Handbrake öffnen.</li>
            <li>Preset <strong>"Web Optimized"</strong> (Web-optimiert) aktivieren. Dies ist besonders wichtig für Moodle/H5P (Fast Start).</li>
            <li>Format: <strong>MP4</strong>, Video Codec: <strong>H.264</strong>.</li>
            <li>Auflösung: Für Bildungszwecke reicht oft <strong>720p (HD)</strong> oder sogar 540p völlig aus. 4K oder 1080p sind meist unnötig groß.</li>
        </ol>
    </div>

    <div class="card">
        <h2>🚀 Integration in Moodle / H5P</h2>
        <p>Wenn du den <strong>Direktlink</strong> aus Clipo kopierst und ihn in Moodle (als URL) oder in H5P (als externe Videoquelle) einfügst, profitiert der Nutzer vom schnellen Abruf direkt vom Clipo-Server.</p>
        <p style="margin-top: 15px; color: #64748b; font-style: italic;">
            Tipp: Kleine Videos bedeuten weniger Last auf dem Server und ein glücklicheres Publikum!
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
