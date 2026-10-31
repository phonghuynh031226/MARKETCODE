<?php
declare(strict_types=1);
$pageTitle = "Quản Lý Đơn Hàng Mua Mã Nguồn";
require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$error = '';

// Xử lý HOÀN TIỀN ĐƠN HÀNG (Refund)
if (isset($_POST['refund_order_id'])) {
    $orderId = public_id_to_internal($db, 'orders', $_POST['refund_order_id']);
    try {
        $db->beginTransaction();

        $oStmt = $db->prepare("SELECT o.* FROM orders o WHERE o.id = ? AND o.deleted_at IS NULL FOR UPDATE");
        $oStmt->execute([$orderId]);
        $order = $oStmt->fetch();

        if (!$order) {
            throw new Exception('Đơn hàng không tồn tại!');
        }
        if ($order['status'] !== 'completed') {
            throw new Exception('Đơn hàng này đã được hoàn tiền trước đó!');
        }

        $refundAmount = money_decimal(money_minor($order['price']));
        $lock = $db->prepare('SELECT balance FROM users WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $lock->execute([$order['user_id']]);
        $balance = $lock->fetchColumn();
        if ($balance === false) { throw new RuntimeException('Người dùng không tồn tại.'); }
        $label = $db->prepare('SELECT username FROM users WHERE id = ?');
        $label->execute([$order['user_id']]);
        $order['username'] = (string)$label->fetchColumn();
        $label = $db->prepare('SELECT title FROM products WHERE id = ?');
        $label->execute([$order['product_id']]);
        $order['product_title'] = (string)$label->fetchColumn();
        $balBefore = money_decimal(money_minor($balance));
        $currentBal = money_decimal(money_add(money_minor($balance), money_minor($refundAmount)));
        $upU = $db->prepare('UPDATE users SET balance = ? WHERE id = ?');
        $upU->execute([$currentBal, $order['user_id']]);

        // Ghi log
        $logStmt = $db->prepare("INSERT INTO balance_logs (user_id, admin_id, amount_before, amount, amount_after, type, note)
                                VALUES (?, ?, ?, ?, ?, 'refund', ?)");
        $logStmt->execute([
            $order['user_id'],
            $currentUser['id'],
            $balBefore,
            $refundAmount,
            $currentBal,
            "Admin hoàn tiền đơn hàng #{$order['order_code']} ({$order['product_title']})"
        ]);

        // Cập nhật trạng thái đơn
        $upO = $db->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?");
        $upO->execute([$orderId]);

        audit_event($db, 'order.refund', 'orders', public_reference('orders', $orderId));
        $db->commit();
        $message = "Đã hoàn tiền " . format_money($refundAmount) . " cho khách hàng @{$order['username']} thành công!";
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'Lỗi hoàn tiền: ' . public_error($e);
    }
}

// Lấy danh sách đơn hàng
$orders = [];
if ($db) {
    try {
        $orders = $db->query("SELECT o.*, u.username, u.email, p.title as product_title, p.download_url
                              FROM orders o
                              JOIN users u ON o.user_id = u.id
                              JOIN products p ON o.product_id = p.id
                              WHERE o.deleted_at IS NULL ORDER BY o.id DESC LIMIT 100")->fetchAll();
    } catch (Throwable $e) {}
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2.5">
                <i class="fa-solid fa-cart-shopping text-indigo-600"></i>
                <span>Quản Lý Đơn Hàng Mua Code</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Danh sách toàn bộ các giao dịch mua mã nguồn và cấp token tải file.</p>
        </div>
    </div>

    <!-- Bảng đơn hàng -->
    <div class="rounded-3xl bg-white border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="w-full overflow-x-auto">
            <div class="responsive-table" role="region" aria-label="Bảng dữ liệu, cuộn ngang khi cần" tabindex="0"><table data-responsive="wide" class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-xs uppercase text-slate-400 bg-slate-50/70 font-semibold tracking-wider">
                        <th class="py-3 px-3 w-24 whitespace-nowrap">Mã Đơn</th>
                        <th class="py-3 px-3 whitespace-nowrap">Người Mua</th>
                        <th class="py-3 px-3 min-w-[180px] whitespace-nowrap">Mã Nguồn</th>
                        <th class="py-3 px-3 whitespace-nowrap">Giá Bán</th>
                        <th class="py-3 px-3 whitespace-nowrap">Download Token</th>
                        <th class="py-3 px-3 whitespace-nowrap">Thời Gian</th>
                        <th class="py-3 px-2.5 text-center whitespace-nowrap">Trạng Thái</th>
                        <th class="py-3 pr-4 pl-2 text-right whitespace-nowrap">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $o): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-3 font-mono font-bold text-xs text-indigo-600 whitespace-nowrap">#<?= xss_clean($o['order_code']) ?></td>
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <strong class="text-slate-900 block font-bold text-xs sm:text-sm">@<?= xss_clean($o['username']) ?></strong>
                                    <span class="text-[11px] text-slate-400 truncate block max-w-[140px]"><?= xss_clean($o['email']) ?></span>
                                </td>
                                <td class="py-3 px-3 min-w-[180px]">
                                    <span class="font-bold text-slate-800 line-clamp-1 max-w-xs block leading-snug text-xs sm:text-sm" title="<?= xss_clean($o['product_title']) ?>"><?= xss_clean($o['product_title']) ?></span>
                                </td>
                                <td class="py-3 px-3 font-black text-emerald-600 whitespace-nowrap text-xs sm:text-sm"><?= e(format_money($o['price'])) ?></td>
                                <td class="py-3 px-3 font-mono text-xs text-slate-400 max-w-[120px] truncate whitespace-nowrap" title="<?= xss_clean($o['download_token']) ?>">
                                    <?= e(substr($o['download_token'], 0, 14)) ?>...
                                </td>
                                <td class="py-3 px-3 text-xs text-slate-500 whitespace-nowrap"><?= e(date('H:i d/m/Y', strtotime($o['created_at']))) ?></td>
                                <td class="py-3 px-2.5 text-center whitespace-nowrap">
                                    <?php if ($o['status'] === 'completed'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Hoàn Tất</span>
                                    <?php elseif ($o['status'] === 'refunded'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Đã Hoàn Tiền</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200"><?= e($o['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 pr-4 pl-2 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <!-- Link tải kiểm tra -->
                                        <a href="<?= e(safe_url($o['download_url'])) ?>" target="_blank" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 border border-slate-200/80 inline-flex items-center gap-1 transition-colors shrink-0" title="Kiểm tra link tải">
                                            <i class="fa-solid fa-download text-[10px]"></i><span>Link</span>
                                        </a>

                                        <!-- Nút Hoàn tiền -->
                                        <?php if ($o['status'] === 'completed'): ?>
                                            <button type="button" onclick="confirmAction('<?= e(BASE_URL) ?>/admin/orders?refund_order_id=<?= e(public_reference('orders', (int)$o['id'])) ?>', { title: 'Hoàn Tiền Đơn Hàng', text: <?= e(js('Xác nhận hoàn tiền cho đơn hàng #' . $o['order_code'] . '? Tiền sẽ được hoàn trả lại vào ví tài khoản của khách hàng.')) ?>, icon: 'warning', confirmText: 'Đồng Ý Hoàn Tiền', isDanger: true })" class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-xs font-semibold text-rose-700 border border-rose-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0" title="Hoàn lại tiền">
                                                <i class="fa-solid fa-rotate-left text-[10px]"></i><span>Hoàn Tiền</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="py-12 text-center text-xs text-slate-400">Chưa có đơn hàng mua mã nguồn nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
