<?php
/**
 * SchickSMS Logout-Seite
 * 
 * Diese Seite beendet die Authentifizierungssitzung und leitet zur Login-Seite weiter.
 */

// Authentifizierungsfunktionen einbinden
require_once 'includes/auth.php';

// Sitzung beenden
logout();

// Zur Login-Seite weiterleiten
header('Location: login.php');
exit;
