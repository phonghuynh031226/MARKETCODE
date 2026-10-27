<?php
$pageTitle = "Cài Đặt Hệ Thống & Cấu Hình API";
require_once __DIR__ . '/includes/admin_header.php';

// Xác định chính xác domain hiện tại của hệ thống
$current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');

$message = '';
$error = '';

// Xử lý lưu cấu hình
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_settings'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token không hợp lệ!';
    } else {
        $settingsToSave = [
            // Cài đặt chung
            'site_name'                  => trim($_POST['site_name'] ?? ''),
            'site_logo'                  => trim($_POST['site_logo'] ?? ''),
            'site_favicon'               => trim($_POST['site_favicon'] ?? ''),
            'site_description'           => trim($_POST['site_description'] ?? ''),
            'site_keywords'              => trim($_POST['site_keywords'] ?? ''),
            'contact_hotline'            => trim($_POST['contact_hotline'] ?? ''),
            'contact_email'              => trim($_POST['contact_email'] ?? ''),
            'contact_telegram'           => trim($_POST['contact_telegram'] ?? ''),
            'popup_enabled'              => isset($_POST['popup_enabled']) ? '1' : '0',
            'popup_title'                => trim($_POST['popup_title'] ?? ''),
            'popup_content'              => trim($_POST['popup_content'] ?? ''),

            // MoMo Gateway credentials: blank fields preserve the saved value.
            'momo_partner_code'          => trim($_POST['momo_partner_code'] ?? '') !== '' ? trim($_POST['momo_partner_code']) : get_setting('momo_partner_code', ''),
            'momo_access_key'            => trim($_POST['momo_access_key'] ?? '') !== '' ? trim($_POST['momo_access_key']) : get_setting('momo_access_key', ''),
            'momo_secret_key'            => trim($_POST['momo_secret_key'] ?? '') !== '' ? trim($_POST['momo_secret_key']) : get_setting('momo_secret_key', ''),
            'momo_api_endpoint'           => trim($_POST['momo_api_endpoint'] ?? get_setting('momo_api_endpoint', 'https://payment.momo.vn/v2/gateway/api/create')),
            // Cổng gạch thẻ
            'card_api_url'               => trim($_POST['card_api_url'] ?? ''),
            'card_partner_id'            => trim($_POST['card_partner_id'] ?? ''),
            'card_partner_key'           => trim($_POST['card_partner_key'] ?? ''),

            // OAuth Google
            'oauth_google_client_id'      => trim($_POST['oauth_google_client_id'] ?? ''),
            'oauth_google_client_secret'  => trim($_POST['oauth_google_client_secret'] ?? ''),
            'oauth_google_redirect_uri'   => trim($_POST['oauth_google_redirect_uri'] ?? ''),

            // OAuth Facebook
            'oauth_facebook_app_id'       => trim($_POST['oauth_facebook_app_id'] ?? ''),
            'oauth_facebook_app_secret'   => trim($_POST['oauth_facebook_app_secret'] ?? ''),
            'oauth_facebook_redirect_uri' => trim($_POST['oauth_facebook_redirect_uri'] ?? ''),

            // OAuth Zalo
            'oauth_zalo_app_id'           => trim($_POST['oauth_zalo_app_id'] ?? ''),
            'oauth_zalo_secret_key'       => trim($_POST['oauth_zalo_secret_key'] ?? ''),
            'oauth_zalo_redirect_uri'     => trim($_POST['oauth_zalo_redirect_uri'] ?? ''),

            // reCAPTCHA & Cron
            'recaptcha_site_key'          => trim($_POST['recaptcha_site_key'] ?? ''),
            'recaptcha_secret_key'        => trim($_POST['recaptcha_secret_key'] ?? ''),
            'cron_security_key'           => trim($_POST['cron_security_key'] ?? '')
        ];

        try {
            $db->beginTransaction();
            foreach ($settingsToSave as $k => $v) {
                // Loại bỏ localhost:8000 nếu lỡ nhập
                if (str_contains($v, 'localhost:8000')) {
                    $v = str_replace('http://localhost:8000', $current_domain, $v);
                }
                update_setting($k, $v);
            }
            $db->commit();
            $message = 'Cập nhật toàn bộ cài đặt hệ thống thành công!';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Lỗi lưu cài đặt: ' . $e->getMessage();
        }
    }
}

// Giá trị mặc định chuẩn xác theo domain thực tế
$googleRedirect = get_setting('oauth_google_redirect_uri');
if (empty($googleRedirect) || str_contains($googleRedirect, 'localhost')) {
    $googleRedirect = $current_domain . '/oauth_google.php';
}

$facebookRedirect = get_setting('oauth_facebook_redirect_uri');
if (empty($facebookRedirect) || str_contains($facebookRedirect, 'localhost')) {
    $facebookRedirect = $current_domain . '/oauth_facebook.php';
}

$zaloRedirect = get_setting('oauth_zalo_redirect_uri');
if (empty($zaloRedirect) || str_contains($zaloRedirect, 'localhost')) {
    $zaloRedirect = $current_domain . '/oauth_zalo.php';
}

$cronKey = get_setting('cron_security_key', 'marketcode_cron_secret');
$cronUrl = $current_domain . '/api/cron_bank.php?cron_key=' . urlencode($cronKey);
$cardCallbackUrl = $current_domain . '/api/callback_card.php';
?>

<div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2.5">
                <i class="fa-solid fa-sliders text-indigo-600"></i>
                <span>Cài Đặt Hệ Thống</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Quản lý website, cổng thanh toán MoMo, gạch thẻ cào và tích hợp API OAuth2.</p>
        </div>
        <div class="px-3.5 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-mono text-indigo-600 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-link text-slate-400"></i>
            <span>Domain: <strong><?= xss_clean($current_domain) ?></strong></span>
        </div>
    </div>

    <!-- Thông báo -->
    <?php if (!empty($message)): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-500 text-base"></i>
            <span><?= xss_clean($message) ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-rose-500 text-base"></i>
            <span><?= xss_clean($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="space-y-6">
        <input type="hidden" name="action_save_settings" value="1">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <!-- BỐ CỤC LƯỚI 2 CỘT RỘNG RÃI -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- KHỐI 1: CÀI ĐẶT CHUNG -->
            <div class="rounded-2xl bg-white p-6 sm:p-7 border border-slate-200/80 shadow-sm space-y-5 flex flex-col">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i class="fa-solid fa-globe text-indigo-600"></i>
                    <span>1. Cài Đặt Chung Website & SEO</span>
                </h3>

                <div class="space-y-4 flex-1">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Tên Website</label>
                        <input type="text" name="site_name" value="<?= xss_clean(get_setting('site_name', 'MARKETCODE.VN - Sàn Giao Dịch Mã Nguồn')) ?>" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Logo URL</label>
                            <input type="text" name="site_logo" value="<?= xss_clean(get_setting('site_logo', 'assets/images/logo.png')) ?>" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Favicon URL</label>
                            <input type="text" name="site_favicon" value="<?= xss_clean(get_setting('site_favicon', 'assets/images/favicon.ico')) ?>" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Hotline</label>
                            <input type="text" name="contact_hotline" value="<?= xss_clean(get_setting('contact_hotline', '0988.888.888')) ?>" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Email Hỗ Trợ</label>
                            <input type="email" name="contact_email" value="<?= xss_clean(get_setting('contact_email', 'support@marketcode.vn')) ?>" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Telegram</label>
                            <input type="text" name="contact_telegram" value="<?= xss_clean(get_setting('contact_telegram', 'https://t.me/admin')) ?>" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Mô Tả SEO (Meta Description)</label>
                        <textarea name="site_description" rows="2" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500"><?= xss_clean(get_setting('site_description')) ?></textarea>
                    </div>

                    <!-- POPUP THÔNG BÁO NỔI TRANG CHỦ -->
                    <div class="pt-3 border-t border-slate-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold uppercase tracking-wider text-amber-600 flex items-center gap-2">
                                <i class="fa-solid fa-bullhorn"></i>
                                <span>Popup Thông Báo Nổi Trang Chủ</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="popup_enabled" value="1" <?= get_setting('popup_enabled', '1') == '1' ? 'checked' : '' ?> class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 bg-slate-50 border-slate-300">
                                <span class="text-xs font-bold text-slate-700">Bật thông báo</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Tiêu Đề Popup</label>
                            <input type="text" name="popup_title" value="<?= xss_clean(get_setting('popup_title', '📢 THÔNG BÁO HỆ THỐNG & KHUYẾN MÃI MỚI NHẤT')) ?>" class="w-full px-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Nội Dung Thông Báo (Hỗ trợ HTML & Emoji)</label>
                            <textarea name="popup_content" rows="4" class="w-full px-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-500 leading-relaxed"><?= htmlspecialchars(get_setting('popup_content', '')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KHỐI 2: CẤU HÌNH TÀI KHOẢN MOMO -->
            <div class="rounded-2xl bg-white p-6 sm:p-7 border border-slate-200/80 shadow-sm space-y-5 flex flex-col">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <span class="w-7 h-7 rounded-lg bg-pink-50 text-[#A50064] flex items-center justify-center font-black">M</span>
                    <span>2. Cấu Hình Tài Khoản MoMo Nạp Tiền</span>
                </h3>

                <div class="space-y-4 flex-1">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">API Endpoint</label>
                        <?php $momoApiEndpoint = get_setting('momo_api_endpoint', 'https://payment.momo.vn/v2/gateway/api/create'); ?>
                        <select name="momo_api_endpoint" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-pink-500">
                            <option value="https://payment.momo.vn/v2/gateway/api/create" <?= $momoApiEndpoint === 'https://payment.momo.vn/v2/gateway/api/create' ? 'selected' : '' ?>>Production — https://payment.momo.vn/v2/gateway/api/create</option>
                            <option value="https://test-payment.momo.vn/v2/gateway/api/create" <?= $momoApiEndpoint === 'https://test-payment.momo.vn/v2/gateway/api/create' ? 'selected' : '' ?>>Sandbox — https://test-payment.momo.vn/v2/gateway/api/create</option>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1.5">Chọn môi trường đúng với Partner Code, Access Key và Secret Key.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Partner Code</label>
                            <input type="text" name="momo_partner_code" value="" autocomplete="off" placeholder="Để trống nếu giữ khóa đã lưu" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-pink-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Access Key</label>
                            <input type="password" name="momo_access_key" value="" autocomplete="new-password" placeholder="Để trống nếu giữ khóa đã lưu" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-pink-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Secret Key</label>
                        <input type="password" name="momo_secret_key" value="" autocomplete="new-password" placeholder="Để trống nếu giữ khóa đã lưu" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-pink-500">
                        <p class="text-[11px] text-slate-500 mt-1.5">Khóa không được hiển thị lại sau khi lưu. Nếu đã lộ, hãy đổi khóa trong MoMo Business trước khi dùng.</p>
                    </div>

                    <div class="p-4 rounded-xl bg-pink-50 border border-pink-100 text-xs text-slate-600 flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-2">
                        <div class="truncate">
                            <span class="block text-slate-500 text-[11px] uppercase tracking-wider font-bold mb-0.5">IPN URL — cấu hình URL HTTPS công khai trên MoMo:</span>
                            <strong class="text-[#A50064] font-mono text-xs"><?= xss_clean($current_domain . '/api/momo_ipn.php') ?></strong>
                        </div>
                        <button type="button" onclick="copyText('<?= xss_clean($current_domain . '/api/momo_ipn.php') ?>')" class="px-3 py-1.5 rounded-xl bg-white hover:bg-pink-100 text-xs font-bold text-slate-700 border border-pink-200 flex items-center gap-1.5 shrink-0 transition-colors shadow-sm">
                            <i class="fa-regular fa-copy"></i>
                            <span>Sao chép</span>
                        </button>
                    </div>
                </div>
            </div>
            <!-- KHỐI 3: CỔNG GẠCH THẺ CÀO -->
            <div class="rounded-2xl bg-white p-6 sm:p-7 border border-slate-200/80 shadow-sm space-y-5 flex flex-col">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i class="fa-solid fa-credit-card text-emerald-600"></i>
                    <span>3. Cấu Hình Cổng Gạch Thẻ Cào Tự Động</span>
                </h3>

                <div class="space-y-4 flex-1">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">API Endpoint Gạch Thẻ</label>
                        <input type="url" name="card_api_url" value="<?= xss_clean(get_setting('card_api_url', 'https://thesieure.com/chargingws/v2')) ?>" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Partner ID</label>
                            <input type="text" name="card_partner_id" value="<?= xss_clean(get_setting('card_partner_id')) ?>" placeholder="ID đối tác cấp..." class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Partner Key (Bí mật)</label>
                            <input type="password" name="card_partner_key" value="<?= xss_clean(get_setting('card_partner_key')) ?>" placeholder="Mã bí mật tạo chữ ký MD5..." class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-2">
                        <div class="truncate">
                            <span class="block text-slate-500 text-[11px] uppercase tracking-wider font-bold mb-0.5">URL Webhook Callback nhận kết quả thẻ:</span>
                            <strong class="text-emerald-700 font-mono text-xs"><?= xss_clean($cardCallbackUrl) ?></strong>
                        </div>
                        <button type="button" onclick="copyText('<?= xss_clean($cardCallbackUrl) ?>')" class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-xs font-bold text-slate-700 border border-slate-200 flex items-center gap-1.5 shrink-0 transition-colors shadow-sm">
                            <i class="fa-regular fa-copy"></i>
                            <span>Sao chép</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- KHỐI 4: OAUTH2 ĐĂNG NHẬP MẠNG XÃ HỘI -->
            <div class="rounded-2xl bg-white p-6 sm:p-7 border border-slate-200/80 shadow-sm space-y-5 flex flex-col">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i class="fa-solid fa-users-viewfinder text-indigo-600"></i>
                    <span>4. Cấu Hình Đăng Nhập Mạng Xã Hội (OAuth2)</span>
                </h3>

                <div class="space-y-4 flex-1">
                    <!-- Google OAuth -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-indigo-600 uppercase flex items-center gap-2">
                                <i class="fa-brands fa-google"></i> Google Login
                            </h4>
                            <span class="text-[10px] text-slate-400 font-mono">Đồng bộ tự động</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input type="text" name="oauth_google_client_id" value="<?= xss_clean(get_setting('oauth_google_client_id')) ?>" placeholder="Google Client ID..." class="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                            <input type="password" name="oauth_google_client_secret" value="<?= xss_clean(get_setting('oauth_google_client_secret')) ?>" placeholder="Google Client Secret..." class="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" name="oauth_google_redirect_uri" id="googleRedirectInput" value="<?= xss_clean($googleRedirect) ?>" class="flex-1 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-[11px] font-mono text-indigo-600 focus:outline-none">
                            <button type="button" onclick="copyText(document.getElementById('googleRedirectInput').value)" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-xs font-bold text-slate-700 border border-slate-200 transition-colors shadow-sm" title="Sao chép Redirect URI">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Facebook OAuth -->
                    <div class="space-y-2.5 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-blue-600 uppercase flex items-center gap-2">
                                <i class="fa-brands fa-facebook"></i> Facebook Login
                            </h4>
                            <span class="text-[10px] text-slate-400 font-mono">Đồng bộ tự động</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input type="text" name="oauth_facebook_app_id" value="<?= xss_clean(get_setting('oauth_facebook_app_id')) ?>" placeholder="Facebook App ID..." class="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                            <input type="password" name="oauth_facebook_app_secret" value="<?= xss_clean(get_setting('oauth_facebook_app_secret')) ?>" placeholder="Facebook App Secret..." class="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" name="oauth_facebook_redirect_uri" id="fbRedirectInput" value="<?= xss_clean($facebookRedirect) ?>" class="flex-1 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-[11px] font-mono text-blue-600 focus:outline-none">
                            <button type="button" onclick="copyText(document.getElementById('fbRedirectInput').value)" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-xs font-bold text-slate-700 border border-slate-200 transition-colors shadow-sm" title="Sao chép Redirect URI">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Zalo OAuth -->
                    <div class="space-y-2.5 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-sky-600 uppercase flex items-center gap-2">
                                <i class="fa-solid fa-comment-dots"></i> Zalo Login
                            </h4>
                            <span class="text-[10px] text-slate-400 font-mono">Đồng bộ tự động</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input type="text" name="oauth_zalo_app_id" value="<?= xss_clean(get_setting('oauth_zalo_app_id')) ?>" placeholder="Zalo App ID..." class="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                            <input type="password" name="oauth_zalo_secret_key" value="<?= xss_clean(get_setting('oauth_zalo_secret_key')) ?>" placeholder="Zalo Secret Key..." class="px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" name="oauth_zalo_redirect_uri" id="zaloRedirectInput" value="<?= xss_clean($zaloRedirect) ?>" class="flex-1 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-[11px] font-mono text-sky-600 focus:outline-none">
                            <button type="button" onclick="copyText(document.getElementById('zaloRedirectInput').value)" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-xs font-bold text-slate-700 border border-slate-200 transition-colors shadow-sm" title="Sao chép Redirect URI">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KHỐI 5: BẢO MẬT CAPTCHA & CRON JOB (CHIẾM 2 CỘT) -->
            <div class="lg:col-span-2 rounded-2xl bg-white p-6 sm:p-7 border border-slate-200/80 shadow-sm space-y-5">
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i class="fa-solid fa-shield-halved text-amber-500"></i>
                    <span>5. Bảo Mật Captcha & Mã Khóa Cron Job Đối Soát Tự Động</span>
                </h3>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">reCAPTCHA v2 Site Key</label>
                        <input type="text" name="recaptcha_site_key" value="<?= xss_clean(get_setting('recaptcha_site_key')) ?>" placeholder="6LeIx..." class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">reCAPTCHA v2 Secret Key</label>
                        <input type="password" name="recaptcha_secret_key" value="<?= xss_clean(get_setting('recaptcha_secret_key')) ?>" placeholder="6LeIx..." class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Mã Bí Mật Cron Bank</label>
                        <input type="text" name="cron_security_key" value="<?= xss_clean($cronKey) ?>" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-amber-600 font-bold focus:outline-none focus:bg-white focus:border-indigo-500">
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="truncate">
                        <span class="block text-slate-500 text-[11px] uppercase tracking-wider font-bold mb-0.5">Đường dẫn Cron đối soát tự động VietQR:</span>
                        <code class="text-amber-700 font-mono text-xs"><?= xss_clean($cronUrl) ?></code>
                    </div>
                    <button type="button" onclick="copyText('<?= xss_clean($cronUrl) ?>')" class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-xs font-bold text-slate-700 border border-slate-200 flex items-center gap-1.5 shrink-0 transition-colors shadow-sm">
                        <i class="fa-regular fa-copy"></i>
                        <span>Sao chép URL Cron</span>
                    </button>
                </div>
            </div>

            <!-- NÚT LƯU CẤU HÌNH (CHIẾM 2 CỘT) -->
            <div class="lg:col-span-2 pt-2">
                <button type="submit" class="w-full py-4 rounded-2xl bg-gradient-to-r from-indigo-600 via-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-black text-sm shadow-xl shadow-indigo-500/20 flex items-center justify-center gap-2.5 transition-all hover:scale-[1.005]">
                    <i class="fa-solid fa-floppy-disk text-base"></i>
                    <span>LƯU TOÀN BỘ CẤU HÌNH HỆ THỐNG</span>
                </button>
            </div>

        </div> <!-- Kết thúc grid 2 cột -->
    </form>
</div>

<script>
    function copyText(str) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(str).then(() => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Đã sao chép đường dẫn!',
                    showConfirmButton: false,
                    timer: 2000
                });
            }).catch(() => {
                prompt('Sao chép đường dẫn sau:', str);
            });
        } else {
            prompt('Sao chép đường dẫn sau:', str);
        }
    }
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
