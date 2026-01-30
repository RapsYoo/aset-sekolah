            </div> <!-- End .content -->
        </div> <!-- End .main -->
    </div> <!-- End .wrapper -->

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo APP_URL; ?>/public/js/main.js"></script>

    <!-- Global Toast Notification -->
    <?php
        $fm = get_flash_message();
        $toastMessage = '';
        $toastType = 'info';

        // Handle explicit flash messages (session based)
        if ($fm) {
            $toastMessage = $fm['message'] ?? '';
            $toastType = $fm['type'] ?? 'info';
        }
        // Handle variable based messages (often used in these files: $success, $error)
        elseif (isset($success) && !empty($success)) {
            $toastMessage = $success;
            $toastType = 'success';
        }
        elseif (isset($error) && !empty($error)) {
            $toastMessage = $error;
            $toastType = 'danger';
        }

        $toastClass = 'text-bg-info';
        if ($toastType === 'success') $toastClass = 'text-bg-success';
        elseif ($toastType === 'danger') $toastClass = 'text-bg-danger';
        elseif ($toastType === 'warning') $toastClass = 'text-bg-warning';
    ?>
    <?php if (!empty($toastMessage)): ?>
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050">
            <div id="globalToast" class="toast align-items-center <?php echo $toastClass; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo escape($toastMessage); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const toastEl = document.getElementById('globalToast');
                if (toastEl && typeof bootstrap !== 'undefined') {
                    new bootstrap.Toast(toastEl).show();
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
