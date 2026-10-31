<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/output.php';

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

check(e('<"\'&>') === '&lt;&quot;&#039;&amp;&gt;', 'HTML metacharacters');
check(str_contains(e("broken\xFF"), "\xEF\xBF\xBD"), 'Invalid UTF-8 substitution');
$attack = '</script><script>alert("x")</script>';
check(!str_contains(js($attack), '<'), 'Script closing tag encoded');
check(json_decode(js($attack), true, 512, JSON_THROW_ON_ERROR) === $attack, 'JSON round trip');
check(!str_contains(e(js('" onclick="alert(1)')), '"'), 'Attribute JSON quotes encoded');
foreach (['javascript:alert(1)', 'data:text/html,<script>', '//evil.test/x', '/\\evil.test', "https://good.test/\n", 'https://user:pass@host.test/', 'https://host.test/%0aevil', '/%2f/evil.test', '../secret', '/%2e%2e/secret'] as $bad) {
    check(safe_url($bad) === '', 'Reject unsafe URL: ' . $bad);
}
foreach (['https://example.com/a?q=1&b=2', 'http://example.com/', '/assets/image.png', 'uploads/image.png', '/categories?slug=test'] as $good) {
    check(safe_url($good) === $good, 'Allow safe URL: ' . $good);
}
check(safe_url('/assets/image.png', false) === '', 'Absolute URL only');
try {
    js("\xFF");
    throw new RuntimeException('Invalid JSON UTF-8 must throw');
} catch (JsonException $expected) {
}
echo "Output encoding tests passed.\n";
