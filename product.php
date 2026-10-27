<?php
require_once __DIR__ . '/config/config.php';

$id = (int)($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

$product = null;
if ($db) {
    try {
        if ($id > 0) {
            $stmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug 
                                  FROM products p 
                                  JOIN categories c ON p.category_id = c.id 
                                  WHERE p.id = ? AND p.status = 'active' LIMIT 1");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
        } elseif (!empty($slug)) {
            $stmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug 
                                  FROM products p 
                                  JOIN categories c ON p.category_id = c.id 
                                  WHERE p.slug = ? AND p.status = 'active' LIMIT 1");
            $stmt->execute([$slug]);
            $product = $stmt->fetch();
        }

        if ($product) {
            $clientIP = getClientIP();

            $pvStmt = $db->prepare("INSERT IGNORE INTO product_views (product_id, ip_address) VALUES (?, ?)");
            $pvStmt->execute([$product['id'], $clientIP]);

            if ($pvStmt->rowCount() > 0) {
                $db->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$product['id']]);
                $product['views'] = ((int)($product['views'] ?? 0)) + 1;
            }
        }
    } catch (Exception $e) {}
}

// Fallback demo data nếu chưa nạp database
if (!$product) {
    $product = [
        'id'             => 1,
        'category_id'    => 1,
        'title'          => 'Hệ Thống Website Bán Mã Nguồn Tự Động PHP Thuần PDO',
        'slug'           => 'he-thong-web-ban-ma-nguon-tu-dong-php',
        'category_name'  => 'PHP & Laravel',
        'category_slug'  => 'php-laravel',
        'short_desc'     => 'Hệ thống sàn mua bán source code tự động tích hợp nạp thẻ cào, VietQR tự động, quản trị toàn diện, mã hóa CSRF & PDO.',
        'content'        => '<p class="mb-4">Bộ mã nguồn được xây dựng bằng <strong>PHP 8.x</strong> thuần hướng đối tượng, bảo mật kết nối PDO, giao diện Tailwind CSS hiện đại chuẩn UI/UX.</p><h3 class="text-lg font-bold my-3 text-indigo-600">Tính năng cốt lõi:</h3><ul class="list-disc pl-5 space-y-2 mb-4"><li>Tự động hóa nạp thẻ cào qua Thesieure/Doithe1s chuẩn chữ ký MD5.</li><li>Tự động hóa nạp tiền qua VietQR MBBank/Vietcombank đối soát biến động số dư.</li><li>Bảo vệ chống tấn công SQL Injection bằng Prepared Statements, mã hóa mật khẩu BCRYPT, xác thực CSRF Token.</li><li>Bảng điều khiển Admin với Chart.js thống kê doanh thu, quản trị thành viên, sản phẩm và giao dịch.</li></ul>',
        'price'          => 499000,
        'sale_price'     => 350000,
        'thumbnail'      => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&auto=format&fit=crop&q=80',
        'demo_url'       => 'https://demo.marketcode.vn',
        'download_url'   => 'https://drive.google.com/sample',
        'file_size'      => '25.8 MB',
        'download_count' => 128,
        'views'          => 1420,
        'created_at'     => date('Y-m-d H:i:s')
    ];
}

$pageTitle = $product['title'];
$user = current_user();

// Kiểm tra user hiện tại đã mua sản phẩm này chưa
$alreadyBought = false;
$boughtOrder = null;
if (is_logged() && $db) {
    try {
        $chkOrder = $db->prepare("SELECT id, order_code, download_token FROM orders WHERE user_id = ? AND product_id = ? AND status = 'completed' LIMIT 1");
        $chkOrder->execute([$user['id'], $product['id']]);
        $boughtOrder = $chkOrder->fetch();
        if ($boughtOrder) {
            $alreadyBought = true;
        }
    } catch (Exception $e) {}
}

$currentPrice = (!empty($product['sale_price']) && $product['sale_price'] > 0) ? (float)$product['sale_price'] : (float)$product['price'];
$hasDiscount = (!empty($product['sale_price']) && $product['sale_price'] < $product['price']);
$discountPercent = $hasDiscount ? round((($product['price'] - $product['sale_price']) / $product['price']) * 100) : 0;

// Query các mã nguồn liên quan cùng danh mục (tối đa 4 sản phẩm)
$relatedProducts = [];
if ($db && !empty($product['category_id'])) {
    try {
        $rStmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug 
                               FROM products p 
                               JOIN categories c ON p.category_id = c.id 
                               WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' 
                               ORDER BY p.id DESC LIMIT 4");
        $rStmt->execute([$product['category_id'], $product['id']]);
        $relatedProducts = $rStmt->fetchAll();

        // Nếu cùng danh mục chưa đủ 4, lấy thêm các sản phẩm active khác
        if (count($relatedProducts) < 4) {
            $needed = 4 - count($relatedProducts);
            $excludeIds = array_merge([$product['id']], array_column($relatedProducts, 'id'));
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $fallbackStmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug 
                                          FROM products p 
                                          JOIN categories c ON p.category_id = c.id 
                                          WHERE p.id NOT IN ($placeholders) AND p.status = 'active' 
                                          ORDER BY p.id DESC LIMIT $needed");
            $fallbackStmt->execute($excludeIds);
            $fallbackProducts = $fallbackStmt->fetchAll();
            $relatedProducts = array_merge($relatedProducts, $fallbackProducts);
        }
    } catch (Exception $e) {}
}

// Xử lý danh sách ảnh gallery (Thumbnail gốc + các ảnh phụ)
$galleryImages = [];
if (!empty($product['thumbnail'])) {
    $galleryImages[] = $product['thumbnail'];
}
if (!empty($product['gallery'])) {
    $decodedGallery = json_decode($product['gallery'], true);
    if (is_array($decodedGallery)) {
        foreach ($decodedGallery as $gImg) {
            $gImg = trim((string)$gImg);
            if (!empty($gImg) && !in_array($gImg, $galleryImages)) {
                $galleryImages[] = $gImg;
            }
        }
    } else {
        $splitLines = preg_split('/[\r\n,]+/', $product['gallery']);
        foreach ($splitLines as $gImg) {
            $gImg = trim((string)$gImg);
            if (!empty($gImg) && !in_array($gImg, $galleryImages)) {
                $galleryImages[] = $gImg;
            }
        }
    }
}

// Nếu chưa đủ ảnh phụ, tự động tạo thêm ảnh demo giao diện để hàng gallery LUÔN HIỂN THỊ ĐẦY ĐẶN tối thiểu 4 ảnh
if (count($galleryImages) < 4) {
    $demoGalleryBackups = [
        'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1200&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&auto=format&fit=crop&q=80',
        'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?w=1200&auto=format&fit=crop&q=80'
    ];
    foreach ($demoGalleryBackups as $demoImg) {
        if (count($galleryImages) >= 4) break;
        if (!in_array($demoImg, $galleryImages)) {
            $galleryImages[] = $demoImg;
        }
    }
}

// Chuẩn hóa đường dẫn ảnh trong gallery (hỗ trợ cả link tuyệt đối và file upload cục bộ)
$galleryImages = array_map(function($src) {
    if (!empty($src) && !str_starts_with($src, 'http') && !str_starts_with($src, '/')) {
        return BASE_URL . '/' . ltrim($src, '/');
    }
    return $src;
}, $galleryImages);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
/* Hiệu ứng chuyển động cho thẻ sản phẩm liên quan */
.product-card {
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs text-slate-500 overflow-x-auto whitespace-nowrap">
        <a href="<?= BASE_URL ?>/" class="hover:text-indigo-600 transition-colors">Trang Chủ</a>
        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
        <a href="<?= BASE_URL ?>/categories" class="hover:text-indigo-600 transition-colors">Kho Mã Nguồn</a>
        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
        <a href="<?= BASE_URL ?>/categories?slug=<?= urlencode($product['category_slug'] ?? '') ?>" class="hover:text-indigo-600 font-medium text-indigo-600 transition-colors">
            <?= xss_clean($product['category_name']) ?>
        </a>
        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
        <span class="text-slate-800 truncate max-w-xs font-semibold"><?= xss_clean($product['title']) ?></span>
    </nav>

    <!-- 1. KHỐI ĐẦU TRANG: 2 CỘT CÂN XỨNG CAO BẰNG NHAU (ITEMS-STRETCH) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
        <!-- CỘT TRÁI (lg:col-span-7): ẢNH THUMBNAIL LỚN + HÀNG ẢNH PHỤ CÂN BẰNG CHIỀU CAO -->
        <div class="lg:col-span-7 flex flex-col justify-between gap-3.5">
            <!-- Phần trên: Ảnh Chính Lớn (flex-1 min-h-[320px]) -->
            <div class="flex-1 min-h-[320px] relative rounded-3xl overflow-hidden border border-slate-200/80 bg-slate-900 shadow-sm group cursor-pointer" onclick="openLightbox(currentGalleryIndex)" title="Click để phóng to ảnh toàn màn hình">
                <img id="mainProductImage" src="<?= xss_clean($galleryImages[0] ?? $product['thumbnail']) ?>" alt="<?= xss_clean($product['title']) ?>" class="w-full h-full object-cover transition-all duration-300">
                
                <?php if ($hasDiscount): ?>
                    <span class="absolute top-5 right-5 px-3 py-1 rounded-xl bg-rose-500 text-white font-extrabold text-xs shadow-md z-10 pointer-events-none">
                        -<?= $discountPercent ?>%
                    </span>
                <?php endif; ?>

                <!-- Nút Phóng To Lightbox -->
                <button type="button" onclick="event.stopPropagation(); openLightbox(currentGalleryIndex)" class="absolute bottom-5 right-5 w-10 h-10 rounded-xl bg-slate-900/70 hover:bg-slate-900 backdrop-blur-md text-white flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 shadow-md cursor-pointer z-10" title="Phóng to ảnh (Lightbox)">
                    <i class="fa-solid fa-expand text-sm"></i>
                </button>
            </div>

            <!-- Phần dưới: Hàng Ảnh Phụ (shrink-0 grid grid-cols-4 sm:grid-cols-5 gap-3) -->
            <div class="shrink-0 grid grid-cols-4 sm:grid-cols-5 gap-2.5 sm:gap-3" id="galleryThumbnailsContainer">
                <?php foreach ($galleryImages as $idx => $img): ?>
                    <button type="button" onclick="changeMainImage('<?= xss_clean($img) ?>', this, <?= $idx ?>)" onmouseenter="changeMainImage('<?= xss_clean($img) ?>', this, <?= $idx ?>)" class="gallery-thumb-item <?= $idx === 0 ? 'border-indigo-600 ring-2 ring-indigo-500/20 opacity-100' : 'border-slate-200 opacity-70 hover:opacity-100 hover:border-indigo-300' ?> h-16 sm:h-20 rounded-2xl overflow-hidden border-2 cursor-pointer transition-all bg-slate-900 relative p-0">
                        <img src="<?= xss_clean($img) ?>" alt="Gallery <?= $idx + 1 ?>" class="w-full h-full object-cover pointer-events-none" onerror="this.onerror=null;this.src='<?= BASE_URL ?>/assets/images/default-code.jpg';">
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CỘT PHẢI (lg:col-span-5): THÔNG TIN MÃ NGUỒN, GIÁ & MUA HÀNG (SÚC TÍCH, CÂN XỨNG) -->
        <div class="lg:col-span-5 flex flex-col justify-between bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-7 shadow-sm space-y-5">
            <!-- Phần 1: Badge Danh mục, Tiêu đề H1, Thống kê & Mô tả ngắn -->
            <div>
                <!-- Badge Danh mục -->
                <span class="bg-indigo-50 border border-indigo-200/70 text-indigo-700 text-xs font-bold px-3 py-1 rounded-lg inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-layer-group text-[11px]"></i>
                    <span><?= xss_clean($product['category_name']) ?></span>
                </span>

                <!-- Tiêu đề lớn H1 -->
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-snug mt-2.5">
                    <?= xss_clean($product['title']) ?>
                </h1>

                <!-- Hàng thống kê -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 text-xs text-slate-500 font-medium mt-3">
                    <span class="flex items-center gap-1.5 text-indigo-700 font-bold bg-indigo-50 border border-indigo-200/60 px-2.5 py-1 rounded-lg">
                        <i class="fa-regular fa-eye text-indigo-600"></i>
                        <span><span class="counter-val" data-target="<?= (int)($product['views'] ?? $product['view_count'] ?? 0) ?>"><?= number_format((int)($product['views'] ?? $product['view_count'] ?? 0)) ?></span> lượt xem</span>
                    </span>
                    <span class="flex items-center gap-1.5 text-emerald-700 font-bold bg-emerald-50 border border-emerald-200/60 px-2.5 py-1 rounded-lg">
                        <i class="fa-solid fa-download text-emerald-600"></i>
                        <span><span class="counter-val" data-target="<?= (int)$product['download_count'] ?>"><?= number_format((int)$product['download_count']) ?></span> lượt tải</span>
                    </span>
                    <span class="flex items-center gap-1.5 text-slate-500 bg-slate-50 border border-slate-200/70 px-2.5 py-1 rounded-lg">
                        <i class="fa-regular fa-calendar-check text-slate-400"></i>
                        <span>Cập nhật: <?= date('d/m/Y', strtotime($product['updated_at'] ?? $product['created_at'])) ?></span>
                    </span>
                </div>

                <!-- 2 dòng mô tả ngắn gọn -->
                <?php if (!empty($product['short_desc'])): ?>
                    <p class="text-slate-500 text-sm mt-3.5 line-clamp-3 leading-relaxed">
                        <?= xss_clean($product['short_desc']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Phần 2: Khối Giá Bán, Số Dư & 2 Nút Hành Động -->
            <div class="space-y-4 pt-3 border-t border-slate-100">
                <!-- Giá Bán -->
                <div>
                    <span class="text-xs font-semibold text-slate-500">Giá sở hữu trọn bộ mã nguồn:</span>
                    <div class="flex items-baseline gap-2.5 mt-1 flex-wrap">
                        <span class="text-3xl font-extrabold text-indigo-600 tracking-tight">
                            <?= $currentPrice <= 0 ? 'Miễn phí' : number_format($currentPrice, 0, ',', '.') . 'đ' ?>
                        </span>
                        <?php if ($hasDiscount && $product['price'] > 0): ?>
                            <span class="text-sm text-slate-400 line-through">
                                <?= number_format($product['price'], 0, ',', '.') ?>đ
                            </span>
                            <span class="px-2 py-0.5 rounded-lg bg-rose-50 text-rose-600 border border-rose-200 text-xs font-black">
                                -<?= $discountPercent ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Thanh Số Dư -->
                <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">Số dư hiện tại:</span>
                    <?php if (is_logged()): ?>
                        <?php $userBal = (float)($user['balance'] ?? 0); ?>
                        <div class="flex items-center gap-2">
                            <strong class="text-emerald-600 font-bold"><?= number_format($userBal, 0, ',', '.') ?>đ</strong>
                            <?php if ($userBal < $currentPrice): ?>
                                <a href="<?= BASE_URL ?>/deposit?tab=bank" class="px-2 py-0.5 rounded-lg bg-indigo-50 hover:bg-indigo-600 text-indigo-600 hover:text-white font-bold text-[11px] transition-colors border border-indigo-200/60" title="Nạp thêm tiền vào tài khoản">
                                    + Nạp thêm
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '/product?id=' . $product['id']) ?>" class="text-indigo-600 hover:text-indigo-700 font-bold hover:underline">
                            Đăng nhập ngay
                        </a>
                    <?php endif; ?>
                </div>

                <!-- 2 Nút Hành Động (Mua Ngay + Live Demo ngang hàng) -->
                <div class="<?= !empty($product['demo_url']) ? 'grid grid-cols-1 sm:grid-cols-2 gap-3' : 'space-y-2' ?>">
                    <?php if ($alreadyBought): ?>
                        <a href="<?= BASE_URL ?>/download?order_id=<?= $boughtOrder['id'] ?>" class="btn-shimmer w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2 transition-all active:scale-95 text-center">
                            <i class="fa-solid fa-cloud-arrow-down text-base"></i>
                            <span>Tải Mã Nguồn</span>
                        </a>
                    <?php else: ?>
                        <button type="button" onclick="confirmBuyProduct(<?= (int)$product['id'] ?>)" class="btn-shimmer w-full py-3.5 rounded-2xl bg-gradient-to-r from-indigo-600 via-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold text-sm shadow-md shadow-indigo-500/25 flex items-center justify-center gap-2 transition-all hover:scale-[1.01] active:scale-95 cursor-pointer">
                            <i class="fa-solid fa-cart-shopping text-base"></i>
                            <span>Mua Ngay</span>
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($product['demo_url'])): ?>
                        <a href="<?= xss_clean($product['demo_url']) ?>" target="_blank" class="w-full py-3.5 rounded-2xl border-2 border-indigo-200 hover:border-indigo-500 text-indigo-600 font-bold text-xs sm:text-sm flex items-center justify-center gap-2 transition-all active:scale-95 bg-white text-center">
                            <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                            <span>Xem Live Demo</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Phần 3: Modern Trust Badges Grid (Khung cam kết uy tín cao cấp) -->
            <div class="border-t border-slate-100 pt-5 mt-5 space-y-2.5">
                <!-- Thẻ 1: Tải Ngay Tự Động -->
                <div class="bg-slate-50/80 hover:bg-amber-50/40 border border-slate-200/60 hover:border-amber-200/80 rounded-2xl p-3 sm:p-3.5 transition-all duration-200 flex items-center gap-3.5 group">
                    <div class="w-10 h-10 rounded-xl bg-white shadow-xs border border-slate-200/70 flex items-center justify-center shrink-0 text-amber-500 group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5 text-amber-500 fill-amber-500/10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-xs font-bold text-slate-800 leading-tight group-hover:text-amber-700 transition-colors">
                            Tải Ngay Tự Động
                        </h4>
                        <p class="text-[11px] text-slate-500 mt-0.5 leading-snug">
                            Nhận link Google Drive / Code trong 30 giây
                        </p>
                    </div>
                </div>

                <!-- Thẻ 2: Cam Kết Mã Sạch 100% -->
                <div class="bg-slate-50/80 hover:bg-indigo-50/40 border border-slate-200/60 hover:border-indigo-200/80 rounded-2xl p-3 sm:p-3.5 transition-all duration-200 flex items-center gap-3.5 group">
                    <div class="w-10 h-10 rounded-xl bg-white shadow-xs border border-slate-200/70 flex items-center justify-center shrink-0 text-indigo-600 group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5 text-indigo-600 fill-indigo-600/10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <path d="m9 12 2 2 4-4"></path>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-xs font-bold text-slate-800 leading-tight group-hover:text-indigo-700 transition-colors">
                            Cam Kết Mã Sạch 100%
                        </h4>
                        <p class="text-[11px] text-slate-500 mt-0.5 leading-snug">
                            Đã kiểm duyệt, không chứa mã độc hay backdoor
                        </p>
                    </div>
                </div>

                <!-- Thẻ 3: Bảo Hành & Hỗ Trợ Trọn Đời -->
                <div class="bg-slate-50/80 hover:bg-emerald-50/40 border border-slate-200/60 hover:border-emerald-200/80 rounded-2xl p-3 sm:p-3.5 transition-all duration-200 flex items-center gap-3.5 group">
                    <div class="w-10 h-10 rounded-xl bg-white shadow-xs border border-slate-200/70 flex items-center justify-center shrink-0 text-emerald-600 group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5 text-emerald-600 fill-emerald-600/10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                            <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-xs font-bold text-slate-800 leading-tight group-hover:text-emerald-700 transition-colors">
                            Bảo Hành & Hỗ Trợ Trọn Đời
                        </h4>
                        <p class="text-[11px] text-slate-500 mt-0.5 leading-snug">
                            Hỗ trợ setup, fix lỗi qua Ultraviewer / Zalo
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. KHỐI MÔ TẢ CHI TIẾT & HƯỚNG DẪN CÀI ĐẶT (RỘNG 100% CHIỀU NGANG Ở BÊN DƯỚI) -->
    <div class="w-full bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="border-b border-slate-100 pb-4 flex items-center gap-2.5 text-lg font-bold text-slate-900">
            <i class="fa-solid fa-file-lines text-indigo-600"></i>
            <span>Mô Tả Chi Tiết & Hướng Dẫn Cài Đặt</span>
        </div>

        <!-- Rich text content -->
        <div class="prose max-w-none text-slate-700 leading-relaxed text-sm sm:text-base space-y-4">
            <?= $product['content'] ?>
        </div>
    </div>

    <!-- 3. MÃ NGUỒN LIÊN QUAN (ĐỒNG BỘ 100% LƯỚI CATEGORIES) -->
    <?php if (!empty($relatedProducts)): ?>
        <div class="w-full pt-8 border-t border-slate-200/80 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                        Mã Nguồn Cùng Danh Mục
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Các sản phẩm liên quan trong chuyên mục <?= xss_clean($product['category_name']) ?></p>
                </div>
                <a href="<?= BASE_URL ?>/categories?slug=<?= urlencode($product['category_slug'] ?? '') ?>" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1.5 transition-colors">
                    <span>Xem tất cả</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($relatedProducts as $idx => $rel): ?>
                    <?php 
                        $relPrice = (!empty($rel['sale_price']) && $rel['sale_price'] > 0) ? (float)$rel['sale_price'] : (float)$rel['price'];
                        $relHasDiscount = (!empty($rel['sale_price']) && $rel['sale_price'] < $rel['price']);
                        $relDiscountPct = $relHasDiscount ? round((($rel['price'] - $rel['sale_price']) / $rel['price']) * 100) : 0;
                    ?>
                    <!-- Product Card chuẩn Gumroad đồng bộ với Categories -->
                    <div class="product-card group bg-white rounded-2xl border border-slate-200/80 overflow-hidden flex flex-col" style="--ci: <?= ($idx % 4) + 1 ?>;">
                        <!-- Phần Ảnh (aspect-[16/10]) -->
                        <a href="<?= BASE_URL ?>/product?id=<?= $rel['id'] ?>" class="product-thumb-container aspect-[16/10] bg-slate-100 block">
                            <img src="<?= xss_clean($rel['thumbnail']) ?>" alt="<?= xss_clean($rel['title']) ?>" class="w-full h-full object-cover" onerror="this.onerror=null;this.src='<?= BASE_URL ?>/assets/images/default-code.jpg';">
                            
                            <!-- Góc trên bên trái ảnh: Badge tên danh mục gọn gàng -->
                            <span class="absolute top-2.5 left-2.5 bg-slate-900/75 backdrop-blur-md text-white text-[11px] font-medium px-2.5 py-1 rounded-lg z-20 truncate max-w-[140px]">
                                <?= xss_clean($rel['category_name'] ?? 'Mã Nguồn') ?>
                            </span>

                            <!-- Góc trên bên phải ảnh: Badge giảm giá nếu có -->
                            <?php if ($relHasDiscount): ?>
                                <span class="absolute top-2.5 right-2.5 bg-rose-500 text-white text-[11px] font-bold px-2 py-1 rounded-lg z-20 shadow-sm">
                                    -<?= $relDiscountPct ?>%
                                </span>
                            <?php endif; ?>
                        </a>

                        <!-- Phần Thân Thẻ -->
                        <div class="p-5 flex flex-col flex-1 justify-between">
                            <div>
                                <!-- Tiêu đề (gọn gàng line-clamp-1) -->
                                <h3 class="text-[15px] font-bold text-slate-900 group-hover:text-indigo-600 transition-colors line-clamp-1">
                                    <a href="<?= BASE_URL ?>/product?id=<?= $rel['id'] ?>" title="<?= xss_clean($rel['title']) ?>">
                                        <?= xss_clean($rel['title']) ?>
                                    </a>
                                </h3>

                                <!-- Mô tả 1 dòng mảnh -->
                                <?php if (!empty($rel['short_desc'])): ?>
                                    <p class="text-xs text-slate-500 line-clamp-1 mt-1">
                                        <?= xss_clean($rel['short_desc']) ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Thống kê Lượt xem & Lượt tải với Number Counter -->
                                <div class="flex items-center gap-4 mt-3 text-xs text-slate-500 font-medium">
                                    <span class="inline-flex items-center gap-1.5" title="Lượt xem">
                                        <i class="fa-regular fa-eye text-slate-400"></i>
                                        <span class="counter-val" data-target="<?= (int)($rel['views'] ?? $rel['view_count'] ?? 0) ?>"><?= number_format((int)($rel['views'] ?? $rel['view_count'] ?? 0)) ?></span> lượt xem
                                    </span>
                                    <span class="inline-flex items-center gap-1.5" title="Lượt tải">
                                        <i class="fa-solid fa-download text-slate-400"></i>
                                        <span class="counter-val" data-target="<?= (int)$rel['download_count'] ?>"><?= number_format((int)$rel['download_count']) ?></span> lượt tải
                                    </span>
                                </div>
                            </div>

                            <!-- Phần Đáy Thẻ (Giá 2 tầng & Nút Hành Động) -->
                            <div class="mt-4 pt-3.5 border-t border-slate-100 flex items-center justify-between gap-3">
                                <!-- Bên trái: Khối Giá 2 tầng xếp dọc -->
                                <div class="flex flex-col justify-center min-w-0 leading-none">
                                    <?php if ($relHasDiscount && $rel['price'] > 0): ?>
                                        <span class="text-[11px] font-medium text-slate-400 line-through mb-1 whitespace-nowrap">
                                            <?= number_format($rel['price'], 0, ',', '.') ?>đ
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[11px] text-transparent mb-1 select-none leading-none whitespace-nowrap" aria-hidden="true">&nbsp;</span>
                                    <?php endif; ?>
                                    <span class="text-[17px] font-extrabold text-indigo-600 tracking-tight whitespace-nowrap">
                                        <?= $relPrice <= 0 ? 'Miễn phí' : number_format($relPrice, 0, ',', '.') . 'đ' ?>
                                    </span>
                                </div>

                                <!-- Bên phải: Nút bấm gọn gàng không rớt dòng -->
                                <a href="<?= BASE_URL ?>/product?id=<?= $rel['id'] ?>" class="product-detail-btn shrink-0 bg-indigo-50 hover:bg-indigo-600 text-indigo-600 hover:text-white text-xs font-bold px-3.5 py-2.5 rounded-xl transition-all duration-300 inline-flex items-center gap-1.5 whitespace-nowrap">
                                    <span>Chi tiết</span>
                                    <i class="product-detail-arrow fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Fullscreen Image Lightbox -->
<div id="imageLightboxModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-950/90 backdrop-blur-md transition-all duration-300 select-none" onclick="closeLightbox()">
    <div class="relative max-w-6xl max-h-[92vh] w-full flex flex-col items-center justify-center" onclick="event.stopPropagation()">
        <!-- Thanh công cụ góc trên: bộ đếm và nút đóng -->
        <div class="w-full flex items-center justify-between text-white text-xs font-semibold px-2 mb-3">
            <span id="lightboxCounter" class="bg-slate-800/80 px-3.5 py-1.5 rounded-xl border border-slate-700 tracking-wider">
                1 / <?= count($galleryImages) ?>
            </span>
            <button type="button" onclick="closeLightbox()" class="w-9 h-9 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-white flex items-center justify-center transition-all cursor-pointer border border-slate-700" title="Đóng Lightbox (Phím Esc)">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Khung ảnh chính trong Lightbox -->
        <div class="relative w-full flex items-center justify-center">
            <img id="lightboxMainImg" src="<?= xss_clean($galleryImages[0] ?? $product['thumbnail']) ?>" alt="Zoomed preview" class="max-w-full max-h-[78vh] object-contain rounded-2xl shadow-2xl transition-all duration-200">

            <!-- Nút Prev -->
            <button type="button" onclick="prevLightboxImage()" class="absolute left-1 sm:-left-5 w-11 h-11 rounded-full bg-slate-900/80 hover:bg-indigo-600 text-white flex items-center justify-center transition-all shadow-lg border border-slate-700 cursor-pointer" title="Ảnh trước (Phím mũi tên trái)">
                <i class="fa-solid fa-chevron-left text-sm"></i>
            </button>

            <!-- Nút Next -->
            <button type="button" onclick="nextLightboxImage()" class="absolute right-1 sm:-right-5 w-11 h-11 rounded-full bg-slate-900/80 hover:bg-indigo-600 text-white flex items-center justify-center transition-all shadow-lg border border-slate-700 cursor-pointer" title="Ảnh kế tiếp (Phím mũi tên phải)">
                <i class="fa-solid fa-chevron-right text-sm"></i>
            </button>
        </div>
    </div>
</div>

<script>
    const galleryList = <?= json_encode($galleryImages, JSON_UNESCAPED_SLASHES) ?>;
    let currentGalleryIndex = 0;

    // Đổi ảnh chính lớn khi click/hover ảnh phụ
    function changeMainImage(src, element, idx) {
        if (typeof idx === 'number') {
            currentGalleryIndex = idx;
        } else {
            const foundIdx = galleryList.indexOf(src);
            if (foundIdx !== -1) currentGalleryIndex = foundIdx;
        }

        const main = document.getElementById('mainProductImage');
        if (main && main.src !== src) {
            main.style.opacity = '0.35';
            setTimeout(() => {
                main.src = src;
                main.style.opacity = '1';
            }, 120);
        }

        // Cập nhật viền tím cho thumbnail đang chọn
        const thumbs = document.querySelectorAll('.gallery-thumb-item');
        thumbs.forEach(el => {
            el.classList.remove('border-indigo-600', 'ring-2', 'ring-indigo-500/20', 'opacity-100');
            el.classList.add('border-slate-200', 'opacity-70');
        });
        if (element) {
            element.classList.remove('border-slate-200', 'opacity-70');
            element.classList.add('border-indigo-600', 'ring-2', 'ring-indigo-500/20', 'opacity-100');
        }
    }

    // Mở Lightbox xem ảnh lớn
    function openLightbox(idx = 0) {
        currentGalleryIndex = (idx >= 0 && idx < galleryList.length) ? idx : 0;
        updateLightboxUI();
        const modal = document.getElementById('imageLightboxModal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }

    // Đóng Lightbox
    function closeLightbox() {
        const modal = document.getElementById('imageLightboxModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    function updateLightboxUI() {
        const lbImg = document.getElementById('lightboxMainImg');
        const lbCounter = document.getElementById('lightboxCounter');
        if (lbImg && galleryList[currentGalleryIndex]) {
            lbImg.style.opacity = '0.4';
            lbImg.src = galleryList[currentGalleryIndex];
            setTimeout(() => { lbImg.style.opacity = '1'; }, 100);
        }
        if (lbCounter) {
            lbCounter.textContent = (currentGalleryIndex + 1) + ' / ' + galleryList.length;
        }
    }

    function prevLightboxImage() {
        if (galleryList.length <= 1) return;
        currentGalleryIndex = (currentGalleryIndex - 1 + galleryList.length) % galleryList.length;
        updateLightboxUI();
        syncMainImageFromIndex(currentGalleryIndex);
    }

    function nextLightboxImage() {
        if (galleryList.length <= 1) return;
        currentGalleryIndex = (currentGalleryIndex + 1) % galleryList.length;
        updateLightboxUI();
        syncMainImageFromIndex(currentGalleryIndex);
    }

    function syncMainImageFromIndex(idx) {
        const thumbs = document.querySelectorAll('.gallery-thumb-item');
        if (thumbs[idx]) {
            changeMainImage(galleryList[idx], thumbs[idx], idx);
        }
    }

    // Phím tắt chuyển ảnh bàn phím
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('imageLightboxModal');
        if (modal && !modal.classList.contains('hidden')) {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') prevLightboxImage();
            if (e.key === 'ArrowRight') nextLightboxImage();
        }
    });

    // Number Counter Animation
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

    async function confirmBuyProduct(productId) {
        <?php if (!is_logged()): ?>
            Swal.fire({
                title: 'Yêu Cầu Đăng Nhập',
                text: 'Bạn cần đăng nhập tài khoản để mua và tải mã nguồn!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Đăng nhập ngay',
                cancelButtonText: 'Để sau',
                confirmButtonColor: '#4f46e5'
            }).then((res) => {
                if (res.isConfirmed) {
                    window.location.href = '<?= BASE_URL ?>/login?redirect=' + encodeURIComponent(window.location.href);
                }
            });
            return;
        <?php endif; ?>

        const result = await Swal.fire({
            title: 'Xác Nhận Mua Mã Nguồn',
            html: `Bạn có chắc muốn thanh toán <b><?= number_format($currentPrice, 0, ',', '.') ?>đ</b> để sở hữu mã nguồn này?<br><small class="text-slate-500">Số dư hiện tại: <?= number_format($user['balance'] ?? 0, 0, ',', '.') ?>đ</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Xác nhận mua ngay',
            cancelButtonText: 'Hủy bỏ',
            confirmButtonColor: '#4f46e5'
        });

        if (!result.isConfirmed) return;

        Swal.showLoading();

        try {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('csrf_token', '<?= csrf_token() ?>');

            const res = await fetch('<?= BASE_URL ?>/api/buy_code.php', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Mua Thành Công!',
                    text: data.message,
                    confirmButtonText: 'Tải Xuống Mã Nguồn Ngay',
                    confirmButtonColor: '#4f46e5'
                }).then(() => {
                    window.location.href = data.download_url;
                });
            } else {
                if (data.insufficient_balance) {
                    Swal.fire({
                        title: 'Số Dư Không Đủ!',
                        text: data.message,
                        icon: 'error',
                        showCancelButton: true,
                        confirmButtonText: 'Nạp tiền VietQR ngay',
                        cancelButtonText: 'Đóng',
                        confirmButtonColor: '#10b981'
                    }).then((r) => {
                        if (r.isConfirmed) {
                            window.location.href = '<?= BASE_URL ?>/deposit?tab=bank';
                        }
                    });
                } else {
                    Swal.fire('Lỗi Thanh Toán', data.message || 'Giao dịch không thành công!', 'error');
                }
            }
        } catch (err) {
            Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ. Vui lòng thử lại!', 'error');
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
