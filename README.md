# SchickSMS Webinterface

Ein modernes, responsives Webinterface zum Versenden von SMS über Gammu SMSD.

## Funktionen

- **SMS-Versand**: Einfaches Versenden von SMS über Gammu SMSD
- **Adressbuchverwaltung**: Speichern und Verwalten von Kontakten
- **SMS-Verlauf**: Anzeigen, Archivieren und Exportieren von gesendeten SMS
- **System-Status**: Überwachung des Gammu SMSD und des Modems
- **Responsive Design**: Optimiert für Desktop und mobile Geräte
- **Light/Dark Mode**: Unterstützung für helles und dunkles Design

## Technische Details

- **Backend**: PHP
- **Datenbank**: SQLite
- **Frontend**: HTML, CSS, JavaScript
- **Frameworks**: Bootstrap 5
- **Abhängigkeiten**: Gammu SMSD

## Voraussetzungen

- Webserver mit PHP 7.4 oder höher
- SQLite-Unterstützung für PHP
- Gammu SMSD installiert und konfiguriert
- Berechtigungen für den Webserver, um mit Gammu SMSD zu interagieren

## Installation

1. **Dateien kopieren**

   Kopieren Sie alle Dateien in das Webserver-Verzeichnis.

2. **Berechtigungen setzen**

   Stellen Sie sicher, dass der Webserver Schreibrechte für folgende Verzeichnisse hat:
   - `db/`
   - `logs/`
   - `exports/`

   ```bash
   mkdir -p logs exports
   chmod 755 db logs exports
   ```

3. **Konfiguration anpassen**

   Bearbeiten Sie die Datei `config/config.php` und passen Sie die Einstellungen an:
   - Setzen Sie ein sicheres Passwort-Hash (verwenden Sie die Funktion `password_hash()`)
   - Konfigurieren Sie die IP-Whitelist
   - Passen Sie die Gammu-Pfade an Ihre Installation an

4. **Datenbank initialisieren**

   Die Datenbank wird automatisch beim ersten Zugriff erstellt. Alternativ können Sie sie manuell initialisieren:

   ```bash
   php -r "require 'includes/functions.php'; getDatabase();"
   ```

5. **Webserver konfigurieren**

   Konfigurieren Sie Ihren Webserver so, dass er auf das Verzeichnis zeigt, in dem Sie die Dateien kopiert haben.

6. **Zugriff testen**

   Öffnen Sie die Anwendung in einem Webbrowser und melden Sie sich mit dem konfigurierten Passwort an.

## Migration von bestehenden Daten

Wenn Sie von der alten Version migrieren, können Sie Ihre Kontakte importieren:

1. Kopieren Sie die Datei `sms_addressbook.json` in das Hauptverzeichnis der Anwendung.
2. Gehen Sie zum Adressbuch-Tab und klicken Sie auf "Kontakte migrieren".

## Sicherheit

- Die Anwendung verwendet Passwort-Hashing mit BCRYPT.
- CSRF-Schutz ist für alle Formulare implementiert.
- Eine IP-Whitelist kann konfiguriert werden, um den Zugriff einzuschränken.
- Sitzungen haben eine begrenzte Lebensdauer (standardmäßig 8 Stunden).

## Fehlerbehebung

- **Probleme mit dem SMS-Versand**: Stellen Sie sicher, dass Gammu SMSD läuft und der Webserver die erforderlichen Berechtigungen hat.
- **Datenbank-Fehler**: Überprüfen Sie die Schreibrechte für das `db/`-Verzeichnis.
- **Leerer Verlauf**: Stellen Sie sicher, dass die Pfade zu den Gammu-Verzeichnissen korrekt konfiguriert sind.

## Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert.
