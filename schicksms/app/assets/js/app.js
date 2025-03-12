
/**
 * SchickSMS Haupt-JavaScript-Datei
 * 
 * Diese Datei enthält gemeinsame JavaScript-Funktionen für die gesamte Anwendung.
 */

// Warten, bis das DOM vollständig geladen ist
document.addEventListener('DOMContentLoaded', function() {
    // Toast-Benachrichtigungen initialisieren
    initToasts();
    
    // Tooltips initialisieren
    initTooltips();
    
    // CSRF-Token zu allen AJAX-Anfragen hinzufügen
    setupAjaxCsrf();
    
    // Tabs-Funktionalität
    setupTabs();
});

/**
 * Toast-Benachrichtigungen initialisieren
 */
function initToasts() {
    // Bootstrap 5 Toasts initialisieren
    const toastElList = [].slice.call(document.querySelectorAll('.toast'));
    toastElList.map(function(toastEl) {
        return new bootstrap.Toast(toastEl);
    });
}

/**
 * Tooltips initialisieren
 */
function initTooltips() {
    // Bootstrap 5 Tooltips initialisieren
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * CSRF-Token zu allen AJAX-Anfragen hinzufügen
 */
function setupAjaxCsrf() {
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (csrfToken) {
        // Event-Listener für alle Fetch-Anfragen
        const originalFetch = window.fetch;
        window.fetch = function(url, options = {}) {
            // Wenn es sich um eine POST-Anfrage handelt und noch kein CSRF-Token vorhanden ist
            if (options.method === 'POST' && options.body instanceof FormData) {
                if (!options.body.has('csrf_token')) {
                    options.body.append('csrf_token', csrfToken);
                }
            }
            
            return originalFetch(url, options);
        };
    }
}

/**
 * Tabs-Funktionalität einrichten
 */
function setupTabs() {
    const tabLinks = document.querySelectorAll('.nav-tabs .nav-link');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Aktiven Tab setzen
            tabLinks.forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');
            
            // Tab-Inhalt anzeigen
            const tabId = this.getAttribute('href').substring(1);
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active', 'show');
            });
            
            const tabPane = document.getElementById(tabId);
            if (tabPane) {
                tabPane.classList.add('active', 'show');
                
                // Tab-ID in der URL speichern
                if (history.pushState) {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.pushState({}, '', url);
                }
                
                // Benutzerdefiniertes Event auslösen, wenn ein Tab aktiviert wird
                const tabEvent = new CustomEvent('tabActivated', {
                    detail: { tabId: tabId }
                });
                document.dispatchEvent(tabEvent);
            }
        });
    });
    
    // Beim Laden der Seite den Tab aus der URL aktivieren
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    if (tabParam) {
        const tabLink = document.querySelector(`.nav-tabs .nav-link[href="#${tabParam}"]`);
        if (tabLink) {
            tabLink.click();
        }
    }
}

/**
 * Eine Toast-Benachrichtigung anzeigen
 * 
 * @param {string} message Die anzuzeigende Nachricht
 * @param {string} type Der Typ der Benachrichtigung (success, danger, warning, info)
 * @param {number} duration Die Anzeigedauer in Millisekunden (Standard: 5000)
 */
function showToast(message, type = 'info', duration = 5000) {
    // Toast-Container erstellen, falls nicht vorhanden
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    // Toast-Element erstellen
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${duration}">
            <div class="toast-header bg-${type} text-white">
                <i class="fas ${getIconForType(type)} me-2"></i>
                <strong class="me-auto">${getTypeText(type)}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Schließen"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    // Toast zum Container hinzufügen
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    // Toast initialisieren und anzeigen
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        delay: duration
    });
    
    toast.show();
    
    // Toast nach dem Ausblenden entfernen
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}

/**
 * Gibt das passende Icon für den Toast-Typ zurück
 * 
 * @param {string} type Der Typ der Benachrichtigung
 * @return {string} Die CSS-Klasse für das Icon
 */
function getIconForType(type) {
    switch (type) {
        case 'success':
            return 'fa-check-circle';
        case 'danger':
            return 'fa-exclamation-circle';
        case 'warning':
            return 'fa-exclamation-triangle';
        case 'info':
        default:
            return 'fa-info-circle';
    }
}

/**
 * Gibt den Text für den Toast-Typ zurück
 * 
 * @param {string} type Der Typ der Benachrichtigung
 * @return {string} Der Text für den Toast-Typ
 */
function getTypeText(type) {
    switch (type) {
        case 'success':
            return 'Erfolg';
        case 'danger':
            return 'Fehler';
        case 'warning':
            return 'Warnung';
        case 'info':
        default:
            return 'Information';
    }
}

/**
 * Führt eine AJAX-Anfrage aus
 * 
 * @param {string} url Die URL für die Anfrage
 * @param {Object} data Die zu sendenden Daten
 * @param {Function} successCallback Die Callback-Funktion bei Erfolg
 * @param {Function} errorCallback Die Callback-Funktion bei Fehler
 */
function ajaxRequest(url, data, successCallback, errorCallback) {
    // FormData-Objekt erstellen
    const formData = new FormData();
    
    // Daten hinzufügen
    for (const key in data) {
        formData.append(key, data[key]);
    }
    
    // CSRF-Token aus dem Meta-Tag abrufen und hinzufügen
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    // Fetch-API verwenden
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Netzwerkantwort war nicht ok');
        }
        return response.json();
    })
    .then(data => {
        if (successCallback) {
            successCallback(data);
        }
    })
    .catch(error => {
        console.error('Fehler bei der AJAX-Anfrage:', error);
        if (errorCallback) {
            errorCallback(error);
        }
    });
}

/**
 * Bestätigungsdialog anzeigen
 * 
 * @param {string} message Die anzuzeigende Nachricht
 * @param {Function} callback Die Callback-Funktion, die aufgerufen wird, wenn der Benutzer bestätigt
 */
function confirmDialog(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Formatiert ein Datum in ein lesbares Format
 * 
 * @param {string} dateString Das zu formatierende Datum
 * @return {string} Das formatierte Datum
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE');
}

/**
 * Berechnet die Anzahl der SMS-Segmente für eine Nachricht
 * 
 * @param {string} message Die Nachricht
 * @param {number} maxSingleSMS Die maximale Länge einer einzelnen SMS
 * @param {number} maxMultiSMS Die maximale Länge eines Segments in einer mehrteiligen SMS
 * @return {Object} Ein Objekt mit der Anzahl der Zeichen und der Anzahl der SMS-Segmente
 */
function calculateSmsSegments(message, maxSingleSMS = 160, maxMultiSMS = 153) {
    const length = message.length;
    
    if (length <= maxSingleSMS) {
        return {
            length: length,
            segments: 1
        };
    } else {
        const segments = 1 + Math.ceil((length - maxSingleSMS) / maxMultiSMS);
        return {
            length: length,
            segments: segments
        };
    }
}
