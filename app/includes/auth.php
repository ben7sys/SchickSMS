<?php
/**
 * SchickSMS Authentifizierungsfunktionen
 * 
 * Diese Datei enthält Funktionen für die Authentifizierung und Sitzungsverwaltung.
 */

// Gemeinsame Funktionen einbinden
require_once __DIR__ . '/functions.php';

/**
 * Initialisiert die Authentifizierungssitzung
 */
function initAuth() {
    // Konfiguration laden
    $config = loadConfig();
    
    // Session-Einstellungen
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.cookie_httponly', 1);
    
    // Session-Lebensdauer setzen
    ini_set('session.gc_maxlifetime', $config['auth']['session_lifetime']);
    session_set_cookie_params($config['auth']['session_lifetime']);
    
    // Session starten, wenn noch nicht gestartet
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // IP-Whitelist prüfen
    if (!isIpWhitelisted($_SERVER['REMOTE_ADDR'])) {
        http_response_code(403);
        die('Zugriff verweigert: Ihre IP-Adresse ist nicht autorisiert.');
    }
}

/**
 * Prüft, ob ein Benutzer authentifiziert ist
 * 
 * @return bool True, wenn der Benutzer authentifiziert ist, sonst False
 */
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Authentifiziert einen Benutzer mit einem Passwort
 * 
 * @param string $password Das eingegebene Passwort
 * @return bool True bei erfolgreicher Authentifizierung, sonst False
 */
function authenticate($password) {
    $config = loadConfig();
    $passwordHash = $config['auth']['password_hash'];
    
    // Brute-Force-Schutz: Verzögerung nach fehlgeschlagenen Versuchen
    if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 0) {
        sleep($config['auth']['brute_force_delay']);
    }
    
    // Passwort überprüfen
    if (password_verify($password, $passwordHash)) {
        // Authentifizierung erfolgreich
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['login_attempts'] = 0;
        
        // Protokollieren
        logMessage('Erfolgreiche Anmeldung von IP: ' . $_SERVER['REMOTE_ADDR'], 'info');
        
        return true;
    } else {
        // Authentifizierung fehlgeschlagen
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
        }
        
        $_SESSION['login_attempts']++;
        
        // Protokollieren
        logMessage('Fehlgeschlagene Anmeldung von IP: ' . $_SERVER['REMOTE_ADDR'], 'warning');
        
        return false;
    }
}

/**
 * Beendet die Authentifizierungssitzung (Logout)
 */
function logout() {
    // Session-Variablen löschen
    $_SESSION = [];
    
    // Session-Cookie löschen
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Session zerstören
    session_destroy();
}

/**
 * Prüft, ob die Sitzung abgelaufen ist
 * 
 * @return bool True, wenn die Sitzung abgelaufen ist, sonst False
 */
function isSessionExpired() {
    if (!isset($_SESSION['login_time'])) {
        return true;
    }
    
    $config = loadConfig();
    $sessionLifetime = $config['auth']['session_lifetime'];
    
    return (time() - $_SESSION['login_time']) > $sessionLifetime;
}

/**
 * Aktualisiert die Sitzungszeit
 */
function refreshSession() {
    $_SESSION['login_time'] = time();
}

/**
 * Erfordert Authentifizierung für den Zugriff auf eine Seite
 * 
 * @param string $redirectUrl Die URL, zu der umgeleitet werden soll, wenn nicht authentifiziert
 */
function requireAuth($redirectUrl = 'login.php') {
    initAuth();
    
    if (!isAuthenticated() || isSessionExpired()) {
        // Wenn nicht authentifiziert oder Sitzung abgelaufen, zur Login-Seite umleiten
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    // Sitzungszeit aktualisieren
    refreshSession();
}

/**
 * Generiert einen Passwort-Hash
 * 
 * @param string $password Das zu hashende Passwort
 * @return string Der generierte Hash
 */
function generatePasswordHash($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}
