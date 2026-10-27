<?php
$pageTitle = "Quản Lý Danh Mục Mã Nguồn";
require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$error = '';

// Xử lý Thêm hoặc Cập nhật danh mục
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token không hợp lệ!';
    } else {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fas fa-code');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;

        if (empty($slug)) {
            $slug = slugify($name);
        }

        if (empty($name)) {
            $error = 'Tên danh mục không được để trống!';
        } else {
            try {
                if ($catId > 0) {
                    $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, description = ?, sort_order = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $icon, $description, $sortOrder, $status, $catId]);
                    $message = 'Cập nhật danh mục thành công!';
                } else {
                    $stmt = $db->prepare("INSERT INTO categories (name, slug, icon, description, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $slug, $icon, $description, $sortOrder, $status]);
                    $message = 'Thêm danh mục mới thành công!';
                }
            } catch (Exception $e) {
                $error = 'Lỗi lưu danh mục (có thể bị trùng Slug): ' . $e->getMessage();
            }
        }
    }
}

// Xử lý Xóa danh mục
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    try {
        $checkProd = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $checkProd->execute([$delId]);
        if ($checkProd->fetchColumn() > 0) {
            $error = 'Không thể xóa danh mục này vì đang có sản phẩm thuộc danh mục! Vui lòng chuyển hoặc xóa sản phẩm trước.';
        } else {
            $delStmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $delStmt->execute([$delId]);
            $message = 'Xóa danh mục thành công!';
        }
    } catch (Exception $e) {
        $error = 'Lỗi khi xóa: ' . $e->getMessage();
    }
}

// Lấy danh sách danh mục
$categories = [];
if ($db) {
    try {
        $categories = $db->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count 
                                 FROM categories c ORDER BY c.sort_order ASC, c.id DESC")->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="space-y-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Danh Mục Mã Nguồn</h1>
            <p class="text-xs text-slate-500 mt-1">Quản lý và phân loại các chủ đề source code trên hệ thống.</p>
        </div>
        <button type="button" onclick="openCategoryModal()" class="btn-shimmer px-5 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-500/25 flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>Thêm Danh Mục Mới
        </button>
    </div>

    <!-- Thông báo -->
    <?php if (!empty($message)): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-check"></i><span><?= xss_clean($message) ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i><span><?= xss_clean($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Bảng danh mục -->
    <div class="rounded-3xl bg-white border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-xs uppercase text-slate-400 bg-slate-50/70 font-semibold tracking-wider">
                        <th class="py-3 px-3 w-12 text-center whitespace-nowrap">ID</th>
                        <th class="py-3 px-3 min-w-[180px] whitespace-nowrap">Icon &amp; Tên Danh Mục</th>
                        <th class="py-3 px-3 whitespace-nowrap">Đường Dẫn (Slug)</th>
                        <th class="py-3 px-2.5 text-center whitespace-nowrap">Số Code</th>
                        <th class="py-3 px-2 text-center whitespace-nowrap">Thứ Tự</th>
                        <th class="py-3 px-2.5 text-center whitespace-nowrap">Trạng Thái</th>
                        <th class="py-3 pr-4 pl-2 text-right whitespace-nowrap">Thao Tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-3 w-12 text-center font-mono text-xs text-slate-400 whitespace-nowrap"><?= $cat['id'] ?></td>
                                <td class="py-3 px-3 min-w-[180px]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shrink-0 text-xs">
                                            <i class="<?= xss_clean($cat['icon']) ?>"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <strong class="text-slate-800 block font-bold text-xs sm:text-sm truncate leading-snug"><?= xss_clean($cat['name']) ?></strong>
                                            <span class="text-[11px] text-slate-400 truncate block leading-tight mt-0.5 max-w-[200px]"><?= xss_clean($cat['description'] ?? 'Không có mô tả') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 font-mono text-xs text-slate-500 whitespace-nowrap"><?= xss_clean($cat['slug']) ?></td>
                                <td class="py-3 px-2.5 text-center font-bold text-indigo-600 whitespace-nowrap"><?= (int)$cat['product_count'] ?></td>
                                <td class="py-3 px-2 text-center font-mono text-xs text-slate-600 whitespace-nowrap"><?= $cat['sort_order'] ?></td>
                                <td class="py-3 px-2.5 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= $cat['status'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' ?>">
                                        <?= $cat['status'] ? 'Hiển thị' : 'Đang ẩn' ?>
                                    </span>
                                </td>
                                <td class="py-3 pr-4 pl-2 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <button type="button" onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0">
                                            <i class="fa-solid fa-pen-to-square text-[11px]"></i><span>Sửa</span>
                                        </button>
                                        <button type="button" onclick="confirmDeleteCat(<?= $cat['id'] ?>, '<?= addslashes($cat['name']) ?>')" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/60 inline-flex items-center gap-1 transition-colors cursor-pointer shrink-0">
                                            <i class="fa-solid fa-trash text-[11px]"></i><span>Xóa</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="py-8 text-center text-xs text-slate-400">Chưa có danh mục nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL THÊM / SỬA DANH MỤC (Chuẩn SaaS Backdrop & Overlay) -->
<div id="catModal" class="hidden fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm overflow-y-auto" style="z-index: 99999;" onclick="if (event.target === this) closeCatModal()">
    <div class="max-h-[90vh] overflow-y-auto w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-200/80 p-6 sm:p-8 relative">
        <!-- Modal Header -->
        <div class="flex items-start justify-between pb-5 border-b border-slate-100 gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-lg shrink-0">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900" id="modalTitle">Thêm Danh Mục Mới</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Thông tin danh mục phân loại mã nguồn</p>
                </div>
            </div>
            <button type="button" onclick="closeCatModal()" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors shrink-0" title="Đóng (Esc)">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-5 mt-6">
            <input type="hidden" name="action_save" value="1">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="category_id" id="catId" value="0">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Tên Danh Mục *</label>
                <input type="text" name="name" id="catName" required placeholder="Ví dụ: PHP & Laravel, Game Unity, Flutter App..." class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Đường Dẫn (Slug)</label>
                    <input type="text" name="slug" id="catSlug" placeholder="Tự động tạo nếu để trống" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm font-mono text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">FontAwesome Icon Class</label>
                    <input type="text" name="icon" id="catIcon" value="fa-brands fa-php" placeholder="fa-brands fa-php" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm font-mono text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Mô Tả Ngắn</label>
                <textarea name="description" id="catDesc" rows="3" placeholder="Mô tả tóm tắt về loại mã nguồn trong danh mục này..." class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Thứ Tự Sắp Xếp</label>
                    <input type="number" name="sort_order" id="catSort" value="0" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition-all">
                </div>
                <div class="pt-2 sm:pt-6">
                    <label class="flex items-center gap-2.5 cursor-pointer text-xs font-bold text-slate-700 select-none">
                        <input type="checkbox" name="status" id="catStatus" checked class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                        <span>Hiển thị danh mục ra ngoài</span>
                    </label>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="pt-5 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="button" onclick="closeCatModal()" class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                    Hủy
                </button>
                <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-500/25 transition-all">
                    Lưu Danh Mục
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function ensureModalInBody() {
        var modal = document.getElementById('catModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        return modal;
    }

    document.addEventListener('DOMContentLoaded', ensureModalInBody);

    function openCategoryModal() {
        var modal = ensureModalInBody();
        document.getElementById('modalTitle').innerText = 'Thêm Danh Mục Mới';
        document.getElementById('catId').value = 0;
        document.getElementById('catName').value = '';
        document.getElementById('catSlug').value = '';
        document.getElementById('catIcon').value = 'fa-solid fa-code';
        document.getElementById('catDesc').value = '';
        document.getElementById('catSort').value = 0;
        document.getElementById('catStatus').checked = true;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function editCategory(cat) {
        var modal = ensureModalInBody();
        document.getElementById('modalTitle').innerText = 'Chỉnh Sửa Danh Mục: ' + cat.name;
        document.getElementById('catId').value = cat.id;
        document.getElementById('catName').value = cat.name;
        document.getElementById('catSlug').value = cat.slug;
        document.getElementById('catIcon').value = cat.icon || 'fa-solid fa-code';
        document.getElementById('catDesc').value = cat.description || '';
        document.getElementById('catSort').value = cat.sort_order;
        document.getElementById('catStatus').checked = (cat.status == 1);
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeCatModal() {
        var modal = document.getElementById('catModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        document.body.classList.remove('overflow-hidden');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCatModal();
        }
    });

    function confirmDeleteCat(id, name) {
        Swal.fire({
            title: 'Xác Nhận Xóa Danh Mục',
            text: `Bạn có chắc muốn xóa danh mục "${name}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Đồng ý xóa',
            cancelButtonText: 'Hủy bỏ',
            confirmButtonColor: '#e11d48'
        }).then((res) => {
            if (res.isConfirmed) {
                window.location.href = '<?= BASE_URL ?>/admin/categories?delete_id=' + id;
            }
        });
    }
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
