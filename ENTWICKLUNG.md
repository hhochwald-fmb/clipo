# Clipo – Entwickler-Cheatsheet

Schnelle Referenz für die tägliche Arbeit an der App.

---

## Nach jeder Änderung am Code

```bash
git add .
git commit -m "kurze Beschreibung was du geändert hast"
git push
```

Danach haben alle Kollegen mit `git pull` + `docker compose up -d --build` die neue Version.

---

## Nach einem Update (wenn du selbst gepullt hast)

```bash
git pull
docker compose up -d --build
```

Das `--build` ist wichtig – ohne es läuft noch der alte Container.

---

## Auf einem zweiten Mac weiterentwickeln

Einmalig auf dem neuen Rechner:
1. Docker Desktop installieren
2. Im Terminal:
```bash
git clone https://github.com/hhochwald-fmb/clipo.git
cd clipo
docker compose up -d
```
3. Dann setup.php aufrufen und einrichten

Ab dann normal arbeiten: Änderungen machen → `git push` → auf dem anderen Rechner `git pull` + `docker compose up -d --build`.

**Wichtig:** Immer zuerst `git pull` bevor du auf einem anderen Rechner anfängst zu arbeiten – sonst gibt es Konflikte.

---

## App-Alltag

| Was | Befehl |
|---|---|
| App starten | `docker compose up -d` |
| App stoppen | `docker compose down` |
| Logs anschauen | `docker compose logs -f app` |
| Neu bauen (nach Code-Änderung) | `docker compose up -d --build` |
| Alles zurücksetzen inkl. DB | `docker compose down -v` |

---

## Git-Alltag

| Was | Befehl |
|---|---|
| Status anzeigen (was hat sich geändert?) | `git status` |
| Änderungen anzeigen | `git diff` |
| Alles vorbereiten | `git add .` |
| Commit erstellen | `git commit -m "Beschreibung"` |
| Hochladen | `git push` |
| Neueste Version holen | `git pull` |
| Verlauf anzeigen | `git log --oneline` |

---

## Zugangsdaten Docker-Datenbank (lokal)

| Was | Wert |
|---|---|
| MySQL Host | `db` |
| Datenbank | `clipo_db` |
| Benutzer | `clipo_user` |
| Passwort | `clipo_pass` |

Diese Zugangsdaten gelten nur lokal im Docker-Container und können in `docker-compose.yml` geändert werden.

Im Docker-Betrieb liest Clipo die Zugangsdaten direkt aus diesen Umgebungsvariablen (`includes/config_loader.php`), es wird keine `config.php` angelegt. Deshalb übersteht die Konfiguration auch `docker compose up -d --build`. Bei einer klassischen Webserver-Installation ohne Docker schreibt `setup.php` wie bisher eine `config.php`.

`setup.php` ist gesperrt, sobald ein Admin existiert – außer man ist als Admin angemeldet. Notfall (Admin-Passwort vergessen): in `docker-compose.yml` beim Service `app` `CLIPO_SETUP_UNLOCK: "1"` ergänzen, `docker compose up -d`, Setup ausführen, Zeile wieder entfernen.

Passwort-Reset-Links landen lokal (ohne Mailserver) im Log: `docker compose logs -f app`

---

## Neue Version veröffentlichen (Online-Update)

Installationen auf normalem Webhosting holen sich Updates selbst: **Admin → Updates → „Nach Updates suchen“**.
Clipo fragt dabei das neueste **GitHub-Release** von `hhochwald-fmb/clipo` ab und installiert es auf Knopfdruck.

So veröffentlichst du eine neue Version:

1. Versionsnummer in der Datei `VERSION` erhöhen (z. B. `1.6.0` → `1.6.1`)
2. Committen und pushen
3. Release anlegen – der Tag muss zur Datei `VERSION` passen:

```bash
gh release create v1.6.1 --title "Clipo 1.6.1" --notes "Was ist neu …"
```

Ein extra ZIP ist nicht nötig: Clipo nimmt das automatische Quellcode-ZIP des Tags.
(Hängt man ein Paket `clipo-x.y.z.zip` an das Release an, wird stattdessen dieses verwendet.)

Was beim Update passiert:
- Vorher wird ein Backup der ersetzten Dateien in `storage/backups/` angelegt
- `config.php`, `uploads/` und `storage/` werden **nie** überschrieben
- Datenbank-Änderungen laufen beim nächsten Seitenaufruf automatisch

**Datenbank ändern:** neuen Schritt in `includes/migrations.php` (unten bei „Neue Schritte hier anhängen“) ergänzen.
Jeder Schritt muss mehrfach laufen dürfen (erst prüfen, ob Spalte/Tabelle schon existiert).

Hinweis: Installationen, die per `git clone` auf dem Server liegen (und Docker), aktualisiert man weiter mit `git pull` – der Admin-Bereich zeigt das dann auch an.
