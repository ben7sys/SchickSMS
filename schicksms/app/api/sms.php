<?php
/**
 * SchickSMS SMS-API
 * 
 * Diese Datei enthält die API-Funktionen für den SMS-Versand.
 */

// Gemeinsame Funktionen einbinden
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Authentifizierung erfordern
requireAuth();

// Konfiguration laden
$config = loadConfig();

// Datenbank initialisieren
$db = getDatabase();

/**
 * Sendet eine SMS über die Gammu-Befehlszeile
 * 
 * @param string $recipient Die Empfängernummer
 * @param string $message Die Nachricht
 * @return array Das Ergebnis des Versands
 */
function sendSmsViaCommand($recipient, $message) {
    // Escape-Zeichen für Shell-Befehle
    $escapedMessage = escapeshellarg($message);
    $escapedRecipient = escapeshellarg($recipient);
    
    // Befehl direkt ausführen (ohne sudo)
    $command = "echo $escapedMessage | gammu-smsd-inject TEXT $escapedRecipient 2>&1";
    $output = [];
    $returnCode = 0;
    
    // Befehl ausführen und Ausgabe erfassen
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        logMessage("SMS-Command fehlgeschlagen: " . implode("\n", $output), 'error');
        return [
            'success' => false,
            'error' => 'SMS-Befehl fehlgeschlagen: ' . implode(" ", $output)
        ];
    }
    
    logMessage("SMS-Command erfolgreich: " . implode("\n", $output), 'info');
    return [
        'success' => true,
        'output' => implode("\n", $output)
    ];
}

/**
 * Speichert eine gesendete SMS in der Datenbank
 * 
 * @param string $recipient Die Empfängernummer
 * @param string $message Die Nachricht
 * @param string $status Der Status der SMS
 * @param string $filename Der Dateiname (optional)
 * @return bool True bei Erfolg, sonst False
 */
function saveSmsToDatabase($recipient, $message, $status, $filename = null) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO sms_history (recipient, message, status, filename)
            VALUES (:recipient, :message, :status, :filename)
        ");
        
        $stmt->bindParam(':recipient', $recipient);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':filename', $filename);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        logMessage("Fehler beim Speichern der SMS in der Datenbank: " . $e->getMessage(), 'error');
        return false;
    }
}

// API-Anfragen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token überprüfen
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        jsonResponse([
            'success' => false,
            'error' => 'Ungültige Anfrage. CSRF-Token fehlt oder ist ungültig.'
        ]);
    }
    
    // SMS senden
    if (isset($_POST['action']) && $_POST['action'] === 'send_sms') {
        $recipient = isset($_POST['recipient']) ? trim($_POST['recipient']) : '';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        
        // Validierung
        if (empty($recipient) || empty($message)) {
            jsonResponse([
                'success' => false,
                'error' => 'Empfänger und Nachricht sind erforderlich.'
            ]);
        }
        
        // Telefonnummer validieren
        if (!validatePhoneNumber($recipient)) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültiges Telefonnummernformat. Bitte internationale Schreibweise verwenden (z.B. +49123456789).'
            ]);
        }
        
        // SMS über Gammu-Befehl senden
        $result = sendSmsViaCommand($recipient, $message);
        
        // Bei Erfolg in der Datenbank speichern
        if ($result['success']) {
            saveSmsToDatabase($recipient, $message, 'Gesendet');
        }
        
        jsonResponse($result);
    }
    
    // SMS-Verlauf abrufen
    if (isset($_POST['action']) && $_POST['action'] === 'get_sms_history') {
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $archived = isset($_POST['archived']) ? (bool)$_POST['archived'] : false;
        
        try {
            $stmt = $db->prepare("
                SELECT id, recipient, message, status, sent_at, filename, archived
                FROM sms_history
                WHERE archived = :archived
                ORDER BY sent_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            $archivedValue = $archived ? 1 : 0;
            $stmt->bindParam(':archived', $archivedValue, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $smsHistory = $stmt->fetchAll();
            
            // Gesamtanzahl der Einträge abrufen
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM sms_history
                WHERE archived = :archived
            ");
            
            $countStmt->bindParam(':archived', $archivedValue, PDO::PARAM_INT);
            $countStmt->execute();
            $totalCount = $countStmt->fetch()['total'];
            
            jsonResponse([
                'success' => true,
                'sms' => $smsHistory,
                'total' => $totalCount
            ]);
        } catch (PDOException $e) {
            logMessage("Fehler beim Abrufen des SMS-Verlaufs: " . $e->getMessage(), 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Abrufen des SMS-Verlaufs.'
            ]);
        }
    }
    
    // SMS archivieren
    if (isset($_POST['action']) && $_POST['action'] === 'archive_sms') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültige SMS-ID.'
            ]);
        }
        
        try {
            $stmt = $db->prepare("
                UPDATE sms_history
                SET archived = 1
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                jsonResponse([
                    'success' => true,
                    'message' => 'SMS erfolgreich archiviert.'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'error' => 'SMS nicht gefunden oder bereits archiviert.'
                ]);
            }
        } catch (PDOException $e) {
            logMessage("Fehler beim Archivieren der SMS: " . $e->getMessage(), 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Archivieren der SMS.'
            ]);
        }
    }
    
    // SMS wiederherstellen
    if (isset($_POST['action']) && $_POST['action'] === 'unarchive_sms') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültige SMS-ID.'
            ]);
        }
        
        try {
            $stmt = $db->prepare("
                UPDATE sms_history
                SET archived = 0
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                jsonResponse([
                    'success' => true,
                    'message' => 'SMS erfolgreich wiederhergestellt.'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'error' => 'SMS nicht gefunden oder bereits wiederhergestellt.'
                ]);
            }
        } catch (PDOException $e) {
            logMessage("Fehler beim Wiederherstellen der SMS: " . $e->getMessage(), 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Wiederherstellen der SMS.'
            ]);
        }
    }
    
    // SMS löschen
    if (isset($_POST['action']) && $_POST['action'] === 'delete_sms') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültige SMS-ID.'
            ]);
        }
        
        try {
            $stmt = $db->prepare("
                DELETE FROM sms_history
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                jsonResponse([
                    'success' => true,
                    'message' => 'SMS erfolgreich gelöscht.'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'error' => 'SMS nicht gefunden.'
                ]);
            }
        } catch (PDOException $e) {
            logMessage("Fehler beim Löschen der SMS: " . $e->getMessage(), 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Löschen der SMS.'
            ]);
        }
    }
    
    // SMS-Verlauf exportieren (CSV)
    if (isset($_POST['action']) && $_POST['action'] === 'export_sms') {
        $archived = isset($_POST['archived']) ? (bool)$_POST['archived'] : false;
        
        try {
            $stmt = $db->prepare("
                SELECT id, recipient, message, status, sent_at, filename, archived
                FROM sms_history
                WHERE archived = :archived
                ORDER BY sent_at DESC
            ");
            
            $archivedValue = $archived ? 1 : 0;
            $stmt->bindParam(':archived', $archivedValue, PDO::PARAM_INT);
            $stmt->execute();
            
            $smsHistory = $stmt->fetchAll();
            
            // CSV-Datei erstellen
            $filename = 'sms_export_' . date('Y-m-d_His') . '.csv';
            $filepath = '../exports/' . $filename;
            
            // Verzeichnis erstellen, falls es nicht existiert
            if (!is_dir('../exports')) {
                mkdir('../exports', 0755, true);
            }
            
            // CSV-Datei öffnen
            $file = fopen($filepath, 'w');
            
            // UTF-8 BOM für Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header schreiben
            fputcsv($file, ['ID', 'Empfänger', 'Nachricht', 'Status', 'Gesendet am', 'Dateiname', 'Archiviert']);
            
            // Daten schreiben
            foreach ($smsHistory as $sms) {
                fputcsv($file, [
                    $sms['id'],
                    $sms['recipient'],
                    $sms['message'],
                    $sms['status'],
                    $sms['sent_at'],
                    $sms['filename'],
                    $sms['archived'] ? 'Ja' : 'Nein'
                ]);
            }
            
            // Datei schließen
            fclose($file);
            
            jsonResponse([
                'success' => true,
                'message' => 'SMS-Verlauf erfolgreich exportiert.',
                'filename' => $filename,
                'filepath' => 'exports/' . $filename
            ]);
        } catch (PDOException $e) {
            logMessage("Fehler beim Exportieren des SMS-Verlaufs: " . $e->getMessage(), 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Exportieren des SMS-Verlaufs.'
            ]);
        }
    }
    
    // Unbekannte Aktion
    jsonResponse([
        'success' => false,
        'error' => 'Unbekannte Aktion.'
    ]);
} else {
    // Nur POST-Anfragen erlauben
    http_response_code(405);
    jsonResponse([
        'success' => false,
        'error' => 'Methode nicht erlaubt. Nur POST-Anfragen sind zulässig.'
    ]);
}
