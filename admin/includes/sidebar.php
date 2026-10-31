<?php
declare(strict_types=1);
if (!defined('ROOT_PATH') || !function_exists('is_admin') || !is_admin()) { http_response_code(404); exit; }
// Sidebar Navigation Component for Admin Panel
$adminUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$adminCleanUri = rtrim($adminUri, '/');
$currentAdminPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!-- SIDEBAR (Modern Clean Light) -->
<aside id="adminSidebar" aria-label="Điều hướng quản trị" class="admin-sidebar w-64 bg-white border-r border-slate-200 flex flex-col justify-between hidden lg:flex sticky top-0 h-screen shrink-0 z-40 shadow-sm">
    <div>
        <button type="button" id="adminMenuClose" class="admin-menu-close" aria-label="Đóng menu quản trị"><svg aria-hidden="true" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        <!-- Brand -->
        <a href="<?= e(BASE_URL) ?>/admin/" class="h-20 flex items-center px-6 border-b border-slate-200 gap-3 group">
            <?php
            $adminLogo = get_setting('site_logo', '');
            $adminLogoUrl = '';
            if (!empty($adminLogo)) {
                $adminLogoPath = (defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__))) . '/' . ltrim($adminLogo, '/');
                if (file_exists($adminLogoPath) || preg_match('#^https?://#i', $adminLogo)) {
                    $adminLogoUrl = preg_match('#^https?://#i', $adminLogo) ? $adminLogo : BASE_URL . '/' . ltrim($adminLogo, '/');
                }
            }
            ?>
            <?php if (!empty($adminLogoUrl)): ?>
                <img src="<?= e(safe_url($adminLogoUrl)) ?>" alt="Logo" class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-sm group-hover:scale-105 transition-transform">
            <?php else: ?>
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                    <i class="fa-solid fa-shield-halved text-lg"></i>
                </div>
            <?php endif; ?>
            <div>
                <span class="text-base font-extrabold tracking-tight text-slate-900 block group-hover:text-indigo-600 transition-colors">ADMIN PANEL</span>
                <span class="text-[10px] text-slate-500 font-semibold uppercase">Management Suite</span>
            </div>
        </a>

        <!-- Navigation Links -->
        <nav class="p-4 space-y-1.5 text-sm">
            <a href="<?= e(BASE_URL) ?>/admin/" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-bold transition-all <?= e(in_array($adminCleanUri, ['/admin', '/admin/index.php']) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900') ?>">
                <i class="fa-solid fa-gauge-high w-5 text-center"></i>
                <span>Bảng Điều Khiển</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/categories" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-bold transition-all <?= e(($currentAdminPage === 'categories.php' || str_ends_with($adminCleanUri, '/categories')) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900') ?>">
                <i class="fa-solid fa-layer-group w-5 text-center"></i>
                <span>Quản Lý Danh Mục</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/products" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-bold transition-all <?= e(($currentAdminPage === 'products.php' || str_ends_with($adminCleanUri, '/products')) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900') ?>">
                <i class="fa-solid fa-code w-5 text-center"></i>
                <span>Quản Lý Mã Nguồn</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/orders" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-bold transition-all <?= e(($currentAdminPage === 'orders.php' || str_ends_with($adminCleanUri, '/orders')) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900') ?>">
                <i class="fa-solid fa-bag-shopping w-5 text-center"></i>
                <span>Đơn Hàng Mua Code</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/deposits" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-bold transition-all <?= e(($currentAdminPage === 'deposits.php' || $currentAdminPage === 'transactions.php' || str_ends_with($adminCleanUri, '/deposits') || str_ends_with($adminCleanUri, '/transactions')) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900') ?>">
                <i class="fa-solid fa-money-bill-transfer w-5 text-center"></i>
                <span>Lịch Sử Nạp Tiền</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/users" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-bold transition-all <?= e(($currentAdminPage === 'users.php' || str_ends_with($adminCleanUri, '/users')) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900') ?>">
                <i class="fa-solid fa-users w-5 text-center"></i>
                <span>Quản Lý Thành Viên</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/settings" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-bold transition-all <?= e(($currentAdminPage === 'settings.php' || str_ends_with($adminCleanUri, '/settings')) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900') ?>">
                <i class="fa-solid fa-sliders w-5 text-center"></i>
                <span>Cài Đặt Hệ Thống</span>
            </a>
        </nav>
    </div>

    <!-- Admin Profile Foot -->
    <div class="p-4 border-t border-slate-200 space-y-2">
        <a href="<?= e(BASE_URL) ?>/" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-600 hover:text-indigo-600 rounded-xl hover:bg-slate-50 transition-colors">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
            <span>Xem Trang Khách Hàng</span>
        </a>
        <div id="adminProfileBox" class="flex items-center justify-between p-2.5 rounded-2xl bg-slate-50 border border-slate-200 transition-colors">
            <div class="flex items-center gap-2.5">
                <img src="<?= e(safe_url(get_user_avatar($currentUser))) ?>" alt="Avatar" onerror="this.onerror=null;this.src='<?= e(BASE_URL) ?>/assets/images/default-avatar.svg';" class="w-8 h-8 rounded-xl object-cover ring-1 ring-slate-300">
                <div class="text-xs">
                    <strong class="text-slate-900 block truncate max-w-[100px]"><?= xss_clean($currentUser['username']) ?></strong>
                    <span class="text-emerald-600 text-[10px] font-semibold">Super Admin</span>
                </div>
            </div>
            <form method="POST" action="<?= e(BASE_URL) ?>/logout"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button type="submit" class="text-slate-400 hover:text-rose-600 p-1.5 transition-colors" title="Đăng xuất">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </button></form>
        </div>
    </div>
</aside>
<button type="button" id="adminMenuBackdrop" class="admin-menu-backdrop" aria-label="Đóng menu quản trị" hidden></button>
