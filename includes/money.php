<?php
declare(strict_types=1);

/** Exact non-negative DECIMAL(15,2) amounts, represented as integer cents. */
function money_minor(mixed $value): int
{
    if (PHP_INT_SIZE < 8 || (!is_string($value) && !is_int($value))) {
        throw new InvalidArgumentException('Invalid monetary value');
    }
    $value = (string)$value;
    if (!preg_match('/\A([0-9]{1,13})(?:\.([0-9]{1,2}))?\z/D', $value, $matches)) {
        throw new InvalidArgumentException('Invalid monetary value');
    }
    return (int)$matches[1] * 100 + (int)str_pad($matches[2] ?? '', 2, '0');
}

function money_decimal(int $minor): string
{
    if ($minor < 0 || $minor > 999999999999999) {
        throw new OverflowException('Monetary value out of range');
    }
    return intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
}

function money_add(int $left, int $right): int
{
    $sum = $left + $right;
    money_decimal($sum);
    return $sum;
}
