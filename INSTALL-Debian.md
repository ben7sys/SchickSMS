# SchickSMS Installation Guide für Debian

Diese Anleitung beschreibt die Installation von SchickSMS auf einem Debian-System.

## Inhaltsverzeichnis

1. [Voraussetzungen](#voraussetzungen)
2. [Systemaktualisierung und Paketinstallation](#systemaktualisierung-und-paketinstallation)
3. [Gammu-Konfiguration](#gammu-konfiguration)
4. [Apache-Webserver-Konfiguration](#apache-webserver-konfiguration)
5. [SchickSMS-Installation](#schicksms-installation)
6. [Datenbank einrichten](#datenbank-einrichten)
7. [Berechtigungen setzen](#berechtigungen-setzen)
8. [Dienste starten](#dienste-starten)
9. [Installation testen](#installation-testen)
10. [SchickSMS aktualisieren](#schicksms-aktualisieren)
11. [Fehlerbehebung](#fehlerbehebung)

## Voraussetzungen

- Debian-System (getestet mit Debian 10/11/12)
- Root- oder sudo-Berechtigungen
- GSM-Modem (z.B. Teltonika, Huawei, ZTE oder anderes kompatibles USB-Modem)
- Internetverbindung für die Paketinstallation

## Systemaktualisierung und Paketinstallation

Aktualisieren Sie zunächst Ihr System und installieren Sie die erforderlichen Pakete:

```bash
# System aktualisieren
sudo apt update && sudo apt upgrade -y

# Gammu und SMS-Daemon installieren
sudo apt install -y gammu gammu-smsd

# Apache, PHP und erforderliche Erweiterungen installieren
sudo apt install -y apache2 php php-sqlite3

# Git für das Herunterladen des Repositories
sudo apt install -y git

rsync installieren
```

## Gammu-Konfiguration

### Verzeichnisse erstellen

```bash
# Gammu-Verzeichnisse erstellen
sudo mkdir -p /var/spool/gammu/{inbox,outbox,sent,error}

# Berechtigungen setzen
sudo chown -R www-data:www-data /var/spool/gammu
sudo chmod -R 774 /var/spool/gammu
```

### Gammu-Konfigurationsdateien erstellen

Erstellen Sie die Hauptkonfigurationsdatei für Gammu:

```bash
sudo nano /etc/gammurc
```

Fügen Sie folgenden Inhalt ein:

```ini
[gammu]
device = /dev/ttyUSB0
connection = at115200
```

Erstellen Sie die Konfigurationsdatei für den Gammu-SMS-Daemon:

```bash
sudo nano /etc/gammu-smsdrc
```

Fügen Sie folgenden Inhalt ein:

```ini
[gammu]
device = /dev/ttyUSB0
connection = at115200
model = at

[smsd]
service = files
logfile = syslog
debuglevel = 255
phoneid = TeltonikaG10
inboxpath = /var/spool/gammu/inbox/
outboxpath = /var/spool/gammu/outbox/
sentsmspath = /var/spool/gammu/sent/
errorsmspath = /var/spool/gammu/error/
```

Hinweis: Passen Sie `device` an, falls Ihr Modem einen anderen Pfad verwendet.

## Apache-Webserver-Konfiguration

### Virtual Host für SchickSMS erstellen

```bash
sudo nano /etc/apache2/sites-available/schicksms.conf
```

Fügen Sie folgenden Inhalt ein:

```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/schicksms
    ServerName localhost

    <Directory /var/www/html/schicksms>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/schicksms_error.log
    CustomLog ${APACHE_LOG_DIR}/schicksms_access.log combined
</VirtualHost>
```

Hinweis: Ersetzen Sie `localhost` durch Ihren Domainnamen, falls verfügbar.

### Apache-Konfiguration aktivieren

```bash
# SchickSMS-Site aktivieren
sudo a2ensite schicksms

# Rewrite-Modul aktivieren
sudo a2enmod rewrite

# Apache neu starten
sudo systemctl restart apache2
```

## SchickSMS-Installation

### SchickSMS-Dateien herunterladen und installieren

```bash
# Repository klonen
git clone https://github.com/ben7sys/SchickSMS.git ~/SchickSMS

# Installationsverzeichnis erstellen
sudo mkdir -p /var/www/html/schicksms

# Anwendungsdateien in das Webverzeichnis kopieren
sudo cp -r ~/SchickSMS/schicksms/* /var/www/html/schicksms/
```

### SchickSMS konfigurieren

#### Passwort-Hash generieren

Generieren Sie einen Passwort-Hash für den Login:

```bash
# Ersetzen Sie "IhrPasswort" durch Ihr gewünschtes Passwort
php -r 'echo "Ihr Passwort-Hash: " . password_hash("IhrPasswort", PASSWORD_BCRYPT) . "\n";'
```

Beispiel:
```
Ihr Passwort-Hash: $2y$10$abcdefghijklmnopqrstuOSomeRandomHashString
```

#### Konfigurationsdatei anpassen

Bearbeiten Sie die Konfigurationsdatei:

```bash
sudo nano /var/www/html/schicksms/config/config.php
```

Aktualisieren Sie die folgenden Einstellungen:

1. Ersetzen Sie `'$2y$10$YourHashHere'` durch Ihren generierten Passwort-Hash:
   ```php
   'password_hash' => '$2y$10$abcdefghijklmnopqrstuOSomeRandomHashString', // Ihr generierter Hash
   ```

2. Passen Sie die IP-Whitelist an Ihre Bedürfnisse an:
   ```php
   'ip_whitelist' => [
       '127.0.0.1',                // Localhost
       '192.168.1.0/24',           // Lokales Netzwerk (anpassen)
   ],
   ```

3. Stellen Sie sicher, dass die Gammu-Pfade korrekt sind:
   ```php
   'gammu' => [
       'outbox_path' => '/var/spool/gammu/outbox/',
       'sent_path' => '/var/spool/gammu/sent/',
       'error_path' => '/var/spool/gammu/error/',
       'default_sender' => 'SchickSMS',
       'max_sms_length' => 160,
   ],
   ```

## Datenbank einrichten

SchickSMS verwendet eine SQLite-Datenbank zur Speicherung von Kontakten und SMS-Verlauf. Führen Sie die folgenden Schritte aus, um die Datenbank zu initialisieren:

```bash
# Datenbankverzeichnis vorbereiten
cd /var/www/html/schicksms
sudo mkdir -p db
sudo chown www-data:www-data db
sudo chmod 755 db

# Datenbankschema importieren
sudo sqlite3 db/schicksms.sqlite < db/schema.sql
sudo chown www-data:www-data db/schicksms.sqlite
sudo chmod 664 db/schicksms.sqlite
```

Sie können die erfolgreiche Erstellung der Datenbank überprüfen mit:

```bash
# Tabellen in der Datenbank anzeigen
sqlite3 /var/www/html/schicksms/db/schicksms.sqlite ".tables"
```

Die Ausgabe sollte die Tabellen `contacts` und `sms_history` anzeigen.

## Berechtigungen setzen

```bash
# Eigentümerschaft setzen
sudo chown -R www-data:www-data /var/www/html/schicksms

# Standardberechtigungen setzen
sudo chmod -R 755 /var/www/html/schicksms
```

# Benutzer zur dialout-Gruppe hinzufügen (für Modemzugriff)
sudo usermod -a -G dialout www-data

# Berechtigungen für gammu-smsd-inject anpassen
sudo chmod +s /usr/bin/gammu-smsd-inject


## Dienste starten

```bash
# Apache neu starten
sudo systemctl restart apache2

# Gammu-SMSD neu starten
sudo systemctl restart gammu-smsd

# Gammu-SMSD für Autostart aktivieren
sudo systemctl enable gammu-smsd
```

## Modem-Erkennung und -Konfiguration

Dieser Schritt wurde in den Abschnitt "Installation testen" integriert.

### Berechtigungen für SMS-Versand einrichten

Um Probleme mit sudo-Berechtigungen beim SMS-Versand zu vermeiden, führen Sie das mitgelieferte Setup-Skript aus:

```bash
# Skript ausführbar machen
sudo chmod +x setup-gammu-permissions.sh

# Skript ausführen
sudo ./setup-gammu-permissions.sh
```

Dieses Skript führt folgende Aktionen aus:
1. Fügt den www-data-Benutzer zur dialout-Gruppe hinzu (für Modemzugriff)
2. Setzt das setuid-Bit für gammu-smsd-inject, damit es ohne sudo ausgeführt werden kann
3. Überprüft die Berechtigungen für das Modemgerät

Nach der Ausführung des Skripts starten Sie Apache neu, damit die Gruppenänderungen wirksam werden:

```bash
sudo systemctl restart apache2
```

## Installation testen

### Webinterface testen

Öffnen Sie einen Webbrowser und navigieren Sie zu:

```
http://localhost
```

oder wenn Sie einen Domainnamen konfiguriert haben:

```
http://ihre-domain.de
```

Melden Sie sich mit dem Passwort `schicksms` an.

### Modem testen (optional)

Schließen Sie Ihr GSM-Modem an und überprüfen Sie, ob es erkannt wird:

```bash
# USB-Geräte auflisten
lsusb

# Serielle Geräte prüfen
ls -l /dev/ttyUSB*
```

Testen Sie die Kommunikation mit dem Modem:

```bash
# Modem identifizieren
gammu identify

# Test-SMS senden (ersetzen Sie +49123456789 durch eine gültige Telefonnummer)
gammu sendsms TEXT +49123456789 -text "Test SMS"
```

Hinweis: Falls Ihr Modem unter einem anderen Pfad als `/dev/ttyUSB0` erkannt wird, passen Sie die Konfigurationsdateien `/etc/gammurc` und `/etc/gammu-smsdrc` entsprechend an.

## SchickSMS aktualisieren

Wenn Sie SchickSMS aktualisieren möchten, führen Sie die folgenden Schritte aus:

```bash
# Git-Repository aktualisieren
cd ~/SchickSMS
git pull

# Alle Dateien außer 'db' und 'config' in das Webverzeichnis kopieren
sudo rsync -av --exclude='db' --exclude='config' ~/SchickSMS/schicksms/ /var/www/html/schicksms/

# Berechtigungen aktualisieren
sudo chown -R www-data:www-data /var/www/html/schicksms
sudo chmod -R 755 /var/www/html/schicksms
```

## Fehlerbehebung

### Gammu-SMSD-Logs prüfen

```bash
# Gammu-SMSD-Logs anzeigen
sudo journalctl -u gammu-smsd -f

# Syslog nach Gammu-Einträgen durchsuchen
sudo tail -f /var/log/syslog | grep gammu
```

### Apache-Logs prüfen

```bash
# SchickSMS-spezifische Fehler
sudo tail -f /var/log/apache2/schicksms_error.log

# Allgemeine PHP-Fehler
sudo tail -f /var/log/apache2/error.log
```

### Häufige Probleme

#### Berechtigungsprobleme

```bash
# Berechtigungen neu setzen
sudo chown -R www-data:www-data /var/www/html/schicksms
sudo chmod -R 755 /var/www/html/schicksms
```

#### Modem-Zugriffsprobleme

```bash
# Benutzer zur dialout-Gruppe hinzufügen
sudo usermod -a -G dialout www-data

# Apache neu starten
sudo systemctl restart apache2
```

---

Bei weiteren Problemen konsultieren Sie die Logs:
- Apache: `/var/log/apache2/schicksms_error.log`
- Gammu: `sudo journalctl -u gammu-smsd -f`
