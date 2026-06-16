# Clipo - Lightweight Video Self-Hosting

Clipo ist eine schlanke, PHP-basierte Videoplattform für Bildungseinrichtungen. Sie ermöglicht ein datenschutzkonformes Hosting von Videos auf eigenen Servern zur Einbindung in Lernmanagementsysteme (LMS) wie Moodle oder in interaktive H5P-Module.

## Features
- ✅ **DSGVO-konform**: Lokales Hosting, keine Tracker, keine Drittanbieter-Fonts.
- ✅ **Manager- & Admin-Rollen**: Getrennte Rechte für Upload und Systemverwaltung.
- ✅ **Public Mode**: Übersicht der Videos optional ohne Login zugänglich.
- ✅ **RTE Impressum**: Integrierter Editor für rechtliche Inhalte.
- ✅ **Einfache Einbindung**: Direktlinks für Moodle/H5P.

---

## Installation

Es gibt zwei Wege: **Docker** (empfohlen, lokal oder auf einem eigenen Server) oder die klassische **Webserver-Installation** (z.B. Shared Hosting bei Mittwald o.ä.).

---

### Variante 1: Docker (empfohlen)

Kein manuelles Einrichten von PHP, MySQL oder Apache nötig. Läuft auf jedem Rechner gleich.

**Voraussetzung:** [Docker Desktop](https://www.docker.com/products/docker-desktop/) installiert und gestartet.

#### Per GitHub (empfohlen – bekommst automatisch Updates)

```bash
git clone https://github.com/hhochwald-fmb/clipo.git
cd clipo
docker compose up -d
```

#### Per ZIP-Datei

ZIP entpacken, Terminal im `clipo`-Ordner öffnen:

```bash
docker compose up -d
```

#### Einrichtung (einmalig)

Öffne im Browser: **http://localhost:8080/setup.php**

Trage folgende Werte ein:

| Feld | Wert |
|---|---|
| MySQL Host | `db` |
| Datenbank Name | `clipo_db` |
| DB Benutzer | `clipo_user` |
| DB Passwort | `clipo_pass` |
| Admin Passwort | frei wählbar |

Danach ist Clipo unter **http://localhost:8080** erreichbar.

Ausführliche Anleitung (auch für Nicht-Techniker): siehe [INSTALLATION.md](INSTALLATION.md)

---

### Variante 2: Klassische Webserver-Installation (Shared Hosting / Mittwald)

Geeignet für normales Shared Hosting ohne Docker-Unterstützung (z.B. Mittwald Webhosting-Pakete).

**Systemvoraussetzungen:**
- Webserver (Apache/Nginx)
- PHP 7.4 oder höher
- MySQL / MariaDB
- PHP-Erweiterungen: `pdo_mysql`, `gd`, `fileinfo`

**Installation:**
1. Alle Dateien per FTP auf den Webserver hochladen
2. `setup.php` im Browser aufrufen (z.B. `deine-domain.de/setup.php`)
3. Datenbankverbindung und Admin-Account einrichten
4. **Wichtig:** Der Webserver benötigt Schreibrechte im Clipo-Hauptverzeichnis damit `uploads/` angelegt werden kann. Falls nicht: Ordner manuell anlegen und CHMOD 775 setzen
5. `setup.php` nach der Installation aus Sicherheitsgründen löschen

**Update:**
1. Alte Dateien durch neue ersetzen (außer `config.php` und `uploads/`)
2. `update.php` im Browser aufrufen um die Datenbankstruktur zu aktualisieren

**Empfohlene PHP-Konfiguration** für große Video-Uploads (`php.ini`):
```
upload_max_filesize = 500M
post_max_size = 510M
max_execution_time = 300
memory_limit = 256M
```

---

## Docker-Update (nach `git pull`)

```bash
git pull
docker compose up -d --build
```

---

Entwickelt vom Stadtmedienzentrum Stuttgart (SMZ).
