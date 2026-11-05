<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
// Keep at most one day of attempts; active rate window is only 60 seconds.
db()->exec('DELETE FROM rate_limits WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
echo "Old rate-limit attempts removed.\n";
