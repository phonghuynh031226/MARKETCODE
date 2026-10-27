        </main>

        <!-- Footer -->
        <footer class="h-14 border-t border-slate-200 px-6 sm:px-8 flex items-center justify-between text-xs text-slate-500 shrink-0 bg-white">
            <span>&copy; <?= date('Y') ?> MARKETCODE.VN Management System</span>
            <span>PHP 8.x PDO • Tailwind CSS • Chart.js</span>
        </footer>
    </div>

    <!-- SweetAlert Toast helper for Admin -->
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });

        <?php if (!empty($_SESSION['flash_message'])): ?>
            Toast.fire({
                icon: '<?= $_SESSION['flash_message']['type'] ?? 'info' ?>',
                title: '<?= addslashes($_SESSION['flash_message']['text'] ?? '') ?>'
            });
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>
    </script>

    <!-- MarketCode SaaS Animations JS -->
    <script src="<?= BASE_URL ?>/assets/js/animations.js"></script>
</body>
</html>
