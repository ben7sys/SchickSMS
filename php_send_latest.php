<?php
/**
 * Verbessertes SMS-Sender Script für Gammu SMSD
 * 
 * Dieses Script bietet ein modernes und benutzerfreundliches Interface
 * zum Versenden von SMS über Gammu-SMSD als Backend.
 */

// Konfiguration
$outboxPath = '/var/spool/gammu/outbox/';
$defaultSender = 'TeltonikaG10';
$maxSmsLength = 160; // Standard SMS-Länge
$addressBookFile = 'sms_addressbook.json'; // Adressbuchdatei im gleichen Verzeichnis

// Aktiviere Fehlerprotokollierung und deaktiviere Anzeige
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Hilfsfunktion zum Prüfen des SMS-Status
function checkSMSStatus($filename) {
    global $outboxPath;
    
    // Prüfen, ob die Datei noch im Outbox-Verzeichnis existiert
    if (file_exists($outboxPath . basename($filename))) {
        return "In der Warteschlange";
    }
    
    // Prüfen, ob die Datei im Error-Verzeichnis gelandet ist
    if (file_exists("/var/spool/gammu/error/" . basename($filename))) {
        return "Fehler bei der Zustellung";
    }
    
    // Prüfen, ob die Datei im Sent-Verzeichnis ist
    if (file_exists("/var/spool/gammu/sent/" . basename($filename))) {
        return "Erfolgreich gesendet";
    }
    
    // Status unbekannt
    return "Status unbekannt";
}

// Direkte Methode zum Aufrufen von gammu-smsd-inject
function sendSMSViaCommand($recipient, $message) {
    // Escape-Zeichen für Shell-Befehle
    $escapedMessage = escapeshellarg($message);
    $escapedRecipient = escapeshellarg($recipient);
    
    // Befehl mit sudo ausführen
    $command = "echo $escapedMessage | sudo gammu-smsd-inject TEXT $escapedRecipient 2>&1";
    $output = [];
    $returnCode = 0;
    
    // Befehl ausführen und Ausgabe erfassen
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        error_log("SMS-Command fehlgeschlagen: " . implode("\n", $output));
        return [
            'success' => false,
            'error' => 'SMS-Befehl fehlgeschlagen: ' . implode(" ", $output)
        ];
    }
    
    error_log("SMS-Command erfolgreich: " . implode("\n", $output));
    return [
        'success' => true,
        'output' => implode("\n", $output)
    ];
}

// Hilfsfunktion zum Senden von SMS über Dateien
function sendSMS($recipient, $message, $sender = null) {
    global $outboxPath, $defaultSender;
    
    // Validiere Telefonnummer
    if (!preg_match('/^\+[0-9]{10,15}$/', $recipient)) {
        return [
            'success' => false, 
            'error' => 'Ungültiges Telefonnummernformat. Bitte internationale Schreibweise verwenden (+491234567890)'
        ];
    }
    
    // Validiere Nachricht
    if (empty($message)) {
        return [
            'success' => false, 
            'error' => 'Nachricht darf nicht leer sein'
        ];
    }
    
    $sender = $sender ?: $defaultSender;
    
    // Einfaches Format für die Outbox-Datei
    $timestamp = date('Ymd_His');
    $randomPart = substr(md5(microtime()), 0, 8);
    $filename = $outboxPath . "OUT_" . $timestamp . "_" . $randomPart . ".txt";
    
    // Inhalt der SMS-Datei - einfaches Format für Textmodus
    $content = "To: $recipient\n";
    $content .= "Alphabet: UTF-8\n";
    $content .= "Coding: Default_No_Compression\n";
    $content .= "\n$message";
    
    // Schreibe Datei ins Outbox-Verzeichnis
    $result = file_put_contents($filename, $content);
    
    // Protokollieren für Debugging
    error_log("SMS-Script: Datei erstellt: $filename mit Inhalt: " . str_replace("\n", "\\n", $content));
    
    if ($result === false) {
        return [
            'success' => false, 
            'error' => 'Konnte nicht in Outbox-Verzeichnis schreiben. Überprüfen Sie die Berechtigungen.'
        ];
    }
    
    // Erfolg melden - Gammu-SMSD wird die Datei automatisch verarbeiten
    return [
        'success' => true, 
        'filename' => basename($filename),
        'message' => 'SMS-Datei in Outbox platziert. Gammu-SMSD wird sie verarbeiten.'
    ];
}

// Adressbuch-Funktionen
function getAddressBook() {
    global $addressBookFile;
    $path = dirname(__FILE__) . '/' . $addressBookFile;
    
    if (!file_exists($path)) {
        // Erstellen einer leeren Adressbuchdatei, wenn nicht vorhanden
        file_put_contents($path, json_encode([]));
        chmod($path, 0666); // Lesbar/schreibbar für alle
        return [];
    }
    
    $content = file_get_contents($path);
    return json_decode($content, true) ?: [];
}

function saveAddressBook($addressBook) {
    global $addressBookFile;
    $path = dirname(__FILE__) . '/' . $addressBookFile;
    return file_put_contents($path, json_encode($addressBook, JSON_PRETTY_PRINT));
}

function addContact($name, $number) {
    $addressBook = getAddressBook();
    
    // Prüfen, ob die Nummer bereits existiert
    foreach ($addressBook as $key => $contact) {
        if ($contact['number'] === $number) {
            // Kontakt aktualisieren
            $addressBook[$key]['name'] = $name;
            return saveAddressBook($addressBook);
        }
    }
    
    // Neuen Kontakt hinzufügen
    $addressBook[] = [
        'name' => $name,
        'number' => $number
    ];
    
    return saveAddressBook($addressBook);
}

function deleteContact($number) {
    $addressBook = getAddressBook();
    
    foreach ($addressBook as $key => $contact) {
        if ($contact['number'] === $number) {
            unset($addressBook[$key]);
            return saveAddressBook(array_values($addressBook)); // Array neu indizieren
        }
    }
    
    return false;
}

// Funktion zum Abrufen der letzten gesendeten SMS
function getRecentSMS($limit = 10) {
    $sentDir = '/var/spool/gammu/sent/';
    $errorDir = '/var/spool/gammu/error/';
    $addressBook = getAddressBook();
    
    $recentSMS = [];
    
    // Suche nach Kontaktnamen zu einer Nummer
    $getContactName = function($number) use ($addressBook) {
        foreach ($addressBook as $contact) {
            if ($contact['number'] === $number) {
                return $contact['name'];
            }
        }
        return null;
    };
    
    // Sent SMS abrufen
    if (is_dir($sentDir)) {
        // Alle .smsbackup-Dateien im Sent-Verzeichnis
        $files = glob($sentDir . '*.smsbackup');
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                
                // Extrahiere die Informationen aus der .smsbackup-Datei
                preg_match('/Number = "([^"]+)"/', $content, $numberMatches);
                preg_match('/DateTime = ([^\s]+)/', $content, $dateMatches);
                
                // Decodiere die Unicode-Text-Nachricht
                preg_match('/Text00 = ([0-9A-F]+)/', $content, $textMatches);
                
                $recipient = isset($numberMatches[1]) ? $numberMatches[1] : 'Unbekannt';
                $datestamp = isset($dateMatches[1]) ? $dateMatches[1] : '';
                
                // Versuche, die Unicode-Nachricht zu entschlüsseln
                $message = 'Keine Nachricht';
                if (isset($textMatches[1])) {
                    $hexString = $textMatches[1];
                    $message = '';
                    
                    // Konvertiere Hex zu UTF-16BE und dann zu UTF-8
                    for ($i = 0; $i < strlen($hexString); $i += 4) {
                        $hex = substr($hexString, $i, 4);
                        $unicode = hexdec($hex);
                        $message .= mb_chr($unicode, 'UTF-8');
                    }
                }
                
                // Kürze lange Nachrichten
                if (strlen($message) > 30) {
                    $message = substr($message, 0, 30) . '...';
                }
                
                // Formatiere das Datum (20250213T183445Z -> 13.02.2025 18:34:45)
                $formattedDate = 'Unbekannt';
                if ($datestamp) {
                    // Entferne das Z am Ende, falls vorhanden
                    $datestamp = rtrim($datestamp, 'Z');
                    
                    // Extrahiere Jahr, Monat, Tag, Stunde, Minute, Sekunde
                    if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})$/', $datestamp, $parts)) {
                        $formattedDate = "{$parts[3]}.{$parts[2]}.{$parts[1]} {$parts[4]}:{$parts[5]}:{$parts[6]}";
                    }
                }
                
                // Versuche, den Namen des Kontakts zu finden
                $contactName = $getContactName($recipient);
                $displayName = $contactName ? "$contactName ($recipient)" : $recipient;
                
                $recentSMS[] = [
                    'filename' => basename($file),
                    'date' => $formattedDate,
                    'recipient' => $displayName,
                    'recipient_number' => $recipient,
                    'message' => $message,
                    'status' => 'Gesendet'
                ];
            }
        }
    }
    
    // Fehlerhafte SMS abrufen (falls vorhanden)
    if (is_dir($errorDir)) {
        $files = glob($errorDir . '*.smsbackup');
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                
                // Extrahiere die gleichen Informationen
                preg_match('/Number = "([^"]+)"/', $content, $numberMatches);
                preg_match('/DateTime = ([^\s]+)/', $content, $dateMatches);
                preg_match('/Text00 = ([0-9A-F]+)/', $content, $textMatches);
                
                $recipient = isset($numberMatches[1]) ? $numberMatches[1] : 'Unbekannt';
                $datestamp = isset($dateMatches[1]) ? $dateMatches[1] : '';
                
                // Versuche, die Unicode-Nachricht zu entschlüsseln
                $message = 'Keine Nachricht';
                if (isset($textMatches[1])) {
                    $hexString = $textMatches[1];
                    $message = '';
                    
                    // Konvertiere Hex zu UTF-16BE und dann zu UTF-8
                    for ($i = 0; $i < strlen($hexString); $i += 4) {
                        $hex = substr($hexString, $i, 4);
                        $unicode = hexdec($hex);
                        $message .= mb_chr($unicode, 'UTF-8');
                    }
                }
                
                // Kürze lange Nachrichten
                if (strlen($message) > 30) {
                    $message = substr($message, 0, 30) . '...';
                }
                
                // Formatiere das Datum
                $formattedDate = 'Unbekannt';
                if ($datestamp) {
                    $datestamp = rtrim($datestamp, 'Z');
                    if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})$/', $datestamp, $parts)) {
                        $formattedDate = "{$parts[3]}.{$parts[2]}.{$parts[1]} {$parts[4]}:{$parts[5]}:{$parts[6]}";
                    }
                }
                
                // Versuche, den Namen des Kontakts zu finden
                $contactName = $getContactName($recipient);
                $displayName = $contactName ? "$contactName ($recipient)" : $recipient;
                
                $recentSMS[] = [
                    'filename' => basename($file),
                    'date' => $formattedDate,
                    'recipient' => $displayName,
                    'recipient_number' => $recipient,
                    'message' => $message,
                    'status' => 'Fehler'
                ];
            }
        }
    }
    
    // Nach Datum sortieren (neueste zuerst)
    usort($recentSMS, function($a, $b) {
        // Versuche, das Datum zu parsen und zu vergleichen
        $dateA = DateTime::createFromFormat('d.m.Y H:i:s', $a['date']);
        $dateB = DateTime::createFromFormat('d.m.Y H:i:s', $b['date']);
        
        if ($dateA && $dateB) {
            return $dateB <=> $dateA;
        }
        
        // Fallback: Vergleiche einfach die Strings
        return strcmp($b['date'], $a['date']);
    });
    
    // Auf Limit beschränken
    return array_slice($recentSMS, 0, $limit);
}

// Verarbeite AJAX-Anfrage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // SMS senden
    if (isset($_POST['action']) && $_POST['action'] === 'send_sms') {
        $recipient = isset($_POST['recipient']) ? trim($_POST['recipient']) : '';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $method = isset($_POST['method']) ? trim($_POST['method']) : 'command';
        
        $result = [];
        
        // Je nach gewählter Methode
        if ($method == 'file' || $method == 'both') {
            // Versuche zuerst mit der Dateimethode
            $result = sendSMS($recipient, $message);
            
            // Kurz warten und Status prüfen
            if ($result['success'] && isset($result['filename'])) {
                sleep(1); // Eine Sekunde warten
                $result['status'] = checkSMSStatus($result['filename']);
            }
        }
        
        // Wenn das fehlschlägt oder die Befehlsmethode ausgewählt wurde
        if (($method == 'both' && !$result['success']) || $method == 'command') {
            error_log("Verwende Befehlsmethode für SMS...");
            $result = sendSMSViaCommand($recipient, $message);
        }
        
        echo json_encode($result);
        exit;
    }
    
    // Letzte SMS abrufen
    if (isset($_POST['action']) && $_POST['action'] === 'get_recent_sms') {
        $recentSMS = getRecentSMS(20); // Erhöht auf 20 für mehr Verlaufseinträge
        echo json_encode(['success' => true, 'sms' => $recentSMS]);
        exit;
    }
    
    // Adressbuch abrufen
    if (isset($_POST['action']) && $_POST['action'] === 'get_address_book') {
        $addressBook = getAddressBook();
        echo json_encode(['success' => true, 'contacts' => $addressBook]);
        exit;
    }
    
    // Kontakt hinzufügen oder aktualisieren
    if (isset($_POST['action']) && $_POST['action'] === 'add_contact') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $number = isset($_POST['number']) ? trim($_POST['number']) : '';
        
        if (empty($name) || empty($number)) {
            echo json_encode(['success' => false, 'error' => 'Name und Nummer sind erforderlich']);
            exit;
        }
        
        // Prüfe, ob die Nummer im richtigen Format ist
        if (!preg_match('/^\+[0-9]{5,}$/', $number)) {
            echo json_encode(['success' => false, 'error' => 'Ungültiges Telefonnummernformat. Bitte internationale Schreibweise verwenden (z.B. +49123456789)']);
            exit;
        }
        
        if (addContact($name, $number)) {
            echo json_encode(['success' => true, 'message' => 'Kontakt gespeichert']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern des Kontakts']);
        }
        exit;
    }
    
    // Kontakt löschen
    if (isset($_POST['action']) && $_POST['action'] === 'delete_contact') {
        $number = isset($_POST['number']) ? trim($_POST['number']) : '';
        
        if (empty($number)) {
            echo json_encode(['success' => false, 'error' => 'Keine Nummer angegeben']);
            exit;
        }
        
        if (deleteContact($number)) {
            echo json_encode(['success' => true, 'message' => 'Kontakt gelöscht']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Fehler beim Löschen des Kontakts']);
        }
        exit;
    }
    
    // Unbekannte Aktion
    echo json_encode(['success' => false, 'error' => 'Unbekannte Aktion']);
    exit;
}

// HTML-Formular im Browser anzeigen
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Gateway</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f39c12;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f8fa;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo i {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        main {
            padding: 2rem 0;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        
        .card-header i {
            margin-right: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-footer {
            background-color: #f8f9fa;
            padding: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .sms-counter {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
            text-align: right;
        }
        
        .btn {
            display: inline-block;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 4px;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn-primary {
            color: #fff;
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .btn-success {
            color: #fff;
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #27ae60;
            border-color: #27ae60;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        
        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeeba;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        
        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0,0,0,0.03);
        }
        
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        
        .badge-success {
            color: #fff;
            background-color: var(--success-color);
        }
        
        .badge-danger {
            color: #fff;
            background-color: var(--error-color);
        }
        
        .tabs {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tabs li {
            margin-right: 0.5rem;
        }
        
        .tabs a {
            display: block;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: #6c757d;
            border: 1px solid transparent;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
        }
        
        .tabs a.active {
            color: var(--dark-color);
            background-color: #fff;
            border-color: #dee2e6;
            border-bottom-color: transparent;
        }
        
        .tabs a:hover:not(.active) {
            color: var(--secondary-color);
            border-color: #e9ecef #e9ecef #dee2e6;
        }
        
        .tab-content {
            padding: 1.5rem 0;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 0.2em solid rgba(0, 0, 0, 0.1);
            border-top: 0.2em solid var(--secondary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn .spinner {
            margin-right: 0.5rem;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        
        .contact-list {
            margin-top: 15px;
        }
        
        .contact-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .contact-item:hover {
            background-color: #f5f5f5;
        }
        
        .contact-name {
            font-weight: bold;
        }
        
        .contact-number {
            color: #666;
            font-size: 0.9em;
        }
        
        /* Responsive Columns */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        
        .col-md-5, .col-md-7 {
            position: relative;
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
        }
        
        @media (min-width: 768px) {
            .col-md-5 {
                flex: 0 0 41.666667%;
                max-width: 41.666667%;
            }
            .col-md-7 {
                flex: 0 0 58.333333%;
                max-width: 58.333333%;
            }
        }
        
        /* Input Group for Recipient Field */
        .input-group {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            width: 100%;
        }
        
        .input-group input {
            position: relative;
            flex: 1 1 auto;
            width: 1%;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .input-group-append {
            display: flex;
            margin-left: -1px;
        }
        
        .input-group-append button {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            padding: 0.75rem;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            color: #495057;
            cursor: pointer;
        }
        
        .input-group-append button:hover {
            background-color: #dde2e6;
        }
        
        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        footer {
            background-color: var(--dark-color);
            color: white;
            text-align: center;
            padding: 1rem 0;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .card-header, .card-body, .card-footer {
                padding: 1rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <i class="fas fa-sms"></i>
                <h1>SMS Gateway</h1>
            </div>
            <div class="status">
                <span id="modemStatus"><i class="fas fa-circle text-success"></i> Online</span>
            </div>
        </div>
    </header>
    
    <main class="container">
        <ul class="tabs">
            <li><a href="#send" class="active" id="sendTabLink" data-tab="send">SMS senden</a></li>
            <li><a href="#history" id="historyTabLink" data-tab="history">Verlauf</a></li>
            <li><a href="#address-book" id="addressBookTabLink" data-tab="address-book">Adressbuch</a></li>
            <li><a href="#status" id="statusTabLink" data-tab="status">Status</a></li>
        </ul>
        
        <div class="tab-content">
            <!-- Tab: SMS senden -->
            <div id="send" class="tab-pane active">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-paper-plane"></i> Neue SMS
                    </div>
                    <div class="card-body">
                        <form id="smsForm">
                            <div class="form-group">
                                <label for="recipient">Empfänger (internationale Schreibweise)</label>
                                <input type="text" id="recipient" name="recipient" placeholder="+491234567890" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Nachricht</label>
                                <textarea id="message" name="message" rows="4" maxlength="1000" required></textarea>
                                <div class="sms-counter">
                                    <span id="charCount">0</span>/<span id="charLimit"><?php echo $maxSmsLength; ?></span> Zeichen 
                                    (<span id="smsCount">1</span> SMS)
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="method">Versandmethode</label>
                                <select id="method" name="method">
                                    <option value="command">Befehlsmethode (zuverlässig)</option>
                                    <option value="file">Dateimethode (native)</option>
                                    <option value="both">Beide Methoden testen</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <button type="button" id="sendButton" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> SMS senden
                        </button>
                    </div>
                </div>
                
                <div id="result" style="display: none;"></div>
            </div>
            
            <!-- Tab: Verlauf -->
            <div id="history" class="tab-pane">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i> Letzte Sendungen
                    </div>
                    <div class="card-body">
                        <div id="historyLoading" class="text-center">
                            <div class="spinner"></div> Lade SMS-Verlauf...
                        </div>
                        <div id="historyContent" style="display: none;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Datum/Zeit</th>
                                        <th>Empfänger</th>
                                        <th>Nachricht</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTable">
                                    <!-- Hier werden die Daten dynamisch eingefügt -->
                                </tbody>
                            </table>
                        </div>
                        <div id="historyEmpty" style="display: none;">
                            <p class="text-center">Keine SMS-Historie verfügbar.</p>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="button" id="refreshHistoryButton" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i> Aktualisieren
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Adressbuch -->
            <div id="address-book" class="tab-pane">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-address-book"></i> Kontakte
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5">
                                <!-- Formular für neue Kontakte -->
                                <h4>Kontakt hinzufügen/bearbeiten</h4>
                                <form id="contactForm">
                                    <div class="form-group">
                                        <label for="contactName">Name</label>
                                        <input type="text" id="contactName" name="name" class="form-control" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="contactNumber">Telefonnummer (international)</label>
                                        <input type="text" id="contactNumber" name="number" placeholder="+491234567890" class="form-control" required>
                                    </div>
                                    
                                    <input type="hidden" id="contactEditMode" value="add">
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Speichern
                                        </button>
                                        <button type="button" id="cancelEditButton" style="display:none;" class="btn btn-secondary">
                                            Abbrechen
                                        </button>
                                    </div>
                                </form>
                                
                                <div id="contactResult" style="display: none;"></div>
                            </div>
                            
                            <div class="col-md-7">
                                <!-- Kontaktliste -->
                                <h4>Kontaktliste</h4>
                                <div id="contactsLoading">
                                    <i class="fas fa-spinner fa-spin"></i> Lade Kontakte...
                                </div>
                                <div id="contactsContainer" style="display: none;">
                                    <div class="form-group">
                                        <input type="text" id="contactSearch" placeholder="Suchen..." class="form-control">
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Nummer</th>
                                                    <th>Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody id="contactsTable">
                                                <!-- Hier werden die Kontakte eingefügt -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div id="contactsEmpty" style="display: none;">
                                    <p class="text-center">Keine Kontakte im Adressbuch.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Status -->
            <div id="status" class="tab-pane">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> System-Status
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <td>Modem-Status:</td>
                                    <td><span class="badge badge-success">Aktiv</span></td>
                                </tr>
                                <tr>
                                    <td>Gammu SMSD Dienst:</td>
                                    <td><span class="badge badge-success">Läuft</span></td>
                                </tr>
                                <tr>
                                    <td>Modem-Typ:</td>
                                    <td>Teltonika</td>
                                </tr>
                                <tr>
                                    <td>Gerät:</td>
                                    <td>/dev/ttyUSB0</td>
                                </tr>
                                <tr>
                                    <td>Verbindung:</td>
                                    <td>at115200</td>
                                </tr>
                                <tr>
                                    <td>Signalstärke:</td>
                                    <td>Sehr gut</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>SMS Gateway mit Gammu &copy; <?php echo date('Y'); ?></p>
        </div>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tabs-Funktionalität
            const tabs = document.querySelectorAll('.tabs a');
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Aktiven Tab setzen
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Tab-Inhalt anzeigen
                    const tabId = this.getAttribute('data-tab');
                    document.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('active');
                    });
                    document.getElementById(tabId).classList.add('active');
                    
                    // Wenn History-Tab aktiviert wird, Daten laden
                    if (tabId === 'history') {
                        loadRecentSMS();
                    }
                });
            });
            
            // SMS-Zähler
            const messageTextarea = document.getElementById('message');
            const charCount = document.getElementById('charCount');
            const smsCount = document.getElementById('smsCount');
            const charLimit = document.getElementById('charLimit');
            
            messageTextarea.addEventListener('input', function() {
                const length = this.value.length;
                charCount.textContent = length;
                
                // SMS-Anzahl berechnen (1 SMS = 160 Zeichen, folgende = 153 Zeichen)
                const maxSingleSMS = <?php echo $maxSmsLength; ?>;
                const maxMultiSMS = 153; // Platz für Segmentierungsinfo
                
                if (length <= maxSingleSMS) {
                    smsCount.textContent = '1';
                } else {
                    const additionalSegments = Math.ceil((length - maxSingleSMS) / maxMultiSMS);
                    smsCount.textContent = (1 + additionalSegments);
                }
                
                // Warnfarbe bei Überschreitung
                if (length > maxSingleSMS) {
                    charCount.style.color = '#f39c12';
                } else {
                    charCount.style.color = '';
                }
            });
            
            // SMS senden
            const sendButton = document.getElementById('sendButton');
            const smsForm = document.getElementById('smsForm');
            const resultDiv = document.getElementById('result');
            
            sendButton.addEventListener('click', function() {
                const recipient = document.getElementById('recipient').value.trim();
                const message = document.getElementById('message').value.trim();
                const method = document.getElementById('method').value;
                
                // Einfache Validierung
                if (!recipient || !message) {
                    showResult('Bitte geben Sie Empfänger und Nachricht ein.', 'error');
                    return;
                }
                
                // SMS senden
                sendButton.disabled = true;
                sendButton.innerHTML = '<div class="spinner"></div> Sendet...';
                
                const formData = new FormData();
                formData.append('action', 'send_sms');
                formData.append('recipient', recipient);
                formData.append('message', message);
                formData.append('method', method);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showResult('SMS erfolgreich gesendet!', 'success', data);
                        smsForm.reset();
                        charCount.textContent = '0';
                        smsCount.textContent = '1';
                    } else {
                        showResult('Fehler: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showResult('Fehler bei der Übertragung: ' + error.message, 'error');
                })
                .finally(() => {
                    sendButton.disabled = false;
                    sendButton.innerHTML = '<i class="fas fa-paper-plane"></i> SMS senden';
                });
            });
            
            // Ergebnisanzeige
            function showResult(message, type, data = null) {
                resultDiv.style.display = 'block';
                resultDiv.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
                
                let html = message;
                
                // Zusätzliche Details hinzufügen, wenn verfügbar
                if (data) {
                    if (data.status) {
                        html += '<br><small>Status: ' + data.status + '</small>';
                    }
                    
                    if (data.output) {
                        html += '<pre style="font-size: 0.8rem; margin-top: 0.5rem; padding: 0.5rem; background-color: #f8f9fa; border: 1px solid #dee2e6;">' + data.output + '</pre>';
                    }
                    
                    if (data.filename) {
                        html += '<br><small>Dateiname: ' + data.filename + '</small>';
                    }
                }
                
                resultDiv.innerHTML = html;
                
                // Automatisches Ausblenden nach 10 Sekunden (nur bei Erfolg)
                if (type === 'success') {
                    setTimeout(() => {
                        resultDiv.style.display = 'none';
                    }, 10000);
                }
            }
            
            // SMS-Verlauf laden
            function loadRecentSMS() {
                const historyLoading = document.getElementById('historyLoading');
                const historyContent = document.getElementById('historyContent');
                const historyEmpty = document.getElementById('historyEmpty');
                const historyTable = document.getElementById('historyTable');
                
                historyLoading.style.display = 'block';
                historyContent.style.display = 'none';
                historyEmpty.style.display = 'none';
                
                const formData = new FormData();
                formData.append('action', 'get_recent_sms');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    historyLoading.style.display = 'none';
                    
                    if (data.success && data.sms && data.sms.length > 0) {
                        historyContent.style.display = 'block';
                        
                        // Tabelle füllen
                        historyTable.innerHTML = '';
                        data.sms.forEach(sms => {
                            const row = document.createElement('tr');
                            
                            const statusClass = sms.status === 'Gesendet' ? 'badge-success' : 'badge-danger';
                            
                            row.innerHTML = `
                                <td>${sms.date}</td>
                                <td>${sms.recipient}</td>
                                <td>${sms.message}</td>
                                <td><span class="badge ${statusClass}">${sms.status}</span></td>
                            `;
                            
                            historyTable.appendChild(row);
                        });
                    } else {
                        historyEmpty.style.display = 'block';
                    }
                })
                .catch(error => {
                    historyLoading.style.display = 'none';
                    historyEmpty.style.display = 'block';
                    historyEmpty.innerHTML = 'Fehler beim Laden des Verlaufs: ' + error.message;
                });
            }
            
            // Event-Handler für Refresh-Button
            document.getElementById('refreshHistoryButton').addEventListener('click', loadRecentSMS);
            
            // Funktionen für die Kontaktliste
            function loadAddressBook() {
                console.log("loadAddressBook wurde aufgerufen");
                const contactsLoading = document.getElementById('contactsLoading');
                const contactsContainer = document.getElementById('contactsContainer');
                const contactsEmpty = document.getElementById('contactsEmpty');
                const contactsTable = document.getElementById('contactsTable');
                
                if (!contactsLoading || !contactsContainer || !contactsEmpty || !contactsTable) {
                    console.error("Ein oder mehrere Elemente für die Kontaktliste wurden nicht gefunden.");
                    return;
                }
                
                contactsLoading.style.display = 'block';
                contactsContainer.style.display = 'none';
                contactsEmpty.style.display = 'none';
                
                const formData = new FormData();
                formData.append('action', 'get_address_book');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    contactsLoading.style.display = 'none';
                    
                    if (data.success && data.contacts && data.contacts.length > 0) {
                        contactsContainer.style.display = 'block';
                        
                        // Tabelle füllen
                        contactsTable.innerHTML = '';
                        data.contacts.forEach(contact => {
                            const row = document.createElement('tr');
                            
                            row.innerHTML = `
                                <td>${contact.name}</td>
                                <td>${contact.number}</td>
                                <td>
                                    <button type="button" class="btn-edit-contact" data-name="${contact.name}" data-number="${contact.number}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn-delete-contact" data-number="${contact.number}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button type="button" class="btn-use-contact" data-number="${contact.number}">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </td>
                            `;
                            
                            contactsTable.appendChild(row);
                        });
                        
                        // Event-Handler für Kontakt-Buttons
                        attachContactButtonHandlers();
                    } else {
                        contactsEmpty.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error("Fehler beim Laden des Adressbuchs:", error);
                    contactsLoading.style.display = 'none';
                    contactsEmpty.style.display = 'block';
                    contactsEmpty.textContent = 'Fehler beim Laden des Adressbuchs: ' + error.message;
                });
            }
            
            function attachContactButtonHandlers() {
                console.log("attachContactButtonHandlers wird ausgeführt");
                
                // Edit-Buttons
                document.querySelectorAll('.btn-edit-contact').forEach(button => {
                    console.log("Edit-Button gefunden:", button);
                    button.addEventListener('click', function() {
                        const name = this.getAttribute('data-name');
                        const number = this.getAttribute('data-number');
                        const contactNameField = document.getElementById('contactName');
                        const contactNumberField = document.getElementById('contactNumber');
                        const contactEditModeField = document.getElementById('contactEditMode');
                        const cancelEditButton = document.getElementById('cancelEditButton');
                        
                        if (contactNameField) contactNameField.value = name;
                        if (contactNumberField) contactNumberField.value = number;
                        if (contactEditModeField) contactEditModeField.value = 'edit';
                        if (cancelEditButton) cancelEditButton.style.display = 'inline-block';
                    });
                });
                
                // Delete-Buttons
                document.querySelectorAll('.btn-delete-contact').forEach(button => {
                    console.log("Delete-Button gefunden:", button);
                    button.addEventListener('click', function() {
                        if (confirm('Sind Sie sicher, dass Sie diesen Kontakt löschen möchten?')) {
                            const number = this.getAttribute('data-number');
                            deleteContact(number);
                        }
                    });
                });
                
                // Use-Contact-Buttons
                document.querySelectorAll('.btn-use-contact').forEach(button => {
                    console.log("Use-Button gefunden:", button);
                    button.addEventListener('click', function() {
                        const number = this.getAttribute('data-number');
                        
                        const recipientField = document.getElementById('recipient');
                        if (recipientField) {
                            recipientField.value = number;
                            
                            // Zum SMS-Senden-Tab wechseln
                            const sendTabLink = document.querySelector('.tabs a[data-tab="send"]');
                            if (sendTabLink) {
                                sendTabLink.click();
                            }
                        }
                    });
                });
            }
            
            function deleteContact(number) {
                const formData = new FormData();
                formData.append('action', 'delete_contact');
                formData.append('number', number);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showContactResult('Kontakt erfolgreich gelöscht', 'success');
                        loadAddressBook(); // Aktualisiere die Kontaktliste
                    } else {
                        showContactResult('Fehler: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showContactResult('Fehler: ' + error.message, 'error');
                });
            }
            
            function showContactResult(message, type) {
                const resultDiv = document.getElementById('contactResult');
                if (!resultDiv) {
                    console.error("contactResult Element nicht gefunden");
                    return;
                }
                
                resultDiv.style.display = 'block';
                resultDiv.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
                resultDiv.textContent = message;
                
                // Ausblenden nach 5 Sekunden, wenn erfolgreich
                if (type === 'success') {
                    setTimeout(() => {
                        resultDiv.style.display = 'none';
                    }, 5000);
                }
            }
            
            // Event-Handler für das Kontaktformular
            const contactForm = document.getElementById('contactForm');
            const contactResult = document.getElementById('contactResult');
            const cancelEditButton = document.getElementById('cancelEditButton');
            
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const nameInput = document.getElementById('contactName');
                    const numberInput = document.getElementById('contactNumber');
                    const editModeInput = document.getElementById('contactEditMode');
                    
                    if (!nameInput || !numberInput) {
                        if (contactResult) {
                            showContactResult('Fehler: Formularelemente nicht gefunden', 'error');
                        }
                        return;
                    }
                    
                    const name = nameInput.value.trim();
                    const number = numberInput.value.trim();
                    
                    if (!name || !number) {
                        if (contactResult) {
                            showContactResult('Bitte geben Sie Name und Nummer ein', 'error');
                        }
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'add_contact');
                    formData.append('name', name);
                    formData.append('number', number);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (contactResult) {
                                showContactResult('Kontakt erfolgreich gespeichert', 'success');
                            }
                            contactForm.reset();
                            
                            if (editModeInput) {
                                editModeInput.value = 'add';
                            }
                            
                            if (cancelEditButton) {
                                cancelEditButton.style.display = 'none';
                            }
                            
                            loadAddressBook(); // Aktualisiere die Kontaktliste
                            
                            // Aktualisiere auch das Kontaktmodal, falls existiert
                            if (typeof loadContactsForModal === 'function') {
                                loadContactsForModal();
                            }
                        } else {
                            if (contactResult) {
                                showContactResult('Fehler: ' + data.error, 'error');
                            }
                        }
                    })
                    .catch(error => {
                        if (contactResult) {
                            showContactResult('Fehler: ' + error.message, 'error');
                        }
                    });
                });
            }
            
            // Abbrechen-Button für Kontakt-Bearbeitung
            if (cancelEditButton) {
                cancelEditButton.addEventListener('click', function() {
                    if (contactForm) {
                        contactForm.reset();
                    }
                    
                    const editModeInput = document.getElementById('contactEditMode');
                    if (editModeInput) {
                        editModeInput.value = 'add';
                    }
                    
                    this.style.display = 'none';
                });
            }
            
            // Kontakt-Suchfunktion
            document.getElementById('contactSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#contactsTable tr');
                
                rows.forEach(row => {
                    const name = row.cells[0].textContent.toLowerCase();
                    const number = row.cells[1].textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || number.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Neue vereinfachte Implementierung der Kontaktauswahl
            const showContactsButton = document.getElementById('showContactsButton');
            if (showContactsButton) {
                showContactsButton.addEventListener('click', function() {
                    initializeContactSelection();
                });
            }
            
            function initializeContactSelection() {
                console.log("Initialisiere Kontaktauswahl-Dialog");
                
                const modal = document.getElementById('contactsModal');
                if (!modal) {
                    console.error("contactsModal nicht gefunden");
                    return;
                }
                
                // Modal anzeigen
                modal.style.display = 'block';
                
                // Kontakte laden
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success && response.contacts) {
                                displayContactsInModal(response.contacts);
                            } else {
                                showModalError("Keine Kontakte gefunden");
                            }
                        } catch(e) {
                            console.error("Fehler beim Parsen der Modal-Daten:", e);
                            showModalError("Fehler beim Laden der Kontakte: " + e.message);
                        }
                    } else {
                        console.error("Server-Fehler beim Modal:", xhr.status);
                        showModalError("Server-Fehler: " + xhr.status);
                    }
                };
                
                xhr.onerror = function() {
                    console.error("Netzwerkfehler beim Laden der Modal-Kontakte");
                    showModalError("Netzwerkfehler beim Laden der Kontakte");
                };
                
                const formData = new FormData();
                formData.append('action', 'get_address_book');
                xhr.send(formData);
                
                // Schließen-Button-Handler
                const closeButton = modal.querySelector('.close');
                if (closeButton) {
                    closeButton.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                }
                
                // Außerhalb-Klick-Handler
                window.onclick = function(event) {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                };
            }
            
            function displayContactsInModal(contacts) {
                const list = document.getElementById('modalContactList');
                if (!list) {
                    console.error("modalContactList nicht gefunden");
                    return;
                }
                
                // Liste leeren
                list.innerHTML = '';
                
                if (contacts.length === 0) {
                    list.innerHTML = '<p class="text-center">Keine Kontakte im Adressbuch.</p>';
                    return;
                }
                
                // Kontakte anzeigen
                contacts.forEach(function(contact) {
                    const item = document.createElement('div');
                    item.className = 'contact-item';
                    
                    item.innerHTML = `
                        <div class="contact-name">${contact.name}</div>
                        <div class="contact-number">${contact.number}</div>
                    `;
                    
                    // Klick-Handler
                    item.addEventListener('click', function() {
                        const recipientField = document.getElementById('recipient');
                        if (recipientField) {
                            recipientField.value = contact.number;
                        }
                        
                        const modal = document.getElementById('contactsModal');
                        if (modal) {
                            modal.style.display = 'none';
                        }
                    });
                    
                    list.appendChild(item);
                });
                
                // Such-Funktion
                const searchInput = document.getElementById('modalContactSearch');
                if (searchInput) {
                    searchInput.value = ''; // Zurücksetzen
                    searchInput.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const items = list.querySelectorAll('.contact-item');
                        
                        items.forEach(function(item) {
                            const name = item.querySelector('.contact-name').textContent.toLowerCase();
                            const number = item.querySelector('.contact-number').textContent.toLowerCase();
                            
                            if (name.includes(searchTerm) || number.includes(searchTerm)) {
                                item.style.display = '';
                            } else {
                                item.style.display = 'none';
                            }
                        });
                    });
                }
            }
            
            function showModalError(message) {
                const list = document.getElementById('modalContactList');
                if (list) {
                    list.innerHTML = `<p class="text-center">${message}</p>`;
                }
            }
            
            // Modal-Suchfunktion
            document.getElementById('modalContactSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const contactItems = document.querySelectorAll('#modalContactList .contact-item');
                
                contactItems.forEach(item => {
                    const name = item.querySelector('.contact-name').textContent.toLowerCase();
                    const number = item.querySelector('.contact-number').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || number.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
            
            // Tabs mit Adressbuch-Unterstützung
            document.querySelectorAll('.tabs a').forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Aktiven Tab setzen
                    document.querySelectorAll('.tabs a').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Tab-Inhalt anzeigen
                    const tabId = this.getAttribute('data-tab');
                    document.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('active');
                    });
                    document.getElementById(tabId).classList.add('active');
                    
                    // Wenn History-Tab aktiviert wird, Daten laden
                    if (tabId === 'history') {
                        loadRecentSMS();
                    }
                    
                    // Wenn Adressbuch-Tab aktiviert wird, Daten laden
                    if (tabId === 'address-book') {
                        loadAddressBook();
                    }
                });
            });
        });
    </script>
</body>
</html>