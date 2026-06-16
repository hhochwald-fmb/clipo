# Clipo – Installationsanleitung

Diese Anleitung erklärt Schritt für Schritt, wie du Clipo auf deinem Computer zum Laufen bringst. Du brauchst keine Vorkenntnisse.

---

## Was du bekommst

Zugang zur Clipo-App – entweder als ZIP-Datei oder über einen GitHub-Link. Clipo läuft in **Docker** – das bedeutet: du installierst keine Datenbank, keinen Webserver oder PHP auf deinem Computer. Docker erledigt das alles automatisch, abgeschottet vom Rest deines Systems.

---

## Schritt 1: Docker Desktop installieren

Docker Desktop ist eine kostenlose App, die du einmalig installieren musst.

### Mac
1. Gehe auf [https://www.docker.com/products/docker-desktop/](https://www.docker.com/products/docker-desktop/)
2. Klicke auf **„Download for Mac"**
3. Wähle die richtige Version:
   - **Apple Silicon** (M1, M2, M3, M4) → „Mac with Apple Silicon"
   - **Intel Mac** → „Mac with Intel Chip"
   - Wenn du nicht weißt welchen du hast: Apfel-Menü → „Über diesen Mac" → dort steht es
4. Öffne die heruntergeladene `.dmg`-Datei und ziehe Docker in den Programme-Ordner
5. Starte Docker aus dem Programme-Ordner
6. Folge dem Einrichtungsassistenten (einfach auf „Next" / „OK" klicken)
7. Docker läuft, wenn du oben in der Menüleiste das **Docker-Wal-Symbol** siehst

### Windows
1. Gehe auf [https://www.docker.com/products/docker-desktop/](https://www.docker.com/products/docker-desktop/)
2. Klicke auf **„Download for Windows"**
3. Führe die heruntergeladene `.exe`-Datei aus und folge dem Installationsassistenten
4. Nach der Installation Computer neu starten wenn gefordert
5. Docker läuft, wenn du unten rechts in der Taskleiste das **Docker-Wal-Symbol** siehst

> **Wichtig:** Docker muss gestartet sein bevor du weitermachst. Das Wal-Symbol muss sichtbar sein.

---

## Schritt 2: Clipo herunterladen

**Variante A – per ZIP-Datei:**
1. Lege die ZIP-Datei an einen Ort deiner Wahl, z.B. deinen Schreibtisch oder den Dokumente-Ordner
2. Entpacke die ZIP-Datei:
   - **Mac:** Doppelklick auf die ZIP-Datei
   - **Windows:** Rechtsklick → „Alle extrahieren"
3. Du erhältst einen Ordner namens `clipo`

**Variante B – per GitHub (empfohlen, bekommst automatisch Updates):**
1. Öffne ein Terminal (wie in Schritt 3 beschrieben)
2. Navigiere zu dem Ordner wo du Clipo ablegen möchtest, z.B.:
   ```
   cd ~/Documents
   ```
3. Lade Clipo herunter:
   ```
   git clone https://github.com/hhochwald-fmb/clipo.git
   ```
4. Du erhältst einen Ordner namens `clipo`

---

## Schritt 3: Terminal öffnen im clipo-Ordner

Du musst jetzt ein Terminal (Kommandozeile) öffnen und in den `clipo`-Ordner navigieren.

### Mac
**Variante A – per Finder (einfacher):**
1. Öffne den `clipo`-Ordner im Finder
2. Rechtsklick auf einen leeren Bereich im Ordner → **„Neues Terminal bei Ordner"**

**Variante B – manuell:**
1. Öffne das Terminal (Programme → Dienstprogramme → Terminal)
2. Tippe: `cd ` (mit Leerzeichen danach), dann ziehe den `clipo`-Ordner ins Terminal-Fenster und drücke Enter

### Windows
1. Öffne den `clipo`-Ordner im Explorer
2. Klicke oben in die Adressleiste des Explorers, tippe `cmd` und drücke Enter
3. Ein schwarzes Fenster (Eingabeaufforderung) öffnet sich direkt im richtigen Ordner

---

## Schritt 4: App starten

Gib im Terminal folgenden Befehl ein und drücke Enter:

```
docker compose up -d
```

Docker lädt jetzt automatisch alles herunter was gebraucht wird (PHP, MySQL, Webserver) und startet die App. Das kann beim **ersten Mal 2–5 Minuten** dauern, je nach Internetverbindung.

Du siehst am Ende eine Ausgabe wie diese:

```
Container clipo-db-1 Healthy
Container clipo-app-1 Started
```

Wenn das erscheint, läuft alles.

---

## Schritt 5: Clipo einrichten (einmalig)

1. Öffne deinen Browser (Chrome, Firefox, Safari…)
2. Gehe zu: **http://localhost:8080/setup.php**
3. Das Setup-Formular erscheint. Trage folgendes ein:

| Feld | Wert |
|---|---|
| MySQL Host | `db` |
| Datenbank Name | `clipo_db` |
| DB Benutzer | `clipo_user` |
| DB Passwort | `clipo_pass` |
| Admin Benutzername | z.B. `admin` |
| Admin Passwort | dein eigenes Passwort (frei wählbar) |

4. Klicke auf **„Installieren / Aktualisieren"**
5. Du siehst die Meldung „Installation erfolgreich!" mit einem Link zur App

---

## Schritt 6: Clipo benutzen

Öffne im Browser: **http://localhost:8080**

Melde dich mit dem Admin-Benutzernamen und Passwort an, das du in Schritt 5 gewählt hast.

---

## App stoppen und wieder starten

Die App läuft so lange Docker läuft. Wenn du den Computer ausschaltest, stoppt Docker automatisch.

**App wieder starten** (nach Neustart des Computers):
1. Sicherstellen dass Docker Desktop läuft (Wal-Symbol in der Menüleiste)
2. Terminal im `clipo`-Ordner öffnen (wie in Schritt 3)
3. Eingeben:
```
docker compose up -d
```

**App stoppen** (wenn du sie nicht mehr brauchst):
```
docker compose down
```

---

## Updates erhalten (nur bei GitHub-Installation)

Wenn eine neue Version von Clipo verfügbar ist, reichen drei Befehle:

1. Terminal im `clipo`-Ordner öffnen (wie in Schritt 3)
2. Neue Version herunterladen:
```
git pull
```
3. App neu starten mit der neuen Version:
```
docker compose up -d --build
```

Die Datenbank und deine Einstellungen bleiben dabei erhalten.

---

## Häufige Probleme

**„docker compose" wird nicht erkannt**
→ Docker Desktop ist nicht gestartet. Starte Docker und warte bis das Wal-Symbol erscheint.

**Die Seite http://localhost:8080 lädt nicht**
→ Warte noch 30 Sekunden und lade die Seite neu. Manchmal braucht die Datenbank etwas länger beim ersten Start.

**„Port already in use" Fehlermeldung**
→ Ein anderes Programm nutzt bereits Port 8080. Schreib demjenigen der dir die ZIP-Datei geschickt hat.

**Setup-Seite erscheint immer wieder nach dem Login**
→ Du hast in Schritt 5 als MySQL Host `localhost` statt `db` eingegeben. Gehe erneut zu http://localhost:8080/setup.php und korrigiere den Host auf `db`.
