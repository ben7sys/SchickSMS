Hier ist die kategorisierte Liste aller Befehle aus dem Dokument, sortiert nach Relevanz bzw. Häufigkeit der Nutzung:

---

## **1. Betriebssystem (Linux Ubuntu/Debian)**
Diese Befehle sind systemweit relevant und werden oft verwendet:

### **Systemaktualisierung & Paketverwaltung**
```bash
sudo apt update && sudo apt upgrade
```
```bash
sudo apt install gammu gammu-smsd
```
```bash
sudo apt install apache2 php php-sqlite3 php-cli php-gd php-curl php-mbstring php-xml php-common php-bcmath
```

### **Benutzer- & Gruppenverwaltung**
```bash
sudo usermod -a -G dialout $USER
```
```bash
sudo usermod -a -G gammu $USER
```
```bash
sudo usermod -a -G gammu www-data
```

### **Berechtigungen & Dateisystem**
```bash
sudo chown -R gammu:gammu /var/spool/gammu
```
```bash
sudo chmod -R 774 /var/spool/gammu
```
```bash
sudo chown -R www-data:www-data /var/www/html/schicksms
```
```bash
sudo chmod -R 755 /var/www/html/schicksms
```

### **System-Prozesse & Logs**
```bash
ps aux | grep gammu-smsd
```
```bash
sudo journalctl -u gammu-smsd -f
```
```bash
sudo tail -f /var/log/syslog | grep gammu
```

### **Neustart & Dienste**
```bash
sudo reboot
```
```bash
sudo systemctl restart gammu-smsd
```
```bash
sudo systemctl status gammu-smsd
```
```bash
sudo systemctl stop gammu-smsd
```
```bash
sudo systemctl reload apache2
```

---

## **2. Modem & Serielle Kommunikation**
Diese Befehle sind wichtig für die Erkennung, Konfiguration und Tests des Modems:

### **USB-Geräte & Kernel-Logs**
```bash
lsusb
```
```bash
sudo lsusb -v -d 0403:6001
```
```bash
dmesg | grep -i modem
```
```bash
mmcli -L
```
```bash
ls -l /dev/ttyUSB*
```
```bash
sudo lsof /dev/ttyUSB0
```

### **Serielle Kommunikation testen**
```bash
sudo screen /dev/ttyUSB0 115200
```
Nach Start im Terminal:
- `AT` eintippen → Modem sollte mit `OK` antworten.
- Beenden mit `CTRL+A`, dann `k` und `y`.

---

## **3. Gammu (SMS-Dienst & Konfiguration)**
Diese Befehle beziehen sich auf die Verwaltung von Gammu und das Versenden von SMS:

### **Gammu Version & Identifikation**
```bash
gammu --version
```
```bash
gammu identify
```

### **SMS-Versand mit Gammu**
```bash
gammu sendsms TEXT +49123456789 -text "Test SMS"
```

### **SMS-Versand über SMS-Daemon**
```bash
echo "Test SMS via SMSD" | sudo gammu-smsd-inject TEXT +49xxxxxxxxxxx
```

### **Gammu-Konfiguration bearbeiten**
```bash
sudo nano /etc/gammurc
```
```ini
[gammu]
device = /dev/ttyUSB0
connection = at115200
```

```bash
sudo nano /etc/gammu-smsdrc
```
```ini
[gammu]
device = /dev/ttyUSB0
connection = at115200
model = at
[smsd]
RunOnReceive = /var/www/html/schicksms/scripts/daemon.sh
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

---

## **4. Webserver (Apache2 & PHP)**
Diese Befehle sind notwendig für die Konfiguration und Verwaltung des Webservers:

### **Apache Virtual Host für SchickSMS konfigurieren**
```bash
sudo nano /etc/apache2/sites-available/schicksms.conf
```
```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/schicksms
    ServerName your.domain.com

    <Directory /var/www/html/schicksms>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/schicksms_error.log
    CustomLog ${APACHE_LOG_DIR}/schicksms_access.log combined
</VirtualHost>
```

### **Apache-Konfiguration aktivieren & prüfen**
```bash
sudo a2ensite schicksms
```
```bash
sudo a2enmod rewrite
```
```bash
sudo systemctl restart apache2
```
```bash
sudo systemctl status apache2
```

### **Standard-Apache-Site deaktivieren**
```bash
sudo a2dissite 000-default.conf
```
```bash
sudo systemctl reload apache2
```

---

## **5. SchickSMS Konfiguration**
Diese Befehle betreffen die Konfiguration von SchickSMS:

### **Passwort-Hash generieren**
```bash
php -r 'echo password_hash("IhrPasswort", PASSWORD_BCRYPT), "\n";'
```

### **Konfigurationsdatei bearbeiten**
```bash
sudo nano /var/www/html/schicksms/config/config.php
```
Ändern Sie den Passwort-Hash und die IP-Whitelist:
```php
'password_hash' => '$2y$10$YourHashHere', // Ersetzen Sie dies mit dem generierten Hash
'ip_whitelist' => [
    '127.0.0.1',
    // Fügen Sie hier weitere erlaubte IP-Adressen hinzu
],
```

---

## **6. SQLite (Datenbank für SchickSMS)**
Diese Befehle richten die SQLite-Datenbank ein:

### **Datenbankverzeichnis vorbereiten**
```bash
cd /var/www/html/schicksms
sudo mkdir -p db
sudo chown www-data:www-data db
sudo chmod 755 db
```

### **Datenbankschema importieren**
```bash
cd /var/www/html/schicksms
sudo sqlite3 db/schicksms.sqlite < db/schema.sql
sudo chown www-data:www-data db/schicksms.sqlite
sudo chmod 664 db/schicksms.sqlite
```

### **Datenbankprüfung**
```bash
sqlite3 /var/www/html/schicksms/db/schicksms.sqlite ".tables"
```
```bash
sqlite3 /var/www/html/schicksms/db/schicksms.sqlite "SELECT * FROM sqlite_master WHERE type='table';"
```

---

## **7. Installation abschließen**

### **Berechtigungen setzen**
```bash
sudo chown -R www-data:www-data /var/www/html/schicksms
sudo chmod -R 755 /var/www/html/schicksms
sudo chmod -R 774 /var/www/html/schicksms/logs
sudo chmod -R 774 /var/www/html/schicksms/db
```

### **Webserver neu starten**
```bash
sudo systemctl restart apache2
```

### **Gammu-SMSD neu starten**
```bash
sudo systemctl restart gammu-smsd
```

### **Installation testen**
Öffnen Sie einen Browser und navigieren Sie zu:
```
http://your.domain.com
```
oder wenn Sie lokal installieren:
```
http://localhost
```

---

## **8. Fehlerbehebung**

### **Gammu-SMSD-Logs prüfen**
```bash
sudo journalctl -u gammu-smsd -f
```

### **Apache-Logs prüfen**
```bash
sudo tail -f /var/log/apache2/schicksms_error.log
```

### **PHP-Fehler prüfen**
```bash
sudo tail -f /var/log/apache2/error.log
```

### **Berechtigungsprobleme beheben**
```bash
sudo chown -R www-data:www-data /var/www/html/schicksms
sudo chmod -R 755 /var/www/html/schicksms
sudo chmod -R 774 /var/www/html/schicksms/logs
sudo chmod -R 774 /var/www/html/schicksms/db
```

---
