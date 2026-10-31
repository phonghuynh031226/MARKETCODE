<?php
declare(strict_types=1);
$pageTitle = "Chính Sách Bảo Hành & Cam Kết 100%";
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
    <!-- Hero Header -->
    <div class="text-center max-w-3xl mx-auto space-y-4">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-50 text-xs font-bold text-emerald-700 border border-emerald-200 shadow-sm">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Bảo Vệ Người Mua Tuyệt Đối</span>
        </div>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-slate-900 leading-tight">
            Chính Sách <span class="bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">Bảo Hành 100%</span> & Hỗ Trợ Kỹ Thuật
        </h1>
        <p class="text-base text-slate-600 leading-relaxed">
            Chúng tôi cam kết mang lại sự an tâm tuyệt đối khi bạn mua sắm mã nguồn trên MarketCode. Tất cả sản phẩm đều được kiểm định chất lượng và bảo hành toàn diện.
        </p>
    </div>

    <!-- 4 Khối Cam Kết Vàng -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- 1 -->
        <div class="rounded-3xl bg-white p-8 border border-slate-200/80 shadow-sm space-y-4 text-center hover:-translate-y-1 transition-all">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-2xl mx-auto shadow-sm">
                <i class="fa-solid fa-rotate-left"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Hoàn Tiền 100%</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                Hoàn tiền ngay vào số dư hoặc tài khoản ngân hàng nếu source code không đúng với mô tả hoặc bị lỗi không thể vận hành.
            </p>
        </div>

        <!-- 2 -->
        <div class="rounded-3xl bg-white p-8 border border-slate-200/80 shadow-sm space-y-4 text-center hover:-translate-y-1 transition-all">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-2xl mx-auto shadow-sm">
                <i class="fa-solid fa-desktop"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Hỗ Trợ Cài Đặt Từ Xa</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                Đội ngũ kỹ thuật sẵn sàng hỗ trợ bạn cấu hình CSDL, cài đặt chạy thử qua UltraViewer / AnyDesk hoàn toàn miễn phí.
            </p>
        </div>

        <!-- 3 -->
        <div class="rounded-3xl bg-white p-8 border border-slate-200/80 shadow-sm space-y-4 text-center hover:-translate-y-1 transition-all">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center text-2xl mx-auto shadow-sm">
                <i class="fa-solid fa-shield-virus"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Mã Nguồn Sạch 100%</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                Mọi source code trước khi duyệt bán đều được quét virus, mã độc, shell ẩn và kiểm tra bảo mật nghiêm ngặt.
            </p>
        </div>

        <!-- 4 -->
        <div class="rounded-3xl bg-white p-8 border border-slate-200/80 shadow-sm space-y-4 text-center hover:-translate-y-1 transition-all">
            <div class="w-14 h-14 rounded-2xl bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center text-2xl mx-auto shadow-sm">
                <i class="fa-solid fa-infinity"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Cập Nhật Trọn Đời</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                Khi tác giả cập nhật phiên bản mới hoặc vá lỗi, bạn luôn có thể tải lại bản cập nhật mà không phải trả thêm bất kỳ chi phí nào.
            </p>
        </div>
    </div>

    <!-- Quy Trình Tiếp Nhận & Hỗ Trợ -->
    <div class="rounded-3xl bg-white p-8 sm:p-12 border border-slate-200/80 shadow-sm space-y-8">
        <h2 class="text-2xl font-extrabold text-slate-900 text-center">
            Quy Trình Xử Lý Yêu Cầu Hỗ Trợ & Hoàn Tiền
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-black text-sm flex items-center justify-center shadow-sm">1</div>
                <h4 class="font-bold text-base text-slate-900">Gửi Yêu Cầu Hỗ Trợ</h4>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Liên hệ qua Hotline, Telegram hoặc Email kèm theo Mã Đơn Hàng (#ORD-...) và mô tả vấn đề bạn gặp phải khi cài đặt.
                </p>
            </div>

            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-black text-sm flex items-center justify-center shadow-sm">2</div>
                <h4 class="font-bold text-base text-slate-900">Kiểm Tra Kỹ Thuật (Trong 15-30 phút)</h4>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Kỹ thuật viên sẽ kiểm tra môi trường chạy (PHP version, cấu hình Apache/Nginx, CSDL) và trực tiếp hỗ trợ bạn setup.
                </p>
            </div>

            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                <div class="w-8 h-8 rounded-full bg-emerald-600 text-white font-black text-sm flex items-center justify-center shadow-sm">3</div>
                <h4 class="font-bold text-base text-slate-900">Xử Lý Lỗi Hoặc Hoàn Tiền</h4>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Nếu mã nguồn có lỗi từ phía tác giả và không thể sửa chữa, quản trị viên sẽ hoàn tiền 100% vào tài khoản của bạn ngay lập tức.
                </p>
            </div>
        </div>

        <!-- Contact Banner -->
        <div class="p-6 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white flex flex-col sm:flex-row items-center justify-between gap-4 shadow-md shadow-indigo-500/20">
            <div>
                <strong class="text-lg block">Cần hỗ trợ gấp?</strong>
                <span class="text-xs text-indigo-100">Đội ngũ kỹ thuật trực tuyến 24/7 từ 8h00 - 23h00 hàng ngày.</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="https://t.me/admin_marketcode" target="_blank" class="px-5 py-2.5 rounded-xl bg-white text-indigo-700 font-bold text-xs shadow-sm hover:bg-slate-50 transition-colors flex items-center gap-2">
                    <i class="fa-brands fa-telegram text-base"></i>Telegram Kỹ Thuật
                </a>
                <a href="tel:<?= e(get_setting('contact_hotline', '0988.888.888')) ?>" class="px-5 py-2.5 rounded-xl bg-white/20 border border-white/30 text-white font-bold text-xs hover:bg-white/30 transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-phone"></i>Hotline
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
