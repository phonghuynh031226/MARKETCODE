<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Phương thức không hợp lệ!');
}

if (!is_logged()) {
    json_response(false, 'Vui lòng đăng nhập tài khoản để mua mã nguồn!');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    json_response(false, 'Phiên làm việc hết hạn hoặc mã bảo mật không đúng! Vui lòng tải lại trang.');
}

$productId = (int)($_POST['product_id'] ?? 0);
if ($productId <= 0) {
    json_response(false, 'Mã nguồn không hợp lệ!');
}

if (!$db) {
    json_response(false, 'Lỗi kết nối cơ sở dữ liệu!');
}

$user = current_user();

try {
    // 1. Kiểm tra sản phẩm
    $pStmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active' LIMIT 1");
    $pStmt->execute([$productId]);
    $product = $pStmt->fetch();

    if (!$product) {
        json_response(false, 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh!');
    }

    $finalPrice = (!empty($product['sale_price']) && $product['sale_price'] > 0) ? (float)$product['sale_price'] : (float)$product['price'];

    // 2. Kiểm tra số dư người dùng
    if ((float)$user['balance'] < $finalPrice) {
        json_response(false, 'Số dư của bạn (' . format_money($user['balance']) . ') không đủ để mua mã nguồn này (' . format_money($finalPrice) . '). Vui lòng nạp thêm tiền!', [
            'insufficient_balance' => true,
            'current_balance'     => $user['balance'],
            'required_price'      => $finalPrice
        ]);
    }

    // 3. Khởi tạo Database Transaction để bảo toàn dữ liệu (Atomic Operation)
    $db->beginTransaction();

    // Trừ tiền user có kiểm tra số dư tại tầng database (chống Race Condition)
    $deductStmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
    $deductStmt->execute([$finalPrice, $user['id'], $finalPrice]);

    if ($deductStmt->rowCount() === 0) {
        $db->rollBack();
        json_response(false, 'Giao dịch thất bại: Số dư tài khoản không đủ hoặc đang có giao dịch song song!');
    }

    // Ghi nhật ký biến động số dư (balance_logs)
    $amountBefore = (float)$user['balance'];
    $amountAfter  = $amountBefore - $finalPrice;
    $logStmt = $db->prepare("INSERT INTO balance_logs (user_id, amount_before, amount, amount_after, type, note) VALUES (?, ?, ?, ?, 'buy_code', ?)");
    $logStmt->execute([$user['id'], $amountBefore, -$finalPrice, $amountAfter, 'Mua mã nguồn: ' . $product['title']]);

    // Tạo mã đơn hàng & download token ngẫu nhiên bảo mật 64 ký tự hex
    $orderCode = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
    $downloadToken = bin2hex(random_bytes(32));

    // Tạo đơn hàng
    $orderStmt = $db->prepare("INSERT INTO orders (order_code, user_id, product_id, price, download_token, status) VALUES (?, ?, ?, ?, ?, 'completed')");
    $orderStmt->execute([$orderCode, $user['id'], $product['id'], $finalPrice, $downloadToken]);
    $orderId = (int)$db->lastInsertId();

    // Cập nhật lượt tải sản phẩm
    $upStmt = $db->prepare("UPDATE products SET download_count = download_count + 1 WHERE id = ?");
    $upStmt->execute([$product['id']]);

    // Commit toàn bộ giao dịch
    $db->commit();

    json_response(true, 'Mua mã nguồn thành công! Bạn có thể tải mã nguồn ngay bây giờ.', [
        'order_id'       => $orderId,
        'order_code'     => $orderCode,
        'download_token' => $downloadToken,
        'download_url'   => BASE_URL . '/download.php?order_id=' . $orderId
    ]);

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    json_response(false, 'Lỗi hệ thống khi thanh toán: ' . $e->getMessage());
}
