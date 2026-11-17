<?php
// Key-gated deploy trigger for the YouAreTheSongNow app (muse-project repo)
// mounted at iainreid.dev/museproject/. The repo is large, so the first
// clone runs detached on the server: call once to start it, call again to
// finish the deploy once the clone marker appears. Anything else 404s.
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
$repo = '/home/iainmcok/repositories/muse-project';
$webroot = '/home/iainmcok/public_html/museproject';
$appUrl = 'https://iainreid.dev/museproject';
$log = '/home/iainmcok/repositories/muse-project-clone.log';
$done = $repo . '/.clone-done';
if (!file_exists($done)) {
    $logAge = is_file($log) ? time() - filemtime($log) : null;
    if (is_dir($repo) && $logAge !== null && $logAge < 1800) {
        echo "CLONE IN PROGRESS (log age {$logAge}s). Call again in a bit.\n";
        exit;
    }
    if (is_dir($repo)) {
        shell_exec('rm -rf ' . $repo);
    }
    $cmd = 'nohup bash -c ' . escapeshellarg('git clone --depth 1 https://github.com/cubixmeow-commits/muse-project.git ' . $repo . ' && touch ' . $done) . ' > ' . $log . ' 2>&1 &';
    shell_exec($cmd);
    echo "CLONE STARTED in background. Call again in a couple of minutes.\n";
    exit;
}
echo shell_exec("cd $repo && git fetch --depth 1 origin main 2>&1") . "\n";
echo shell_exec("cd $repo && git reset --hard origin/main 2>&1") . "\n";
echo shell_exec("cd $repo && git log --oneline -1 2>&1") . "\n";
$envFile = $repo . '/.env';
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
if (is_link($oldLink) && readlink($oldLink) === $repo . '/public') {
    unlink($oldLink);
    echo "removed earlier /song symlink\n";
}
if (is_link($webroot)) {
    unlink($webroot);
}
if (!file_exists($webroot)) {
    symlink($repo . '/public', $webroot);
    echo "symlink created: $webroot -> $repo/public\n";
} elseif (is_dir($webroot)) {
    echo "WARNING: $webroot exists as a real directory, symlink not created\n";
}
echo 'readlink: ' . (readlink($webroot) ?: 'n/a') . "\n";
echo "DEPLOY DONE\n";
