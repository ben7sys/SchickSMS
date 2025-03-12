# SchickSMS Troubleshooting Guide

Dieses Dokument enthält Informationen zur Behebung häufiger Probleme mit SchickSMS.

## Bekannte Probleme und Lösungen

### 1. CSS und JavaScript werden nicht geladen (404-Fehler)

**Problem:** Die Webseite zeigt keine Formatierung an und JavaScript-Funktionen funktionieren nicht, weil die CSS- und JavaScript-Dateien nicht gefunden werden können.

**Lösung:** Die Pfade zu den CSS- und JavaScript-Dateien wurden korrigiert. Wenn Sie immer noch 404-Fehler sehen, stellen Sie sicher, dass die folgenden Dateien existieren:
- `schicksms/app/assets/css/styles.css`
- `schicksms/app/assets/css/dark-mode.css`
- `schicksms/app/assets/js/app.js`
- `schicksms/app/assets/js/sms.js`
- `schicksms/app/assets/js/contacts.js`
- `schicksms/app/assets/js/status.js`
- `schicksms/app/assets/js/auth.js`

### 2. Datenbank-Probleme (SMS-Verlauf und Kontakte werden nicht geladen)

**Problem:** Der SMS-Verlauf und die Kontakte werden nicht geladen, und es erscheint eine Ladeanimation, die nicht verschwindet.

**Lösung:** Dies ist in der Regel ein Problem mit den Datenbankberechtigungen. Führen Sie die folgenden Schritte aus:

1. Führen Sie das Skript `fix-permissions.sh` aus, um die Berechtigungen zu korrigieren:
   ```bash
   sudo bash fix-permissions.sh
   ```

2. Wenn das nicht funktioniert, überprüfen Sie die Datenbankprobleme mit dem `debug-database.php` Skript:
   - Kopieren Sie die Datei `debug-database.php` in das Hauptverzeichnis Ihrer SchickSMS-Installation
   - Rufen Sie die Datei im Browser auf (z.B. `http://192.168.88.18/debug-database.php`)
   - Folgen Sie den Anweisungen und Empfehlungen im Debug-Bericht

3. Manuelle Schritte zur Behebung von Datenbankproblemen:
   ```bash
   # Datenbankverzeichnis vorbereiten
   cd /var/www/html/schicksms
   sudo mkdir -p db
   sudo chown www-data:www-data db
   sudo chmod 775 db

   # Datenbankdatei überprüfen
   sudo ls -la db/schicksms.sqlite
   
   # Falls die Datei nicht existiert oder leer ist, neu erstellen:
   sudo sqlite3 db/schicksms.sqlite < db/schema.sql
   sudo chown www-data:www-data db/schicksms.sqlite
   sudo chmod 664 db/schicksms.sqlite
   ```

### 3. SMS können nicht gesendet werden

**Problem:** Beim Versuch, eine SMS zu senden, dreht sich der Ladekreis endlos, aber die SMS wird nicht gesendet.

**Lösung:**

1. Überprüfen Sie, ob Gammu korrekt installiert und konfiguriert ist:
   ```bash
   gammu-config
   ```

2. Testen Sie, ob Gammu-SMSD läuft:
   ```bash
   sudo systemctl status gammu-smsd
   ```

3. Testen Sie den SMS-Versand direkt über die Kommandozeile:
   ```bash
   echo "Test message" | gammu-smsd-inject TEXT +491234567890
   ```

4. Überprüfen Sie die Berechtigungen für das Gammu-Outbox-Verzeichnis:
   ```bash
   sudo ls -la /var/spool/gammu/outbox/
   sudo chown -R www-data:www-data /var/spool/gammu/
   sudo chmod -R 775 /var/spool/gammu/
   ```

5. Wenn Sie die Fehlermeldung "sudo: a terminal is required to read the password" erhalten:
   - Führen Sie das mitgelieferte Setup-Skript aus, um die Berechtigungen zu korrigieren:
     ```bash
     sudo bash setup-gammu-permissions.sh
     ```
   - Dieses Skript fügt den www-data-Benutzer zur dialout-Gruppe hinzu und setzt das setuid-Bit für gammu-smsd-inject, damit es ohne sudo ausgeführt werden kann.
   - Starten Sie Apache nach der Ausführung des Skripts neu:
     ```bash
     sudo systemctl restart apache2
     ```

## Debugging aktivieren

Um detailliertere Fehlerinformationen zu erhalten, wurde der Debug-Modus in der Konfigurationsdatei aktiviert. Sie können die Debug-Logs im Verzeichnis `schicksms/app/logs/` einsehen.

```bash
sudo tail -f /var/www/html/schicksms/app/logs/$(date +%Y-%m-%d).log
```

## Weitere Hilfe

Wenn die oben genannten Schritte das Problem nicht lösen, überprüfen Sie die Apache-Fehlerprotokolle:

```bash
sudo tail -f /var/log/apache2/error.log
```

Oder die PHP-Fehlerprotokolle:

```bash
sudo tail -f /var/log/php*-fpm.log
```
