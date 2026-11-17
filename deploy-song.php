<?php
// Key-gated deploy for the YouAreTheSongNow app (muse-project) mounted at
// iainreid.dev/museproject/. The repo pack is too large for this server's
// GitHub connection (git clone dies with early EOF), so the app is seeded
// from a runtime tarball in 5 parts, each downloaded with curl resume.
// Call once to start the detached seed, again to finish. Anything else 404s.
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
$appdir = '/home/iainmcok/apps/museproject';
$webroot = '/home/iainmcok/public_html/museproject';
$appUrl = 'https://iainreid.dev/museproject';
$log = '/home/iainmcok/museproject-seed.log';
$done = $appdir . '/.seed-done';
if (($_POST['status'] ?? '') === '1') {
    echo 'seed_done: ' . (file_exists($done) ? 'yes' : 'no') . "\n";
    echo 'app_dir: ' . (is_dir($appdir) ? 'exists' : 'missing') . "\n";
    echo 'log_tail:' . "\n";
    echo is_file($log) ? implode('', array_slice(file($log), -15)) : '(no log)';
    exit;
}
if (!file_exists($done)) {
    $logAge = is_file($log) ? time() - filemtime($log) : null;
    $logText = is_file($log) ? (string) file_get_contents($log) : '';
    $seedDied = str_contains($logText, 'curl:') || str_contains($logText, 'sha256sum:');
    if ($logAge !== null && $logAge < 1800 && !$seedDied) {
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
        $dl .= 'curl -L -C - --retry 30 --retry-delay 3 -o /home/iainmcok/apps/mp-part-' . $i . '.bin ' . escapeshellarg($u) . ' || exit 1; ';
    }
    $inner = 'mkdir -p ' . $appdir . ' && cd /home/iainmcok/apps && ' . $dl
        . 'cat mp-part-0.bin mp-part-1.bin mp-part-2.bin mp-part-3.bin mp-part-4.bin > mp-runtime.tar.gz && '
        . 'echo "b69e1557743b0ae8f2b40386f5beb6fcd6c01ff34a169d466510cca46c5894cd  mp-runtime.tar.gz" | sha256sum -c - && '
        . 'tar -xzf mp-runtime.tar.gz -C ' . $appdir . ' && touch ' . $done . ' && echo SEED COMPLETE';
    $cmd = 'nohup bash -c ' . escapeshellarg($inner) . ' > ' . $log . ' 2>&1 &';
    shell_exec($cmd);
    echo "SEED STARTED in background. Call again in a couple of minutes.\n";
    exit;
}
$envFile = $appdir . '/.env';
if (!is_file($envFile)) {
    $appKey = bin2hex(random_bytes(32));
    $env = "APP_ENV=development\nAPP_DEBUG=false\nAPP_URL=$appUrl\nAPP_KEY=$appKey\nAPP_TIMEZONE=America/Los_Angeles\nDATABASE_PATH=var/data/yatsn.sqlite\nPRIVATE_STORAGE_PATH=var/storage\nLOG_PATH=var/log\nALLOW_EXTERNAL_USERS=false\nALLOW_PUBLIC_REGISTRATION=false\nALLOW_LIVE_PAYMENTS=false\nALLOW_PROTECTED_LYRICS_COMMERCIAL_USE=false\nAI_PROVIDERS_ENABLED=false\nSTRIPE_MODE=test\nMAIL_TRANSPORT=log\n";
    file_put_contents($envFile, $env);
    echo ".env written\n";
} else {
    $env = (string) file_get_contents($envFile);
    $fixed = preg_replace('#^APP_URL=.*$#m', 'APP_URL=' . $appUrl, $env);
    if ($fixed !== $env) {
        file_put_contents($envFile, $fixed);
        echo ".env APP_URL updated to $appUrl\n";
    } else {
        echo ".env already present, APP_URL correct\n";
    }
}
$oldLink = '/home/iainmcok/public_html/song';
if (is_link($oldLink)) {
    unlink($oldLink);
    echo "removed earlier /song symlink\n";
}
if (is_link($webroot)) {
    unlink($webroot);
}
if (!file_exists($webroot)) {
    symlink($appdir . '/public', $webroot);
    echo "symlink created: $webroot -> $appdir/public\n";
} elseif (is_dir($webroot)) {
    echo "WARNING: $webroot exists as a real directory, symlink not created\n";
}
echo 'readlink: ' . (readlink($webroot) ?: 'n/a') . "\n";
echo "DEPLOY DONE\n";
