<?php
$pageTitle = "Quản Lý Giao Dịch Nạp Tiền";
require_once __DIR__ . '/includes/admin_header.php';

$activeTab = $_GET['tab'] ?? 'card';
$message = '';
$error = '';

// Xử lý DUYỆT THẺ THỦ CÔNG (Nếu API gạch thẻ bị lỗi hoặc chậm)
if (isset($_GET['approve_card_id'])) {
    $cardId = (int)$_GET['approve_card_id'];
    try {
        $db->beginTransaction();

        $cStmt = $db->prepare("SELECT * FROM card_transactions WHERE id = ? FOR UPDATE");
        $cStmt->execute([$cardId]);
        $card = $cStmt->fetch();

        if (!$card) {
            throw new Exception('Giao dịch thẻ không tồn tại!');
        }
        if ($card['status'] === 'success') {
            throw new Exception('Thẻ này đã được duyệt thành công trước đó!');
        }

        // Tính tiền thực nhận (mặc định 85% của declared_amount nếu received_amount = 0)
        $received = $card['received_amount'] > 0 ? (float)$card['received_amount'] : ($card['declared_amount'] * 0.85);

        // Cộng tiền cho user
        $upU = $db->prepare("UPDATE users SET balance = balance + ?, total_deposited = total_deposited + ? WHERE id = ?");
        $upU->execute([$received, $received, $card['user_id']]);

        // Ghi log
        $bStmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $bStmt->execute([$card['user_id']]);
        $newBal = (float)$bStmt->fetchColumn();
        $oldBal = $newBal - $received;

        $logStmt = $db->prepare("INSERT INTO balance_logs (user_id, admin_id, amount_before, amount, amount_after, type, note) 
                                VALUES (?, ?, ?, ?, ?, 'deposit_card', ?)");
        $logStmt->execute([
            $card['user_id'],
            $currentUser['id'],
            $oldBal,
            $received,
            $newBal,
            "Admin duyệt thẻ cào thủ công {$card['telco']} seri {$card['serial']}"
        ]);

        // Đổi trạng thái thẻ
        $upC = $db->prepare("UPDATE card_transactions SET status = 'success', real_amount = ?, received_amount = ?, response_message = 'Admin duyệt thẻ thủ công' WHERE id = ?");
        $upC->execute([$card['declared_amount'], $received, $cardId]);

        $db->commit();
        $message = "Đã duyệt thẻ cào thành công và cộng " . format_money($received) . " cho người dùng!";
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'Lỗi duyệt thẻ: ' . $e->getMessage();
    }
}

// Xử lý BÁO THẺ SAI THỦ CÔNG
if (isset($_GET['reject_card_id'])) {
    $cardId = (int)$_GET['reject_card_id'];
    try {
        $upC = $db->prepare("UPDATE card_transactions SET status = 'error', response_message = 'Admin báo thẻ sai / không hợp lệ' WHERE id = ? AND status = 'pending'");
        $upC->execute([$cardId]);
        $message = 'Đã từ chối thẻ cào thành công!';
    } catch (Exception $e) {
        $error = 'Lỗi từ chối thẻ: ' . $e->getMessage();
    }
}

// Lấy danh sách thẻ cào & ngân hàng
$cardTransactions = [];
$bankTransactions = [];
if ($db) {
    try {
        $cardTransactions = $db->query("SELECT c.*, u.username 
                                        FROM card_transactions c 
                                        JOIN users u ON c.user_id = u.id 
                                        ORDER BY c.id DESC LIMIT 100")->fetchAll();

        $bankTransactions = $db->query("SELECT b.*, u.username 
                                        FROM bank_transactions b 
                                        JOIN users u ON b.user_id = u.id 
                                        ORDER BY b.id DESC LIMIT 100")->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2.5">
                <i class="fa-solid fa-money-bill-transfer text-indigo-600"></i>
                <span>Quản Lý Giao Dịch Nạp Tiền</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Theo dõi, kiểm tra lịch sử nạp thẻ cào và nạp VietQR ngân hàng của toàn hệ thống.</p>
        </div>
    </div>

    <!-- Tab Switcher -->
    <div class="flex rounded-2xl bg-slate-100 p-1.5 max-w-lg border border-slate-200">
        <a href="<?= BASE_URL ?>/admin/transactions?tab=card" class="flex-1 py-2.5 text-center text-xs sm:text-sm font-bold rounded-xl transition-all <?= $activeTab === 'card' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
            <i class="fa-solid fa-credit-card mr-2"></i>Nạp Thẻ Cào (<?= count($cardTransactions) ?>)
        </a>
        <a href="<?= BASE_URL ?>/admin/transactions?tab=bank" class="flex-1 py-2.5 text-center text-xs sm:text-sm font-bold rounded-xl transition-all <?= $activeTab === 'bank' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
            <i class="fa-solid fa-qrcode mr-2"></i>Nạp VietQR &amp; Ngân Hàng (<?= count($bankTransactions) ?>)
        </a>
    </div>

    <!-- TAB 1: THẺ CÀO -->
    <?php if ($activeTab === 'card'): ?>
        <div class="rounded-3xl bg-white border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="w-full overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-xs uppercase text-slate-400 bg-slate-50/70 font-semibold tracking-wider">
                            <th class="py-3 px-3 w-12 text-center whitespace-nowrap">ID</th>
                            <th class="py-3 px-3 whitespace-nowrap">Thành Viên</th>
                            <th class="py-3 px-3 whitespace-nowrap">Nhà Mạng</th>
                            <th class="py-3 px-3 whitespace-nowrap">Mệnh Giá</th>
                            <th class="py-3 px-3 whitespace-nowrap">Thực Nhận</th>
                            <th class="py-3 px-3 whitespace-nowrap">PIN / Seri</th>
                            <th class="py-3 px-3 whitespace-nowrap">Thời Gian</th>
                            <th class="py-3 px-2.5 text-center whitespace-nowrap">Trạng Thái</th>
                            <th class="py-3 pr-4 pl-2 text-right whitespace-nowrap">Duyệt Thủ Công</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (!empty($cardTransactions)): ?>
                            <?php foreach ($cardTransactions as $c): ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-3 w-12 text-center font-mono text-xs text-slate-400 whitespace-nowrap">#<?= $c['id'] ?></td>
                                    <td class="py-3 px-3 font-bold text-slate-900 whitespace-nowrap text-xs sm:text-sm">@<?= xss_clean($c['username']) ?></td>
                                    <td class="py-3 px-3 font-black text-rose-600 whitespace-nowrap text-xs"><?= xss_clean($c['telco']) ?></td>
                                    <td class="py-3 px-3 font-bold whitespace-nowrap text-xs sm:text-sm"><?= format_money($c['declared_amount']) ?></td>
                                    <td class="py-3 px-3 font-black text-emerald-600 whitespace-nowrap text-xs sm:text-sm"><?= format_money($c['received_amount']) ?></td>
                                    <td class="py-3 px-3 font-mono text-xs text-slate-500 whitespace-nowrap">
                                        PIN: <strong class="text-slate-800"><?= xss_clean($c['pin']) ?></strong><br>
                                        SERI: <?= xss_clean($c['serial']) ?>
                                    </td>
                                    <td class="py-3 px-3 text-xs text-slate-500 whitespace-nowrap"><?= date('H:i d/m/Y', strtotime($c['created_at'])) ?></td>
                                    <td class="py-3 px-2.5 text-center whitespace-nowrap">
                                        <?php if ($c['status'] === 'success'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Thành Công</span>
                                        <?php elseif ($c['status'] === 'error'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200" title="<?= xss_clean($c['response_message']) ?>">Thẻ Lỗi</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Đang Chờ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-4 pl-2 text-right whitespace-nowrap">
                                        <?php if ($c['status'] === 'pending'): ?>
                                            <div class="inline-flex items-center justify-end gap-1.5 whitespace-nowrap">
                                                <button type="button" onclick="confirmAction('<?= BASE_URL ?>/admin/transactions?tab=card&approve_card_id=<?= $c['id'] ?>', { title: 'Duyệt Thẻ Đúng', text: 'Xác nhận duyệt thẻ đúng mệnh giá và cộng tiền tự động cho người dùng?', icon: 'question', confirmText: 'Duyệt & Cộng Tiền', confirmColor: '#10b981' })" class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/60 font-semibold text-xs transition-colors inline-flex items-center gap-1 cursor-pointer shrink-0" title="Duyệt thẻ thành công">
                                                    <i class="fa-solid fa-check text-[10px]"></i><span>Duyệt Đúng</span>
                                                </button>
                                                <button type="button" onclick="confirmAction('<?= BASE_URL ?>/admin/transactions?tab=card&reject_card_id=<?= $c['id'] ?>', { title: 'Từ Chối Thẻ Cào', text: 'Xác nhận báo thẻ sai và TỪ CHỐI thẻ cào này?', icon: 'warning', confirmText: 'Báo Thẻ Sai', isDanger: true })" class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/60 font-semibold text-xs transition-colors inline-flex items-center gap-1 cursor-pointer shrink-0" title="Báo thẻ sai">
                                                    <i class="fa-solid fa-xmark text-[10px]"></i><span>Báo Sai</span>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">Đã duyệt</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="py-12 text-center text-xs text-slate-400">Chưa có giao dịch thẻ cào nào.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 2: VIETQR & NGÂN HÀNG -->
    <?php if ($activeTab === 'bank'): ?>
        <div class="rounded-3xl bg-white border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="w-full overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-xs uppercase text-slate-400 bg-slate-50/70 font-semibold tracking-wider">
                            <th class="py-3 px-3 w-12 text-center whitespace-nowrap">ID</th>
                            <th class="py-3 px-3 whitespace-nowrap">Mã GD</th>
                            <th class="py-3 px-3 whitespace-nowrap">Thành Viên</th>
                            <th class="py-3 px-3 whitespace-nowrap">Cổng Nạp</th>
                            <th class="py-3 px-3 min-w-[180px]">Nội Dung Chuyển Khoản</th>
                            <th class="py-3 px-3 whitespace-nowrap">Số Tiền Nạp</th>
                            <th class="py-3 px-3 whitespace-nowrap">Ref ID Giao Dịch</th>
                            <th class="py-3 px-3 whitespace-nowrap">Thời Gian</th>
                            <th class="py-3 px-2.5 text-center whitespace-nowrap">Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (!empty($bankTransactions)): ?>
                            <?php foreach ($bankTransactions as $b): ?>
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-3 w-12 text-center font-mono text-xs text-slate-400 whitespace-nowrap">#<?= $b['id'] ?></td>
                                    <td class="py-3 px-3 font-mono text-xs text-slate-500 whitespace-nowrap"><?= xss_clean($b['trans_id']) ?></td>
                                    <td class="py-3 px-3 font-bold text-slate-900 whitespace-nowrap text-xs sm:text-sm">@<?= xss_clean($b['username']) ?></td>
                                    <td class="py-3 px-3 font-black text-indigo-600 whitespace-nowrap text-xs"><?= xss_clean($b['bank_code'] ?: 'VietQR') ?></td>
                                    <td class="py-3 px-3 font-semibold text-slate-800 text-xs sm:text-sm"><?= xss_clean($b['transfer_content']) ?></td>
                                    <td class="py-3 px-3 font-black text-emerald-600 whitespace-nowrap text-xs sm:text-sm">+<?= format_money($b['amount']) ?></td>
                                    <td class="py-3 px-3 font-mono text-xs text-slate-400 whitespace-nowrap"><?= xss_clean($b['ref_transaction_id'] ?: '-') ?></td>
                                    <td class="py-3 px-3 text-xs text-slate-500 whitespace-nowrap"><?= date('H:i d/m/Y', strtotime($b['created_at'])) ?></td>
                                    <td class="py-3 px-2.5 text-center whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= $b['status'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                                            <?= $b['status'] === 'success' ? 'Thành Công' : 'Đang Chờ' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="py-12 text-center text-xs text-slate-400">Chưa có giao dịch nạp VietQR / Ngân hàng nào.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
