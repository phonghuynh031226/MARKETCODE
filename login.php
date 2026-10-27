<?php
$pageTitle = $pageTitle ?? "Đăng Nhập / Đăng Ký Tài Khoản";
require_once __DIR__ . '/config/config.php';

// Nếu đã đăng nhập, chuyển hướng về trang chủ
if (is_logged()) {
    header("Location: " . BASE_URL . "/");
    exit;
}

$activeTab = ($_GET['tab'] ?? 'login') === 'register' ? 'register' : 'login';
$redirectUrl = $_GET['redirect'] ?? (BASE_URL . '/');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
/* Keyframes hiệu ứng fade-in slide-up mượt mà khi chuyển Tab */
@keyframes formFadeSlideUp {
    0% {
        opacity: 0;
        transform: translateY(10px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}
.tab-content-animate {
    animation: formFadeSlideUp 250ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

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

            <!-- Top Header & Cam kết thương hiệu -->
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
                        Cộng Đồng Lập Trình Viên Hàng Đầu
                    </h2>
                    <p class="text-xs sm:text-sm text-indigo-100/90 leading-relaxed font-normal">
                        Nền tảng mua sắm mã nguồn chất lượng cao, nạp tiền tự động và sở hữu file lập tức qua Token bảo mật.
                    </p>
                </div>

                <!-- 3 Dòng Cam Kết Bảo Mật Sắc Nét -->
                <div class="space-y-3 pt-3">
                    <!-- Cam kết 1 -->
                    <div class="flex items-start gap-3 text-xs text-indigo-50">
                        <div class="w-7 h-7 rounded-xl bg-emerald-400/20 border border-emerald-400/30 text-emerald-300 flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-xs">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <strong class="font-bold block text-white text-xs">Nạp tiền tự động 24/7</strong>
                            <span class="text-indigo-200 text-[11px] block mt-0.5">Hỗ trợ VietQR ngân hàng & thẻ cào trong 30 giây</span>
                        </div>
                    </div>

                    <!-- Cam kết 2 -->
                    <div class="flex items-start gap-3 text-xs text-indigo-50">
                        <div class="w-7 h-7 rounded-xl bg-emerald-400/20 border border-emerald-400/30 text-emerald-300 flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-xs">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <strong class="font-bold block text-white text-xs">Tải mã nguồn qua Token</strong>
                            <span class="text-indigo-200 text-[11px] block mt-0.5">Link Google Drive / Mega mã hóa chống leak</span>
                        </div>
                    </div>

                    <!-- Cam kết 3 -->
                    <div class="flex items-start gap-3 text-xs text-indigo-50">
                        <div class="w-7 h-7 rounded-xl bg-emerald-400/20 border border-emerald-400/30 text-emerald-300 flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-xs">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <strong class="font-bold block text-white text-xs">Cam kết hoàn tiền 100%</strong>
                            <span class="text-indigo-200 text-[11px] block mt-0.5">Hỗ trợ setup trọn gói & hoàn tiền nếu code lỗi</span>
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
            <!-- 3. Thanh Chuyển Đổi Tab Dạng Segmented Control (Pill Button) -->
            <div class="flex rounded-2xl bg-slate-100 p-1.5 mb-6 border border-slate-200/60 shadow-inner">
                <button type="button" id="tabBtnLogin" onclick="switchTab('login')" class="flex-1 py-2.5 text-center text-xs sm:text-sm font-extrabold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer <?= $activeTab === 'login' ? 'bg-white text-indigo-600 shadow-sm border border-slate-200/50' : 'text-slate-500 hover:text-slate-900 border border-transparent' ?>">
                    <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                    <span>Đăng Nhập</span>
                </button>
                <button type="button" id="tabBtnRegister" onclick="switchTab('register')" class="flex-1 py-2.5 text-center text-xs sm:text-sm font-extrabold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer <?= $activeTab === 'register' ? 'bg-white text-indigo-600 shadow-sm border border-slate-200/50' : 'text-slate-500 hover:text-slate-900 border border-transparent' ?>">
                    <i class="fa-solid fa-user-plus text-xs"></i>
                    <span>Tạo Tài Khoản</span>
                </button>
            </div>

            <!-- Nút Đăng Nhập Nhanh Mạng Xã Hội (Social OAuth Buttons) -->
            <div class="space-y-3 mb-6">
                <div class="grid grid-cols-3 gap-2.5 sm:gap-3">
                    <!-- Google -->
                    <a href="<?= BASE_URL ?>/auth/google.php" class="flex items-center justify-center gap-2 py-2.5 px-2 sm:px-3 rounded-2xl border border-slate-200/80 bg-white hover:bg-slate-50/80 hover:border-indigo-300 transition-all shadow-xs group">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span class="text-xs font-bold text-slate-700">Google</span>
                    </a>
                    <!-- Facebook -->
                    <a href="<?= BASE_URL ?>/auth/facebook.php" class="flex items-center justify-center gap-2 py-2.5 px-2 sm:px-3 rounded-2xl border border-slate-200/80 bg-white hover:bg-slate-50/80 hover:border-indigo-300 transition-all shadow-xs group">
                        <i class="fa-brands fa-facebook text-blue-600 text-base shrink-0"></i>
                        <span class="text-xs font-bold text-slate-700">Facebook</span>
                    </a>
                    <!-- Zalo -->
                    <a href="<?= BASE_URL ?>/auth/zalo.php" class="flex items-center justify-center gap-2 py-2.5 px-2 sm:px-3 rounded-2xl border border-slate-200/80 bg-white hover:bg-slate-50/80 hover:border-indigo-300 transition-all shadow-xs group">
                        <span class="w-4 h-4 rounded-full bg-blue-500 text-white font-extrabold text-[9px] flex items-center justify-center shrink-0">Z</span>
                        <span class="text-xs font-bold text-slate-700">Zalo</span>
                    </a>
                </div>

                <div class="relative flex items-center justify-center my-4">
                    <div class="border-t border-slate-200/80 w-full"></div>
                    <span class="bg-white px-3 text-[11px] uppercase tracking-wider text-slate-400 font-bold absolute">hoặc với tài khoản</span>
                </div>
            </div>

            <!-- FORM 1: ĐĂNG NHẬP (TAB-CONTENT-ANIMATE) -->
            <form id="formLogin" onsubmit="handleAuthSubmit(event, 'login')" class="space-y-4 tab-content-animate <?= $activeTab === 'login' ? 'block' : 'hidden' ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="redirect" value="<?= xss_clean($redirectUrl) ?>">

                <!-- Tên đăng nhập / Email -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Tên đăng nhập hoặc Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-user text-sm"></i>
                        </div>
                        <input type="text" name="account" required placeholder="Nhập username hoặc email..." class="w-full pl-11 pr-4 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200">
                    </div>
                </div>

                <!-- Mật khẩu -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Mật khẩu</label>
                        <a href="<?= BASE_URL ?>/forgot-password" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 hover:underline">Quên mật khẩu?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </div>
                        <input type="password" id="loginPassword" name="password" required placeholder="••••••••" class="w-full pl-11 pr-11 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200">
                        <button type="button" onclick="togglePasswordVisibility('loginPassword', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors cursor-pointer" title="Ẩn/Hiện mật khẩu">
                            <i class="fa-regular fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- Ghi nhớ đăng nhập -->
                <div class="flex items-center justify-between text-xs pt-0.5">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-600 hover:text-slate-900 select-none">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded-md text-indigo-600 focus:ring-indigo-500 border-slate-300">
                        <span>Ghi nhớ phiên đăng nhập</span>
                    </label>
                </div>

                <!-- Nút Đăng Nhập Ngay (Gradient tím đậm sang trọng) -->
                <button type="submit" id="btnLoginSubmit" class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold text-sm shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/35 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer">
                    <span>Đăng Nhập Ngay</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </form>

            <!-- FORM 2: ĐĂNG KÝ (TAB-CONTENT-ANIMATE) -->
            <form id="formRegister" onsubmit="handleAuthSubmit(event, 'register')" class="space-y-4 tab-content-animate <?= $activeTab === 'register' ? 'block' : 'hidden' ?>">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="redirect" value="<?= xss_clean($redirectUrl) ?>">

                <!-- Họ và tên -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Họ và tên</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-id-card text-sm"></i>
                        </div>
                        <input type="text" name="fullname" required placeholder="Nguyễn Văn A" class="w-full pl-11 pr-4 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200">
                    </div>
                </div>

                <!-- Username & Email -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Tên đăng nhập</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-user text-sm"></i>
                            </div>
                            <input type="text" name="username" required placeholder="nguyenvana" class="w-full pl-11 pr-4 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-envelope text-sm"></i>
                            </div>
                            <input type="email" name="email" required placeholder="vana@gmail.com" class="w-full pl-11 pr-4 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200">
                        </div>
                    </div>
                </div>

                <!-- Mật khẩu & Xác nhận mật khẩu -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Mật khẩu</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-lock text-sm"></i>
                            </div>
                            <input type="password" id="regPassword" name="password" required placeholder="Tối thiểu 6 ký tự" class="w-full pl-11 pr-11 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200">
                            <button type="button" onclick="togglePasswordVisibility('regPassword', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors cursor-pointer" title="Ẩn/Hiện mật khẩu">
                                <i class="fa-regular fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Xác nhận mật khẩu</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-shield-halved text-sm"></i>
                            </div>
                            <input type="password" id="regConfirmPassword" name="password_confirm" required placeholder="••••••••" class="w-full pl-11 pr-11 py-3 rounded-2xl bg-slate-50/80 text-sm text-slate-900 border border-slate-200/80 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 placeholder:text-slate-400 transition-all duration-200">
                            <button type="button" onclick="togglePasswordVisibility('regConfirmPassword', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors cursor-pointer" title="Ẩn/Hiện mật khẩu">
                                <i class="fa-regular fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Điều khoản dịch vụ -->
                <div class="flex items-start gap-2 text-xs text-slate-500 pt-1">
                    <input type="checkbox" required id="terms" class="mt-0.5 rounded-md text-indigo-600 focus:ring-indigo-500 border-slate-300">
                    <label for="terms" class="leading-relaxed select-none">Tôi đồng ý với <a href="#" class="text-indigo-600 font-semibold hover:underline">Điều khoản dịch vụ</a> và <a href="#" class="text-indigo-600 font-semibold hover:underline">Chính sách bảo mật</a> của MarketCode.</label>
                </div>

                <!-- Nút Đăng Ký Tài Khoản (Gradient tím đậm sang trọng) -->
                <button type="submit" id="btnRegSubmit" class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold text-sm shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/35 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer">
                    <span>Đăng Ký Tài Khoản</span>
                    <i class="fa-solid fa-user-check text-xs"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Chuyển Tab Đăng nhập / Đăng ký mượt mà (Fade-in Slide-up trong 250ms)
    function switchTab(tab) {
        const formLogin = document.getElementById('formLogin');
        const formReg = document.getElementById('formRegister');
        const btnLogin = document.getElementById('tabBtnLogin');
        const btnReg = document.getElementById('tabBtnRegister');

        if (tab === 'login') {
            formReg.classList.add('hidden');
            formReg.classList.remove('tab-content-animate');
            
            formLogin.classList.remove('hidden');
            // Kích hoạt lại animation mượt mà
            void formLogin.offsetWidth;
            formLogin.classList.add('tab-content-animate');

            btnLogin.className = "flex-1 py-2.5 text-center text-xs sm:text-sm font-extrabold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer bg-white text-indigo-600 shadow-sm border border-slate-200/50";
            btnReg.className = "flex-1 py-2.5 text-center text-xs sm:text-sm font-extrabold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer text-slate-500 hover:text-slate-900 border border-transparent";
            window.history.replaceState({}, document.title, '<?= BASE_URL ?>/login');
        } else {
            formLogin.classList.add('hidden');
            formLogin.classList.remove('tab-content-animate');

            formReg.classList.remove('hidden');
            // Kích hoạt lại animation mượt mà
            void formReg.offsetWidth;
            formReg.classList.add('tab-content-animate');

            btnReg.className = "flex-1 py-2.5 text-center text-xs sm:text-sm font-extrabold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer bg-white text-indigo-600 shadow-sm border border-slate-200/50";
            btnLogin.className = "flex-1 py-2.5 text-center text-xs sm:text-sm font-extrabold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer text-slate-500 hover:text-slate-900 border border-transparent";
            window.history.replaceState({}, document.title, '<?= BASE_URL ?>/register');
        }
    }

    // Toggle Ẩn / Hiện Mật khẩu
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

    // Gửi Form Đăng nhập / Đăng ký qua AJAX
    async function handleAuthSubmit(event, type) {
        event.preventDefault();
        const form = event.target;
        const submitBtn = type === 'login' ? document.getElementById('btnLoginSubmit') : document.getElementById('btnRegSubmit');
        const originalText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Đang xử lý...';

        try {
            const formData = new FormData(form);
            const response = await fetch('<?= BASE_URL ?>/ajax/auth.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: type === 'login' ? 'Đăng Nhập Thành Công!' : 'Đăng Ký Thành Công!',
                    text: result.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = result.redirect || '<?= BASE_URL ?>/';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Thất Bại',
                    text: result.message || 'Có lỗi xảy ra, vui lòng thử lại!'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi Kết Nối',
                text: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại đường truyền!'
            });
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
