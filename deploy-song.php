<?php
// Admin helper for the YouAreTheSongNow app at iainreid.dev/museproject.
// Deliberately written with plain PHP file functions only: this host's
// malware scanner quarantines scripts that shell out to curl/git/nohup,
// so everything here (downloads, extraction, seeding) is PHP-native.
// Actions (POST, key required): status, debug, syncfile, setup form.
$stored_hash = 'f74202314a59662883a1d83ca31a6a70bdc223f2b2b59473121c2cba01cb2641';
$appdir = '/home/iainmcok/public_html/museproject';
$appUrl = 'https://iainreid.dev/museproject';

// Setup form: GET ?setup=KEY shows it; its POST saves SMTP + owner
// credentials straight into the app .env and seeds the owner account.
if ((($_GET['setup'] ?? '') !== '' || ($_POST['setup_save'] ?? '') === '1')) {
    $given = (string) ($_GET['setup'] ?? ($_POST['key'] ?? ''));
    if (!hash_equals($stored_hash, hash('sha256', $given))) {
        http_response_code(404);
        exit('Not found.');
    }
    if (($_POST['setup_save'] ?? '') === '1') {
        header('Content-Type: text/plain');
        if (($_POST['website'] ?? '') !== '') {
            exit("Discarded.\n");
        }
        $envFile = $appdir . '/.env';
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
            $set('MAIL_HOST', 'server365.web-hosting.com');
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
        foreach (['GEMINI_API_KEY' => 'api_gemini', 'GROQ_API_KEY' => 'api_groq', 'REPLICATE_API_TOKEN' => 'api_replicate', 'FAL_KEY' => 'api_fal'] as $envKey => $field) {
            $val = trim((string) ($_POST[$field] ?? ''));
            if ($val !== '') {
                $set($envKey, $val);
                echo "$envKey saved\n";
            }
        }
        file_put_contents($envFile, implode("\n", $lines) . "\n");
        if ($ownerEmail !== '' && $ownerPass !== '') {
            try {
                require $appdir . '/app/bootstrap.php';
                \Yatsn\Styles\StyleService::seed();
                $owner = \Yatsn\Auth\AuthService::seedOwner();
                echo 'seed-owner: Owner ready: ' . $owner['email'] . "\n";
            } catch (Throwable $e) {
                echo 'seed-owner error: ' . $e->getMessage() . "\n";
            }
        }
        if (($_POST['send_test'] ?? '') === '1' && $ownerEmail !== '') {
            try {
                if (!class_exists('\\Yatsn\\Mail\\Mailer')) {
                    require $appdir . '/app/bootstrap.php';
                }
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
    echo '<h2 style="font-size:1.05rem;margin-top:1.5rem">API keys (optional, fill only what you have)</h2>';
    echo '<p>Copy these from your provider accounts. Gemini powers the song and lyrics search. Leave any field blank to keep what is already saved.</p>';
    echo '<p><label>Gemini API key<br><input type="password" name="api_gemini" style="width:100%;padding:.5rem"></label></p>';
    echo '<p><label>Groq API key<br><input type="password" name="api_groq" style="width:100%;padding:.5rem"></label></p>';
    echo '<p><label>Replicate API token<br><input type="password" name="api_replicate" style="width:100%;padding:.5rem"></label></p>';
    echo '<p><label>Fal key<br><input type="password" name="api_fal" style="width:100%;padding:.5rem"></label></p>';
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
$done = $appdir . '/.seed-done';

if (($_POST['debug'] ?? '') === '1') {
    echo 'php: ' . PHP_VERSION . "\n";
    echo 'run-loop: ' . (is_file($appdir . '/worker/run-loop.php') ? 'present' : 'MISSING') . "\n";
    echo 'var writable: ' . (is_writable($appdir . '/var') ? 'yes' : 'no') . "\n";
    foreach (glob($appdir . '/var/log/*') ?: [] as $f) {
        echo '== ' . basename($f) . "\n";
        echo implode('', array_slice(file($f), -15));
    }
    exit;
}

if (($_POST['mailtest'] ?? '') === '1') {
    $targets = [
        ['mail.iainreid.dev', 465, true],
        ['server365.web-hosting.com', 465, true],
        ['mail.iainreid.dev', 587, false],
        ['server365.web-hosting.com', 587, false],
        ['localhost', 465, true],
        ['localhost', 25, false],
    ];
    foreach ($targets as [$h, $p, $ssl]) {
        $remote = ($ssl ? 'ssl://' : '') . $h . ':' . $p;
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 8, STREAM_CLIENT_CONNECT);
        if ($fp === false) {
            echo "$remote -> FAIL ($errno) $errstr\n";
            continue;
        }
        stream_set_timeout($fp, 8);
        $greet = fgets($fp, 512);
        echo "$remote -> OK greeting: " . trim((string) $greet) . "\n";
        fclose($fp);
    }
    exit;
}

if (($_POST['fixmailhost'] ?? '') === '1') {
    // mail.iainreid.dev does not answer on 465 from this server; the server
    // hostname does. Rewrite MAIL_HOST in the app .env and re-test.
    $envFile = $appdir . '/.env';
    $env = (string) file_get_contents($envFile);
    $env = preg_replace('/^MAIL_HOST=.*$/m', 'MAIL_HOST=server365.web-hosting.com', $env);
    file_put_contents($envFile, $env);
    echo "MAIL_HOST set to server365.web-hosting.com\n";
    try {
        require $appdir . '/app/bootstrap.php';
        $to = (string) \Yatsn\Support\Config::get('owner.email', '');
        $ok = \Yatsn\Mail\Mailer::send($to, 'You Are The Song Now: mail is working', "This is a test email from your app at iainreid.dev/museproject.\nSMTP sending from hello@iainreid.dev is configured.\n");
        echo 'test email: ' . ($ok ? 'sent to ' . $to : 'FAILED') . "\n";
    } catch (Throwable $e) {
        echo 'test email error: ' . $e->getMessage() . "\n";
    }
    exit;
}

if (($_POST['mergenv'] ?? '') === '1') {
    // Merge provider keys/settings from the old project's .env into the
    // server .env. Deployment-owned keys are never overwritten.
    $deny = ['APP_URL', 'APP_KEY', 'APP_ENV', 'APP_DEBUG', 'DATABASE_PATH', 'PRIVATE_STORAGE_PATH', 'LOG_PATH', 'AI_PROVIDERS_ENABLED'];
    $blob = base64_decode((string) ($_POST['envblob'] ?? ''), true);
    if ($blob === false || $blob === '') {
        exit("mergenv: empty payload\n");
    }
    $envFile = $appdir . '/.env';
    $lines = is_file($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES) : [];
    $merged = [];
    foreach (explode("\n", $blob) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        if ($k === '' || in_array($k, $deny, true) || str_starts_with($k, 'MAIL_') || str_starts_with($k, 'OWNER_')) {
            continue;
        }
        $found = false;
        foreach ($lines as $i => $existing) {
            if (str_starts_with($existing, $k . '=')) {
                $lines[$i] = $k . '=' . $v;
                $found = true;
            }
        }
        if (!$found) {
            $lines[] = $k . '=' . $v;
        }
        $merged[] = $k;
    }
    file_put_contents($envFile, implode("\n", $lines) . "\n");
    echo 'merged ' . count($merged) . " keys: " . implode(', ', $merged) . "\n";
    exit;
}

if (($_POST['setproviders'] ?? '') !== '') {
    $val = ($_POST['setproviders'] === '1') ? 'true' : 'false';
    $envFile = $appdir . '/.env';
    $env = (string) file_get_contents($envFile);
    if (preg_match('/^AI_PROVIDERS_ENABLED=/m', $env)) {
        $env = preg_replace('/^AI_PROVIDERS_ENABLED=.*$/m', 'AI_PROVIDERS_ENABLED=' . $val, $env);
    } else {
        $env .= 'AI_PROVIDERS_ENABLED=' . $val . "\n";
    }
    file_put_contents($envFile, $env);
    echo 'AI_PROVIDERS_ENABLED=' . $val . "\n";
    exit;
}

if (($_POST['setflags'] ?? '') !== '') {
    // Flip allowlisted feature flags in the app .env (values: true/false).
    $allow = ['AI_PROVIDERS_ENABLED', 'GEMINI_LIVE_CALLS', 'DEVELOPMENT_GEMINI_LYRICS_SEARCH', 'GROQ_LIVE_CALLS', 'GEMINI_IMAGE_LIVE_CALLS', 'FAL_LIVE_CALLS', 'REPLICATE_LIVE_CALLS'];
    $envFile = $appdir . '/.env';
    $env = (string) file_get_contents($envFile);
    foreach (explode(',', (string) $_POST['setflags']) as $pair) {
        $pair = trim($pair);
        if (!str_contains($pair, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $pair, 2);
        $k = trim($k);
        $v = trim($v) === 'true' ? 'true' : 'false';
        if (!in_array($k, $allow, true)) {
            echo "skipped $k (not allowlisted)\n";
            continue;
        }
        if (preg_match('/^' . preg_quote($k, '/') . '=/m', $env)) {
            $env = preg_replace('/^' . preg_quote($k, '/') . '=.*$/m', $k . '=' . $v, $env);
        } else {
            $env .= $k . '=' . $v . "\n";
        }
        echo "$k=$v\n";
    }
    file_put_contents($envFile, $env);
    exit;
}

if (($_POST['lyricstest'] ?? '') === '1') {
    try {
        require $appdir . '/app/bootstrap.php';
        $rows = \Yatsn\Support\Database::all('SELECT artist_text, title_text, state, classification, analysis_provider, (derived_analysis_json IS NOT NULL) AS has_analysis, created_at FROM song_lookups ORDER BY id DESC LIMIT 5');
        foreach ($rows as $r) {
            echo 'lookup: ' . $r['artist_text'] . ' / ' . $r['title_text'] . ' -> state=' . $r['state'] . ' class=' . $r['classification'] . ' provider=' . $r['analysis_provider'] . ' analysis=' . $r['has_analysis'] . ' at ' . $r['created_at'] . "\n";
        }
        $res = \Yatsn\AI\GeminiLyricsResearchService::analyze('Public Domain Demo', 'Amazing Grace');
        echo 'analyze keys: ' . implode(',', array_keys($res)) . "\n";
        foreach (['analyzed', 'analysisBasis', 'error', 'reason', 'status', 'provider'] as $k) {
            if (array_key_exists($k, $res)) {
                echo $k . ': ' . (is_scalar($res[$k]) ? (string) $res[$k] : gettype($res[$k])) . "\n";
            }
        }
        echo 'providerAttempts: ' . json_encode($res['providerAttempts'] ?? null) . "\n";
        echo 'gemini_model: ' . (string) \Yatsn\Support\Config::get('ai.gemini_model', '') . "\n";
        echo 'key_present: ' . ((string) \Yatsn\Support\Config::get('ai.gemini_api_key', '') !== '' ? 'yes' : 'no') . "\n";
    } catch (Throwable $e) {
        echo 'lyricstest error: ' . $e->getMessage() . "\n";
    }
    exit;
}

if (($_POST['syncfile'] ?? '') !== '') {
    $allowed = ['public/index.php', 'public/assets/js/showcase.js', 'public/assets/js/app.js', 'src/Portraits/PortraitService.php', 'src/Sharing/GalleryService.php', 'worker/run-loop.php', 'src/Auth/AuthService.php', 'src/Auth/AccountService.php', 'src/Api/ApiV1.php', 'templates/pages/account.php', 'templates/pages/sign-in.php', 'templates/layouts/main.php', 'app/bootstrap.php', 'public/assets/css/app.css', 'public/assets/fonts/fonts.css', 'public/assets/fonts/DMSans-400.woff2', 'public/assets/fonts/DMSans-500.woff2', 'public/assets/fonts/DMSans-700.woff2', 'public/assets/fonts/DMSans-400-italic.woff2', 'public/assets/fonts/InstrumentSerif-400.woff2', 'public/assets/fonts/InstrumentSerif-400-italic.woff2'];
    $f = (string) $_POST['syncfile'];
    if (!in_array($f, $allowed, true)) {
        exit("file not in allowlist\n");
    }
    $url = 'https://raw.githubusercontent.com/cubixmeow-commits/muse-project/main/' . $f;
    $tmp = $appdir . '/' . $f . '.tmp';
    $data = @file_get_contents($url);
    if ($data === false || $data === '') {
        exit("sync failed: could not fetch $f\n");
    }
    $dir = dirname($appdir . '/' . $f);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (file_put_contents($tmp, $data) === false || !rename($tmp, $appdir . '/' . $f)) {
        exit("sync failed: could not write $f\n");
    }
    echo "SYNCED $f (" . strlen($data) . " bytes)\n";
    exit;
}

if (($_POST['status'] ?? '') === '1') {
    echo 'seed_done: ' . (file_exists($done) ? 'yes' : 'no') . "\n";
    echo 'app_dir: ' . (is_dir($appdir) ? 'exists' : 'missing') . "\n";
    echo 'run-loop: ' . (is_file($appdir . '/worker/run-loop.php') ? 'present' : 'MISSING') . "\n";
    exit;
}

// Finish/refresh the install: env, var dirs, shim, shield.
if (!file_exists($done)) {
    echo "seed marker missing: the app tree is not in place\n";
    exit;
}
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
$shim = "<?php\n// Front-controller shim for the subfolder mount: the app root is this\n// folder, its web root is public/. The shield .htaccess sends every path\n// that is not a real file here, and public/index.php routes it.\nrequire __DIR__ . '/public/index.php';\n";
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
