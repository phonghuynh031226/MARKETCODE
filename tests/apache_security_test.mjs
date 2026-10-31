import { spawn } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
const home = (process.env.APACHE_HOME || 'C:/xampp/apache').replaceAll('\\', '/');
const root = fileURLToPath(new URL('..', import.meta.url)).replaceAll('\\', '/').replace(/\/$/, '');
const temp = mkdtempSync(path.join(tmpdir(), 'marketcode-apache-'));
const config = path.join(temp, 'httpd.conf');
const port = 27000 + Math.floor(Math.random() * 1000);
const modules = ['authz_core', 'authz_host', 'access_compat', 'rewrite', 'dir'];
writeFileSync(config, `ServerRoot "${home}"
Listen 127.0.0.1:${port}
ServerName localhost
PidFile "${temp.replaceAll('\\', '/')}/httpd.pid"
ErrorLog "${temp.replaceAll('\\', '/')}/error.log"
${modules.map(m => `LoadModule ${m}_module modules/mod_${m}.so`).join('\n')}
DocumentRoot "${root}"
<Directory "${root}">
AllowOverride All
Require all granted
<FilesMatch "\\.php$">
Require all denied
</FilesMatch>
</Directory>
`);
const server = spawn(home + '/bin/httpd.exe', ['-f', config, '-X'], { stdio: 'ignore' });
try {
    let ready = false;
    for (let i = 0; i < 80; i++) { try { await fetch(`http://127.0.0.1:${port}/assets/css/animations.css`); ready = true; break; } catch { await new Promise(r => setTimeout(r, 50)); } }
    assert.ok(ready, 'Apache starts');
    let checks = 0;
    for (const target of ['/database.sql', '/.git/config', '/storage/rate-limits/example.json', '/config/database.local.php', '/tests/security_test.php', '/database/migrations/2026_10_27_create_momo_transactions.sql']) {
        const response = await fetch(`http://127.0.0.1:${port}${target}`);
        assert.equal(response.status, 403, target + ': ' + await response.text()); checks++;
    }
    assert.equal((await fetch(`http://127.0.0.1:${port}/assets/css/animations.css`)).status, 200); checks++;
    console.log(`PASS: ${checks} Apache .htaccess checks`);
} finally {
    server.kill(); await new Promise(resolve => server.once('close', resolve));
    if (path.resolve(temp).startsWith(path.resolve(tmpdir()) + path.sep) && path.basename(temp).startsWith('marketcode-apache-')) rmSync(temp, { recursive: true, force: true });
}
