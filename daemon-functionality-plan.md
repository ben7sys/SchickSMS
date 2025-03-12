# SchickSMS daemon.sh Funktionalitätsplan

## Aktueller Status

Nach Analyse des SchickSMS-Codes wurde festgestellt, dass:

1. Die SchickSMS-Anwendung verwendet den Gammu-Befehlszeilentool direkt zum Senden von SMS-Nachrichten über den `gammu-smsd-inject`-Befehl.
2. Es gibt derzeit keine Funktionalität zum Empfangen und Verarbeiten eingehender SMS-Nachrichten.
3. Die Installationsskripte (install.sh und INSTALL-Debian.md) verweisen auf ein daemon.sh-Skript, das ausgeführt werden soll, wenn eine SMS empfangen wird (über den RunOnReceive-Parameter in der Gammu-Konfiguration).
4. Das daemon.sh-Skript wird während der Installation als leeres Skript mit nur einer Shebang-Zeile erstellt.

## Schlussfolgerung

**Die daemon.sh-Skriptlogik wird derzeit nicht benötigt**, da die SchickSMS-Anwendung sich auf das Senden von SMS-Nachrichten konzentriert und keine Funktionalität für den Empfang von Nachrichten implementiert hat.

## Zukünftige Implementierung

Wenn in Zukunft die Funktionalität zum Empfangen von SMS-Nachrichten hinzugefügt werden soll, müsste Folgendes implementiert werden:

1. **Datenbank-Schema erweitern**:
   - Eine neue Tabelle für eingehende SMS-Nachrichten erstellen
   ```sql
   CREATE TABLE IF NOT EXISTS incoming_sms (
       id INTEGER PRIMARY KEY AUTOINCREMENT,
       sender TEXT NOT NULL,
       message TEXT NOT NULL,
       received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       processed INTEGER DEFAULT 0,
       archived INTEGER DEFAULT 0
   );
   ```

2. **daemon.sh-Skript implementieren**:
   ```bash
   #!/bin/bash
   
   # Pfad zur SchickSMS-Installation
   SCHICKSMS_PATH="/var/www/html/schicksms"
   
   # Logdatei
   LOG_FILE="$SCHICKSMS_PATH/app/logs/incoming_sms.log"
   
   # Datum und Uhrzeit
   TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")
   
   # SMS-Informationen aus Gammu-Umgebungsvariablen
   SMS_SENDER="$SMS_1_NUMBER"
   SMS_TEXT="$SMS_1_TEXT"
   
   # Logging
   echo "[$TIMESTAMP] Neue SMS empfangen von $SMS_SENDER: $SMS_TEXT" >> "$LOG_FILE"
   
   # SMS in Datenbank speichern
   # Hier könnte ein PHP-Skript aufgerufen werden, das die SMS in die Datenbank einfügt
   php "$SCHICKSMS_PATH/app/scripts/process_incoming_sms.php" "$SMS_SENDER" "$SMS_TEXT"
   
   exit 0
   ```

3. **PHP-Skript zum Verarbeiten eingehender SMS erstellen** (process_incoming_sms.php):
   ```php
   <?php
   // Gemeinsame Funktionen einbinden
   require_once __DIR__ . '/../includes/functions.php';
   
   // Argumente prüfen
   if ($argc < 3) {
       logMessage("Fehler: Nicht genügend Argumente für process_incoming_sms.php", 'error');
       exit(1);
   }
   
   $sender = $argv[1];
   $message = $argv[2];
   
   // Datenbank initialisieren
   $db = getDatabase();
   
   try {
       // SMS in Datenbank speichern
       $stmt = $db->prepare("
           INSERT INTO incoming_sms (sender, message)
           VALUES (:sender, :message)
       ");
       
       $stmt->bindParam(':sender', $sender);
       $stmt->bindParam(':message', $message);
       
       if ($stmt->execute()) {
           logMessage("Eingehende SMS von $sender erfolgreich gespeichert", 'info');
       } else {
           logMessage("Fehler beim Speichern der eingehenden SMS von $sender", 'error');
       }
   } catch (PDOException $e) {
       logMessage("Datenbankfehler beim Speichern der eingehenden SMS: " . $e->getMessage(), 'error');
       exit(1);
   }
   
   // Hier könnten weitere Verarbeitungsschritte erfolgen, z.B. Benachrichtigungen senden
   
   exit(0);
   ```

4. **Frontend-Funktionalität zum Anzeigen eingehender SMS hinzufügen**:
   - API-Endpunkt zum Abrufen eingehender SMS
   - JavaScript-Funktionen zum Anzeigen und Verwalten eingehender SMS
   - UI-Elemente für eingehende SMS

## Anpassungen an der Installation

Wenn die Funktionalität zum Empfangen von SMS implementiert wird, müssten folgende Änderungen an den Installationsskripten vorgenommen werden:

1. **install.sh**: Das daemon.sh-Skript mit dem tatsächlichen Code erstellen
2. **INSTALL-Debian.md**: Anweisungen zur Konfiguration und Verwendung der Empfangsfunktionalität hinzufügen
3. **Gammu-Konfiguration**: Sicherstellen, dass der RunOnReceive-Parameter korrekt auf das daemon.sh-Skript verweist

## Nächste Schritte

1. Entscheiden, ob die Funktionalität zum Empfangen von SMS benötigt wird
2. Falls ja, die oben beschriebenen Implementierungen durchführen
3. Falls nein, die Verweise auf daemon.sh aus den Installationsskripten entfernen
