<?php
require __DIR__.'/../src/bootstrap.php';$u=seller();
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) stop(400,'Invalid parcel reference.');
$s=db()->prepare('SELECT * FROM parcels WHERE parcel_id=? AND seller_id=?');$s->execute([$id,$u['user_id']]);$p=$s->fetch(PDO::FETCH_ASSOC);
if (!$p) { audit('ACCESS_DENIED',(int)$u['user_id']);stop(404,'Parcel unavailable.'); }
$s=db()->prepare('SELECT status,changed_at FROM parcel_status_history WHERE parcel_id=? ORDER BY changed_at,history_id');$s->execute([$id]);$history=$s->fetchAll(PDO::FETCH_ASSOC);
top('Parcel details'); echo '<dl>';
foreach (['tracking_no'=>'Tracking number','recipient_name'=>'Recipient','recipient_phone'=>'Phone','recipient_address'=>'Address','weight_kg'=>'Weight in kg','current_status'=>'Current status'] as $k=>$label) echo '<dt>'.esc($label).'</dt><dd>'.nl2br(esc($p[$k])).'</dd>';
echo '</dl><h2>Delivery history</h2><ol>';
foreach($history as $r) echo '<li>'.esc($r['status']).' — '.esc(local_time($r['changed_at'])).'</li>';
echo '</ol><p><small>Times shown in Malaysia time.</small></p>';bottom();
