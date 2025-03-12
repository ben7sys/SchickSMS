<?php
/**
 * SchickSMS Status-API
 * 
 * Diese Datei enthält die API-Funktionen für den Systemstatus.
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
 * Prüft den Status des Gammu-SMSD-Dienstes
 * 
 * @return array Status-Informationen
 */
function checkGammuStatus() {
    // Befehl ausführen, um den Status zu prüfen
    $command = "sudo systemctl is-active gammu-smsd 2>&1";
    $output = [];
    $returnCode = 0;
    
    exec($command, $output, $returnCode);
    
    $status = implode("\n", $output);
    $isActive = ($status === 'active');
    
    // Wenn aktiv, weitere Informationen abrufen
    $version = '';
    $deviceInfo = '';
    
    if ($isActive) {
        // Gammu-Version abrufen
        $versionCommand = "gammu --version 2>&1";
        $versionOutput = [];
        exec($versionCommand, $versionOutput);
        $version = !empty($versionOutput) ? $versionOutput[0] : 'Unbekannt';
        
        // Geräteinformationen abrufen
        $deviceCommand = "gammu --identify 2>&1";
        $deviceOutput = [];
        exec($deviceCommand, $deviceOutput);
        $deviceInfo = implode("\n", $deviceOutput);
    }
    
    return [
        'is_active' => $isActive,
        'status' => $isActive ? 'Aktiv' : 'Inaktiv',
        'version' => $version,
        'device_info' => $deviceInfo
    ];
}

/**
 * Prüft den Status des Modems
 * 
 * @return array Status-Informationen
 */
function checkModemStatus() {
    // Befehl ausführen, um den Status zu prüfen
    $command = "gammu --monitor 1 2>&1";
    $output = [];
    $returnCode = 0;
    
    exec($command, $output, $returnCode);
    
    $status = implode("\n", $output);
    
    // Signalstärke extrahieren (falls vorhanden)
    $signalStrength = 'Unbekannt';
    $batteryLevel = 'Unbekannt';
    $networkName = 'Unbekannt';
    
    foreach ($output as $line) {
        if (strpos($line, 'Signal strength') !== false) {
            preg_match('/Signal strength: (\d+)%/', $line, $matches);
            if (isset($matches[1])) {
                $signalStrength = $matches[1] . '%';
            }
        }
        
        if (strpos($line, 'Battery') !== false) {
            preg_match('/Battery level: (\d+)%/', $line, $matches);
            if (isset($matches[1])) {
                $batteryLevel = $matches[1] . '%';
            }
        }
        
        if (strpos($line, 'Network') !== false) {
            preg_match('/Network: (.+)$/', $line, $matches);
            if (isset($matches[1])) {
                $networkName = $matches[1];
            }
        }
    }
    
    $isConnected = $returnCode === 0 && strpos($status, 'Error') === false;
    
    return [
        'is_connected' => $isConnected,
        'status' => $isConnected ? 'Verbunden' : 'Nicht verbunden',
        'signal_strength' => $signalStrength,
        'battery_level' => $batteryLevel,
        'network_name' => $networkName,
        'details' => $status
    ];
}

/**
 * Ruft Statistiken über gesendete SMS ab
 * 
 * @return array SMS-Statistiken
 */
function getSmsStatistics() {
    global $db;
    
    try {
        // Gesamtanzahl der gesendeten SMS
        $totalStmt = $db->query("SELECT COUNT(*) as total FROM sms_history");
        $total = $totalStmt->fetch()['total'];
        
        // Anzahl der erfolgreichen SMS
        $successStmt = $db->prepare("SELECT COUNT(*) as count FROM sms_history WHERE status = :status");
        $successStmt->bindValue(':status', 'Gesendet');
        $successStmt->execute();
        $success = $successStmt->fetch()['count'];
        
        // Anzahl der fehlgeschlagenen SMS
        $failedStmt = $db->prepare("SELECT COUNT(*) as count FROM sms_history WHERE status = :status");
        $failedStmt->bindValue(':status', 'Fehler');
        $failedStmt->execute();
        $failed = $failedStmt->fetch()['count'];
        
        // SMS pro Tag (letzte 7 Tage)
        $dailyStmt = $db->query("
            SELECT 
                date(sent_at) as date, 
                COUNT(*) as count 
            FROM sms_history 
            WHERE sent_at >= date('now', '-7 days') 
            GROUP BY date(sent_at) 
            ORDER BY date(sent_at)
        ");
        $daily = $dailyStmt->fetchAll();
        
        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'daily' => $daily
        ];
    } catch (PDOException $e) {
        logMessage("Fehler beim Abrufen der SMS-Statistiken: " . $e->getMessage(), 'error');
        return [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'success_rate' => 0,
            'daily' => []
        ];
    }
}

/**
 * Ruft Systeminformationen ab
 * 
 * @return array Systeminformationen
 */
function getSystemInfo() {
    // PHP-Version
    $phpVersion = phpversion();
    
    // Betriebssystem
    $os = php_uname();
    
    // Speichernutzung
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    
    // Festplattennutzung
    $diskTotal = disk_total_space('/');
    $diskFree = disk_free_space('/');
    $diskUsed = $diskTotal - $diskFree;
    $diskUsagePercent = round(($diskUsed / $diskTotal) * 100, 2);
    
    // Laufzeit
    $uptime = '';
    if (function_exists('shell_exec')) {
        $uptime = shell_exec('uptime -p');
    }
    
    return [
        'php_version' => $phpVersion,
        'os' => $os,
        'memory_usage' => formatBytes($memoryUsage),
        'memory_limit' => $memoryLimit,
        'disk_total' => formatBytes($diskTotal),
        'disk_free' => formatBytes($diskFree),
        'disk_used' => formatBytes($diskUsed),
        'disk_usage_percent' => $diskUsagePercent,
        'uptime' => $uptime
    ];
}

/**
 * Formatiert Bytes in eine lesbare Größe
 * 
 * @param int $bytes Die zu formatierenden Bytes
 * @param int $precision Die Anzahl der Nachkommastellen
 * @return string Die formatierte Größe
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
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
    
    // Systemstatus abrufen
    if (isset($_POST['action']) && $_POST['action'] === 'get_status') {
        $gammuStatus = checkGammuStatus();
        $modemStatus = checkModemStatus();
        $smsStatistics = getSmsStatistics();
        $systemInfo = getSystemInfo();
        
        jsonResponse([
            'success' => true,
            'gammu_status' => $gammuStatus,
            'modem_status' => $modemStatus,
            'sms_statistics' => $smsStatistics,
            'system_info' => $systemInfo
        ]);
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
