<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/money.php';

try {
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Phương thức không hợp lệ!');
}

if (!is_logged()) {
    json_response(false, 'Vui lòng đăng nhập tài khoản để mua mã nguồn!');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken) || !verify_csrf_token($csrfToken)) {
    json_response(false, 'Phiên làm việc hết hạn hoặc mã bảo mật không đúng! Vui lòng tải lại trang.');
}

$productId = $db ? public_id_to_internal($db, 'products', $_POST['product_id'] ?? null) : 0;
if ($productId <= 0) {
    json_response(false, 'Mã nguồn không hợp lệ!');
}

if (!$db) {
    json_response(false, 'Lỗi kết nối cơ sở dữ liệu!');
}

$user = current_user();

try {
    $db->beginTransaction();
    // 1. Kiểm tra sản phẩm
    $pStmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1 FOR UPDATE");
    $pStmt->execute([$productId]);
    $product = $pStmt->fetch();

    if (!$product) {
        $db->rollBack();
        json_response(false, 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh!');
    }

    $baseMinor = money_minor($product['price']);
    $saleMinor = $product['sale_price'] === null ? 0 : money_minor($product['sale_price']);
    $priceMinor = $saleMinor > 0 ? $saleMinor : $baseMinor;
    $finalPrice = money_decimal($priceMinor);
    $locked = $db->prepare("SELECT id, balance FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL FOR UPDATE");
    $locked->execute([$user['id']]);
    $lockedUser = $locked->fetch();
    if (!$lockedUser) { throw new RuntimeException('Purchase user unavailable'); }
    $beforeMinor = money_minor($lockedUser['balance']);
    if ($beforeMinor < $priceMinor) {
        $db->rollBack();
        json_response(false, 'Số dư không đủ. Vui lòng nạp thêm tiền!', ['insufficient_balance' => true, 'current_balance' => money_decimal($beforeMinor), 'required_price' => $finalPrice]);
    }
    $amountBefore = money_decimal($beforeMinor);
    $amountAfter = money_decimal($beforeMinor - $priceMinor);
    $deductStmt = $db->prepare('UPDATE users SET balance = ? WHERE id = ?');
    $deductStmt->execute([$amountAfter, $user['id']]);

    // Ghi nhật ký biến động số dư (balance_logs)
    $logStmt = $db->prepare("INSERT INTO balance_logs (user_id, amount_before, amount, amount_after, type, note) VALUES (?, ?, ?, ?, 'buy_code', ?)");
    $logStmt->execute([$user['id'], $amountBefore, ($priceMinor === 0 ? '0.00' : '-' . $finalPrice), $amountAfter, 'Mua mã nguồn: ' . $product['title']]);

    // Tạo mã đơn hàng & download token ngẫu nhiên bảo mật 64 ký tự hex
    $orderPublicId = uuid_v4();
    $orderCode = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
    $downloadToken = bin2hex(random_bytes(32));

    // Tạo đơn hàng
    $orderStmt = $db->prepare("INSERT INTO orders (public_id, order_code, user_id, product_id, price, download_token, status) VALUES (?, ?, ?, ?, ?, ?, 'completed')");
    $orderStmt->execute([$orderPublicId, $orderCode, $user['id'], $product['id'], $finalPrice, $downloadToken]);
    $orderId = (int)$db->lastInsertId();

    // Cập nhật lượt tải sản phẩm
    $upStmt = $db->prepare("UPDATE products SET download_count = download_count + 1 WHERE id = ?");
    $upStmt->execute([$product['id']]);

    // Commit toàn bộ giao dịch
    $db->commit();

    json_response(true, 'Mua mã nguồn thành công! Bạn có thể tải mã nguồn ngay bây giờ.', [
        'order_id'       => $orderPublicId,
        'order_code'     => $orderCode,
        'download_token' => $downloadToken,
        'download_url'   => BASE_URL . '/download.php?order_id=' . $orderPublicId
    ]);

} catch (Throwable $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    json_response(false, 'Lỗi hệ thống khi thanh toán: ' . public_error($e));
}

} catch (Throwable $unexpected) {
    error_log('Payment handler failed: ' . $unexpected->getMessage());
    try {
        $connection = $pdo ?? $db ?? null;
        if ($connection instanceof PDO && $connection->inTransaction()) { $connection->rollBack(); }
    } catch (Throwable $rollbackError) { error_log('Payment rollback failed: ' . $rollbackError->getMessage()); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
