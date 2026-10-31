<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/security.php';
$helper = __DIR__ . '/../includes/passwords.php';
if (!is_file($helper)) { fwrite(STDERR, "FAIL: password helper is missing\n"); exit(1); }
require_once $helper;
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
putenv('PASSWORD_PEPPER'); unset($_ENV['PASSWORD_PEPPER']);
try { app_password_hash('a sufficiently long password'); throw new LogicException('Missing pepper accepted'); } catch (RuntimeException $e) { check(!$e instanceof LogicException, $e->getMessage()); }
putenv('PASSWORD_PEPPER=' . str_repeat('p', 40));
check(app_password_verify('legacy password', password_hash('legacy password', PASSWORD_BCRYPT)), 'Legacy bcrypt login rejected');
check(!app_password_verify('wrong', password_hash('legacy password', PASSWORD_BCRYPT)), 'Wrong legacy password accepted');
check(app_password_verify(str_repeat('L', 90), password_hash(str_repeat('L', 90), PASSWORD_BCRYPT)), 'Original long legacy password cannot be upgraded');
check(!app_password_verify(str_repeat('x', 1025), 'invalid'), 'Overlong password accepted');
check(!app_password_verify('password', 'invalid'), 'Unknown hash accepted');
if (!defined('PASSWORD_ARGON2ID') || !in_array('argon2id', password_algos(), true)) {
    try { app_password_hash('a sufficiently long password'); throw new LogicException('Missing Argon2 accepted'); } catch (RuntimeException $e) { check(!$e instanceof LogicException, $e->getMessage()); }
    echo "PASS: fail closed, legacy verification; SKIP: Argon2id unavailable in this PHP build\n"; exit;
}
$password = str_repeat('a', 80) . 'one';
$hash = app_password_hash($password);
check(str_starts_with($hash, '$argon2id$'), 'New hash is not Argon2id');
check(app_password_verify($password, $hash), 'Password rejected');
check(!app_password_verify(str_repeat('a', 80) . 'two', $hash), 'Password truncated');
check(!password_verify($password, $hash), 'Password was not peppered');
check(!app_password_needs_rehash($hash), 'Fresh hash needs rehash');
check(app_password_needs_rehash(password_hash('legacy', PASSWORD_BCRYPT)), 'Legacy hash does not need upgrade');
putenv('PASSWORD_PEPPER=' . str_repeat('q', 40));
check(!app_password_verify($password, $hash), 'Wrong pepper accepted');
putenv('PASSWORD_PEPPER');
try { app_password_verify('legacy', password_hash('legacy', PASSWORD_BCRYPT)); throw new LogicException('Legacy accepted without pepper'); } catch (RuntimeException $e) { check(!$e instanceof LogicException, $e->getMessage()); }
echo "PASS: password hashing, pepper, legacy migration and boundaries\n";
