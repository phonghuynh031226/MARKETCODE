<?php
$pageTitle = "Đặt Lại Mật Khẩu Mới";
require_once __DIR__ . '/config/config.php';

// Nếu đã đăng nhập, chuyển hướng về trang chủ
if (is_logged()) {
    header("Location: " . BASE_URL . "/");
    exit;
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'] ?? trim($_GET['email'] ?? '');
$flashOtp = $_SESSION['flash_otp_msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token không hợp lệ. Vui lòng thử lại!';
    } else {
        $inputEmail       = trim(filter_var($_POST['email'] ?? $email, FILTER_SANITIZE_EMAIL));
        $otp              = trim($_POST['otp'] ?? '');
        $newPassword      = $_POST['new_password'] ?? '';
        $confirmPassword  = $_POST['confirm_password'] ?? '';

        if (empty($inputEmail) || !filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Không xác định được địa chỉ email. Vui lòng thực hiện lại từ bước quên mật khẩu!';
        } elseif (empty($otp) || strlen($otp) !== 6 || !ctype_digit($otp)) {
            $error = 'Mã xác thực OTP phải gồm đúng 6 chữ số!';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Mật khẩu mới phải có tối thiểu 6 ký tự!';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Mật khẩu nhập lại không khớp với mật khẩu mới!';
        } else {
            try {
                // Kiểm tra mã OTP trong bảng password_resets (hiệu lực trong 15 phút)
                $stmt = $db->prepare("SELECT * FROM password_resets 
                                      WHERE email = ? AND token = ? 
                                      AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) <= 15 
                                      ORDER BY id DESC LIMIT 1");
                $stmt->execute([$inputEmail, $otp]);
                $resetRecord = $stmt->fetch();

                if (!$resetRecord) {
                    $error = 'Mã xác thực OTP không chính xác hoặc đã hết hạn (quá 15 phút). Vui lòng yêu cầu mã mới!';
                } else {
                    // Mã hóa mật khẩu an toàn chuẩn BCRYPT
                    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

                    // Cập nhật mật khẩu mới vào bảng users
                    $updateStmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
                    $updateStmt->execute([$hashedPassword, $inputEmail]);

                    // Xóa token vừa sử dụng để tránh dùng lại
                    $delStmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                    $delStmt->execute([$inputEmail]);

                    // Dọn dẹp session
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['latest_otp']);
                    unset($_SESSION['flash_otp_msg']);

                    $success = 'Đặt lại mật khẩu thành công! Bạn có thể sử dụng mật khẩu mới để đăng nhập.';
                }
            } catch (Exception $e) {
                $error = 'Lỗi hệ thống khi cập nhật mật khẩu: ' . $e->getMessage();
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

<div class="flex-1 flex items-center justify-center py-10 sm:py-16 px-4 sm:px-6 lg:px-8 relative overflow-hidden bg-[#F8FAFC] min-h-[calc(100vh-140px)]">
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

            <!-- Top Header & Hướng dẫn bảo mật -->
            <div class="relative z-10 space-y-6">
                <!-- Logo Brand -->
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-white/15 backdrop-blur-md border border-white/20 flex items-center justify-center text-white shadow-inner">
                        <i class="fa-solid fa-code text-lg"></i>
                    </div>
                    <div>
                        <span class="text-xl font-black tracking-tight block">MARKET<span class="text-indigo-200">CODE</span></span>
                        <span class="text-[10px] font-semibold text-indigo-200/80 uppercase tracking-widest block -mt-0.5">SaaS Code Hub</span>
                    </div>
                </div>

                <!-- Tiêu đề & Giới thiệu -->
                <div class="pt-2 space-y-2.5">
                    <h2 class="text-2xl sm:text-3xl font-black leading-tight tracking-tight">
                        Đặt Lại Mật Khẩu An Toàn
                    </h2>
                    <p class="text-xs sm:text-sm text-indigo-100/90 leading-relaxed font-normal">
                        Vui lòng nhập mã OTP 6 số và thiết lập mật khẩu mạnh để bảo vệ tài khoản cũng như số dư của bạn.
                    </p>
                </div>

                <!-- Lời khuyên bảo mật mật khẩu -->
                <div class="space-y-3 pt-3">
                    <div class="flex items-start gap-3 text-xs text-indigo-50">
                        <div class="w-7 h-7 rounded-xl bg-emerald-400/20 border border-emerald-400/30 text-emerald-300 flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-xs">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <strong class="font-bold block text-white text-xs">Độ dài tối thiểu 6 ký tự</strong>
                            <span class="text-indigo-200 text-[11px] block mt-0.5">Nên kết hợp chữ cái và số để tăng bảo mật</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 text-xs text-indigo-50">
                        <div class="w-7 h-7 rounded-xl bg-emerald-400/20 border border-emerald-400/30 text-emerald-300 flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-xs">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div>
                            <strong class="font-bold block text-white text-xs">Mã OTP có hạn 15 phút</strong>
                            <span class="text-indigo-200 text-[11px] block mt-0.5">Sau 15 phút mã OTP sẽ tự động vô hiệu hóa</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 text-xs text-indigo-50">
                        <div class="w-7 h-7 rounded-xl bg-emerald-400/20 border border-emerald-400/30 text-emerald-300 flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-xs">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <div>
                            <strong class="font-bold block text-white text-xs">Mã hóa chuẩn BCRYPT</strong>
                            <span class="text-indigo-200 text-[11px] block mt-0.5">Mật khẩu được lưu trữ dưới dạng băm 1 chiều</span>
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
            <!-- Nút Quay lại Đổi Email -->
            <div class="mb-5">
                <a href="<?= BASE_URL ?>/forgot-password" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-indigo-600 transition-colors">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                    <span>Thay đổi Email nhận mã</span>
                </a>
            </div>

            <!-- Tiêu đề Form -->
            <div class="mb-5">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center text-xl shadow-xs mb-3">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                    Đặt Mật Khẩu Mới
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1 leading-relaxed">
                    Xác minh mã OTP và tạo mật khẩu mới cho tài khoản: 
                    <strong class="text-indigo-600 font-semibold"><?= !empty($email) ? xss_clean($email) : 'được chỉ định' ?></strong>
                </p>
            </div>

            <!-- Hộp Thoại Hiển Thị Mã OTP Demo Trên Localhost (Nếu có) -->
            <?php if (!empty($flashOtp)): ?>
                <div class="p-3.5 rounded-2xl bg-indigo-50/90 border border-indigo-200 text-indigo-900 text-xs font-medium flex items-center gap-3 mb-5 shadow-xs">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 text-sm">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
                    <div>
                        <span class="block text-[11px] font-bold text-indigo-700 uppercase tracking-wider mb-0.5">Thông báo thử nghiệm Localhost:</span>
                        <span><?= $flashOtp ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Thông báo Thành Công -->
            <?php if (!empty($success)): ?>
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium space-y-3 mb-5 shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-check text-base text-emerald-600 shrink-0"></i>
                        <span class="font-bold text-sm text-emerald-900"><?= xss_clean($success) ?></span>
                    </div>
                    <div class="pt-1">
                        <a href="<?= BASE_URL ?>/login" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all">
                            <span>Đăng nhập ngay bây giờ</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Thông báo Lỗi nếu có -->
            <?php if (!empty($error)): ?>
                <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium flex items-center gap-2.5 mb-5 shadow-xs">
                    <i class="fa-solid fa-triangle-exclamation text-base shrink-0 text-rose-500"></i>
                    <span><?= xss_clean($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($success)): ?>
                <!-- Form Đặt Lại Mật Khẩu -->
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <?php if (!empty($email)): ?>
                        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    <?php else: ?>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Email tài khoản *</label>
                            <input type="email" name="email" required placeholder="name@example.com" class="w-full px-4 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 transition-all duration-200 font-medium">
                        </div>
                    <?php endif; ?>

                    <!-- Mã OTP 6 Số -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Mã xác thực OTP (6 chữ số) *</label>
                            <a href="<?= BASE_URL ?>/forgot-password" class="text-[11px] font-semibold text-indigo-600 hover:underline">Gửi lại mã?</a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-hashtag text-sm"></i>
                            </div>
                            <input type="text" name="otp" required maxlength="6" autofocus placeholder="123456" class="w-full pl-11 pr-4 py-3 rounded-2xl bg-slate-50/80 text-base text-slate-900 font-mono font-bold tracking-widest border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-300 transition-all duration-200">
                        </div>
                        <span class="text-[11px] text-slate-400 mt-1 block">Nhập mã 6 số được gửi về email của bạn.</span>
                    </div>

                    <!-- Mật Khẩu Mới -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Mật khẩu mới *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-lock text-sm"></i>
                                </div>
                                <input type="password" id="newPassword" name="new_password" required placeholder="Tối thiểu 6 ký tự" class="w-full pl-11 pr-11 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200">
                                <button type="button" onclick="togglePasswordVisibility('newPassword', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors cursor-pointer" title="Ẩn/Hiện mật khẩu">
                                    <i class="fa-regular fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Nhập lại mật khẩu *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-lock text-sm"></i>
                                </div>
                                <input type="password" id="confirmPassword" name="confirm_password" required placeholder="••••••••" class="w-full pl-11 pr-11 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200">
                                <button type="button" onclick="togglePasswordVisibility('confirmPassword', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors cursor-pointer" title="Ẩn/Hiện mật khẩu">
                                    <i class="fa-regular fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Nút Xác Nhận (Gradient tím đậm sang trọng) -->
                    <button type="submit" class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold text-sm shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/35 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer mt-2">
                        <i class="fa-solid fa-check text-xs"></i>
                        <span>Xác Nhận & Đổi Mật Khẩu</span>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Hỗ trợ thêm -->
            <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Quay lại đăng nhập?</span>
                <a href="<?= BASE_URL ?>/login" class="font-bold text-indigo-600 hover:underline">
                    Đăng nhập tài khoản
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
