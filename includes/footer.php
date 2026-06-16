    <footer>
        <p>
            &copy; <?php echo date('Y'); ?> Clipo v<?php echo defined('DB_VERSION') ? DB_VERSION : '1.0'; ?> | 
            Ein Projekt des <a href="https://www.smz-stuttgart.de" target="_blank">SMZ Stuttgart</a>
        </p>
        <p style="margin-top: 5px;">
            <a href="mailto:<?php echo htmlspecialchars(getSetting('footer_email', 'hochwald@lmz-bw.de')); ?>">Kontakt</a> | 
            <a href="impressum.php">Impressum</a> | 
            <a href="disclaimer.php">Haftungsausschluss</a>
        </p>
    </footer>
</body>
</html>
