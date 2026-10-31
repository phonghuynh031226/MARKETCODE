<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete_id'], $_GET['user_id'])
    || isset($_GET['delete_id']) || isset($_GET['toggle_status']) || isset($_GET['toggle_role'])
    || isset($_GET['approve_card_id']) || isset($_GET['reject_card_id']) || isset($_GET['refund_order_id'])) {
    security_require_post_csrf();
}

// Kiểm tra quyền Admin chặt chẽ
if (!is_logged()) {
    header("Location: " . BASE_URL . "/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$currentUser = current_user();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    die('<!DOCTYPE html>
    <html lang="vi" class="light">
    <head><meta charset="UTF-8"><title>403 - Cấm truy cập</title></head>
    <body class="bg-slate-50 text-slate-900 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white border border-slate-200 rounded-3xl p-8 text-center space-y-4 shadow-xl">
            <div class="w-16 h-16 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center text-3xl mx-auto font-black">!</div>
            <h1 class="text-2xl font-black text-rose-600">403 FORBIDDEN</h1>
            <p class="text-xs text-slate-500">Bạn không có quyền quản trị viên để truy cập khu vực này!</p>
            <a href="' . BASE_URL . '/" class="inline-block px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-md">Về Trang Chủ</a>
        </div>
    </body>
    </html>');
}

$siteName = get_setting('site_name', 'MARKETCODE.VN');
$adminUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$adminCleanUri = rtrim($adminUri, '/');
$currentAdminPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(isset($pageTitle) ? xss_clean($pageTitle) . ' | Admin CP' : 'Admin Control Panel') ?></title>

    <?php
    $siteFavicon = get_setting('site_favicon', '');
    if (!empty($siteFavicon)) {
        $faviconUrl = preg_match('#^https?://#i', $siteFavicon) ? $siteFavicon : BASE_URL . '/' . ltrim($siteFavicon, '/');
    ?>
    <link rel="icon" href="<?= e(safe_url($faviconUrl)) ?>">
    <link rel="shortcut icon" href="<?= e(safe_url($faviconUrl)) ?>">
    <?php } ?>

    <!-- Clean URL Script for Admin Panel -->
    <script>
        (function() {
            var path = window.location.pathname;
            var clean = null;
            if (path.endsWith('/admin/index.php') || path === '/admin/index.php') {
                clean = '/admin/';
            } else if (path.endsWith('.php')) {
                clean = path.replace(/\.php$/, '');
            }
            if (clean && clean !== path) {
                window.history.replaceState({}, document.title, clean + window.location.search + window.location.hash);
            }
        })();
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome 6 Pro CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS -->
    <script src="<?= e(BASE_URL . '/assets/vendor/tailwind.js') ?>"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Chart.js CDN -->
    <script src="<?= e(BASE_URL . '/assets/vendor/chart.js') ?>"></script>

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?= e(BASE_URL . '/assets/vendor/sweetalert2.min.css') ?>">
    <script src="<?= e(BASE_URL . '/assets/vendor/sweetalert2.js') ?>"></script>

    <!-- MarketCode SaaS Animations CSS -->
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/animations.css?v=<?= e(filemtime(__DIR__ . '/../../assets/css/animations.css')) ?>">

    <style>
        html {
            scroll-behavior: smooth;
        }
        * {
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        /* Thanh cuộn chính rõ nét, mượt mà và dễ kéo trên toàn trang admin */
        ::-webkit-scrollbar {
            width: 10px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f8fafc;
        }
        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 9999px;
            border: 2px solid #f8fafc;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        /* Thanh cuộn ngang siêu mỏng cho bảng dữ liệu */
        .overflow-x-auto::-webkit-scrollbar {
            height: 6px;
        }
        .overflow-x-auto::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 9999px;
        }
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
        .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
    <link rel="stylesheet" href="<?= e(BASE_URL . '/assets/css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= e(BASE_URL . '/assets/css/navigation.css') ?>">
    <script defer src="<?= e(BASE_URL . '/assets/js/navigation.js') ?>"></script>
    <script defer src="<?= e(BASE_URL . '/assets/js/image-fallbacks.js') ?>"></script>
</head>
<body class="app-responsive admin-shell bg-[#F8FAFC] text-slate-800 min-h-screen flex font-sans antialiased selection:bg-indigo-600 selection:text-white">

    <!-- SIDEBAR (Modular Clean Component) -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- MAIN CONTENT AREA (Cuộn mượt mà tự nhiên theo toàn trang) -->
    <div class="admin-workspace flex-1 flex flex-col min-w-0 min-h-screen">
        <!-- Top Navbar -->
        <header class="admin-toolbar h-20 bg-white/90 backdrop-blur-md border-b border-slate-200 px-6 sm:px-8 flex items-center justify-between sticky top-0 z-30 shadow-sm shrink-0">
            <div class="admin-toolbar-heading flex items-center gap-4"><button id="adminMenuBtn" type="button" class="admin-menu-toggle" aria-label="Mở menu quản trị" aria-expanded="false" aria-controls="adminSidebar"><svg aria-hidden="true" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                <h2 class="text-lg font-black text-slate-900"><?= e(isset($pageTitle) ? xss_clean($pageTitle) : 'Admin Dashboard') ?></h2>
            </div>
            <div class="admin-toolbar-actions flex items-center gap-3 text-xs font-bold">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Hệ Thống Đang Trực Tuyến
                </span>
                <a href="<?= e(BASE_URL) ?>/" class="px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 transition-colors flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-house text-indigo-600"></i>
                    <span>Trang Chủ</span>
                </a>
            </div>
        </header>

        <!-- Content Body Wrapper -->
        <main class="admin-main p-6 sm:p-8 flex-1 space-y-8 w-full max-w-[1600px] mx-auto">
