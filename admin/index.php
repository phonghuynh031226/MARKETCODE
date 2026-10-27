<?php
// ========================================================
// 1. FALLBACK DISPATCHER CHO CLEAN URLS TRONG ADMIN
// Tự động phân phối đúng file con khi máy chủ trỏ vào admin/index.php
// ========================================================
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$adminCleanPath = trim($requestUri, '/');

if (str_starts_with($adminCleanPath, 'admin/')) {
    $sub = trim(substr($adminCleanPath, 6), '/');
    if ($sub !== '' && $sub !== 'index') {
        // Alias deposits -> transactions
        if ($sub === 'deposits') {
            $sub = 'transactions';
        }
        $targetSubFile = __DIR__ . '/' . $sub . '.php';
        if (file_exists($targetSubFile) && is_file($targetSubFile)) {
            $_SERVER['SCRIPT_NAME'] = '/admin/' . $sub . '.php';
            $_SERVER['PHP_SELF']    = '/admin/' . $sub . '.php';
            require $targetSubFile;
            exit;
        }
    }
}

// ========================================================
// 2. DASHBOARD TỔNG QUAN (ADMIN INDEX)
// ========================================================
$pageTitle = "Bảng Thống Kê Tổng Quan";
require_once __DIR__ . '/includes/admin_header.php';

// Khởi tạo các biến thống kê
$totalUsers = 0;
$totalOrders = 0;
$totalRevenue = 0.0;
$revenueToday = 0.0;
$revenueMonth = 0.0;
$recentOrders = [];
$recentTransactions = [];

// Mảng 7 ngày gần nhất cho biểu đồ
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('d/m', strtotime("-$i days"));
    $chartData[$dateKey] = 0.0;
}

if ($db) {
    try {
        // 1. Tổng thành viên
        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();

        // 2. Tổng đơn hàng mua code hoàn tất
        $totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();

        // 3. Doanh thu MoMo (bank_transactions) & Thẻ cào (card_transactions)
        $bankRev = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM bank_transactions WHERE status = 'success'")->fetchColumn();
        $cardRev = (float)$db->query("SELECT COALESCE(SUM(received_amount), 0) FROM card_transactions WHERE status = 'success'")->fetchColumn();
        $totalRevenue = $bankRev + $cardRev;

        // 4. Doanh thu hôm nay
        $todayBank = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM bank_transactions WHERE status = 'success' AND DATE(created_at) = CURDATE()")->fetchColumn();
        $todayCard = (float)$db->query("SELECT COALESCE(SUM(received_amount), 0) FROM card_transactions WHERE status = 'success' AND DATE(created_at) = CURDATE()")->fetchColumn();
        $revenueToday = $todayBank + $todayCard;

        // 5. Doanh thu tháng này
        $monthBank = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM bank_transactions WHERE status = 'success' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
        $monthCard = (float)$db->query("SELECT COALESCE(SUM(received_amount), 0) FROM card_transactions WHERE status = 'success' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
        $revenueMonth = $monthBank + $monthCard;

        // 6. Dữ liệu biểu đồ 7 ngày gần nhất
        $cBank = $db->query("SELECT DATE(created_at) as dt, SUM(amount) as total FROM bank_transactions WHERE status = 'success' AND created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)");
        while ($r = $cBank->fetch()) {
            if (isset($chartData[$r['dt']])) $chartData[$r['dt']] += (float)$r['total'];
        }

        $cCard = $db->query("SELECT DATE(created_at) as dt, SUM(received_amount) as total FROM card_transactions WHERE status = 'success' AND created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)");
        while ($r = $cCard->fetch()) {
            if (isset($chartData[$r['dt']])) $chartData[$r['dt']] += (float)$r['total'];
        }

        // 7. 5 Đơn hàng mua code mới nhất
        $oStmt = $db->query("SELECT o.*, u.username, p.title as product_title 
                             FROM orders o 
                             JOIN users u ON o.user_id = u.id 
                             JOIN products p ON o.product_id = p.id 
                             ORDER BY o.id DESC LIMIT 5");
        $recentOrders = $oStmt->fetchAll();

        // 8. 5 Giao dịch nạp tiền MoMo & Thẻ cào mới nhất
        $tStmt = $db->query("
            (SELECT b.id, b.trans_id, b.amount, b.created_at, b.status, u.username, 'MoMo' as channel 
             FROM bank_transactions b 
             JOIN users u ON b.user_id = u.id)
            UNION ALL
            (SELECT c.id, c.trans_id, c.received_amount as amount, c.created_at, c.status, u.username, 'Card' as channel 
             FROM card_transactions c 
             JOIN users u ON c.user_id = u.id)
            ORDER BY created_at DESC LIMIT 5
        ");
        $recentTransactions = $tStmt->fetchAll();

    } catch (Exception $e) {}
}

$chartDataValues = array_values($chartData);
$totalChart7Days = array_sum($chartDataValues);
?>

<!-- ===================================================== -->
<!-- 1. BỐ CỤC 4 THẺ KPI GỌN GÀNG (MODERN COMPACT SAAS)    -->
<!-- ===================================================== -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    <!-- Card 1: Tổng Doanh Thu -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex flex-col justify-between hover:border-emerald-300 transition-all duration-300 group">
        <div class="flex items-center justify-between gap-3 mb-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tổng Doanh Thu</span>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-base shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
                <i class="fa-solid fa-sack-dollar"></i>
            </div>
        </div>
        <div>
            <div class="text-2xl font-extrabold text-slate-900 tracking-tight">
                <?= number_format($totalRevenue, 0, ',', '.') ?> đ
            </div>
            <p class="text-[11px] font-medium text-slate-400 mt-1 flex items-center gap-1">
                <i class="fa-solid fa-bolt text-emerald-500 text-[10px]"></i>
                <span>Tích lũy Ví MoMo &amp; Thẻ cào</span>
            </p>
        </div>
    </div>

    <!-- Card 2: Doanh Thu Hôm Nay -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex flex-col justify-between hover:border-indigo-300 transition-all duration-300 group">
        <div class="flex items-center justify-between gap-3 mb-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Hôm Nay</span>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-base shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
        </div>
        <div>
            <div class="text-2xl font-extrabold text-indigo-600 tracking-tight">
                +<?= number_format($revenueToday, 0, ',', '.') ?> đ
            </div>
            <p class="text-[11px] font-medium text-slate-400 mt-1 flex items-center gap-1 truncate">
                <span class="text-indigo-600 font-bold">Tháng này:</span>
                <span>+<?= number_format($revenueMonth, 0, ',', '.') ?> đ</span>
            </p>
        </div>
    </div>

    <!-- Card 3: Đơn Hàng Hoàn Tất -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex flex-col justify-between hover:border-purple-300 transition-all duration-300 group">
        <div class="flex items-center justify-between gap-3 mb-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Đơn Hàng Mua Code</span>
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center text-base shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
        </div>
        <div>
            <div class="text-2xl font-extrabold text-slate-900 tracking-tight">
                <?= number_format($totalOrders) ?>
            </div>
            <div class="mt-1">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                    <i class="fa-solid fa-circle-check text-[9px]"></i>
                    <?= number_format($totalOrders) ?> đơn hoàn tất
                </span>
            </div>
        </div>
    </div>

    <!-- Card 4: Tổng Thành Viên -->
    <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex flex-col justify-between hover:border-blue-300 transition-all duration-300 group">
        <div class="flex items-center justify-between gap-3 mb-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tổng Thành Viên</span>
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center text-base shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div>
            <div class="text-2xl font-extrabold text-slate-900 tracking-tight">
                <?= number_format($totalUsers) ?>
            </div>
            <div class="mt-1">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <?= number_format($totalUsers) ?> tài khoản active
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- 2. BIỂU ĐỒ 7 NGÀY (8 CỘT) + LỐI TẮT QUẢN TRỊ (4 CỘT) -->
<!-- ===================================================== -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6 items-stretch">
    <!-- Cột Trái: Biểu Đồ Doanh Thu 7 Ngày Gần Nhất (Col 8) -->
    <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4 gap-3">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs shadow-2xs">
                        <i class="fa-solid fa-chart-line"></i>
                    </span>
                    <span>Biểu Đồ Doanh Thu 7 Ngày</span>
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Biến động nạp tiền tự động qua Ví MoMo &amp; Thẻ cào</p>
            </div>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-2xs shrink-0">
                <i class="fa-solid fa-arrow-trend-up text-[10px]"></i>
                7 ngày: +<?= number_format($totalChart7Days, 0, ',', '.') ?> đ
            </span>
        </div>

        <!-- Khung Canvas Chart.js Chiều Cao Vừa Vặn -->
        <div class="relative w-full h-64">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Cột Phải: Lối Tắt Quản Trị Nhanh & Trạng Thái Máy Chủ (Col 4) -->
    <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-sm flex flex-col justify-between space-y-4">
        <div>
            <div class="pb-3 border-b border-slate-100 mb-3">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs shadow-2xs">
                        <i class="fa-solid fa-bolt"></i>
                    </span>
                    <span>Lối Tắt Quản Trị</span>
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Truy cập nhanh tác vụ cốt lõi</p>
            </div>

            <!-- 4 Nút Lối Tắt Nhanh -->
            <div class="space-y-2">
                <a href="<?= BASE_URL ?>/admin/products" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-indigo-50/80 hover:text-indigo-600 border border-slate-200/70 transition-all font-bold text-xs text-slate-700 shadow-2xs group cursor-pointer">
                    <div class="flex items-center gap-2.5">
                        <span class="w-6 h-6 rounded-lg bg-white border border-slate-200 text-slate-500 group-hover:text-indigo-600 group-hover:border-indigo-200 flex items-center justify-center text-[10px]">
                            <i class="fa-solid fa-plus"></i>
                        </span>
                        <span>Đăng mã nguồn mới</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all"></i>
                </a>

                <a href="<?= BASE_URL ?>/admin/categories" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-indigo-50/80 hover:text-indigo-600 border border-slate-200/70 transition-all font-bold text-xs text-slate-700 shadow-2xs group cursor-pointer">
                    <div class="flex items-center gap-2.5">
                        <span class="w-6 h-6 rounded-lg bg-white border border-slate-200 text-slate-500 group-hover:text-indigo-600 group-hover:border-indigo-200 flex items-center justify-center text-[10px]">
                            <i class="fa-solid fa-layer-group"></i>
                        </span>
                        <span>Thêm danh mục mới</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all"></i>
                </a>

                <a href="<?= BASE_URL ?>/admin/deposits" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-indigo-50/80 hover:text-indigo-600 border border-slate-200/70 transition-all font-bold text-xs text-slate-700 shadow-2xs group cursor-pointer">
                    <div class="flex items-center gap-2.5">
                        <span class="w-6 h-6 rounded-lg bg-white border border-slate-200 text-slate-500 group-hover:text-indigo-600 group-hover:border-indigo-200 flex items-center justify-center text-[10px]">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </span>
                        <span>Duyệt nạp tiền / Lịch sử</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all"></i>
                </a>

                <a href="<?= BASE_URL ?>/admin/settings" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-indigo-50/80 hover:text-indigo-600 border border-slate-200/70 transition-all font-bold text-xs text-slate-700 shadow-2xs group cursor-pointer">
                    <div class="flex items-center gap-2.5">
                        <span class="w-6 h-6 rounded-lg bg-white border border-slate-200 text-slate-500 group-hover:text-indigo-600 group-hover:border-indigo-200 flex items-center justify-center text-[10px]">
                            <i class="fa-solid fa-sliders"></i>
                        </span>
                        <span>Cấu hình hệ thống</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all"></i>
                </a>
            </div>
        </div>

        <!-- Khung Trạng Thái Máy Chủ Nhỏ Gọn (Server Status) -->
        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-[11px] space-y-1.5">
            <div class="flex items-center justify-between text-slate-500 font-semibold">
                <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Máy chủ trực tuyến
                </span>
                <span class="font-mono text-slate-700">PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?></span>
            </div>
            <div class="flex items-center justify-between text-slate-400 pt-1 border-t border-slate-200/60 text-[10px]">
                <span>CSDL MySQL (PDO)</span>
                <span class="font-bold text-emerald-600">Đã kết nối</span>
            </div>
            <div class="flex items-center justify-between text-slate-400 text-[10px]">
                <span>Giờ hệ thống</span>
                <span class="font-mono text-slate-600"><?= date('H:i - d/m/Y') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- 3. 2 BẢNG GIAO DỊCH MỚI NHẤT (ĐƠN MUA & NẠP TIỀN)     -->
<!-- ===================================================== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Bảng 1: Đơn Mua Mã Nguồn Mới -->
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm overflow-hidden flex flex-col justify-between">
        <div>
            <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-xs shadow-2xs">
                        <i class="fa-solid fa-bag-shopping"></i>
                    </span>
                    <span>Đơn Mua Mã Nguồn Mới</span>
                </h3>
                <a href="<?= BASE_URL ?>/admin/orders" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                    <span>Xem tất cả</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>
            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[500px] text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase bg-slate-50/30">
                            <th class="py-2.5 px-3.5 whitespace-nowrap">Mã Đơn</th>
                            <th class="py-2.5 px-3.5 whitespace-nowrap">Khách Hàng</th>
                            <th class="py-2.5 px-3.5 min-w-[170px] whitespace-nowrap">Mã Nguồn</th>
                            <th class="py-2.5 px-3.5 text-right whitespace-nowrap">Giá Mua</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $ro): ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-3.5 font-mono font-bold text-indigo-600 whitespace-nowrap">
                                        #<?= xss_clean($ro['order_code']) ?>
                                    </td>
                                    <td class="py-3 px-3.5 text-slate-800 font-semibold whitespace-nowrap">
                                        @<?= xss_clean($ro['username']) ?>
                                    </td>
                                    <td class="py-3 px-3.5 text-slate-600 max-w-[170px] truncate" title="<?= xss_clean($ro['product_title']) ?>">
                                        <?= xss_clean($ro['product_title']) ?>
                                    </td>
                                    <td class="py-3 px-3.5 font-extrabold text-slate-900 text-right whitespace-nowrap">
                                        <?= number_format($ro['price'], 0, ',', '.') ?> đ
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Empty State Gọn Nhẹ -->
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-400 space-y-1.5">
                                    <i class="fa-solid fa-box-open text-xl text-slate-300 block"></i>
                                    <span class="text-xs font-semibold block">Chưa có đơn hàng mua mã nguồn nào.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bảng 2: Giao Dịch Nạp Tiền Mới -->
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm overflow-hidden flex flex-col justify-between">
        <div>
            <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-pink-50 text-pink-600 flex items-center justify-center text-xs shadow-2xs">
                        <i class="fa-solid fa-wallet"></i>
                    </span>
                    <span>Giao Dịch Nạp Tiền Mới</span>
                </h3>
                <a href="<?= BASE_URL ?>/admin/deposits" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                    <span>Xem tất cả</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>
            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[500px] text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase bg-slate-50/30">
                            <th class="py-2.5 px-3.5 whitespace-nowrap">Mã GD</th>
                            <th class="py-2.5 px-3.5 whitespace-nowrap">Kênh</th>
                            <th class="py-2.5 px-3.5 whitespace-nowrap">Thành Viên</th>
                            <th class="py-2.5 px-3.5 text-right whitespace-nowrap">Số Tiền</th>
                            <th class="py-2.5 px-3.5 text-right whitespace-nowrap">Thời Gian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($recentTransactions)): ?>
                            <?php foreach ($recentTransactions as $rt): ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-3.5 font-mono text-slate-500 whitespace-nowrap">
                                        <?= xss_clean($rt['trans_id']) ?>
                                    </td>
                                    <td class="py-3 px-3.5 whitespace-nowrap">
                                        <?php if ($rt['channel'] === 'MoMo' || $rt['channel'] === 'VietQR'): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-pink-50 text-pink-700 border border-pink-200">
                                                <i class="fa-solid fa-wallet text-[8px]"></i>
                                                Ví MoMo
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <i class="fa-solid fa-credit-card text-[8px]"></i>
                                                Thẻ cào
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3.5 text-slate-800 font-semibold whitespace-nowrap">
                                        @<?= xss_clean($rt['username']) ?>
                                    </td>
                                    <td class="py-3 px-3.5 font-extrabold text-emerald-600 text-right whitespace-nowrap">
                                        +<?= number_format($rt['amount'], 0, ',', '.') ?> đ
                                    </td>
                                    <td class="py-3 px-3.5 text-slate-400 text-right whitespace-nowrap">
                                        <?= date('H:i d/m', strtotime($rt['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Empty State Gọn Nhẹ -->
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400 space-y-1.5">
                                    <i class="fa-solid fa-receipt text-xl text-slate-300 block"></i>
                                    <span class="text-xs font-semibold block">Chưa có giao dịch nạp tiền Ví MoMo hay Thẻ cào nào.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================== -->
<!-- CHART.JS: INITIALIZATION                             -->
<!-- ===================================================== -->
<script>
    (function() {
        const chartCanvas = document.getElementById('revenueChart');
        if (!chartCanvas) return;

        const ctx = chartCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 260);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.22)');
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0.00)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Doanh thu',
                    data: <?= json_encode($chartDataValues) ?>,
                    borderColor: '#4f46e5',
                    borderWidth: 2.5,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#4f46e5',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#ffffff',
                        titleColor: '#0f172a',
                        bodyColor: '#0f172a',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 10,
                        boxPadding: 4,
                        usePointStyle: true,
                        callbacks: {
                            label: function(context) {
                                return ' ' + new Intl.NumberFormat('vi-VN').format(context.parsed.y) + ' đ';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(0, 0, 0, 0.03)' },
                        ticks: { color: '#64748b', font: { size: 11, family: '"Plus Jakarta Sans", sans-serif' } }
                    },
                    y: {
                        beginAtZero: true,
                        min: 0,
                        suggestedMax: 1000000,
                        grid: { color: 'rgba(0, 0, 0, 0.03)' },
                        ticks: {
                            color: '#64748b',
                            font: { size: 11, family: '"Plus Jakarta Sans", sans-serif' },
                            precision: 0,
                            callback: function(val) {
                                if (val <= 0) return '0 đ';
                                if (val >= 1000000) return (val / 1000000).toFixed(1).replace('.0', '') + 'M';
                                return (val / 1000).toFixed(0) + 'k';
                            }
                        }
                    }
                }
            }
        });
    })();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
