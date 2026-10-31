<?php
declare(strict_types=1);
require __DIR__ . '/../includes/uploads.php';
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'marketcode-image-' . bin2hex(random_bytes(12));
mkdir($root, 0700);
$source = $root . '/input.bin';
try {
    file_put_contents($source, '<?php echo "disguised";');
    try { reconstruct_image($source, $root); throw new LogicException('Fake image accepted'); }
    catch (InvalidArgumentException) {}
    file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    if (!extension_loaded('gd') && !extension_loaded('imagick')) {
        try { reconstruct_image($source, $root); throw new LogicException('No image encoder accepted'); }
        catch (RuntimeException $error) { if ($error instanceof LogicException) { throw $error; } }
        echo "PASS: upload rejects fake images and fails closed without an encoder\n";
    } elseif (extension_loaded('gd')) {
        $pixels = imagecreatetruecolor(2, 2);
        imagepng($pixels, $source); imagedestroy($pixels);
        file_put_contents($source, '<?php malicious_tail(); ?> GPS-PRIVATE-METADATA', FILE_APPEND);
        $name = reconstruct_image($source, $root);
        if (!preg_match('/\A[0-9a-f]{32}\.png\z/D', $name)) { throw new RuntimeException('Filename entropy missing'); }
        $encoded = file_get_contents($root . '/' . $name);
        if (str_contains($encoded, 'malicious_tail') || str_contains($encoded, 'GPS-PRIVATE')) { throw new RuntimeException('Source data survived reconstruction'); }
        if ((new finfo(FILEINFO_MIME_TYPE))->file($root . '/' . $name) !== 'image/png') { throw new RuntimeException('Output is not PNG'); }
        $second = reconstruct_image($source, $root);
        if (hash_equals($name, $second)) { throw new RuntimeException('Duplicate randomized name'); }
        unlink($root . '/' . $name); unlink($root . '/' . $second);
        echo "PASS: uploads decode/re-encode pixels, strip appended data, randomize filenames\n";
    }
} finally {
    if (is_file($source)) { unlink($source); }
    rmdir($root);
}
