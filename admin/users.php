<?php
declare(strict_types=1);
$pageTitle = "Quản Lý Thành Viên & Số Dư";
require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$error = '';

// Xử lý Khóa / Mở khóa tài khoản
if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $uid = public_id_to_internal($db, 'users', $_POST['user_id']);
    $newStatus = $_POST['toggle_status'] === 'banned' ? 'banned' : 'active';

    if ($uid <= 0 || $uid === (int)$currentUser['id']) {
        $error = 'Bạn không thể tự khóa tài khoản của chính mình!';
    } else {
        try {
            $db->beginTransaction();
            $lock = $db->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$uid]);
            if (!$lock->fetchColumn()) { throw new RuntimeException('Người dùng không tồn tại.'); }
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $uid]);
            audit_event($db, 'user.status', 'users', public_reference('users', $uid));
            $db->commit();
            $message = 'Cập nhật trạng thái tài khoản thành công!';
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $error = 'Lỗi cập nhật: ' . public_error($e);
        }
    }
}

// Xử lý Phân quyền Admin / Member
if (isset($_POST['toggle_role']) && isset($_POST['user_id'])) {
    $uid = public_id_to_internal($db, 'users', $_POST['user_id']);
    $newRole = $_POST['toggle_role'] === 'admin' ? 'admin' : 'user';

    if ($uid <= 0 || $uid === (int)$currentUser['id']) {
        $error = 'Bạn không thể tự hạ quyền Admin của chính mình!';
    } else {
        try {
            $db->beginTransaction();
            $lock = $db->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$uid]);
            if (!$lock->fetchColumn()) { throw new RuntimeException('Người dùng không tồn tại.'); }
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $uid]);
            audit_event($db, 'user.role', 'users', public_reference('users', $uid));
            $db->commit();
            $message = 'Phân quyền tài khoản thành công!';
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $error = 'Lỗi phân quyền: ' . public_error($e);
        }
    }
}

// Xử lý Cộng / Trừ tiền thủ công
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_adjust_balance'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token không hợp lệ!';
    } else {
        $targetUserId = public_id_to_internal($db, 'users', $_POST['target_user_id'] ?? '');
        $adjustType   = $_POST['adjust_type'] ?? 'add'; // 'add' hoặc 'sub'
        $amount = '0.00';
        try { $amount = money_decimal(money_minor($_POST['amount'] ?? '0')); } catch (Throwable $e) { $error = public_error($e); }
        $reason       = trim($_POST['reason'] ?? '');

        if ($error !== '' || $targetUserId <= 0 || money_minor($amount) <= 0 || !in_array($adjustType, ['add', 'sub'], true) || empty($reason)) {
            $error = 'Vui lòng nhập số tiền hợp lệ (> 0) và ghi rõ lý do điều chỉnh số dư!';
        } else {
            try {
                $db->beginTransaction();

                // Lấy thông tin người dùng
                $uStmt = $db->prepare("SELECT id, username, balance FROM users WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
                $uStmt->execute([$targetUserId]);
                $targetUser = $uStmt->fetch();

                if (!$targetUser) {
                    throw new Exception('Người dùng không tồn tại!');
                }

                $beforeMinor = money_minor($targetUser['balance']);
                $amountMinor = money_minor($amount);
                $afterMinor = $adjustType === 'add' ? money_add($beforeMinor, $amountMinor) : $beforeMinor - $amountMinor;
                $balanceBefore = money_decimal($beforeMinor);
                $delta = ($adjustType === 'add' ? '' : '-') . $amount;
                $balanceAfter = money_decimal($afterMinor);

                if ($balanceAfter < 0) {
                    throw new Exception('Số dư của người dùng không đủ để thực hiện trừ khoản tiền này!');
                }

                // Cập nhật số dư
                $upStmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $upStmt->execute([$balanceAfter, $targetUserId]);

                // Ghi nhật ký biến động số dư (balance_logs)
                $note = ($adjustType === 'add' ? 'Admin cộng tiền: ' : 'Admin trừ tiền: ') . $reason;
                $logStmt = $db->prepare("INSERT INTO balance_logs (user_id, admin_id, amount_before, amount, amount_after, type, note)
                                        VALUES (?, ?, ?, ?, ?, 'admin_adjust', ?)");
                $logStmt->execute([$targetUserId, $currentUser['id'], $balanceBefore, $delta, $balanceAfter, $note]);

                audit_event($db, 'user.balance.adjust', 'users', public_reference('users', $targetUserId));
                $db->commit();
                $message = "Đã " . ($adjustType === 'add' ? 'cộng' : 'trừ') . " thành công " . format_money($amount) . " cho user {$targetUser['username']}!";
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Lỗi điều chỉnh số dư: ' . public_error($e);
            }
        }
    }
}

// Tìm kiếm thành viên
$search = trim($_GET['q'] ?? '');
$users = [];
if ($db) {
    try {
        $sql = "SELECT * FROM users WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($search)) {
            $sql .= " AND (username LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR fullname LIKE ? ESCAPE '!')";
            $params = [like_pattern($search), like_pattern($search), like_pattern($search)];
        }
        $sql .= " ORDER BY id DESC LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
    } catch (Throwable $e) {}
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2.5">
                <i class="fa-solid fa-users text-indigo-600"></i>
                <span>Quản Lý Thành Viên</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Danh sách người dùng, phân quyền, khóa tài khoản và điều chỉnh số dư.</p>
        </div>

        <!-- Form tìm kiếm -->
        <form method="GET" action="" class="flex items-center gap-2">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="q" value="<?= xss_clean($search) ?>" placeholder="Tìm username, email..." class="pl-8 pr-4 py-2 rounded-xl bg-white border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 w-64 shadow-sm">
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition-colors shadow-sm">
                Tìm
            </button>
            <?php if (!empty($search)): ?>
                <a href="<?= e(BASE_URL) ?>/admin/users" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors" title="Xóa tìm kiếm">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bảng thành viên -->
    <div class="rounded-3xl bg-white border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="w-full overflow-x-auto">
            <div class="responsive-table" role="region" aria-label="Bảng dữ liệu, cuộn ngang khi cần" tabindex="0"><table data-responsive="wide" class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-xs uppercase text-slate-400 bg-slate-50/70 font-semibold tracking-wider">
                        <th class="py-3 px-3 w-12 text-center whitespace-nowrap">ID</th>
                        <th class="py-3 px-3 whitespace-nowrap">Thành Viên</th>
                        <th class="py-3 px-3 whitespace-nowrap">Email</th>
                        <th class="py-3 px-3 whitespace-nowrap">Số Dư</th>
                        <th class="py-3 px-3 whitespace-nowrap">Tổng Nạp</th>
                        <th class="py-3 px-2.5 text-center whitespace-nowrap">Vai Trò</th>
                        <th class="py-3 px-2.5 text-center whitespace-nowrap">Trạng Thái</th>
                        <th class="py-3 pr-4 pl-2 text-right whitespace-nowrap">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u):
                            $displayName = !empty(trim($u['fullname'] ?? '')) ? $u['fullname'] : $u['username'];
                            $avatarField = trim($u['avatar'] ?? ($u['profile_image'] ?? ''));

                            $hasCustomAvatar = false;
                            $avatarUrl = '';
                            if (!empty($avatarField) && $avatarField !== 'assets/images/default-avatar.png') {
                                if (preg_match('#^https?://#i', $avatarField)) {
                                    $hasCustomAvatar = true;
                                    $avatarUrl = $avatarField;
                                } else {
                                    $cleanAvatarPath = ltrim($avatarField, '/');
                                    $diskPath = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__)) . '/' . $cleanAvatarPath;
                                    if (file_exists($diskPath)) {
                                        $hasCustomAvatar = true;
                                        $avatarUrl = (defined('BASE_URL') ? BASE_URL : '') . '/' . $cleanAvatarPath;
                                    } elseif (file_exists(dirname(__DIR__) . '/' . $cleanAvatarPath)) {
                                        $hasCustomAvatar = true;
                                        $avatarUrl = (defined('BASE_URL') ? BASE_URL : '') . '/' . $cleanAvatarPath;
                                    }
                                }
                            }

                            // Chữ cái đầu tiên và gradient theo ID cho người dùng chưa có ảnh đại diện
                            $firstLetter = mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8');
                            $avatarGradients = [
                                'from-indigo-500 to-indigo-700 border-indigo-200',
                                'from-violet-500 to-purple-700 border-purple-200',
                                'from-blue-500 to-cyan-700 border-blue-200',
                                'from-emerald-500 to-teal-700 border-emerald-200',
                                'from-rose-500 to-pink-700 border-rose-200',
                                'from-amber-500 to-orange-700 border-amber-200'
                            ];
                            $userGradient = $avatarGradients[$u['id'] % count($avatarGradients)];
                            $fallbackAvatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&background=6366f1&color=fff&bold=true&rounded=true';
                        ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-3 w-12 text-center font-mono text-xs text-slate-400 whitespace-nowrap">#<?= e(public_reference('users', (int)$u['id'])) ?></td>
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <?php if ($hasCustomAvatar): ?>
                                            <img src="<?= e(safe_url($avatarUrl)) ?>"
                                                 alt="Avatar"
                                                 onerror="this.onerror=null;this.src='<?= xss_clean($fallbackAvatarUrl) ?>';"
                                                 class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-2xs shrink-0">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?= e($userGradient) ?> text-white font-black text-sm flex items-center justify-center border shadow-2xs shrink-0 select-none uppercase">
                                                <?= xss_clean($firstLetter) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <strong class="text-slate-900 block font-bold text-xs sm:text-sm truncate max-w-[160px]"><?= xss_clean($displayName) ?></strong>
                                            <span class="text-xs text-slate-500 block leading-tight mt-0.5 truncate max-w-[140px]"><?= xss_clean($u['username']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-xs text-slate-600 font-mono whitespace-nowrap"><?= xss_clean($u['email']) ?></td>
                                <td class="py-3 px-3 font-extrabold text-indigo-600 whitespace-nowrap"><?= e(format_money($u['balance'])) ?></td>
                                <td class="py-3 px-3 font-bold text-emerald-600 text-xs whitespace-nowrap"><?= e(format_money($u['total_deposited'])) ?></td>
                                <td class="py-3 px-2.5 text-center whitespace-nowrap">
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200">ADMIN</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">MEMBER</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-2.5 text-center whitespace-nowrap">
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Hoạt Động</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Đã Khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 pr-4 pl-2 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <!-- Nút Cộng/Trừ tiền -->
                                        <button type="button" onclick="openBalanceModal(<?= e(js(public_reference('users', (int)$u['id']))) ?>, <?= e(js($u['username'])) ?>, <?= e(js(money_decimal(money_minor($u['balance'])))) ?>)" class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold border border-indigo-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0" title="Cộng / Trừ tiền">
                                            <i class="fa-solid fa-coins text-[10px]"></i><span>Số Dư</span>
                                        </button>

                                        <!-- Nút Khóa / Mở -->
                                        <?php if ($u['status'] === 'active'): ?>
                                            <button type="button" onclick="openUserStatusModal(<?= e(js(public_reference('users', (int)$u['id']))) ?>, <?= e(js($displayName)) ?>, 'banned')" class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold border border-rose-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0">
                                                <i class="fa-solid fa-lock text-[10px]"></i><span>Khóa</span>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" onclick="openUserStatusModal(<?= e(js(public_reference('users', (int)$u['id']))) ?>, <?= e(js($displayName)) ?>, 'active')" class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold border border-emerald-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0">
                                                <i class="fa-solid fa-lock-open text-[10px]"></i><span>Mở</span>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Nút Quyền Admin -->
                                        <?php if ($u['role'] === 'user'): ?>
                                            <button type="button" onclick="openUserStatusModal(<?= e(js(public_reference('users', (int)$u['id']))) ?>, <?= e(js($displayName)) ?>, 'admin')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-purple-100 hover:text-purple-700 text-slate-600 text-xs font-semibold border border-slate-200/80 transition-colors inline-flex items-center justify-center cursor-pointer shrink-0" title="Cấp quyền Admin">
                                                <i class="fa-solid fa-shield text-[11px]"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" onclick="openUserStatusModal(<?= e(js(public_reference('users', (int)$u['id']))) ?>, <?= e(js($displayName)) ?>, 'user')" class="w-7 h-7 rounded-lg bg-purple-50 hover:bg-amber-100 hover:text-amber-700 text-purple-700 text-xs font-semibold border border-purple-200/60 transition-colors inline-flex items-center justify-center cursor-pointer shrink-0" title="Hạ quyền Admin">
                                                <i class="fa-solid fa-user-minus text-[11px]"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="py-12 text-center text-xs text-slate-400">Không tìm thấy thành viên nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<!-- MODAL CỘNG / TRỪ TIỀN THỦ CÔNG (Chuẩn SaaS Backdrop & Overlay - Compact) -->
<div id="balanceModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/60 backdrop-blur-sm overflow-y-auto" onclick="if (event.target === this) closeBalanceModal()">
    <div class="max-h-[92dvh] overflow-y-auto w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-slate-200/80 p-5 sm:p-6 my-auto relative">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 gap-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-base shrink-0">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900">Điều Chỉnh Số Dư</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Cộng hoặc trừ số dư ví của thành viên</p>
                </div>
            </div>
            <button type="button" onclick="closeBalanceModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors shrink-0" title="Đóng (Esc)">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3 mt-4">
            <input type="hidden" name="action_adjust_balance" value="1">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="target_user_id" id="balUserId" value="0">

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1">
                <div class="text-xs text-slate-500">Thành viên: <strong class="text-slate-900 font-bold" id="balUsername"></strong></div>
                <div class="text-xs text-slate-500">Số dư hiện tại: <strong class="text-emerald-600 font-extrabold text-sm" id="balCurrent"></strong></div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Loại Thao Tác</label>
                <div class="grid grid-cols-2 gap-2.5">
                    <label class="cursor-pointer">
                        <input type="radio" name="adjust_type" value="add" checked class="peer sr-only">
                        <div class="p-2.5 rounded-xl border-2 border-slate-200 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 text-center font-bold text-xs text-slate-600 peer-checked:text-emerald-700 transition-all">
                            <i class="fa-solid fa-plus mr-1"></i>Cộng Tiền (+)
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="adjust_type" value="sub" class="peer sr-only">
                        <div class="p-2.5 rounded-xl border-2 border-slate-200 peer-checked:border-rose-500 peer-checked:bg-rose-50 text-center font-bold text-xs text-slate-600 peer-checked:text-rose-700 transition-all">
                            <i class="fa-solid fa-minus mr-1"></i>Trừ Tiền (-)
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Số Tiền (VND) *</label>
                <input type="number" name="amount" required min="1000" step="1000" placeholder="Ví dụ: 100000" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-bold text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all">
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Lý Do Điều Chỉnh (Audit Log) *</label>
                <input type="text" name="reason" required placeholder="Ví dụ: Nạp bù, khuyến mãi sự kiện, hoàn tiền..." class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all">
            </div>

            <!-- Modal Footer -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeBalanceModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                    Hủy
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-check text-[11px]"></i>
                    <span>Xác Nhận Thực Hiện</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL XÁC NHẬN KHÓA / MỞ KHÓA TÀI KHOẢN (Chuẩn SaaS) -->
<div id="userStatusModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm overflow-y-auto" onclick="if (event.target === this) closeUserStatusModal()">
    <div class="max-h-[90dvh] overflow-y-auto w-full max-w-md bg-white rounded-3xl shadow-2xl border border-slate-200/80 p-6 sm:p-7 my-auto relative text-center">
        <!-- Nút đóng -->
        <button type="button" onclick="closeUserStatusModal()" class="absolute top-4 right-4 w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors" title="Đóng (Esc)">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>

        <!-- Icon -->
        <div id="statusModalIconBox" class="w-14 h-14 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center text-2xl mx-auto mb-4">
            <i id="statusModalIcon" class="fa-solid fa-lock"></i>
        </div>

        <h3 id="statusModalTitle" class="text-lg font-black text-slate-900">Khóa Tài Khoản</h3>
        <p id="statusModalDesc" class="text-xs text-slate-500 mt-2 leading-relaxed">
            Bạn có chắc chắn muốn KHÓA tài khoản của thành viên này?
        </p>

        <!-- Modal Footer -->
        <div class="mt-6 flex items-center justify-center gap-3">
            <button type="button" onclick="closeUserStatusModal()" class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                Hủy Bỏ
            </button>
            <a id="statusModalConfirmBtn" href="#" class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-md shadow-rose-500/20 text-xs inline-flex items-center justify-center gap-1.5">
                <i id="statusModalBtnIcon" class="fa-solid fa-lock text-xs"></i>
                <span id="statusModalBtnText">Xác Nhận Khóa</span>
            </a>
        </div>
    </div>
</div>

<script>
    function ensureBalanceModalInBody() {
        var modal = document.getElementById('balanceModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        return modal;
    }

    function ensureUserStatusModalInBody() {
        var modal = document.getElementById('userStatusModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        return modal;
    }

    document.addEventListener('DOMContentLoaded', function() {
        ensureBalanceModalInBody();
        ensureUserStatusModalInBody();
    });

    function openUserStatusModal(userId, username, actionType) {
        var modal = ensureUserStatusModalInBody();
        var titleEl = document.getElementById('statusModalTitle');
        var descEl = document.getElementById('statusModalDesc');
        var btn = document.getElementById('statusModalConfirmBtn');
        var btnText = document.getElementById('statusModalBtnText');
        var btnIcon = document.getElementById('statusModalBtnIcon');
        var iconBox = document.getElementById('statusModalIconBox');
        var icon = document.getElementById('statusModalIcon');

        if (actionType === 'banned') {
            titleEl.innerText = 'Khóa Tài Khoản';
            descEl.textContent = 'Bạn có chắc chắn muốn KHÓA tài khoản của thành viên ' + username + '?Tài khoản này sẽ bị tạm ngưng và không thể đăng nhập.';
            iconBox.className = 'w-14 h-14 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center text-2xl mx-auto mb-4';
            icon.className = 'fa-solid fa-lock';
            btn.className = 'bg-rose-600 hover:bg-rose-700 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-md shadow-rose-500/20 text-xs inline-flex items-center justify-center gap-1.5';
            btnIcon.className = 'fa-solid fa-lock text-xs';
            btnText.innerText = 'Xác Nhận Khóa';
            btn.href = '<?= BASE_URL ?>/admin/users?user_id=' + userId + '&toggle_status=banned';
        } else if (actionType === 'active') {
            titleEl.innerText = 'Mở Khóa Tài Khoản';
            descEl.textContent = 'Bạn có chắc chắn muốn MỞ KHÓA tài khoản cho thành viên ' + username + '?Thành viên này sẽ có thể đăng nhập bình thường.';
            iconBox.className = 'w-14 h-14 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center text-2xl mx-auto mb-4';
            icon.className = 'fa-solid fa-lock-open';
            btn.className = 'bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-md shadow-emerald-500/20 text-xs inline-flex items-center justify-center gap-1.5';
            btnIcon.className = 'fa-solid fa-lock-open text-xs';
            btnText.innerText = 'Xác Nhận Mở Khóa';
            btn.href = '<?= BASE_URL ?>/admin/users?user_id=' + userId + '&toggle_status=active';
        } else if (actionType === 'admin') {
            titleEl.innerText = 'Cấp Quyền Admin';
            descEl.textContent = 'Cấp quyền Quản trị viên (Admin) cho tài khoản ' + username + '?';
            iconBox.className = 'w-14 h-14 rounded-2xl bg-purple-50 border border-purple-200 text-purple-600 flex items-center justify-center text-2xl mx-auto mb-4';
            icon.className = 'fa-solid fa-shield';
            btn.className = 'bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-md shadow-purple-500/20 text-xs inline-flex items-center justify-center gap-1.5';
            btnIcon.className = 'fa-solid fa-shield text-xs';
            btnText.innerText = 'Xác Nhận Cấp Quyền';
            btn.href = '<?= BASE_URL ?>/admin/users?user_id=' + userId + '&toggle_role=admin';
        } else if (actionType === 'user') {
            titleEl.innerText = 'Hạ Quyền Admin';
            descEl.textContent = 'Hạ quyền Quản trị viên của ' + username + ' về Thành viên (Member)?';
            iconBox.className = 'w-14 h-14 rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center text-2xl mx-auto mb-4';
            icon.className = 'fa-solid fa-user-minus';
            btn.className = 'bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-md shadow-amber-500/20 text-xs inline-flex items-center justify-center gap-1.5';
            btnIcon.className = 'fa-solid fa-user-minus text-xs';
            btnText.innerText = 'Xác Nhận Hạ Quyền';
            btn.href = '<?= BASE_URL ?>/admin/users?user_id=' + userId + '&toggle_role=user';
        }

        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeUserStatusModal() {
        var modal = document.getElementById('userStatusModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        document.body.classList.remove('overflow-hidden');
    }

    function openBalanceModal(userId, username, balance) {
        var modal = ensureBalanceModalInBody();
        document.getElementById('balUserId').value = userId;
        document.getElementById('balUsername').innerText = username;
        document.getElementById('balCurrent').innerText = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(balance);
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeBalanceModal() {
        var modal = document.getElementById('balanceModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        document.body.classList.remove('overflow-hidden');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeBalanceModal();
            closeUserStatusModal();
        }
    });
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
