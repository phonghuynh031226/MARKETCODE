<?php
declare(strict_types=1);
$pageTitle = "Lịch Sử Giao Dịch & Đơn Hàng";
require_once __DIR__ . '/config/config.php';
require_login();

$user = current_user();
$activeTab = $_GET['tab'] ?? 'orders';
if ($activeTab === 'bank') {
    $activeTab = 'momo';
}
if (!in_array($activeTab, ['orders', 'momo', 'card'])) {
    $activeTab = 'orders';
}

$orders = [];
$bankTx = [];
$cardTx = [];

if ($db) {
    try {
        // 1. Danh sách đơn hàng mua code
        $oStmt = $db->prepare("SELECT o.*, p.title as product_title, p.slug as product_slug, p.thumbnail, p.file_size
                              FROM orders o
                              JOIN products p ON o.product_id = p.id
                              WHERE o.user_id = ?
                              ORDER BY o.id DESC");
        $oStmt->execute([$user['id']]);
        $orders = $oStmt->fetchAll();

        // 2. Lịch sử nạp Ví MoMo
        $bStmt = $db->prepare("SELECT * FROM bank_transactions WHERE user_id = ? ORDER BY id DESC");
        $bStmt->execute([$user['id']]);
        $bankTx = $bStmt->fetchAll();

        // 3. Lịch sử nạp thẻ cào
        $cStmt = $db->prepare("SELECT * FROM card_transactions WHERE user_id = ? ORDER BY id DESC");
        $cStmt->execute([$user['id']]);
        $cardTx = $cStmt->fetchAll();
    } catch (Exception $e) {}
}

// Thống kê nhanh
$totalOrdersCount = count($orders);
$totalSpentOrders = array_sum(array_column($orders, 'price'));

$successBankTx = array_filter($bankTx, fn($b) => $b['status'] === 'success');
$totalBankAmount = array_sum(array_column($successBankTx, 'amount'));

$successCardTx = array_filter($cardTx, fn($c) => $c['status'] === 'success');
$totalCardAmount = array_sum(array_column($successCardTx, 'received_amount'));

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="flex-1">
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">

    <!-- 1. HEADER TRANG LỊCH SỬ GIAO DỊCH -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 mb-1 uppercase tracking-wider">
                <i class="fa-solid fa-receipt text-indigo-500"></i>
                <span>Tài Khoản &amp; Giao Dịch</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200/80 flex items-center justify-center text-base shadow-2xs">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </span>
                <span>Lịch Sử Giao Dịch</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Theo dõi mã nguồn đã sở hữu, lịch sử nạp Ví MoMo và trạng thái gạch thẻ cào 24/7.</p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a href="<?= e(BASE_URL) ?>/deposit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs hover:shadow-indigo-500/20 flex items-center gap-1.5 transition-all cursor-pointer">
                <i class="fa-solid fa-wallet text-xs"></i>
                <span>Nạp Tiền Ngay</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/categories" class="px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs border border-slate-200 transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer">
                <i class="fa-solid fa-layer-group text-indigo-500 text-xs"></i>
                <span>Xem Mã Nguồn</span>
            </a>
        </div>
    </div>

    <!-- 2. THANH THỐNG KÊ NGANG GỌN NHẸ (3 THẺ COMPACT STATS) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <!-- Thẻ 1: Mã nguồn đã mua -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center text-base font-bold shrink-0 shadow-2xs">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <div class="min-w-0">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block truncate">Mã nguồn đã mua</span>
                    <div class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight">
                        <?= e(number_format((float)($totalSpentOrders), 0, ',', '.')) ?> đ
                    </div>
                </div>
            </div>
            <span class="text-[11px] font-bold text-purple-700 bg-purple-50 border border-purple-200 px-2.5 py-1 rounded-full shrink-0">
                <?= e($totalOrdersCount) ?> bộ code
            </span>
        </div>

        <!-- Thẻ 2: Tổng nạp Ví MoMo -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-[#A50064]/10 text-[#A50064] border border-[#A50064]/20 flex items-center justify-center text-base font-bold shrink-0 shadow-2xs">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div class="min-w-0">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block truncate">Tổng nạp Ví MoMo</span>
                    <div class="text-lg sm:text-xl font-extrabold text-emerald-600 tracking-tight">
                        +<?= e(number_format((float)($totalBankAmount), 0, ',', '.')) ?> đ
                    </div>
                </div>
            </div>
            <span class="text-[11px] font-bold text-[#A50064] bg-pink-50 border border-pink-200 px-2.5 py-1 rounded-full shrink-0">
                <?= e(count($successBankTx)) ?> GD Thành công
            </span>
        </div>

        <!-- Thẻ 3: Tổng nạp Thẻ cào -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-base font-bold shrink-0 shadow-2xs">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <div class="min-w-0">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block truncate">Tổng nạp Thẻ cào</span>
                    <div class="text-lg sm:text-xl font-extrabold text-emerald-600 tracking-tight">
                        +<?= e(number_format((float)($totalCardAmount), 0, ',', '.')) ?> đ
                    </div>
                </div>
            </div>
            <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full shrink-0">
                <?= e(count($successCardTx)) ?> Thẻ đúng
            </span>
        </div>
    </div>

    <!-- 3. GỘP THANH TAB & BẢNG DỮ LIỆU VÀO CHUNG 1 CARD HIỆN ĐẠI -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">

        <!-- Header của Card: 3 nút Tab Segmented Pill & Ô Tìm Kiếm Nhanh -->
        <div class="p-3 sm:p-4 border-b border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <!-- 3 nút Tab Segmented Pill -->
            <div class="bg-slate-200/70 p-1 rounded-xl inline-flex gap-1 overflow-x-auto shrink-0">
                <a href="<?= e(BASE_URL) ?>/history?tab=orders"
                   class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap cursor-pointer <?= e($activeTab === 'orders' ? 'bg-white text-indigo-600 shadow-2xs' : 'text-slate-600 hover:text-slate-900') ?>">
                    <span>📦</span>
                    <span>Mã nguồn đã mua</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] <?= e($activeTab === 'orders' ? 'bg-indigo-50 text-indigo-700 font-extrabold' : 'bg-slate-200 text-slate-600') ?>"><?= e(count($orders)) ?></span>
                </a>
                <a href="<?= e(BASE_URL) ?>/history?tab=momo"
                   class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap cursor-pointer <?= e($activeTab === 'momo' ? 'bg-white text-[#A50064] shadow-2xs' : 'text-slate-600 hover:text-slate-900') ?>">
                    <span>🟣</span>
                    <span>Nạp Ví MoMo</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] <?= e($activeTab === 'momo' ? 'bg-pink-50 text-[#A50064] font-extrabold' : 'bg-slate-200 text-slate-600') ?>"><?= e(count($bankTx)) ?></span>
                </a>
                <a href="<?= e(BASE_URL) ?>/history?tab=card"
                   class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap cursor-pointer <?= e($activeTab === 'card' ? 'bg-white text-emerald-600 shadow-2xs' : 'text-slate-600 hover:text-slate-900') ?>">
                    <span>💳</span>
                    <span>Nạp Thẻ cào</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] <?= e($activeTab === 'card' ? 'bg-emerald-50 text-emerald-700 font-extrabold' : 'bg-slate-200 text-slate-600') ?>"><?= e(count($cardTx)) ?></span>
                </a>
            </div>

            <!-- Ô tìm kiếm nhanh nhỏ gọn -->
            <div class="relative w-full sm:w-64 shrink-0">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="historySearchInput" onkeyup="filterHistoryTable(this.value)" placeholder="Tìm mã GD, tên code..." class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-white border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 outline-none transition-all shadow-2xs">
            </div>
        </div>

        <!-- 4. NỘI DUNG DỮ LIỆU & BẢNG BẢO ĐẢM THEO TAB -->

        <!-- TAB 1: MÃ NGUỒN ĐÃ MUA -->
        <?php if ($activeTab === 'orders'): ?>
            <?php if (!empty($orders)): ?>
                <div class="overflow-x-auto">
                    <div class="responsive-table" role="region" aria-label="Bảng dữ liệu, cuộn ngang khi cần" tabindex="0"><table data-responsive="wide" class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/40">
                                <th class="py-3 px-4">Mã Đơn</th>
                                <th class="py-3 px-4">Mã Nguồn Đã Mua</th>
                                <th class="py-3 px-4">Giá Mua</th>
                                <th class="py-3 px-4">Ngày Mua</th>
                                <th class="py-3 px-4 text-center">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody" class="divide-y divide-slate-100">
                            <?php foreach ($orders as $o): ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3.5 px-4 font-mono font-bold text-xs text-indigo-600 whitespace-nowrap">
                                        #<?= xss_clean($o['order_code']) ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= e(safe_url($o['thumbnail'])) ?>" alt="" class="w-10 h-10 rounded-xl object-cover border border-slate-200/80 shrink-0 shadow-2xs" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=100&auto=format&fit=crop&q=80';">
                                            <div class="min-w-0">
                                                <a href="<?= e(BASE_URL) ?>/product?id=<?= e(public_reference('products', (int)$o['product_id'])) ?>" class="font-bold text-xs sm:text-sm text-slate-800 hover:text-indigo-600 line-clamp-1 transition-colors">
                                                    <?= xss_clean($o['product_title']) ?>
                                                </a>
                                                <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-400">
                                                    <span>Mã số: <?= e(public_reference('products', (int)$o['product_id'])) ?></span>
                                                    <?php if (!empty($o['file_size'])): ?>
                                                        <span>•</span>
                                                        <span><?= xss_clean($o['file_size']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-extrabold text-xs sm:text-sm text-slate-900 whitespace-nowrap">
                                        <?= e(number_format((float)($o['price']), 0, ',', '.')) ?> đ
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate-500 whitespace-nowrap">
                                        <?= e(date('H:i - d/m/Y', strtotime($o['created_at']))) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <a href="<?= e(BASE_URL) ?>/download?order_id=<?= e(public_reference('orders', (int)$o['id'])) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs hover:shadow-indigo-500/20 active:scale-95 transition-all">
                                            <i class="fa-solid fa-cloud-arrow-down text-xs"></i>
                                            <span>Tải xuống ngay</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            <?php else: ?>
                <!-- Compact Empty State -->
                <div class="py-12 px-4 text-center space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center text-xl mx-auto shadow-2xs">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Bạn chưa mua mã nguồn nào</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">Hàng nghìn source code chất lượng cao, sạch mã độc đang chờ đón bạn khám phá.</p>
                    </div>
                    <div class="pt-1">
                        <a href="<?= e(BASE_URL) ?>/categories" class="inline-flex items-center gap-1.5 py-2.5 px-5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-all">
                            <span>Khám phá kho mã nguồn</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- TAB 2: NẠP VÍ MOMO -->
        <?php if ($activeTab === 'momo'): ?>
            <?php if (!empty($bankTx)): ?>
                <div class="overflow-x-auto">
                    <div class="responsive-table" role="region" aria-label="Bảng dữ liệu, cuộn ngang khi cần" tabindex="0"><table data-responsive="wide" class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/40">
                                <th class="py-3 px-4">Mã GD</th>
                                <th class="py-3 px-4">Kênh Thanh Toán</th>
                                <th class="py-3 px-4">Nội Dung Chuyển</th>
                                <th class="py-3 px-4">Số Tiền Thực Nhận</th>
                                <th class="py-3 px-4">Mã Ref ID</th>
                                <th class="py-3 px-4">Thời Gian</th>
                                <th class="py-3 px-4 text-center">Trạng Thái</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody" class="divide-y divide-slate-100">
                            <?php foreach ($bankTx as $b): ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3.5 px-4 font-mono font-bold text-xs text-slate-600 whitespace-nowrap">
                                        <?= xss_clean($b['trans_id']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-pink-50 text-[#A50064] border border-pink-200/80">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#A50064]"></span>
                                            <span>Ví MoMo</span>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-xs text-slate-800">
                                        <?= xss_clean($b['transfer_content']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-extrabold text-xs sm:text-sm text-emerald-600 whitespace-nowrap">
                                        +<?= e(number_format((float)($b['amount']), 0, ',', '.')) ?> đ
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-xs text-slate-400 whitespace-nowrap">
                                        <?= xss_clean($b['ref_transaction_id'] ?: '-') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate-500 whitespace-nowrap">
                                        <?= e(date('H:i - d/m/Y', strtotime($b['created_at']))) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <?php if ($b['status'] === 'success'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Thành công
                                            </span>
                                        <?php elseif ($b['status'] === 'pending'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                Đang xử lý
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                Thất bại
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            <?php else: ?>
                <!-- Compact Empty State -->
                <div class="py-12 px-4 text-center space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-pink-50 text-[#A50064] border border-pink-200/80 flex items-center justify-center text-xl mx-auto shadow-2xs">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Chưa có giao dịch nạp Ví MoMo</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">Nạp tiền tự động 24/7 qua Ví MoMo với mã giao dịch tự động cộng tiền trong 15 giây.</p>
                    </div>
                    <div class="pt-1">
                        <a href="<?= e(BASE_URL) ?>/deposit" class="inline-flex items-center gap-1.5 py-2.5 px-5 text-xs font-bold rounded-xl bg-[#A50064] hover:bg-[#850050] text-white shadow-xs transition-all">
                            <span>Nạp Tiền MoMo Ngay</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- TAB 3: NẠP THẺ CÀO -->
        <?php if ($activeTab === 'card'): ?>
            <?php if (!empty($cardTx)): ?>
                <div class="overflow-x-auto">
                    <div class="responsive-table" role="region" aria-label="Bảng dữ liệu, cuộn ngang khi cần" tabindex="0"><table data-responsive="wide" class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/40">
                                <th class="py-3 px-4">Mã GD</th>
                                <th class="py-3 px-4">Nhà Mạng</th>
                                <th class="py-3 px-4">Mệnh Giá Khai</th>
                                <th class="py-3 px-4">Thực Nhận</th>
                                <th class="py-3 px-4">Mã PIN / Seri</th>
                                <th class="py-3 px-4">Thời Gian</th>
                                <th class="py-3 px-4 text-center">Trạng Thái</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody" class="divide-y divide-slate-100">
                            <?php foreach ($cardTx as $c): ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3.5 px-4 font-mono font-bold text-xs text-slate-600 whitespace-nowrap">
                                        <?= xss_clean($c['trans_id']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 border border-slate-200/80 text-slate-800">
                                            <?= xss_clean($c['telco']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-xs text-slate-600 whitespace-nowrap">
                                        <?= e(number_format((float)($c['declared_amount']), 0, ',', '.')) ?> đ
                                    </td>
                                    <td class="py-3.5 px-4 font-extrabold text-xs sm:text-sm text-emerald-600 whitespace-nowrap">
                                        +<?= e(number_format((float)($c['received_amount']), 0, ',', '.')) ?> đ
                                    </td>
                                    <td class="py-3.5 px-4 text-xs font-mono text-slate-400 whitespace-nowrap">
                                        <?= e(substr($c['pin'], 0, 4) . '****') ?> / <?= e(substr($c['serial'], 0, 4) . '****') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate-500 whitespace-nowrap">
                                        <?= e(date('H:i - d/m/Y', strtotime($c['created_at']))) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <?php if ($c['status'] === 'success'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Thành công
                                            </span>
                                        <?php elseif ($c['status'] === 'error'): ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                Thất bại / Thẻ sai
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                Đang chờ xử lý
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            <?php else: ?>
                <!-- Compact Empty State -->
                <div class="py-12 px-4 text-center space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-xl mx-auto shadow-2xs">
                        <i class="fa-solid fa-credit-card"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Chưa có thẻ cào nào được nạp</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">Hỗ trợ các mạng Viettel, Vina, Mobi, Zing... Xử lý gạch thẻ tự động không cần chờ đợi.</p>
                    </div>
                    <div class="pt-1">
                        <a href="<?= e(BASE_URL) ?>/deposit" class="inline-flex items-center gap-1.5 py-2.5 px-5 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition-all">
                            <span>Nạp Thẻ Cào Ngay</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>

</div>
</main>

<script>
function filterHistoryTable(keyword) {
    keyword = keyword.toLowerCase().trim();
    const tbody = document.getElementById('historyTableBody');
    if (!tbody) return;
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(keyword) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
