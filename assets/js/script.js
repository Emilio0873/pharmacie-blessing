/**
 * Global JavaScript for PHARMACIE BLESSING
 * Sidebar toggle lives in includes/footer.php (with overlay + resize handling).
 */

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal').forEach(function (modal) {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            try {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            } catch (e) { /* ignore */ }
        }, 5000);
    });
});
