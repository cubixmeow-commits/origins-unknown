<?php
// Key-gated deploy for the YouAreTheSongNow app (muse-project) mounted at
// iainreid.dev/museproject/. This host will not run PHP through a symlinked
// folder, so the app lives in a real folder under public_html, shielded by
// a root .htaccess that rewrites everything into public/ and forbids the
// private paths. Seeded from a runtime tarball in 5 resumable parts (git
// clone of this repo dies with early EOF on this host's GitHub route).
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
if (($_POST['status'] ?? '') === '1') {
    echo 'seed_done: ' . (file_exists($done) ? 'yes' : 'no') . "\n";
    echo 'app_dir: ' . (is_dir($appdir) ? 'exists' : 'missing') . "\n";
    echo 'log_tail:' . "\n";
    echo is_file($log) ? implode('', array_slice(file($log), -6)) : '(no log)';
    exit;
}
// If a previous attempt left a symlink at the app path, replace it with a
// real directory first, so the seed extracts into the right place.
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
// Remove the out-of-webroot seed tree from the symlink attempt.
shell_exec('rm -rf /home/iainmcok/apps/museproject');
$envFile = $appdir . '/.env';
if (!is_file($envFile)) {
    $appKey = bin2hex(random_bytes(32));
    $env = "APP_ENV=development\nAPP_DEBUG=false\nAPP_URL=$appUrl\nAPP_KEY=$appKey\nAPP_TIMEZONE=America/Los_Angeles\nDATABASE_PATH=var/data/yatsn.sqlite\nPRIVATE_STORAGE_PATH=var/storage\nLOG_PATH=var/log\nALLOW_EXTERNAL_USERS=false\nALLOW_PUBLIC_REGISTRATION=false\nALLOW_LIVE_PAYMENTS=false\nALLOW_PROTECTED_LYRICS_COMMERCIAL_USE=false\nAI_PROVIDERS_ENABLED=false\nSTRIPE_MODE=test\nMAIL_TRANSPORT=log\n";
    file_put_contents($envFile, $env);
    echo ".env written\n";
}
$shield = "Options -Indexes\nRewriteEngine On\nRewriteRule ^\\.env(\$|/) - [F,L]\nRewriteRule ^(app|src|templates|var|bin|worker|database|assets|tests|design|docs|development-vault)/ - [F,L]\nRewriteRule ^(.*)\$ public/\$1 [L]\n";
file_put_contents($appdir . '/.htaccess', $shield);
echo "shield .htaccess written\n";
echo "DEPLOY DONE\n";
