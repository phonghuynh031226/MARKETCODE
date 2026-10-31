import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
const php = process.env.PHP_BINARY || (existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
const port = 19000 + Math.floor(Math.random() * 1000);
const base = `http://127.0.0.1:${port}`;
const temp = mkdtempSync(path.join(tmpdir(), 'marketcode-http-'));
const server = spawn(php, ['-S', `127.0.0.1:${port}`, 'router.php'], { cwd: new URL('..', import.meta.url), env: { ...process.env, RATE_LIMIT_DIRECTORY: temp }, stdio: ['ignore', 'pipe', 'pipe'] });
let diagnostics = '';
server.stderr.on('data', chunk => { diagnostics += chunk.toString(); });
try {
    let ready = false;
    for (let i = 0; i < 60; i++) {
        try { await fetch(base + '/assets/css/animations.css'); ready = true; break; }
        catch (error) { if (i === 59) diagnostics += String(error) + ' ' + String(error.cause); await new Promise(resolve => setTimeout(resolve, 50)); }
    }
    assert.ok(ready, 'PHP server starts: ' + diagnostics);
    let checks = 0;
    for (const path of ['/database.sql', '/database/migrations/2026_10_27_create_momo_transactions.sql', '/config/database.local.php', '/.git/config', '/tests/security_test.php', '/storage/rate-limits/example.json', '/router.php']) {
        assert.equal((await fetch(base + path)).status, 403, `deny ${path}`); checks++;
    }
    for (const path of ['/api/webhook_bank.php', '/api/callback_card.php']) {
        const response = await fetch(base + path, { method: 'POST', body: JSON.stringify({ amount: 100000, status: 1 }), headers: { 'Content-Type': 'application/json' } });
        assert.equal(response.status, 403, `reject unsigned ${path}`);
        assert.equal((await response.json()).success ?? false, false); checks++;
    }
    for (const path of ['/admin/products?delete_id=1', '/admin/users?user_id=1&toggle_role=admin', '/admin/transactions?approve_card_id=1', '/admin/transactions?reject_card_id=1', '/admin/orders?refund_order_id=1']) {
        assert.equal((await fetch(base + path, { redirect: 'manual' })).status, 403, `reject GET mutation ${path}`); checks++;
    }
    for (const target of ['/admin/includes/sidebar.php', '/admin/includes/admin_footer.php']) {
        assert.equal((await fetch(base + target)).status, 404, 'direct admin template rejected'); checks++;
    }
    assert.equal((await fetch(base + '/login?redirect[]=bad')).status, 400, 'array parameter pollution rejected'); checks++;
    const checkout = await fetch(base + '/api/momo_create_payment.php', { method: 'POST' });
    assert.equal(checkout.status, 403);
    assert.equal((await checkout.json()).success, false); checks++;
    const page = await fetch(base + '/forgot-password');
    const cookie = page.headers.get('set-cookie')?.split(';')[0];
    const html = await page.text();
    const token = html.match(/name="csrf_token" value="([^"]+)"/)?.[1];
    assert.ok(cookie && token, 'reset form has cookie and CSRF token; status ' + page.status + '; ' + html.slice(0, 300) + '; ' + diagnostics);
    const recovery = await fetch(base + '/forgot-password', { method: 'POST', headers: { Cookie: cookie }, body: new URLSearchParams({ csrf_token: token, email: 'nobody@example.test' }) });
    const content = await recovery.text();
    assert.ok(content.includes('Khôi phục mật khẩu đang tạm khóa'), 'unconfigured recovery fails closed');
    assert.ok(!content.includes('OTP đã được tạo:'), 'no OTP displayed'); checks++;
    console.log(`PASS: ${checks} HTTP security checks`);
} finally {
    if (server.exitCode === null && server.signalCode === null) { const closed = new Promise(resolve => server.once('close', resolve)); server.kill(); await closed; }
    if (path.dirname(path.resolve(temp)) === path.resolve(tmpdir()) && path.basename(temp).startsWith('marketcode-http-')) rmSync(temp, { recursive: true, force: true });
}
