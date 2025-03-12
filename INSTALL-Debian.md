# SchickSMS Installation Guide für Debian

Diese Anleitung beschreibt die Installation von SchickSMS auf einem Debian-System von Grund auf.

## Inhaltsverzeichnis

1. [Voraussetzungen](#voraussetzungen)
2. [Systemaktualisierung und Paketinstallation](#systemaktualisierung-und-paketinstallation)
3. [Benutzer- und Gruppenkonfiguration](#benutzer--und-gruppenkonfiguration)
4. [Gammu-Konfiguration](#gammu-konfiguration)
5. [Apache-Webserver-Konfiguration](#apache-webserver-konfiguration)
6. [SchickSMS-Installation](#schicksms-installation)
7. [SQLite-Datenbank-Einrichtung](#sqlite-datenbank-einrichtung)
8. [Berechtigungen setzen](#berechtigungen-setzen)
9. [Dienste starten](#dienste-starten)
10. [Modem-Erkennung und -Konfiguration](#modem-erkennung-und--konfiguration)
11. [Installation testen](#installation-testen)
12. [Fehlerbehebung](#fehlerbehebung)

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
sudo apt install -y apache2 php php-sqlite3 php-cli php-gd php-curl php-mbstring php-xml php-common php-bcmath

# Zusätzliche nützliche Tools installieren
sudo apt install -y sqlite3 screen
```

## Benutzer- und Gruppenkonfiguration

### Überprüfen und Erstellen der erforderlichen Gruppen

Prüfen Sie zunächst, ob die erforderlichen Gruppen existieren:

```bash
# Prüfen, ob die dialout-Gruppe existiert
getent group dialout

# Prüfen, ob die gammu-Gruppe existiert
getent group gammu
```

Falls die gammu-Gruppe nicht existiert, erstellen Sie sie:

```bash
# Gammu-Gruppe erstellen, falls sie nicht existiert
sudo groupadd gammu
```

Die dialout-Gruppe sollte standardmäßig auf Debian-Systemen vorhanden sein, da sie für den Zugriff auf serielle Schnittstellen verwendet wird.

### Benutzer zu Gruppen hinzufügen

Fügen Sie Ihren Benutzer und den Webserver-Benutzer zu den erforderlichen Gruppen hinzu:

```bash
# Aktuellen Benutzer zur dialout-Gruppe hinzufügen (für Zugriff auf serielle Schnittstellen)
sudo usermod -a -G dialout $USER

# Aktuellen Benutzer zur gammu-Gruppe hinzufügen
sudo usermod -a -G gammu $USER

# Webserver-Benutzer zur gammu-Gruppe hinzufügen
sudo usermod -a -G gammu www-data
```

Hinweis: Die Änderungen werden erst nach einem Neustart oder Abmelden und erneuten Anmelden wirksam.

## Gammu-Konfiguration

### Verzeichnisse erstellen

```bash
# Gammu-Verzeichnisse erstellen
sudo mkdir -p /var/spool/gammu/{inbox,outbox,sent,error}

# Gammu-Benutzer erstellen, falls er nicht existiert
if ! id -u gammu &>/dev/null; then
    sudo useradd -r -M -d /var/spool/gammu -s /usr/sbin/nologin gammu
    echo "Gammu-Benutzer wurde erstellt."
fi

# Berechtigungen setzen
sudo chown -R gammu:gammu /var/spool/gammu
sudo chmod -R 774 /var/spool/gammu
```

Hinweis: Falls der Befehl `chown` einen Fehler ausgibt, weil der Benutzer oder die Gruppe gammu nicht existiert, führen Sie die folgenden Befehle aus:

```bash
# Alternative Berechtigungen, falls gammu-Benutzer nicht existiert
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
# RunOnReceive ist optional, da SchickSMS derzeit keine SMS-Empfangsfunktionalität implementiert hat
# Wenn Sie die Empfangsfunktionalität in Zukunft implementieren möchten, können Sie diese Zeile aktivieren
# RunOnReceive = /var/www/html/schicksms/app/scripts/daemon.sh
use_locking = 1
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

# Optional: Standard-Apache-Site deaktivieren
sudo a2dissite 000-default.conf

# Apache neu starten
sudo systemctl restart apache2
```

## SchickSMS-Installation

### Installationsverzeichnis vorbereiten

```bash
# Installationsverzeichnis erstellen
sudo mkdir -p /var/www/html/schicksms
```

### SchickSMS-Dateien herunterladen und installieren

Klonen Sie das SchickSMS-Repository und kopieren Sie die Anwendungsdateien in das Installationsverzeichnis:

```bash
# Git installieren, falls noch nicht vorhanden
sudo apt install -y git

# Repository direkt in das Home-Verzeichnis des Benutzers klonen
git clone https://github.com/ben7sys/SchickSMS.git ~/SchickSMS

# Installationsverzeichnis erstellen
sudo mkdir -p /var/www/html/schicksms

# Nur die Anwendungsdateien in das Webverzeichnis kopieren
sudo cp -r ~/SchickSMS/schicksms/* /var/www/html/schicksms/
```

### SchickSMS aktualisieren

Um SchickSMS später zu aktualisieren, führen Sie folgende Befehle aus:

```bash
# Repository erneut klonen oder aktualisieren
cd ~
rm -rf SchickSMS  # Falls ein vorheriger Klon existiert
git clone https://github.com/ben7sys/SchickSMS.git SchickSMS

# Backup der Konfiguration und Datenbank erstellen
sudo cp /var/www/html/schicksms/config/config.php ~/config.php.bak
sudo cp /var/www/html/schicksms/db/schicksms.sqlite ~/schicksms.sqlite.bak

# Nur die Anwendungsdateien aktualisieren, ohne Konfiguration und Datenbank zu überschreiben
sudo cp -r ~/SchickSMS/schicksms/* /var/www/html/schicksms/

# Konfiguration und Datenbank wiederherstellen (falls sie überschrieben wurden)
sudo cp ~/config.php.bak /var/www/html/schicksms/config/config.php
sudo cp ~/schicksms.sqlite.bak /var/www/html/schicksms/db/schicksms.sqlite

# Berechtigungen nach dem Update erneut setzen
sudo chown -R www-data:www-data /var/www/html/schicksms
sudo chmod -R 755 /var/www/html/schicksms
sudo chmod -R 774 /var/www/html/schicksms/app/logs
sudo chmod -R 774 /var/www/html/schicksms/db
sudo chmod 664 /var/www/html/schicksms/db/schicksms.sqlite
```

### Scripts-Verzeichnis erstellen

```bash
sudo mkdir -p /var/www/html/schicksms/app/scripts
```

### Daemon-Script erstellen (Optional)

**Hinweis:** Das daemon.sh-Script wird für die Grundfunktionalität von SchickSMS nicht benötigt, da die Anwendung derzeit nur SMS-Versand unterstützt und keine Funktionalität zum Empfangen von SMS implementiert hat. Weitere Details finden Sie in der Datei `daemon-functionality-plan.md` im Hauptverzeichnis.

Wenn Sie dennoch ein leeres Script erstellen möchten (für zukünftige Erweiterungen):

```bash
sudo nano /var/www/html/schicksms/app/scripts/daemon.sh
```

Fügen Sie folgenden minimalen Inhalt ein:

```bash
#!/bin/bash
# Dieses Script ist ein Platzhalter für zukünftige SMS-Empfangsfunktionalität
exit 0
```

Machen Sie das Script ausführbar:

```bash
sudo chmod +x /var/www/html/schicksms/app/scripts/daemon.sh
```

### SchickSMS konfigurieren

Erstellen Sie das Konfigurationsverzeichnis:

```bash
sudo mkdir -p /var/www/html/schicksms/config
```

Generieren Sie einen Passwort-Hash für den Admin-Benutzer:

```bash
# Ersetzen Sie "IhrPasswort" durch Ihr gewünschtes Passwort
PASSWORT_HASH=$(php -r 'echo password_hash("IhrPasswort", PASSWORD_BCRYPT), "\n";')
echo $PASSWORT_HASH
```

Erstellen Sie die Konfigurationsdatei:

```bash
sudo nano /var/www/html/schicksms/config/config.php
```

Fügen Sie folgenden Inhalt ein:

```php
<?php
return [
    'password_hash' => '$2y$10$YourHashHere', // Ersetzen Sie dies mit dem generierten Hash
    'ip_whitelist' => [
        '127.0.0.1',
        // Fügen Sie hier weitere erlaubte IP-Adressen hinzu
    ],
    'db_path' => __DIR__ . '/../db/schicksms.sqlite',
'log_path' => __DIR__ . '/../app/logs/app.log',
];
```

Ersetzen Sie `'$2y$10$YourHashHere'` durch den generierten Passwort-Hash.

## SQLite-Datenbank-Einrichtung

### Datenbankverzeichnis erstellen

```bash
sudo mkdir -p /var/www/html/schicksms/db
```

### Datenbankschema importieren

```bash
cd /var/www/html/schicksms
sudo sqlite3 db/schicksms.sqlite < db/schema.sql
```

### Logs-Verzeichnis erstellen

```bash
sudo mkdir -p /var/www/html/schicksms/app/logs
```

## Berechtigungen setzen

Setzen Sie die richtigen Berechtigungen für die SchickSMS-Dateien und -Verzeichnisse:

```bash
# Eigentümerschaft setzen
sudo chown -R www-data:www-data /var/www/html/schicksms

# Standardberechtigungen setzen
sudo chmod -R 755 /var/www/html/schicksms

# Spezielle Berechtigungen für Logs und Datenbank
sudo chmod -R 774 /var/www/html/schicksms/app/logs
sudo chmod -R 774 /var/www/html/schicksms/db

# Datenbankdatei-Berechtigungen
sudo chmod 664 /var/www/html/schicksms/db/schicksms.sqlite
```

## Dienste starten

Starten Sie die erforderlichen Dienste und aktivieren Sie sie für den Autostart:

```bash
# Apache neu starten
sudo systemctl restart apache2

# Gammu-SMSD neu starten
sudo systemctl restart gammu-smsd

# Gammu-SMSD für Autostart aktivieren
sudo systemctl enable gammu-smsd
```

## Modem-Erkennung und -Konfiguration

### Modem erkennen

Schließen Sie Ihr GSM-Modem an und überprüfen Sie, ob es erkannt wird:

```bash
# USB-Geräte auflisten
lsusb

# Kernel-Logs nach Modem durchsuchen
dmesg | grep -i modem

# Serielle Geräte prüfen
ls -l /dev/ttyUSB*
```

### Modem testen

Testen Sie die Kommunikation mit dem Modem:

```bash
# Mit dem Modem über screen kommunizieren
sudo screen /dev/ttyUSB0 115200
```

Nach dem Start im Terminal:
- Tippen Sie `AT` ein → Das Modem sollte mit `OK` antworten.
- Beenden Sie mit `CTRL+A`, dann `k` und `y`.

### Gammu-Konfiguration testen

```bash
# Gammu-Version anzeigen
gammu --version

# Modem identifizieren
gammu identify
```

### Test-SMS senden

```bash
# Direkt mit Gammu
gammu sendsms TEXT +49123456789 -text "Test SMS"

# Über den SMS-Daemon
echo "Test SMS via SMSD" | sudo gammu-smsd-inject TEXT +49123456789
```

Ersetzen Sie `+49123456789` durch eine gültige Telefonnummer.

## Installation testen

Öffnen Sie einen Webbrowser und navigieren Sie zu:

```
http://localhost
```

oder wenn Sie einen Domainnamen konfiguriert haben:

```
http://ihre-domain.de
```

Melden Sie sich mit dem Passwort an, das Sie in der Konfigurationsdatei festgelegt haben.

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

### Gammu-SMSD-Status prüfen

```bash
# Status des Dienstes anzeigen
sudo systemctl status gammu-smsd

# Prozesse prüfen
ps aux | grep gammu-smsd
```

### Berechtigungsprobleme beheben

Wenn Sie Berechtigungsprobleme vermuten, setzen Sie die Berechtigungen erneut:

```bash
sudo chown -R www-data:www-data /var/www/html/schicksms
sudo chmod -R 755 /var/www/html/schicksms
sudo chmod -R 774 /var/www/html/schicksms/app/logs
sudo chmod -R 774 /var/www/html/schicksms/db
sudo chmod 664 /var/www/html/schicksms/db/schicksms.sqlite
```

### Modem-Zugriffsprobleme

Wenn der Zugriff auf das Modem Probleme bereitet:

```bash
# Prüfen, welche Prozesse das Modem verwenden
sudo lsof /dev/ttyUSB0

# Benutzer zu relevanten Gruppen hinzufügen
sudo usermod -a -G dialout,gammu www-data
sudo usermod -a -G dialout,gammu $USER
```

Nach Änderungen an Gruppen ist ein Neustart erforderlich, damit diese wirksam werden:

```bash
sudo reboot
```

---

Bei weiteren Problemen oder Fragen konsultieren Sie bitte die Dokumentation oder wenden Sie sich an den Support.
