// Isolated Windows/XAMPP security-v2 integration harness. Never uses the configured application DB.
import { spawn } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { randomUUID, createHmac, createHash } from 'node:crypto';
import assert from 'node:assert/strict';
const bin = process.env.MYSQL_BIN || 'C:/xampp/mysql/bin';
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const root = fileURLToPath(new URL('..', import.meta.url));
const temp = mkdtempSync(path.join(tmpdir(), 'marketcode-v2-'));
const dbPort = 23000 + Math.floor(Math.random() * 1000);
const httpPort = 25000 + Math.floor(Math.random() * 1000) * 2;
const secret = 'fixture-only-' + 'a'.repeat(64);
const database = 'security_v2_fixture';
const env = { ...process.env, DB_HOST: '127.0.0.1', DB_PORT: String(dbPort), DB_NAME: database, DB_USER: 'root', DB_PASS: '', BANK_WEBHOOK_HMAC_SECRET: secret, RESET_TOKEN_KEY: secret, PASSWORD_PEPPER: secret, CARD_PARTNER_KEY: secret, CARD_DATA_KEY: secret, RESET_MAIL_FROM: 'fixture@example.test', MOMO_PARTNER_CODE: 'fixture-partner', MOMO_ACCESS_KEY: 'fixture-access', MOMO_SECRET_KEY: secret, RATE_LIMIT_DIRECTORY: path.join(temp, 'test-rate-limits') };
const children = [];
let checks = 0;
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
function run(command, args, input = '', childEnv = env) {
    return new Promise((resolve, reject) => {
        const child = spawn(command, args, { cwd: root, env: childEnv, stdio: ['pipe', 'pipe', 'pipe'] });
        let out = '', err = '';
        child.stdout.on('data', b => { out += b; }); child.stderr.on('data', b => { err += b; });
        child.on('error', reject); child.on('close', code => code === 0 ? resolve(out.replaceAll('\r\n', '\n')) : reject(new Error(`${path.basename(command)} failed (${code}): ${err}\n${out}`)));
        child.stdin.on('error', () => {}); child.stdin.end(input);
    });
}
const sqlArgs = ['--no-defaults', '--host=127.0.0.1', `--port=${dbPort}`, '--user=root', '--batch', '--skip-column-names'];
const rawSql = text => run(path.join(bin, 'mysql.exe'), sqlArgs, text);
const sql = text => rawSql(`USE ${database};\n${text}`);
async function eventually(action, label) {
    let last;
    for (let i = 0; i < 100; i++) { try { await action(); return; } catch (error) { last = error; await new Promise(r => setTimeout(r, 100)); } }
    throw new Error(`${label}: ${last}`);
}
function cookie(response, fallback = '') { return response.headers.get('set-cookie')?.split(';')[0] || fallback; }
function csrf(text) { const match = text.match(/name="csrf_token" value="([^"]+)"/); assert.ok(match, 'CSRF token rendered'); return match[1]; }
try {
    await run(path.join(bin, 'mysql_install_db.exe'), [`--datadir=${temp}`, `--port=${dbPort}`, '--silent']);
    const maria = spawn(path.join(bin, 'mysqld.exe'), ['--no-defaults', `--datadir=${temp}`, `--port=${dbPort}`, '--bind-address=127.0.0.1', '--innodb-buffer-pool-size=32M', '--console'], { stdio: 'ignore' });
    children.push(maria);
    await eventually(() => rawSql('SELECT 1;'), 'isolated MariaDB starts');
    const dump = readFileSync(path.join(root, 'database.sql'), 'utf8');
    let baseline = `CREATE DATABASE ${database}; USE ${database}; SET FOREIGN_KEY_CHECKS=0;\n`;
    const tables = ['users','settings','categories','products','orders','balance_logs','bank_transactions','card_transactions','product_views','password_resets','momo_transactions'];
    for (const table of tables) {
        const schema = dump.match(new RegExp('CREATE TABLE `' + table + '` \\([\\s\\S]*?;'))?.[0];
        assert.ok(schema, `baseline schema ${table}`); baseline += schema + '\n';
    }
    baseline += 'SET FOREIGN_KEY_CHECKS=1;';
    await rawSql(baseline);
    // Legacy rows intentionally predate UUID migration, exercising deterministic backfill on rerun.
    const legacyHash = (await run(php, ['-r', "echo password_hash('FixturePassword123!', PASSWORD_BCRYPT);"])).trim();
    await sql(`INSERT INTO users (id,username,email,password,fullname,role,status) VALUES
(1,'fixture','fixture@example.test','${legacyHash}','Fixture','user','active'),
(2,'other','other@example.test','${legacyHash}','Other','user','active');
INSERT INTO card_transactions(trans_id,request_id,user_id,telco,declared_amount,serial,pin,status) VALUES ('LEGACY_CARD','legacy-card',2,'VIETTEL',10000,'legacy-serial','legacy-pin','pending');`);
    await run(php, ['database/migrate_security.php']);
    assert.equal((await sql("SELECT pin FROM card_transactions WHERE trans_id='LEGACY_CARD';")).trim(), '', 'legacy raw PIN cleared'); checks++;
    assert.match((await sql("SELECT pin_digest FROM card_transactions WHERE trans_id='LEGACY_CARD';")).trim(), /^[a-f0-9]{64}$/, 'legacy PIN digest backfilled'); checks++;
    if (process.env.EXPORT_SCHEMA_PATH) {
        const ddl = await run(path.join(bin, 'mysqldump.exe'), [...sqlArgs.slice(0, 4), '--user=root', '--no-data', '--skip-comments', '--skip-add-drop-table', '--skip-lock-tables', database]);
        writeFileSync(process.env.EXPORT_SCHEMA_PATH, '-- Generated from the verified security migration. Fresh installations only.\n' + ddl.replace(/ AUTO_INCREMENT=\d+/g, ''));
    }
    const firstIds = (await sql('SELECT id,public_id FROM users ORDER BY id;')).trim();
    for (const row of firstIds.split('\n')) assert.match(row.split('\t')[1], uuid);
    await run(php, ['database/migrate_security.php']);
    assert.equal((await sql('SELECT id,public_id FROM users ORDER BY id;')).trim(), firstIds, 'migration rerun preserves UUIDs'); checks++;
    const [userUUID, otherUUID] = firstIds.split('\n').map(line => line.split('\t')[1]);
    assert.notEqual(userUUID, otherUUID); checks++;
    const coreTables = ['users','categories','products','orders'];
    for (const table of coreTables) {
        assert.equal((await sql(`SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='${database}' AND TABLE_NAME='${table}' AND COLUMN_NAME='deleted_at';`)).trim(), '1', `${table} soft-delete column`); checks++;
    }
    const constraints = await sql(`SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='${database}';`);
    assert.ok(constraints.trim(), 'foreign key constraints exist');
    assert.ok(!constraints.includes('CASCADE'), 'financial foreign keys do not cascade-delete'); checks++;
    await assert.rejects(sql(`UPDATE users SET public_id='${userUUID}' WHERE id=2;`), 'UUID uniqueness enforced'); checks++;
    await assert.rejects(sql('UPDATE users SET balance=-1 WHERE id=1;'), 'negative balances rejected by constraint'); checks++;
    await assert.rejects(sql('UPDATE users SET total_deposited=-1 WHERE id=1;'), 'negative deposited total rejected by constraint'); checks++;
    const categoryUUID = randomUUID(), productUUID = randomUUID(), deletedUUID = randomUUID(), cardUUID = randomUUID();
    await sql(`INSERT INTO categories (id,public_id,name,slug) VALUES (1,'${categoryUUID}','Fixture','fixture');
INSERT INTO products (id,public_id,category_id,title,slug,price,download_url) VALUES
(1,'${productUUID}',1,'Fixture product','fixture-product',10000,'https://example.test/fixture-download.zip'),
(2,'${deletedUUID}',1,'Deleted fixture','deleted-fixture',10000,'https://example.test/deleted.zip');
UPDATE products SET deleted_at=NOW() WHERE id=2;
INSERT INTO settings (setting_key,setting_value) VALUES ('bank_account_number','123456789'),('bank_prefix','NAPTIEN'),('card_partner_key','${secret}');
INSERT INTO card_transactions (public_id,trans_id,request_id,user_id,telco,declared_amount,serial,pin,pin_digest,status) VALUES ('${cardUUID}','CARD_FIXTURE','req-fixture',1,'VIETTEL',100000,'serial-fixture','','${createHmac('sha256', secret).update('serial-fixture:code-fixture').digest('hex')}','pending');`);
    for (const port of [httpPort, httpPort + 1]) {
        const server = spawn(php, ['-S', `127.0.0.1:${port}`, 'router.php'], { cwd: root, env, stdio: 'ignore' }); children.push(server);
        await eventually(() => fetch(`http://127.0.0.1:${port}/assets/css/animations.css`), 'PHP fixture server starts');
    }
    const base = `http://127.0.0.1:${httpPort}`;
    async function login(account = 'fixture', password = 'FixturePassword123!', redirect = '//evil.example') {
        const form = await fetch(base + '/login');
        let session = cookie(form), token = csrf(await form.text());
        const response = await fetch(base + '/ajax/auth.php', { method: 'POST', headers: { Cookie: session }, body: new URLSearchParams({ action: 'login', account, password, csrf_token: token, redirect }) });
        const data = await response.json(); session = cookie(response, session);
        return { data, session };
    }
    assert.equal((await login('fixture', 'wrong-password')).data.success, false, 'wrong password denied'); checks++;
    const auth = await login(); assert.equal(auth.data.success, true); assert.equal(auth.data.redirect, '/'); checks++;
    const upgraded = (await sql('SELECT password FROM users WHERE id=1;')).trim();
    assert.ok(upgraded.includes('$argon2id$'), 'legacy password upgraded to Argon2id'); checks++;
    assert.equal((await run(php, ['-r', `echo password_verify('FixturePassword123!', '${upgraded}') ? 'raw' : 'peppered';`])).trim(), 'peppered', 'upgraded hash requires pepper'); checks++;
    async function bank(data, port = httpPort, signed = true) {
        const body = JSON.stringify(data), timestamp = String(Math.floor(Date.now() / 1000));
        const headers = { 'Content-Type': 'application/json' };
        if (signed) { headers['X-SePay-Timestamp'] = timestamp; headers['X-SePay-Signature'] = 'sha256=' + createHmac('sha256', secret).update(timestamp + '.' + body).digest('hex'); }
        return fetch(`http://127.0.0.1:${port}/api/webhook_bank.php`, { method: 'POST', headers, body });
    }
    const payment = { referenceCode: 'fixture-bank-1', transferAmount: 100000, content: `NAPTIEN ${userUUID}`, accountNumber: '123456789', gateway: 'MBBANK', transferType: 'in', code: null, subAccount: null };
    assert.equal((await bank(payment, httpPort, false)).status, 403); checks++;
    assert.equal((await bank({ ...payment, accountNumber: 'wrong' })).status, 422); checks++;
    const banks = await Promise.all(Array.from({ length: 6 }, (_, i) => bank(payment, httpPort + i % 2)));
    for (const response of banks) assert.equal(response.status, 200, await response.clone().text());
    assert.equal((await sql('SELECT balance FROM users WHERE id=1;')).trim(), '100000.00', 'bank retries credit once'); checks++;
    assert.ok((await (await fetch(base + '/categories?q=Fixture&sort=price_asc')).text()).includes('Fixture product'), 'catalog prepared search and pagination work'); checks++;
    assert.ok(!(await (await fetch(base + '/categories?q=%25')).text()).includes('Fixture product'), 'LIKE percent is literal'); checks++;
    assert.ok((await (await fetch(base + '/product?id=' + productUUID)).text()).includes('Fixture product'), 'UUID public product page renders'); checks++;
    async function card(extra = {}, port = httpPort) {
        const payload = { status: 1, request_id: 'req-fixture', code: 'code-fixture', serial: 'serial-fixture', value: 100000, amount: 85000, optional_metadata: null, callback_sign: createHash('md5').update(secret + 'code-fixture' + 'serial-fixture').digest('hex'), ...extra };
        return fetch(`http://127.0.0.1:${port}/api/callback_card.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    }
    assert.equal((await card({ callback_sign: '' })).status, 403); checks++;
    assert.equal((await card({ callback_sign: 'bad' })).status, 403); checks++;
    const cards = await Promise.all(Array.from({ length: 6 }, (_, i) => card({}, httpPort + i % 2)));
    for (const response of cards) assert.equal(response.status, 200, await response.clone().text());
    assert.equal((await sql('SELECT balance FROM users WHERE id=1;')).trim(), '185000.00', 'card retries credit once'); checks++;
    assert.equal((await sql('SELECT COUNT(*) FROM balance_logs;')).trim(), '2'); checks++;
    // Independent authenticated sessions avoid PHP session locks masking DB races.
    const auth2 = await login();
    assert.equal(auth2.data.success, true, 'second independent session authenticated');
    async function buy(product, authSession = auth.session, port = httpPort) {
        const form = await fetch(`http://127.0.0.1:${port}/profile`, { headers: { Cookie: authSession } });
        const token = csrf(await form.text());
        return fetch(`http://127.0.0.1:${port}/api/buy_code.php`, { method: 'POST', headers: { Cookie: authSession }, body: new URLSearchParams({ product_id: product, csrf_token: token }) });
    }
    assert.equal((await (await buy('1')).json()).success, false, 'numeric product ID rejected'); checks++;
    assert.equal((await (await buy(deletedUUID)).json()).success, false, 'deleted product rejected'); checks++;
    const purchaseResponses = await Promise.all([buy(productUUID), buy(productUUID, auth2.session, httpPort + 1)]);
    const purchases = await Promise.all(purchaseResponses.map(response => response.json()));
    for (const purchase of purchases) { assert.equal(purchase.success, true, JSON.stringify(purchase)); assert.match(purchase.order_id ?? purchase.data?.order_id, uuid); }
    assert.equal((await sql('SELECT balance FROM users WHERE id=1;')).trim(), '165000.00', 'concurrent purchases both debit exact amounts'); checks++;
    assert.equal((await sql("SELECT GROUP_CONCAT(CONCAT(amount_before,':',amount_after) ORDER BY id SEPARATOR ',') FROM balance_logs WHERE type='buy_code';")).trim(), '185000.00:175000.00,175000.00:165000.00', 'purchase journal records locked balances'); checks++;
    const orderUUID = purchases[0].order_id ?? purchases[0].data?.order_id;
    assert.equal((await sql('SELECT balance FROM users WHERE id=2;')).trim(), '0.00', 'other wallet unchanged'); checks++;
    const numericOrder = (await sql('SELECT id FROM orders ORDER BY id LIMIT 1;')).trim();
    const numericDownload = await fetch(base + '/download.php?order_id=' + numericOrder, { headers: { Cookie: auth.session } });
    assert.ok(!(await numericDownload.text()).includes('https://example.test/fixture-download.zip'), 'numeric order ID rejected'); checks++;
    const ownDownload = await fetch(base + '/download.php?order_id=' + orderUUID, { headers: { Cookie: auth.session } });
    assert.ok((await ownDownload.text()).includes('https://example.test/fixture-download.zip'), 'own UUID grants purchased download'); checks++;
    const otherAuth = await login('other');
    const otherDownload = await fetch(base + '/download.php?order_id=' + orderUUID, { headers: { Cookie: otherAuth.session } });
    assert.ok(!(await otherDownload.text()).includes('https://example.test/fixture-download.zip'), 'other account cannot download purchased order'); checks++;
    await sql("UPDATE users SET role='admin' WHERE id=2;");
    const admin = await login('other');
    assert.equal((await fetch(base + '/admin/users.php', { method: 'POST', headers: { Cookie: admin.session }, body: new URLSearchParams({ action: 'delete', id: userUUID }) })).status, 403, 'admin POST requires CSRF'); checks++;
    await sql("UPDATE users SET role='user' WHERE id=2;");
    const recoveryForm = await fetch(base + '/reset-password');
    const recoveryCookie = cookie(recoveryForm), recoveryCsrf = csrf(await recoveryForm.text());
    const digest = createHmac('sha256', secret).update('fixture@example.test:123456').digest('hex');
    await sql(`INSERT INTO password_resets (email,token) VALUES ('fixture@example.test','${digest}');`);
    const reset = await fetch(base + '/reset-password', { method: 'POST', headers: { Cookie: recoveryCookie }, body: new URLSearchParams({ csrf_token: recoveryCsrf, email: 'fixture@example.test', otp: '123456', new_password: 'NewFixturePassword123!', confirm_password: 'NewFixturePassword123!' }) });
    assert.ok((await reset.text()).includes('Đặt lại mật khẩu thành công'), 'full keyed OTP digest resets password'); checks++;
    assert.equal((await sql('SELECT COUNT(*) FROM password_resets;')).trim(), '0', 'OTP consumed'); checks++;
    assert.equal((await fetch(base + '/profile', { headers: { Cookie: auth.session }, redirect: 'manual' })).status, 302, 'reset revokes old session'); checks++;
    assert.equal((await login('fixture', 'NewFixturePassword123!')).data.success, true, 'reset password verified with pepper'); checks++;
    const bootstrapEnv = { ...env, BOOTSTRAP_ADMIN_USERNAME: 'fixtureadmin', BOOTSTRAP_ADMIN_EMAIL: 'admin@example.test', BOOTSTRAP_ADMIN_PASSWORD: 'FixturePassword123!' };
    assert.equal(JSON.parse(await run(php, ['database/create_admin.php'], '', bootstrapEnv)).success, true, 'fresh admin CLI bootstrap'); checks++;
    await assert.rejects(run(php, ['database/create_admin.php'], '', bootstrapEnv), 'bootstrap cannot replace existing administrator'); checks++;
    await sql(`UPDATE users SET fullname='"><img src=x onerror=alert(1)>' WHERE id=2;`);
    const adminAuth = await login('fixtureadmin'); assert.equal(adminAuth.data.success, true); checks++;
    async function adminPost(route, parameters) {
        const form = await fetch(base + route, { headers: { Cookie: adminAuth.session } });
        const html = await form.text();
        assert.ok(!html.includes('"><img src=x onerror=alert(1)>'), 'stored user markup is escaped');
        return fetch(base + route, { method: 'POST', headers: { Cookie: adminAuth.session }, body: new URLSearchParams({ csrf_token: csrf(html), ...parameters }) });
    }
    let result = await adminPost('/admin/categories', { action_save: '1', category_id: '0', name: 'Secure fixture', slug: 'secure-fixture', icon: 'fas fa-code', description: '', sort_order: '0', status: '1' });
    assert.equal(result.status, 200); const adminCategory = (await sql("SELECT public_id FROM categories WHERE slug='secure-fixture';")).trim(); assert.match(adminCategory, uuid); checks++;
    result = await adminPost('/admin/products', { action_save_product: '1', product_id: '0', category_id: adminCategory, title: 'Private download', slug: 'private-fixture', short_desc: '', content: '<script>alert(1)</script>', price: '10.25', sale_price: '', download_url: 'fixture.zip', demo_url: '', status: 'active' });
    assert.equal(result.status, 200); const privateProduct = (await sql("SELECT public_id FROM products WHERE slug='private-fixture';")).trim(); assert.match(privateProduct, uuid); checks++;
    result = await adminPost('/admin/products', { delete_id: privateProduct });
    assert.equal((await sql("SELECT deleted_at IS NOT NULL FROM products WHERE slug='private-fixture';")).trim(), '1', 'admin product soft deletion'); checks++;
    await adminPost('/admin/users', { action_adjust_balance: '1', target_user_id: otherUUID, adjust_type: 'add', amount: '0.20', reason: 'Fixture adjustment' });
    await adminPost('/admin/users', { action_adjust_balance: '1', target_user_id: otherUUID, adjust_type: 'sub', amount: '0.10', reason: 'Fixture adjustment' });
    assert.equal((await sql('SELECT balance FROM users WHERE id=2;')).trim(), '0.10', 'admin exact decimal adjustments'); checks++;
    const secondAdmin = await login('fixtureadmin'); assert.equal(secondAdmin.data.success, true);
    async function refund(session, port) {
        const form = await fetch(`http://127.0.0.1:${port}/profile`, { headers: { Cookie: session } });
        return fetch(`http://127.0.0.1:${port}/admin/orders`, { method: 'POST', headers: { Cookie: session }, body: new URLSearchParams({ csrf_token: csrf(await form.text()), refund_order_id: orderUUID }) });
    }
    await Promise.all([refund(adminAuth.session, httpPort), refund(secondAdmin.session, httpPort + 1)]);
    assert.equal((await sql('SELECT balance FROM users WHERE id=1;')).trim(), '175000.00', 'parallel admin refund credits exactly once'); checks++;
    assert.equal((await sql("SELECT COUNT(*) FROM balance_logs WHERE type='refund';")).trim(), '1', 'refund ledger inserted once'); checks++;
    const momoOrder = 'MOMO' + randomUUID().replaceAll('-', ''), momoRequest = randomUUID();
    await sql(`INSERT INTO momo_transactions(public_id,order_id,request_id,user_id,amount,status) VALUES ('${randomUUID()}','${momoOrder}','${momoRequest}',2,50000,'pending');`);
    const ipn = { partnerCode: 'fixture-partner', requestId: momoRequest, amount: 50000, orderId: momoOrder, orderInfo: 'Fixture', orderType: 'momo_wallet', transId: '123456789', message: 'Success', responseTime: Date.now(), resultCode: 0, payType: 'qr', extraData: '', metadata: null };
    const signedText = `accessKey=fixture-access&amount=${ipn.amount}&extraData=&message=${ipn.message}&orderId=${ipn.orderId}&orderInfo=${ipn.orderInfo}&orderType=${ipn.orderType}&partnerCode=${ipn.partnerCode}&payType=${ipn.payType}&requestId=${ipn.requestId}&responseTime=${ipn.responseTime}&resultCode=0&transId=${ipn.transId}`;
    ipn.signature = createHmac('sha256', secret).update(signedText).digest('hex');
    const ipnResults = await Promise.all(Array.from({ length: 4 }, (_, i) => fetch(`http://127.0.0.1:${httpPort + i % 2}/api/momo_ipn.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(ipn) })));
    for (const response of ipnResults) assert.equal(response.status, 204, await response.clone().text());
    assert.equal((await sql('SELECT balance FROM users WHERE id=2;')).trim(), '50000.10', 'MoMo signature without supplied accessKey credits once'); checks++;
    const roleSql = readFileSync(path.join(root, 'database/least_privilege.sql'), 'utf8').replaceAll('app_database', database);
    await sql(roleSql + "\nCREATE USER 'fixture_app'@'127.0.0.1' IDENTIFIED BY 'fixture-test-only'; GRANT app_runtime TO 'fixture_app'@'127.0.0.1'; SET DEFAULT ROLE app_runtime FOR 'fixture_app'@'127.0.0.1';");
    const restrictedArgs = sqlArgs.map(arg => arg === '--user=root' ? '--user=fixture_app' : arg);
    const limitedSql = text => run(path.join(bin, 'mysql.exe'), restrictedArgs, `USE ${database};\n${text}`, { ...env, MYSQL_PWD: 'fixture-test-only' });
    assert.equal((await limitedSql('SELECT COUNT(*) FROM users;')).trim(), '3', 'runtime role reads accounts'); checks++;
    await assert.rejects(limitedSql("UPDATE audit_events SET action='tamper';"), 'runtime cannot alter audit history'); checks++;
    await assert.rejects(limitedSql('DELETE FROM balance_logs;'), 'runtime cannot erase financial ledger'); checks++;
    await assert.rejects(limitedSql('CREATE TABLE unauthorized(id INT);'), 'runtime cannot perform DDL'); checks++;
    await sql("UPDATE users SET oauth_provider='google', oauth_uid='fixture-unique-provider' WHERE id=1;");
    await assert.rejects(sql("UPDATE users SET oauth_provider='google', oauth_uid='fixture-unique-provider' WHERE id=2;"), 'OAuth UID unique constraint'); checks++;
    await sql('UPDATE users SET deleted_at=NOW() WHERE id=2;');
    assert.equal((await login('other')).data.success, false, 'soft-deleted account cannot log in'); checks++;
    const auditTables = (await sql("SHOW TABLES LIKE '%audit%';")).trim().split('\n').filter(Boolean);
    assert.ok(auditTables.length, 'migration creates audit table');
    let auditCount = 0;
    for (const table of auditTables) { assert.match(table, /^[a-zA-Z0-9_]+$/); auditCount += Number((await sql(`SELECT COUNT(*) FROM \`${table}\`;`)).trim()); }
    assert.ok(auditCount > 0, 'financial or account operations wrote audit rows'); checks++;
    console.log(`PASS: ${checks} v2 migration/auth/payment/purchase/reset checks (two PHP servers)`);
} finally {
    for (const child of children.reverse()) {
        if (child.exitCode !== null || child.signalCode !== null) continue;
        const closed = new Promise(resolve => child.once('close', resolve)); child.kill(); await closed;
    }
    // Only remove this exact harness-created directory inside the OS temporary directory.
    const resolved = path.resolve(temp), parent = path.resolve(tmpdir());
    if (path.dirname(resolved) === parent && path.basename(resolved).startsWith('marketcode-v2-')) rmSync(resolved, { recursive: true, force: true });
}
