<?php
/**
 * ePlayHD Setup — .env ও Database কনফিগ
 * URL: yourdomain.com/setup.php?token=eplayhd_setup_2024
 * শেষে DELETE করুন!
 */
define('SETUP_TOKEN', 'eplayhd_setup_2024');
if (($_GET['token'] ?? '') !== SETUP_TOKEN) {
    die('<h2 style="font-family:sans-serif;color:red;padding:2rem">?token=eplayhd_setup_2024 দিয়ে আসুন</h2>');
}

$base    = __DIR__;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Write .env
    if ($action === 'write_env') {
        $appKey = 'base64:' . base64_encode(random_bytes(32));
        $appUrl = rtrim($_POST['app_url'] ?? 'https://photocard.fun', '/');
        $dbHost = $_POST['db_host'] ?? 'localhost';
        $dbName = $_POST['db_name'] ?? '';
        $dbUser = $_POST['db_user'] ?? '';
        $dbPass = $_POST['db_pass'] ?? '';

        $env = "APP_NAME=ePlayHD\nAPP_ENV=production\nAPP_KEY={$appKey}\nAPP_DEBUG=false\nAPP_URL={$appUrl}\n\nLOG_CHANNEL=stack\nLOG_LEVEL=error\n\nDB_CONNECTION=mysql\nDB_HOST={$dbHost}\nDB_PORT=3306\nDB_DATABASE={$dbName}\nDB_USERNAME={$dbUser}\nDB_PASSWORD={$dbPass}\n\nBROADCAST_DRIVER=log\nCACHE_DRIVER=file\nFILESYSTEM_DISK=local\nQUEUE_CONNECTION=sync\nSESSION_DRIVER=file\nSESSION_LIFETIME=120\n";

        if (file_put_contents($base . '/.env', $env) !== false) {
            $message = ['type'=>'ok', 'text'=>'.env সফলভাবে তৈরি হয়েছে!'];
        } else {
            $message = ['type'=>'er', 'text'=>'.env লেখা যায়নি। storage permission চেক করুন।'];
        }
    }

    // Fix permissions
    if ($action === 'permissions') {
        $dirs = ['storage','storage/logs','storage/app','storage/app/public','storage/framework','storage/framework/sessions','storage/framework/views','storage/framework/cache','storage/framework/cache/data','bootstrap/cache'];
        $r = [];
        foreach ($dirs as $d) {
            $p = $base . '/' . $d;
            if (!is_dir($p)) @mkdir($p, 0775, true);
            @chmod($p, 0775);
            shell_exec('chmod 775 ' . escapeshellarg($p) . ' 2>/dev/null');
            $r[] = (is_writable($p) ? '✓' : '✗') . ' ' . $d;
        }
        $message = ['type'=>'ok', 'text'=>'Permission: ' . implode(' | ', $r)];
    }

    // Test DB
    if ($action === 'test_db') {
        if (!file_exists($base . '/.env')) {
            $message = ['type'=>'wn', 'text'=>'আগে .env তৈরি করুন।'];
        } else {
            $env = parse_ini_file($base . '/.env') ?: [];
            try {
                $pdo    = new PDO('mysql:host='.($env['DB_HOST']??'localhost').';dbname='.($env['DB_DATABASE']??''), $env['DB_USERNAME']??'', $env['DB_PASSWORD']??'');
                $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                $message = ['type'=>'ok', 'text'=>'Database connected! Tables: ' . count($tables) . (count($tables) ? ' ('.implode(', ', array_slice($tables,0,5)).'…)' : ' — phpMyAdmin দিয়ে SQL import করুন')];
            } catch (Exception $e) {
                $message = ['type'=>'er', 'text'=>'DB Error: ' . $e->getMessage()];
            }
        }
    }
}

$env = file_exists($base.'/.env') ? (parse_ini_file($base.'/.env') ?: []) : [];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ePlayHD Setup</title>
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;color:#1e293b;padding:1.25rem;font-size:14px;margin:0}
h1{font-size:1.3rem;font-weight:800;margin-bottom:.25rem}
.sub{color:#64748b;font-size:.8125rem;margin-bottom:1.25rem}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;padding:1.25rem;margin-bottom:1rem;box-shadow:0 1px 3px rgba(0,0,0,.05)}
h2{font-size:.9375rem;font-weight:700;margin-bottom:1rem;color:#334155;display:flex;align-items:center;gap:.375rem}
label{display:block;font-size:.8rem;font-weight:600;color:#64748b;margin:.6rem 0 .2rem}
input{width:100%;padding:.5rem .75rem;border:1px solid #cbd5e1;border-radius:.5rem;font-size:.875rem;color:#1e293b;background:#f8fafc}
input:focus{outline:2px solid #6366f1;border-color:#6366f1;background:#fff}
.btn{display:inline-flex;align-items:center;padding:.5rem 1.1rem;border-radius:.5rem;font-size:.875rem;font-weight:600;cursor:pointer;border:none;margin:.2rem .2rem .2rem 0}
.bp{background:#6366f1;color:#fff} .bg{background:#16a34a;color:#fff} .bs{background:#e2e8f0;color:#334155}
.msg{padding:.75rem 1rem;border-radius:.5rem;margin-bottom:.875rem;font-size:.8375rem;line-height:1.6}
.msg-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d}
.msg-er{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
.msg-wn{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
.warn-box{background:#fffbeb;border:1px solid #fde68a;padding:.75rem 1rem;border-radius:.5rem;font-size:.8rem;color:#92400e;margin-bottom:1rem}
.row{display:flex;justify-content:space-between;align-items:center;padding:.375rem 0;border-bottom:1px solid #f1f5f9;font-size:.8125rem}
.row:last-child{border:none}
.ok{color:#16a34a;font-weight:600} .er{color:#dc2626;font-weight:600} .wn{color:#d97706;font-weight:600}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
</style>
</head>
<body>
<h1>⚙️ ePlayHD Setup</h1>
<p class="sub">Database ও .env কনফিগারেশন</p>

<div class="warn-box">⚠️ সেটআপ শেষে <strong>setup.php</strong> ও <strong>install.php</strong> File Manager থেকে Delete করুন।</div>

<?php if ($message): ?>
<div class="msg msg-<?= $message['type'] ?>"><?= htmlspecialchars($message['text']) ?></div>
<?php endif; ?>

<!-- Status -->
<div class="card">
    <h2>📊 সিস্টেম স্ট্যাটাস</h2>
    <?php
    $sItems = [
        'PHP'           => version_compare(PHP_VERSION,'8.1','>=') ? '<span class="ok">'.PHP_VERSION.'</span>' : '<span class="er">'.PHP_VERSION.'</span>',
        '.env'          => file_exists($base.'/.env') ? '<span class="ok">আছে</span>' : '<span class="er">নেই</span>',
        'vendor'        => file_exists($base.'/vendor/autoload.php') ? '<span class="ok">আছে ✓</span>' : '<span class="wn">নেই — install.php দিয়ে করুন</span>',
        'storage write' => is_writable($base.'/storage') ? '<span class="ok">OK</span>' : '<span class="er">Not writable</span>',
        'PDO MySQL'     => extension_loaded('pdo_mysql') ? '<span class="ok">Loaded</span>' : '<span class="er">Missing</span>',
    ];
    foreach ($sItems as $l => $v) echo '<div class="row"><span>'.$l.'</span>'.$v.'</div>';
    ?>
    <div style="margin-top:.75rem;display:flex;gap:.375rem;flex-wrap:wrap">
        <form method="post" style="display:inline"><input type="hidden" name="action" value="permissions"><button class="btn bs" type="submit">🔧 Permission ঠিক করুন</button></form>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="test_db"><button class="btn bs" type="submit">🔌 DB Test করুন</button></form>
        <a href="/install.php?token=go123" class="btn bg">▶ install.php খুলুন</a>
    </div>
</div>

<!-- .env Form -->
<div class="card">
    <h2>① .env কনফিগার করুন</h2>
    <form method="post">
        <input type="hidden" name="action" value="write_env">
        <div class="form-group">
            <label>Site URL</label>
            <input type="url" name="app_url" value="<?= htmlspecialchars($env['APP_URL'] ?? 'https://photocard.fun') ?>" required>
        </div>
        <div class="g2">
            <div><label>Database Name *</label><input type="text" name="db_name" value="<?= htmlspecialchars($env['DB_DATABASE'] ?? '') ?>" placeholder="dealdoka_eplayhd" required></div>
            <div><label>Database Host</label><input type="text" name="db_host" value="<?= htmlspecialchars($env['DB_HOST'] ?? 'localhost') ?>"></div>
        </div>
        <div class="g2">
            <div><label>Database Username *</label><input type="text" name="db_user" value="<?= htmlspecialchars($env['DB_USERNAME'] ?? '') ?>" required></div>
            <div><label>Database Password</label><input type="password" name="db_pass" value="<?= htmlspecialchars($env['DB_PASSWORD'] ?? '') ?>"></div>
        </div>
        <div style="margin-top:.875rem">
            <button type="submit" class="btn bp">💾 .env সেভ করুন</button>
        </div>
    </form>
</div>

<!-- Next steps -->
<div class="card" style="background:#f0fdf4;border-color:#bbf7d0">
    <h2 style="color:#15803d">② পরবর্তী ধাপ</h2>
    <ol style="padding-left:1.25rem;font-size:.875rem;color:#166534;line-height:2.2">
        <li>cPanel phpMyAdmin-এ <strong>eplayhd_mysql.sql</strong> import করুন</li>
        <li><a href="/install.php?token=go123" style="color:#6366f1;font-weight:600">install.php খুলুন</a> → Composer install করুন</li>
        <li>সব শেষে setup.php ও install.php <strong>Delete</strong> করুন</li>
        <li>Admin: <a href="/admin" style="color:#6366f1">/admin</a> — <code>admin@eplayhd.com</code> / <code>admin123</code></li>
    </ol>
</div>
</body>
</html>
