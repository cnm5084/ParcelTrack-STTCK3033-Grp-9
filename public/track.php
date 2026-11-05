<?php
require __DIR__.'/../src/bootstrap.php';$token='';$message='';$rows=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!tracking_limit()) {
  audit('TRACKING_RATE_LIMITED');header('Retry-After: 60');stop(429,'Too many tracking requests. Please wait one minute.');
 }
 $token=strtoupper(field($_POST,'tracking_no'));
 if (!preg_match('/\AKC[A-F0-9]{12}\z/',$token)) $message='Enter KC followed by 12 hexadecimal characters.';
 else {
  $s=db()->prepare('SELECT status,changed_at FROM v_public_tracking WHERE tracking_no=? ORDER BY changed_at');$s->execute([$token]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) $message='Tracking information not found.';
 }
}
top('Track a parcel'); ?>
<form method="post"><label>Tracking number<input name="tracking_no" maxlength="14" required value="<?=esc($token)?>"></label><button>Track parcel</button></form>
<?php if($message) echo '<p>'.esc($message).'</p>';
if($rows){echo '<h2>Delivery history</h2><ol>';foreach($rows as $r) echo '<li>'.esc($r['status']).' — '.esc(local_time($r['changed_at'])).'</li>';echo '</ol><small>Malaysia time</small>';}
bottom();
