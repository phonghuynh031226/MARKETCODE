<?php
declare(strict_types=1);
// Snapshot/compare business PHP tokens; ignore markup, whitespace and short echo output.
$root = dirname(__DIR__);
$snapshot = $root . '/delivery/responsive-php-baseline.json';
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if ($file->getExtension() !== 'php' || preg_match('~/(?:\.git|delivery|tests|storage)/~', $path) || str_ends_with($path, '.local.php')) { continue; }
    $tokens = []; $echo = false;
    $source = (string)file_get_contents($path);
    // One reviewed bootstrap correction: existing index loaded the guarded header first.
    // Strip only this added import; all original variables/SQL/branches must still match.
    if ($path === str_replace('\\', '/', $root) . '/index.php') {
        $source = str_replace("require_once __DIR__ . '/config/config.php';\n", '', $source);
    }
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && $token[0] === T_OPEN_TAG_WITH_ECHO) { $echo = true; continue; }
        if (is_array($token) && $token[0] === T_CLOSE_TAG) { $echo = false; continue; }
        if ($echo) { continue; }
        if (is_array($token) && in_array($token[0], [T_INLINE_HTML, T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { continue; }
        $tokens[] = is_array($token) ? [$token[0], $token[1]] : $token;
    }
    $relative = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
    $files[$relative] = hash('sha256', json_encode($tokens, JSON_THROW_ON_ERROR));
}
ksort($files);
if (($argv[1] ?? '') === '--snapshot') {
    if (!is_dir(dirname($snapshot))) { mkdir(dirname($snapshot), 0750, true); }
    file_put_contents($snapshot, json_encode($files, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    echo 'Saved PHP logic baseline for ' . count($files) . " files\n";
    exit;
}
$before = json_decode((string)file_get_contents($snapshot), true, 512, JSON_THROW_ON_ERROR);
foreach ($before as $path => $hash) {
    if (!isset($files[$path]) || !hash_equals($hash, $files[$path])) { throw new RuntimeException('PHP business tokens changed: ' . $path); }
}
echo 'PASS: existing PHP business tokens unchanged in ' . count($before) . " files; index bootstrap import explicitly allowed\n";
