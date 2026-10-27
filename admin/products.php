<?php
$pageTitle = "Quản Lý Mã Nguồn (Source Code)";
require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$error = '';

// Lấy danh sách danh mục để đổ vào dropdown
$categories = [];
if ($db) {
    try {
        $categories = $db->query("SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
    } catch (Exception $e) {}
}

// Tự động đảm bảo cột gallery tồn tại trong bảng products (không phá vỡ CSDL)
if ($db) {
    try {
        $chkCol = $db->query("SHOW COLUMNS FROM products LIKE 'gallery'");
        if (!$chkCol || $chkCol->rowCount() === 0) {
            $db->exec("ALTER TABLE products ADD COLUMN gallery TEXT DEFAULT NULL");
        }
    } catch (Exception $e) {}
}

// Xử lý Thêm / Sửa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_product'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token không hợp lệ!';
    } else {
        $prodId       = (int)($_POST['product_id'] ?? 0);
        $categoryId   = (int)($_POST['category_id'] ?? 0);
        $title        = trim($_POST['title'] ?? '');
        $slug         = trim($_POST['slug'] ?? '');
        $shortDesc    = trim($_POST['short_desc'] ?? '');
        $content      = trim($_POST['content'] ?? '');
        $price        = (float)($_POST['price'] ?? 0);
        $salePrice    = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
        $thumbnail    = trim($_POST['thumbnail'] ?? '');
        $demoUrl      = trim($_POST['demo_url'] ?? '');
        $downloadUrl  = trim($_POST['download_url'] ?? '');
        $status       = in_array($_POST['status'] ?? '', ['active', 'hidden', 'draft']) ? $_POST['status'] : 'active';

        // Xử lý Danh Sách Ảnh Phụ Demo (Upload Trực Tiếp từ Máy & Giữ Ảnh Cũ)
        $finalGallery = [];

        // 1. Giữ lại các ảnh phụ cũ đã có (nếu chỉnh sửa sản phẩm)
        if (!empty($_POST['existing_gallery']) && is_array($_POST['existing_gallery'])) {
            foreach ($_POST['existing_gallery'] as $oldImg) {
                $oldImg = trim($oldImg);
                if (!empty($oldImg) && !in_array($oldImg, $finalGallery)) {
                    $finalGallery[] = $oldImg;
                }
            }
        }

        // 2. Upload các file ảnh phụ mới được chọn từ máy tính
        if (!empty($_FILES['secondary_images']['name']) && is_array($_FILES['secondary_images']['name'])) {
            $uploadDir = ROOT_PATH . '/assets/uploads/products';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $fileCount = count($_FILES['secondary_images']['name']);

            for ($i = 0; $i < $fileCount; $i++) {
                // Tối đa 5 ảnh phụ tổng cộng
                if (count($finalGallery) >= 5) {
                    break;
                }

                $errCode  = $_FILES['secondary_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                $tmpName  = $_FILES['secondary_images']['tmp_name'][$i] ?? '';
                $fileName = $_FILES['secondary_images']['name'][$i] ?? '';
                $fileSize = $_FILES['secondary_images']['size'][$i] ?? 0;

                if ($errCode === UPLOAD_ERR_OK && is_uploaded_file($tmpName) && $fileSize > 0) {
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExtensions)) {
                        // Đặt tên file ngẫu nhiên an toàn, chống trùng lặp
                        $newFileName = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $destPath = $uploadDir . '/' . $newFileName;
                        if (move_uploaded_file($tmpName, $destPath)) {
                            $savedPath = 'assets/uploads/products/' . $newFileName;
                            $finalGallery[] = $savedPath;
                        }
                    }
                }
            }
        }

        $galleryJson = !empty($finalGallery) ? json_encode(array_values($finalGallery), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        if (empty($slug)) {
            $slug = slugify($title);
        }

        if (empty($title) || $categoryId <= 0 || $price < 0 || empty($downloadUrl)) {
            $error = 'Vui lòng nhập đầy đủ tiêu đề, danh mục, giá tiền và link tải source code!';
        } else {
            try {
                if ($prodId > 0) {
                    $stmt = $db->prepare("UPDATE products SET category_id = ?, title = ?, slug = ?, short_desc = ?, content = ?, price = ?, sale_price = ?, thumbnail = ?, gallery = ?, demo_url = ?, download_url = ?, status = ? WHERE id = ?");
                    $stmt->execute([$categoryId, $title, $slug, $shortDesc, $content, $price, $salePrice, $thumbnail ?: 'assets/images/default-thumbnail.jpg', $galleryJson, $demoUrl, $downloadUrl, $status, $prodId]);
                    $message = 'Cập nhật mã nguồn thành công!';
                } else {
                    $stmt = $db->prepare("INSERT INTO products (category_id, title, slug, short_desc, content, price, sale_price, thumbnail, gallery, demo_url, download_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$categoryId, $title, $slug, $shortDesc, $content, $price, $salePrice, $thumbnail ?: 'assets/images/default-thumbnail.jpg', $galleryJson, $demoUrl, $downloadUrl, $status]);
                    $message = 'Đăng mã nguồn mới thành công!';
                }
            } catch (Exception $e) {
                $error = 'Lỗi lưu mã nguồn (có thể trùng Slug): ' . $e->getMessage();
            }
        }
    }
}

// Xử lý Xóa sản phẩm
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    try {
        $chkOrder = $db->prepare("SELECT COUNT(*) FROM orders WHERE product_id = ?");
        $chkOrder->execute([$delId]);
        if ($chkOrder->fetchColumn() > 0) {
            $error = 'Không thể xóa mã nguồn này vì đã có đơn hàng của khách mua! Bạn có thể chuyển trạng thái sang "Ẩn" để ngừng bán.';
        } else {
            $delStmt = $db->prepare("DELETE FROM products WHERE id = ?");
            $delStmt->execute([$delId]);
            $message = 'Xóa mã nguồn thành công!';
        }
    } catch (Exception $e) {
        $error = 'Lỗi khi xóa mã nguồn: ' . $e->getMessage();
    }
}

// Thống kê nhanh
$totalCount  = 0;
$activeCount = 0;
$hiddenCount = 0;
if ($db) {
    try {
        $totalCount  = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $activeCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
        $hiddenCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE status IN ('hidden', 'draft')")->fetchColumn();
    } catch (Exception $e) {}
}

// Bộ lọc & Tìm kiếm nhanh
$searchQ      = trim($_GET['q'] ?? '');
$filterCat    = (int)($_GET['category_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($searchQ)) {
    $where[] = "(p.title LIKE ? OR p.slug LIKE ?)";
    $params[] = "%$searchQ%";
    $params[] = "%$searchQ%";
}

if ($filterCat > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $filterCat;
}

if (!empty($filterStatus) && in_array($filterStatus, ['active', 'hidden', 'draft'])) {
    $where[] = "p.status = ?";
    $params[] = $filterStatus;
}

$whereSql = implode(' AND ', $where);

// Lấy danh sách sản phẩm theo bộ lọc
$products = [];
if ($db) {
    try {
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                JOIN categories c ON p.category_id = c.id 
                WHERE $whereSql 
                ORDER BY p.id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="space-y-6">
    <!-- Tiêu đề & Nút thêm mới -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2.5">
                <i class="fa-solid fa-code text-indigo-600"></i>
                <span>Quản Lý Mã Nguồn</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Thêm mới, sửa giá, cập nhật link tải và trạng thái của các sản phẩm source code trên sàn giao dịch.
            </p>
        </div>
        <button type="button" onclick="openProductModal()" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-500/20 flex items-center gap-2 active:scale-95 transition-all">
            <i class="fa-solid fa-plus"></i>
            <span>Đăng Bán Mã Nguồn Mới</span>
        </button>
    </div>

    <!-- Thông báo Alert -->
    <?php if (!empty($message)): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-500"></i>
            <span><?= xss_clean($message) ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-rose-500"></i>
            <span><?= xss_clean($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- THANH CÔNG CỤ NHANH: TÌM KIẾM, LỌC & THỐNG KÊ -->
    <div class="rounded-2xl bg-white p-3.5 sm:p-4 border border-slate-200/80 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Thống kê nhanh dạng Pill -->
        <div class="flex items-center gap-2 shrink-0 overflow-x-auto pb-1 md:pb-0 text-xs">
            <a href="<?= BASE_URL ?>/admin/products" class="px-3 py-1.5 rounded-xl font-bold border transition-colors flex items-center gap-1.5 <?= empty($filterStatus) ? 'bg-indigo-50 text-indigo-600 border-indigo-200' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-slate-300' ?>">
                <span>Tất cả:</span>
                <strong class="font-black"><?= $totalCount ?></strong>
            </a>
            <a href="<?= BASE_URL ?>/admin/products?status=active" class="px-3 py-1.5 rounded-xl font-bold border transition-colors flex items-center gap-1.5 <?= $filterStatus === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-emerald-300' ?>">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Đang bán:</span>
                <strong class="font-black text-emerald-600"><?= $activeCount ?></strong>
            </a>
            <a href="<?= BASE_URL ?>/admin/products?status=hidden" class="px-3 py-1.5 rounded-xl font-bold border transition-colors flex items-center gap-1.5 <?= ($filterStatus === 'hidden' || $filterStatus === 'draft') ? 'bg-slate-200 text-slate-800 border-slate-300' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-slate-300' ?>">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                <span>Đang ẩn:</span>
                <strong class="font-black text-slate-600"><?= $hiddenCount ?></strong>
            </a>
        </div>

        <!-- Form Tìm kiếm & Lọc theo Danh mục -->
        <form method="GET" action="<?= BASE_URL ?>/admin/products" class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
            <?php if (!empty($filterStatus)): ?>
                <input type="hidden" name="status" value="<?= xss_clean($filterStatus) ?>">
            <?php endif; ?>

            <div class="relative flex-1 min-w-[200px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="q" value="<?= xss_clean($searchQ) ?>" placeholder="Tìm tên mã nguồn, slug..." class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all">
            </div>

            <select name="category_id" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                <option value="0">Tất cả danh mục</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterCat === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= xss_clean($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-colors shrink-0 shadow-2xs">
                Lọc
            </button>

            <?php if (!empty($searchQ) || $filterCat > 0 || !empty($filterStatus)): ?>
                <a href="<?= BASE_URL ?>/admin/products" class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors shrink-0" title="Xóa bộ lọc">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- BẢNG DANH SÁCH MÃ NGUỒN CHUẨN SAAS (CHỐNG RỚT DÒNG) -->
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase text-slate-500 bg-slate-50/80 font-bold tracking-wider">
                        <th class="py-3 px-3 w-12 text-center whitespace-nowrap">ID</th>
                        <th class="py-3 px-3 min-w-[200px] whitespace-nowrap">Mã Nguồn</th>
                        <th class="py-3 px-3 whitespace-nowrap">Danh Mục</th>
                        <th class="py-3 px-3 whitespace-nowrap">Giá Bán</th>
                        <th class="py-3 px-3 text-center whitespace-nowrap">Lượt Xem &amp; Tải</th>
                        <th class="py-3 px-2.5 text-center whitespace-nowrap">Trạng Thái</th>
                        <th class="py-3 pr-4 pl-2 text-right whitespace-nowrap">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $p): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- ID -->
                                <td class="py-3 px-3 w-12 text-center font-mono text-xs text-slate-400 whitespace-nowrap">
                                    #<?= $p['id'] ?>
                                </td>

                                <!-- Mã Nguồn (Thumbnail + Tiêu đề + Slug) -->
                                <td class="py-3 px-3 min-w-[200px]">
                                    <div class="flex items-center gap-2.5">
                                        <img src="<?= xss_clean($p['thumbnail']) ?>" alt="" onerror="this.onerror=null;this.src='https://placehold.co/120x80/f1f5f9/64748b?text=CODE';" class="w-12 h-8 rounded-lg object-cover border border-slate-200 shrink-0">
                                        <div class="min-w-0">
                                            <a href="<?= BASE_URL ?>/product?id=<?= $p['id'] ?>" target="_blank" class="font-bold text-slate-800 hover:text-indigo-600 transition-colors line-clamp-1 block text-xs sm:text-sm leading-snug" title="<?= xss_clean($p['title']) ?>">
                                                <?= xss_clean($p['title']) ?>
                                            </a>
                                            <span class="text-[11px] text-slate-400 font-mono truncate block max-w-xs leading-tight mt-0.5">
                                                /<?= xss_clean($p['slug']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Danh Mục -->
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-indigo-50 border border-indigo-100 text-indigo-600 whitespace-nowrap">
                                        <?= xss_clean($p['category_name']) ?>
                                    </span>
                                </td>

                                <!-- Giá Bán -->
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <div class="font-extrabold text-indigo-600 text-xs sm:text-sm whitespace-nowrap">
                                        <?= format_money(!empty($p['sale_price']) && $p['sale_price'] > 0 ? $p['sale_price'] : $p['price']) ?>
                                    </div>
                                    <?php if (!empty($p['sale_price']) && $p['sale_price'] < $p['price']): ?>
                                        <span class="text-[11px] text-slate-400 line-through whitespace-nowrap block mt-0.5">
                                            <?= format_money($p['price']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Lượt xem & Lượt tải -->
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200 text-xs font-bold whitespace-nowrap">
                                        <span class="text-indigo-600 flex items-center gap-1" title="Lượt xem thực tế">
                                            <i class="fa-regular fa-eye text-[11px]"></i><?= number_format((int)($p['views'] ?? $p['view_count'] ?? 0)) ?>
                                        </span>
                                        <span class="text-slate-300">|</span>
                                        <span class="text-emerald-600 flex items-center gap-1" title="Lượt tải thành công">
                                            <i class="fa-solid fa-download text-[11px]"></i><?= number_format((int)$p['download_count']) ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- Trạng Thái -->
                                <td class="py-3 px-2.5 text-center whitespace-nowrap">
                                    <?php if ($p['status'] === 'active'): ?>
                                        <span class="whitespace-nowrap inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Đang Bán</span>
                                        </span>
                                    <?php elseif ($p['status'] === 'hidden'): ?>
                                        <span class="whitespace-nowrap inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            <span>Đang Ẩn</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="whitespace-nowrap inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            <span>Bản Nháp</span>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Thao Tác -->
                                <td class="py-3 pr-4 pl-2 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <a href="<?= BASE_URL ?>/product?id=<?= $p['id'] ?>" target="_blank" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors inline-flex items-center gap-1 text-xs font-semibold shrink-0" title="Xem thử ngoài trang khách hàng">
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                            <span>Xem</span>
                                        </a>
                                        <button type="button" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0">
                                            <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                            <span>Sửa</span>
                                        </button>
                                        <button type="button" onclick="confirmDeleteProduct(<?= $p['id'] ?>, '<?= addslashes($p['title']) ?>')" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0">
                                            <i class="fa-solid fa-trash text-[11px]"></i>
                                            <span>Xóa</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-xs text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-2xl text-slate-300 mb-2 block"></i>
                                Không tìm thấy mã nguồn nào phù hợp với bộ lọc.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL THÊM / SỬA MÃ NGUỒN (Chuẩn SaaS Backdrop & Overlay) -->
<div id="productModal" class="hidden fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm overflow-y-auto" style="z-index: 99999;" onclick="if (event.target === this) closeProductModal()">
    <div class="max-h-[90vh] overflow-y-auto w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-200/80 p-6 sm:p-8 relative">
        <!-- Modal Header -->
        <div class="flex items-start justify-between pb-5 border-b border-slate-100 gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-lg shrink-0">
                    <i class="fa-solid fa-code"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900" id="pModalTitle">Đăng Bán Mã Nguồn Mới</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Điền thông tin và liên kết tải mã nguồn</p>
                </div>
            </div>
            <button type="button" onclick="closeProductModal()" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors shrink-0" title="Đóng (Esc)">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Modal Form -->
        <form method="POST" action="" enctype="multipart/form-data" class="space-y-5 mt-6">
            <input type="hidden" name="action_save_product" value="1">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="product_id" id="prodId" value="0">

            <!-- Tên mã nguồn -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Tên Mã Nguồn *</label>
                <input type="text" name="title" id="prodTitle" required placeholder="Nhập tên mã nguồn đầy đủ..." class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all">
            </div>

            <!-- Danh mục & Trạng thái -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Danh Mục *</label>
                    <select name="category_id" id="prodCatId" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= xss_clean($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Trạng Thái *</label>
                    <select name="status" id="prodStatus" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                        <option value="active">Đang Bán (Active)</option>
                        <option value="hidden">Đang Ẩn (Hidden)</option>
                        <option value="draft">Bản Nháp (Draft)</option>
                    </select>
                </div>
            </div>

            <!-- Hàng Giá -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Giá Gốc (VND) *</label>
                    <input type="number" name="price" id="prodPrice" required placeholder="500000" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Giá Khuyến Mãi (Nếu có)</label>
                    <input type="number" name="sale_price" id="prodSalePrice" placeholder="350000" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                </div>
            </div>

            <!-- Thumbnail & Demo URL -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Ảnh Thumbnail (URL)</label>
                    <input type="url" name="thumbnail" id="prodThumbnail" placeholder="https://..." class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Link Xem Live Demo (URL)</label>
                    <input type="url" name="demo_url" id="prodDemoUrl" placeholder="https://demo..." class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                </div>
            </div>

            <!-- Chọn Ảnh Phụ Demo Tải Lên Trực Tiếp (Tối đa 5 ảnh) -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                        <i class="fa-regular fa-images text-indigo-600"></i>
                        <span>Ảnh Phụ Demo Tải Lên (Tối đa 5 ảnh)</span>
                    </label>
                    <span id="secondaryImagesCount" class="text-[11px] font-bold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">0/5 ảnh</span>
                </div>
                
                <div class="relative">
                    <input type="file" name="secondary_images[]" id="secondaryImagesInput" accept="image/jpeg,image/png,image/webp,image/gif" multiple max="5" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 file:cursor-pointer cursor-pointer border border-slate-200 rounded-xl bg-slate-50 focus:outline-none transition-all">
                </div>
                <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
                    <i class="fa-solid fa-circle-info text-indigo-500"></i>
                    <span>Chọn tối đa 5 ảnh phụ demo (.jpg, .png, .webp). Khung preview sẽ hiển thị ngay bên dưới.</span>
                </p>

                <!-- Lưới Xem Trước Ảnh Phụ Thu Nhỏ (Instant Multi-Image Preview Grid) -->
                <div id="secondaryImagesPreviewGrid" class="grid grid-cols-5 gap-2 mt-2.5 hidden"></div>
            </div>

            <!-- Link tải source code -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Link Tải Source Code (Google Drive / Mega / Host) *</label>
                <input type="text" name="download_url" id="prodDownloadUrl" required placeholder="https://drive.google.com/..." class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                <span class="text-[11px] text-slate-400 mt-1 block">Khách hàng chỉ nhận được link này sau khi hoàn tất mua hàng.</span>
            </div>

            <!-- Mô tả ngắn -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Mô Tả Ngắn</label>
                <textarea name="short_desc" id="prodShortDesc" rows="2" placeholder="Tóm tắt tính năng chính của mã nguồn..." class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all"></textarea>
            </div>

            <!-- Nội dung chi tiết & Hướng dẫn cài đặt -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Nội Dung Chi Tiết &amp; Hướng Dẫn Cài Đặt (HTML)</label>
                <textarea name="content" id="prodContent" rows="5" placeholder="<p>Hướng dẫn cài đặt và cấu hình database...</p>" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all"></textarea>
            </div>

            <!-- Modal Footer -->
            <div class="pt-5 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="button" onclick="closeProductModal()" class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                    Hủy
                </button>
                <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition-all">
                    Lưu Mã Nguồn
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let selectedSecondaryFiles = [];
    let existingSecondaryImages = [];

    function ensureProductModalInBody() {
        var modal = document.getElementById('productModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        return modal;
    }

    function initSecondaryImagesHandlers() {
        const input = document.getElementById('secondaryImagesInput');
        if (!input) return;

        input.addEventListener('change', function(e) {
            const files = Array.from(e.target.files || []);
            if (!files.length) return;

            const currentTotal = existingSecondaryImages.length + selectedSecondaryFiles.length;
            const remainingSlots = 5 - currentTotal;

            if (remainingSlots <= 0) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Đã đạt tối đa 5 ảnh',
                        text: 'Bạn đã có đủ 5 ảnh phụ demo. Hãy xóa bớt ảnh nếu muốn chọn thêm!',
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else {
                    alert('Bạn đã chọn đủ tối đa 5 ảnh phụ demo!');
                }
                syncSecondaryFileInput();
                return;
            }

            if (files.length > remainingSlots) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Giới hạn số lượng ảnh',
                        text: `Chỉ được chọn tối đa 5 ảnh phụ demo. Hệ thống sẽ lấy ${remainingSlots} ảnh đầu tiên!`,
                        timer: 3500,
                        showConfirmButton: false
                    });
                } else {
                    alert(`Chỉ được chọn tối đa 5 ảnh! Hệ thống lấy ${remainingSlots} ảnh đầu tiên.`);
                }
            }

            const filesToAdd = files.slice(0, remainingSlots);
            filesToAdd.forEach(file => {
                if (file.type.startsWith('image/')) {
                    selectedSecondaryFiles.push(file);
                }
            });

            syncSecondaryFileInput();
            renderSecondaryImagesPreviews();
        });
    }

    function syncSecondaryFileInput() {
        const input = document.getElementById('secondaryImagesInput');
        if (!input) return;
        try {
            const dt = new DataTransfer();
            selectedSecondaryFiles.forEach(file => dt.items.add(file));
            input.files = dt.files;
        } catch (e) {
            console.error('DataTransfer error:', e);
        }
    }

    function removeNewSecondaryImage(index) {
        if (index >= 0 && index < selectedSecondaryFiles.length) {
            selectedSecondaryFiles.splice(index, 1);
            syncSecondaryFileInput();
            renderSecondaryImagesPreviews();
        }
    }

    function removeExistingSecondaryImage(index) {
        if (index >= 0 && index < existingSecondaryImages.length) {
            existingSecondaryImages.splice(index, 1);
            renderSecondaryImagesPreviews();
        }
    }

    function renderSecondaryImagesPreviews() {
        const grid = document.getElementById('secondaryImagesPreviewGrid');
        const countBadge = document.getElementById('secondaryImagesCount');
        if (!grid) return;

        const totalCount = existingSecondaryImages.length + selectedSecondaryFiles.length;
        if (countBadge) {
            countBadge.innerText = `${totalCount}/5 ảnh`;
            if (totalCount >= 5) {
                countBadge.className = 'text-[11px] font-bold text-amber-600 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200';
            } else {
                countBadge.className = 'text-[11px] font-bold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full';
            }
        }

        grid.innerHTML = '';

        // 1. Hiển thị các ảnh phụ đã có sẵn
        existingSecondaryImages.forEach((imgUrl, idx) => {
            let displayUrl = imgUrl;
            if (!displayUrl.startsWith('http') && !displayUrl.startsWith('/') && !displayUrl.startsWith('data:')) {
                displayUrl = '<?= BASE_URL ?>/' + displayUrl;
            }
            const item = document.createElement('div');
            item.className = 'relative group aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-100 shadow-2xs';
            item.innerHTML = `
                <img src="${displayUrl}" alt="Ảnh cũ ${idx + 1}" class="w-full h-full object-cover" onerror="this.src='https://placehold.co/100x100/f1f5f9/64748b?text=IMG'">
                <input type="hidden" name="existing_gallery[]" value="${imgUrl}">
                <button type="button" onclick="removeExistingSecondaryImage(${idx})" class="absolute top-1 right-1 w-5 h-5 rounded-md bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center text-[9px] shadow-sm transition-all cursor-pointer opacity-90 hover:opacity-100" title="Xóa ảnh này">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <span class="absolute bottom-1 left-1 px-1 py-0.2 rounded text-[8px] font-bold bg-slate-900/75 text-white">Ảnh cũ</span>
            `;
            grid.appendChild(item);
        });

        // 2. Hiển thị các ảnh phụ mới được chọn từ máy tính
        selectedSecondaryFiles.forEach((file, idx) => {
            const objectUrl = URL.createObjectURL(file);
            const item = document.createElement('div');
            item.className = 'relative group aspect-square rounded-xl overflow-hidden border-2 border-indigo-400 bg-indigo-50/20 shadow-2xs';
            item.innerHTML = `
                <img src="${objectUrl}" alt="Ảnh mới ${idx + 1}" class="w-full h-full object-cover">
                <button type="button" onclick="removeNewSecondaryImage(${idx})" class="absolute top-1 right-1 w-5 h-5 rounded-md bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center text-[9px] shadow-sm transition-all cursor-pointer opacity-90 hover:opacity-100" title="Xóa ảnh này">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <span class="absolute bottom-1 left-1 px-1 py-0.2 rounded text-[8px] font-bold bg-indigo-600 text-white">Ảnh mới</span>
            `;
            grid.appendChild(item);
        });

        if (totalCount === 0) {
            grid.classList.add('hidden');
        } else {
            grid.classList.remove('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        ensureProductModalInBody();
        initSecondaryImagesHandlers();
    });

    function openProductModal() {
        var modal = ensureProductModalInBody();
        document.getElementById('pModalTitle').innerText = 'Đăng Bán Mã Nguồn Mới';
        document.getElementById('prodId').value = 0;
        document.getElementById('prodTitle').value = '';
        document.getElementById('prodPrice').value = '';
        document.getElementById('prodSalePrice').value = '';
        document.getElementById('prodThumbnail').value = '';
        
        // Reset danh sách ảnh phụ
        selectedSecondaryFiles = [];
        existingSecondaryImages = [];
        const fileInput = document.getElementById('secondaryImagesInput');
        if (fileInput) fileInput.value = '';
        syncSecondaryFileInput();
        renderSecondaryImagesPreviews();

        document.getElementById('prodDemoUrl').value = '';
        document.getElementById('prodDownloadUrl').value = '';
        document.getElementById('prodShortDesc').value = '';
        document.getElementById('prodContent').value = '';
        document.getElementById('prodStatus').value = 'active';
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function editProduct(p) {
        var modal = ensureProductModalInBody();
        document.getElementById('pModalTitle').innerText = 'Chỉnh Sửa Mã Nguồn: #' + p.id;
        document.getElementById('prodId').value = p.id;
        document.getElementById('prodTitle').value = p.title;
        document.getElementById('prodCatId').value = p.category_id;
        document.getElementById('prodPrice').value = p.price;
        document.getElementById('prodSalePrice').value = p.sale_price || '';
        document.getElementById('prodThumbnail').value = p.thumbnail || '';

        // Đổ danh sách ảnh phụ đã có
        selectedSecondaryFiles = [];
        existingSecondaryImages = [];
        const fileInput = document.getElementById('secondaryImagesInput');
        if (fileInput) fileInput.value = '';

        if (p.gallery) {
            try {
                let parsed = typeof p.gallery === 'string' ? JSON.parse(p.gallery) : p.gallery;
                if (Array.isArray(parsed)) {
                    existingSecondaryImages = parsed.filter(x => typeof x === 'string' && x.trim() !== '').slice(0, 5);
                } else if (typeof p.gallery === 'string') {
                    existingSecondaryImages = p.gallery.split(/[\r\n,]+/).map(x => x.trim()).filter(Boolean).slice(0, 5);
                }
            } catch(e) {
                if (typeof p.gallery === 'string') {
                    existingSecondaryImages = p.gallery.split(/[\r\n,]+/).map(x => x.trim()).filter(Boolean).slice(0, 5);
                }
            }
        }
        syncSecondaryFileInput();
        renderSecondaryImagesPreviews();

        document.getElementById('prodDemoUrl').value = p.demo_url || '';
        document.getElementById('prodDownloadUrl').value = p.download_url || '';
        document.getElementById('prodShortDesc').value = p.short_desc || '';
        document.getElementById('prodContent').value = p.content || '';
        document.getElementById('prodStatus').value = p.status;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeProductModal() {
        var modal = document.getElementById('productModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        document.body.classList.remove('overflow-hidden');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeProductModal();
        }
    });

    function confirmDeleteProduct(id, title) {
        Swal.fire({
            title: 'Xác Nhận Xóa Mã Nguồn',
            text: `Bạn có chắc muốn xóa vĩnh viễn "${title}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Đồng ý xóa',
            cancelButtonText: 'Hủy bỏ',
            confirmButtonColor: '#e11d48'
        }).then((res) => {
            if (res.isConfirmed) {
                window.location.href = '<?= BASE_URL ?>/admin/products?delete_id=' + id;
            }
        });
    }
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
