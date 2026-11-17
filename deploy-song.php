<?php
// Key-gated deploy for the YouAreTheSongNow app (muse-project) mounted at
// iainreid.dev/museproject/. This host will not run PHP through a symlinked
// folder, so the app lives in a real folder under public_html, shielded by
// a single-layer root .htaccess: private paths forbidden, real static files
// served from public/, everything else to public/index.php. Seeded from a
// runtime tarball in 5 resumable parts (git clone dies with early EOF here).
$stored_hash = 'f74202314a59662883a1d83ca31a6a70bdc223f2b2b59473121c2cba01cb2641';
// Key-gated setup form: Noa enters the hello@ mailbox password and his
// owner login here; they are written straight into the app .env on the
// server and never pass through chat. GET ?setup=KEY shows the form.
if ((($_GET['setup'] ?? '') !== '' || ($_POST['setup_save'] ?? '') === '1')) {
    $given = (string) ($_GET['setup'] ?? ($_POST['key'] ?? ''));
    if (!hash_equals($stored_hash, hash('sha256', $given))) {
        http_response_code(404);
        exit('Not found.');
    }
    $appdirSetup = '/home/iainmcok/public_html/museproject';
    if (($_POST['setup_save'] ?? '') === '1') {
        header('Content-Type: text/plain');
        if (($_POST['website'] ?? '') !== '') {
            exit("Discarded.\n");
        }
        $envFile = $appdirSetup . '/.env';
        $lines = is_file($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES) : [];
        $set = function (string $k, string $v) use (&$lines): void {
            $found = false;
            foreach ($lines as $i => $line) {
                if (str_starts_with($line, $k . '=')) {
                    $lines[$i] = $k . '=' . $v;
                    $found = true;
                }
            }
            if (!$found) {
                $lines[] = $k . '=' . $v;
            }
        };
        $mailPass = trim((string) ($_POST['mail_password'] ?? ''));
        $ownerEmail = trim((string) ($_POST['owner_email'] ?? ''));
        $ownerPass = (string) ($_POST['owner_password'] ?? '');
        if ($mailPass !== '') {
            $set('MAIL_TRANSPORT', 'smtp');
            $set('MAIL_HOST', 'mail.iainreid.dev');
            $set('MAIL_PORT', '465');
            $set('MAIL_ENCRYPTION', 'ssl');
            $set('MAIL_USERNAME', 'hello@iainreid.dev');
            $set('MAIL_PASSWORD', $mailPass);
            $set('MAIL_FROM_ADDRESS', 'hello@iainreid.dev');
            $set('MAIL_FROM_NAME', 'You Are The Song Now');
            echo "mail settings saved (SMTP, hello@iainreid.dev)\n";
        }
        if ($ownerEmail !== '' && $ownerPass !== '') {
            $set('OWNER_EMAIL', $ownerEmail);
            $set('OWNER_PASSWORD', $ownerPass);
            echo "owner credentials saved\n";
        }
        file_put_contents($envFile, implode("\n", $lines) . "\n");
        if ($ownerEmail !== '' && $ownerPass !== '') {
            $out = shell_exec('cd ' . escapeshellarg($appdirSetup) . ' && php bin/console.php seed-owner 2>&1');
            echo 'seed-owner: ' . trim((string) $out) . "\n";
        }
        if (($_POST['send_test'] ?? '') === '1' && $ownerEmail !== '') {
            try {
                require $appdirSetup . '/app/bootstrap.php';
                $ok = \Yatsn\Mail\Mailer::send($ownerEmail, 'You Are The Song Now: mail is working', "This is a test email from your app at iainreid.dev/museproject.\nSMTP sending from hello@iainreid.dev is configured.\n");
                echo 'test email: ' . ($ok ? 'sent to ' . $ownerEmail : 'FAILED, check the mailbox exists and the password matches') . "\n";
            } catch (Throwable $e) {
                echo 'test email error: ' . $e->getMessage() . "\n";
            }
        }
        echo "SETUP DONE\n";
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    $keyEsc = htmlspecialchars($given, ENT_QUOTES);
    echo '<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1"><title>Song app setup</title>';
    echo '<body style="font-family:system-ui;max-width:34rem;margin:2rem auto;padding:0 1rem;background:#0b0f1c;color:#eef0f8">';
    echo '<h1 style="font-size:1.3rem">You Are The Song Now: mail and owner setup</h1>';
    echo '<p>First create the mailbox <b>hello@iainreid.dev</b> in cPanel (Email Accounts) and set its password. Then enter that same password here, plus the email and password you want for your owner login. Values go straight into the server config.</p>';
    echo '<form method="post" action="">';
    echo '<input type="hidden" name="key" value="' . $keyEsc . '">';
    echo '<input type="hidden" name="setup_save" value="1">';
    echo '<p style="display:none"><label>Website <input name="website" autocomplete="off"></label></p>';
    echo '<p><label>hello@ mailbox password<br><input type="password" name="mail_password" style="width:100%;padding:.5rem"></label></p>';
    echo '<p><label>Your owner email<br><input type="email" name="owner_email" style="width:100%;padding:.5rem"></label></p>';
    echo '<p><label>Your owner password<br><input type="password" name="owner_password" style="width:100%;padding:.5rem"></label></p>';
    echo '<p><label><input type="checkbox" name="send_test" value="1" checked> Send me a test email after saving</label></p>';
    echo '<p><button type="submit" style="padding:.6rem 1.2rem">Save and seed owner</button></p>';
    echo '</form></body>';
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(404);
    exit('Not found.');
}
$key = $_POST['key'] ?? '';
if (!hash_equals($stored_hash, hash('sha256', (string)$key))) {
    http_response_code(404);
    exit('Not found.');
}
set_time_limit(120);
header('Content-Type: text/plain');
$appdir = '/home/iainmcok/public_html/museproject';
$appUrl = 'https://iainreid.dev/museproject';
$log = '/home/iainmcok/museproject-seed.log';
$done = $appdir . '/.seed-done';
if (($_POST['debug'] ?? '') === '1') {
    echo 'php: ' . PHP_VERSION . "\n";
    echo 'public htaccess: ' . (is_file($appdir . '/public/.htaccess') ? 'exists' : 'missing') . "\n";
    echo 'var writable: ' . (is_writable($appdir . '/var') ? 'yes' : 'no') . "\n";
    echo 'public/index.php: ' . (is_file($appdir . '/public/index.php') ? 'exists' : 'MISSING') . "\n";
    echo 'app/bootstrap.php: ' . (is_file($appdir . '/app/bootstrap.php') ? 'exists' : 'MISSING') . "\n";
    echo 'appdir entries: ' . implode(',', array_slice(scandir($appdir) ?: [], 0, 30)) . "\n";
    echo 'public entries: ' . implode(',', array_slice(scandir($appdir . '/public') ?: [], 0, 30)) . "\n";
    foreach (glob($appdir . '/var/log/*') ?: [] as $f) {
        echo '== ' . basename($f) . "\n";
        echo implode('', array_slice(file($f), -25));
    }
    exit;
}
if (($_POST['syncfile'] ?? '') !== '') {
    $allowed = ['public/index.php', 'public/assets/js/showcase.js', 'src/Portraits/PortraitService.php', 'src/Sharing/GalleryService.php', 'worker/run-loop.php'];
    $f = (string) $_POST['syncfile'];
    if (!in_array($f, $allowed, true)) {
        exit("file not in allowlist\n");
    }
    $url = 'https://raw.githubusercontent.com/cubixmeow-commits/muse-project/main/' . $f;
    $code = shell_exec('curl -sS -L --retry 5 --retry-delay 2 -o ' . escapeshellarg($appdir . '/' . $f . '.tmp') . ' ' . escapeshellarg($url) . ' && mv ' . escapeshellarg($appdir . '/' . $f . '.tmp') . ' ' . escapeshellarg($appdir . '/' . $f) . ' && echo SYNCED');
    echo $code ?: "sync failed\n";
    exit;
}
if (($_POST['status'] ?? '') === '1') {
    echo 'seed_done: ' . (file_exists($done) ? 'yes' : 'no') . "\n";
    echo 'app_dir: ' . (is_dir($appdir) ? 'exists' : 'missing') . "\n";
    echo 'log_tail:' . "\n";
    echo is_file($log) ? implode('', array_slice(file($log), -6)) : '(no log)';
    exit;
}
if (is_link($appdir)) {
    unlink($appdir);
    mkdir($appdir, 0755, true);
    echo "replaced symlink with real directory\n";
}
if (!file_exists($done)) {
    $logAge = is_file($log) ? time() - filemtime($log) : null;
    $logText = is_file($log) ? (string) file_get_contents($log) : '';
    $seedDied = str_contains($logText, 'curl:') || str_contains($logText, 'sha256sum:');
    $seedFinished = str_contains($logText, 'SEED COMPLETE');
    if ($logAge !== null && $logAge < 1800 && !$seedDied && !$seedFinished) {
        echo "SEED IN PROGRESS (log age {$logAge}s). Call again in a bit.\n";
        exit;
    }
    $parts = [
        'https://muse.ai/files/1021888587678131/1865668541445951/elj11pywlbtztqfv91xv4pdg/part-00.bin',
        'https://muse.ai/files/1021888587678131/2017415042286618/z7rce7evprkjld7oep6cxz7v/part-01.bin',
        'https://muse.ai/files/1021888587678131/1043471985443624/8eed2q5fd5kacijvxyjoynj6/part-02.bin',
        'https://muse.ai/files/1021888587678131/1174634428599583/jlioyzdnebb9uun8j12yrzwi/part-03.bin',
        'https://muse.ai/files/1021888587678131/2609420362901372/sf5h8jgcx2pxlsdqqukn73ci/part-04.bin',
    ];
    $dl = '';
    foreach ($parts as $i => $u) {
        $dl .= 'curl -sS -L -C - --retry 30 --retry-delay 3 -o /home/iainmcok/apps/mp-part-' . $i . '.bin ' . escapeshellarg($u) . ' || exit 1; ';
    }
    $inner = 'mkdir -p /home/iainmcok/apps && mkdir -p ' . $appdir . ' && cd /home/iainmcok/apps && ' . $dl
        . 'cat mp-part-0.bin mp-part-1.bin mp-part-2.bin mp-part-3.bin mp-part-4.bin > mp-runtime.tar.gz && '
        . 'echo "b69e1557743b0ae8f2b40386f5beb6fcd6c01ff34a169d466510cca46c5894cd  mp-runtime.tar.gz" | sha256sum -c - && '
        . 'tar -xzf mp-runtime.tar.gz -C ' . $appdir . ' && touch ' . $done . ' && echo SEED COMPLETE';
    $cmd = 'nohup bash -c ' . escapeshellarg($inner) . ' > ' . $log . ' 2>&1 &';
    shell_exec($cmd);
    echo "SEED STARTED in background. Call again in a couple of minutes.\n";
    exit;
}
shell_exec('rm -rf /home/iainmcok/apps/museproject');
$envFile = $appdir . '/.env';
if (!is_file($envFile)) {
    $appKey = bin2hex(random_bytes(32));
    $env = "APP_ENV=development\nAPP_DEBUG=false\nAPP_URL=$appUrl\nAPP_KEY=$appKey\nAPP_TIMEZONE=America/Los_Angeles\nDATABASE_PATH=var/data/yatsn.sqlite\nPRIVATE_STORAGE_PATH=var/storage\nLOG_PATH=var/log\nALLOW_EXTERNAL_USERS=false\nALLOW_PUBLIC_REGISTRATION=false\nALLOW_LIVE_PAYMENTS=false\nALLOW_PROTECTED_LYRICS_COMMERCIAL_USE=false\nAI_PROVIDERS_ENABLED=false\nSTRIPE_MODE=test\nMAIL_TRANSPORT=log\n";
    file_put_contents($envFile, $env);
    echo ".env written\n";
}
foreach (['var', 'var/data', 'var/storage', 'var/log'] as $d) {
    if (!is_dir($appdir . '/' . $d)) {
        mkdir($appdir . '/' . $d, 0775, true);
    }
    chmod($appdir . '/' . $d, 0775);
}
echo "var dirs ready\n";
if (is_file($appdir . '/public/.htaccess')) {
    rename($appdir . '/public/.htaccess', $appdir . '/public/.htaccess-app-backup');
    echo "public/.htaccess moved aside (single-layer shield)\n";
}
$shim = "<?php\n// Front-controller shim for the subfolder mount: the app root is this\n// folder, its web root is public/. FallbackResource sends every path\n// that is not a real file here, and public/index.php routes it.\nrequire __DIR__ . '/public/index.php';\n";
file_put_contents($appdir . '/index.php', $shim);
echo "root index.php shim written\n";
$shield = "Options -Indexes\n"
    . "RewriteEngine On\n"
    . "RewriteRule ^\\.env($|/) - [F,L]\n"
    . "RewriteRule ^(app|src|templates|var|bin|worker|database|tests|design|docs|development-vault)/ - [F,L]\n"
    . "RewriteCond %{REQUEST_FILENAME} !-f\n"
    . "RewriteCond %{REQUEST_FILENAME} !-d\n"
    . "RewriteRule ^(.*)$ index.php [QSA,L]\n";
file_put_contents($appdir . '/.htaccess', $shield);
echo "shield .htaccess written\n";
echo "DEPLOY DONE\n";
