<?php
/**
 * SchickSMS Authentifizierungs-API
 * 
 * Diese Datei enthält die API-Funktionen für die Authentifizierung.
 */

// Gemeinsame Funktionen einbinden
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Konfiguration laden
$config = loadConfig();

// API-Anfragen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Login-Anfrage
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        // CSRF-Token überprüfen
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültige Anfrage. CSRF-Token fehlt oder ist ungültig.'
            ]);
        }
        
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        // IP-Whitelist prüfen
        if (!isIpWhitelisted($_SERVER['REMOTE_ADDR'])) {
            logMessage('Login-Versuch von nicht autorisierter IP: ' . $_SERVER['REMOTE_ADDR'], 'warning');
            
            jsonResponse([
                'success' => false,
                'error' => 'Zugriff verweigert. Ihre IP-Adresse ist nicht autorisiert.'
            ]);
        }
        
        // Authentifizierung durchführen
        if (authenticate($password)) {
            jsonResponse([
                'success' => true,
                'message' => 'Erfolgreich angemeldet.'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültiges Passwort. Bitte versuchen Sie es erneut.'
            ]);
        }
    }
    
    // Logout-Anfrage
    if (isset($_POST['action']) && $_POST['action'] === 'logout') {
        // CSRF-Token überprüfen
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültige Anfrage. CSRF-Token fehlt oder ist ungültig.'
            ]);
        }
        
        // Sitzung beenden
        logout();
        
        jsonResponse([
            'success' => true,
            'message' => 'Erfolgreich abgemeldet.'
        ]);
    }
    
    // Sitzungsstatus prüfen
    if (isset($_POST['action']) && $_POST['action'] === 'check_session') {
        // CSRF-Token überprüfen
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültige Anfrage. CSRF-Token fehlt oder ist ungültig.'
            ]);
        }
        
        // Authentifizierung initialisieren
        initAuth();
        
        // Prüfen, ob der Benutzer authentifiziert ist
        $authenticated = isAuthenticated();
        $expired = isSessionExpired();
        
        if ($authenticated && !$expired) {
            // Sitzungszeit aktualisieren
            refreshSession();
            
            jsonResponse([
                'success' => true,
                'authenticated' => true,
                'message' => 'Sitzung ist gültig.'
            ]);
        } else {
            jsonResponse([
                'success' => true,
                'authenticated' => false,
                'message' => $expired ? 'Sitzung ist abgelaufen.' : 'Nicht authentifiziert.'
            ]);
        }
    }
    
    // Passwort ändern
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        // CSRF-Token überprüfen
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültige Anfrage. CSRF-Token fehlt oder ist ungültig.'
            ]);
        }
        
        // Authentifizierung erfordern
        requireAuth();
        
        $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validierung
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            jsonResponse([
                'success' => false,
                'error' => 'Alle Felder sind erforderlich.'
            ]);
        }
        
        // Passwörter müssen übereinstimmen
        if ($newPassword !== $confirmPassword) {
            jsonResponse([
                'success' => false,
                'error' => 'Die neuen Passwörter stimmen nicht überein.'
            ]);
        }
        
        // Mindestlänge prüfen
        if (strlen($newPassword) < 8) {
            jsonResponse([
                'success' => false,
                'error' => 'Das neue Passwort muss mindestens 8 Zeichen lang sein.'
            ]);
        }
        
        // Aktuelles Passwort überprüfen
        if (!password_verify($currentPassword, $config['auth']['password_hash'])) {
            jsonResponse([
                'success' => false,
                'error' => 'Das aktuelle Passwort ist falsch.'
            ]);
        }
        
        // Neues Passwort-Hash generieren
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        // Konfigurationsdatei aktualisieren
        $configFile = __DIR__ . '/../config/config.php';
        $configContent = file_get_contents($configFile);
        
        // Passwort-Hash ersetzen
        $pattern = "/('password_hash'\s*=>\s*').*?(')/";
        $replacement = "$1$newHash$2";
        $newConfigContent = preg_replace($pattern, $replacement, $configContent);
        
        if ($newConfigContent === null || $newConfigContent === $configContent) {
            logMessage('Fehler beim Aktualisieren des Passwort-Hashs in der Konfigurationsdatei', 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Aktualisieren des Passworts. Bitte wenden Sie sich an den Administrator.'
            ]);
        }
        
        // Neue Konfiguration speichern
        if (file_put_contents($configFile, $newConfigContent) === false) {
            logMessage('Fehler beim Schreiben der Konfigurationsdatei', 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Speichern des neuen Passworts. Bitte wenden Sie sich an den Administrator.'
            ]);
        }
        
        // Erfolg melden
        logMessage('Passwort erfolgreich geändert', 'info');
        jsonResponse([
            'success' => true,
            'message' => 'Passwort erfolgreich geändert. Bitte melden Sie sich mit dem neuen Passwort an.'
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
