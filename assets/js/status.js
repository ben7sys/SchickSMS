/**
 * SchickSMS Status-Funktionalität
 * 
 * Diese Datei enthält die JavaScript-Funktionen für die Statusanzeige.
 */

// Warten, bis das DOM vollständig geladen ist
document.addEventListener('DOMContentLoaded', function() {
    // Status-Anzeige initialisieren
    initStatus();
    
    // Event-Listener für Tab-Aktivierung
    document.addEventListener('tabActivated', function(e) {
        if (e.detail.tabId === 'status') {
            loadStatus();
        }
    });
});

/**
 * Status-Anzeige initialisieren
 */
function initStatus() {
    // Aktualisieren-Button
    const refreshStatusButton = document.getElementById('refreshStatusButton');
    if (refreshStatusButton) {
        refreshStatusButton.addEventListener('click', function() {
            loadStatus();
        });
    }
    
    // Auto-Refresh-Checkbox
    const autoRefreshCheckbox = document.getElementById('autoRefreshStatus');
    if (autoRefreshCheckbox) {
        autoRefreshCheckbox.addEventListener('change', function() {
            toggleAutoRefresh(this.checked);
        });
    }
}

// Timer für automatische Aktualisierung
let autoRefreshTimer = null;

/**
 * Automatische Aktualisierung ein-/ausschalten
 * 
 * @param {boolean} enabled Ob die automatische Aktualisierung aktiviert werden soll
 */
function toggleAutoRefresh(enabled) {
    // Bestehenden Timer löschen
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
    
    // Neuen Timer erstellen, wenn aktiviert
    if (enabled) {
        // Alle 30 Sekunden aktualisieren
        autoRefreshTimer = setInterval(function() {
            // Nur aktualisieren, wenn der Status-Tab aktiv ist
            if (document.getElementById('status').classList.contains('active')) {
                loadStatus();
            }
        }, 30000);
    }
}

/**
 * Status laden
 */
function loadStatus() {
    const statusLoading = document.getElementById('statusLoading');
    const statusContent = document.getElementById('statusContent');
    const statusError = document.getElementById('statusError');
    
    if (!statusLoading || !statusContent || !statusError) {
        return;
    }
    
    // Lade-Anzeige
    statusLoading.style.display = 'block';
    statusContent.style.display = 'none';
    statusError.style.display = 'none';
    
    // CSRF-Token aus dem Meta-Tag abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // FormData-Objekt erstellen
    const formData = new FormData();
    formData.append('action', 'get_status');
    formData.append('csrf_token', csrfToken);
    
    // Fetch-API verwenden
    fetch('api/status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        statusLoading.style.display = 'none';
        
        if (data.success) {
            statusContent.style.display = 'block';
            
            // Status-Informationen anzeigen
            updateGammuStatus(data.gammu_status);
            updateModemStatus(data.modem_status);
            updateSmsStatistics(data.sms_statistics);
            updateSystemInfo(data.system_info);
            
            // Letztes Update-Datum anzeigen
            document.getElementById('lastStatusUpdate').textContent = formatDate(new Date());
        } else {
            statusError.style.display = 'block';
            statusError.innerHTML = '<p class="text-center text-danger">Fehler beim Laden des Status.</p>';
        }
    })
    .catch(error => {
        statusLoading.style.display = 'none';
        statusError.style.display = 'block';
        statusError.innerHTML = `<p class="text-center text-danger">Fehler beim Laden des Status: ${error.message}</p>`;
    });
}

/**
 * Gammu-Status aktualisieren
 * 
 * @param {Object} gammuStatus Die Gammu-Status-Informationen
 */
function updateGammuStatus(gammuStatus) {
    const gammuStatusCard = document.getElementById('gammuStatusCard');
    if (!gammuStatusCard) {
        return;
    }
    
    // Status-Indikator
    const statusClass = gammuStatus.is_active ? 'status-active' : 'status-inactive';
    const statusText = gammuStatus.status;
    
    // HTML erstellen
    let html = `
        <div class="card-header">
            <h5 class="mb-0">
                <span class="status-indicator ${statusClass}"></span>
                Gammu SMSD Status
            </h5>
        </div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr>
                        <td>Status:</td>
                        <td><span class="badge bg-${gammuStatus.is_active ? 'success' : 'danger'}">${statusText}</span></td>
                    </tr>
    `;
    
    // Weitere Informationen, wenn aktiv
    if (gammuStatus.is_active) {
        html += `
                    <tr>
                        <td>Version:</td>
                        <td>${gammuStatus.version || 'Unbekannt'}</td>
                    </tr>
        `;
        
        // Geräteinformationen, wenn vorhanden
        if (gammuStatus.device_info) {
            html += `
                    <tr>
                        <td>Gerät:</td>
                        <td><pre class="mb-0 small">${gammuStatus.device_info}</pre></td>
                    </tr>
            `;
        }
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    gammuStatusCard.innerHTML = html;
}

/**
 * Modem-Status aktualisieren
 * 
 * @param {Object} modemStatus Die Modem-Status-Informationen
 */
function updateModemStatus(modemStatus) {
    const modemStatusCard = document.getElementById('modemStatusCard');
    if (!modemStatusCard) {
        return;
    }
    
    // Status-Indikator
    const statusClass = modemStatus.is_connected ? 'status-active' : 'status-inactive';
    const statusText = modemStatus.status;
    
    // HTML erstellen
    let html = `
        <div class="card-header">
            <h5 class="mb-0">
                <span class="status-indicator ${statusClass}"></span>
                Modem Status
            </h5>
        </div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr>
                        <td>Status:</td>
                        <td><span class="badge bg-${modemStatus.is_connected ? 'success' : 'danger'}">${statusText}</span></td>
                    </tr>
    `;
    
    // Weitere Informationen, wenn verbunden
    if (modemStatus.is_connected) {
        html += `
                    <tr>
                        <td>Signalstärke:</td>
                        <td>${modemStatus.signal_strength || 'Unbekannt'}</td>
                    </tr>
                    <tr>
                        <td>Batteriestand:</td>
                        <td>${modemStatus.battery_level || 'Unbekannt'}</td>
                    </tr>
                    <tr>
                        <td>Netzwerk:</td>
                        <td>${modemStatus.network_name || 'Unbekannt'}</td>
                    </tr>
        `;
    }
    
    html += `
                </tbody>
            </table>
    `;
    
    // Details-Accordion, wenn Details vorhanden sind
    if (modemStatus.details) {
        html += `
            <div class="accordion" id="modemDetailsAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="modemDetailsHeading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#modemDetailsCollapse" aria-expanded="false" aria-controls="modemDetailsCollapse">
                            Details anzeigen
                        </button>
                    </h2>
                    <div id="modemDetailsCollapse" class="accordion-collapse collapse" aria-labelledby="modemDetailsHeading" data-bs-parent="#modemDetailsAccordion">
                        <div class="accordion-body">
                            <pre class="mb-0 small">${modemStatus.details}</pre>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    html += `
        </div>
    `;
    
    modemStatusCard.innerHTML = html;
}

/**
 * SMS-Statistiken aktualisieren
 * 
 * @param {Object} smsStatistics Die SMS-Statistiken
 */
function updateSmsStatistics(smsStatistics) {
    const smsStatsCard = document.getElementById('smsStatsCard');
    if (!smsStatsCard) {
        return;
    }
    
    // HTML erstellen
    let html = `
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-bar me-2"></i>
                SMS-Statistiken
            </h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-4 text-center">
                    <div class="h1">${smsStatistics.total}</div>
                    <div>Gesamt</div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="h1 text-success">${smsStatistics.success}</div>
                    <div>Erfolgreich</div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="h1 text-danger">${smsStatistics.failed}</div>
                    <div>Fehlgeschlagen</div>
                </div>
            </div>
            
            <div class="progress mb-3">
                <div class="progress-bar bg-success" role="progressbar" style="width: ${smsStatistics.success_rate}%" aria-valuenow="${smsStatistics.success_rate}" aria-valuemin="0" aria-valuemax="100">${smsStatistics.success_rate}%</div>
            </div>
    `;
    
    // Tägliche Statistiken, wenn vorhanden
    if (smsStatistics.daily && smsStatistics.daily.length > 0) {
        html += `
            <h6 class="mt-4">SMS pro Tag (letzte 7 Tage)</h6>
            <div class="chart-container" style="position: relative; height: 200px;">
                <canvas id="smsChart"></canvas>
            </div>
        `;
    }
    
    html += `
        </div>
    `;
    
    smsStatsCard.innerHTML = html;
    
    // Chart erstellen, wenn Daten vorhanden sind
    if (smsStatistics.daily && smsStatistics.daily.length > 0) {
        createSmsChart(smsStatistics.daily);
    }
}

/**
 * SMS-Chart erstellen
 * 
 * @param {Array} dailyData Die täglichen SMS-Daten
 */
function createSmsChart(dailyData) {
    const ctx = document.getElementById('smsChart');
    if (!ctx) {
        return;
    }
    
    // Daten für das Chart vorbereiten
    const labels = [];
    const data = [];
    
    dailyData.forEach(item => {
        // Datum formatieren (YYYY-MM-DD -> DD.MM.)
        const dateParts = item.date.split('-');
        const formattedDate = `${dateParts[2]}.${dateParts[1]}.`;
        
        labels.push(formattedDate);
        data.push(item.count);
    });
    
    // Chart erstellen
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Gesendete SMS',
                data: data,
                backgroundColor: 'rgba(52, 152, 219, 0.5)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * System-Informationen aktualisieren
 * 
 * @param {Object} systemInfo Die System-Informationen
 */
function updateSystemInfo(systemInfo) {
    const systemInfoCard = document.getElementById('systemInfoCard');
    if (!systemInfoCard) {
        return;
    }
    
    // HTML erstellen
    let html = `
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-server me-2"></i>
                System-Informationen
            </h5>
        </div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr>
                        <td>Betriebssystem:</td>
                        <td>${systemInfo.os}</td>
                    </tr>
                    <tr>
                        <td>PHP-Version:</td>
                        <td>${systemInfo.php_version}</td>
                    </tr>
                    <tr>
                        <td>Laufzeit:</td>
                        <td>${systemInfo.uptime || 'Unbekannt'}</td>
                    </tr>
                    <tr>
                        <td>Speichernutzung:</td>
                        <td>${systemInfo.memory_usage} / ${systemInfo.memory_limit}</td>
                    </tr>
                    <tr>
                        <td>Festplattennutzung:</td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar ${getDiskUsageClass(systemInfo.disk_usage_percent)}" role="progressbar" style="width: ${systemInfo.disk_usage_percent}%;" aria-valuenow="${systemInfo.disk_usage_percent}" aria-valuemin="0" aria-valuemax="100">
                                    ${systemInfo.disk_usage_percent}%
                                </div>
                            </div>
                            <small class="text-muted">${systemInfo.disk_used} von ${systemInfo.disk_total} verwendet (${systemInfo.disk_free} frei)</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    systemInfoCard.innerHTML = html;
}

/**
 * CSS-Klasse für Festplattennutzung ermitteln
 * 
 * @param {number} usagePercent Der Prozentsatz der Festplattennutzung
 * @return {string} Die CSS-Klasse
 */
function getDiskUsageClass(usagePercent) {
    if (usagePercent >= 90) {
        return 'bg-danger';
    } else if (usagePercent >= 70) {
        return 'bg-warning';
    } else {
        return 'bg-success';
    }
}
