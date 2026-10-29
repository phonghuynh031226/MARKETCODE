        </main>

        <!-- Footer -->
        <footer class="h-14 border-t border-slate-200 px-6 sm:px-8 flex items-center justify-between text-xs text-slate-500 shrink-0 bg-white">
            <span>&copy; <?= date('Y') ?> MARKETCODE.VN Management System</span>
            <span>PHP 8.x PDO • Tailwind CSS • Chart.js</span>
        </footer>
    </div>

    <!-- SweetAlert Toast & SaaS Confirmation Modal helper for Admin -->
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            },
            customClass: {
                popup: 'rounded-2xl shadow-xl border border-slate-100 font-sans text-xs bg-white/95 backdrop-blur-md'
            }
        });

        // Tự động kích hoạt Toast thông báo góc phải khi có $message hoặc $error
        <?php if (!empty($message)): ?>
            Toast.fire({
                icon: 'success',
                title: '<?= addslashes($message) ?>'
            });
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            Toast.fire({
                icon: 'error',
                title: '<?= addslashes($error) ?>'
            });
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_message'])): ?>
            Toast.fire({
                icon: '<?= $_SESSION['flash_message']['type'] ?? 'info' ?>',
                title: '<?= addslashes($_SESSION['flash_message']['text'] ?? '') ?>'
            });
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        /**
         * Modal Xác Nhận Nổi SaaS (Thay thế hoàn toàn confirm() gốc của browser)
         * Thiết kế bo góc hiện đại, icon cảnh báo, nút Hủy và nút Xác nhận nổi bật
         */
        function confirmAction(urlOrCallback, options = {}) {
            const {
                title = 'Xác Nhận Thao Tác',
                text = 'Bạn có chắc chắn muốn thực hiện hành động này?',
                icon = 'warning',
                confirmText = 'Xác Nhận Thực Hiện',
                cancelText = 'Hủy Bỏ',
                confirmColor = '#4f46e5',
                isDanger = false
            } = options;

            const actualConfirmColor = isDanger ? '#e11d48' : confirmColor;

            return Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: cancelText,
                confirmButtonColor: actualConfirmColor,
                cancelButtonColor: '#94a3b8',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-3xl p-6 sm:p-7 shadow-2xl border border-slate-100 bg-white font-sans',
                    title: 'text-lg font-black text-slate-900 pt-2',
                    htmlContainer: 'text-xs text-slate-500 leading-relaxed mt-2',
                    actions: 'gap-3 mt-6 w-full flex justify-center',
                    confirmButton: 'px-5 py-2.5 rounded-xl font-bold text-xs text-white shadow-md transition-all cursor-pointer',
                    cancelButton: 'px-5 py-2.5 rounded-xl font-bold text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all cursor-pointer'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    if (typeof urlOrCallback === 'function') {
                        urlOrCallback();
                    } else if (typeof urlOrCallback === 'string' && urlOrCallback.trim() !== '') {
                        window.location.href = urlOrCallback;
                    }
                }
            });
        }
    </script>

    <!-- MarketCode SaaS Animations JS -->
    <script src="<?= BASE_URL ?>/assets/js/animations.js"></script>
</body>
</html>
