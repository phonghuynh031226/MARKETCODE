<?php
$user = current_user();
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$cleanUri = rtrim($currentUri, '/');
if ($cleanUri === '') $cleanUri = '/';

$isHome       = in_array($cleanUri, ['/', '/index.php', '/index']);
$isCategories = ($cleanUri === '/categories' || str_starts_with($cleanUri, '/categories/'));
$isDeposit    = in_array($cleanUri, ['/deposit', '/recharge', '/deposit.php', '/recharge.php']) || str_starts_with($cleanUri, '/deposit/') || str_starts_with($cleanUri, '/recharge/');
$isWarranty   = in_array($cleanUri, ['/warranty', '/warranty.php']) || str_starts_with($cleanUri, '/warranty/');

// Lấy 5 thông báo hoạt động gần nhất của user
$userNotifications = [];
if ($user && $db) {
    try {
        $nStmt = $db->prepare("
            (SELECT 'order' as ntype, CONCAT('Đã mua: ', p.title) as title, o.price as amount, o.created_at 
             FROM orders o JOIN products p ON o.product_id = p.id WHERE o.user_id = ?)
            UNION ALL
            (SELECT 'bank' as ntype, CONCAT('Nạp MoMo (', bank_code, ')') as title, amount, created_at 
             FROM bank_transactions WHERE user_id = ? AND status = 'success')
            UNION ALL
            (SELECT 'card' as ntype, CONCAT('Nạp thẻ cào ', telco) as title, received_amount as amount, created_at 
             FROM card_transactions WHERE user_id = ? AND status = 'success')
            ORDER BY created_at DESC LIMIT 5
        ");
        $nStmt->execute([$user['id'], $user['id'], $user['id']]);
        $userNotifications = $nStmt->fetchAll();
    } catch (Exception $e) {}
}
$siteLogo = get_setting('site_logo', '');
$logoUrl = '';
$isWideLogo = false;
if (!empty($siteLogo)) {
    $logoPath = ROOT_PATH . '/' . ltrim($siteLogo, '/');
    if (file_exists($logoPath) || preg_match('#^https?://#i', $siteLogo)) {
        $logoUrl = preg_match('#^https?://#i', $siteLogo) ? $siteLogo : BASE_URL . '/' . ltrim($siteLogo, '/');
        if (file_exists($logoPath)) {
            $imgSize = @getimagesize($logoPath);
            if ($imgSize && $imgSize[0] > $imgSize[1] * 1.8) {
                $isWideLogo = true;
            }
        }
    }
}
?>
<header class="sticky top-0 z-50 bg-white/85 backdrop-blur-xl border-b border-slate-200/80 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <!-- Brand Logo -->
            <div class="flex items-center gap-8">
                <a href="<?= BASE_URL ?>/" class="flex items-center gap-3 group">
                    <?php if (!empty($logoUrl)): ?>
                        <?php if ($isWideLogo): ?>
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="MarketCode Logo" class="h-10 sm:h-11 w-auto max-w-[200px] object-contain group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="MarketCode Logo" class="w-11 h-11 rounded-xl object-cover border border-slate-200/80 shadow-md shadow-indigo-500/10 group-hover:scale-105 transition-transform duration-300">
                            <div class="flex flex-col">
                                <span class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 bg-clip-text text-transparent">
                                    MARKET<span class="text-indigo-600">CODE</span>
                                </span>
                                <span class="text-[10px] tracking-widest font-semibold uppercase text-slate-500">Source Marketplace</span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-indigo-600 via-violet-600 to-indigo-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform duration-300">
                            <i class="fa-solid fa-code text-xl"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 bg-clip-text text-transparent">
                                MARKET<span class="text-indigo-600">CODE</span>
                            </span>
                            <span class="text-[10px] tracking-widest font-semibold uppercase text-slate-500">Source Marketplace</span>
                        </div>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Modern Pill Container Navigation (Cụm Menu chính phong cách SaaS tối giản) -->
            <nav class="hidden lg:flex items-center bg-slate-100/80 p-1.5 rounded-full border border-slate-200/70 gap-1 shadow-inner">
                <!-- 1. Khám Phá -->
                <a href="<?= BASE_URL ?>/" class="<?= $isHome ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' ?> px-4 py-2 rounded-full text-sm font-semibold transition-all duration-200 flex items-center gap-2">
                    <i class="fa-regular fa-compass <?= $isHome ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
                    <span>Khám Phá</span>
                </a>

                <!-- 2. Danh Mục -->
                <a href="<?= BASE_URL ?>/categories" class="<?= $isCategories ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' ?> px-4 py-2 rounded-full text-sm font-semibold transition-all duration-200 flex items-center gap-2">
                    <i class="fa-solid fa-layer-group <?= $isCategories ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
                    <span>Danh Mục</span>
                </a>

                <!-- 3. Nạp Tiền -->
                <a href="<?= BASE_URL ?>/deposit" class="<?= $isDeposit ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' ?> px-4 py-2 rounded-full text-sm font-semibold transition-all duration-200 flex items-center gap-2">
                    <i class="fa-regular fa-credit-card <?= $isDeposit ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
                    <span>Nạp Tiền</span>
                </a>

                <!-- 4. Bảo Hành -->
                <a href="<?= BASE_URL ?>/warranty" class="<?= $isWarranty ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' ?> px-4 py-2 rounded-full text-sm font-semibold transition-all duration-200 flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved <?= $isWarranty ? 'text-indigo-600' : 'text-slate-400' ?>"></i>
                    <span>Bảo Hành</span>
                </a>
            </nav>

            <!-- Right Controls: Wallet, Notification, Auth Status -->
            <div class="flex items-center gap-3">
                <?php if ($user): ?>
                    <!-- Số dư người dùng -->
                    <a href="<?= BASE_URL ?>/deposit" class="hidden sm:flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-indigo-50/80 border border-indigo-200/80 text-indigo-700 text-sm font-bold shadow-sm hover:bg-indigo-100 transition-colors" title="Nạp thêm số dư">
                        <i class="fa-solid fa-wallet text-indigo-600"></i>
                        <span><?= format_money($user['balance']) ?></span>
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    </a>

                    <!-- Nút Icon Chuông Thông Báo (Bell Icon) -->
                    <div class="relative" id="notiMenuContainer">
                        <button id="notiMenuBtn" type="button" class="relative w-10 h-10 rounded-xl flex items-center justify-center text-slate-600 hover:text-indigo-600 hover:bg-slate-100 border border-slate-200 transition-colors" title="Thông báo hệ thống & hoạt động cá nhân">
                            <i class="fa-regular fa-bell text-lg"></i>
                            <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-rose-500 rounded-full ring-2 ring-white animate-pulse"></span>
                        </button>

                        <!-- Dropdown Menu Thông Báo -->
                        <div id="notiMenuDropdown" class="hidden absolute right-0 mt-3 w-80 sm:w-96 rounded-2xl bg-white shadow-2xl border border-slate-200 z-50 overflow-hidden">
                            <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-bell text-indigo-600"></i>
                                    <h4 class="text-sm font-bold text-slate-800">Thông Báo Của Bạn</h4>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-200">
                                    <?= count($userNotifications) ?> mới
                                </span>
                            </div>

                            <!-- Nút Xem Thông Báo Hệ Thống Nổi -->
                            <div class="p-3 bg-indigo-50/40 border-b border-slate-100">
                                <button type="button" onclick="openSystemAnnouncementModal()" class="w-full p-2.5 rounded-xl bg-white border border-indigo-200 hover:border-indigo-300 text-xs font-bold text-indigo-700 flex items-center justify-between transition-all shadow-sm group">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-bullhorn text-amber-500 group-hover:scale-110 transition-transform"></i>
                                        <span>Xem Thông Báo Hệ Thống & Khuyến Mãi</span>
                                    </div>
                                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                                </button>
                            </div>

                            <!-- Danh sách biến động / hoạt động -->
                            <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                                <?php if (!empty($userNotifications)): ?>
                                    <?php foreach ($userNotifications as $n): ?>
                                        <div class="p-3.5 hover:bg-slate-50 transition-colors flex items-start gap-3">
                                            <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 text-xs font-bold
                                                <?= $n['ntype'] === 'order' ? 'bg-purple-100 text-purple-600' : ($n['ntype'] === 'bank' ? 'bg-indigo-100 text-indigo-600' : 'bg-emerald-100 text-emerald-600') ?>">
                                                <?php if ($n['ntype'] === 'order'): ?>
                                                    <i class="fa-solid fa-bag-shopping"></i>
                                                <?php elseif ($n['ntype'] === 'bank'): ?>
                                                    <i class="fa-solid fa-building-columns"></i>
                                                <?php else: ?>
                                                    <i class="fa-solid fa-credit-card"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-semibold text-slate-800 line-clamp-1">
                                                    <?= xss_clean($n['title']) ?>
                                                </p>
                                                <div class="flex items-center justify-between mt-1 text-[11px]">
                                                    <span class="text-slate-400 font-mono"><?= date('H:i d/m', strtotime($n['created_at'])) ?></span>
                                                    <span class="font-bold <?= $n['ntype'] === 'order' ? 'text-slate-500' : 'text-emerald-600' ?>">
                                                        <?= $n['ntype'] === 'order' ? '-' . format_money($n['amount']) : '+' . format_money($n['amount']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-6 text-center text-xs text-slate-400">
                                        <i class="fa-regular fa-bell-slash text-2xl text-slate-300 mb-2 block"></i>
                                        Chưa có giao dịch hoặc đơn hàng mới.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Footer Dropdown -->
                            <div class="p-2.5 border-t border-slate-100 text-center bg-slate-50/70">
                                <a href="<?= BASE_URL ?>/history" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1.5 transition-colors">
                                    <span>Xem tất cả lịch sử hoạt động</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Dropdown Menu -->
                    <div class="relative" id="userMenuContainer">
                        <button id="userMenuBtn" type="button" class="flex items-center gap-2 p-1 rounded-xl hover:bg-slate-100 transition-colors border border-transparent hover:border-slate-200">
                            <img src="<?= xss_clean(get_user_avatar($user)) ?>" alt="Avatar" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=User&background=4f46e5&color=fff&bold=true';" class="w-10 h-10 rounded-xl object-cover ring-2 ring-indigo-500/30">
                            <i class="fa-solid fa-chevron-down text-xs text-slate-400"></i>
                        </button>

                        <div id="userMenuDropdown" class="hidden absolute right-0 mt-3 w-64 rounded-2xl bg-white shadow-2xl p-2 border border-slate-200 z-50">
                            <div class="px-3 py-2 border-b border-slate-100 bg-slate-50/50 rounded-xl mb-1">
                                <p class="text-sm font-bold text-slate-800 truncate"><?= xss_clean($user['fullname'] ?: $user['username']) ?></p>
                                <p class="text-xs text-slate-500 truncate">@<?= xss_clean($user['username']) ?> (<?= $user['role'] ?>)</p>
                                <div class="mt-2 text-xs font-semibold text-emerald-600 flex items-center justify-between">
                                    <span>Số dư:</span>
                                    <span class="font-bold"><?= format_money($user['balance']) ?></span>
                                </div>
                            </div>
                            <div class="py-1 space-y-0.5">
                                <?php if ($user['role'] === 'admin'): ?>
                                    <a href="<?= BASE_URL ?>/admin/" class="flex items-center gap-2.5 px-3 py-2 text-sm text-purple-700 bg-purple-50/70 hover:bg-purple-100 rounded-xl font-bold transition-colors">
                                        <i class="fa-solid fa-gauge-high"></i>Trang Quản Trị (Admin)
                                    </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>/profile" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-xl transition-colors">
                                    <i class="fa-regular fa-user"></i>Thông tin cá nhân
                                </a>
                                <a href="<?= BASE_URL ?>/history" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-xl transition-colors">
                                    <i class="fa-solid fa-clock-rotate-left"></i>Lịch sử giao dịch
                                </a>
                                <a href="<?= BASE_URL ?>/deposit" class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-600 hover:text-indigo-600 hover:bg-slate-50 rounded-xl transition-colors">
                                    <i class="fa-regular fa-credit-card"></i>Nạp tiền tài khoản
                                </a>
                            </div>
                            <div class="pt-1 mt-1 border-t border-slate-100">
                                <a href="<?= BASE_URL ?>/logout.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 rounded-xl font-medium transition-colors">
                                    <i class="fa-solid fa-arrow-right-from-bracket"></i>Đăng xuất
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Guest: Login / Register (Thiết kế phẳng & Gradient chuẩn SaaS) -->
                    <div class="flex items-center gap-2.5">
                        <a href="<?= BASE_URL ?>/login" class="text-slate-700 hover:text-indigo-600 hover:bg-slate-100 px-4 py-2 rounded-xl text-sm font-semibold transition-all">
                            Đăng nhập
                        </a>
                        <a href="<?= BASE_URL ?>/register" class="bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition-all">
                            Đăng ký
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Mobile Menu Hamburger Button -->
                <button id="mobileMenuBtn" type="button" class="lg:hidden w-10 h-10 rounded-xl flex items-center justify-center text-slate-600 hover:bg-slate-100 border border-slate-200">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Drawer Navigation -->
    <div id="mobileMenu" class="hidden lg:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-6 space-y-2">
        <a href="<?= BASE_URL ?>/" class="block px-3 py-2.5 text-base font-semibold rounded-xl <?= $isHome ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-800 hover:bg-slate-50' ?>">
            <i class="fa-regular fa-compass mr-2 <?= $isHome ? 'text-indigo-600' : 'text-slate-400' ?>"></i>Khám Phá
        </a>
        <a href="<?= BASE_URL ?>/categories" class="block px-3 py-2.5 text-base font-semibold rounded-xl <?= $isCategories ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-800 hover:bg-slate-50' ?>">
            <i class="fa-solid fa-layer-group mr-2 <?= $isCategories ? 'text-indigo-600' : 'text-slate-400' ?>"></i>Danh Mục
        </a>
        <a href="<?= BASE_URL ?>/deposit" class="block px-3 py-2.5 text-base font-semibold rounded-xl <?= $isDeposit ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-800 hover:bg-slate-50' ?>">
            <i class="fa-regular fa-credit-card mr-2 <?= $isDeposit ? 'text-indigo-600' : 'text-slate-400' ?>"></i>Nạp Tiền
        </a>
        <a href="<?= BASE_URL ?>/warranty" class="block px-3 py-2.5 text-base font-semibold rounded-xl <?= $isWarranty ? 'bg-indigo-50 text-indigo-600 font-bold' : 'text-slate-800 hover:bg-slate-50' ?>">
            <i class="fa-solid fa-shield-halved mr-2 <?= $isWarranty ? 'text-indigo-600' : 'text-slate-400' ?>"></i>Bảo Hành
        </a>
    </div>
</header>

<script>
    // Notification menu dropdown toggle
    const notiBtn = document.getElementById('notiMenuBtn');
    const notiDropdown = document.getElementById('notiMenuDropdown');
    const userBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userMenuDropdown');

    if (notiBtn && notiDropdown) {
        notiBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (userDropdown) userDropdown.classList.add('hidden');
            notiDropdown.classList.toggle('hidden');
        });
    }

    if (userBtn && userDropdown) {
        userBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (notiDropdown) notiDropdown.classList.add('hidden');
            userDropdown.classList.toggle('hidden');
        });
    }

    document.addEventListener('click', (e) => {
        if (userDropdown && !userDropdown.contains(e.target) && e.target !== userBtn && !userBtn?.contains(e.target)) {
            userDropdown.classList.add('hidden');
        }
        if (notiDropdown && !notiDropdown.contains(e.target) && e.target !== notiBtn && !notiBtn?.contains(e.target)) {
            notiDropdown.classList.add('hidden');
        }
    });

    // Mobile menu toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileBtn && mobileMenu) {
        mobileBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }
</script>
