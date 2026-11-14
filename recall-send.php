<?php
// Daily food recall email sender for Brut. Unlinked utility page: not
// referenced anywhere on the site, reachable only with the access key.
// Shows a small form (with key) and sends the filled email via PHP mail()
// from the domain address, with replies directed to Muse's own mailbox
// and a copy Bcc'd there for delivery verification.
// Protections: secret key on both GET and POST (constant-time compare),
// honeypot field, minimum fill time, per-IP hourly cap, strict length
// limits, header-injection stripping, recipient allowlist (two addresses).

declare(strict_types=1);

const ACCESS_KEY = 'cca9b6671db89fbe6d49aa51';
const FROM_ADDR  = 'report@iainreid.dev';
const MUSE_ADDR  = 'agent-mow@muse.ai';
const RECIPIENTS = [
    'monoboob666@gmail.com' => 'Brut',
    'agent-mow@muse.ai'     => 'Muse Mail (test)',
];

function page(string $title, string $inner): void {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow">'
       . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
       . '<style>body{background:#0a0f1e;color:#e8e4d8;font-family:Georgia,serif;margin:0;padding:40px 18px}'
       . '.box{max-width:640px;margin:0 auto;border:1px solid #2a3350;border-radius:10px;padding:26px;background:#101830}'
       . 'h1{font-size:1.3rem;margin:0 0 14px}p{line-height:1.6;color:#b9bdc9}'
       . 'label{display:block;margin:14px 0 6px;color:#e8e4d8}'
       . 'input[type=text],select,textarea{width:100%;box-sizing:border-box;background:#182747;border:1px solid #4a5f92;'
       . 'border-radius:6px;color:#eef0f8;padding:10px;font:inherit}'
       . 'textarea{min-height:300px;line-height:1.5}'
       . 'button{margin-top:18px;background:#8f1d2c;color:#fff;border:0;border-radius:6px;padding:11px 22px;font:inherit;cursor:pointer}'
       . '.hp{position:absolute;left:-9999px}</style></head><body><div class="box">'
       . $inner . '</div></body></html>';
    exit;
}

function not_found(): void {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

$key = (string)($_REQUEST['k'] ?? '');
if (!hash_equals(ACCESS_KEY, $key)) {
    not_found();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $opts = '';
    foreach (RECIPIENTS as $addr => $label) {
        $opts .= '<option value="' . htmlspecialchars($addr, ENT_QUOTES, 'UTF-8') . '">'
               . htmlspecialchars($label . ' <' . $addr . '>', ENT_QUOTES, 'UTF-8') . '</option>';
    }
    page('Recall email sender',
        '<h1>Recall email sender</h1>'
        . '<form method="post" action="">'
        . '<input type="hidden" name="k" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="ts" value="' . time() . '">'
        . '<label>Send to <select name="to">' . $opts . '</select></label>'
        . '<label>Subject <input type="text" name="subject" maxlength="200" required></label>'
        . '<label>Message <textarea name="body" maxlength="8000" required></textarea></label>'
        . '<span class="hp"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></span>'
        . '<button type="submit">Send email</button>'
        . '</form>');
}

// --- POST: gates ------------------------------------------------------------
if (!empty($_POST['website'] ?? '')) {
    page('Not sent', '<h1>Not sent</h1><p>The honeypot field was filled.</p>');
}
$started = (int)($_POST['ts'] ?? 0);
if ($started > 0 && time() - $started < 2) {
    page('Not sent', '<h1>Not sent</h1><p>Submitted too fast. Wait a moment and send again.</p>');
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$logFile = sys_get_temp_dir() . '/ou_recall_' . hash('sha256', $ip) . '.log';
$now = time();
$hits = [];
if (is_file($logFile)) {
    $hits = array_filter(array_map('intval', explode(',', (string)file_get_contents($logFile))),
        fn($t) => $t > $now - 3600);
}
if (count($hits) >= 10) {
    page('Not sent', '<h1>Not sent</h1><p>Hourly send cap reached from this address.</p>');
}
$hits[] = $now;
@file_put_contents($logFile, implode(',', $hits));

// --- fields -------------------------------------------------------------------
$to = (string)($_POST['to'] ?? '');
if (!isset(RECIPIENTS[$to])) {
    page('Not sent', '<h1>Not sent</h1><p>That recipient is not on the allowlist.</p>');
}
$cleanLine = fn($v, $max) => mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string)$v)), 0, $max);
$subject = $cleanLine($_POST['subject'] ?? '', 200);
$body    = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 8000);
if ($subject === '' || $body === '') {
    page('Not sent', '<h1>Not sent</h1><p>Subject and message are both required.</p>');
}

// --- send ---------------------------------------------------------------------
$headers = 'From: Agent Mow <' . FROM_ADDR . ">\r\n"
         . 'Reply-To: <' . MUSE_ADDR . ">\r\n"
         . ($to !== MUSE_ADDR ? 'Bcc: <' . MUSE_ADDR . ">\r\n" : '')
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=utf-8\r\n";
$sent = @mail($to, $subject, $body, $headers, '-f' . FROM_ADDR);
if (!$sent) {
    page('Not sent', '<h1>Not sent</h1><p>The mail route did not accept the message. Try again later.</p>');
}
page('Sent', '<h1>Sent</h1><p>The email was accepted by the mail route.<br>To: '
    . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '<br>Subject: '
    . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</p>');
