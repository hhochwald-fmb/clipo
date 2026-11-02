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

Passwort-Reset-Links landen lokal (ohne Mailserver) im Log: `docker compose logs -f app`
