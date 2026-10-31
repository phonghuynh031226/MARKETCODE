<?php
declare(strict_types=1);
$pageTitle = "Quên Mật Khẩu - Khôi Phục Tài Khoản";
require_once __DIR__ . '/config/config.php';

// Nếu đã đăng nhập, chuyển hướng về trang chủ
if (is_logged()) {
    header("Location: " . BASE_URL . "/");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token không hợp lệ. Vui lòng thử lại!';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Vui lòng nhập địa chỉ email hợp lệ!';
        } elseif (!security_rate_limit('reset-request-ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 900)
            || !security_rate_limit('reset-request-email:' . strtolower($email), 3, 900)) {
            $error = 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau 15 phút.';
        } elseif (strlen(security_config('RESET_TOKEN_KEY')) < 32 || !filter_var(security_config('RESET_MAIL_FROM'), FILTER_VALIDATE_EMAIL)) {
            $error = 'Khôi phục mật khẩu đang tạm khóa. Vui lòng liên hệ quản trị viên.';
        } else {
            try {
                $stmt = $db->prepare("SELECT id, username, email FROM users WHERE email = ? AND deleted_at IS NULL AND status = 'active' LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $success = 'Nếu email đã được đăng ký, hệ thống sẽ gửi mã xác thực đến hộp thư của bạn.';
                } else {
                    // Tạo mã OTP 6 số ngẫu nhiên
                    $otp = (string)random_int(100000, 999999);

                    // Xóa các mã cũ của email này nếu có
                    $delStmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                    $delStmt->execute([$email]);

                    // Lưu mã xác thực mới vào bảng password_resets
                    $insStmt = $db->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())");
                    $insStmt->execute([$email, reset_token_digest($email, $otp)]);

                    // Lưu email và OTP vào session (giúp test dễ dàng trên môi trường localhost không có SMTP)
                    $_SESSION['reset_email'] = $email;
                    unset($_SESSION['latest_otp'], $_SESSION['flash_otp_msg']);
                    $sent = mail($email, 'Ma xac thuc khoi phuc mat khau', 'Ma xac thuc cua ban: ' . $otp . '. Hieu luc 15 phut. Khong chia se ma nay.', 'From: ' . security_config('RESET_MAIL_FROM'));
                    if (!$sent) {
                        $delStmt->execute([$email]);
                        throw new RuntimeException('Password reset email delivery failed');
                    }

                    header("Location: " . BASE_URL . "/reset-password");
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Lỗi hệ thống: ' . public_error($e);
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
/* Quầng sáng Mesh Gradient chậm rãi */
@keyframes meshPulseSlow {
    0%, 100% { transform: scale(1) translate(0, 0); opacity: 0.18; }
    50% { transform: scale(1.08) translate(15px, -15px); opacity: 0.28; }
}
.mesh-pulse-1 {
    animation: meshPulseSlow 9s ease-in-out infinite;
}
.mesh-pulse-2 {
    animation: meshPulseSlow 11s ease-in-out infinite reverse;
}
</style>

<div class="flex-1 flex items-center justify-center py-10 sm:py-16 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-[#F8FAFC] min-h-[calc(100dvh-140px)]">
    <!-- 1. Quầng Sáng Mesh Gradient Nổi Bật Phía Sau -->
    <div class="absolute -top-28 -left-20 w-[520px] h-[520px] bg-gradient-to-tr from-indigo-500/25 via-indigo-300/15 to-transparent rounded-full blur-[110px] pointer-events-none mesh-pulse-1"></div>
    <div class="absolute top-1/2 -right-28 w-[480px] h-[480px] bg-gradient-to-bl from-cyan-400/25 via-sky-300/15 to-transparent rounded-full blur-[120px] pointer-events-none mesh-pulse-2"></div>
    <div class="absolute -bottom-24 left-1/3 w-[420px] h-[420px] bg-gradient-to-t from-violet-500/20 via-purple-300/10 to-transparent rounded-full blur-[100px] pointer-events-none"></div>

    <!-- Họa tiết chấm bi mờ tăng chiều sâu -->
    <div class="absolute inset-0 bg-dot-pattern opacity-40 pointer-events-none"></div>

    <!-- 2. Khung Form Chính (Floating Card & Layered Shadows chuẩn Stripe/Linear) -->
    <div class="max-w-4xl w-full grid grid-cols-1 lg:grid-cols-12 rounded-3xl bg-white/95 backdrop-blur-xl shadow-[0_25px_60px_-15px_rgba(15,23,42,0.15)] border border-slate-200/80 overflow-hidden relative z-10 transition-all duration-300">

        <!-- NỬA BÊN TRÁI: BRAND PANEL (TINH CHỈNH SANG TRỌNG) -->
        <div class="hidden lg:flex lg:col-span-5 flex-col justify-between p-8 md:p-10 bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 text-white relative overflow-hidden select-none">
            <!-- Vòng tròn ánh sáng mờ đa tầng -->
            <div class="absolute -right-10 -bottom-10 w-60 h-60 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            <div class="absolute -left-12 -top-12 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-violet-400/10 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Top Header & Cam kết thương hiệu -->
            <div class="relative z-10 space-y-6">
                <!-- Logo Brand -->
                <a href="<?= e(BASE_URL) ?>/" class="flex items-center gap-3 group">
                    <?php
                    $authLogo = get_setting('site_logo', '');
                    $authLogoUrl = '';
                    if (!empty($authLogo)) {
                        $authLogoPath = ROOT_PATH . '/' . ltrim($authLogo, '/');
                        if (file_exists($authLogoPath) || preg_match('#^https?://#i', $authLogo)) {
                            $authLogoUrl = preg_match('#^https?://#i', $authLogo) ? $authLogo : BASE_URL . '/' . ltrim($authLogo, '/');
                        }
                    }
                    ?>
                    <?php if (!empty($authLogoUrl)): ?>
                        <img src="<?= e($authLogoUrl) ?>" alt="Logo" class="w-11 h-11 rounded-2xl object-cover border border-white/20 shadow-inner group-hover:scale-105 transition-transform">
                    <?php else: ?>
                        <div class="w-11 h-11 rounded-2xl bg-white/15 backdrop-blur-md border border-white/20 flex items-center justify-center text-white shadow-inner group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-code text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="text-xl font-black tracking-tight block">MARKET<span class="text-indigo-200">CODE</span></span>
                        <span class="text-[10px] font-semibold text-indigo-200/80 uppercase tracking-widest block -mt-0.5">SaaS Code Hub</span>
                    </div>
                </a>

                <!-- Tiêu đề & Giới thiệu -->
                <div class="pt-2 space-y-2.5">
                    <h2 class="text-2xl sm:text-3xl font-black leading-tight tracking-tight">
                        Khôi Phục Mật Khẩu Nhanh Chóng
                    </h2>
                    <p class="text-xs sm:text-sm text-indigo-100/90 leading-relaxed font-normal">
                        Xác minh tài khoản qua mã OTP 6 số bảo mật để thiết lập mật khẩu mới trong 15 phút.
                    </p>
                </div>

                <!-- 3 Bước khôi phục trực quan -->
                <div class="space-y-3 pt-3">
                    <div class="flex items-start gap-3 text-xs text-indigo-50">
                        <div class="w-7 h-7 rounded-xl bg-white/20 border border-white/30 text-white font-bold flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-xs">
                            1
                        </div>
                        <div>
                            <strong class="font-bold block text-white text-xs">Nhập Email Tài Khoản</strong>
                            <span class="text-indigo-200 text-[11px] block mt-0.5">Hệ thống đối chiếu và gửi mã OTP bảo mật</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 text-xs text-indigo-50">
                        <div class="w-7 h-7 rounded-xl bg-white/20 border border-white/30 text-white font-bold flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-xs">
                            2
                        </div>
                        <div>
                            <strong class="font-bold block text-white text-xs">Xác Thực Mã OTP 6 Số</strong>
                            <span class="text-indigo-200 text-[11px] block mt-0.5">Nhận mã và xác thực trong vòng 15 phút</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 text-xs text-indigo-50">
                        <div class="w-7 h-7 rounded-xl bg-white/20 border border-white/30 text-white font-bold flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-xs">
                            3
                        </div>
                        <div>
                            <strong class="font-bold block text-white text-xs">Tạo Mật Khẩu Mới</strong>
                            <span class="text-indigo-200 text-[11px] block mt-0.5">Mật khẩu được mã hóa an toàn bằng BCRYPT</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer: Security Standard -->
            <div class="relative z-10 pt-6 border-t border-white/15 text-[11px] text-indigo-200 flex items-center justify-between font-medium">
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-shield-halved text-emerald-300"></i> BCRYPT & CSRF Protected</span>
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-lock text-indigo-200"></i> SSL 256-bit</span>
            </div>
        </div>

        <!-- NỬA BÊN PHẢI: INTERACTIVE FORM PANEL -->
        <div class="lg:col-span-7 p-6 sm:p-10 flex flex-col justify-center bg-white/95">
            <!-- Nút Quay lại Đăng Nhập -->
            <div class="mb-5">
                <a href="<?= e(BASE_URL) ?>/login" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-indigo-600 transition-colors">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                    <span>Quay lại trang Đăng Nhập</span>
                </a>
            </div>

            <!-- Tiêu đề Form -->
            <div class="mb-6">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center text-xl shadow-xs mb-3">
                    <i class="fa-solid fa-key"></i>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                    Quên Mật Khẩu?
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1 leading-relaxed">
                    Đừng lo lắng! Hãy nhập địa chỉ email bạn đã đăng ký tài khoản. Chúng tôi sẽ gửi mã OTP 6 số để bạn đặt lại mật khẩu.
                </p>
            </div>

            <!-- Thông báo Lỗi nếu có -->
            <?php if (!empty($error)): ?>
                <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium flex items-center gap-2.5 mb-5 shadow-xs">
                    <i class="fa-solid fa-triangle-exclamation text-base shrink-0 text-rose-500"></i>
                    <span><?= xss_clean($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Nhập Email -->
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Địa chỉ Email tài khoản *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-envelope text-sm"></i>
                        </div>
                        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus placeholder="name@example.com" class="w-full pl-11 pr-4 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200 font-medium">
                    </div>
                    <span class="text-[11px] text-slate-400 mt-1 block">Email đã sử dụng khi đăng ký tài khoản trên MarketCode.</span>
                </div>

                <!-- Nút Gửi Mã Xác Thực OTP (Gradient tím đậm sang trọng) -->
                <button type="submit" class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold text-sm shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/35 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer mt-2">
                    <i class="fa-regular fa-paper-plane text-xs"></i>
                    <span>Gửi Mã Xác Thực OTP</span>
                </button>
            </form>

            <!-- Hỗ trợ thêm -->
            <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Bạn nhớ lại mật khẩu?</span>
                <a href="<?= e(BASE_URL) ?>/login" class="font-bold text-indigo-600 hover:underline">
                    Đăng nhập ngay
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
