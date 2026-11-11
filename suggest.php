<?php
// Origins Unknown Report: topic suggestion endpoint.
// Sends each valid suggestion to the Report's editor inbox using PHP mail()
// with a domain-based From (Namecheap/cPanel rule) and a matching envelope
// sender. If delivery ever proves unreliable, upgrade path: create the
// report@iainreid.dev mailbox and switch send_suggestion() to authenticated
// SMTP with its config outside the web root.
// Protections: honeypot field, minimum fill time, per-IP hourly cap,
// strict length limits, header-injection stripping.

declare(strict_types=1);

function respond(bool $ok, string $msg): void {
    $title = $ok ? 'Suggestion received' : 'Something went wrong';
    $back = 'https://iainreid.dev/origins-unknown/#/report';
    http_response_code($ok ? 200 : 400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . $title . ' · The Origins Unknown Report</title>'
       . '<style>body{background:#0a0f1e;color:#e8e4d8;font-family:Georgia,serif;margin:0;padding:48px 20px}'
       . '.box{max-width:560px;margin:0 auto;border:1px solid #2a3350;border-radius:10px;padding:28px;background:#101830}'
       . 'h1{font-size:1.4rem;margin:0 0 12px}p{line-height:1.6;color:#b9bdc9}'
       . 'a{color:#d8b25c}</style></head><body><div class="box"><h1>' . $title . '</h1><p>'
       . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
       . '</p><p><a href="' . $back . '">Back to the Report</a></p></div></body></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'This address only accepts the suggestion form.');
}

// --- anti-spam gates -------------------------------------------------------
if (!empty($_POST['website'] ?? '')) {
    respond(true, 'Thank you. Your suggestion is on file and will be read.'); // honeypot: silently drop
}
$started = (int)($_POST['ts'] ?? 0);
if ($started > 0 && time() - $started < 3) {
    respond(false, 'That was a little too fast for a person. Go back and try once more.');
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$logFile = sys_get_temp_dir() . '/ou_suggest_' . hash('sha256', $ip) . '.log';
$now = time();
$hits = [];
if (is_file($logFile)) {
    $hits = array_filter(array_map('intval', explode(',', (string)file_get_contents($logFile))),
        fn($t) => $t > $now - 3600);
}
if (count($hits) >= 5) {
    respond(false, 'That is plenty of suggestions for one hour. Thank you, and come back later.');
}
$hits[] = $now;
@file_put_contents($logFile, implode(',', $hits));

// --- read and clean the fields --------------------------------------------
$clean = fn($v, $max) => mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string)$v)), 0, $max);
$topic = $clean($_POST['topic'] ?? '', 200);
$why   = mb_substr(trim((string)($_POST['why'] ?? '')), 0, 2000);
$name  = $clean($_POST['name'] ?? '', 80);
$email = $clean($_POST['email'] ?? '', 120);

if ($topic === '') {
    respond(false, 'The suggestion itself was empty. Go back and tell us the topic.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'That email address does not look right. Go back and check it, or leave it blank.');
}

// --- send -------------------------------------------------------------------
$to   = 'agent-mow@muse.ai';
$from = 'report@iainreid.dev';
$subject = 'Report topic suggestion: ' . $topic;
$body = "A reader suggested a topic for the Origins Unknown Report.\n\n"
      . "Topic: {$topic}\n"
      . 'Why it matters: ' . ($why !== '' ? $why : '(not given)') . "\n"
      . 'Name: ' . ($name !== '' ? $name : '(not given)') . "\n"
      . 'Email: ' . ($email !== '' ? $email : '(not given)') . "\n"
      . 'Sent: ' . date('Y-m-d H:i:s T') . " from the Report page suggestion form.\n";
$headers = 'From: Origins Unknown Report <' . $from . ">" . "\r\n"
         . ($email !== '' ? 'Reply-To: <' . $email . ">" . "\r\n" : '')
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=utf-8\r\n";
$sent = @mail($to, $subject, $body, $headers, '-f' . $from);
if (!$sent) {
    respond(false, 'The mail route did not accept the message. Please try again later.');
}
respond(true, 'Thank you. Your suggestion is on file and will be read.'); // honeypot: silently drop
