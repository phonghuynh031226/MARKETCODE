<?php
declare(strict_types=1);

// Decode pixels and encode a new PNG. No original EXIF, comments, scripts or filenames survive.
function reconstruct_image(string $source, string $directory, int $maxBytes = 5242880): string
{
    if (!is_file($source) || filesize($source) <= 0 || filesize($source) > $maxBytes) {
        throw new InvalidArgumentException('Ảnh vượt quá kích thước cho phép.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
        throw new InvalidArgumentException('Chỉ chấp nhận ảnh PNG, JPG, WEBP hoặc GIF.');
    }
    $info = @getimagesize($source);
    if (!is_array($info) || $info[0] <= 0 || $info[1] <= 0 || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 12000000) {
        throw new InvalidArgumentException('Độ phân giải ảnh vượt quá giới hạn.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create image directory');
    }
    $name = bin2hex(random_bytes(16)) . '.png';
    $destination = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;
    try {
        if (extension_loaded('gd')) {
            $pixels = @imagecreatefromstring((string)file_get_contents($source));
            if ($pixels === false) { throw new InvalidArgumentException('Không thể giải mã ảnh.'); }
            try {
                imagealphablending($pixels, false);
                imagesavealpha($pixels, true);
                if (!imagepng($pixels, $destination, 6)) { throw new RuntimeException('Unable to encode image'); }
            } finally { imagedestroy($pixels); }
        } elseif (extension_loaded('imagick')) {
            $image = new Imagick();
            try {
                $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 64 * 1024 * 1024);
                $image->setResourceLimit(Imagick::RESOURCETYPE_MAP, 128 * 1024 * 1024);
                $image->readImageBlob((string)file_get_contents($source));
                $image->setIteratorIndex(0);
                $image->stripImage();
                $image->setImageFormat('png');
                if (!$image->writeImage($destination)) { throw new RuntimeException('Unable to encode image'); }
            } finally { $image->clear(); $image->destroy(); }
        } else {
            throw new RuntimeException('Image reconstruction requires GD or Imagick; original upload was not saved');
        }
        chmod($destination, 0640);
        return $name;
    } catch (Throwable $error) {
        if (is_file($destination)) { unlink($destination); }
        throw $error;
    }
}

function store_uploaded_image(array $upload, string $directory, int $maxBytes = 5242880): string
{
    if (($upload['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null)
        || !is_uploaded_file($upload['tmp_name'])) { throw new InvalidArgumentException('Tải ảnh không hợp lệ.'); }
    return reconstruct_image($upload['tmp_name'], $directory, $maxBytes);
}
