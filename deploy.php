<?php
// Key-gated deploy trigger for iainreid.dev (Origins Unknown + Seeds).
// POST with the deploy key runs: git fetch + reset of the server-side
// origins-unknown clone, then the same file copies as .cpanel.yml.
// Anything else answers like a missing page.
$stored_hash = 'fa88d93e991c5e20d9d785b74d56a160d9c82bf77094c9e77492016199e01c08';
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
$repo = '/home/iainmcok/repositories/origins-unknown';
$cmds = [
    "cd $repo && git fetch origin 2>&1",
    "cd $repo && git reset --hard origin/main 2>&1",
    "cd $repo && git log --oneline -1 2>&1",
    "mkdir -p /home/iainmcok/public_html/origins-unknown && cp $repo/index.html $repo/favicon.svg $repo/suggest.php $repo/recall-send.php $repo/deploy.php $repo/deploy-song.php /home/iainmcok/public_html/origins-unknown/ 2>&1",
    "mkdir -p /home/iainmcok/public_html/seeds && cp $repo/seeds/index.html $repo/seeds/world.html $repo/seeds/luminai.html $repo/seeds/people.html $repo/seeds/faction.html $repo/seeds/timeline.html $repo/seeds/visuals.html $repo/seeds/archive.html /home/iainmcok/public_html/seeds/ 2>&1",
    "chmod 644 /home/iainmcok/public_html/seeds/*.html /home/iainmcok/public_html/origins-unknown/index.html 2>&1",
];
foreach ($cmds as $c) {
    echo '$ ' . $c . "\n";
    echo shell_exec($c) . "\n";
}
echo "DEPLOY DONE\n";
