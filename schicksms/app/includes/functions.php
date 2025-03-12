<?php
/**
 * SchickSMS Gemeinsame Funktionen
 * 
 * Diese Datei enthält gemeinsame Funktionen, die in der gesamten Anwendung verwendet werden.
 */

/**
 * Lädt die Konfiguration
 * 
 * @return array Die Konfigurationseinstellungen
 */
function loadConfig() {
    static $config = null;
    
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }
    
    return $config;
}

/**
 * Initialisiert die Datenbankverbindung
 * 
 * @return PDO Die PDO-Datenbankverbindung
 */
function getDatabase() {
    static $db = null;
    
    if ($db === null) {
        $config = loadConfig();
        $dbPath = $config['database']['path'];
        
        // Stellen Sie sicher, dass das Datenbankverzeichnis existiert
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        // Verbindung zur Datenbank herstellen
        try {
            $db = new PDO('sqlite:' . $dbPath);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // SQLite-Fremdschlüsselunterstützung aktivieren
            $db->exec('PRAGMA foreign_keys = ON;');
            
            // Datenbank initialisieren, wenn sie neu ist
            initializeDatabase($db);
        } catch (PDOException $e) {
            die('Datenbankfehler: ' . $e->getMessage());
        }
    }
    
    return $db;
}

/**
 * Initialisiert die Datenbank mit dem Schema
 * 
 * @param PDO $db Die Datenbankverbindung
 */
function initializeDatabase($db) {
    $schemaFile = __DIR__ . '/../db/schema.sql';
    
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $db->exec($sql);
    } else {
        die('Datenbankschema nicht gefunden: ' . $schemaFile);
    }
}

/**
 * Generiert ein CSRF-Token und speichert es in der Session
 * 
 * @return string Das generierte CSRF-Token
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Überprüft ein CSRF-Token
 * 
 * @param string $token Das zu überprüfende Token
 * @return bool True, wenn das Token gültig ist, sonst False
 */
function verifyCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validiert eine Telefonnummer
 * 
 * @param string $number Die zu validierende Telefonnummer
 * @return bool True, wenn die Nummer gültig ist, sonst False
 */
function validatePhoneNumber($number) {
    // Nummer muss mit + beginnen und mindestens 3 Zeichen lang sein
    return preg_match('/^\+[0-9]{2,}$/', $number);
}

/**
 * Bereinigt Eingabedaten
 * 
 * @param string $data Die zu bereinigenden Daten
 * @return string Die bereinigten Daten
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Gibt eine JSON-Antwort zurück und beendet die Ausführung
 * 
 * @param array $data Die Daten, die als JSON zurückgegeben werden sollen
 */
function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Protokolliert eine Nachricht
 * 
 * @param string $message Die zu protokollierende Nachricht
 * @param string $level Das Log-Level (info, warning, error)
 */
function logMessage($message, $level = 'info') {
    $config = loadConfig();
    
    if ($config['app']['debug'] || $level === 'error') {
        $logFile = __DIR__ . '/../logs/' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);
        
        // Stellen Sie sicher, dass das Log-Verzeichnis existiert
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }
    
    // Bei Fehlern auch in das Systemprotokoll schreiben
    if ($level === 'error') {
        error_log("SchickSMS: $message");
    }
}

/**
 * Berechnet die Anzahl der SMS-Segmente für eine Nachricht
 * 
 * @param string $message Die Nachricht
 * @return array Ein Array mit der Anzahl der Zeichen und der Anzahl der SMS-Segmente
 */
function calculateSmsSegments($message) {
    $config = loadConfig();
    $maxSingleSMS = $config['gammu']['max_sms_length'];
    $maxMultiSMS = 153; // Platz für Segmentierungsinformationen
    
    $length = mb_strlen($message, 'UTF-8');
    
    if ($length <= $maxSingleSMS) {
        $segments = 1;
    } else {
        $segments = 1 + ceil(($length - $maxSingleSMS) / $maxMultiSMS);
    }
    
    return [
        'length' => $length,
        'segments' => $segments
    ];
}

/**
 * Prüft, ob eine IP-Adresse in der Whitelist enthalten ist
 * 
 * @param string $ip Die zu prüfende IP-Adresse
 * @return bool True, wenn die IP-Adresse in der Whitelist enthalten ist, sonst False
 */
function isIpWhitelisted($ip) {
    $config = loadConfig();
    $whitelist = $config['auth']['ip_whitelist'];
    
    // Wenn die Whitelist leer ist, alle IPs zulassen
    if (empty($whitelist)) {
        return true;
    }
    
    return in_array($ip, $whitelist);
}

/**
 * Formatiert ein Datum in ein lesbares Format
 * 
 * @param string $date Das zu formatierende Datum
 * @return string Das formatierte Datum
 */
function formatDate($date) {
    $timestamp = strtotime($date);
    return date('d.m.Y H:i:s', $timestamp);
}

/**
 * Erstellt eine Sicherheitskopie der Datenbank
 * 
 * @return bool True bei Erfolg, sonst False
 */
function backupDatabase() {
    $config = loadConfig();
    $dbPath = $config['database']['path'];
    $backupDir = __DIR__ . '/../backups';
    
    // Stellen Sie sicher, dass das Backup-Verzeichnis existiert
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backupFile = $backupDir . '/backup_' . date('Y-m-d_His') . '.sqlite';
    
    // Datenbank kopieren
    if (copy($dbPath, $backupFile)) {
        logMessage("Datenbank-Backup erstellt: $backupFile", 'info');
        return true;
    } else {
        logMessage("Fehler beim Erstellen des Datenbank-Backups: $backupFile", 'error');
        return false;
    }
}
