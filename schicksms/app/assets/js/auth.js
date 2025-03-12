/**
 * SchickSMS Authentifizierungs-Funktionalität
 * 
 * Diese Datei enthält die JavaScript-Funktionen für die Authentifizierung.
 */

// Warten, bis das DOM vollständig geladen ist
document.addEventListener('DOMContentLoaded', function() {
    // Login-Formular initialisieren
    initLoginForm();
    
    // Passwort-Änderungsformular initialisieren
    initChangePasswordForm();
    
    // Sitzungsstatus prüfen
    checkSessionStatus();
});

/**
 * Login-Formular initialisieren
 */
function initLoginForm() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) {
        return;
    }
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = document.getElementById('password').value;
        const submitButton = loginForm.querySelector('button[type="submit"]');
        
        // Validierung
        if (!password) {
            showLoginError('Bitte geben Sie Ihr Passwort ein.');
            return;
        }
        
        // Button deaktivieren und Ladeanimation anzeigen
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Anmelden...';
        
        // CSRF-Token aus dem Meta-Tag abrufen
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // FormData-Objekt erstellen
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('password', password);
        formData.append('csrf_token', csrfToken);
        
        // Fetch-API verwenden
        fetch('app/api/auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Erfolg - zur Hauptseite weiterleiten
                window.location.href = 'index.php';
            } else {
                // Fehler anzeigen
                showLoginError(data.error || 'Anmeldung fehlgeschlagen.');
                
                // Passwortfeld leeren
                document.getElementById('password').value = '';
                document.getElementById('password').focus();
            }
        })
        .catch(error => {
            showLoginError('Fehler bei der Übertragung: ' + error.message);
        })
        .finally(() => {
            // Button wieder aktivieren
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Anmelden';
        });
    });
}

/**
 * Fehlermeldung im Login-Formular anzeigen
 * 
 * @param {string} message Die Fehlermeldung
 */
function showLoginError(message) {
    const errorDiv = document.getElementById('loginError');
    if (!errorDiv) {
        return;
    }
    
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

/**
 * Passwort-Änderungsformular initialisieren
 */
function initChangePasswordForm() {
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (!changePasswordForm) {
        return;
    }
    
    changePasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const submitButton = changePasswordForm.querySelector('button[type="submit"]');
        
        // Validierung
        if (!currentPassword || !newPassword || !confirmPassword) {
            showToast('Bitte füllen Sie alle Felder aus.', 'danger');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showToast('Die neuen Passwörter stimmen nicht überein.', 'danger');
            return;
        }
        
        if (newPassword.length < 8) {
            showToast('Das neue Passwort muss mindestens 8 Zeichen lang sein.', 'danger');
            return;
        }
        
        // Button deaktivieren und Ladeanimation anzeigen
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Ändert Passwort...';
        
        // CSRF-Token aus dem Meta-Tag abrufen
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // FormData-Objekt erstellen
        const formData = new FormData();
        formData.append('action', 'change_password');
        formData.append('current_password', currentPassword);
        formData.append('new_password', newPassword);
        formData.append('confirm_password', confirmPassword);
        formData.append('csrf_token', csrfToken);
        
        // Fetch-API verwenden
        fetch('app/api/auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Erfolg
                showToast(data.message, 'success');
                
                // Formular zurücksetzen
                changePasswordForm.reset();
                
                // Nach 2 Sekunden zur Login-Seite weiterleiten
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 2000);
            } else {
                // Fehler anzeigen
                showToast(data.error || 'Fehler beim Ändern des Passworts.', 'danger');
            }
        })
        .catch(error => {
            showToast('Fehler bei der Übertragung: ' + error.message, 'danger');
        })
        .finally(() => {
            // Button wieder aktivieren
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-key me-2"></i>Passwort ändern';
        });
    });
}

/**
 * Sitzungsstatus prüfen
 */
function checkSessionStatus() {
    // Nur auf der Hauptseite prüfen, nicht auf der Login-Seite
    if (window.location.pathname.endsWith('login.php')) {
        return;
    }
    
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) {
        return;
    }
    
    // FormData-Objekt erstellen
    const formData = new FormData();
    formData.append('action', 'check_session');
    formData.append('csrf_token', csrfToken);
    
    // Fetch-API verwenden
    fetch('app/api/auth.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && !data.authenticated) {
            // Sitzung abgelaufen oder nicht authentifiziert - zur Login-Seite weiterleiten
            showSessionExpiredModal();
        }
    })
    .catch(error => {
        console.error('Fehler beim Prüfen des Sitzungsstatus:', error);
    });
}

/**
 * Modal für abgelaufene Sitzung anzeigen
 */
function showSessionExpiredModal() {
    // Modal erstellen, falls es noch nicht existiert
    const modalId = 'sessionExpiredModal';
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title" id="${modalId}Label">Sitzung abgelaufen</h5>
                        </div>
                        <div class="modal-body">
                            <p>Ihre Sitzung ist abgelaufen oder Sie sind nicht angemeldet. Bitte melden Sie sich erneut an.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="redirectToLoginButton">Zur Anmeldeseite</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById(modalId);
        
        // Event-Handler für den Redirect-Button
        document.getElementById('redirectToLoginButton').addEventListener('click', function() {
            window.location.href = 'login.php';
        });
    }
    
    // Modal anzeigen
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

/**
 * Abmelden
 */
function logout() {
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // FormData-Objekt erstellen
    const formData = new FormData();
    formData.append('action', 'logout');
    formData.append('csrf_token', csrfToken);
    
    // Fetch-API verwenden
    fetch('app/api/auth.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Zur Login-Seite weiterleiten
        window.location.href = 'login.php';
    })
    .catch(error => {
        console.error('Fehler beim Abmelden:', error);
        // Trotzdem zur Login-Seite weiterleiten
        window.location.href = 'login.php';
    });
}

// Regelmäßige Prüfung des Sitzungsstatus (alle 5 Minuten)
setInterval(checkSessionStatus, 5 * 60 * 1000);
