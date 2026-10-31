<?php
declare(strict_types=1);
if (!defined('ROOT_PATH')) { http_response_code(404); exit; }
?>
<footer class="mt-auto border-t border-slate-200 bg-white transition-colors">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8">
            <!-- Col 1: Brand & Bio -->
            <div class="lg:col-span-2 space-y-4">
                <?php
                $footerLogo = get_setting('site_logo', '');
                $footerLogoUrl = '';
                $isFooterLogoWide = false;
                if (!empty($footerLogo)) {
                    $footerLogoPath = ROOT_PATH . '/' . ltrim($footerLogo, '/');
                    if (file_exists($footerLogoPath) || preg_match('#^https?://#i', $footerLogo)) {
                        $footerLogoUrl = preg_match('#^https?://#i', $footerLogo) ? $footerLogo : BASE_URL . '/' . ltrim($footerLogo, '/');
                        if (file_exists($footerLogoPath)) {
                            $imgSize = @getimagesize($footerLogoPath);
                            if ($imgSize && $imgSize[0] > $imgSize[1] * 1.8) {
                                $isFooterLogoWide = true;
                            }
                        }
                    }
                }
                ?>
                <a href="<?= BASE_URL ?>/" class="inline-flex items-center gap-3 group">
                    <?php if (!empty($footerLogoUrl)): ?>
                        <?php if ($isFooterLogoWide): ?>
                            <img src="<?= e(safe_url($footerLogoUrl)) ?>" alt="MarketCode Logo" class="h-10 w-auto max-w-[200px] object-contain group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <img src="<?= e(safe_url($footerLogoUrl)) ?>" alt="MarketCode Logo" class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-sm group-hover:scale-105 transition-transform duration-300">
                            <span class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 bg-clip-text text-transparent">
                                MARKET<span class="text-indigo-600">CODE</span>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform duration-300">
                            <i class="fa-solid fa-code text-lg"></i>
                        </div>
                        <span class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 bg-clip-text text-transparent">
                            MARKET<span class="text-indigo-600">CODE</span>
                        </span>
                    <?php endif; ?>
                </a>
                <p class="text-sm text-slate-600 leading-relaxed max-w-sm">
                    Hệ thống sàn giao dịch mã nguồn tự động hóa 100%. Cung cấp source code website, app mobile, script bot chất lượng cao, sạch mã độc và hỗ trợ cài đặt nhanh chóng.
                </p>
                <div class="flex items-center gap-3 pt-2">
                    <a href="https://t.me/admin_marketcode" target="_blank" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-indigo-600 hover:text-white flex items-center justify-center text-slate-600 transition-colors">
                        <i class="fa-brands fa-telegram text-base"></i>
                    </a>
                    <a href="https://facebook.com" target="_blank" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-blue-600 hover:text-white flex items-center justify-center text-slate-600 transition-colors">
                        <i class="fa-brands fa-facebook text-base"></i>
                    </a>
                    <a href="https://github.com" target="_blank" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-900 hover:text-white flex items-center justify-center text-slate-600 transition-colors">
                        <i class="fa-brands fa-github text-base"></i>
                    </a>
                </div>
            </div>

            <!-- Col 2: Danh Mục Nổi Bật -->
            <div>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 mb-4">Danh Mục Mã Nguồn</h3>
                <ul class="space-y-2.5 text-sm text-slate-600">
                    <li><a href="<?= BASE_URL ?>/categories?slug=php-laravel" class="hover:text-indigo-600 transition-colors">PHP & Laravel</a></li>
                    <li><a href="<?= BASE_URL ?>/categories?slug=nodejs-react" class="hover:text-indigo-600 transition-colors">Node.js & React</a></li>
                    <li><a href="<?= BASE_URL ?>/categories?slug=python-django" class="hover:text-indigo-600 transition-colors">Python Script & AI</a></li>
                    <li><a href="<?= BASE_URL ?>/categories?slug=mobile-apps" class="hover:text-indigo-600 transition-colors">Ứng Dụng Mobile</a></li>
                    <li><a href="<?= BASE_URL ?>/categories?slug=game-unity" class="hover:text-indigo-600 transition-colors">Game & Unity</a></li>
                </ul>
            </div>

            <!-- Col 3: Hỗ Trợ & Nạp Tiền -->
            <div>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 mb-4">Dịch Vụ Khách Hàng</h3>
                <ul class="space-y-2.5 text-sm text-slate-600">
                    <li><a href="<?= BASE_URL ?>/deposit" class="hover:text-indigo-600 transition-colors">Nạp ví MoMo Tự động</a></li>
                    <li><a href="<?= BASE_URL ?>/deposit" class="hover:text-indigo-600 transition-colors">Nạp thẻ cào 24/7</a></li>
                    <li><a href="<?= BASE_URL ?>/history" class="hover:text-indigo-600 transition-colors">Lịch sử giao dịch</a></li>
                    <li><a href="<?= BASE_URL ?>/warranty" class="hover:text-indigo-600 transition-colors">Chính sách bảo hành 100%</a></li>
                    <li><a href="<?= BASE_URL ?>/categories" class="hover:text-indigo-600 transition-colors">Bộ lọc mã nguồn chuyên sâu</a></li>
                </ul>
            </div>

            <!-- Col 4: Cổng Thanh Toán & Liên Hệ -->
            <div>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 mb-4">Cổng Thanh Toán Hỗ Trợ</h3>
                <p class="text-xs text-slate-500 mb-3">Tự động cộng tiền trong 15 giây không cần chờ duyệt:</p>
                <div class="grid grid-cols-3 gap-2">
                    <div class="p-2 rounded-xl bg-pink-50 border border-pink-200/90 flex items-center justify-center font-black text-xs text-[#A50064]">MoMo</div>
                    <div class="p-2 rounded-xl bg-slate-100 border border-slate-200/60 flex items-center justify-center font-bold text-xs text-rose-600">Viettel</div>
                    <div class="p-2 rounded-xl bg-slate-100 border border-slate-200/60 flex items-center justify-center font-bold text-xs text-blue-500">Vina</div>
                    <div class="p-2 rounded-xl bg-slate-100 border border-slate-200/60 flex items-center justify-center font-bold text-xs text-sky-600">Mobi</div>
                    <div class="p-2 rounded-xl bg-slate-100 border border-slate-200/60 flex items-center justify-center font-bold text-xs text-emerald-600">Zing</div>
                    <div class="p-2 rounded-xl bg-slate-100 border border-slate-200/60 flex items-center justify-center font-bold text-xs text-indigo-600">24/7</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                    <div>Hotline: <strong class="text-indigo-600 font-bold"><?= xss_clean(get_setting('contact_hotline', '0988.888.888')) ?></strong></div>
                    <div>Email: <strong class="text-slate-700"><?= xss_clean(get_setting('contact_email', 'support@marketcode.vn')) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="mt-12 pt-8 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
            <p>&copy; <?= date('Y') ?> <strong class="text-slate-800"><?= xss_clean($siteName) ?></strong>. All rights reserved. Bản quyền thuộc về tác giả.</p>
            <p class="flex items-center gap-4">
                <span>Thiết kế Modern Clean Light SaaS</span>
                <span>Bảo mật chuẩn OWASP</span>
            </p>
        </div>
    </div>
</footer>

<?php
// System Announcement Modal Configuration from Settings
$popupEnabled = (get_setting('popup_enabled', '1') == '1');
$popupTitle   = get_setting('popup_title', '📢 THÔNG BÁO HỆ THỐNG & KHUYẾN MÃI MỚI NHẤT');
$popupContent = get_setting('popup_content', '');
$justLoggedIn = !empty($_SESSION['just_logged_in']);
if ($justLoggedIn) {
    unset($_SESSION['just_logged_in']);
}
$currentUser = $user ?? current_user();
?>

<?php if ($popupEnabled): ?>
<!-- Modal Thông Báo Nổi Hệ Thống (Clean Light Dialog) -->
<div id="systemAnnouncementModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 opacity-0 pointer-events-none transition-all duration-300">
    <!-- Backdrop with soft blur -->
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeAnnouncementModal(false)"></div>

    <!-- Modal Window (Modern Light SaaS Card) -->
    <div id="systemAnnouncementBox" class="relative w-full max-w-2xl max-h-[90vh] flex flex-col rounded-3xl bg-white border border-slate-200 text-slate-900 shadow-2xl transform scale-95 transition-all duration-300 overflow-hidden">

        <!-- Top accent gradient bar -->
        <div class="h-1.5 w-full bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600"></div>

        <!-- Modal Header -->
        <div class="p-5 sm:p-6 pb-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600 shadow-sm">
                    <i class="fa-solid fa-bullhorn text-lg"></i>
                </div>
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-200">
                        Thông Báo Quan Trọng
                    </span>
                    <h3 class="text-base sm:text-lg font-black text-slate-900 leading-tight mt-0.5">
                        <?= xss_clean($popupTitle) ?>
                    </h3>
                </div>
            </div>
            <button type="button" onclick="closeAnnouncementModal(false)" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors" title="Đóng thông báo">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 sm:p-6 overflow-y-auto max-h-[58vh] space-y-4 text-sm text-slate-600 leading-relaxed scrollbar-thin">
            <!-- Dòng chào mừng cá nhân hóa khi đã đăng nhập -->
            <?php if ($currentUser): ?>
                <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-200/80 flex items-center gap-3.5 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-lg shrink-0">
                        <i class="fa-solid fa-sparkles"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Chào mừng trở lại</p>
                        <p class="text-sm font-bold text-slate-900">
                            Xin chào <span class="text-indigo-600 font-black"><?= xss_clean($currentUser['fullname'] ?: $currentUser['username']) ?></span>, chúc bạn một ngày làm việc hiệu quả và thành công!
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Nội dung HTML thông báo từ CSDL Settings -->
            <div class="prose max-w-none text-slate-700 space-y-3 font-normal leading-relaxed">
                <?= e($popupContent) ?>
            </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="p-4 sm:p-5 border-t border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row items-center justify-between gap-3">
            <button type="button" onclick="closeAnnouncementModal(true)" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 hover:border-slate-300 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-600 hover:text-slate-800 transition-colors flex items-center justify-center gap-2 shadow-sm">
                <i class="fa-regular fa-clock"></i>
                <span>Tắt thông báo hôm nay (24h)</span>
            </button>
            <button type="button" onclick="closeAnnouncementModal(false)" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold text-xs shadow-md shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-all flex items-center justify-center gap-2">
                <span>Đã Hiểu & Đóng</span>
                <i class="fa-solid fa-check"></i>
            </button>
        </div>
    </div>
</div>

<script>
    const announcementModal = document.getElementById('systemAnnouncementModal');
    const announcementBox = document.getElementById('systemAnnouncementBox');

    function openSystemAnnouncementModal() {
        if (!announcementModal) return;
        announcementModal.classList.remove('opacity-0', 'pointer-events-none');
        announcementModal.classList.add('opacity-100', 'pointer-events-auto');
        if (announcementBox) {
            announcementBox.classList.remove('scale-95');
            announcementBox.classList.add('scale-100');
        }
    }

    function closeAnnouncementModal(hide24h = false) {
        if (!announcementModal) return;
        if (hide24h) {
            const expiry = Date.now() + (24 * 60 * 60 * 1000);
            localStorage.setItem('hide_marketcode_announcement', expiry.toString());
        }
        if (announcementBox) {
            announcementBox.classList.remove('scale-100');
            announcementBox.classList.add('scale-95');
        }
        announcementModal.classList.remove('opacity-100', 'pointer-events-auto');
        announcementModal.classList.add('opacity-0', 'pointer-events-none');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const justLoggedIn = <?= $justLoggedIn ? 'true' : 'false' ?>;
        const hideUntil = localStorage.getItem('hide_marketcode_announcement');
        const isHidden = hideUntil && parseInt(hideUntil, 10) > Date.now();

        // Xác định trang chủ
        const path = window.location.pathname;
        const isHome = path.endsWith('/') ||
                       path.endsWith('/index.php') ||
                       path === <?= js(rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/')) ?> ||
                       path === <?= js(rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/') . '/') ?> ||
                       path === <?= js(rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/') . '/index.php') ?>;

        if (justLoggedIn) {
            setTimeout(openSystemAnnouncementModal, 600);
        } else if (isHome && !isHidden) {
            setTimeout(openSystemAnnouncementModal, 900);
        }
    });
</script>
<?php endif; ?>

<!-- Global Toast Notification Helper -->
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
        }
    });

    <?php if (!empty($_SESSION['flash_message'])): ?>
        Toast.fire({
            icon: <?= js($_SESSION['flash_message']['type'] ?? 'info') ?>,
            titleText: <?= js($_SESSION['flash_message']['title'] ?? $_SESSION['flash_message']['text'] ?? '') ?>
        });
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
</script>

<!-- AOS (Animate On Scroll) Library JS -->
<script src="<?= e(BASE_URL . '/assets/vendor/aos.js') ?>"></script>

<!-- MarketCode SaaS Micro-interactions & Animations JS -->
<script src="<?= BASE_URL ?>/assets/js/animations.js"></script>
</body>
</html>
