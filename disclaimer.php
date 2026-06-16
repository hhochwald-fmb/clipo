<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$page_title = 'Clipo - Haftungsausschluss';
include 'includes/header.php';
?>

<div class="container" style="max-width: 800px;">
    <div class="card">
        <h1>Haftungsausschluss (Disclaimer)</h1>
        
        <h3>1. Allgemeines</h3>
        <p>Die Software "Clipo" wird als Open-Source-Projekt des Stadtmedienzentrums Stuttgart (SMZ) zur Verfügung gestellt. Die Nutzung der Software erfolgt ausdrücklich auf eigene Gefahr des Betreibers und der Endnutzer.</p>

        <h3>2. Haftungsbeschränkung</h3>
        <p>Das SMZ Stuttgart übernimmt keine Haftung für Schäden, die durch die Installation oder Nutzung dieser Software entstehen. Dies gilt insbesondere für:</p>
        <ul>
            <li>Datenverlust oder Beschädigung von Videodateien.</li>
            <li>Sicherheitslücken, Hacking-Angriffe oder unbefugten Zugriff auf den Server.</li>
            <li>Fehlfunktionen der Software oder Inkompatibilität mit Serverumgebungen.</li>
            <li>Unterbrechungen der Verfügbarkeit oder fehlerhafte Darstellung von Inhalten.</li>
        </ul>

        <h3>3. Eigenverantwortung beim Self-Hosting</h3>
        <p>Da Clipo auf eigenen Servern ("Self-Hosted") betrieben wird, liegt die volle Verantwortung für die Sicherheit des Systems (z.B. SSL-Zertifikate, PHP-Sicherheitseinstellungen, Backup-Strategien) beim jeweiligen Betreiber der Instanz.</p>

        <h3>4. Keine Gewährleistung</h3>
        <p>Die Software wurde mit Sorgfalt entwickelt, wird jedoch "wie gesehen" (as is) ohne jegliche ausdrückliche oder implizite Gewährleistung für die Funktionsfähigkeit oder Marktgängigkeit bereitgestellt.</p>

        <div style="margin-top: 30px;">
            <a href="index.php" class="btn">Zurück zur Übersicht</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
