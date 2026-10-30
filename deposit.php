<?php
$pageTitle = "Nạp Tiền Tài Khoản - Ví MoMo & Thẻ Cào 24/7";
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

if (!isset($pdo) || $pdo === null) {
    $pdo = Database::getInstance();
}

if (isset($_GET['demo']) || isset($_GET['preview'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
    }
}
require_login();

$user = current_user();
$user_id = $user['id'];
$currentUser = $user;
$currentBalance = $user ? (float)$user['balance'] : 0;
$naptien_memo = "NAPTIEN " . $user_id;



$recentBanks = [];
$recentCards = [];
$recentMomo = [];
try {
    $bStmt = $pdo->prepare("SELECT * FROM bank_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    $bStmt->execute([$user_id]);
    $recentBanks = $bStmt->fetchAll();
    $cStmt = $pdo->prepare("SELECT * FROM card_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    $cStmt->execute([$user_id]);
    $recentCards = $cStmt->fetchAll();
    $mStmt = $pdo->prepare("SELECT * FROM momo_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    $mStmt->execute([$user_id]);
    $recentMomo = $mStmt->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
    /* ========================================================
       MODERN CLEAN LIGHT SAAS - MOMO & THẺ CÀO STYLES
       ======================================================== */
    .momo-btn-active {
        background-color: #A50064 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px -2px rgba(165, 0, 100, 0.4) !important;
    }
    .card-btn-active {
        background-color: #4f46e5 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px -2px rgba(79, 70, 229, 0.35) !important;
    }
    .momo-amt-chip {
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .momo-amt-chip.active {
        border-color: #A50064 !important;
        background-color: #fdf2f8 !important;
        color: #A50064 !important;
        font-weight: 800;
        box-shadow: 0 2px 10px -3px rgba(165, 0, 100, 0.25);
    }
    .telco-card input:checked + div {
        border-color: #4f46e5 !important;
        background-color: #eef2ff !important;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15) !important;
    }
    @keyframes gentlePulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(0.96); }
    }
    .status-pulse {
        animation: gentlePulse 2s ease-in-out infinite;
    }
</style>

<main class="flex-1">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-7">

    <!-- ===================================================== -->
    <!-- 1. COMPACT TOP HEADER & SEGMENTED PILL SWITCHER        -->
    <!-- ===================================================== -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <!-- Bên trái: Tiêu đề + Mô tả ngắn -->
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2.5">
                <span>Nạp Tiền Tài Khoản</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 status-pulse"></span>
                    Tự động 24/7
                </span>
            </h1>
            <p class="text-sm text-slate-500 mt-1">Hệ thống nạp tự động qua Ví MoMo &amp; Thẻ cào 24/7</p>
        </div>

        <!-- Bên phải: Nút chuyển đổi 2 phương thức dạng Segmented Pill -->
        <div class="bg-slate-100 p-1.5 rounded-2xl inline-flex items-center gap-1 border border-slate-200/70 self-start sm:self-auto shadow-inner">
            <button type="button" id="tabMomoBtn" onclick="switchMethod('momo')" class="momo-btn-active px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 cursor-pointer">
                <span class="w-5 h-5 rounded-full bg-white text-[#A50064] flex items-center justify-center font-black text-[10px] shadow-xs">M</span>
                <span>Ví MoMo Tự Động</span>
            </button>
            <button type="button" id="tabCardBtn" onclick="switchMethod('card')" class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-slate-600 hover:text-slate-900 transition-all flex items-center gap-2 cursor-pointer">
                <i class="fa-solid fa-sim-card text-xs"></i>
                <span>Nạp Thẻ Cào</span>
            </button>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- 2. PHƯƠNG THỨC 1: VÍ MOMO (2 CỘT TÁCH BIỆT CÂN ĐỐI)   -->
    <!-- ===================================================== -->
    <section id="sectionMomo" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
        
        <!-- Cột Trái: Chọn mệnh giá & Thông tin ví nhận tiền -->
        <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-7 shadow-sm flex flex-col justify-between space-y-6">
            <div class="space-y-6">
                <!-- Chọn mệnh giá -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">1. Chọn số tiền nạp vào tài khoản</label>
                    <div class="grid grid-cols-3 gap-2.5">
                        <?php
                        $momoAmounts = [50000, 100000, 200000, 500000, 1000000, 2000000];
                        foreach ($momoAmounts as $idx => $amt):
                            $activeClass = ($amt === 100000) ? 'active' : '';
                        ?>
                        <button type="button" onclick="selectMomoAmount(<?= $amt ?>, this)" class="momo-amt-chip <?= $activeClass ?> py-3 px-2 rounded-2xl border border-slate-200/90 text-xs sm:text-sm font-bold text-slate-700 bg-slate-50/50 hover:bg-white hover:border-slate-300 text-center cursor-pointer">
                            <?= number_format($amt, 0, ',', '.') ?> ₫
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Ô nhập số tiền tùy chọn -->
                    <div class="mt-3">
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">₫</span>
                            <input type="number" id="momoCustomAmount" value="100000" min="10000" max="50000000" step="10000" oninput="updateMomoAmount()" placeholder="Nhập số tiền khác (tối thiểu 10.000 ₫)..." class="w-full pl-9 pr-16 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-sm font-bold text-slate-900 font-mono focus:bg-white focus:border-[#A50064] focus:ring-4 focus:ring-pink-500/10 outline-none transition-all">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">VNĐ</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-pink-50 border border-pink-100 p-4 flex gap-3 items-start">
                    <span class="w-8 h-8 shrink-0 rounded-xl bg-white text-[#A50064] flex items-center justify-center"><i class="fa-solid fa-shield-halved"></i></span>
                    <div>
                        <p class="text-sm font-bold text-slate-900">Thanh toán qua cổng MoMo chính thức</p>
                        <p class="text-xs text-slate-600 mt-1 leading-relaxed">Bạn sẽ được chuyển đến MoMo để xác nhận. Số dư chỉ được cộng sau khi hệ thống nhận và xác minh thông báo thanh toán.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cột Phải: MoMo Gateway checkout -->
        <div class="lg:col-span-5 bg-gradient-to-br from-[#A50064] via-[#be185d] to-[#831843] rounded-3xl p-7 text-white shadow-xl shadow-pink-950/10 flex flex-col items-center justify-center text-center relative overflow-hidden space-y-5">
            <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-md px-3.5 py-1 rounded-full text-xs font-bold">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Thanh toán MoMo an toàn</span>
            </div>
            <div class="w-20 h-20 rounded-3xl bg-white text-[#A50064] flex items-center justify-center text-4xl font-black shadow-xl">M</div>
            <div>
                <h2 class="text-lg font-extrabold">Sẵn sàng nạp <span id="momoCheckoutAmount">100.000 ₫</span>?</h2>
                <p class="text-xs text-pink-100 mt-2 max-w-xs">Tối thiểu 10.000 ₫ · Tối đa 50.000.000 ₫ mỗi giao dịch</p>
            </div>
            <input type="hidden" id="momoCsrfToken" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" id="momoCheckoutButton" onclick="startMomoPayment()" class="w-full max-w-xs px-5 py-3.5 rounded-2xl bg-white text-[#A50064] font-extrabold text-sm hover:bg-pink-50 active:scale-[.98] transition-all shadow-lg">
                <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>Thanh toán với MoMo
            </button>
            <p class="text-[11px] text-pink-100/80 max-w-xs leading-relaxed">Không đóng trang khi đang chuyển hướng. Nếu đã thanh toán, chờ hệ thống xác nhận để cập nhật số dư.</p>
        </div>
    </section>

    <!-- ===================================================== -->
    <!-- 3. PHƯƠNG THỨC 2: NẠP THẺ CÀO (GỌN ĐẸP, 2 CỘT)         -->
    <!-- ===================================================== -->
    <section id="sectionCard" class="hidden grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
        
        <!-- Cột Trái: Form Nạp Thẻ Cào -->
        <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-7 shadow-sm flex flex-col justify-between">
            <form id="cardChargingForm" onsubmit="handleCardSubmit(event)" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Chọn nhà mạng dạng 4 thẻ ngang -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2.5">1. Chọn nhà mạng</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <?php
                        $telcoList = [
                            ['code' => 'VIETTEL',   'name' => 'Viettel',   'color' => 'text-rose-600'],
                            ['code' => 'VINAPHONE', 'name' => 'Vinaphone', 'color' => 'text-blue-600'],
                            ['code' => 'MOBIFONE',  'name' => 'Mobifone',  'color' => 'text-sky-600'],
                            ['code' => 'ZING',      'name' => 'Zing',      'color' => 'text-emerald-600'],
                        ];
                        foreach ($telcoList as $tIdx => $t):
                        ?>
                        <label class="telco-card cursor-pointer">
                            <input type="radio" name="telco" value="<?= $t['code'] ?>" <?= $tIdx === 0 ? 'checked' : '' ?> class="sr-only">
                            <div class="p-3 rounded-2xl border-2 border-slate-200 bg-white hover:border-slate-300 text-center transition-all">
                                <span class="font-black text-sm block <?= $t['color'] ?>"><?= $t['name'] ?></span>
                                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 mt-1">CK -15%</span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Chọn mệnh giá -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">2. Mệnh giá thẻ cào</label>
                    <select name="amount" id="cardAmountSelect" onchange="updateCardExpectedAmount()" required class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-sm font-bold text-slate-900 focus:bg-white focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                        <option value="10000">10.000 ₫ (Thực nhận: 8.500 ₫)</option>
                        <option value="20000">20.000 ₫ (Thực nhận: 17.000 ₫)</option>
                        <option value="50000" selected>50.000 ₫ (Thực nhận: 42.500 ₫)</option>
                        <option value="100000">100.000 ₫ (Thực nhận: 85.000 ₫)</option>
                        <option value="200000">200.000 ₫ (Thực nhận: 170.000 ₫)</option>
                        <option value="500000">500.000 ₫ (Thực nhận: 425.000 ₫)</option>
                        <option value="1000000">1.000.000 ₫ (Thực nhận: 850.000 ₫)</option>
                    </select>
                </div>

                <!-- Số Seri & Mã Thẻ 2 cột -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Số Seri thẻ *</label>
                        <div class="relative">
                            <i class="fa-solid fa-barcode absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="serial" required placeholder="Nhập số seri..." class="w-full pl-9 pr-3.5 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-sm font-mono focus:bg-white focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Mã Thẻ (Mã PIN) *</label>
                        <div class="relative">
                            <i class="fa-solid fa-key absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="pin" required placeholder="Nhập mã thẻ cào..." class="w-full pl-9 pr-3.5 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-sm font-mono focus:bg-white focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                        </div>
                    </div>
                </div>

                <!-- Nút nạp thẻ -->
                <button type="submit" id="btnSubmitCard" class="w-full py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow-md shadow-indigo-600/20 flex items-center justify-center gap-2 cursor-pointer active:scale-[0.99] transition-all">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                    <span>Nạp Thẻ Cào Ngay</span>
                </button>
            </form>
        </div>

        <!-- Cột Phải: Bảng tính thực nhận & Lưu ý thẻ cào -->
        <div class="lg:col-span-5 bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-7 shadow-sm flex flex-col justify-between space-y-5">
            <div>
                <!-- Khối tóm tắt số tiền thực nhận -->
                <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-100 flex items-center justify-between mb-5">
                    <div>
                        <span class="text-xs text-indigo-700 font-semibold block">Số tiền thực nhận vào ví:</span>
                        <span class="text-2xl font-black text-indigo-600 font-mono" id="cardExpectedAmountText">42.500 ₫</span>
                    </div>
                    <span class="px-2.5 py-1 rounded-lg bg-white border border-indigo-200 text-xs font-black text-indigo-700 shadow-2xs">Chiết khấu 15%</span>
                </div>

                <!-- Bảng chiết khấu ngắn gọn -->
                <div class="rounded-2xl border border-slate-200 overflow-hidden mb-4">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-400 font-bold uppercase text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="py-2 px-3 text-left">Mệnh giá</th>
                                <th class="py-2 px-3 text-center">Phí</th>
                                <th class="py-2 px-3 text-right">Thực nhận</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600">
                            <tr><td class="py-2 px-3 font-medium">50.000 ₫</td><td class="py-2 px-3 text-center text-rose-500 font-semibold">15%</td><td class="py-2 px-3 text-right font-bold text-emerald-600">42.500 ₫</td></tr>
                            <tr class="bg-indigo-50/30"><td class="py-2 px-3 font-medium">100.000 ₫</td><td class="py-2 px-3 text-center text-rose-500 font-semibold">15%</td><td class="py-2 px-3 text-right font-bold text-indigo-600">85.000 ₫</td></tr>
                            <tr><td class="py-2 px-3 font-medium">200.000 ₫</td><td class="py-2 px-3 text-center text-rose-500 font-semibold">15%</td><td class="py-2 px-3 text-right font-bold text-emerald-600">170.000 ₫</td></tr>
                            <tr><td class="py-2 px-3 font-medium">500.000 ₫</td><td class="py-2 px-3 text-center text-rose-500 font-semibold">15%</td><td class="py-2 px-3 text-right font-bold text-emerald-600">425.000 ₫</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3 Lưu ý ngắn gọn -->
            <div class="space-y-2 text-xs text-slate-500 pt-2 border-t border-slate-100">
                <p class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-emerald-500 mt-0.5 shrink-0"></i><span>Hệ thống tự động duyệt thẻ trong 15 – 30 giây.</span></p>
                <p class="flex items-start gap-2"><i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5 shrink-0"></i><span>Chọn đúng mệnh giá; chọn sai sẽ bị phạt trừ 50% theo quy định nhà mạng.</span></p>
                <p class="flex items-start gap-2"><i class="fa-solid fa-shield-halved text-indigo-500 mt-0.5 shrink-0"></i><span>Thẻ cào đã nạp ở nơi khác sẽ bị từ chối tự động.</span></p>
            </div>
        </div>
    </section>

    <!-- ===================================================== -->
    <!-- 4. BẢNG LỊCH SỬ NẠP TIỀN GỌN GÀNG (TẤT CẢ / MOMO / THẺ) -->
    <!-- ===================================================== -->
    <section class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <!-- Tiêu đề & Bộ lọc 3 nút -->
        <div class="p-5 sm:p-6 pb-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                    <span>Lịch Sử Nạp Tiền</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Danh sách các giao dịch nạp tiền gần nhất của bạn</p>
            </div>
            <div class="flex rounded-xl bg-slate-100 p-1 text-xs font-bold border border-slate-200/60 shrink-0">
                <button type="button" onclick="filterHistory('all')" id="btnHistAll" class="px-3 py-1.5 rounded-lg bg-white text-indigo-600 shadow-xs cursor-pointer">Tất cả</button>
                <button type="button" onclick="filterHistory('momo')" id="btnHistMomo" class="px-3 py-1.5 rounded-lg text-slate-500 hover:text-slate-800 cursor-pointer">Ví MoMo</button>
                <button type="button" onclick="filterHistory('card')" id="btnHistCard" class="px-3 py-1.5 rounded-lg text-slate-500 hover:text-slate-800 cursor-pointer">Thẻ cào</button>
            </div>
        </div>

        <!-- Bảng danh sách giao dịch -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[10px] uppercase text-slate-400 font-extrabold bg-slate-50/70 border-b border-slate-100">
                        <th class="py-3 px-5">Phương thức</th>
                        <th class="py-3 px-5">Mã Giao Dịch</th>
                        <th class="py-3 px-5">Chi tiết</th>
                        <th class="py-3 px-5">Số tiền</th>
                        <th class="py-3 px-5">Thời gian</th>
                        <th class="py-3 px-5 text-center">Trạng thái</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <!-- Giao dịch ngân hàng -->
                    <?php foreach ($recentBanks as $b): ?>
                    <tr class="hist-row hist-momo hover:bg-slate-50/50 transition-colors">
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-black bg-pink-50 text-[#A50064] border border-pink-200">
                                <span class="w-3.5 h-3.5 rounded-full bg-[#A50064] text-white flex items-center justify-center text-[8px]">M</span>
                                MoMo
                            </span>
                        </td>
                        <td class="py-3.5 px-5 font-mono text-xs text-slate-500"><?= htmlspecialchars($b['trans_id']) ?></td>
                        <td class="py-3.5 px-5 text-xs text-slate-700 font-medium"><?= htmlspecialchars($b['transfer_content']) ?></td>
                        <td class="py-3.5 px-5 font-extrabold text-emerald-600">+<?= number_format($b['amount'], 0, ',', '.') ?> ₫</td>
                        <td class="py-3.5 px-5 text-xs text-slate-400"><?= date('H:i d/m/Y', strtotime($b['created_at'])) ?></td>
                        <td class="py-3.5 px-5 text-center">
                            <?php if ($b['status'] === 'success'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><i class="fa-solid fa-check text-[9px]"></i>Thành công</span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"><i class="fa-solid fa-clock text-[9px]"></i>Đang xử lý</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Giao dịch MoMo API -->
                    <?php foreach ($recentMomo as $m): ?>
                    <tr class="hist-row hist-momo hover:bg-slate-50/50 transition-colors">
                        <td class="py-3.5 px-5"><span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-black bg-pink-50 text-[#A50064] border border-pink-200">MoMo API</span></td>
                        <td class="py-3.5 px-5 font-mono text-xs text-slate-500"><?= htmlspecialchars($m['order_id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3.5 px-5 text-xs text-slate-700 font-medium">Nạp ví qua cổng MoMo</td>
                        <td class="py-3.5 px-5 font-extrabold text-slate-900"><?= number_format((int)$m['amount'], 0, ',', '.') ?> ₫</td>
                        <td class="py-3.5 px-5 text-xs text-slate-400"><?= date('H:i d/m/Y', strtotime($m['created_at'])) ?></td>
                        <td class="py-3.5 px-5 text-center">
                            <?php if ($m['status'] === 'success'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Thành công</span>
                            <?php elseif ($m['status'] === 'failed'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Thất bại</span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Đang xử lý</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Giao dịch Thẻ Cào -->
                    <?php foreach ($recentCards as $c): ?>
                    <tr class="hist-row hist-card hover:bg-slate-50/50 transition-colors">
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-black bg-amber-50 text-amber-800 border border-amber-200">
                                <i class="fa-solid fa-sim-card text-xs"></i>
                                <?= htmlspecialchars($c['telco']) ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-5 font-mono text-xs text-slate-500"><?= htmlspecialchars($c['request_id'] ?? ('CARD-' . $c['id'])) ?></td>
                        <td class="py-3.5 px-5 font-mono text-xs text-slate-600">Seri: <?= htmlspecialchars($c['serial']) ?></td>
                        <td class="py-3.5 px-5">
                            <span class="font-extrabold text-slate-900"><?= number_format($c['declared_amount'], 0, ',', '.') ?> ₫</span>
                            <?php if (!empty($c['received_amount']) && $c['received_amount'] > 0): ?>
                                <span class="block text-[10px] font-bold text-emerald-600">+<?= number_format($c['received_amount'], 0, ',', '.') ?> ₫ nhận</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-5 text-xs text-slate-400"><?= date('H:i d/m/Y', strtotime($c['created_at'])) ?></td>
                        <td class="py-3.5 px-5 text-center">
                            <?php if ($c['status'] === 'success'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><i class="fa-solid fa-check text-[9px]"></i>Thành công</span>
                            <?php elseif ($c['status'] === 'pending'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"><i class="fa-solid fa-clock text-[9px]"></i>Đang xử lý</span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200"><i class="fa-solid fa-xmark text-[9px]"></i>Thẻ lỗi</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Empty State gọn gàng -->
                    <?php if (empty($recentBanks) && empty($recentMomo) && empty($recentCards)): ?>
                    <tr id="emptyHistoryRow">
                        <td colspan="6" class="py-10 text-center text-slate-400">
                            <i class="fa-regular fa-folder-open text-2xl mb-1.5 block text-slate-300"></i>
                            <p class="font-bold text-slate-500 text-xs">Chưa có giao dịch nạp tiền nào</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>
</main>

<!-- ===================================================== -->
<!-- JAVASCRIPT: MOMO QR, CARD CHARGING & LIVE POLLING      -->
<!-- ===================================================== -->
<script>


// 1. Chuyển đổi phương thức MoMo <-> Thẻ Cào
function switchMethod(method) {
    const btnMomo = document.getElementById('tabMomoBtn');
    const btnCard = document.getElementById('tabCardBtn');
    const secMomo = document.getElementById('sectionMomo');
    const secCard = document.getElementById('sectionCard');

    if (method === 'momo') {
        btnMomo.className = 'momo-btn-active px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 cursor-pointer';
        btnCard.className = 'px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-slate-600 hover:text-slate-900 transition-all flex items-center gap-2 cursor-pointer';
        secMomo.classList.remove('hidden');
        secCard.classList.add('hidden');
    } else {
        btnCard.className = 'card-btn-active px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all flex items-center gap-2 cursor-pointer';
        btnMomo.className = 'px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-slate-600 hover:text-slate-900 transition-all flex items-center gap-2 cursor-pointer';
        secCard.classList.remove('hidden');
        secMomo.classList.add('hidden');
    }
}

// 2. Chọn mệnh giá MoMo nhanh
function selectMomoAmount(amount, buttonElement) {
    document.querySelectorAll('.momo-amt-chip').forEach(b => b.classList.remove('active'));
    buttonElement.classList.add('active');
    document.getElementById('momoCustomAmount').value = amount;
    updateMomoAmount();
}

// 3. Cập nhật số tiền và trạng thái nút thanh toán
function updateMomoAmount() {
    const input = document.getElementById('momoCustomAmount');
    const amount = Number(input.value);
    const valid = Number.isSafeInteger(amount) && amount >= 10000 && amount <= 50000000;
    document.getElementById('momoCheckoutAmount').innerText = valid ? amount.toLocaleString('vi-VN') + ' ₫' : '—';
    document.getElementById('momoCheckoutButton').disabled = !valid;
    document.getElementById('momoCheckoutButton').classList.toggle('opacity-50', !valid);
}

async function startMomoPayment() {
    const button = document.getElementById('momoCheckoutButton');
    const amount = document.getElementById('momoCustomAmount').value;
    const formData = new FormData();
    formData.append('amount', amount);
    formData.append('csrf_token', document.getElementById('momoCsrfToken').value);
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Đang kết nối MoMo...';
    try {
        const response = await fetch('<?= BASE_URL ?>/api/momo_create_payment.php', { method: 'POST', body: formData });
        const rawText = await response.text();
        let data;
        try {
            data = JSON.parse(rawText);
        } catch (parseErr) {
            console.error('MoMo raw response:', rawText);
            const cleanError = rawText.replace(/<[^>]*>?/gm, '').trim().substring(0, 200);
            throw new Error(cleanError || 'Máy chủ trả về dữ liệu không hợp lệ.');
        }
        if (!response.ok || !data.success || !data.pay_url) throw new Error(data.message || 'Không tạo được giao dịch MoMo.');
        window.location.assign(data.pay_url);
    } catch (error) {
        Swal.fire('Không thể thanh toán', error.message || 'Vui lòng thử lại sau.', 'error');
        button.disabled = false;
        button.innerHTML = '<i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>Thanh toán với MoMo';
        updateMomoAmount();
    }
}

// Khi MoMo quay về, chỉ đọc trạng thái backend đã xác minh qua IPN.
const momoReturn = new URLSearchParams(window.location.search);
if (momoReturn.get('momo_return') === '1' && momoReturn.get('order_id')) {
    switchMethod('momo');
    Swal.fire({ title: 'Đang xác nhận thanh toán', text: 'Đang chờ MoMo gửi kết quả an toàn về hệ thống...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const orderId = momoReturn.get('order_id');
    let attempts = 0;
    const checkMomoStatus = async () => {
        attempts++;
        try {
            const response = await fetch('<?= BASE_URL ?>/api/momo_payment_status.php?order_id=' + encodeURIComponent(orderId));
            const data = await response.json();
            if (data.success && data.status === 'success') {
                Swal.fire('Nạp tiền thành công', 'Đã cộng ' + Number(data.amount).toLocaleString('vi-VN') + ' ₫ vào số dư.', 'success').then(() => location.reload());
                return;
            }
            if (data.success && data.status === 'failed') {
                Swal.fire('Thanh toán chưa thành công', 'Giao dịch MoMo không hoàn tất. Bạn có thể thử nạp lại.', 'error');
                return;
            }
        } catch (error) {}
        if (attempts >= 30) {
            Swal.fire('Đang chờ xác nhận', 'Chưa nhận được kết quả từ MoMo. Giao dịch vẫn đang chờ; hãy kiểm tra lại lịch sử nạp sau ít phút.', 'info');
            return;
        }
        setTimeout(checkMomoStatus, 3000);
    };
    checkMomoStatus();
}

updateMomoAmount();

// 4. Cập nhật số tiền thực nhận thẻ cào
function updateCardExpectedAmount() {
    const select = document.getElementById('cardAmountSelect');
    const amount = parseInt(select.value, 10) || 50000;
    const received = Math.round(amount * 0.85);
    document.getElementById('cardExpectedAmountText').innerText = received.toLocaleString('vi-VN') + ' ₫';
}

// 5. Sao chép nhanh
function copyText(text, label) {
    const onSuccess = () => {
        if (typeof Swal !== 'undefined') {
            Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1800,
                timerProgressBar: true
            }).fire({
                icon: 'success',
                title: 'Đã sao chép ' + label + '!'
            });
        }
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(onSuccess).catch(() => {
            fallbackCopy(text);
            onSuccess();
        });
    } else {
        fallbackCopy(text);
        onSuccess();
    }
}
function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;opacity:0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
}

// 6. Bộ lọc Lịch Sử: Tất cả / MoMo / Thẻ Cào
function filterHistory(type) {
    const btnAll  = document.getElementById('btnHistAll');
    const btnMomo = document.getElementById('btnHistMomo');
    const btnCard = document.getElementById('btnHistCard');

    [btnAll, btnMomo, btnCard].forEach(b => {
        if (b) b.className = 'px-3 py-1.5 rounded-lg text-slate-500 hover:text-slate-800 cursor-pointer';
    });

    if (type === 'all' && btnAll) btnAll.className = 'px-3 py-1.5 rounded-lg bg-white text-indigo-600 shadow-xs cursor-pointer';
    if (type === 'momo' && btnMomo) btnMomo.className = 'px-3 py-1.5 rounded-lg bg-white text-[#A50064] shadow-xs cursor-pointer';
    if (type === 'card' && btnCard) btnCard.className = 'px-3 py-1.5 rounded-lg bg-white text-indigo-600 shadow-xs cursor-pointer';

    document.querySelectorAll('.hist-row').forEach(row => {
        if (type === 'all') {
            row.style.display = '';
        } else if (type === 'momo') {
            row.style.display = row.classList.contains('hist-momo') ? '' : 'none';
        } else {
            row.style.display = row.classList.contains('hist-card') ? '' : 'none';
        }
    });
}

// 7. Gửi nạp thẻ cào qua AJAX
async function handleCardSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitCard');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Đang xử lý thẻ...';

    try {
        const formData = new FormData(e.target);
        const res = await fetch('<?= BASE_URL ?>/api/card_charging.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Gửi Thẻ Thành Công!',
                text: data.message || 'Thẻ đã được gửi lên hệ thống và đang được xử lý trong 30 giây.',
                confirmButtonColor: '#4f46e5'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Thất Bại', data.message || 'Không thể xử lý thẻ cào. Vui lòng thử lại!', 'error');
        }
    } catch (err) {
        Swal.fire('Lỗi Kết Nối', 'Không thể kết nối đến máy chủ. Vui lòng thử lại sau!', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// 8. Tự động kiểm tra số dư (Polling 4.5s)
let lastTxId = <?= !empty($recentBanks[0]['id']) ? (int)$recentBanks[0]['id'] : 0 ?>;
setInterval(async () => {
    try {
        const res = await fetch('<?= BASE_URL ?>/api/check_bank_status.php?last_id=' + lastTxId);
        const data = await res.json();
        if (data.success && data.has_new) {
            lastTxId = data.tx_id;
            const balEl = document.getElementById('liveBalanceText');
            if (balEl) balEl.innerText = data.new_balance;
            Swal.fire({
                icon: 'success',
                title: 'Nạp Tiền Thành Công!',
                html: `Bạn vừa được cộng <b>${data.amount_fmt}</b> vào tài khoản!<br>Số dư mới: <b>${data.new_balance}</b>`,
                confirmButtonText: 'Xác Nhận',
                confirmButtonColor: '#A50064'
            }).then(() => {
                location.reload();
            });
        }
    } catch (e) {}
}, 4500);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>