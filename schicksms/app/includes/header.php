<?php
/**
 * SchickSMS Header-Template
 * 
 * Dieses Template wird am Anfang jeder Seite eingebunden.
 */

// Authentifizierung initialisieren
require_once __DIR__ . '/auth.php';

// Konfiguration laden
$config = loadConfig();

// Prüfen, ob Dark Mode aktiviert ist
$darkMode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true';
$darkModeClass = $darkMode ? 'dark-mode' : '';

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
    <title><?php echo htmlspecialchars($config['app']['name']); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="app/assets/css/styles.css">
    <?php if ($darkMode): ?>
    <link rel="stylesheet" href="app/assets/css/dark-mode.css">
    <?php endif; ?>
</head>
<body class="<?php echo $darkModeClass; ?>">
    <header class="header">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-sms me-2"></i>
                    <?php echo htmlspecialchars($config['app']['name']); ?>
                </a>
                
                <?php if (isAuthenticated()): ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="#" id="darkModeToggle">
                                <i class="fas <?php echo $darkMode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                                <span class="d-lg-none ms-2"><?php echo $darkMode ? 'Light Mode' : 'Dark Mode'; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span class="d-lg-none ms-2">Abmelden</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    
    <main class="container my-4">
        <!-- Hauptinhalt beginnt hier -->
