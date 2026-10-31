<?php
declare(strict_types=1);

function e(mixed $value): string
{
    if ($value !== null && !is_scalar($value)) { throw new InvalidArgumentException('Invalid output value'); }
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function js(mixed $value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}

function safe_url(mixed $value, bool $allowRelative = true): string
{
    if ($value !== null && !is_string($value)) { return ''; }
    $url = (string)($value ?? '');
    if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
        return '';
    }
    $decoded = rawurldecode($url);
    if (preg_match('/[\x00-\x20\x7f\\\\]/', $decoded)) {
        return '';
    }
    $parts = parse_url($url);
    if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
        return '';
    }
    if (isset($parts['scheme'])) {
        return in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && !empty($parts['host']) && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : '';
    }
    if (!$allowRelative || isset($parts['host']) || str_starts_with($decoded, '//')
        || str_contains($decoded, ':') || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $decoded)) {
        return '';
    }
    return $url;
}
