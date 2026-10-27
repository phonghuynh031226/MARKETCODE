<?php
require_once __DIR__ . '/config/config.php';

// Các tham số lọc & phân trang
$keyword    = trim($_GET['q'] ?? '');
$catSlug    = trim($_GET['slug'] ?? ($_GET['cat'] ?? ''));
$priceRange = trim($_GET['price'] ?? 'all');
$sortBy     = trim($_GET['sort'] ?? 'newest');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 12; // 12 sản phẩm để hiển thị cân đối cho cả lưới 3 cột và 4 cột
$offset     = ($page - 1) * $limit;

// Truy vấn toàn bộ danh mục kèm số lượng mã nguồn
$allCategories = [];
$currentCategory = null;
if ($db) {
    try {
        $catStmt = $db->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active') as product_count 
                              FROM categories c 
                              WHERE c.status = 1 
                              ORDER BY c.sort_order ASC, c.id DESC");
        $allCategories = $catStmt->fetchAll();

        if (!empty($catSlug)) {
            foreach ($allCategories as $c) {
                if ($c['slug'] === $catSlug) {
                    $currentCategory = $c;
                    break;
                }
            }
        }
    } catch (Exception $e) {}
}

// Xây dựng câu truy vấn lọc sản phẩm
$where = ["p.status = 'active'"];
$params = [];

if (!empty($keyword)) {
    $where[] = "(p.title LIKE ? OR p.short_desc LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

if (!empty($catSlug)) {
    $where[] = "c.slug = ?";
    $params[] = $catSlug;
}

if ($priceRange === 'free') {
    $where[] = "COALESCE(p.sale_price, p.price) = 0";
} elseif ($priceRange === 'under_200') {
    $where[] = "COALESCE(p.sale_price, p.price) > 0 AND COALESCE(p.sale_price, p.price) < 200000";
} elseif ($priceRange === '200_500') {
    $where[] = "COALESCE(p.sale_price, p.price) >= 200000 AND COALESCE(p.sale_price, p.price) <= 500000";
} elseif ($priceRange === 'above_500') {
    $where[] = "COALESCE(p.sale_price, p.price) > 500000";
}

$whereSql = implode(' AND ', $where);

// Thứ tự sắp xếp
$orderSql = "ORDER BY p.id DESC";
if ($sortBy === 'views_desc' || $sortBy === 'views' || $sortBy === 'popular') {
    $orderSql = "ORDER BY p.views DESC";
} elseif ($sortBy === 'downloads_desc' || $sortBy === 'downloads') {
    $orderSql = "ORDER BY p.download_count DESC";
} elseif ($sortBy === 'price_asc') {
    $orderSql = "ORDER BY COALESCE(p.sale_price, p.price) ASC";
} elseif ($sortBy === 'price_desc') {
    $orderSql = "ORDER BY COALESCE(p.sale_price, p.price) DESC";
}

// Đếm tổng số bản ghi để phân trang
$totalProducts = 0;
$products = [];
if ($db) {
    try {
        $countSql = "SELECT COUNT(*) FROM products p JOIN categories c ON p.category_id = c.id WHERE $whereSql";
        $cStmt = $db->prepare($countSql);
        $cStmt->execute($params);
        $totalProducts = (int)$cStmt->fetchColumn();

        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                FROM products p 
                JOIN categories c ON p.category_id = c.id 
                WHERE $whereSql 
                $orderSql 
                LIMIT $limit OFFSET $offset";
        $pStmt = $db->prepare($sql);
        $pStmt->execute($params);
        $products = $pStmt->fetchAll();
    } catch (Exception $e) {}
}

$totalPages = max(1, ceil($totalProducts / $limit));

$pageTitle = $currentCategory ? "Danh Mục: " . $currentCategory['name'] : "Kho Mã Nguồn";
$user = current_user();

// Helper xây dựng URL bộ lọc
function filter_url(array $overrides = []): string {
    $params = $_GET;
    if (array_key_exists('slug', $overrides)) {
        unset($params['cat']);
    }
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '' || ($k === 'price' && $v === 'all') || ($k === 'sort' && $v === 'newest') || ($k === 'page' && $v == 1)) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    if (!isset($overrides['page'])) {
        unset($params['page']);
    }
    $query = http_build_query($params);
    return BASE_URL . '/categories' . ($query ? '?' . $query : '');
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
/* ========================================================
   ANIMATIONS & MICRO-INTERACTIONS FOR CATEGORIES PAGE
   ======================================================== */

/* 1. KEYFRAMES */
@keyframes headerSlideDown {
    0% {
        opacity: 0;
        transform: translateY(-15px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pillSlideIn {
    0% {
        opacity: 0;
        transform: translateX(-12px);
    }
    100% {
        opacity: 1;
        transform: translateX(0);
    }
}

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

/* Header animation */
.header-entrance {
    animation: headerSlideDown 0.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

/* 5. Nút Danh Mục: Xuất hiện tuần tự + Spring / Magnetic Click */
.category-pill-item {
    animation: pillSlideIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(var(--i, 0) * 40ms);
    transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), background-color 0.25s ease, border-color 0.25s ease, color 0.25s ease, box-shadow 0.25s ease;
    cursor: pointer;
}
.category-pill-item:hover {
    transform: translateY(-2px);
}
.category-pill-item:active {
    transform: scale(0.95);
}

/* 2. Thẻ Sản Phẩm: 3D Hover Lift + Image Zoom + Shine Sweep */
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

/* Image Zoom */
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

/* Shine Sweep Effect trên ảnh */
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

/* Nút Chi Tiết tự đổi nền tím & mũi tên trượt sang phải */
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

/* 3. Lọc AJAX Smooth Transition */
#productContainer {
    transition: opacity 150ms ease, transform 150ms ease;
}
#productContainer.is-loading {
    opacity: 0;
    transform: translateY(10px);
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-7">

    <!-- 1. COMPACT TOP HEADER & HORIZONTAL FILTER BAR (STAGGERED ENTRANCE) -->
    <div class="header-entrance space-y-4">
        <!-- Dòng Tiêu Đề & Ô Tìm Kiếm + Dropdown Lọc Cùng 1 Hàng Ngang -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-slate-200/80">
            <!-- Bên trái: Tiêu đề Kho Mã Nguồn + Badge số lượng -->
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                    Kho Mã Nguồn
                </h1>
                <span class="bg-slate-100 text-slate-600 text-xs font-semibold px-2.5 py-1 rounded-full whitespace-nowrap">
                    <span id="productTotalBadge" class="counter-val font-bold" data-target="<?= (int)$totalProducts ?>"><?= number_format($totalProducts) ?></span> sản phẩm
                </span>
            </div>

            <!-- Bên phải: Ô Tìm Kiếm + Dropdown Giá + Dropdown Sắp Xếp (CÙNG MỘT HÀNG NGANG) -->
            <form id="filterForm" method="GET" action="<?= BASE_URL ?>/categories" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full lg:w-auto">
                <?php if (!empty($catSlug)): ?>
                    <input type="hidden" name="slug" value="<?= xss_clean($catSlug) ?>">
                <?php endif; ?>

                <!-- Ô Tìm Kiếm (w-full md:w-80) -->
                <div class="relative w-full sm:w-64 md:w-80 shrink-0">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= xss_clean($keyword) ?>" placeholder="Tìm tên mã nguồn, công nghệ..." autocomplete="off" class="w-full pl-9 pr-8 py-2 rounded-xl bg-white border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all shadow-2xs">
                    <?php if (!empty($keyword)): ?>
                        <a href="<?= filter_url(['q' => null]) ?>" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" title="Xóa tìm kiếm">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Dropdown Chọn Khoảng Giá -->
                <select name="price" class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all shadow-2xs cursor-pointer">
                    <option value="all" <?= $priceRange === 'all' ? 'selected' : '' ?>>Tất cả mức giá</option>
                    <option value="free" <?= $priceRange === 'free' ? 'selected' : '' ?>>Miễn phí</option>
                    <option value="under_200" <?= $priceRange === 'under_200' ? 'selected' : '' ?>>Dưới 200k</option>
                    <option value="200_500" <?= $priceRange === '200_500' ? 'selected' : '' ?>>200k - 500k</option>
                    <option value="above_500" <?= $priceRange === 'above_500' ? 'selected' : '' ?>>Trên 500k</option>
                </select>

                <!-- Dropdown Sắp Xếp -->
                <select name="sort" class="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all shadow-2xs cursor-pointer">
                    <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="views_desc" <?= ($sortBy === 'views_desc' || $sortBy === 'views' || $sortBy === 'popular') ? 'selected' : '' ?>>Xem nhiều</option>
                    <option value="downloads_desc" <?= ($sortBy === 'downloads_desc') ? 'selected' : '' ?>>Tải nhiều</option>
                    <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
                    <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
                </select>
            </form>
        </div>

        <!-- 5. Thanh Chuyên Mục Ngang (Horizontal Category Pills với Sequential Delay) -->
        <div id="categoryPills" class="flex items-center gap-2 overflow-x-auto pb-2 no-scrollbar">
            <a href="<?= filter_url(['slug' => null]) ?>" style="--i: 0;" class="category-pill-item <?= empty($catSlug) ? 'bg-slate-900 text-white font-medium px-4 py-2 rounded-full text-sm shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300 hover:text-slate-900 px-4 py-2 rounded-full text-sm whitespace-nowrap' ?> shrink-0">
                Tất cả
            </a>
            <?php foreach ($allCategories as $cIdx => $cat): ?>
                <?php $isCatActive = ($catSlug === $cat['slug']); ?>
                <a href="<?= filter_url(['slug' => $cat['slug']]) ?>" style="--i: <?= $cIdx + 1 ?>;" class="category-pill-item <?= $isCatActive ? 'bg-slate-900 text-white font-medium px-4 py-2 rounded-full text-sm shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300 hover:text-slate-900 px-4 py-2 rounded-full text-sm whitespace-nowrap' ?> shrink-0">
                    <?= xss_clean($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 2 & 3. FULL-WIDTH PRODUCT GRID & PAGINATION AREA (SMOOTH AJAX CONTAINER) -->
    <div id="productContainer" class="space-y-6">
        <?php if (!empty($products)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $idx => $p): ?>
                    <?php
                    $curPrice = !empty($p['sale_price']) && $p['sale_price'] > 0 ? (float)$p['sale_price'] : (float)$p['price'];
                    $discount = (!empty($p['sale_price']) && $p['sale_price'] < $p['price']);
                    $discountPct = $discount ? round((($p['price'] - $p['sale_price']) / $p['price']) * 100) : 0;
                    ?>
                    <!-- Product Card Chuẩn Gumroad / Tailwind UI -->
                    <div class="product-card group bg-white rounded-2xl border border-slate-200/80 overflow-hidden flex flex-col" style="--ci: <?= ($idx % 4) + 1 ?>;">
                        <!-- Phần Ảnh (aspect-[16/10]) -->
                        <a href="<?= BASE_URL ?>/product?id=<?= $p['id'] ?>" class="product-thumb-container aspect-[16/10] bg-slate-100 block">
                            <img src="<?= xss_clean($p['thumbnail']) ?>" alt="<?= xss_clean($p['title']) ?>" class="w-full h-full object-cover" onerror="this.onerror=null;this.src='<?= BASE_URL ?>/assets/images/default-code.jpg';">
                            
                            <!-- Góc trên bên trái ảnh: Badge tên danh mục gọn gàng -->
                            <span class="absolute top-2.5 left-2.5 bg-slate-900/75 backdrop-blur-md text-white text-[11px] font-medium px-2.5 py-1 rounded-lg z-20 truncate max-w-[140px]">
                                <?= xss_clean($p['category_name'] ?? 'Mã Nguồn') ?>
                            </span>

                            <!-- Góc trên bên phải ảnh: Badge giảm giá nếu có -->
                            <?php if ($discount): ?>
                                <span class="absolute top-2.5 right-2.5 bg-rose-500 text-white text-[11px] font-bold px-2 py-1 rounded-lg z-20 shadow-sm">
                                    -<?= $discountPct ?>%
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

                                <!-- 4. Thống kê Lượt xem & Lượt tải với Number Counter -->
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
                                    <?php if ($discount && $p['price'] > 0): ?>
                                        <span class="text-[11px] font-medium text-slate-400 line-through mb-1 whitespace-nowrap">
                                            <?= number_format($p['price'], 0, ',', '.') ?>đ
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[11px] text-transparent mb-1 select-none leading-none whitespace-nowrap" aria-hidden="true">&nbsp;</span>
                                    <?php endif; ?>
                                    <span class="text-[17px] font-extrabold text-indigo-600 tracking-tight whitespace-nowrap">
                                        <?= $curPrice <= 0 ? 'Miễn phí' : number_format($curPrice, 0, ',', '.') . 'đ' ?>
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

            <!-- PHÂN TRANG GỌN GÀNG -->
            <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-center gap-2 pt-6">
                    <?php if ($page > 1): ?>
                        <a href="<?= filter_url(['page' => $page - 1]) ?>" class="pagination-btn px-4 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all active:scale-[0.97] shadow-2xs flex items-center gap-1">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i> Trang trước
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="<?= filter_url(['page' => $i]) ?>" class="pagination-btn w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold transition-all <?= $page === $i ? 'bg-slate-900 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' ?> active:scale-[0.97] shadow-2xs">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= filter_url(['page' => $page + 1]) ?>" class="pagination-btn px-4 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all active:scale-[0.97] shadow-2xs flex items-center gap-1">
                            Trang sau <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Trạng Thái Trống Khi Không Có Sản Phẩm Khớp -->
            <div class="rounded-3xl bg-white p-12 text-center border border-slate-200/80 shadow-sm space-y-4 max-w-md mx-auto my-8">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center text-2xl mx-auto shadow-2xs">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <h3 class="text-base font-bold text-slate-900">Không Tìm Thấy Mã Nguồn Phù Hợp</h3>
                <p class="text-xs text-slate-500 leading-relaxed">Hiện tại không có mã nguồn nào khớp với tiêu chí tìm kiếm hoặc bộ lọc bạn đã chọn.</p>
                <a href="<?= BASE_URL ?>/categories" class="pagination-btn inline-block px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-indigo-600 text-white font-bold text-xs shadow-sm transition-all active:scale-[0.97]">
                    Xóa Bộ Lọc & Xem Tất Cả
                </a>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
// ========================================================
// 4. HIỆU ỨNG ĐẾM SỐ TĂNG DẦN (NUMBER COUNTER - 800MS)
// ========================================================
function animateCounters(root = document) {
    const counters = root.querySelectorAll('.counter-val');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target') || '0', 10);
        if (isNaN(target)) return;
        
        const duration = 800;
        const startTime = performance.now();
        
        function updateCounter(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Cubic ease-out
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

// ========================================================
// 3. HIỆU ỨNG LỌC & SẮP XẾP KHÔNG CẦN TẢI LẠI TRANG (SMOOTH AJAX)
// ========================================================
(function() {
    const productContainer = document.getElementById('productContainer');
    const filterForm       = document.getElementById('filterForm');
    const searchInput      = filterForm ? filterForm.querySelector('input[name="q"]') : null;
    const priceSelect      = filterForm ? filterForm.querySelector('select[name="price"]') : null;
    const sortSelect       = filterForm ? filterForm.querySelector('select[name="sort"]') : null;
    const pillsContainer   = document.getElementById('categoryPills');
    const totalBadge       = document.getElementById('productTotalBadge');

    let isFetching = false;
    let searchDebounceTimer = null;

    async function loadFilteredData(url, pushToHistory = true) {
        if (isFetching || !productContainer) return;
        isFetching = true;

        // 1. Fade out mượt lưới sản phẩm cũ
        productContainer.classList.add('is-loading');

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('Network error');
            const htmlText = await response.text();

            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlText, 'text/html');

            // 2. Chờ 150ms để fade out hoàn tất trước khi thế nội dung mới
            setTimeout(() => {
                const newContainer = doc.getElementById('productContainer');
                if (newContainer) {
                    productContainer.innerHTML = newContainer.innerHTML;
                    productContainer.classList.remove('is-loading');
                    
                    // Kích hoạt lại hiệu ứng đếm số cho các thẻ mới
                    animateCounters(productContainer);
                    
                    // Gắn lại sự kiện cho các nút phân trang
                    attachPaginationEvents();
                }

                // Cập nhật badge tổng số lượng
                const newTotalBadge = doc.getElementById('productTotalBadge');
                if (newTotalBadge && totalBadge) {
                    const newTotal = newTotalBadge.getAttribute('data-target') || '0';
                    totalBadge.setAttribute('data-target', newTotal);
                    animateCounters(totalBadge.parentElement);
                }

                // Cập nhật trạng thái active của các nút danh mục
                const newPills = doc.querySelectorAll('.category-pill-item');
                const currentPills = document.querySelectorAll('.category-pill-item');
                if (newPills.length === currentPills.length) {
                    newPills.forEach((np, idx) => {
                        currentPills[idx].className = np.className;
                    });
                }

                // Đồng bộ lại dropdown & input
                const newPrice = doc.querySelector('select[name="price"]');
                if (newPrice && priceSelect) priceSelect.value = newPrice.value;

                const newSort = doc.querySelector('select[name="sort"]');
                if (newSort && sortSelect) sortSelect.value = newSort.value;

                if (pushToHistory) {
                    window.history.pushState({ url }, '', url);
                }
            }, 150);

        } catch (err) {
            console.error('Filter AJAX error, fallback to page navigation:', err);
            window.location.href = url;
        } finally {
            setTimeout(() => { isFetching = false; }, 250);
        }
    }

    // Gắn sự kiện click danh mục ngang
    if (pillsContainer) {
        pillsContainer.addEventListener('click', function(e) {
            const link = e.target.closest('.category-pill-item');
            if (!link) return;
            e.preventDefault();
            loadFilteredData(link.href);
        });
    }

    // Dropdown Khoảng giá & Sắp xếp tự động gửi AJAX
    if (priceSelect) {
        priceSelect.addEventListener('change', function() {
            submitFormAjax();
        });
    }
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            submitFormAjax();
        });
    }

    // Gõ tìm kiếm tự lọc live với Debounce 350ms
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                submitFormAjax();
            }, 350);
        });
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitFormAjax();
        });
    }

    function submitFormAjax() {
        if (!filterForm) return;
        const formData = new FormData(filterForm);
        const params = new URLSearchParams();
        
        for (const [k, v] of formData.entries()) {
            if (v && v !== 'all' && v !== 'newest') {
                params.set(k, v);
            }
        }

        const query = params.toString();
        const targetUrl = '<?= BASE_URL ?>/categories' + (query ? '?' + query : '');
        loadFilteredData(targetUrl);
    }

    function attachPaginationEvents() {
        if (!productContainer) return;
        const pageLinks = productContainer.querySelectorAll('.pagination-btn');
        pageLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                loadFilteredData(this.href);
                window.scrollTo({
                    top: productContainer.offsetTop - 90,
                    behavior: 'smooth'
                });
            });
        });
    }

    // Lắng nghe Back/Forward của trình duyệt
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.url) {
            loadFilteredData(e.state.url, false);
        } else {
            loadFilteredData(window.location.href, false);
        }
    });

    // Kích hoạt ban đầu
    document.addEventListener('DOMContentLoaded', function() {
        attachPaginationEvents();
        animateCounters();
    });
    if (document.readyState !== 'loading') {
        attachPaginationEvents();
        animateCounters();
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
