<?php
/**
 * SchickSMS Hauptseite
 * 
 * Dies ist die Hauptseite des SchickSMS Webinterface.
 */

// Authentifizierung erfordern
require_once 'app/includes/auth.php';
requireAuth();

// Header einbinden
include 'app/includes/header.php';
?>

<!-- Tabs-Navigation -->
<ul class="nav nav-tabs mb-4" id="mainTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link active" id="sendTabLink" data-bs-toggle="tab" href="#send" role="tab" aria-controls="send" aria-selected="true">
            <i class="fas fa-paper-plane me-2"></i>SMS senden
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="historyTabLink" data-bs-toggle="tab" href="#history" role="tab" aria-controls="history" aria-selected="false">
            <i class="fas fa-history me-2"></i>Verlauf
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="addressBookTabLink" data-bs-toggle="tab" href="#address-book" role="tab" aria-controls="address-book" aria-selected="false">
            <i class="fas fa-address-book me-2"></i>Adressbuch
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="statusTabLink" data-bs-toggle="tab" href="#status" role="tab" aria-controls="status" aria-selected="false">
            <i class="fas fa-info-circle me-2"></i>Status
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="settingsTabLink" data-bs-toggle="tab" href="#settings" role="tab" aria-controls="settings" aria-selected="false">
            <i class="fas fa-cog me-2"></i>Einstellungen
        </a>
    </li>
</ul>

<!-- Tab-Inhalte -->
<div class="tab-content" id="mainTabsContent">
    <!-- SMS senden Tab -->
    <div class="tab-pane fade show active" id="send" role="tabpanel" aria-labelledby="sendTabLink">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Neue SMS</h5>
            </div>
            <div class="card-body">
                <form id="smsForm">
                    <div class="mb-3">
                        <label for="recipient" class="form-label">Empfänger (internationale Schreibweise)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="recipient" name="recipient" placeholder="+491234567890" required>
                            <button class="btn btn-outline-secondary" type="button" id="showContactsButton" title="Kontakt auswählen">
                                <i class="fas fa-address-book"></i>
                            </button>
                        </div>
                        <div class="form-text">Beispiel: +491234567890</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Nachricht</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                        <div class="sms-counter">
                            <span id="charCount">0</span>/<span id="charLimit"><?php echo loadConfig()['gammu']['max_sms_length']; ?></span> Zeichen 
                            (<span id="smsCount">1</span> SMS)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="method" class="form-label">Versandmethode</label>
                        <select class="form-select" id="method" name="method">
                            <option value="command">Befehlsmethode (zuverlässig)</option>
                            <option value="file">Dateimethode (native)</option>
                            <option value="both">Beide Methoden testen</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <button type="button" id="sendButton" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i>SMS senden
                </button>
            </div>
        </div>
        
        <div id="result" class="alert mt-3" style="display: none;"></div>
    </div>
    
    <!-- Verlauf Tab -->
    <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="historyTabLink">
        <!-- Verlaufs-Tabs -->
        <ul class="nav nav-pills mb-3" id="historySubTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="currentTabLink" data-bs-toggle="pill" href="#currentTab" role="tab" aria-controls="currentTab" aria-selected="true">
                    <i class="fas fa-inbox me-2"></i>Aktuell
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="archiveTabLink" data-bs-toggle="pill" href="#archiveTab" role="tab" aria-controls="archiveTab" aria-selected="false">
                    <i class="fas fa-archive me-2"></i>Archiv
                </a>
            </li>
        </ul>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>SMS-Verlauf</h5>
                <div>
                    <button type="button" id="exportHistoryButton" class="btn btn-sm btn-secondary">
                        <i class="fas fa-file-export me-2"></i>Exportieren
                    </button>
                    <button type="button" id="refreshHistoryButton" class="btn btn-sm btn-primary ms-2">
                        <i class="fas fa-sync-alt me-2"></i>Aktualisieren
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="historyLoading" class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Lädt...</span>
                    </div>
                    <p class="mt-2">Lade SMS-Verlauf...</p>
                </div>
                
                <div id="historyContent" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Datum/Zeit</th>
                                    <th>Empfänger</th>
                                    <th>Nachricht</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="historyTable">
                                <!-- Hier werden die Daten dynamisch eingefügt -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="historyPagination" class="mt-3">
                        <!-- Hier wird die Pagination dynamisch eingefügt -->
                    </div>
                </div>
                
                <div id="historyEmpty" style="display: none;">
                    <!-- Hier wird eine Meldung angezeigt, wenn keine Daten vorhanden sind -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Adressbuch Tab -->
    <div class="tab-pane fade" id="address-book" role="tabpanel" aria-labelledby="addressBookTabLink">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Kontakt hinzufügen/bearbeiten</h5>
                    </div>
                    <div class="card-body">
                        <form id="contactForm">
                            <input type="hidden" id="contactId" name="id" value="">
                            <input type="hidden" id="contactEditMode" name="editMode" value="add">
                            
                            <div class="mb-3">
                                <label for="contactName" class="form-label">Name</label>
                                <input type="text" class="form-control" id="contactName" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="contactNumber" class="form-label">Telefonnummer (international)</label>
                                <input type="text" class="form-control" id="contactNumber" name="number" placeholder="+491234567890" required>
                                <div class="form-text">Beispiel: +491234567890</div>
                            </div>
                            
                            <div class="d-flex">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Speichern
                                </button>
                                <button type="button" id="cancelEditButton" class="btn btn-secondary ms-2" style="display: none;">
                                    <i class="fas fa-times me-2"></i>Abbrechen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-import me-2"></i>Kontakte importieren</h5>
                    </div>
                    <div class="card-body">
                        <p>Importieren Sie Kontakte aus der alten JSON-Datei.</p>
                        <button type="button" id="migrateContactsButton" class="btn btn-secondary">
                            <i class="fas fa-file-import me-2"></i>Kontakte migrieren
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-address-book me-2"></i>Kontaktliste</h5>
                        <button type="button" id="refreshContactsButton" class="btn btn-sm btn-primary">
                            <i class="fas fa-sync-alt me-2"></i>Aktualisieren
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="contactSearch" placeholder="Suchen...">
                        </div>
                        
                        <div id="contactsLoading" class="text-center py-4">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Lädt...</span>
                            </div>
                            <p class="mt-2">Lade Kontakte...</p>
                        </div>
                        
                        <div id="contactsContainer" style="display: none;">
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
                                        <!-- Hier werden die Daten dynamisch eingefügt -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <div id="contactsPagination" class="mt-3">
                                <!-- Hier wird die Pagination dynamisch eingefügt -->
                            </div>
                        </div>
                        
                        <div id="contactsEmpty" style="display: none;">
                            <!-- Hier wird eine Meldung angezeigt, wenn keine Daten vorhanden sind -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Tab -->
    <div class="tab-pane fade" id="status" role="tabpanel" aria-labelledby="statusTabLink">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>System-Status</h5>
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="autoRefreshStatus">
                    <label class="form-check-label" for="autoRefreshStatus">Auto-Refresh</label>
                </div>
                <button type="button" id="refreshStatusButton" class="btn btn-sm btn-primary ms-2">
                    <i class="fas fa-sync-alt me-2"></i>Aktualisieren
                </button>
            </div>
        </div>
        
        <div id="statusLoading" class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Lädt...</span>
            </div>
            <p class="mt-2">Lade Systemstatus...</p>
        </div>
        
        <div id="statusContent" style="display: none;">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100" id="gammuStatusCard">
                        <!-- Hier wird der Gammu-Status dynamisch eingefügt -->
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card h-100" id="modemStatusCard">
                        <!-- Hier wird der Modem-Status dynamisch eingefügt -->
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card h-100" id="smsStatsCard">
                        <!-- Hier werden die SMS-Statistiken dynamisch eingefügt -->
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card h-100" id="systemInfoCard">
                        <!-- Hier werden die System-Informationen dynamisch eingefügt -->
                    </div>
                </div>
            </div>
            
            <div class="text-muted text-end">
                <small>Letztes Update: <span id="lastStatusUpdate"></span></small>
            </div>
        </div>
        
        <div id="statusError" style="display: none;">
            <!-- Hier wird eine Fehlermeldung angezeigt, wenn der Status nicht geladen werden kann -->
        </div>
    </div>
    
    <!-- Einstellungen Tab -->
    <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settingsTabLink">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i>Passwort ändern</h5>
                    </div>
                    <div class="card-body">
                        <form id="changePasswordForm">
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Aktuelles Passwort</label>
                                <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="newPassword" class="form-label">Neues Passwort</label>
                                <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                                <div class="form-text">Mindestens 8 Zeichen</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Passwort bestätigen</label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Passwort ändern
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Über</h5>
                    </div>
                    <div class="card-body">
                        <h5><?php echo htmlspecialchars($config['app']['name']); ?> v<?php echo htmlspecialchars($config['app']['version']); ?></h5>
                        <p>Ein einfaches Webinterface zum Versenden von SMS über Gammu SMSD.</p>
                        
                        <h6 class="mt-4">Funktionen:</h6>
                        <ul>
                            <li>SMS-Versand über Gammu SMSD</li>
                            <li>Adressbuchverwaltung</li>
                            <li>SMS-Verlauf mit Archivierung und Export</li>
                            <li>System-Status-Anzeige</li>
                            <li>Light/Dark Mode</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js für Diagramme -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Eigene JavaScript-Dateien -->
<script src="app/assets/js/app.js"></script>
<script src="app/assets/js/sms.js"></script>
<script src="app/assets/js/contacts.js"></script>
<script src="app/assets/js/status.js"></script>
<script src="app/assets/js/auth.js"></script>

<?php
// Footer einbinden
include 'app/includes/footer.php';
?>
