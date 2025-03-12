<!-- Hauptinhalt endet hier -->
    </main>
    
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">
                <?php echo htmlspecialchars($config['app']['name']); ?> v<?php echo htmlspecialchars($config['app']['version']); ?> &copy; <?php echo date('Y'); ?>
            </span>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/app.js"></script>
    
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
