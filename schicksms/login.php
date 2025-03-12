<?php
/**
 * SchickSMS Login-Seite
 * 
 * Diese Seite ermöglicht die Authentifizierung für das SchickSMS Webinterface.
 */

// Authentifizierungsfunktionen einbinden
require_once 'includes/auth.php';

// Konfiguration laden
$config = loadConfig();

// Prüfen, ob Dark Mode aktiviert ist
$darkMode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true';
$darkModeClass = $darkMode ? 'dark-mode' : '';

// Fehlermeldung initialisieren
$error = '';

// Wenn bereits authentifiziert, zur Hauptseite weiterleiten
initAuth();
if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

// Login-Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token überprüfen
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ungültige Anfrage. Bitte versuchen Sie es erneut.';
    } else {
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (authenticate($password)) {
            // Erfolgreich authentifiziert, zur Hauptseite weiterleiten
            header('Location: index.php');
            exit;
        } else {
            // Authentifizierung fehlgeschlagen
            $error = 'Ungültiges Passwort. Bitte versuchen Sie es erneut.';
        }
    }
}

// CSRF-Token generieren
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Login - <?php echo htmlspecialchars($config['app']['name']); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <?php if ($darkMode): ?>
    <link rel="stylesheet" href="assets/css/dark-mode.css">
    <?php endif; ?>
</head>
<body class="<?php echo $darkModeClass; ?>">
    <div class="login-container">
        <div class="login-card card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-sms"></i>
                </div>
                <h1><?php echo htmlspecialchars($config['app']['name']); ?></h1>
                <p>Bitte melden Sie sich an, um fortzufahren</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                <div class="alert alert-danger login-error" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Passwort" required autofocus>
                        <label for="password">Passwort</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Anmelden
                    </button>
                </form>
            </div>
            
            <div class="login-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <span><?php echo htmlspecialchars($config['app']['name']); ?> v<?php echo htmlspecialchars($config['app']['version']); ?></span>
                    
                    <a href="#" id="darkModeToggle" class="text-decoration-none">
                        <i class="fas <?php echo $darkMode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                        <span class="ms-1"><?php echo $darkMode ? 'Light Mode' : 'Dark Mode'; ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dark Mode Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Toggle Dark Mode Cookie
                    const isDarkMode = document.body.classList.contains('dark-mode');
                    document.cookie = `dark_mode=${!isDarkMode}; path=/; max-age=31536000`; // 1 Jahr
                    
                    // Seite neu laden, um den Modus zu wechseln
                    window.location.reload();
                });
            }
        });
    </script>
</body>
</html>
