<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
$c=parse_ini_file(getenv('PARCELTRACK_CONFIG')?:'C:/xampp/parceltrack-private/config.ini',true,INI_SCANNER_RAW);
$dir=$c['maintenance']['proofs_dir']??'C:/xampp/parceltrack-private/proofs';
$p=db();
$ids=$p->query('SELECT parcel_id FROM parcels WHERE delivered_at <= UTC_TIMESTAMP() - INTERVAL 12 MONTH AND anonymised_at IS NULL')->fetchAll(PDO::FETCH_COLUMN);
foreach($ids as $id){
 $p->beginTransaction();
 try {
  $s=$p->prepare('SELECT parcel_id FROM parcels WHERE parcel_id=? AND delivered_at <= UTC_TIMESTAMP() - INTERVAL 12 MONTH AND anonymised_at IS NULL FOR UPDATE');$s->execute([$id]);
  if(!$s->fetchColumn()){$p->rollBack();continue;}
  $s=$p->prepare('SELECT stored_filename FROM delivery_proofs WHERE parcel_id=?');$s->execute([$id]);
  foreach($s->fetchAll(PDO::FETCH_COLUMN) as $name){
   if(!preg_match('/\A[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/i',$name)) throw new RuntimeException('Invalid proof name');
   $file=$dir.DIRECTORY_SEPARATOR.$name;
   if(is_link($file))throw new RuntimeException('Unexpected proof link');
   if(is_file($file) && !unlink($file))throw new RuntimeException('Cannot remove proof');
  }
  $s=$p->prepare('DELETE FROM delivery_proofs WHERE parcel_id=?');$s->execute([$id]);
  $s=$p->prepare("UPDATE parcels SET recipient_name='Anonymised',recipient_phone='',recipient_address='Anonymised',anonymised_at=UTC_TIMESTAMP() WHERE parcel_id=?");$s->execute([$id]);
  audit('DATA_ANONYMISED',null,(int)$id);$p->commit();echo "Anonymised parcel $id\n";
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}
