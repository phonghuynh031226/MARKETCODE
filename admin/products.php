<?php
declare(strict_types=1);
$pageTitle = "Quản Lý Mã Nguồn (Source Code)";
require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$error = '';

// Lấy danh sách danh mục để đổ vào dropdown
$categories = [];
if ($db) {
    try {
        $categories = $db->query("SELECT id, name FROM categories WHERE deleted_at IS NULL ORDER BY sort_order ASC, name ASC")->fetchAll();
    } catch (Throwable $e) {}
}

// Xử lý Thêm / Sửa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_product'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token không hợp lệ!';
    } else {
        try {
        $rawProduct = $_POST['product_id'] ?? '';
        $isNewProduct = $rawProduct === '' || $rawProduct === '0';
        $prodId = $isNewProduct ? 0 : public_id_to_internal($db, 'products', $rawProduct);
        if (!$isNewProduct && $prodId <= 0) { throw new RuntimeException('Sản phẩm không hợp lệ.'); }
        $categoryId = public_id_to_internal($db, 'categories', $_POST['category_id'] ?? '');
        $title        = trim($_POST['title'] ?? '');
        $slug         = trim($_POST['slug'] ?? '');
        $shortDesc    = trim($_POST['short_desc'] ?? '');
        $content      = trim($_POST['content'] ?? '');
        $price = money_decimal(money_minor($_POST['price'] ?? '0'));
        $salePrice = ($_POST['sale_price'] ?? '') !== '' ? money_decimal(money_minor($_POST['sale_price'])) : null;
        $demoUrl      = trim($_POST['demo_url'] ?? '');
        $downloadUrl  = trim($_POST['download_url'] ?? '');
        $status       = in_array($_POST['status'] ?? '', ['active', 'hidden', 'draft']) ? $_POST['status'] : 'active';

        // Xử lý Danh Sách Ảnh Sản Phẩm (Tối đa 5 ảnh: Ảnh 1 là Thumbnail, Ảnh 2-5 là Ảnh phụ)
        $finalGallery = [];
        if (isset($_POST['existing_gallery']) && !is_array($_POST['existing_gallery'])) { throw new RuntimeException('Danh sách ảnh không hợp lệ.'); }
        if (count($_POST['existing_gallery'] ?? []) > 5) { throw new RuntimeException('Tối đa 5 ảnh.'); }

        // 1. Giữ lại các ảnh cũ đã có (nếu chỉnh sửa sản phẩm)
        if (!empty($_POST['existing_gallery']) && is_array($_POST['existing_gallery'])) {
            foreach ($_POST['existing_gallery'] as $oldImg) {
                if (!is_string($oldImg) || safe_url($oldImg) === '') { throw new RuntimeException('Ảnh không hợp lệ.'); }
                $oldImg = trim($oldImg);
                if (!empty($oldImg) && !in_array($oldImg, $finalGallery)) {
                    $finalGallery[] = $oldImg;
                }
            }
        }

        if (isset($_FILES['secondary_images']['error']) && is_array($_FILES['secondary_images']['error'])) {
            foreach ($_FILES['secondary_images']['error'] as $i => $uploadError) {
                if ($uploadError === UPLOAD_ERR_NO_FILE) { continue; }
                if (count($finalGallery) >= 5) { throw new RuntimeException('Tối đa 5 ảnh.'); }
                $upload = ['error' => $uploadError, 'tmp_name' => $_FILES['secondary_images']['tmp_name'][$i] ?? ''];
                $finalGallery[] = 'assets/uploads/products/' . store_uploaded_image($upload, ROOT_PATH . '/assets/uploads/products', 5242880);
            }
        }

        // Ảnh số 1 tự động làm thumbnail, các ảnh còn lại làm gallery (ảnh phụ)
        $thumbnail = 'assets/images/default-thumbnail.jpg';
        $secondaryImages = [];
        if (!empty($finalGallery)) {
            $thumbnail = $finalGallery[0];
            $secondaryImages = array_slice($finalGallery, 1);
        }
        $galleryJson = !empty($secondaryImages) ? js(array_values($secondaryImages)) : null;

        if (empty($slug)) {
            $slug = slugify($title);
        }

        if (empty($title) || $categoryId <= 0 || (safe_url($downloadUrl, false) === '' && !preg_match('/\\A[a-zA-Z0-9._-]{1,160}\\.(zip|7z|rar|pdf)\\z/iD', $downloadUrl)) || ($demoUrl !== '' && safe_url($demoUrl, false) === '')) {
            $error = 'Vui lòng nhập đầy đủ tiêu đề, danh mục, giá tiền và link tải source code!';
        } else {
            try {
                $db->beginTransaction();
                $lock = $db->prepare('SELECT id FROM categories WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
                $lock->execute([$categoryId]);
                if (!$lock->fetchColumn()) { throw new RuntimeException('Danh mục không tồn tại.'); }
                if ($prodId > 0) {
                    $lock = $db->prepare('SELECT id FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
                    $lock->execute([$prodId]);
                    if (!$lock->fetchColumn()) { throw new RuntimeException('Sản phẩm không tồn tại.'); }
                }
                if ($prodId > 0) {
                    $stmt = $db->prepare("UPDATE products SET category_id = ?, title = ?, slug = ?, short_desc = ?, content = ?, price = ?, sale_price = ?, thumbnail = ?, gallery = ?, demo_url = ?, download_url = ?, status = ? WHERE id = ?");
                    $stmt->execute([$categoryId, $title, $slug, $shortDesc, $content, $price, $salePrice, $thumbnail ?: 'assets/images/default-thumbnail.jpg', $galleryJson, $demoUrl, $downloadUrl, $status, $prodId]);
                    $message = 'Cập nhật mã nguồn thành công!';
                } else {
                    $stmt = $db->prepare("INSERT INTO products (public_id, category_id, title, slug, short_desc, content, price, sale_price, thumbnail, gallery, demo_url, download_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([uuid_v4(), $categoryId, $title, $slug, $shortDesc, $content, $price, $salePrice, $thumbnail ?: 'assets/images/default-thumbnail.jpg', $galleryJson, $demoUrl, $downloadUrl, $status]);
                    $prodId = (int)$db->lastInsertId();
                    $message = 'Đăng mã nguồn mới thành công!';
                }
                audit_event($db, 'product.save', 'products', $prodId > 0 ? public_reference('products', $prodId) : null);
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) { $db->rollBack(); }
                $error = 'Lỗi lưu mã nguồn (có thể trùng Slug): ' . public_error($e);
            }
        }
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $error = public_error($e);
        }
    }
}

// Xử lý Xóa sản phẩm
if (isset($_POST['delete_id'])) {
    try {
        $delId = public_id_to_internal($db, 'products', $_POST['delete_id']);
        if ($delId <= 0) { throw new RuntimeException('Sản phẩm không hợp lệ.'); }
        $db->beginTransaction();
        $lock = $db->prepare('SELECT id FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $lock->execute([$delId]);
        if (!$lock->fetchColumn()) { throw new RuntimeException('Sản phẩm không tồn tại.'); }
        $stmt = $db->prepare("UPDATE products SET deleted_at = NOW(), status = 'hidden' WHERE id = ?");
        $stmt->execute([$delId]);
        audit_event($db, 'product.delete', 'products', public_reference('products', $delId));
        $db->commit();
        $message = 'Đã ẩn sản phẩm.';
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        $error = public_error($e);
    }
}

// Thống kê nhanh
$totalCount  = 0;
$activeCount = 0;
$hiddenCount = 0;
if ($db) {
    try {
        $totalCount  = (int)$db->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
        $activeCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = 'active'")->fetchColumn();
        $hiddenCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status IN ('hidden', 'draft')")->fetchColumn();
    } catch (Throwable $e) {}
}

// Bộ lọc & Tìm kiếm nhanh
$searchQ      = trim($_GET['q'] ?? '');
$filterCat = public_id_to_internal($db, 'categories', $_GET['category_id'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$where = ["p.deleted_at IS NULL", "c.deleted_at IS NULL"];
$params = [];
if (isset($_GET['category_id']) && $_GET['category_id'] !== '' && $_GET['category_id'] !== '0' && $filterCat <= 0) { $where[] = '1=0'; $error = 'Danh mục không hợp lệ.'; }

if (!empty($searchQ)) {
    $where[] = "(p.title LIKE ? ESCAPE '!' OR p.slug LIKE ? ESCAPE '!')";
    $params[] = like_pattern($searchQ);
    $params[] = like_pattern($searchQ);
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
    } catch (Throwable $e) {}
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

    <!-- THANH CÔNG CỤ NHANH: TÌM KIẾM, LỌC & THỐNG KÊ -->
    <div class="responsive-toolbar rounded-2xl bg-white p-3.5 sm:p-4 border border-slate-200/80 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Thống kê nhanh dạng Pill -->
        <div class="flex items-center gap-2 shrink-0 overflow-x-auto pb-1 md:pb-0 text-xs">
            <a href="<?= e(BASE_URL) ?>/admin/products" class="px-3 py-1.5 rounded-xl font-bold border transition-colors flex items-center gap-1.5 <?= e(empty($filterStatus) ? 'bg-indigo-50 text-indigo-600 border-indigo-200' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-slate-300') ?>">
                <span>Tất cả:</span>
                <strong class="font-black"><?= e($totalCount) ?></strong>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/products?status=active" class="px-3 py-1.5 rounded-xl font-bold border transition-colors flex items-center gap-1.5 <?= e($filterStatus === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-emerald-300') ?>">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Đang bán:</span>
                <strong class="font-black text-emerald-600"><?= e($activeCount) ?></strong>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/products?status=hidden" class="px-3 py-1.5 rounded-xl font-bold border transition-colors flex items-center gap-1.5 <?= e(($filterStatus === 'hidden' || $filterStatus === 'draft') ? 'bg-slate-200 text-slate-800 border-slate-300' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-slate-300') ?>">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                <span>Đang ẩn:</span>
                <strong class="font-black text-slate-600"><?= e($hiddenCount) ?></strong>
            </a>
        </div>

        <!-- Form Tìm kiếm & Lọc theo Danh mục -->
        <form method="GET" action="<?= e(BASE_URL) ?>/admin/products" class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
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
                    <option value="<?= e(public_reference('categories', (int)$c['id'])) ?>" <?= e($filterCat === (int)$c['id'] ? 'selected' : '') ?>>
                        <?= xss_clean($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-colors shrink-0 shadow-2xs">
                Lọc
            </button>

            <?php if (!empty($searchQ) || $filterCat > 0 || !empty($filterStatus)): ?>
                <a href="<?= e(BASE_URL) ?>/admin/products" class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition-colors shrink-0" title="Xóa bộ lọc">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- BẢNG DANH SÁCH MÃ NGUỒN CHUẨN SAAS (CHỐNG RỚT DÒNG) -->
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="w-full overflow-x-auto">
            <div class="responsive-table" role="region" aria-label="Bảng dữ liệu, cuộn ngang khi cần" tabindex="0"><table data-responsive="wide" class="w-full text-left text-sm border-collapse">
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
                                    #<?= e(public_reference('products', (int)$p['id'])) ?>
                                </td>

                                <!-- Mã Nguồn (Thumbnail + Tiêu đề + Slug) -->
                                <td class="py-3 px-3 min-w-[200px]">
                                    <div class="flex items-center gap-2.5">
                                        <img src="<?= e(safe_url($p['thumbnail'])) ?>" alt="" onerror="this.onerror=null;this.src='https://placehold.co/120x80/f1f5f9/64748b?text=CODE';" class="w-12 h-8 rounded-lg object-cover border border-slate-200 shrink-0">
                                        <div class="min-w-0">
                                            <a href="<?= e(BASE_URL) ?>/product?id=<?= e(public_reference('products', (int)$p['id'])) ?>" target="_blank" class="font-bold text-slate-800 hover:text-indigo-600 transition-colors line-clamp-1 block text-xs sm:text-sm leading-snug" title="<?= xss_clean($p['title']) ?>">
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
                                        <?= e(format_money(!empty($p['sale_price']) && $p['sale_price'] > 0 ? $p['sale_price'] : $p['price'])) ?>
                                    </div>
                                    <?php if (!empty($p['sale_price']) && $p['sale_price'] < $p['price']): ?>
                                        <span class="text-[11px] text-slate-400 line-through whitespace-nowrap block mt-0.5">
                                            <?= e(format_money($p['price'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Lượt xem & Lượt tải -->
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200 text-xs font-bold whitespace-nowrap">
                                        <span class="text-indigo-600 flex items-center gap-1" title="Lượt xem thực tế">
                                            <i class="fa-regular fa-eye text-[11px]"></i><?= e(number_format((float)((int)($p['views'] ?? $p['view_count'] ?? 0)))) ?>
                                        </span>
                                        <span class="text-slate-300">|</span>
                                        <span class="text-emerald-600 flex items-center gap-1" title="Lượt tải thành công">
                                            <i class="fa-solid fa-download text-[11px]"></i><?= e(number_format((float)((int)$p['download_count']))) ?>
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
                                        <a href="<?= e(BASE_URL) ?>/product?id=<?= e(public_reference('products', (int)$p['id'])) ?>" target="_blank" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors inline-flex items-center gap-1 text-xs font-semibold shrink-0" title="Xem thử ngoài trang khách hàng">
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                            <span>Xem</span>
                                        </a>
                                        <button type="button" onclick="editProduct(<?= e(js(browser_record('products', $p))) ?>)" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0">
                                            <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                            <span>Sửa</span>
                                        </button>
                                        <button type="button" onclick="confirmDeleteProduct(<?= e(js(public_reference('products', (int)$p['id']))) ?>, <?= e(js($p['title'])) ?>)" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0">
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
            </table></div>
        </div>
    </div>
</div>

<!-- MODAL THÊM / SỬA MÃ NGUỒN (Chuẩn SaaS Backdrop & Overlay - No-scroll Compact Modal) -->
<div id="productModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/60 backdrop-blur-sm overflow-y-auto" onclick="if (event.target === this) closeProductModal()">
    <div class="max-h-[92dvh] overflow-y-auto w-full max-w-4xl bg-white rounded-3xl shadow-2xl border border-slate-200/80 p-5 sm:p-6 my-auto relative">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 gap-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-base shrink-0">
                    <i class="fa-solid fa-code"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900" id="pModalTitle">Đăng Bán Mã Nguồn Mới</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Điền thông tin và liên kết tải mã nguồn</p>
                </div>
            </div>
            <button type="button" onclick="closeProductModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors shrink-0" title="Đóng (Esc)">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Modal Form (Bố Cục 2 Cột Cân Đối Compact Trọn Vẹn 1 Màn Hình) -->
        <form method="POST" action="" enctype="multipart/form-data" class="mt-4 space-y-3">
            <input type="hidden" name="action_save_product" value="1">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="product_id" id="prodId" value="0">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-3">
                <!-- CỘT TRÁI: THÔNG TIN CƠ BẢN, GIÁ & LINK TẢI -->
                <div class="space-y-3">
                    <!-- Tên mã nguồn -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Tên Mã Nguồn *</label>
                        <input type="text" name="title" id="prodTitle" required placeholder="Nhập tên mã nguồn đầy đủ..." class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all">
                    </div>

                    <!-- Danh mục & Trạng thái -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Danh Mục *</label>
                            <select name="category_id" id="prodCatId" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= e(public_reference('categories', (int)$c['id'])) ?>"><?= xss_clean($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Trạng Thái *</label>
                            <select name="status" id="prodStatus" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                                <option value="active">Đang Bán (Active)</option>
                                <option value="hidden">Đang Ẩn (Hidden)</option>
                                <option value="draft">Bản Nháp (Draft)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Hàng Giá -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Giá Gốc (VND) *</label>
                            <input type="number" name="price" id="prodPrice" required placeholder="500000" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Giá Khuyến Mãi</label>
                            <input type="number" name="sale_price" id="prodSalePrice" placeholder="350000" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                        </div>
                    </div>

                    <!-- Link Xem Live Demo (Tùy Chọn) -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Link Xem Live Demo (Tùy Chọn)</label>
                        <input type="url" name="demo_url" id="prodDemoUrl" placeholder="https://demo.example.com (không bắt buộc)..." class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                    </div>

                    <!-- Link tải source code -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Link Tải Source Code (Drive / Mega) *</label>
                        <input type="text" name="download_url" id="prodDownloadUrl" required placeholder="https://drive.google.com/..." class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all">
                    </div>
                </div>

                <!-- CỘT PHẢI: ẢNH PHỤ DEMO, MÔ TẢ & HƯỚNG DẪN -->
                <div class="space-y-3 flex flex-col justify-between">
                    <!-- Chọn Ảnh Bộ Sưu Tập & Thumbnail Thông Minh -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <i class="fa-regular fa-images text-purple-600"></i>
                                <span>Bộ Sưu Tập Ảnh Sản Phẩm (Tối đa 5 ảnh)</span>
                            </label>
                            <span id="secondaryImagesCount" class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">0/5 ảnh</span>
                        </div>
                        <p class="text-[10px] text-slate-400 mb-1.5 leading-tight">Ảnh số 1 sẽ tự động làm <strong class="text-purple-600">⭐ Ảnh chính (Thumbnail)</strong>. Ảnh 2-5 là ảnh phụ.</p>

                        <input type="file" name="secondary_images[]" id="secondaryImagesInput" accept="image/jpeg,image/png,image/webp,image/gif" multiple max="5" class="block w-full text-[11px] text-slate-500 file:mr-2.5 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 file:cursor-pointer cursor-pointer border border-slate-200 rounded-xl bg-slate-50 focus:outline-none transition-all">

                        <!-- Lưới Xem Trước Ảnh Thu Nhỏ -->
                        <div id="secondaryImagesPreviewGrid" class="grid grid-cols-5 gap-1.5 mt-1.5 hidden"></div>
                    </div>

                    <!-- Mô tả ngắn -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Mô Tả Ngắn</label>
                        <textarea name="short_desc" id="prodShortDesc" rows="2" placeholder="Tóm tắt tính năng chính của mã nguồn..." class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all"></textarea>
                    </div>

                    <!-- Nội dung chi tiết & Hướng dẫn cài đặt -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Nội Dung Chi Tiết (HTML)</label>
                        <textarea name="content" id="prodContent" rows="3" placeholder="<p>Hướng dẫn cài đặt và cấu hình database...</p>" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:bg-white focus:border-indigo-500 transition-all"></textarea>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeProductModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                    Hủy
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk text-[11px]"></i>
                    <span>Lưu Mã Nguồn</span>
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
        let overallIdx = 0;

        // 1. Hiển thị các ảnh đã có sẵn
        existingSecondaryImages.forEach((imgUrl, idx) => {
            let displayUrl = imgUrl;
            if (!displayUrl.startsWith('http') && !displayUrl.startsWith('/') && !displayUrl.startsWith('data:')) {
                displayUrl = '<?= BASE_URL ?>/' + displayUrl;
            }
            const isMain = (overallIdx === 0);
            overallIdx++;

            const item = document.createElement('div');
            item.className = isMain
                ? 'relative group aspect-square rounded-xl overflow-hidden border-2 border-purple-600 ring-2 ring-purple-500/20 bg-purple-50/20 shadow-xs'
                : 'relative group aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-100 shadow-2xs';

            const badgeHtml = isMain
                ? `<span class="absolute bottom-0.5 inset-x-0.5 py-0.5 rounded text-[7.5px] font-extrabold bg-purple-600 text-white text-center leading-tight shadow-xs truncate">⭐ Ảnh chính (Thumbnail)</span>`
                : `<span class="absolute bottom-0.5 left-0.5 px-1 py-0.2 rounded text-[7.5px] font-bold bg-slate-900/75 text-white leading-tight">Ảnh phụ</span>`;

            item.innerHTML = `
                <img alt="Ảnh ${overallIdx}" class="w-full h-full object-cover" onerror="this.src='https://placehold.co/100x100/f1f5f9/64748b?text=IMG'">
                <input type="hidden" name="existing_gallery[]">
                <button type="button" onclick="removeExistingSecondaryImage(${idx})" class="absolute top-1 right-1 w-5 h-5 rounded-md bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center text-[9px] shadow-sm transition-all cursor-pointer opacity-90 hover:opacity-100" title="Xóa ảnh này">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                ${badgeHtml}
            `;
            const preview = item.querySelector('img');
            try {
                const parsed = new URL(displayUrl, window.location.origin);
                preview.src = ['http:', 'https:'].includes(parsed.protocol) && !parsed.username && !parsed.password ? parsed.href : '';
            } catch (_) { preview.removeAttribute('src'); }
            item.querySelector('input').value = imgUrl;
            grid.appendChild(item);
        });

        // 2. Hiển thị các ảnh mới được chọn từ máy tính
        selectedSecondaryFiles.forEach((file, idx) => {
            const objectUrl = URL.createObjectURL(file);
            const isMain = (overallIdx === 0);
            overallIdx++;

            const item = document.createElement('div');
            item.className = isMain
                ? 'relative group aspect-square rounded-xl overflow-hidden border-2 border-purple-600 ring-2 ring-purple-500/20 bg-purple-50/20 shadow-xs'
                : 'relative group aspect-square rounded-xl overflow-hidden border-2 border-indigo-300 bg-indigo-50/20 shadow-2xs';

            const badgeHtml = isMain
                ? `<span class="absolute bottom-0.5 inset-x-0.5 py-0.5 rounded text-[7.5px] font-extrabold bg-purple-600 text-white text-center leading-tight shadow-xs truncate">⭐ Ảnh chính (Thumbnail)</span>`
                : `<span class="absolute bottom-0.5 left-0.5 px-1 py-0.2 rounded text-[7.5px] font-bold bg-indigo-600 text-white leading-tight">Ảnh phụ</span>`;

            item.innerHTML = `
                <img src="${objectUrl}" alt="Ảnh mới ${idx + 1}" class="w-full h-full object-cover">
                <button type="button" onclick="removeNewSecondaryImage(${idx})" class="absolute top-1 right-1 w-5 h-5 rounded-md bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center text-[9px] shadow-sm transition-all cursor-pointer opacity-90 hover:opacity-100" title="Xóa ảnh này">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                ${badgeHtml}
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

        // Reset danh sách ảnh
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

        // Đổ danh sách ảnh: Ảnh thumbnail cũ làm ảnh số 1, tiếp theo là gallery
        selectedSecondaryFiles = [];
        existingSecondaryImages = [];
        const fileInput = document.getElementById('secondaryImagesInput');
        if (fileInput) fileInput.value = '';

        if (p.thumbnail && p.thumbnail !== 'assets/images/default-thumbnail.jpg') {
            existingSecondaryImages.push(p.thumbnail);
        }

        if (p.gallery) {
            try {
                let parsed = typeof p.gallery === 'string' ? JSON.parse(p.gallery) : p.gallery;
                if (Array.isArray(parsed)) {
                    parsed.forEach(img => {
                        if (typeof img === 'string' && img.trim() !== '' && !existingSecondaryImages.includes(img.trim()) && existingSecondaryImages.length < 5) {
                            existingSecondaryImages.push(img.trim());
                        }
                    });
                } else if (typeof p.gallery === 'string') {
                    p.gallery.split(/[\r\n,]+/).map(x => x.trim()).filter(Boolean).forEach(img => {
                        if (!existingSecondaryImages.includes(img) && existingSecondaryImages.length < 5) {
                            existingSecondaryImages.push(img);
                        }
                    });
                }
            } catch(e) {
                if (typeof p.gallery === 'string') {
                    p.gallery.split(/[\r\n,]+/).map(x => x.trim()).filter(Boolean).forEach(img => {
                        if (!existingSecondaryImages.includes(img) && existingSecondaryImages.length < 5) {
                            existingSecondaryImages.push(img);
                        }
                    });
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
        confirmAction('<?= BASE_URL ?>/admin/products?delete_id=' + id, {
            title: 'Xóa Mã Nguồn',
            text: `Bạn có chắc chắn muốn xóa vĩnh viễn sản phẩm "${title}"? Thao tác này không thể hoàn tác.`,
            icon: 'warning',
            confirmText: 'Đồng Ý Xóa',
            isDanger: true
        });
    }
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
