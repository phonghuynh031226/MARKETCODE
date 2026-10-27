<?php
/**
 * ========================================================
 * FRONT CONTROLLER ROUTER & DISPATCHER
 * Xử lý Clean URLs khi chạy với PHP Built-in Server / Web Server
 * ========================================================
 */
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$route = trim($requestUri, '/');

if ($route !== '' && $route !== 'index' && $route !== 'index.php') {
    // 1. Chặn truy cập trực tiếp vào các thư mục nhạy cảm
    if (preg_match('#^(config|includes|brain|\.git)(/|$)#i', $route)) {
        http_response_code(403);
        echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>403 Forbidden</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-slate-50 min-h-screen flex items-center justify-center p-4 font-sans text-center'><div class='max-w-md w-full bg-white p-8 rounded-3xl border border-slate-200 shadow-xl space-y-4'><div class='w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 border border-rose-200 flex items-center justify-center text-2xl font-bold mx-auto'>!</div><h1 class='text-xl font-extrabold text-slate-900'>403 Forbidden</h1><p class='text-xs text-slate-500'>Truy cập thư mục hệ thống bị từ chối.</p><a href='/' class='inline-block px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 shadow-sm'>Về Trang Chủ</a></div></body></html>";
        exit;
    }

    // 2. Chuyển hướng nếu người dùng nhập có đuôi .php trên thanh địa chỉ (trừ /api/ và /ajax/)
    $queryStr = (!empty($_SERVER['QUERY_STRING'])) ? '?' . $_SERVER['QUERY_STRING'] : '';
    if (str_ends_with($route, '.php') && !preg_match('#^(api|ajax)/#i', $route) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $cleanRoute = substr($route, 0, -4);
        if ($cleanRoute === 'recharge') $cleanRoute = 'deposit';
        header("Location: /" . $cleanRoute . $queryStr, true, 301);
        exit;
    }

    // 3. Đường dẫn vào Khu vực Quản Trị Admin (/admin hoặc /admin/*)
    if ($route === 'admin') {
        $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
        $_SERVER['PHP_SELF']    = '/admin/index.php';
        require __DIR__ . '/admin/index.php';
        exit;
    }
    if (str_starts_with($route, 'admin/')) {
        $adminSub = substr($route, 6);
        if ($adminSub === '' || $adminSub === 'index') {
            $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
            $_SERVER['PHP_SELF']    = '/admin/index.php';
            require __DIR__ . '/admin/index.php';
            exit;
        }
        if ($adminSub === 'deposits') {
            $adminSub = 'transactions';
        }
        $adminTarget = __DIR__ . '/admin/' . $adminSub . '.php';
        if (file_exists($adminTarget) && is_file($adminTarget)) {
            $_SERVER['SCRIPT_NAME'] = '/admin/' . $adminSub . '.php';
            $_SERVER['PHP_SELF']    = '/admin/' . $adminSub . '.php';
            require $adminTarget;
            exit;
        }
        $adminDirect = __DIR__ . '/admin/' . $adminSub;
        if (file_exists($adminDirect) && is_file($adminDirect)) {
            $_SERVER['SCRIPT_NAME'] = '/admin/' . $adminSub;
            $_SERVER['PHP_SELF']    = '/admin/' . $adminSub;
            require $adminDirect;
            exit;
        }
    }

    // 4. Các đường dẫn thân thiện đặc biệt (Pretty URL cho Chi tiết Sản phẩm & Danh mục)
    if (preg_match('#^(product|code)/([0-9]+)$#', $route, $matches)) {
        $_GET['id'] = (int)$matches[2];
        $_SERVER['SCRIPT_NAME'] = '/product.php';
        $_SERVER['PHP_SELF']    = '/product.php';
        require __DIR__ . '/product.php';
        exit;
    }
    if (preg_match('#^(product|code)/([a-zA-Z0-9_\-]+)$#', $route, $matches)) {
        $_GET['slug'] = $matches[2];
        $_SERVER['SCRIPT_NAME'] = '/product.php';
        $_SERVER['PHP_SELF']    = '/product.php';
        require __DIR__ . '/product.php';
        exit;
    }
    if (preg_match('#^categories/([a-zA-Z0-9_\-]+)$#', $route, $matches)) {
        $_GET['slug'] = $matches[1];
        $_GET['cat']  = $matches[1];
        $_SERVER['SCRIPT_NAME'] = '/categories.php';
        $_SERVER['PHP_SELF']    = '/categories.php';
        require __DIR__ . '/categories.php';
        exit;
    }

    // 5. Alias đặc biệt: Nạp tiền (/deposit hoặc /recharge)
    if ($route === 'deposit' || $route === 'recharge') {
        $_SERVER['SCRIPT_NAME'] = '/recharge.php';
        $_SERVER['PHP_SELF']    = '/recharge.php';
        require __DIR__ . '/recharge.php';
        exit;
    }

    // 6. Alias đặc biệt: Đăng ký (/register)
    if ($route === 'register') {
        $_GET['tab'] = 'register';
        $_SERVER['SCRIPT_NAME'] = '/login.php';
        $_SERVER['PHP_SELF']    = '/login.php';
        require __DIR__ . '/login.php';
        exit;
    }

    // 7. Route thông thường tương ứng với file .php ở thư mục gốc (login, categories, warranty, profile, history, product, logout...)
    $targetFile = __DIR__ . '/' . $route . '.php';
    if (file_exists($targetFile) && is_file($targetFile)) {
        $_SERVER['SCRIPT_NAME'] = '/' . $route . '.php';
        $_SERVER['PHP_SELF']    = '/' . $route . '.php';
        require $targetFile;
        exit;
    }

    // 8. Tệp tin trực tiếp (ví dụ /ajax/xxx.php, /api/xxx.php)
    $directFile = __DIR__ . '/' . $route;
    if (file_exists($directFile) && is_file($directFile)) {
        $_SERVER['SCRIPT_NAME'] = '/' . $route;
        $_SERVER['PHP_SELF']    = '/' . $route;
        require $directFile;
        exit;
    }

    // 9. Không tìm thấy đường dẫn -> 404 Not Found
    http_response_code(404);
    echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>404 Không Tìm Thấy Trang</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-slate-50 min-h-screen flex items-center justify-center p-4 text-center font-sans'><div class='max-w-md w-full bg-white p-8 rounded-3xl border border-slate-200 shadow-xl space-y-4'><div class='w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl font-bold mx-auto'>404</div><h1 class='text-xl font-extrabold text-slate-900'>Không Tìm Thấy Trang</h1><p class='text-xs text-slate-500'>Đường dẫn bạn yêu cầu không tồn tại hoặc đã bị thay đổi.</p><a href='/' class='inline-block px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition-colors shadow-sm'>Về Trang Chủ</a></div></body></html>";
    exit;
}

$pageTitle = "Trang Chủ - Mua Bán Mã Nguồn Chất Lượng Cao";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// Xử lý bộ lọc sản phẩm
$searchKeyword = trim($_GET['q'] ?? '');
$selectedCategory = trim($_GET['cat'] ?? '');
$sortBy = trim($_GET['sort'] ?? 'newest');

// Truy vấn danh mục
$categories = [];
if ($db) {
    try {
        $catStmt = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY sort_order ASC");
        $categories = $catStmt->fetchAll();
    } catch (Exception $e) {}
}

// Truy vấn sản phẩm theo điều kiện
$products = [];
if ($db) {
    try {
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                FROM products p 
                JOIN categories c ON p.category_id = c.id 
                WHERE p.status = 'active'";
        $params = [];

        if (!empty($searchKeyword)) {
            $sql .= " AND (p.title LIKE ? OR p.short_desc LIKE ?)";
            $params[] = "%$searchKeyword%";
            $params[] = "%$searchKeyword%";
        }

        if (!empty($selectedCategory)) {
            $sql .= " AND c.slug = ?";
            $params[] = $selectedCategory;
        }

        switch ($sortBy) {
            case 'price_asc':
                $sql .= " ORDER BY COALESCE(p.sale_price, p.price) ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY COALESCE(p.sale_price, p.price) DESC";
                break;
            case 'views_desc':
            case 'views':
                $sql .= " ORDER BY p.views DESC";
                break;
            case 'popular':
            case 'downloads_desc':
                $sql .= " ORDER BY p.download_count DESC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY p.id DESC";
                break;
        }

        $prodStmt = $db->prepare($sql);
        $prodStmt->execute($params);
        $products = $prodStmt->fetchAll();
    } catch (Exception $e) {
        $products = [];
    }
}

// Nếu DB chưa có dữ liệu mẫu, tạo fallback demo data để giao diện luôn hiển thị lung linh
if (empty($products)) {
    $products = [
        [
            'id' => 1,
            'title' => 'Hệ Thống Website Bán Mã Nguồn Tự Động PHP Thuần PDO',
            'slug' => 'he-thong-web-ban-ma-nguon-tu-dong-php',
            'category_name' => 'PHP & Laravel',
            'category_slug' => 'php-laravel',
            'short_desc' => 'Tự động hóa nạp thẻ cào, VietQR tự động, quản trị toàn diện, bảo mật CSRF & BCRYPT.',
            'price' => 499000,
            'sale_price' => 350000,
            'thumbnail' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&auto=format&fit=crop&q=80',
            'demo_url' => 'https://demo.marketcode.vn',
            'download_count' => 128,
            'views' => 1420,
        ],
        [
            'id' => 2,
            'title' => 'Full-Stack E-Commerce Next.js 14 & Node.js Microservices',
            'slug' => 'full-stack-ecommerce-nextjs-nodejs',
            'category_name' => 'Node.js & React',
            'category_slug' => 'nodejs-react',
            'short_desc' => 'App Router, Redux Toolkit, Stripe & PayPal, Tailwind CSS, tối ưu SEO 100 điểm Lighthouse.',
            'price' => 890000,
            'sale_price' => 650000,
            'thumbnail' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&auto=format&fit=crop&q=80',
            'demo_url' => 'https://next-ecommerce.demo.vn',
            'download_count' => 89,
            'views' => 980,
        ],
        [
            'id' => 3,
            'title' => 'Flutter Food Delivery App (iOS & Android) + Admin Panel',
            'slug' => 'flutter-food-delivery-app-ios-android',
            'category_name' => 'Mobile Apps',
            'category_slug' => 'mobile-apps',
            'short_desc' => 'Định vị GPS shipper thời gian thực, thông báo push Firebase, tích hợp cổng thanh toán.',
            'price' => 1200000,
            'sale_price' => 890000,
            'thumbnail' => 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=800&auto=format&fit=crop&q=80',
            'demo_url' => 'https://flutter-food.demo.vn',
            'download_count' => 215,
            'views' => 1860,
        ],
        [
            'id' => 4,
            'title' => 'Python Bot Quét Dữ Liệu TMĐT & Gửi Báo Cáo Telegram',
            'slug' => 'python-bot-auto-crawl-telegram',
            'category_name' => 'Python & AI',
            'category_slug' => 'python-django',
            'short_desc' => 'Thu thập giá Shopee, Lazada tự động, phát hiện giảm giá sốc và gửi tin nhắn Telegram tức thì.',
            'price' => 250000,
            'sale_price' => 199000,
            'thumbnail' => 'https://images.unsplash.com/photo-1526379095098-d400fd0bf935?w=800&auto=format&fit=crop&q=80',
            'demo_url' => 'https://t.me/demo_crawler_bot',
            'download_count' => 340,
            'views' => 2450,
        ]
    ];
}
?>

<!-- HERO SECTION (Modern Clean Light Aesthetic) -->
<section class="relative overflow-hidden py-16 lg:py-24 bg-dot-pattern border-b border-slate-200/70">
    <!-- Soft Pastel Mesh Glow Orbs -->
    <div class="absolute -top-24 left-1/4 w-96 h-96 bg-indigo-500/10 rounded-full blur-[100px] pointer-events-none floating-orb-1"></div>
    <div class="absolute top-10 right-1/4 w-80 h-80 bg-sky-500/10 rounded-full blur-[100px] pointer-events-none floating-orb-2"></div>
    <div class="absolute -bottom-16 left-1/3 w-80 h-80 bg-rose-500/5 rounded-full blur-[90px] pointer-events-none floating-orb-3"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <!-- Badge Tag -->
        <div data-aos="fade-down" class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white border border-slate-200/80 text-xs sm:text-sm font-semibold text-indigo-700 mb-6 shadow-sm">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Hệ thống giao dịch tự động 24/7 - Tải mã nguồn tức thì</span>
        </div>

        <!-- Main Heading -->
        <h1 data-aos="fade-up" data-aos-delay="100" class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 max-w-4xl mx-auto leading-tight">
            Chợ Mua Bán <span class="bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 bg-clip-text text-transparent">Mã Nguồn Cao Cấp</span> Cho Lập Trình Viên
        </h1>
        <p data-aos="fade-up" data-aos-delay="200" class="mt-6 text-base sm:text-lg text-slate-600 max-w-2xl mx-auto leading-relaxed">
            Hơn 1.500+ source code website, app mobile, script bot tự động đã được kiểm định an toàn, hướng dẫn cài đặt chi tiết và hỗ trợ kỹ thuật trọn đời.
        </p>

        <!-- Search Bar with Live Form -->
        <form data-aos="fade-up" data-aos-delay="300" action="<?= BASE_URL ?>/" method="GET" class="mt-10 max-w-2xl mx-auto">
            <div class="relative flex items-center bg-white rounded-2xl p-2 border border-slate-200/90 shadow-[0_10px_40px_-10px_rgba(15,23,42,0.08)] transition-all focus-within:border-indigo-500 focus-within:ring-4 focus-within:ring-indigo-500/10">
                <i class="fa-solid fa-magnifying-glass text-slate-400 ml-3 text-lg"></i>
                <input type="text" name="q" value="<?= xss_clean($searchKeyword) ?>" placeholder="Tìm kiếm theo từ khóa (PHP, Flutter, Laravel, E-commerce, Game...)" class="w-full bg-transparent px-4 py-3 text-sm sm:text-base focus:outline-none text-slate-900 placeholder-slate-400">
                <button type="submit" class="btn-shimmer px-6 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold text-sm shadow-md shadow-indigo-500/25 transition-all flex items-center gap-2 whitespace-nowrap active:scale-95">
                    <span>Tìm Kiếm</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>

        <!-- 4 Quick Stats Boxes -->
        <div data-aos="zoom-in-up" data-aos-delay="400" class="mt-12 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
            <div class="p-5 rounded-2xl bg-white border border-slate-200/70 shadow-sm hover:shadow-md transition-all text-center hover:-translate-y-1">
                <div class="text-2xl sm:text-3xl font-extrabold text-indigo-600 font-mono" data-counter="1500+">1.500+</div>
                <div class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">Source Code Uy Tín</div>
            </div>
            <div class="p-5 rounded-2xl bg-white border border-slate-200/70 shadow-sm hover:shadow-md transition-all text-center hover:-translate-y-1">
                <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 font-mono" data-counter="25000+">25.000+</div>
                <div class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">Lượt Tải Thành Công</div>
            </div>
            <div class="p-5 rounded-2xl bg-white border border-slate-200/70 shadow-sm hover:shadow-md transition-all text-center hover:-translate-y-1">
                <div class="text-2xl sm:text-3xl font-extrabold text-blue-600 font-mono" data-counter="100%">100%</div>
                <div class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">Cam Kết Bảo Hành Code</div>
            </div>
            <div class="p-5 rounded-2xl bg-white border border-slate-200/70 shadow-sm hover:shadow-md transition-all text-center hover:-translate-y-1">
                <div class="text-2xl sm:text-3xl font-extrabold text-violet-600 font-mono" data-counter="30 Giây">30 Giây</div>
                <div class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">Nạp Tiền & Tải Ngay</div>
            </div>
        </div>
    </div>
</section>

<style>
/* ========================================================
   ANIMATIONS & MICRO-INTERACTIONS FOR PRODUCT CARDS
   ======================================================== */
@keyframes cardReveal {
    0% {
        opacity: 0;
        transform: translateY(28px) scale(0.96);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.category-pill-item {
    transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), background-color 0.25s ease, border-color 0.25s ease, color 0.25s ease, box-shadow 0.25s ease;
    cursor: pointer;
}
.category-pill-item:hover {
    transform: translateY(-2px);
}
.category-pill-item:active {
    transform: scale(0.95);
}

.product-card {
    animation: cardReveal 0.65s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(var(--ci, 0) * 0.08s);
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s cubic-bezier(0.22, 1, 0.36, 1), border-color 0.35s ease;
    will-change: transform, box-shadow;
}

.product-card:hover {
    transform: translateY(-8px) !important;
    box-shadow: 0 20px 35px -10px rgba(79, 70, 229, 0.18) !important;
    border-color: #818cf8 !important;
}

.product-thumb-container {
    position: relative;
    overflow: hidden;
}
.product-thumb-container img {
    transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.product-card:hover .product-thumb-container img {
    transform: scale(1.08);
}

.product-thumb-container::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 60%;
    height: 100%;
    background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.45), transparent);
    transform: skewX(-20deg);
    pointer-events: none;
    transition: none;
    z-index: 15;
}
.product-card:hover .product-thumb-container::after {
    left: 140%;
    transition: left 0.65s cubic-bezier(0.22, 1, 0.36, 1);
}

.product-detail-btn {
    transition: background-color 0.3s ease, color 0.3s ease, box-shadow 0.3s ease, transform 0.2s ease;
}
.product-detail-btn .product-detail-arrow {
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}
.product-card:hover .product-detail-btn {
    background-color: #4f46e5 !important;
    color: #ffffff !important;
    box-shadow: 0 6px 16px -4px rgba(79, 70, 229, 0.4);
}
.product-card:hover .product-detail-arrow {
    transform: translateX(4px);
}
</style>

<!-- CATEGORY FILTER PILLS -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-600">Danh Mục Mã Nguồn</h3>
        <a href="<?= BASE_URL ?>/categories" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
            <span>Mở bộ lọc nâng cao</span>
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
        </a>
    </div>
    <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
        <a href="<?= BASE_URL ?>/categories" class="category-pill-item px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap bg-indigo-600 text-white shadow-md shadow-indigo-600/20">
            <i class="fa-solid fa-border-all mr-2"></i>Tất Cả Danh Mục
        </a>
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $cat): ?>
                <a href="<?= BASE_URL ?>/categories?slug=<?= urlencode($cat['slug']) ?>" class="category-pill-item px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap bg-white border border-slate-200 text-slate-700 hover:text-indigo-600 hover:border-indigo-300 hover:bg-slate-50 transition-all shadow-sm">
                    <i class="<?= xss_clean($cat['icon']) ?> mr-2 text-indigo-500"></i><?= xss_clean($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/categories?slug=php-laravel" class="category-pill-item px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap bg-white border border-slate-200 text-slate-700 hover:text-indigo-600 hover:border-indigo-300 transition-all shadow-sm">
                <i class="fa-brands fa-php mr-2 text-indigo-500"></i>PHP & Laravel
            </a>
            <a href="<?= BASE_URL ?>/categories?slug=nodejs-react" class="category-pill-item px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap bg-white border border-slate-200 text-slate-700 hover:text-indigo-600 hover:border-indigo-300 transition-all shadow-sm">
                <i class="fa-brands fa-node-js mr-2 text-indigo-500"></i>Node.js & React
            </a>
            <a href="<?= BASE_URL ?>/categories?slug=python-django" class="category-pill-item px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap bg-white border border-slate-200 text-slate-700 hover:text-indigo-600 hover:border-indigo-300 transition-all shadow-sm">
                <i class="fa-brands fa-python mr-2 text-indigo-500"></i>Python & AI
            </a>
            <a href="<?= BASE_URL ?>/categories?slug=mobile-apps" class="category-pill-item px-5 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap bg-white border border-slate-200 text-slate-700 hover:text-indigo-600 hover:border-indigo-300 transition-all shadow-sm">
                <i class="fa-solid fa-mobile-screen mr-2 text-indigo-500"></i>Mobile Apps
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- MAIN STORE SECTION: PRODUCTS & FILTER -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 pb-16">
    <!-- Header of products list: Total count & Sort Selector -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-900 flex items-center gap-2">
                <span>Kho Mã Nguồn Mới Cập Nhật</span>
                <span class="text-xs px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 font-bold border border-indigo-200/60"><span class="counter-val font-bold" data-target="<?= count($products) ?>"><?= count($products) ?></span> mã nguồn</span>
            </h2>
            <p class="text-sm text-slate-500 mt-1">Toàn bộ mã nguồn đã được quét virus và kiểm tra chạy tốt trước khi đăng bán.</p>
        </div>

        <!-- Sort Select -->
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <span class="text-xs font-semibold text-slate-500 whitespace-nowrap">Sắp xếp:</span>
            <select id="sortSelect" onchange="applySort(this.value)" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm cursor-pointer">
                <option value="newest" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Mới Nhất</option>
                <option value="views_desc" <?= $sortBy == 'views_desc' ? 'selected' : '' ?>>Xem Nhiều Nhất (Phổ biến)</option>
                <option value="popular" <?= ($sortBy == 'popular' || $sortBy == 'downloads_desc') ? 'selected' : '' ?>>Tải Nhiều Nhất</option>
                <option value="price_asc" <?= $sortBy == 'price_asc' ? 'selected' : '' ?>>Giá Thấp Đến Cao</option>
                <option value="price_desc" <?= $sortBy == 'price_desc' ? 'selected' : '' ?>>Giá Cao Đến Thấp</option>
            </select>
        </div>
    </div>

    <!-- PRODUCT GRID (Modern Clean Light Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php foreach ($products as $idx => $p): ?>
            <?php
            $currentPrice = !empty($p['sale_price']) && $p['sale_price'] > 0 ? $p['sale_price'] : $p['price'];
            $hasDiscount = !empty($p['sale_price']) && $p['sale_price'] < $p['price'];
            $discountPercent = $hasDiscount ? round((($p['price'] - $p['sale_price']) / $p['price']) * 100) : 0;
            ?>
            <div class="product-card group bg-white rounded-2xl border border-slate-200/80 overflow-hidden flex flex-col" style="--ci: <?= ($idx % 4) + 1 ?>;">
                <!-- Phần Ảnh (aspect-[16/10]) -->
                <a href="<?= BASE_URL ?>/product?id=<?= $p['id'] ?>" class="product-thumb-container aspect-[16/10] bg-slate-100 block">
                    <img src="<?= xss_clean($p['thumbnail']) ?>" alt="<?= xss_clean($p['title']) ?>" class="w-full h-full object-cover" onerror="this.onerror=null;this.src='<?= BASE_URL ?>/assets/images/default-code.jpg';">
                    
                    <!-- Góc trên bên trái ảnh: Badge tên danh mục gọn gàng -->
                    <span class="absolute top-2.5 left-2.5 bg-slate-900/75 backdrop-blur-md text-white text-[11px] font-medium px-2.5 py-1 rounded-lg z-20 truncate max-w-[140px]">
                        <?= xss_clean($p['category_name'] ?? 'Mã Nguồn') ?>
                    </span>

                    <!-- Góc trên bên phải ảnh: Badge giảm giá nếu có -->
                    <?php if ($hasDiscount): ?>
                        <span class="absolute top-2.5 right-2.5 bg-rose-500 text-white text-[11px] font-bold px-2 py-1 rounded-lg z-20 shadow-sm">
                            -<?= $discountPercent ?>%
                        </span>
                    <?php endif; ?>
                </a>

                <!-- Phần Thân Thẻ -->
                <div class="p-5 flex flex-col flex-1 justify-between">
                    <div>
                        <!-- Tiêu đề (gọn gàng line-clamp-1) -->
                        <h3 class="text-[15px] font-bold text-slate-900 group-hover:text-indigo-600 transition-colors line-clamp-1">
                            <a href="<?= BASE_URL ?>/product?id=<?= $p['id'] ?>" title="<?= xss_clean($p['title']) ?>">
                                <?= xss_clean($p['title']) ?>
                            </a>
                        </h3>

                        <!-- Mô tả 1 dòng mảnh -->
                        <?php if (!empty($p['short_desc'])): ?>
                            <p class="text-xs text-slate-500 line-clamp-1 mt-1">
                                <?= xss_clean($p['short_desc']) ?>
                            </p>
                        <?php endif; ?>

                        <!-- Thống kê Lượt xem & Lượt tải với Number Counter -->
                        <div class="flex items-center gap-4 mt-3 text-xs text-slate-500 font-medium">
                            <span class="inline-flex items-center gap-1.5" title="Lượt xem">
                                <i class="fa-regular fa-eye text-slate-400"></i>
                                <span class="counter-val" data-target="<?= (int)($p['views'] ?? $p['view_count'] ?? 0) ?>"><?= number_format((int)($p['views'] ?? $p['view_count'] ?? 0)) ?></span> lượt xem
                            </span>
                            <span class="inline-flex items-center gap-1.5" title="Lượt tải">
                                <i class="fa-solid fa-download text-slate-400"></i>
                                <span class="counter-val" data-target="<?= (int)$p['download_count'] ?>"><?= number_format((int)$p['download_count']) ?></span> lượt tải
                            </span>
                        </div>
                    </div>

                    <!-- Phần Đáy Thẻ (Giá & Nút Hành Động) -->
                    <div class="mt-4 pt-3.5 border-t border-slate-100 flex items-center justify-between gap-3">
                        <!-- Bên trái: Khối Giá 2 tầng xếp dọc -->
                        <div class="flex flex-col justify-center min-w-0 leading-none">
                            <?php if ($hasDiscount && $p['price'] > 0): ?>
                                <span class="text-[11px] font-medium text-slate-400 line-through mb-1 whitespace-nowrap">
                                    <?= number_format($p['price'], 0, ',', '.') ?>đ
                                </span>
                            <?php else: ?>
                                <span class="text-[11px] text-transparent mb-1 select-none leading-none whitespace-nowrap" aria-hidden="true">&nbsp;</span>
                            <?php endif; ?>
                            <span class="text-[17px] font-extrabold text-indigo-600 tracking-tight whitespace-nowrap">
                                <?= $currentPrice <= 0 ? 'Miễn phí' : number_format($currentPrice, 0, ',', '.') . 'đ' ?>
                            </span>
                        </div>

                        <!-- Bên phải: Nút bấm gọn gàng không rớt dòng -->
                        <a href="<?= BASE_URL ?>/product?id=<?= $p['id'] ?>" class="product-detail-btn shrink-0 bg-indigo-50 hover:bg-indigo-600 text-indigo-600 hover:text-white text-xs font-bold px-3.5 py-2.5 rounded-xl transition-all duration-300 inline-flex items-center gap-1.5 whitespace-nowrap">
                            <span>Chi tiết</span>
                            <i class="product-detail-arrow fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- FEATURES / TRUST BANNER -->
<section id="features" data-aos="fade-up" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="rounded-3xl bg-white p-8 sm:p-12 border border-slate-200/80 shadow-sm relative overflow-hidden">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="text-3xl font-extrabold text-slate-900">Tại Sao Chọn MarketCode?</h2>
            <p class="text-sm text-slate-500 mt-2">Chúng tôi đặt chất lượng mã nguồn và sự an tâm của khách hàng lên hàng đầu.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div data-aos="zoom-in" data-aos-delay="100" class="flex flex-col items-center text-center p-4 rounded-2xl hover:bg-slate-50 transition-all">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-2xl mb-4 shadow-sm">
                    <i class="fa-solid fa-cloud-arrow-down"></i>
                </div>
                <h4 class="font-bold text-base text-slate-900 mb-2">Tải Về Tự Động 100%</h4>
                <p class="text-xs text-slate-500 leading-relaxed">Hệ thống cấp link tải Google Drive & Mega ngay khi bạn thanh toán thành công.</p>
            </div>
            <div data-aos="zoom-in" data-aos-delay="200" class="flex flex-col items-center text-center p-4 rounded-2xl hover:bg-slate-50 transition-all">
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center text-2xl mb-4 shadow-sm">
                    <i class="fa-solid fa-virus-slash"></i>
                </div>
                <h4 class="font-bold text-base text-slate-900 mb-2">Mã Nguồn Sạch 100%</h4>
                <p class="text-xs text-slate-500 leading-relaxed">Tất cả file được quét bằng antivirus chuyên dụng, không shell, không backdoor.</p>
            </div>
            <div data-aos="zoom-in" data-aos-delay="300" class="flex flex-col items-center text-center p-4 rounded-2xl hover:bg-slate-50 transition-all">
                <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center text-2xl mb-4 shadow-sm">
                    <i class="fa-solid fa-book-bookmark"></i>
                </div>
                <h4 class="font-bold text-base text-slate-900 mb-2">Hướng Dẫn Cài Đặt</h4>
                <p class="text-xs text-slate-500 leading-relaxed">Đầy đủ file README, video hướng dẫn setup từ A-Z hoặc hỗ trợ qua Ultraview/AnyDesk.</p>
            </div>
            <div data-aos="zoom-in" data-aos-delay="400" class="flex flex-col items-center text-center p-4 rounded-2xl hover:bg-slate-50 transition-all">
                <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center text-2xl mb-4 shadow-sm">
                    <i class="fa-solid fa-rotate-left"></i>
                </div>
                <h4 class="font-bold text-base text-slate-900 mb-2">Bảo Hành & Hoàn Tiền</h4>
                <p class="text-xs text-slate-500 leading-relaxed">Hoàn tiền 100% nếu mã nguồn không đúng mô tả hoặc không chạy được.</p>
            </div>
        </div>
    </div>
</section>

<!-- BUY CONFIRMATION MODAL (Clean Light Dialog) -->
<div id="buyModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 sm:p-8 shadow-2xl border border-slate-200 animate-in fade-in zoom-in duration-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-cart-check text-indigo-600"></i>Xác Nhận Mua Mã Nguồn
            </h3>
            <button type="button" onclick="closeBuyModal()" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="py-5 space-y-4">
            <div>
                <span class="text-xs font-semibold text-slate-500">Sản phẩm:</span>
                <p id="modalProductTitle" class="text-sm font-bold text-slate-900 mt-0.5 line-clamp-2"></p>
            </div>
            <div class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80">
                <span class="text-xs font-semibold text-slate-500">Giá thanh toán:</span>
                <span id="modalProductPrice" class="text-base font-extrabold text-indigo-600"></span>
            </div>
            <div class="flex items-center justify-between text-xs font-semibold px-1">
                <span class="text-slate-500">Số dư hiện tại của bạn:</span>
                <span class="text-emerald-600 font-bold"><?= is_logged() ? format_money($user['balance']) : 'Chưa đăng nhập' ?></span>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-3">
            <button type="button" onclick="closeBuyModal()" class="w-1/2 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-bold text-sm hover:bg-slate-50 transition-colors">
                Hủy bỏ
            </button>
            <button type="button" id="btnConfirmBuy" onclick="executeBuy()" class="w-1/2 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold text-sm shadow-md shadow-indigo-500/25 flex items-center justify-center gap-2 transition-all">
                <span>Thanh Toán</span>
                <i class="fa-solid fa-check"></i>
            </button>
        </div>
    </div>
</div>

<script>
    let currentProductId = null;
    const isUserLogged = <?= is_logged() ? 'true' : 'false' ?>;

    function applySort(val) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', val);
        window.location.href = url.toString();
    }

    function openBuyModal(id, title, price) {
        if (!isUserLogged) {
            Swal.fire({
                title: 'Yêu Cầu Đăng Nhập',
                text: 'Bạn cần đăng nhập tài khoản để mua và nhận mã nguồn!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Đăng nhập ngay',
                cancelButtonText: 'Để sau',
                confirmButtonColor: '#4f46e5'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?= BASE_URL ?>/login?redirect=' + encodeURIComponent(window.location.href);
                }
            });
            return;
        }

        currentProductId = id;
        document.getElementById('modalProductTitle').innerText = title;
        document.getElementById('modalProductPrice').innerText = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
        document.getElementById('buyModal').classList.remove('hidden');
    }

    function closeBuyModal() {
        document.getElementById('buyModal').classList.add('hidden');
    }

    async function executeBuy() {
        if (!currentProductId) return;

        const btn = document.getElementById('btnConfirmBuy');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';

        try {
            const formData = new FormData();
            formData.append('product_id', currentProductId);
            formData.append('csrf_token', '<?= csrf_token() ?>');

            const res = await fetch('<?= BASE_URL ?>/ajax/buy.php', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (data.success) {
                closeBuyModal();
                Swal.fire({
                    title: 'Mua Thành Công!',
                    text: data.message || 'Mã nguồn đã được thêm vào tài khoản của bạn.',
                    icon: 'success',
                    confirmButtonText: 'Xem mã nguồn đã mua',
                    confirmButtonColor: '#4f46e5'
                }).then(() => {
                    window.location.href = '<?= BASE_URL ?>/history?tab=orders';
                });
            } else {
                if (data.insufficient_balance) {
                    Swal.fire({
                        title: 'Số Dư Không Đủ!',
                        text: data.message,
                        icon: 'error',
                        showCancelButton: true,
                        confirmButtonText: 'Nạp tiền ngay',
                        cancelButtonText: 'Đóng',
                        confirmButtonColor: '#10b981'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '<?= BASE_URL ?>/deposit?tab=bank';
                        }
                    });
                } else {
                    Swal.fire('Lỗi', data.message || 'Có lỗi xảy ra trong quá trình thanh toán!', 'error');
                }
            }
        } catch (err) {
            Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ. Vui lòng thử lại!', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>Thanh Toán</span> <i class="fa-solid fa-check"></i>';
        }
    }

    // Number Counter Animation cho Thẻ Sản Phẩm & Badge
    function animateCounters() {
        const counters = document.querySelectorAll('.counter-val');
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target') || '0', 10);
            if (isNaN(target)) return;
            const duration = 800;
            const startTime = performance.now();
            function updateCounter(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeProgress = 1 - Math.pow(1 - progress, 3);
                const currentVal = Math.floor(easeProgress * target);
                counter.textContent = new Intl.NumberFormat('vi-VN').format(currentVal);
                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = new Intl.NumberFormat('vi-VN').format(target);
                }
            }
            requestAnimationFrame(updateCounter);
        });
    }

    document.addEventListener('DOMContentLoaded', animateCounters);
    if (document.readyState !== 'loading') animateCounters();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
