<?php
declare(strict_types=1);
/**
 * ========================================================
 * PHP Built-in Server Router (Clean URLs / Pretty Routing)
 * ========================================================
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$urlParts   = parse_url($requestUri);
$path       = $urlParts['path'] ?? '/';
$query      = isset($urlParts['query']) && $urlParts['query'] !== '' ? '?' . $urlParts['query'] : '';
$isPost     = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$path = rawurldecode($path);

// 2. Chặn truy cập trực tiếp vào các thư mục nhạy cảm
if (preg_match('#^/(config|includes|brain|database|docs|tests|storage|delivery|\.git|\.codex|\.agents)(/|$)#i', $path)
    || preg_match('#(^|/)\.|\.(sql|log|bak|old|ini|env|zip|tar|gz)$#i', $path)
    || preg_match('#^/assets/.*\.(php[0-9]?|phtml|phar)(/|$)#i', $path)
    || $path === '/router.php') {
    http_response_code(403);
    echo "<h1>403 Forbidden</h1><p>Truy cập bị từ chối.</p>";
    exit;
}

if ($path === '/forgot-password' || $path === '/reset-password') {
    $file = $path === '/forgot-password' ? '/forgot_password.php' : '/reset_password.php';
    $_SERVER['SCRIPT_NAME'] = $file;
    $_SERVER['PHP_SELF'] = $file;
    require __DIR__ . $file;
    exit;
}


// 1. Phục vụ trực tiếp các file tĩnh (CSS, JS, Hình ảnh, Font, Icons...)
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$staticExtensions = [
    'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
    'woff', 'woff2', 'ttf', 'eot', 'otf', 'json', 'map', 'pdf', 'txt', 'zip'
];

if (in_array($extension, $staticExtensions)) {
    $filePath = __DIR__ . $path;
    if (file_exists($filePath) && is_file($filePath)) {
        return false; // Để PHP server tự động phục vụ file tĩnh
    }
}

// 3. Tự động chuyển hướng (301 Redirect) nếu người dùng gõ trực tiếp /index.php -> /
if (!$isPost) {
    if ($path === '/index.php') {
        header("Location: /" . $query, true, 301);
        exit;
    }
    if ($path === '/admin/index.php') {
        header("Location: /admin/" . $query, true, 301);
        exit;
    }

    // 4. Nếu truy cập đường dẫn có đuôi .php (trừ /api/ và /ajax/) -> Chuyển hướng sang Clean URL
    if (str_ends_with($path, '.php') && !preg_match('#^/(api|ajax)/#i', $path)) {
        $cleanPath = substr($path, 0, -4);
        // Chuẩn hóa /recharge thành /deposit
        if ($cleanPath === '/recharge') {
            $cleanPath = '/deposit';
        }
        header("Location: " . $cleanPath . $query, true, 301);
        exit;
    }
}

// 5. Điều hướng Clean URLs tới file PHP tương ứng

// Trang chủ
if ($path === '/' || $path === '') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF']    = '/index.php';
    require __DIR__ . '/index.php';
    exit;
}

// Nạp tiền
if ($path === '/deposit' || $path === '/recharge') {
    $_SERVER['SCRIPT_NAME'] = '/recharge.php';
    $_SERVER['PHP_SELF']    = '/recharge.php';
    require __DIR__ . '/recharge.php';
    exit;
}

// Đăng ký
if ($path === '/register') {
    $_GET['tab'] = 'register';
    $_SERVER['SCRIPT_NAME'] = '/login.php';
    $_SERVER['PHP_SELF']    = '/login.php';
    require __DIR__ . '/login.php';
    exit;
}

// Pretty URLs cho Product / Code
$cleanPath = trim($path, '/');
if (preg_match('#^(product|code)/([0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})$#', $cleanPath, $matches)) {
    $_GET['id'] = $matches[2];
    $_SERVER['SCRIPT_NAME'] = '/product.php';
    $_SERVER['PHP_SELF']    = '/product.php';
    require __DIR__ . '/product.php';
    exit;
}
if (preg_match('#^(product|code)/([a-zA-Z0-9_\-]+)$#', $cleanPath, $matches)) {
    $_GET['slug'] = $matches[2];
    $_SERVER['SCRIPT_NAME'] = '/product.php';
    $_SERVER['PHP_SELF']    = '/product.php';
    require __DIR__ . '/product.php';
    exit;
}

// Pretty URLs cho Categories
if (preg_match('#^categories/([a-zA-Z0-9_\-]+)$#', $cleanPath, $matches)) {
    $_GET['slug'] = $matches[1];
    $_GET['cat']  = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/categories.php';
    $_SERVER['PHP_SELF']    = '/categories.php';
    require __DIR__ . '/categories.php';
    exit;
}

// Trang Admin
if ($path === '/admin' || $path === '/admin/') {
    $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
    $_SERVER['PHP_SELF']    = '/admin/index.php';
    require __DIR__ . '/admin/index.php';
    exit;
}

if (str_starts_with($path, '/admin/')) {
    $adminSub = trim(substr($path, 7), '/'); // Bỏ tiền tố '/admin/' và dấu / thừa
    if ($adminSub === 'deposits') {
        $adminSub = 'transactions';
    }
    $adminFile = __DIR__ . '/admin/' . $adminSub . '.php';
    if (file_exists($adminFile) && is_file($adminFile)) {
        $_SERVER['SCRIPT_NAME'] = '/admin/' . $adminSub . '.php';
        $_SERVER['PHP_SELF']    = '/admin/' . $adminSub . '.php';
        require $adminFile;
        exit;
    }
}

// Các trang con khác ở thư mục gốc (categories, warranty, login, product, history, profile, download...)
$targetFile = __DIR__ . $path . '.php';
if (file_exists($targetFile) && is_file($targetFile)) {
    $_SERVER['SCRIPT_NAME'] = $path . '.php';
    $_SERVER['PHP_SELF']    = $path . '.php';
    require $targetFile;
    exit;
}

// File vật lý tồn tại trực tiếp (ví dụ /api/xxx.php, /ajax/xxx.php)
$directFile = __DIR__ . $path;
if (file_exists($directFile) && is_file($directFile)) {
    $_SERVER['SCRIPT_NAME'] = $path;
    $_SERVER['PHP_SELF']    = $path;
    require $directFile;
    exit;
}

// Không tìm thấy đường dẫn -> 404
http_response_code(404);
echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>404 Not Found</title></head><body class='bg-slate-50 min-h-screen flex items-center justify-center p-4 text-center font-sans'><div class='max-w-md w-full bg-white p-8 rounded-3xl border border-slate-200 shadow-xl space-y-4'><div class='w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl font-bold mx-auto'>404</div><h1 class='text-xl font-extrabold text-slate-900'>Không Tìm Thấy Trang</h1><p class='text-xs text-slate-500'>Đường dẫn bạn yêu cầu không tồn tại hoặc đã được thay đổi.</p><a href='/' class='inline-block px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700 transition-colors shadow-sm'>Về Trang Chủ</a></div></body></html>";
exit;
