<?php
declare(strict_types=1);
if (!defined('ROOT_PATH')) { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$siteName = get_setting('site_name', 'MARKETCODE.VN - Sàn Giao Dịch Mã Nguồn');
$siteDesc = get_setting('site_description', 'Chợ mua bán mã nguồn chất lượng cao, chia sẻ template website, ứng dụng mobile.');
$user = current_user();
?>
<!DOCTYPE html>
<html lang="vi" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(isset($pageTitle) ? xss_clean($pageTitle) . ' | ' : '') ?><?= xss_clean($siteName) ?></title>
    <meta name="description" content="<?= xss_clean($siteDesc) ?>">

    <?php
    $siteFavicon = get_setting('site_favicon', '');
    if (!empty($siteFavicon)) {
        $faviconUrl = preg_match('#^https?://#i', $siteFavicon) ? $siteFavicon : BASE_URL . '/' . ltrim($siteFavicon, '/');
    ?>
    <link rel="icon" href="<?= e(safe_url($faviconUrl)) ?>">
    <link rel="shortcut icon" href="<?= e(safe_url($faviconUrl)) ?>">
    <?php } ?>

    <!-- Clean URL Script: Tự động làm sạch đuôi .php và index.php trên thanh địa chỉ ngay tức thì -->
    <script>
        (function() {
            var path = window.location.pathname;
            var clean = null;
            if (path.endsWith('/index.php') || path === '/index.php') {
                clean = path.replace(/\/index\.php$/, '/') || '/';
            } else if (path === '/recharge.php' || path === '/deposit.php') {
                clean = '/deposit';
            } else if (path.endsWith('.php')) {
                clean = path.replace(/\.php$/, '');
            }
            if (clean && clean !== path) {
                window.history.replaceState({}, document.title, clean + window.location.search + window.location.hash);
            }
        })();
    </script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome 6 Pro CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS CDN -->
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
                            800: '#3730a3',
                            900: '#312e81',
                            950: '#1e1b4b',
                        }
                    }
                }
            }
        }
    </script>

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?= e(BASE_URL . '/assets/vendor/sweetalert2.min.css') ?>">
    <script src="<?= e(BASE_URL . '/assets/vendor/sweetalert2.js') ?>"></script>

    <!-- AOS (Animate On Scroll) Library CSS -->
    <link rel="stylesheet" href="<?= e(BASE_URL . '/assets/vendor/aos.css') ?>">

    <!-- MarketCode SaaS Animations CSS -->
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/animations.css?v=<?= e(filemtime(__DIR__ . '/../assets/css/animations.css')) ?>">

    <style>
        * {
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
    </style>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/responsive.css?v=<?= e(filemtime(__DIR__ . '/../assets/css/responsive.css')) ?>">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/navigation.css?v=<?= e(filemtime(__DIR__ . '/../assets/css/navigation.css')) ?>">
    <script src="<?= e(BASE_URL) ?>/assets/js/navigation.js" defer></script>
    <script src="<?= e(BASE_URL) ?>/assets/js/image-fallbacks.js" defer></script>
</head>
<body class="app-responsive bg-[#F8FAFC] text-slate-800 min-h-screen flex flex-col font-sans antialiased selection:bg-indigo-600 selection:text-white">
