<?php
require __DIR__.'/../src/bootstrap.php'; $u=seller();
$q=field($_GET,'q');if(mb_strlen($q)>100) stop(400,'Search is too long.');
$page=filter_var($_GET['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]) ?: 1;
$offset=($page-1)*20;
// LOCATE treats % and _ literally. Owner filter applies to every search.
$s=db()->prepare('SELECT parcel_id,tracking_no,recipient_name,current_status,created_at FROM parcels WHERE seller_id=? AND (LOCATE(?,tracking_no)>0 OR LOCATE(?,recipient_name)>0) ORDER BY created_at DESC,parcel_id DESC LIMIT 21 OFFSET '.$offset);
$s->execute([$u['user_id'],$q,$q]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);$more=count($rows)>20;$rows=array_slice($rows,0,20);
top('My parcels'); ?>
<form method="get"><label>Search tracking number or recipient name<input name="q" maxlength="100" value="<?=esc($q)?>"></label><button>Search</button></form>
<div class="scroll"><table><tr><th>Tracking number</th><th>Recipient</th><th>Status</th></tr>
<?php foreach($rows as $r): ?><tr><td><a href="parcel_details.php?id=<?=(int)$r['parcel_id']?>"><?=esc($r['tracking_no'])?></a></td><td><?=esc($r['recipient_name'])?></td><td><?=esc($r['current_status'])?></td></tr><?php endforeach; ?>
</table></div>
<?php if (!$rows) echo '<p>No parcels found.</p>';
if ($page>1) echo '<a href="?'.esc(http_build_query(['q'=>$q,'page'=>$page-1])).'">Previous</a> ';
if ($more) echo '<a href="?'.esc(http_build_query(['q'=>$q,'page'=>$page+1])).'">Next</a>';
bottom();
