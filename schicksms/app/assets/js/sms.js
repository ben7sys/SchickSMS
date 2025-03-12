/**
 * SchickSMS SMS-Funktionalität
 * 
 * Diese Datei enthält die JavaScript-Funktionen für den SMS-Versand und -Verlauf.
 */

// Warten, bis das DOM vollständig geladen ist
document.addEventListener('DOMContentLoaded', function() {
    // SMS-Formular initialisieren
    initSmsForm();
    
    // SMS-Verlauf initialisieren
    initSmsHistory();
    
    // Event-Listener für Tab-Aktivierung
    document.addEventListener('tabActivated', function(e) {
        if (e.detail.tabId === 'history') {
            loadSmsHistory();
        }
    });
});

/**
 * SMS-Formular initialisieren
 */
function initSmsForm() {
    const smsForm = document.getElementById('smsForm');
    const sendButton = document.getElementById('sendButton');
    const messageTextarea = document.getElementById('message');
    const recipientInput = document.getElementById('recipient');
    const charCount = document.getElementById('charCount');
    const smsCount = document.getElementById('smsCount');
    const maxSingleSMS = 160; // Standard SMS-Länge
    const maxMultiSMS = 153; // Platz für Segmentierungsinfo
    
    if (!smsForm || !sendButton || !messageTextarea || !recipientInput) {
        return;
    }
    
    // SMS-Zähler aktualisieren
    messageTextarea.addEventListener('input', function() {
        updateSmsCounter(this.value, charCount, smsCount, maxSingleSMS, maxMultiSMS);
    });
    
    // SMS senden
    smsForm.addEventListener('submit', function(e) {
        e.preventDefault();
        sendSms();
    });
    
    // Alternativ über den Button
    sendButton.addEventListener('click', function() {
        sendSms();
    });
    
    // Kontaktauswahl-Button
    const contactButton = document.getElementById('showContactsButton');
    if (contactButton) {
        contactButton.addEventListener('click', function() {
            showContactSelector(function(contact) {
                recipientInput.value = contact.number;
            });
        });
    }
}

/**
 * SMS-Zähler aktualisieren
 * 
 * @param {string} message Die Nachricht
 * @param {HTMLElement} charCountElement Das Element für die Zeichenanzahl
 * @param {HTMLElement} smsCountElement Das Element für die SMS-Anzahl
 * @param {number} maxSingleSMS Die maximale Länge einer einzelnen SMS
 * @param {number} maxMultiSMS Die maximale Länge eines Segments in einer mehrteiligen SMS
 */
function updateSmsCounter(message, charCountElement, smsCountElement, maxSingleSMS, maxMultiSMS) {
    const length = message.length;
    charCountElement.textContent = length;
    
    // SMS-Anzahl berechnen
    let segments = 1;
    if (length > maxSingleSMS) {
        segments = 1 + Math.ceil((length - maxSingleSMS) / maxMultiSMS);
    }
    
    smsCountElement.textContent = segments;
    
    // Warnfarbe bei Überschreitung
    if (length > maxSingleSMS) {
        charCountElement.style.color = '#f39c12';
    } else {
        charCountElement.style.color = '';
    }
}

/**
 * SMS senden
 */
function sendSms() {
    const recipient = document.getElementById('recipient').value.trim();
    const message = document.getElementById('message').value.trim();
    const method = document.getElementById('method') ? document.getElementById('method').value : 'command';
    const sendButton = document.getElementById('sendButton');
    const resultDiv = document.getElementById('result');
    
    // Validierung
    if (!recipient || !message) {
        showToast('Bitte geben Sie Empfänger und Nachricht ein.', 'danger');
        return;
    }
    
    // Button deaktivieren und Ladeanimation anzeigen
    sendButton.disabled = true;
    sendButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sendet...';
    
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // FormData-Objekt erstellen
    const formData = new FormData();
    formData.append('action', 'send_sms');
    formData.append('recipient', recipient);
    formData.append('message', message);
    formData.append('method', method);
    formData.append('csrf_token', csrfToken);
    
    // Fetch-API verwenden
    fetch('app/api/sms.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Erfolg
            showToast('SMS erfolgreich gesendet!', 'success');
            
            // Formular zurücksetzen
            document.getElementById('smsForm').reset();
            document.getElementById('charCount').textContent = '0';
            document.getElementById('smsCount').textContent = '1';
            
            // SMS-Verlauf aktualisieren, falls sichtbar
            if (document.getElementById('history').classList.contains('active')) {
                loadSmsHistory();
            }
        } else {
            // Fehler
            showToast('Fehler: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        showToast('Fehler bei der Übertragung: ' + error.message, 'danger');
    })
    .finally(() => {
        // Button wieder aktivieren
        sendButton.disabled = false;
        sendButton.innerHTML = '<i class="fas fa-paper-plane me-2"></i>SMS senden';
    });
}

/**
 * SMS-Verlauf initialisieren
 */
function initSmsHistory() {
    // Aktualisieren-Button
    const refreshButton = document.getElementById('refreshHistoryButton');
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            loadSmsHistory();
        });
    }
    
    // Archiv-Tab
    const archiveTabLink = document.getElementById('archiveTabLink');
    if (archiveTabLink) {
        archiveTabLink.addEventListener('click', function() {
            loadSmsHistory(true);
        });
    }
    
    // Export-Button
    const exportButton = document.getElementById('exportHistoryButton');
    if (exportButton) {
        exportButton.addEventListener('click', function() {
            exportSmsHistory();
        });
    }
}

/**
 * SMS-Verlauf laden
 * 
 * @param {boolean} archived Ob archivierte SMS geladen werden sollen
 * @param {number} page Die Seitennummer (beginnend bei 1)
 */
function loadSmsHistory(archived = false, page = 1) {
    const historyLoading = document.getElementById('historyLoading');
    const historyContent = document.getElementById('historyContent');
    const historyEmpty = document.getElementById('historyEmpty');
    const historyTable = document.getElementById('historyTable');
    const historyPagination = document.getElementById('historyPagination');
    
    if (!historyLoading || !historyContent || !historyEmpty || !historyTable) {
        return;
    }
    
    // Lade-Anzeige
    historyLoading.style.display = 'block';
    historyContent.style.display = 'none';
    historyEmpty.style.display = 'none';
    
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Einträge pro Seite
    const limit = 10;
    const offset = (page - 1) * limit;
    
    // FormData-Objekt erstellen
    const formData = new FormData();
    formData.append('action', 'get_sms_history');
    formData.append('archived', archived ? '1' : '0');
    formData.append('limit', limit);
    formData.append('offset', offset);
    formData.append('csrf_token', csrfToken);
    
        // Fetch-API verwenden
        fetch('app/api/sms.php', {
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
                
                const statusClass = sms.status === 'Gesendet' ? 'success' : 'danger';
                
                row.innerHTML = `
                    <td>${formatDate(sms.sent_at)}</td>
                    <td>${sms.recipient}</td>
                    <td class="text-truncate" style="max-width: 200px;">${sms.message}</td>
                    <td><span class="badge bg-${statusClass}">${sms.status}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-info view-sms" data-id="${sms.id}" title="Details anzeigen">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-warning ${archived ? 'unarchive-sms' : 'archive-sms'}" data-id="${sms.id}" title="${archived ? 'Wiederherstellen' : 'Archivieren'}">
                            <i class="fas ${archived ? 'fa-box-open' : 'fa-archive'}"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger delete-sms" data-id="${sms.id}" title="Löschen">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                
                historyTable.appendChild(row);
            });
            
            // Event-Handler für Buttons hinzufügen
            addSmsButtonHandlers();
            
            // Pagination erstellen
            if (historyPagination) {
                createPagination(historyPagination, data.total, limit, page, function(newPage) {
                    loadSmsHistory(archived, newPage);
                });
            }
        } else {
            historyEmpty.style.display = 'block';
            historyEmpty.innerHTML = '<p class="text-center">Keine SMS im Verlauf gefunden.</p>';
        }
    })
    .catch(error => {
        historyLoading.style.display = 'none';
        historyEmpty.style.display = 'block';
        historyEmpty.innerHTML = `<p class="text-center text-danger">Fehler beim Laden des Verlaufs: ${error.message}</p>`;
    });
}

/**
 * Event-Handler für SMS-Buttons hinzufügen
 */
function addSmsButtonHandlers() {
    // Details anzeigen
    document.querySelectorAll('.view-sms').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            viewSmsDetails(id);
        });
    });
    
    // Archivieren
    document.querySelectorAll('.archive-sms').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            archiveSms(id);
        });
    });
    
    // Wiederherstellen
    document.querySelectorAll('.unarchive-sms').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            unarchiveSms(id);
        });
    });
    
    // Löschen
    document.querySelectorAll('.delete-sms').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            deleteSms(id);
        });
    });
}

/**
 * SMS-Details anzeigen
 * 
 * @param {number} id Die ID der SMS
 */
function viewSmsDetails(id) {
    // Hier würde man die Details der SMS abrufen und in einem Modal anzeigen
    // Da wir die Details bereits in der Tabelle haben, simulieren wir das hier
    
    const row = document.querySelector(`.view-sms[data-id="${id}"]`).closest('tr');
    const recipient = row.cells[1].textContent;
    const message = row.cells[2].textContent;
    const status = row.cells[3].textContent;
    const date = row.cells[0].textContent;
    
    // Modal erstellen
    const modalId = 'smsDetailModal';
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        // Modal erstellen, falls es noch nicht existiert
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="${modalId}Label">SMS-Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Empfänger:</label>
                                <div id="smsDetailRecipient" class="form-control-plaintext"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Datum:</label>
                                <div id="smsDetailDate" class="form-control-plaintext"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status:</label>
                                <div id="smsDetailStatus" class="form-control-plaintext"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nachricht:</label>
                                <div id="smsDetailMessage" class="form-control-plaintext border p-2 bg-light" style="white-space: pre-wrap;"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById(modalId);
    }
    
    // Modal-Inhalt aktualisieren
    document.getElementById('smsDetailRecipient').textContent = recipient;
    document.getElementById('smsDetailDate').textContent = date;
    document.getElementById('smsDetailStatus').textContent = status;
    document.getElementById('smsDetailMessage').textContent = message;
    
    // Modal anzeigen
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

/**
 * SMS archivieren
 * 
 * @param {number} id Die ID der SMS
 */
function archiveSms(id) {
    confirmDialog('Möchten Sie diese SMS wirklich archivieren?', function() {
        // CSRF-Token aus dem Meta-Tag abrufen
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // FormData-Objekt erstellen
        const formData = new FormData();
        formData.append('action', 'archive_sms');
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);
        
        // Fetch-API verwenden
        fetch('app/api/sms.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('SMS erfolgreich archiviert.', 'success');
                loadSmsHistory();
            } else {
                showToast('Fehler: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            showToast('Fehler bei der Übertragung: ' + error.message, 'danger');
        });
    });
}

/**
 * SMS wiederherstellen
 * 
 * @param {number} id Die ID der SMS
 */
function unarchiveSms(id) {
    confirmDialog('Möchten Sie diese SMS wirklich wiederherstellen?', function() {
        // CSRF-Token aus dem Meta-Tag abrufen
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // FormData-Objekt erstellen
        const formData = new FormData();
        formData.append('action', 'unarchive_sms');
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);
        
        // Fetch-API verwenden
        fetch('app/api/sms.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('SMS erfolgreich wiederhergestellt.', 'success');
                loadSmsHistory(true);
            } else {
                showToast('Fehler: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            showToast('Fehler bei der Übertragung: ' + error.message, 'danger');
        });
    });
}

/**
 * SMS löschen
 * 
 * @param {number} id Die ID der SMS
 */
function deleteSms(id) {
    confirmDialog('Möchten Sie diese SMS wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.', function() {
        // CSRF-Token aus dem Meta-Tag abrufen
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // FormData-Objekt erstellen
        const formData = new FormData();
        formData.append('action', 'delete_sms');
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);
        
    // Fetch-API verwenden
    fetch('app/api/sms.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('SMS erfolgreich gelöscht.', 'success');
                
                // Aktuellen Tab ermitteln
                const isArchiveTab = document.querySelector('#archiveTab.active') !== null;
                loadSmsHistory(isArchiveTab);
            } else {
                showToast('Fehler: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            showToast('Fehler bei der Übertragung: ' + error.message, 'danger');
        });
    });
}

/**
 * SMS-Verlauf exportieren
 */
function exportSmsHistory() {
    // Aktuellen Tab ermitteln
    const isArchiveTab = document.querySelector('#archiveTab.active') !== null;
    
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // FormData-Objekt erstellen
    const formData = new FormData();
    formData.append('action', 'export_sms');
    formData.append('archived', isArchiveTab ? '1' : '0');
    formData.append('csrf_token', csrfToken);
    
    // Fetch-API verwenden
    fetch('app/api/sms.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('SMS-Verlauf erfolgreich exportiert.', 'success');
            
            // Download-Link erstellen
            const link = document.createElement('a');
            link.href = data.filepath;
            link.download = data.filename;
            link.click();
        } else {
            showToast('Fehler: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        showToast('Fehler bei der Übertragung: ' + error.message, 'danger');
    });
}

/**
 * Pagination erstellen
 * 
 * @param {HTMLElement} container Das Container-Element für die Pagination
 * @param {number} total Die Gesamtanzahl der Einträge
 * @param {number} limit Die Anzahl der Einträge pro Seite
 * @param {number} currentPage Die aktuelle Seite
 * @param {Function} callback Die Callback-Funktion, die bei Seitenwechsel aufgerufen wird
 */
function createPagination(container, total, limit, currentPage, callback) {
    const totalPages = Math.ceil(total / limit);
    
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<ul class="pagination justify-content-center">';
    
    // Zurück-Button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Zurück">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
    `;
    
    // Seitenzahlen
    const maxPages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
    let endPage = Math.min(totalPages, startPage + maxPages - 1);
    
    if (endPage - startPage + 1 < maxPages) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>
        `;
    }
    
    // Weiter-Button
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Weiter">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    `;
    
    html += '</ul>';
    
    container.innerHTML = html;
    
    // Event-Handler für Pagination-Links
    container.querySelectorAll('.page-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const page = parseInt(this.getAttribute('data-page'));
            
            if (page >= 1 && page <= totalPages && page !== currentPage) {
                callback(page);
            }
        });
    });
}

/**
 * Kontaktauswahl anzeigen
 * 
 * @param {Function} callback Die Callback-Funktion, die bei Auswahl eines Kontakts aufgerufen wird
 */
function showContactSelector(callback) {
    // Modal erstellen
    const modalId = 'contactSelectorModal';
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        // Modal erstellen, falls es noch nicht existiert
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="${modalId}Label">Kontakt auswählen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <input type="text" id="contactSearchInput" class="form-control" placeholder="Suchen...">
                            </div>
                            <div id="contactSelectorLoading" class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Lädt...</span>
                                </div>
                            </div>
                            <div id="contactSelectorContent" style="display: none;">
                                <div class="list-group contact-list" id="contactSelectorList"></div>
                            </div>
                            <div id="contactSelectorEmpty" style="display: none;">
                                <p class="text-center">Keine Kontakte gefunden.</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById(modalId);
        
        // Suchfunktion
        const searchInput = document.getElementById('contactSearchInput');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const contactItems = document.querySelectorAll('#contactSelectorList .list-group-item');
            
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
    }
    
    // Modal anzeigen
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Kontakte laden
    loadContactsForSelector(callback);
}

/**
 * Kontakte für die Auswahl laden
 * 
 * @param {Function} callback Die Callback-Funktion, die bei Auswahl eines Kontakts aufgerufen wird
 */
function loadContactsForSelector(callback) {
    const loading = document.getElementById('contactSelectorLoading');
    const content = document.getElementById('contactSelectorContent');
    const empty = document.getElementById('contactSelectorEmpty');
    const list = document.getElementById('contactSelectorList');
    
    if (!loading || !content || !empty || !list) {
        return;
    }
    
    // Lade-Anzeige
    loading.style.display = 'block';
    content.style.display = 'none';
    empty.style.display = 'none';
    
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // FormData-Objekt erstellen
    const formData = new FormData();
    formData.append('action', 'get_contacts');
    formData.append('csrf_token', csrfToken);
    
    // Fetch-API verwenden
    fetch('app/api/contacts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        loading.style.display = 'none';
        
        if (data.success && data.contacts && data.contacts.length > 0) {
            content.style.display = 'block';
            
            // Liste füllen
            list.innerHTML = '';
            
            data.contacts.forEach(contact => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="contact-name">${contact.name}</div>
                            <div class="contact-number">${contact.number}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                `;
                
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Modal schließen
                    bootstrap.Modal.getInstance(document.getElementById('contactSelectorModal')).hide();
                    
                    // Callback aufrufen
                    callback(contact);
                });
                
                list.appendChild(item);
            });
        } else {
            empty.style.display = 'block';
        }
    })
    .catch(error => {
        loading.style.display = 'none';
        empty.style.display = 'block';
        empty.innerHTML = `<p class="text-center text-danger">Fehler beim Laden der Kontakte: ${error.message}</p>`;
    });
}
