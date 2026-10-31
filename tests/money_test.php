<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/money.php';
$checks = 0;
function money_check(bool $condition): void { global $checks; if (!$condition) { throw new RuntimeException('Money test failed'); } $checks++; }
foreach (['0' => 0, '0.01' => 1, '10.2' => 1020, '9999999999999.99' => 999999999999999] as $input => $expected) {
    money_check(money_minor((string)$input) === $expected);
    money_check(money_minor(money_decimal($expected)) === $expected);
}
money_check(money_decimal(money_add(money_minor('0.10'), money_minor('0.20'))) === '0.30');
money_check(money_decimal(money_minor('100.00') - money_minor('99.99')) === '0.01');
money_check(money_decimal(10000 * 85) === '8500.00');
foreach (['-1', '1e3', 'NaN', '1.001', '10000000000000', '', [], 0.1, null, true] as $invalid) {
    try { money_minor($invalid); throw new RuntimeException('Accepted invalid amount'); }
    catch (InvalidArgumentException $expected) { $checks++; }
}
try { money_add(999999999999999, 1); throw new RuntimeException('Accepted overflow'); }
catch (OverflowException $expected) { $checks++; }
echo "Passed $checks money checks\n";
