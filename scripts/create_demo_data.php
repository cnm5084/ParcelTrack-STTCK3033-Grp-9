<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
// Run with a CLI-only setup account that can INSERT users. No known passwords.
$p=db();$p->beginTransaction();
try {
 $suffix=bin2hex(random_bytes(5));
 $s=$p->prepare("INSERT INTO users(full_name,email,password_hash,role,is_active) VALUES(?,?,?,'seller',0)");
 $s->execute(['Fictional Demo Seller','demo-'.$suffix.'@example.invalid',password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
 $uid=(int)$p->lastInsertId();$t='KC'.strtoupper(bin2hex(random_bytes(6)));
 $s=$p->prepare("INSERT INTO parcels(tracking_no,seller_id,recipient_name,recipient_phone,recipient_address,weight_kg) VALUES(?,?,'Fictional Recipient','0123456789','Fictional test address',1.25)");$s->execute([$t,$uid]);$pid=(int)$p->lastInsertId();
 $s=$p->prepare("INSERT INTO parcel_status_history(parcel_id,status,changed_by) VALUES(?,'Registered',?)");$s->execute([$pid,$uid]);
 audit('PARCEL_REGISTERED',$uid,$pid);$p->commit();echo "Demo tracking number: $t\nDisabled seller ID: $uid\n";
} catch(Throwable $e) {if($p->inTransaction())$p->rollBack();throw $e;}
