<?php
/**
 * SchickSMS Konfigurationsdatei
 * 
 * Diese Datei enthält alle Konfigurationseinstellungen für das SchickSMS Webinterface.
 */

// Sicherheitseinstellungen
$config = [
    // Authentifizierung
    'auth' => [
        'password_hash' => '$2y$10$YourHashHere', // Ersetzen Sie dies mit einem echten Passwort-Hash (password_hash('IhrPasswort', PASSWORD_BCRYPT))
        'session_lifetime' => 28800, // 8 Stunden in Sekunden
        'ip_whitelist' => [
            '127.0.0.1',                // Einzelne IP-Adresse
            '192.168.88.0/24',          // CIDR-Notation für ein Subnetz
            // Fügen Sie hier weitere erlaubte IP-Adressen oder Subnetze hinzu
        ],
        'brute_force_delay' => 2, // Verzögerung in Sekunden nach fehlgeschlagenen Login-Versuchen
    ],
    
    // Datenbank
    'database' => [
        'path' => __DIR__ . '/../db/schicksms.sqlite', // Pfad zur SQLite-Datenbank
    ],
    
    // Gammu-Einstellungen
    'gammu' => [
        'outbox_path' => '/var/spool/gammu/outbox/',
        'sent_path' => '/var/spool/gammu/sent/',
        'error_path' => '/var/spool/gammu/error/',
        'default_sender' => 'SchickSMS',
        'max_sms_length' => 160, // Standard SMS-Länge
    ],
    
    // Anwendungseinstellungen
    'app' => [
        'name' => 'SchickSMS',
        'version' => '1.0.0',
        'debug' => false, // Auf true setzen, um Debug-Informationen anzuzeigen
    ],
];

// Fehlerbehandlung
if ($config['app']['debug']) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// Konfiguration zurückgeben
return $config;
