<?php
// Key-gated deploy for the YouAreTheSongNow app (muse-project) mounted at
// iainreid.dev/museproject/. This host will not run PHP through a symlinked
// folder, so the app lives in a real folder under public_html, shielded by
// a single-layer root .htaccess: private paths forbidden, real static files
// served from public/, everything else to public/index.php. Seeded from a
// runtime tarball in 5 resumable parts (git clone dies with early EOF here).
$stored_hash = 'f74202314a59662883a1d83ca31a6a70bdc223f2b2b59473121c2cba01cb2641';
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
