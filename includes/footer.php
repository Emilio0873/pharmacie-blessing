            </div> <!-- End of container-fluid -->
        </div> <!-- End of content -->
    </div> <!-- End of wrapper -->

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Bootstrap 5 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo app_base_url('assets/js/script.js'); ?>"></script>
    <script src="<?php echo app_base_url('assets/js/theme.js'); ?>"></script>
    <!-- Sidebar Toggle -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var toggle  = document.getElementById('sidebarCollapse');
        var overlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            if (!sidebar) return;
            sidebar.classList.add('active');
            if (overlay) overlay.classList.add('show');
            document.body.classList.add('sidebar-open');
        }
        function closeSidebar() {
            if (!sidebar) return;
            sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }

        if (toggle) {
            toggle.addEventListener('click', function() {
                if (sidebar && sidebar.classList.contains('active')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        if (sidebar) {
            sidebar.querySelectorAll('a:not([data-bs-toggle])').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) closeSidebar();
                });
            });
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) closeSidebar();
        });
    });
    </script>
</body>
</html>
