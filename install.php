<?php
/**
 * ePlayHD — One-Click Installer
 * ─────────────────────────────
 * URL: yourdomain.com/install.php?token=go123
 * শেষে এই ফাইল DELETE করুন!
 */
define('TOKEN', 'go123');
if (($_GET['token'] ?? '') !== TOKEN) {
    die('<h2 style="font-family:sans-serif;color:red;padding:2rem">Access denied.<br><small>?token=go123 দিয়ে আসুন</small></h2>');
}

set_time_limit(300);
ini_set('memory_limit', '512M');
error_reporting(0);

$base = __DIR__;
$phar = $base . '/composer.phar';
$step = $_GET['do'] ?? 'status';

// ── Find CLI PHP ────────────────────────────────────────────
function findCliPhp() {
    $maj   = PHP_MAJOR_VERSION;
    $min   = PHP_MINOR_VERSION;
    $short = $maj . $min;

    $candidates = [
        "/usr/local/bin/php{$maj}.{$min}",
        "/usr/local/bin/php{$short}",
        "/opt/cpanel/ea-php{$short}/root/usr/bin/php",
        "/usr/local/bin/ea-php{$short}",
        "/usr/bin/php{$maj}.{$min}",
        "/usr/local/bin/php{$maj}",
        "/usr/local/bin/php",
        "/usr/bin/php",
    ];

    foreach ($candidates as $p) {
        if (!file_exists($p) || !is_executable($p)) continue;
        $sapi = trim((string)shell_exec($p . ' -r "echo php_sapi_name();" 2>/dev/null'));
        if ($sapi === 'cli') return $p;
    }

    // Scan /opt/cpanel for any matching version
    foreach (glob('/opt/cpanel/ea-php*/root/usr/bin/php') ?: [] as $p) {
        if (!is_executable($p)) continue;
        $sapi = trim((string)shell_exec($p . ' -r "echo php_sapi_name();" 2>/dev/null'));
        if ($sapi === 'cli') return $p;
    }

    // Scan /usr/local/bin/php*
    foreach (glob('/usr/local/bin/php*') ?: [] as $p) {
        if (!is_executable($p)) continue;
        if (preg_match('/(phpize|php-config|phpdbg)/', $p)) continue;
        $sapi = trim((string)shell_exec($p . ' -r "echo php_sapi_name();" 2>/dev/null'));
        if ($sapi === 'cli') return $p;
    }

    return PHP_BINARY;
}

function sh($cmd) {
    $out = []; $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    return ['out' => implode("\n", $out), 'code' => $code];
}

function fixPerms($base) {
    $dirs = [
        'storage', 'storage/logs', 'storage/app', 'storage/app/public',
        'storage/framework', 'storage/framework/sessions',
        'storage/framework/views', 'storage/framework/cache',
        'storage/framework/cache/data', 'bootstrap/cache',
    ];
    foreach ($dirs as $d) {
        $p = $base . '/' . $d;
        if (!is_dir($p)) @mkdir($p, 0775, true);
        @chmod($p, 0775);
        shell_exec('chmod 775 ' . escapeshellarg($p) . ' 2>/dev/null');
    }
}

$phpBin = findCliPhp();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ePlayHD Installer</title>
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f172a;color:#e2e8f0;padding:1.25rem;font-size:14px;line-height:1.6;margin:0}
h1{color:#818cf8;font-size:1.3rem;margin-bottom:1rem}
h3{font-size:1rem;margin:0 0 .75rem;color:#e2e8f0}
.card{background:#1e293b;border-radius:.75rem;padding:1.25rem;margin-bottom:1rem;border:1px solid #334155}
pre{background:#0a0f1a;padding:.875rem;border-radius:.5rem;overflow:auto;white-space:pre-wrap;word-break:break-all;font-size:11px;color:#86efac;max-height:350px;margin:.5rem 0}
.ok{color:#4ade80;font-weight:600}
.er{color:#f87171;font-weight:600}
.wn{color:#fbbf24;font-weight:600}
.in{color:#60a5fa}
.row{display:flex;justify-content:space-between;align-items:center;padding:.375rem 0;border-bottom:1px solid #1e293b;font-size:.8125rem}
.row:last-child{border:none}
a.b{display:inline-flex;align-items:center;padding:.5rem 1.1rem;border-radius:.5rem;text-decoration:none;font-weight:600;font-size:.875rem;margin:.2rem .2rem .2rem 0;cursor:pointer}
.bp{background:#6366f1;color:#fff}
.bg{background:#16a34a;color:#fff}
.br{background:#dc2626;color:#fff}
.bs{background:#334155;color:#94a3b8}
</style>
</head>
<body>
<h1>🚀 ePlayHD Installer</h1>

<?php
// ════════════════════════════════════════════════════════════
// DOWNLOAD
// ════════════════════════════════════════════════════════════
if ($step === 'download') {
    echo '<div class="card"><h3>📥 composer.phar ডাউনলোড</h3>';
    $done = false;

    // Try curl
    echo '<p class="in">› curl চেষ্টা করছি...</p>';
    sh('curl -sS --max-time 120 -L -o ' . escapeshellarg($phar) . ' https://getcomposer.org/composer-stable.phar');
    if (file_exists($phar) && filesize($phar) > 100000) {
        $done = true;
        echo '<p class="ok">✓ curl সফল (' . round(filesize($phar)/1024) . ' KB)</p>';
    }

    // Try wget
    if (!$done) {
        echo '<p class="in">› wget চেষ্টা করছি...</p>';
        sh('wget -q --timeout=120 -O ' . escapeshellarg($phar) . ' https://getcomposer.org/composer-stable.phar');
        if (file_exists($phar) && filesize($phar) > 100000) {
            $done = true;
            echo '<p class="ok">✓ wget সফল (' . round(filesize($phar)/1024) . ' KB)</p>';
        }
    }

    // Try PHP stream
    if (!$done) {
        echo '<p class="in">› PHP file_get_contents চেষ্টা করছি...</p>';
        $ctx  = stream_context_create(['http'=>['timeout'=>120,'user_agent'=>'Composer'],'ssl'=>['verify_peer'=>false]]);
        $data = @file_get_contents('https://getcomposer.org/composer-stable.phar', false, $ctx);
        if ($data && strlen($data) > 100000) {
            file_put_contents($phar, $data);
            $done = true;
            echo '<p class="ok">✓ PHP stream সফল (' . round(strlen($data)/1024) . ' KB)</p>';
        }
    }

    if ($done) {
        @chmod($phar, 0755);
        echo '<p class="ok" style="font-size:1rem;margin-top:.5rem">✓ Download সম্পন্ন!</p>';
        echo '<a class="b bg" href="?token='.TOKEN.'&do=install">② Composer Install করুন ›</a>';
    } else {
        echo '<div style="margin-top:.75rem;background:#1c1007;border:1px solid #92400e;padding:1rem;border-radius:.5rem">';
        echo '<p class="wn">⚠ Auto-download ব্যর্থ। ম্যানুয়াল করুন:</p>';
        echo '<ol style="margin:.5rem 0 0 1.25rem;color:#fbbf24;line-height:2;font-size:.8125rem">';
        echo '<li>এখান থেকে ডাউনলোড করুন: <a href="https://getcomposer.org/composer-stable.phar" style="color:#818cf8">composer-stable.phar</a></li>';
        echo '<li>নাম দিন: <code style="background:#0f172a;padding:0 .375rem;border-radius:.25rem">composer.phar</code></li>';
        echo '<li><code>photocard.fun/</code> ফোল্ডারে আপলোড করুন</li>';
        echo '<li>তারপর: <a href="?token='.TOKEN.'&do=install" class="b bg" style="display:inline-flex;margin-top:.25rem">Install করুন ›</a></li>';
        echo '</ol></div>';
    }
    echo '</div>';

// ════════════════════════════════════════════════════════════
// INSTALL
// ════════════════════════════════════════════════════════════
} elseif ($step === 'install') {
    echo '<div class="card"><h3>⚙️ Composer Install</h3>';

    $sapiName = trim((string)shell_exec($phpBin . ' -r "echo php_sapi_name();" 2>/dev/null'));
    echo '<p class="in" style="font-size:.8rem">PHP: <code>' . htmlspecialchars($phpBin) . '</code> | SAPI: <code>' . $sapiName . '</code></p>';

    // Determine composer command
    $composerCmd = null;
    if (file_exists($phar) && filesize($phar) > 100000) {
        $composerCmd = $phpBin . ' ' . escapeshellarg($phar);
    } else {
        $w = sh('which composer');
        if ($w['code'] === 0 && trim($w['out'])) $composerCmd = trim($w['out']);
    }

    if (!$composerCmd) {
        echo '<p class="er">✗ composer.phar নেই।</p>';
        echo '<a class="b bp" href="?token='.TOKEN.'&do=download">⬇ আগে Download করুন</a>';
        echo '</div></body></html>'; exit;
    }

    // Fix permissions before install
    fixPerms($base);

    $cmd = 'cd ' . escapeshellarg($base)
         . ' && HOME=' . escapeshellarg($base)
         . ' COMPOSER_HOME=' . escapeshellarg($base . '/.composer')
         . ' COMPOSER_PROCESS_TIMEOUT=600'
         . ' ' . $composerCmd
         . ' install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist';

    echo '<p style="font-size:.75rem;color:#475569;margin:.25rem 0 .5rem">Running...</p>';

    $output = []; $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    $outStr = implode("\n", $output);

    echo '<pre>' . htmlspecialchars($outStr) . '</pre>';

    if (file_exists($base . '/vendor/autoload.php')) {
        echo '<p class="ok" style="font-size:1rem;margin-top:.75rem">✓ Install সফল! vendor তৈরি হয়েছে।</p>';
        echo '<a class="b bg" href="?token='.TOKEN.'&do=artisan">③ Cache & Optimize ›</a>';
    } else {
        echo '<p class="er" style="margin-top:.5rem">✗ vendor/autoload.php তৈরি হয়নি।</p>';
        echo '<a class="b bp" href="?token='.TOKEN.'&do=install">🔄 আবার চেষ্টা</a>';
        echo '<a class="b bs" href="?token='.TOKEN.'&do=download">⬇ composer.phar নামান</a>';
    }
    echo '</div>';

// ════════════════════════════════════════════════════════════
// ARTISAN
// ════════════════════════════════════════════════════════════
} elseif ($step === 'artisan') {
    echo '<div class="card"><h3>⚡ Artisan Cache</h3>';

    if (!file_exists($base . '/vendor/autoload.php')) {
        echo '<p class="er">vendor নেই। <a href="?token='.TOKEN.'&do=install" class="b bp">Install করুন</a></p>';
        echo '</div></body></html>'; exit;
    }

    fixPerms($base);

    $art = $phpBin . ' ' . escapeshellarg($base . '/artisan');
    foreach (['config:clear','cache:clear','view:clear','config:cache','route:cache','view:cache'] as $c) {
        $r    = sh($art . ' ' . $c);
        $icon = $r['code'] === 0 ? '<span class="ok">✓</span>' : '<span class="wn">⚠</span>';
        echo '<div class="row">' . $icon . ' <code>' . $c . '</code> <span style="color:#64748b;font-size:.75rem">' . htmlspecialchars(trim(substr($r['out'], 0, 80))) . '</span></div>';
    }

    echo '<div style="margin-top:1rem;padding:.875rem;background:#0d2818;border-radius:.5rem;border:1px solid #166534">';
    echo '<p class="ok" style="font-size:1rem;margin-bottom:.5rem">🎉 সেটআপ সম্পন্ন!</p>';
    echo '<p style="font-size:.8125rem;color:#86efac">Admin: <code>admin@eplayhd.com</code> / <code>admin123</code></p>';
    echo '</div>';
    echo '<div style="margin-top:.75rem">';
    echo '<a class="b bg" href="/" target="_blank">🌐 সাইট দেখুন</a>';
    echo '<a class="b bp" href="/admin" target="_blank">🔧 Admin Panel</a>';
    echo '<a class="b br" href="?token='.TOKEN.'&do=delete">🗑 এই ফাইল Delete করুন</a>';
    echo '</div>';
    echo '</div>';

// ════════════════════════════════════════════════════════════
// DELETE SELF
// ════════════════════════════════════════════════════════════
} elseif ($step === 'delete') {
    $deleted = @unlink(__FILE__);
    @unlink($base . '/setup.php');
    @unlink($base . '/composer.phar');
    echo '<div class="card">';
    echo $deleted ? '<p class="ok">✓ install.php deleted।</p>' : '<p class="er">Delete হয়নি, ম্যানুয়ালি করুন।</p>';
    echo '<p style="margin-top:.5rem"><a href="/" style="color:#818cf8">সাইট ভিজিট করুন →</a></p>';
    echo '</div>';

// ════════════════════════════════════════════════════════════
// STATUS (default)
// ════════════════════════════════════════════════════════════
} else {
    $sapiName = trim((string)shell_exec($phpBin . ' -r "echo php_sapi_name();" 2>/dev/null'));
    $envOk    = file_exists($base . '/.env');

    // Read DB from .env
    $dbOk = false;
    if ($envOk) {
        $env  = parse_ini_file($base . '/.env') ?: [];
        try {
            $pdo  = new PDO('mysql:host='.($env['DB_HOST']??'localhost').';dbname='.($env['DB_DATABASE']??''), $env['DB_USERNAME']??'', $env['DB_PASSWORD']??'');
            $dbOk = true;
        } catch (Exception $e) { $dbOk = false; }
    }

    $rows = [
        'PHP Version'   => version_compare(PHP_VERSION,'8.1','>=') ? '<span class="ok">'.PHP_VERSION.'</span>' : '<span class="er">'.PHP_VERSION.' (8.1+ দরকার)</span>',
        'PHP CLI'       => '<span class="'.($sapiName==='cli'?'ok':'wn').'">'.htmlspecialchars(basename($phpBin)).' ('.$sapiName.')</span>',
        '.env'          => $envOk ? '<span class="ok">✓ আছে</span>' : '<span class="er">✗ নেই</span>',
        'Database'      => $dbOk  ? '<span class="ok">✓ Connected</span>' : '<span class="'.($envOk?'er':'wn').'">'.($envOk?'✗ Connection failed':'setup.php দিয়ে সেট করুন').'</span>',
        'vendor'        => file_exists($base.'/vendor/autoload.php') ? '<span class="ok">✓ আছে</span>' : '<span class="er">✗ নেই — install দরকার</span>',
        'composer.phar' => file_exists($phar) ? '<span class="ok">✓ '.round(filesize($phar)/1024).' KB</span>' : '<span class="wn">✗ নেই</span>',
        'storage write' => is_writable($base.'/storage') ? '<span class="ok">✓ OK</span>' : '<span class="er">✗ Not writable</span>',
        'exec()'        => function_exists('exec') ? '<span class="ok">✓ Available</span>' : '<span class="er">✗ Disabled</span>',
        'PDO MySQL'     => extension_loaded('pdo_mysql') ? '<span class="ok">✓ Loaded</span>' : '<span class="er">✗ Missing</span>',
    ];

    echo '<div class="card">';
    foreach ($rows as $label => $val) {
        echo '<div class="row"><span>'.$label.'</span>'.$val.'</div>';
    }
    echo '</div>';

    echo '<div class="card"><h3>ধাপ অনুযায়ী করুন:</h3>';

    if (!$envOk) {
        echo '<div style="padding:.75rem;background:#1c1007;border:1px solid #92400e;border-radius:.5rem;margin-bottom:.75rem;font-size:.8125rem;color:#fbbf24">';
        echo '⚠ আগে <strong>setup.php</strong> দিয়ে .env ও Database সেট করুন:<br>';
        echo '<a href="/setup.php?token=eplayhd_setup_2024" class="b bp" style="margin-top:.375rem;display:inline-flex">setup.php খুলুন</a>';
        echo '</div>';
    }

    if (!file_exists($phar)) {
        echo '<a class="b bp" href="?token='.TOKEN.'&do=download">① composer.phar ডাউনলোড করুন</a><br>';
    } else {
        echo '<p class="ok" style="font-size:.8125rem;margin-bottom:.375rem">✓ composer.phar আছে</p>';
    }

    echo '<a class="b bg" href="?token='.TOKEN.'&do=install">② Composer Install চালান</a><br>';
    echo '<a class="b bp" href="?token='.TOKEN.'&do=artisan">③ Cache & Optimize</a>';
    echo '</div>';
}
?>
</body>
</html>
