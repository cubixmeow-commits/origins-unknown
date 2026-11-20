<?php
// Key-gated deployer for the Purrnia site at iainreid.dev/purrnia.
// PHP-native only (no shell calls): the host scanner quarantines shell-out scripts.
// The server stores only the SHA-256 hash of the deploy key.
// The purrnia repo is public: files are fetched through the GitHub contents API
// (API responses are not CDN-cached, so fetches are fresh).
declare(strict_types=1);

const PURRNIA_KEY_HASH = 'fb57c8f4ba9303f2f909bfe226486a70977d8d95287cc6864753cdb1e4464968';
const PURRNIA_DIR = '/home/iainmcok/public_html/purrnia';
const PURRNIA_REPO_API = 'https://api.github.com/repos/cubixmeow-commits/purrnia/contents/';

header('Content-Type: text/plain; charset=UTF-8');

$key = (string) ($_POST['key'] ?? $_GET['key'] ?? '');
if (!hash_equals(PURRNIA_KEY_HASH, hash('sha256', $key))) {
    http_response_code(404);
    echo "Not found.\n";
    exit;
}

if (($_POST['status'] ?? '') === '1') {
    echo 'site_dir: ' . (is_dir(PURRNIA_DIR) ? 'exists' : 'missing') . "\n";
    foreach (['index.html', 'purrnia.php', 'about.php', 'purrnia-map-homepage.png'] as $f) {
        echo $f . ': ' . (is_file(PURRNIA_DIR . '/' . $f) ? filesize(PURRNIA_DIR . '/' . $f) . ' bytes' : 'MISSING') . "\n";
    }
    exit;
}

if (($_POST['syncfile'] ?? '') !== '') {
    $allowed = ['index.html', 'purrnia.php', 'about.php', 'purrnia-map-homepage.png'];
    $f = (string) $_POST['syncfile'];
    if (!in_array($f, $allowed, true)) {
        exit("file not in allowlist\n");
    }
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: deploy-purrnia\r\nAccept: application/vnd.github.raw+json\r\n", 'timeout' => 30]]);
    $data = @file_get_contents(PURRNIA_REPO_API . $f . '?ref=main', false, $ctx);
    if ($data === false || $data === '') {
        exit("sync failed: could not fetch $f\n");
    }
    if (!is_dir(PURRNIA_DIR)) {
        mkdir(PURRNIA_DIR, 0775, true);
    }
    $tmp = PURRNIA_DIR . '/' . $f . '.tmp';
    if (file_put_contents($tmp, $data) === false || !rename($tmp, PURRNIA_DIR . '/' . $f)) {
        exit("sync failed: could not write $f\n");
    }
    echo "SYNCED $f (" . strlen($data) . " bytes)\n";
    exit;
}

echo "deploy-purrnia ready\n";
