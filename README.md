# Clipo - Lightweight Video Self-Hosting

Clipo ist eine schlanke, PHP-basierte Videoplattform für Bildungseinrichtungen. Sie ermöglicht ein datenschutzkonformes Hosting von Videos auf eigenen Servern zur Einbindung in Lernmanagementsysteme (LMS) wie Moodle oder in interaktive H5P-Module.

## Features
- ✅ **DSGVO-konform**: Lokales Hosting, keine Tracker, keine Drittanbieter-Fonts.
- ✅ **Manager- & Admin-Rollen**: Getrennte Rechte für Upload und Systemverwaltung.
- ✅ **Public Mode**: Übersicht der Videos optional ohne Login zugänglich.
- ✅ **RTE Impressum**: Integrierter Editor für rechtliche Inhalte.
- ✅ **Einfache Einbindung**: Direktlinks für Moodle/H5P.

## Systemvoraussetzungen
- Webserver (Apache/Nginx)
- PHP 7.4 oder höher
- MySQL / MariaDB
- PHP-Erweiterungen: `pdo_mysql`, `gd`, `fileinfo`

## Installation
1. Lade alle Dateien auf deinen Webserver hoch.
2. Erstelle eine leere MySQL-Datenbank.
3. Rufe `setup.php` in deinem Browser auf (z.B. `deine-domain.de/setup.php`).
4. Folge den Anweisungen, um die Datenbankverbindung und den Admin-Account einzurichten.
- **Wichtig:** Der Webserver benötigt Schreibrechte im Clipo-Hauptverzeichnis, um den Ordner `uploads/` zu erstellen. Sollte dies fehlschlagen, erstelle den Ordner `uploads/` bitte manuell und gib ihm Schreibrechte (CHMOD 775 oder 777).
5. Lösche die `setup.php` nach erfolgreicher Installation aus Sicherheitsgründen.

## Update
Wenn eine neue Version verfügbar ist:
1. Ersetze die alten Dateien durch die neuen (außer `config.php` und den Ordner `uploads`).
2. Rufe die `update.php` im Browser auf, um die Datenbankstruktur zu aktualisieren.

## PHP-Konfguiration (Empfohlen)
Für große Video-Uploads sollten folgende Werte in der `php.ini` angepasst werden:
- `upload_max_filesize = 500M`
- `post_max_size = 510M`
- `max_execution_time = 300`
- `memory_limit = 256M`

---
Entwickelt vom Stadtmedienzentrum Stuttgart (SMZ).
