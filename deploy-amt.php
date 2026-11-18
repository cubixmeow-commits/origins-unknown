<?php
// Key-gated deployer for the Agent Mow Toolkit channel app (amt).
// PHP-native only (no shell calls): the host scanner quarantines shell-out scripts.
// The server stores only the SHA-256 hash of the deploy key.
declare(strict_types=1);

const AMT_KEY_HASH = 'e683c7105d29cbaee581384f9afaeb094e178181c44efed132ea148558f61c5a';
const AMT_DIR = '/home/iainmcok/public_html/amt';
// The toolkit repo is private (2026-11-17): file fetches use the GitHub API with a
// read-only fine-grained token, stored outside the web root, entered via the token form.
const AMT_TOKEN_FILE = '/home/iainmcok/.deploy-amt-token';
const AMT_REPO_API = 'https://api.github.com/repos/cubixmeow-commits/agent-mow-toolkit/contents/amt/';

header('Content-Type: text/plain; charset=UTF-8');

$key = (string) ($_POST['key'] ?? $_GET['key'] ?? '');
if (!hash_equals(AMT_KEY_HASH, hash('sha256', $key))) {
    http_response_code(404);
    echo "Not found.\n";
    exit;
}

// Token form: GET ?tokenform=1&key=KEY shows it; its POST saves the GitHub token
// straight to the server-side token file. The token never passes through chat.
if (($_GET['tokenform'] ?? '') === '1' || ($_POST['token_save'] ?? '') === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    if (($_POST['token_save'] ?? '') === '1') {
        if (($_POST['website'] ?? '') !== '') {
            exit('Discarded.');
        }
        $token = trim((string) ($_POST['ghtoken'] ?? ''));
        if ($token === '' || strlen($token) < 20) {
            exit('That does not look like a token. Nothing saved.');
        }
        file_put_contents(AMT_TOKEN_FILE, $token . "\n");
        @chmod(AMT_TOKEN_FILE, 0600);
        exit('Token saved. File syncs will now use it.');
    }
    $has = is_file(AMT_TOKEN_FILE) ? 'A token is already stored; submitting replaces it.' : 'No token stored yet.';
    echo '<!doctype html><meta charset="utf-8"><title>amt deploy token</title>'
        . '<body style="font-family:system-ui;max-width:32rem;margin:3rem auto;padding:0 1rem">'
        . '<h1>GitHub token for amt deploys</h1><p>' . $has . '</p>'
        . '<form method="post"><input type="hidden" name="token_save" value="1">'
        . '<input type="hidden" name="key" value="' . htmlspecialchars($key, ENT_QUOTES) . '">'
        . '<input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">'
        . '<label>Fine-grained token (agent-mow-toolkit, Contents read-only)<br>'
        . '<input type="password" name="ghtoken" style="width:100%;padding:.5rem" autocomplete="off" required></label>'
        . '<p><button type="submit" style="padding:.5rem 1.2rem">Save token</button></p></form></body>';
    exit;
}

if (($_POST['status'] ?? '') === '1') {
    echo 'app_dir: ' . (is_dir(AMT_DIR) ? 'exists' : 'missing') . "\n";
    echo 'github_token: ' . (is_file(AMT_TOKEN_FILE) ? 'stored' : 'MISSING') . "\n";
    echo 'index: ' . (is_file(AMT_DIR . '/index.php') ? 'present' : 'MISSING') . "\n";
    echo 'env: ' . (is_file(AMT_DIR . '/.env') ? 'present' : 'MISSING') . "\n";
    echo 'sqlite: ' . (is_file(AMT_DIR . '/var/data/amt.sqlite') ? 'present' : 'not yet created') . "\n";
    exit;
}

if (($_POST['syncfile'] ?? '') !== '') {
    $allowed = ['index.php', 'src/bootstrap.php', 'src/Mailer.php', 'templates/app.php', 'assets/app.css', 'assets/app.js', '.htaccess'];
    $f = (string) $_POST['syncfile'];
    if (!in_array($f, $allowed, true)) {
        exit("file not in allowlist\n");
    }
    // Private repo: fetch through the GitHub contents API with the stored token.
    // The raw accept header returns the file bytes directly, and API responses are
    // not CDN-cached, so branch fetches are fresh (raw branch URLs served stale copies).
    $token = is_file(AMT_TOKEN_FILE) ? trim((string) file_get_contents(AMT_TOKEN_FILE)) : '';
    if ($token === '') {
        exit("sync failed: no GitHub token stored (open deploy-amt.php?tokenform=1 with the deploy key)\n");
    }
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: deploy-amt\r\nAuthorization: Bearer $token\r\nAccept: application/vnd.github.raw+json\r\n", 'timeout' => 20]]);
    $data = @file_get_contents(AMT_REPO_API . $f . '?ref=main', false, $ctx);
    if ($data === false || $data === '') {
        exit("sync failed: could not fetch $f\n");
    }
    $dir = dirname(AMT_DIR . '/' . $f);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $tmp = AMT_DIR . '/' . $f . '.tmp';
    if (file_put_contents($tmp, $data) === false || !rename($tmp, AMT_DIR . '/' . $f)) {
        exit("sync failed: could not write $f\n");
    }
    echo "SYNCED $f (" . strlen($data) . " bytes)\n";
    exit;
}

if (($_POST['finish'] ?? '') === '1') {
    foreach (['var/data', 'var/files', 'var/log'] as $d) {
        if (!is_dir(AMT_DIR . '/' . $d)) {
            mkdir(AMT_DIR . '/' . $d, 0775, true);
        }
    }
    $envFile = AMT_DIR . '/.env';
    if (is_file($envFile)) {
        echo "env: already present, left unchanged\n";
    } else {
        // Copy the mail settings from the You Are The Song Now app on this same server.
        // Values are read and written server-side only; they never leave this machine.
        $mail = [];
        $songEnv = '/home/iainmcok/public_html/museproject/.env';
        if (is_file($songEnv)) {
            foreach (file($songEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    $mail[trim($k)] = trim($v);
                }
            }
        }
        $bootstrap = bin2hex(random_bytes(24));
        $lines = [
            'APP_URL=https://iainreid.dev/amt',
            'BOOTSTRAP_KEY=' . $bootstrap,
            'INVITE_EMAIL=monoboob666@gmail.com',
            'HUMAN_A_NAME=Noa',
            "AGENT_A_NAME=Noa's Muse (Agent Mow)",
            'HUMAN_B_NAME=Brut',
            "AGENT_B_NAME=Brut's Muse",
            'MAX_FOLDER_BYTES=104857600',
            'MAX_FILE_BYTES=26214400',
            'MAIL_TRANSPORT=smtp',
            'MAIL_HOST=' . ($mail['MAIL_HOST'] ?? 'server365.web-hosting.com'),
            'MAIL_PORT=' . ($mail['MAIL_PORT'] ?? '465'),
            'MAIL_USERNAME=' . ($mail['MAIL_USERNAME'] ?? ''),
            'MAIL_PASSWORD=' . ($mail['MAIL_PASSWORD'] ?? ''),
            'MAIL_FROM_ADDRESS=' . ($mail['MAIL_FROM_ADDRESS'] ?? ''),
            'MAIL_FROM_NAME=Agent Mow Toolkit',
        ];
        file_put_contents($envFile, implode("\n", $lines) . "\n");
        @chmod($envFile, 0600);
        echo "env: written (mail settings copied from the song app: " . (isset($mail['MAIL_PASSWORD']) && $mail['MAIL_PASSWORD'] !== '' ? 'password found' : 'PASSWORD MISSING') . ")\n";
        echo "BOOTSTRAP_KEY: $bootstrap\n";
    }
    echo "FINISH DONE\n";
    exit;
}

if (($_POST['claimtokens'] ?? '') === '1') {
    $envFile = AMT_DIR . '/.env';
    if (!is_file($envFile)) {
        exit("env missing\n");
    }
    $contents = (string) file_get_contents($envFile);
    foreach (['CLAIM_A_TOKEN', 'CLAIM_B_TOKEN'] as $name) {
        if (!preg_match('/^' . $name . '=/m', $contents)) {
            $value = bin2hex(random_bytes(16));
            file_put_contents($envFile, $name . '=' . $value . "\n", FILE_APPEND);
            $contents .= $name . '=' . $value . "\n";
            echo "$name: $value\n";
        } else {
            preg_match('/^' . $name . '=(.+)$/m', $contents, $m);
            echo "$name: " . trim($m[1]) . "\n";
        }
    }
    exit;
}

if (($_POST['showbootstrap'] ?? '') === '1') {
    $envFile = AMT_DIR . '/.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
            if (str_starts_with($line, 'BOOTSTRAP_KEY=')) {
                echo trim(substr($line, strlen('BOOTSTRAP_KEY='))) . "\n";
                exit;
            }
        }
    }
    echo "not set\n";
    exit;
}

if (($_POST['rotateenv'] ?? '') === '1') {
    $envFile = AMT_DIR . '/.env';
    if (!is_file($envFile)) {
        exit("env missing\n");
    }
    $fresh = [
        'BOOTSTRAP_KEY' => bin2hex(random_bytes(24)),
        'CLAIM_A_TOKEN' => bin2hex(random_bytes(16)),
        'CLAIM_B_TOKEN' => bin2hex(random_bytes(16)),
    ];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES);
    $seen = [];
    foreach ($lines as $i => $line) {
        foreach ($fresh as $name => $value) {
            if (str_starts_with($line, $name . '=')) {
                $lines[$i] = $name . '=' . $value;
                $seen[$name] = true;
            }
        }
    }
    foreach ($fresh as $name => $value) {
        if (empty($seen[$name])) {
            $lines[] = $name . '=' . $value;
        }
    }
    file_put_contents($envFile, implode("\n", $lines) . "\n");
    header('Content-Type: application/json');
    echo json_encode($fresh) . "\n";
    exit;
}

echo "deploy-amt ready\n";
