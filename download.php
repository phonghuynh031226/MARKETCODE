<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$user = current_user();
$orderId = $db ? public_id_to_internal($db, 'orders', $_GET['order_id'] ?? null) : 0;
$token = trim($_GET['token'] ?? '');

if (!$db) {
    die('Lỗi kết nối cơ sở dữ liệu!');
}

$order = null;
try {
    if ($orderId > 0) {
        $stmt = $db->prepare("SELECT o.*, p.title as product_title, p.download_url, p.file_size
                              FROM orders o
                              JOIN products p ON o.product_id = p.id
                              WHERE o.id = ? AND o.user_id = ? AND o.status = 'completed' AND o.deleted_at IS NULL LIMIT 1");
        $stmt->execute([$orderId, $user['id']]);
        $order = $stmt->fetch();
    } elseif (!empty($token)) {
        $stmt = $db->prepare("SELECT o.*, p.title as product_title, p.download_url, p.file_size
                              FROM orders o
                              JOIN products p ON o.product_id = p.id
                              WHERE o.download_token = ? AND o.user_id = ? AND o.status = 'completed' AND o.deleted_at IS NULL LIMIT 1");
        $stmt->execute([$token, $user['id']]);
        $order = $stmt->fetch();
    }
} catch (Exception $e) {
    die('Lỗi tra cứu đơn hàng: ' . public_error($e));
}

if (!$order) {
    http_response_code(404);
    die('<!DOCTYPE html>
    <html lang="vi">
    <head><meta charset="UTF-8"><title>Không tìm thấy file</title></head>
    <body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center space-y-4">
            <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-500 border border-rose-200 flex items-center justify-center mx-auto text-2xl font-bold">✕</div>
            <h2 class="text-xl font-extrabold text-slate-900">Không Tìm Thấy Quyền Tải Mã Nguồn</h2>
            <p class="text-xs text-slate-500">Bạn chưa mua mã nguồn này hoặc liên kết tải về không hợp lệ. Vui lòng kiểm tra lại đơn hàng.</p>
            <a href="' . BASE_URL . '/history?tab=orders" class="inline-block px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition-colors shadow-sm">Xem Lịch Sử Mua Hàng</a>
        </div>
    </body>
    </html>');
}

if ($token !== '' && !hash_equals((string)$order['download_token'], $token)) { http_response_code(404); exit('Không tìm thấy quyền tải.'); }
$downloadUrl = (string)$order['download_url'];

// Local paid files may only be read from private storage/downloads.
if (!preg_match('#^https?://#i', $downloadUrl)) {
    if (!preg_match('/\A[a-zA-Z0-9._-]{1,160}\.(zip|7z|rar|pdf)\z/iD', basename($downloadUrl))) {
        http_response_code(404); exit('Tệp tải xuống không hợp lệ.');
    }
    $privateRoot = realpath(ROOT_PATH . '/storage/downloads');
    $filePath = $privateRoot !== false ? realpath($privateRoot . DIRECTORY_SEPARATOR . basename($downloadUrl)) : false;
    if ($privateRoot === false || $filePath === false || !str_starts_with($filePath, $privateRoot . DIRECTORY_SEPARATOR)
        || !is_file($filePath) || !in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['zip', '7z', 'rar', 'pdf'], true)) {
        http_response_code(404); exit('Tệp tải xuống không hợp lệ.');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . (string)filesize($filePath));
    readfile($filePath); exit;
}
$downloadUrl = safe_url($downloadUrl, false);
if ($downloadUrl === '') { http_response_code(404); exit('Liên kết tải không hợp lệ.'); }

// Nếu là liên kết ngoài (Google Drive / Mega / Dropbox) -> Hiển thị trang trung chuyển an toàn có đếm ngược
$pageTitle = "Tải Mã Nguồn: " . $order['product_title'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex items-center justify-center py-16 px-4">
    <div class="max-w-md w-full rounded-3xl bg-white p-8 border border-slate-200/80 shadow-2xl text-center space-y-6">
        <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center text-3xl mx-auto shadow-sm">
            <i class="fa-solid fa-cloud-arrow-down"></i>
        </div>

        <div class="space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Bảo Mật Xác Thực Thành Công</span>
            <h1 class="text-xl font-extrabold text-slate-900 line-clamp-2">
                <?= xss_clean($order['product_title']) ?>
            </h1>
            <p class="text-xs text-slate-500 font-mono">Đơn hàng: #<?= xss_clean($order['order_code']) ?> • Trạng thái: Đã thanh toán</p>
        </div>

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
            <span class="text-xs text-slate-500 block">Tự động chuyển hướng đến link tải sau:</span>
            <div id="countdownNumber" class="text-4xl font-extrabold text-indigo-600">3</div>
            <span class="text-[10px] text-slate-400">Nếu trình duyệt không tự tải, vui lòng bấm nút bên dưới.</span>
        </div>

        <div class="space-y-3">
            <a id="btnDirectDownload" href="<?= e($downloadUrl) ?>" target="_blank" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-sm shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2 transition-all">
                <i class="fa-solid fa-download"></i>
                <span>Tải Xuống Trực Tiếp</span>
            </a>
            <a href="<?= e(BASE_URL) ?>/history?tab=orders" class="block text-xs font-bold text-slate-500 hover:text-indigo-600 transition-colors">
                Quay lại danh sách đã mua
            </a>
        </div>
    </div>
</div>

<script>
    let timeLeft = 3;
    const countEl = document.getElementById('countdownNumber');
    const targetUrl = <?= js($downloadUrl) ?>;

    const timer = setInterval(() => {
        timeLeft--;
        if (countEl) countEl.innerText = timeLeft;
        if (timeLeft <= 0) {
            clearInterval(timer);
            window.location.href = targetUrl;
        }
    }, 1000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
