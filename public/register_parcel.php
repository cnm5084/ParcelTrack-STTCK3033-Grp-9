<?php
require __DIR__.'/../src/bootstrap.php';
$u=seller(); $errors=[]; $v=['name'=>'','phone'=>'','address'=>'','weight'=>''];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 check_csrf(); foreach ($v as $k=>$_) $v[$k]=field($_POST,$k);
 foreach (['name'=>100,'address'=>255] as $k=>$max) {
  if ($v[$k]==='' || !mb_check_encoding($v[$k],'UTF-8') || mb_strlen($v[$k],'UTF-8')>$max || preg_match('/\p{C}/u',str_replace(["\r","\n","\t"],'',$v[$k]))) $errors[]="Enter a valid $k (maximum $max characters).";
 }
 $phone=preg_replace('/[\s()-]/','',$v['phone']);
 // Malaysian domestic 9-11 digit number or +60 followed by 8-10 digits.
 if (!preg_match('/\A(?:0[1-9][0-9]{7,9}|\+60[1-9][0-9]{7,9})\z/',$phone)) $errors[]='Enter a Malaysian phone number, such as 0123456789 or +60123456789.';
 if (!preg_match('/\A[0-9]{1,3}(?:\.[0-9]{1,2})?\z/',$v['weight']) || (float)$v['weight']<=0 || (float)$v['weight']>999.99) $errors[]='Weight must be 0.01 to 999.99 kg with up to two decimal places.';
 if (!$errors) {
  $pdo=db();$pid=null;
  for ($attempt=0;$attempt<5;$attempt++) {
   $token='KC'.strtoupper(bin2hex(random_bytes(6)));
   try {
    $pdo->beginTransaction();
    $s=$pdo->prepare('INSERT INTO parcels(tracking_no,seller_id,recipient_name,recipient_phone,recipient_address,weight_kg) VALUES(?,?,?,?,?,?)');
    $s->execute([$token,$u['user_id'],$v['name'],$phone,$v['address'],$v['weight']]);
    $pid=(int)$pdo->lastInsertId();
    $s=$pdo->prepare("INSERT INTO parcel_status_history(parcel_id,status,changed_by) VALUES(?,'Registered',?)");$s->execute([$pid,$u['user_id']]);
    audit('PARCEL_REGISTERED',(int)$u['user_id'],$pid);$pdo->commit();break;
   } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof PDOException && ($e->errorInfo[1]??0)===1062 && $attempt<4) continue;
    throw $e;
   }
  }
  header('Location: parcel_details.php?id='.$pid, true,303);exit;
 }
}
top('Register parcel');
foreach ($errors as $e) echo '<p class="error">'.esc($e).'</p>';
?>
<form method="post">
<input type="hidden" name="csrf" value="<?=esc(csrf())?>">
<label>Recipient name<input name="name" maxlength="100" required value="<?=esc($v['name'])?>"></label>
<label>Recipient phone<input name="phone" maxlength="20" required value="<?=esc($v['phone'])?>"></label>
<label>Recipient address<textarea name="address" maxlength="255" required><?=esc($v['address'])?></textarea></label>
<label>Weight in kg<input name="weight" type="number" min="0.01" max="999.99" step="0.01" required value="<?=esc($v['weight'])?>"></label>
<button>Register parcel</button></form>
<?php bottom(); ?>
