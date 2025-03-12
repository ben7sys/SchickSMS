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
        $config = require __DIR__ . '/../../config/config.php';
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
        $dbDebug = isset($config['database']['debug']) ? $config['database']['debug'] : false;
        
        // Stellen Sie sicher, dass das Datenbankverzeichnis existiert
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        // Prüfen, ob die Datenbankdatei existiert und lesbar/schreibbar ist
        if (file_exists($dbPath)) {
            if (!is_readable($dbPath)) {
                $error = "Datenbankdatei ist nicht lesbar: $dbPath";
                logMessage($error, 'error');
                if ($dbDebug) {
                    die($error . " (Berechtigungen: " . substr(sprintf('%o', fileperms($dbPath)), -4) . ")");
                } else {
                    die($error);
                }
            }
            
            if (!is_writable($dbPath)) {
                $error = "Datenbankdatei ist nicht schreibbar: $dbPath";
                logMessage($error, 'error');
                if ($dbDebug) {
                    die($error . " (Berechtigungen: " . substr(sprintf('%o', fileperms($dbPath)), -4) . ")");
                } else {
                    die($error);
                }
            }
        } else {
            // Prüfen, ob das Verzeichnis schreibbar ist
            if (!is_writable($dbDir)) {
                $error = "Datenbankverzeichnis ist nicht schreibbar: $dbDir";
                logMessage($error, 'error');
                if ($dbDebug) {
                    die($error . " (Berechtigungen: " . substr(sprintf('%o', fileperms($dbDir)), -4) . ")");
                } else {
                    die($error);
                }
            }
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
            
            // Testen, ob die Datenbank funktioniert
            $testQuery = $db->query("SELECT 1");
            if (!$testQuery) {
                throw new PDOException("Datenbankverbindung konnte nicht hergestellt werden");
            }
            
            logMessage("Datenbankverbindung erfolgreich hergestellt", 'info');
        } catch (PDOException $e) {
            $error = 'Datenbankfehler: ' . $e->getMessage();
            logMessage($error, 'error');
            if ($dbDebug) {
                die($error . " (Pfad: $dbPath)");
            } else {
                die($error);
            }
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
    // Prüfen, ob die Tabellen bereits existieren
    $tablesExist = false;
    try {
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='contacts'");
        $tablesExist = ($result && $result->fetch());
    } catch (PDOException $e) {
        // Ignorieren und fortfahren
    }
    
    // Wenn die Tabellen nicht existieren, das Schema importieren
    if (!$tablesExist) {
        $schemaFile = __DIR__ . '/../../db/schema.sql';
        
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $db->exec($sql);
            logMessage("Datenbankschema wurde initialisiert", 'info');
        } else {
            die('Datenbankschema nicht gefunden: ' . $schemaFile);
        }
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
    
    // Exakte IP-Übereinstimmung prüfen
    if (in_array($ip, $whitelist)) {
        return true;
    }
    
    // CIDR-Notation prüfen
    foreach ($whitelist as $whitelistedIp) {
        if (strpos($whitelistedIp, '/') !== false) {
            if (ipInCidrRange($ip, $whitelistedIp)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Prüft, ob eine IP-Adresse in einem CIDR-Bereich liegt
 * 
 * @param string $ip Die zu prüfende IP-Adresse
 * @param string $cidr Der CIDR-Bereich (z.B. 192.168.0.0/24)
 * @return bool True, wenn die IP-Adresse im CIDR-Bereich liegt, sonst False
 */
function ipInCidrRange($ip, $cidr) {
    list($subnet, $bits) = explode('/', $cidr);
    
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet &= $mask;
    
    return ($ip & $mask) == $subnet;
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
