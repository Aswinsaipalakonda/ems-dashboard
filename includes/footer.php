    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo asset('assets/js/main.js'); ?>"></script>
    
    <?php if (isset($extraJS)) echo $extraJS; ?>
    
    <?php
    // Display flash messages
    $flash = getFlash();
    if ($flash): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo $flash['message']; ?>', '<?php echo $flash['type']; ?>');
        });
    </script>
    <?php endif; ?>
</body>
</html>
