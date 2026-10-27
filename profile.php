<?php
$pageTitle = "Trang Cá Nhân & Quản Lý Tài Khoản - MarketCode";
require_once __DIR__ . '/config/config.php';
require_login();

$user = current_user();
$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// Lấy số lượng mã nguồn đã mua & tổng tiền đã nạp
$totalBought = 0;
$totalDeposited = (float)($user['total_deposited'] ?? 0);

if ($db) {
    try {
        $bStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed'");
        $bStmt->execute([$user['id']]);
        $totalBought = (int)$bStmt->fetchColumn();

        if ($totalDeposited <= 0) {
            $bankStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM bank_transactions WHERE user_id = ? AND status = 'success'");
            $bankStmt->execute([$user['id']]);
            $bankSum = (float)$bankStmt->fetchColumn();

            $cardStmt = $db->prepare("SELECT COALESCE(SUM(received_amount), 0) FROM card_transactions WHERE user_id = ? AND status = 'success'");
            $cardStmt->execute([$user['id']]);
            $cardSum = (float)$cardStmt->fetchColumn();

            $totalDeposited = $bankSum + $cardSum;
        }
    } catch (Exception $e) {}
}

// Xử lý Cập nhật thông tin cá nhân & Upload Avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Mã bảo mật CSRF Token không hợp lệ!';
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $avatar = trim($_POST['avatar'] ?? ($user['avatar'] ?? ''));

        if (!empty($_FILES['avatar_file']['name']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $fileInfo = pathinfo($_FILES['avatar_file']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');

            if (!in_array($ext, $allowedExts)) {
                $error = 'Định dạng ảnh không hợp lệ! Chỉ chấp nhận JPG, PNG, WEBP, GIF.';
            } elseif ($_FILES['avatar_file']['size'] > 5 * 1024 * 1024) {
                $error = 'Kích thước file ảnh không được vượt quá 5MB!';
            } else {
                $uploadDir = ROOT_PATH . '/assets/uploads/avatars';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $newFileName = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
                $destination = $uploadDir . '/' . $newFileName;

                if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $destination)) {
                    $avatar = 'assets/uploads/avatars/' . $newFileName;
                } else {
                    $error = 'Không thể lưu file ảnh tải lên máy chủ!';
                }
            }
        }

        if (empty($error)) {
            if (empty($fullname)) {
                $error = 'Họ và tên không được để trống!';
            } else {
                try {
                    $upStmt = $db->prepare("UPDATE users SET fullname = ?, avatar = ? WHERE id = ?");
                    $upStmt->execute([$fullname, $avatar, $user['id']]);
                    $success = 'Cập nhật thông tin cá nhân & ảnh đại diện thành công!';
                    $user = current_user(); // Refresh
                } catch (Exception $e) {
                    $error = 'Lỗi cập nhật: ' . $e->getMessage();
                }
            }
        }
    }
}

// Xử lý Đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Mã bảo mật CSRF Token không hợp lệ!';
    } else {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
            $error = 'Vui lòng điền đầy đủ các thông tin mật khẩu!';
        } elseif (strlen($newPass) < 6) {
            $error = 'Mật khẩu mới phải có tối thiểu 6 ký tự!';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Mật khẩu xác nhận không trùng khớp!';
        } else {
            try {
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$user['id']]);
                $currentHash = $stmt->fetchColumn();

                if (!password_verify($oldPass, $currentHash)) {
                    $error = 'Mật khẩu hiện tại không chính xác!';
                } else {
                    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                    $upPass = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upPass->execute([$newHash, $user['id']]);
                    $success = 'Đổi mật khẩu thành công! Vui lòng ghi nhớ mật khẩu mới.';
                }
            } catch (Exception $e) {
                $error = 'Lỗi đổi mật khẩu: ' . $e->getMessage();
            }
        }
    }
}

$userRoleText = ($user['role'] === 'admin') ? 'Quản trị viên' : 'Thành viên chính thức';
$clientIp = function_exists('getClientIP') ? getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
/* Floating Toast Animations (Vercel / Sonner style) */
@keyframes toastSlideIn {
    0% {
        opacity: 0;
        transform: translateX(120%);
    }
    100% {
        opacity: 1;
        transform: translateX(0);
    }
}
@keyframes toastSlideOut {
    0% {
        opacity: 1;
        transform: translateX(0);
    }
    100% {
        opacity: 0;
        transform: translateX(60%);
    }
}
@keyframes toastProgress {
    0% { width: 100%; }
    100% { width: 0%; }
}
.toast-enter {
    animation: toastSlideIn 400ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.toast-exit {
    animation: toastSlideOut 350ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.toast-progress-bar {
    animation: toastProgress 3.5s linear forwards;
}
</style>

<?php if (!empty($success) || !empty($error)): 
    $isSuccess  = !empty($success);
    $toastTitle = $isSuccess ? 'Thành công!' : 'Có lỗi xảy ra!';
    $toastMsg   = $isSuccess ? $success : $error;
    $toastBg    = $isSuccess ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600';
    $toastIcon  = $isSuccess ? 'fa-solid fa-circle-check' : 'fa-solid fa-triangle-exclamation';
    $toastBar   = $isSuccess ? 'bg-emerald-500' : 'bg-rose-500';
?>
<!-- Floating Toast Notification (Vercel / Sonner Style) -->
<div id="floatingToast" class="fixed top-24 right-6 z-[9999] max-w-sm w-full bg-white/95 backdrop-blur-xl border border-slate-200/80 rounded-2xl p-4 shadow-[0_20px_50px_rgba(15,23,42,0.15)] flex items-start gap-3.5 overflow-hidden toast-enter">
    <!-- Icon bên trái -->
    <div class="w-9 h-9 rounded-xl <?= $toastBg ?> flex items-center justify-center text-base shrink-0 shadow-2xs">
        <i class="<?= $toastIcon ?>"></i>
    </div>
    <!-- Nội dung ở giữa -->
    <div class="flex-1 min-w-0 pr-1">
        <h4 class="text-sm font-extrabold text-slate-900 leading-tight"><?= $toastTitle ?></h4>
        <p class="text-xs text-slate-600 mt-0.5 leading-relaxed"><?= xss_clean($toastMsg) ?></p>
    </div>
    <!-- Nút tắt bên phải -->
    <button type="button" onclick="dismissToast()" class="text-slate-400 hover:text-slate-600 p-1 -mr-1 -mt-1 transition-colors cursor-pointer" title="Đóng thông báo">
        <i class="fa-solid fa-xmark text-sm"></i>
    </button>
    <!-- Thanh tiến trình mảnh dưới đáy -->
    <div class="absolute bottom-0 left-0 h-1 <?= $toastBar ?> toast-progress-bar"></div>
</div>
<?php endif; ?>

<main class="flex-1">
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">

    <!-- ===================================================== -->
    <!-- 1. UNIFIED PROFILE BANNER CARD (HEADER & 3 CHỈ SỐ)    -->
    <!-- ===================================================== -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 mb-5 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-5">
        
        <!-- Bên trái: Avatar Tương Tác + Tên + Vai trò -->
        <div class="flex items-center gap-4 w-full lg:w-auto">
            <!-- Vòng tròn Avatar tương tác gọn gàng -->
            <div class="relative group cursor-pointer shrink-0" onclick="triggerAvatarUpload()" title="Bấm vào đây để thay đổi ảnh đại diện">
                <img id="bannerAvatarImg"
                     src="<?= xss_clean(get_user_avatar($user)) ?>"
                     alt="Avatar"
                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['username']) ?>&background=4f46e5&color=fff&bold=true';"
                     class="w-14 h-14 rounded-xl object-cover ring-2 ring-indigo-50 border border-slate-200/80 shadow-2xs transition-transform duration-200 group-hover:scale-105">
                
                <!-- Overlay mờ khi hover -->
                <div class="absolute inset-0 rounded-xl bg-slate-900/60 backdrop-blur-xs text-white text-[10px] font-bold flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                    <i class="fa-solid fa-camera text-xs mb-0.5"></i>
                    <span>Đổi</span>
                </div>

                <!-- Icon bút nhỏ góc dưới -->
                <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-indigo-600 text-white text-[9px] flex items-center justify-center shadow-xs border-2 border-white">
                    <i class="fa-solid fa-pen text-[8px]"></i>
                </span>
            </div>

            <!-- Tên & Thông tin định danh -->
            <div class="space-y-0.5">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg font-extrabold text-slate-900 tracking-tight">
                        <?= xss_clean($user['fullname'] ?: $user['username']) ?>
                    </h1>
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                            <i class="fa-solid fa-shield-halved text-[9px]"></i>
                            <?= $userRoleText ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                            <i class="fa-solid fa-circle-check text-[9px]"></i>
                            <?= $userRoleText ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-slate-500 font-medium flex items-center gap-1.5">
                    <span>@<?= xss_clean($user['username']) ?></span>
                    <span class="text-slate-300">•</span>
                    <span class="text-slate-600"><?= xss_clean($user['email']) ?></span>
                </p>
            </div>
        </div>

        <!-- Bên phải: 3 Chỉ Số Thống Kê Ngang Hàng Gọn Gàng -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 w-full lg:w-auto">
            <!-- 1. Số dư khả dụng -->
            <div class="bg-slate-50/80 border border-slate-200/70 rounded-xl px-4 py-2.5 flex flex-col justify-center min-w-[140px] shadow-2xs">
                <div class="flex items-center justify-between gap-2 mb-0.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Số dư khả dụng</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                </div>
                <div class="text-base font-extrabold text-indigo-600 tracking-tight">
                    <?= number_format($user['balance'], 0, ',', '.') ?> đ
                </div>
            </div>

            <!-- 2. Tổng tiền đã nạp -->
            <div class="bg-slate-50/80 border border-slate-200/70 rounded-xl px-4 py-2.5 flex flex-col justify-center min-w-[140px] shadow-2xs">
                <div class="flex items-center justify-between gap-2 mb-0.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tổng tiền nạp</span>
                    <i class="fa-solid fa-arrow-trend-up text-[10px] text-emerald-500"></i>
                </div>
                <div class="text-base font-extrabold text-emerald-600 tracking-tight">
                    <?= number_format($totalDeposited, 0, ',', '.') ?> đ
                </div>
            </div>

            <!-- 3. Mã nguồn đã mua -->
            <div class="bg-slate-50/80 border border-slate-200/70 rounded-xl px-4 py-2.5 flex flex-col justify-center min-w-[140px] shadow-2xs">
                <div class="flex items-center justify-between gap-2 mb-0.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Mã nguồn đã mua</span>
                    <i class="fa-solid fa-bag-shopping text-[10px] text-slate-400"></i>
                </div>
                <div class="text-base font-extrabold text-slate-900 tracking-tight">
                    <?= number_format($totalBought) ?> <span class="text-xs font-semibold text-slate-500">bộ code</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- 2. BỐ CỤC 2 CỘT CÂN ĐỐI TUYỆT ĐỐI (GAP-5, ITEMS-STRETCH)-->
    <!-- ===================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">

        <!-- ================================================= -->
        <!-- CỘT TRÁI (col-span-6): THÔNG TIN ĐỊNH DANH        -->
        <!-- ================================================= -->
        <div class="lg:col-span-6 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-sm flex flex-col justify-between">
            <form method="POST" action="" enctype="multipart/form-data" id="profileForm" class="flex flex-col justify-between h-full space-y-4">
                <input type="hidden" name="action_profile" value="1">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="avatar" value="<?= xss_clean($user['avatar']) ?>">

                <!-- Phần trên: Tiêu đề + 3 Hàng nhập liệu gọn gàng -->
                <div class="space-y-3.5">
                    <div class="pb-1 border-b border-slate-100">
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs shadow-2xs">
                                <i class="fa-regular fa-id-card"></i>
                            </span>
                            <span>Thông Tin Định Danh</span>
                        </h2>
                    </div>

                    <!-- Hàng 1 (Grid 2 cột): Tên đăng nhập & Vai trò cố định -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                                <span>Tên đăng nhập</span>
                                <span class="text-[10px] text-slate-400 font-normal"><i class="fa-solid fa-lock text-[8px] mr-0.5"></i>Cố định</span>
                            </label>
                            <div class="relative">
                                <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="text" value="<?= xss_clean($user['username']) ?>" disabled class="w-full pl-8 pr-3 py-2.5 rounded-xl bg-slate-50 text-xs sm:text-sm font-mono text-slate-500 border border-slate-200/80 cursor-not-allowed">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                                <span>Vai trò tài khoản</span>
                                <span class="text-[10px] text-slate-400 font-normal"><i class="fa-solid fa-lock text-[8px] mr-0.5"></i>Cố định</span>
                            </label>
                            <div class="relative">
                                <i class="fa-solid fa-shield-halved absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="text" value="<?= strtoupper($user['role']) ?>" disabled class="w-full pl-8 pr-3 py-2.5 rounded-xl bg-slate-50 text-xs sm:text-sm font-bold text-slate-500 border border-slate-200/80 cursor-not-allowed">
                            </div>
                        </div>
                    </div>

                    <!-- Hàng 2 (Grid 2 cột): Email cố định & Họ và Tên hiển thị -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1 flex items-center justify-between">
                                <span>Địa chỉ Email</span>
                                <span class="text-[10px] text-slate-400 font-normal"><i class="fa-solid fa-lock text-[8px] mr-0.5"></i>Cố định</span>
                            </label>
                            <div class="relative">
                                <i class="fa-regular fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="email" value="<?= xss_clean($user['email']) ?>" disabled class="w-full pl-8 pr-3 py-2.5 rounded-xl bg-slate-50 text-xs sm:text-sm text-slate-500 border border-slate-200/80 cursor-not-allowed">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Họ và Tên hiển thị <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <i class="fa-regular fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="text" name="fullname" value="<?= xss_clean($user['fullname']) ?>" required placeholder="Nhập họ và tên..." class="w-full pl-8 pr-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-semibold text-slate-900 focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/10 outline-none transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- Hàng 3: Khu vực chọn ảnh đại diện thu gọn thành thanh ngang mảnh -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Ảnh đại diện tài khoản
                        </label>
                        <!-- Input file ẩn thực tế -->
                        <input type="file" id="avatarFileInput" name="avatar_file" accept=".jpg,.jpeg,.png,.webp,.gif" class="hidden" onchange="handleAvatarFileSelect(this)">
                        
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-2.5">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-7 h-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-500 text-xs shrink-0 shadow-2xs">
                                    <i class="fa-solid fa-cloud-arrow-up text-indigo-600"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800 truncate" id="avatarSelectedName">Tải ảnh mới từ máy tính</p>
                                    <p class="text-[10px] text-slate-400 truncate">JPG, PNG, WEBP — Tối đa 5MB</p>
                                </div>
                            </div>
                            <button type="button" onclick="triggerAvatarUpload()" class="shrink-0 px-3 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-indigo-500 hover:text-indigo-600 text-slate-700 font-bold text-xs transition-all shadow-2xs cursor-pointer">
                                <span>Chọn ảnh...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Đáy cột trái: Nút Lưu Thay Đổi -->
                <div class="pt-1">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs sm:text-sm shadow-sm shadow-indigo-500/20 flex items-center justify-center gap-2 cursor-pointer active:scale-[0.99] transition-all">
                        <i class="fa-solid fa-check text-xs"></i>
                        <span>Lưu Thông Tin Hồ Sơ</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- ================================================= -->
        <!-- CỘT PHẢI (col-span-6): BẢO MẬT & ĐỔI MẬT KHẨU    -->
        <!-- ================================================= -->
        <div class="lg:col-span-6 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-sm flex flex-col justify-between">
            <form method="POST" action="" id="passwordForm" class="flex flex-col justify-between h-full space-y-4">
                <input type="hidden" name="action_password" value="1">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Phần trên: Tiêu đề + 3 Phần mật khẩu tương ứng hoàn hảo -->
                <div class="space-y-3.5">
                    <div class="pb-1 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center text-xs shadow-2xs">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <span>Bảo Mật &amp; Mật Khẩu</span>
                        </h2>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0">
                            <i class="fa-solid fa-shield-halved text-[9px]"></i>
                            BCRYPT
                        </span>
                    </div>

                    <!-- Hàng 1: Mật khẩu hiện tại -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Mật khẩu hiện tại <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-key absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="password" id="inputOldPassword" name="old_password" required placeholder="Nhập mật khẩu hiện tại..." class="w-full pl-8 pr-9 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/10 outline-none transition-all">
                            <button type="button" onclick="togglePassVisibility('inputOldPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                                <i class="fa-regular fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Hàng 2 (Grid 2 cột): Mật khẩu mới & Xác nhận mật khẩu mới -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Mật khẩu mới <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <i class="fa-solid fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="password" id="inputNewPassword" name="new_password" required minlength="6" oninput="checkPassStrength(this.value)" placeholder="Tối thiểu 6 ký tự..." class="w-full pl-8 pr-9 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/10 outline-none transition-all">
                                <button type="button" onclick="togglePassVisibility('inputNewPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                                    <i class="fa-regular fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Xác nhận mật khẩu <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <i class="fa-solid fa-lock-open absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="password" id="inputConfirmPassword" name="confirm_password" required minlength="6" placeholder="Nhập lại mật khẩu..." class="w-full pl-8 pr-9 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono focus:bg-white focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/10 outline-none transition-all">
                                <button type="button" onclick="togglePassVisibility('inputConfirmPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                                    <i class="fa-regular fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Hàng 3: Thanh đo độ mạnh mật khẩu (Password Strength Bar) gọn nhẹ -->
                    <div class="space-y-1 pt-0.5">
                        <div class="flex items-center justify-between text-[11px] font-semibold text-slate-400">
                            <span>Độ mạnh mật khẩu:</span>
                            <span id="passStrengthLabel" class="font-bold text-slate-500">Chưa nhập</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden flex gap-1">
                            <div id="strBar1" class="h-full flex-1 rounded-full bg-slate-200 transition-colors"></div>
                            <div id="strBar2" class="h-full flex-1 rounded-full bg-slate-200 transition-colors"></div>
                            <div id="strBar3" class="h-full flex-1 rounded-full bg-slate-200 transition-colors"></div>
                            <div id="strBar4" class="h-full flex-1 rounded-full bg-slate-200 transition-colors"></div>
                        </div>
                    </div>
                </div>

                <!-- Đáy cột phải: Nút Cập Nhật Mật Khẩu -->
                <div class="pt-1">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-indigo-600 text-white font-bold text-xs sm:text-sm shadow-sm flex items-center justify-center gap-2 cursor-pointer active:scale-[0.99] transition-all">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                        <span>Cập Nhật Mật Khẩu</span>
                    </button>
                </div>
            </form>
        </div>

    </div>

</div>
</main>

<!-- ===================================================== -->
<!-- JAVASCRIPT: AVATAR PREVIEW & PASS VISIBILITY & STRENGTH-->
<!-- ===================================================== -->
<script>
// 1. Kích hoạt chọn file avatar
function triggerAvatarUpload() {
    const input = document.getElementById('avatarFileInput');
    if (input) input.click();
}

// 2. Instant Preview ảnh avatar ngay sau khi chọn
function handleAvatarFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Kiểm tra kích thước < 5MB
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire('Lỗi', 'Kích thước file ảnh không được vượt quá 5MB!', 'error');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('bannerAvatarImg');
            if (preview) {
                preview.src = e.target.result;
            }
            const nameEl = document.getElementById('avatarSelectedName');
            if (nameEl) {
                nameEl.innerText = file.name;
                nameEl.classList.add('text-indigo-600');
            }
        };
        reader.readAsDataURL(file);
    }
}

// 3. Ẩn / Hiện mật khẩu
function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-regular fa-eye-slash text-xs text-indigo-600';
    } else {
        input.type = 'password';
        icon.className = 'fa-regular fa-eye text-xs text-slate-400';
    }
}

// 4. Đo độ mạnh mật khẩu (Password Strength Meter)
function checkPassStrength(val) {
    const bars = [
        document.getElementById('strBar1'),
        document.getElementById('strBar2'),
        document.getElementById('strBar3'),
        document.getElementById('strBar4')
    ];
    const label = document.getElementById('passStrengthLabel');

    // Reset màu
    bars.forEach(b => {
        if (b) b.className = 'h-full flex-1 rounded-full bg-slate-200 transition-colors';
    });

    if (!val || val.length === 0) {
        if (label) {
            label.innerText = 'Chưa nhập';
            label.className = 'font-bold text-slate-500';
        }
        return;
    }

    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 9) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val) || /[^A-Za-z0-9]/.test(val)) score++;

    const colors = ['bg-rose-500', 'bg-amber-500', 'bg-blue-500', 'bg-emerald-500'];
    const labels = ['Yếu', 'Trung bình', 'Khá', 'Rất mạnh'];
    const labelClasses = ['text-rose-500 font-bold', 'text-amber-500 font-bold', 'text-blue-500 font-bold', 'text-emerald-500 font-bold'];

    for (let i = 0; i < score; i++) {
        if (bars[i]) bars[i].className = 'h-full flex-1 rounded-full transition-colors ' + colors[score - 1];
    }

    if (label) {
        label.innerText = labels[score - 1] || 'Yếu';
        label.className = labelClasses[score - 1] || 'text-rose-500 font-bold';
    }
}

// 5. Đóng Floating Toast Notification (Hiệu ứng Slide-out & Tự hủy)
function dismissToast() {
    const toast = document.getElementById('floatingToast');
    if (!toast) return;
    toast.classList.remove('toast-enter');
    toast.classList.add('toast-exit');
    setTimeout(() => {
        toast.remove();
    }, 350);
}

// Tự động đóng Toast sau 3.5 giây
document.addEventListener('DOMContentLoaded', () => {
    const toast = document.getElementById('floatingToast');
    if (toast) {
        setTimeout(() => {
            dismissToast();
        }, 3500);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
