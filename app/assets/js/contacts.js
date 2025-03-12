/**
 * SchickSMS Kontakt-Funktionalität
 * 
 * Diese Datei enthält die JavaScript-Funktionen für die Kontaktverwaltung.
 */

// Warten, bis das DOM vollständig geladen ist
document.addEventListener('DOMContentLoaded', function() {
    // Kontaktverwaltung initialisieren
    initContacts();
    
    // Event-Listener für Tab-Aktivierung
    document.addEventListener('tabActivated', function(e) {
        if (e.detail.tabId === 'address-book') {
            loadContacts();
        }
    });
});

/**
 * Kontaktverwaltung initialisieren
 */
function initContacts() {
    // Kontaktformular
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveContact();
        });
    }
    
    // Abbrechen-Button
    const cancelEditButton = document.getElementById('cancelEditButton');
    if (cancelEditButton) {
        cancelEditButton.addEventListener('click', function() {
            resetContactForm();
        });
    }
    
    // Aktualisieren-Button
    const refreshContactsButton = document.getElementById('refreshContactsButton');
    if (refreshContactsButton) {
        refreshContactsButton.addEventListener('click', function() {
            loadContacts();
        });
    }
    
    // Suchfeld
    const contactSearch = document.getElementById('contactSearch');
    if (contactSearch) {
        contactSearch.addEventListener('input', function() {
            filterContacts(this.value);
        });
    }
    
    // Migration-Button
    const migrateContactsButton = document.getElementById('migrateContactsButton');
    if (migrateContactsButton) {
        migrateContactsButton.addEventListener('click', function() {
            migrateContacts();
        });
    }
}

/**
 * Kontakte laden
 * 
 * @param {string} search Suchbegriff (optional)
 * @param {number} page Die Seitennummer (beginnend bei 1)
 */
function loadContacts(search = '', page = 1) {
    const contactsLoading = document.getElementById('contactsLoading');
    const contactsContainer = document.getElementById('contactsContainer');
    const contactsEmpty = document.getElementById('contactsEmpty');
    const contactsTable = document.getElementById('contactsTable');
    const contactsPagination = document.getElementById('contactsPagination');
    
    if (!contactsLoading || !contactsContainer || !contactsEmpty || !contactsTable) {
        return;
    }
    
    // Lade-Anzeige
    contactsLoading.style.display = 'block';
    contactsContainer.style.display = 'none';
    contactsEmpty.style.display = 'none';
    
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Einträge pro Seite
    const limit = 10;
    const offset = (page - 1) * limit;
    
    // FormData-Objekt erstellen
    const formData = new FormData();
    formData.append('action', 'get_contacts');
    formData.append('search', search);
    formData.append('limit', limit);
    formData.append('offset', offset);
    formData.append('csrf_token', csrfToken);
    
    // Fetch-API verwenden
    fetch('api/contacts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        contactsLoading.style.display = 'none';
        
        if (data.success && data.contacts && data.contacts.length > 0) {
            contactsContainer.style.display = 'block';
            
            // Tabelle füllen
            contactsTable.innerHTML = '';
            
            data.contacts.forEach(contact => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td>${contact.name}</td>
                    <td>${contact.number}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary edit-contact" data-id="${contact.id}" data-name="${contact.name}" data-number="${contact.number}" title="Bearbeiten">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger delete-contact" data-id="${contact.id}" title="Löschen">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-success use-contact" data-number="${contact.number}" title="SMS senden">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </td>
                `;
                
                contactsTable.appendChild(row);
            });
            
            // Event-Handler für Buttons hinzufügen
            addContactButtonHandlers();
            
            // Pagination erstellen
            if (contactsPagination) {
                createPagination(contactsPagination, data.total, limit, page, function(newPage) {
                    loadContacts(search, newPage);
                });
            }
        } else {
            contactsEmpty.style.display = 'block';
            contactsEmpty.innerHTML = '<p class="text-center">Keine Kontakte gefunden.</p>';
        }
    })
    .catch(error => {
        contactsLoading.style.display = 'none';
        contactsEmpty.style.display = 'block';
        contactsEmpty.innerHTML = `<p class="text-center text-danger">Fehler beim Laden der Kontakte: ${error.message}</p>`;
    });
}

/**
 * Event-Handler für Kontakt-Buttons hinzufügen
 */
function addContactButtonHandlers() {
    // Bearbeiten
    document.querySelectorAll('.edit-contact').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const number = this.getAttribute('data-number');
            
            // Formular mit Kontaktdaten füllen
            document.getElementById('contactId').value = id;
            document.getElementById('contactName').value = name;
            document.getElementById('contactNumber').value = number;
            document.getElementById('contactEditMode').value = 'edit';
            
            // Abbrechen-Button anzeigen
            document.getElementById('cancelEditButton').style.display = 'inline-block';
            
            // Zum Formular scrollen
            document.getElementById('contactForm').scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    // Löschen
    document.querySelectorAll('.delete-contact').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            deleteContact(id);
        });
    });
    
    // SMS senden
    document.querySelectorAll('.use-contact').forEach(button => {
        button.addEventListener('click', function() {
            const number = this.getAttribute('data-number');
            
            // Zum SMS-Tab wechseln und Nummer eintragen
            document.querySelector('.nav-tabs .nav-link[href="#send"]').click();
            document.getElementById('recipient').value = number;
        });
    });
}

/**
 * Kontakt speichern (hinzufügen oder bearbeiten)
 */
function saveContact() {
    const id = document.getElementById('contactId').value;
    const name = document.getElementById('contactName').value.trim();
    const number = document.getElementById('contactNumber').value.trim();
    const editMode = document.getElementById('contactEditMode').value;
    
    // Validierung
    if (!name || !number) {
        showToast('Bitte geben Sie Name und Nummer ein.', 'danger');
        return;
    }
    
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // FormData-Objekt erstellen
    const formData = new FormData();
    
    if (editMode === 'edit' && id) {
        // Kontakt bearbeiten
        formData.append('action', 'edit_contact');
        formData.append('id', id);
    } else {
        // Neuen Kontakt hinzufügen
        formData.append('action', 'add_contact');
    }
    
    formData.append('name', name);
    formData.append('number', number);
    formData.append('csrf_token', csrfToken);
    
    // Speichern-Button deaktivieren
    const saveButton = document.querySelector('#contactForm button[type="submit"]');
    saveButton.disabled = true;
    saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Speichert...';
    
    // Fetch-API verwenden
    fetch('api/contacts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Erfolg
            showToast(data.message, 'success');
            
            // Formular zurücksetzen
            resetContactForm();
            
            // Kontaktliste aktualisieren
            loadContacts();
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
        saveButton.disabled = false;
        saveButton.innerHTML = '<i class="fas fa-save me-2"></i>Speichern';
    });
}

/**
 * Kontakt löschen
 * 
 * @param {number} id Die ID des Kontakts
 */
function deleteContact(id) {
    confirmDialog('Möchten Sie diesen Kontakt wirklich löschen?', function() {
        // CSRF-Token aus dem Meta-Tag abrufen
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // FormData-Objekt erstellen
        const formData = new FormData();
        formData.append('action', 'delete_contact');
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);
        
        // Fetch-API verwenden
        fetch('api/contacts.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Kontakt erfolgreich gelöscht.', 'success');
                loadContacts();
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
 * Kontaktformular zurücksetzen
 */
function resetContactForm() {
    document.getElementById('contactForm').reset();
    document.getElementById('contactId').value = '';
    document.getElementById('contactEditMode').value = 'add';
    document.getElementById('cancelEditButton').style.display = 'none';
}

/**
 * Kontakte filtern
 * 
 * @param {string} searchTerm Der Suchbegriff
 */
function filterContacts(searchTerm) {
    // Wenn das Suchfeld leer ist, alle Kontakte anzeigen
    if (!searchTerm.trim()) {
        loadContacts();
        return;
    }
    
    // Sonst Kontakte mit dem Suchbegriff laden
    loadContacts(searchTerm.trim());
}

/**
 * Kontakte aus JSON-Datei migrieren
 */
function migrateContacts() {
    confirmDialog('Möchten Sie Kontakte aus der JSON-Datei migrieren? Bestehende Kontakte werden nicht überschrieben.', function() {
        // CSRF-Token aus dem Meta-Tag abrufen
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // FormData-Objekt erstellen
        const formData = new FormData();
        formData.append('action', 'migrate_contacts');
        formData.append('csrf_token', csrfToken);
        
        // Migration-Button deaktivieren
        const migrateButton = document.getElementById('migrateContactsButton');
        migrateButton.disabled = true;
        migrateButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Migriert...';
        
        // Fetch-API verwenden
        fetch('api/contacts.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Migration erfolgreich: ${data.imported} Kontakte importiert, ${data.skipped} übersprungen.`, 'success');
                loadContacts();
            } else {
                showToast('Fehler: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            showToast('Fehler bei der Übertragung: ' + error.message, 'danger');
        })
        .finally(() => {
            // Button wieder aktivieren
            migrateButton.disabled = false;
            migrateButton.innerHTML = '<i class="fas fa-file-import me-2"></i>Kontakte migrieren';
        });
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
