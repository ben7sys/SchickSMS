<?php
/**
 * SchickSMS Kontakt-API
 * 
 * Diese Datei enthält die API-Funktionen für die Kontaktverwaltung.
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
 * Migriert Kontakte aus der JSON-Datei in die SQLite-Datenbank
 * 
 * @param string $jsonFile Der Pfad zur JSON-Datei
 * @return array Das Ergebnis der Migration
 */
function migrateContactsFromJson($jsonFile) {
    global $db;
    
    if (!file_exists($jsonFile)) {
        return [
            'success' => false,
            'error' => 'JSON-Datei nicht gefunden.'
        ];
    }
    
    try {
        // JSON-Datei lesen
        $jsonContent = file_get_contents($jsonFile);
        $contacts = json_decode($jsonContent, true);
        
        if (!is_array($contacts)) {
            return [
                'success' => false,
                'error' => 'Ungültiges JSON-Format.'
            ];
        }
        
        // Zähler für importierte Kontakte
        $importedCount = 0;
        $skippedCount = 0;
        
        // Kontakte in die Datenbank importieren
        $stmt = $db->prepare("
            INSERT OR IGNORE INTO contacts (name, number)
            VALUES (:name, :number)
        ");
        
        foreach ($contacts as $contact) {
            if (isset($contact['name']) && isset($contact['number'])) {
                $stmt->bindParam(':name', $contact['name']);
                $stmt->bindParam(':number', $contact['number']);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $importedCount++;
                } else {
                    $skippedCount++;
                }
            } else {
                $skippedCount++;
            }
        }
        
        return [
            'success' => true,
            'imported' => $importedCount,
            'skipped' => $skippedCount,
            'total' => count($contacts)
        ];
    } catch (PDOException $e) {
        logMessage("Fehler bei der Kontaktmigration: " . $e->getMessage(), 'error');
        return [
            'success' => false,
            'error' => 'Datenbankfehler: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        logMessage("Fehler bei der Kontaktmigration: " . $e->getMessage(), 'error');
        return [
            'success' => false,
            'error' => 'Fehler: ' . $e->getMessage()
        ];
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
    
    // Kontakte abrufen
    if (isset($_POST['action']) && $_POST['action'] === 'get_contacts') {
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        try {
            if (empty($search)) {
                // Alle Kontakte abrufen
                $stmt = $db->prepare("
                    SELECT id, name, number, created_at, updated_at
                    FROM contacts
                    ORDER BY name ASC
                    LIMIT :limit OFFSET :offset
                ");
                
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            } else {
                // Kontakte mit Suchbegriff abrufen
                $searchTerm = '%' . $search . '%';
                $stmt = $db->prepare("
                    SELECT id, name, number, created_at, updated_at
                    FROM contacts
                    WHERE name LIKE :search OR number LIKE :search
                    ORDER BY name ASC
                    LIMIT :limit OFFSET :offset
                ");
                
                $stmt->bindParam(':search', $searchTerm);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $contacts = $stmt->fetchAll();
            
            // Gesamtanzahl der Kontakte abrufen
            if (empty($search)) {
                $countStmt = $db->query("SELECT COUNT(*) as total FROM contacts");
            } else {
                $countStmt = $db->prepare("
                    SELECT COUNT(*) as total
                    FROM contacts
                    WHERE name LIKE :search OR number LIKE :search
                ");
                
                $countStmt->bindParam(':search', $searchTerm);
                $countStmt->execute();
            }
            
            $totalCount = $countStmt->fetch()['total'];
            
            jsonResponse([
                'success' => true,
                'contacts' => $contacts,
                'total' => $totalCount
            ]);
        } catch (PDOException $e) {
            logMessage("Fehler beim Abrufen der Kontakte: " . $e->getMessage(), 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Abrufen der Kontakte.'
            ]);
        }
    }
    
    // Kontakt hinzufügen
    if (isset($_POST['action']) && $_POST['action'] === 'add_contact') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $number = isset($_POST['number']) ? trim($_POST['number']) : '';
        
        // Validierung
        if (empty($name) || empty($number)) {
            jsonResponse([
                'success' => false,
                'error' => 'Name und Nummer sind erforderlich.'
            ]);
        }
        
        // Telefonnummer validieren
        if (!validatePhoneNumber($number)) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültiges Telefonnummernformat. Bitte internationale Schreibweise verwenden (z.B. +49123456789).'
            ]);
        }
        
        try {
            // Prüfen, ob die Nummer bereits existiert
            $checkStmt = $db->prepare("
                SELECT id FROM contacts WHERE number = :number
            ");
            
            $checkStmt->bindParam(':number', $number);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                // Kontakt aktualisieren
                $stmt = $db->prepare("
                    UPDATE contacts
                    SET name = :name, updated_at = CURRENT_TIMESTAMP
                    WHERE number = :number
                ");
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':number', $number);
                $stmt->execute();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Kontakt erfolgreich aktualisiert.',
                    'updated' => true
                ]);
            } else {
                // Neuen Kontakt hinzufügen
                $stmt = $db->prepare("
                    INSERT INTO contacts (name, number)
                    VALUES (:name, :number)
                ");
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':number', $number);
                $stmt->execute();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Kontakt erfolgreich hinzugefügt.',
                    'id' => $db->lastInsertId(),
                    'updated' => false
                ]);
            }
        } catch (PDOException $e) {
            logMessage("Fehler beim Hinzufügen des Kontakts: " . $e->getMessage(), 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Hinzufügen des Kontakts.'
            ]);
        }
    }
    
    // Kontakt bearbeiten
    if (isset($_POST['action']) && $_POST['action'] === 'edit_contact') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $number = isset($_POST['number']) ? trim($_POST['number']) : '';
        
        // Validierung
        if ($id <= 0 || empty($name) || empty($number)) {
            jsonResponse([
                'success' => false,
                'error' => 'ID, Name und Nummer sind erforderlich.'
            ]);
        }
        
        // Telefonnummer validieren
        if (!validatePhoneNumber($number)) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültiges Telefonnummernformat. Bitte internationale Schreibweise verwenden (z.B. +49123456789).'
            ]);
        }
        
        try {
            // Prüfen, ob die Nummer bereits von einem anderen Kontakt verwendet wird
            $checkStmt = $db->prepare("
                SELECT id FROM contacts WHERE number = :number AND id != :id
            ");
            
            $checkStmt->bindParam(':number', $number);
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Diese Telefonnummer wird bereits von einem anderen Kontakt verwendet.'
                ]);
            }
            
            // Kontakt aktualisieren
            $stmt = $db->prepare("
                UPDATE contacts
                SET name = :name, number = :number, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':number', $number);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Kontakt erfolgreich aktualisiert.'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'error' => 'Kontakt nicht gefunden oder keine Änderungen vorgenommen.'
                ]);
            }
        } catch (PDOException $e) {
            logMessage("Fehler beim Bearbeiten des Kontakts: " . $e->getMessage(), 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Bearbeiten des Kontakts.'
            ]);
        }
    }
    
    // Kontakt löschen
    if (isset($_POST['action']) && $_POST['action'] === 'delete_contact') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            jsonResponse([
                'success' => false,
                'error' => 'Ungültige Kontakt-ID.'
            ]);
        }
        
        try {
            $stmt = $db->prepare("
                DELETE FROM contacts
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Kontakt erfolgreich gelöscht.'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'error' => 'Kontakt nicht gefunden.'
                ]);
            }
        } catch (PDOException $e) {
            logMessage("Fehler beim Löschen des Kontakts: " . $e->getMessage(), 'error');
            jsonResponse([
                'success' => false,
                'error' => 'Fehler beim Löschen des Kontakts.'
            ]);
        }
    }
    
    // Kontakte aus JSON-Datei migrieren
    if (isset($_POST['action']) && $_POST['action'] === 'migrate_contacts') {
        $jsonFile = '../sms_addressbook.json';
        $result = migrateContactsFromJson($jsonFile);
        jsonResponse($result);
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
