<?php
declare(strict_types=1);

function app_password_pepper(): string
{
    $pepper = security_config('PASSWORD_PEPPER');
    if (strlen($pepper) < 32) { throw new RuntimeException('Password secret is not configured'); }
    return $pepper;
}

function app_password_options(): array
{
    return ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];
}

function app_password_hash(string $password, bool $legacyUpgrade = false): string
{
    $pepper = app_password_pepper();
    if (strlen($password) < ($legacyUpgrade ? 1 : 12) || strlen($password) > 1024) { throw new InvalidArgumentException('Invalid password length'); }
    if (!defined('PASSWORD_ARGON2ID') || !in_array('argon2id', password_algos(), true)) {
        throw new RuntimeException('Argon2id is not available');
    }
    return password_hash(hash_hmac('sha256', $password, $pepper), PASSWORD_ARGON2ID, app_password_options());
}

function app_password_verify(string $password, string $hash): bool
{
    $pepper = app_password_pepper();
    if ($password === '' || strlen($password) > 1024) { return false; }
    $algorithm = password_get_info($hash)['algoName'];
    // Explicit compatibility only: all successful legacy logins must replace this hash.
    // Preserve bounded legacy verification semantics; successful login immediately upgrades
    // the complete submitted password to peppered Argon2id, which has no bcrypt truncation.
    if ($algorithm === 'bcrypt') { return password_verify($password, $hash); }
    if ($algorithm !== 'argon2id') { return false; }
    return password_verify(hash_hmac('sha256', $password, $pepper), $hash);
}

function app_password_needs_rehash(string $hash): bool
{
    if (!defined('PASSWORD_ARGON2ID') || !in_array('argon2id', password_algos(), true)) {
        throw new RuntimeException('Argon2id is not available');
    }
    return password_needs_rehash($hash, PASSWORD_ARGON2ID, app_password_options());
}
