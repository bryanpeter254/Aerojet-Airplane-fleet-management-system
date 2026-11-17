
<?php
require 'config.php';
$a=$_GET['action']??''; $in=json_decode(file_get_contents('php://input'),true)??[];
$T=[ // table => editable columns (whitelist)
 'voyages'=>['trip_date','trip_no','flight_no','flight_type','ac_reg','ac_type','origin','destination','pax','cargo_kg','seats','payload_kg','distance_km','hours_flown','fuel_start','fuel_used'],
 'crew'=>['name','crew_code','position','status','pause_reason','first_name','last_name','ac_type','nationality','sex','passport_no','passport_expiry','dob','show_roster','designation','email','card_no','card_expiry','photo','docs_json'],
 'voyage_crew'=>['voyage_id','crew_id','duty','source'],
 'crew_log'=>['crew_id','voyage_id','log_date','flight_hours','duty_hours'],
 'crew_roster'=>['crew_id','flight_no','origin','destination','roster_date','status'],
 'postholders'=>['title','name','company','crm','dgr','avsec','sms','seq_no','status','photo','first_name','last_name','nationality','sex','passport_no','passport_expiry','dob','show_roster','designation','email','card_no','card_expiry'],
 'aircraft_docs'=>['aircraft','cor','coa','coi','rl','nc','wb','asl','aoc','aoc_ops','spp','foc','crs'], // upload_file is set only by the upload action below
 'flight_types'=>['code','name'],
 'trip_reports'=>['trip_no','report_date','ac_type','ac_reg','remark','crew_json','sectors_json'],
 'crew_duty'=>['crew_id','duty_date','category','start_time','end_time','total_hours'],
 'crew_docnames'=>['code','name','doc_type','sort_order'],
 // upload_file is set only by the upload action below
 'dispatchers'=>['name','code','duty_type','shift_date','status','title','licence','li_expiry','rt_expiry','crm','dgr','avsec','famlz','sms','compet','foo_it','foo_rt','foo_f50_it','foo_f50_rt','ojt'],
 'dispatcher_duty'=>['dispatcher_id','duty_date','category','start_time','end_time','total_hours'],
 'dispatcher_roster'=>['dispatcher_id','reg','station','roster_date','status'],
 // password is handled separately below (hashed, never returned)
 'users'=>['username','name','email','role','admin_level','address1','address2','address3','city','country','contact_person','mobile','office_tel']];
$USER_LIST='id,username,name,email,role,admin_level,address1,address2,address3,city,country,contact_person,mobile,office_tel';
$lvl=fn($v,$lim)=>$v>=$lim?'danger':($v>=$lim*WARN_RATIO?'warn':'ok');
 
switch($a){
 case 'login': // accepts User Name or email
  $id=trim($in['email']??($in['username']??''));
  $u=q('SELECT * FROM users WHERE username=? OR email=? LIMIT 1',[$id,$id])->fetch();
  if(!$u||!password_verify($in['password']??'',$u['password_hash']))out(['error'=>'Wrong user name or password'],401);
  session_regenerate_id(true); $_SESSION['user']=['id'=>(int)$u['id'],'name'=>$u['name'],'role'=>$u['role']]; out($_SESSION['user']);
 case 'logout': session_destroy(); out(['ok'=>true]);
 case 'me': out(need());
 case 'password':
  $u=need(); $r=q('SELECT password_hash FROM users WHERE id=?',[$u['id']])->fetch();
  if(!password_verify($in['current']??'',$r['password_hash']))out(['error'=>'Current password is wrong'],403);
  if(strlen($in['new']??'')<8)out(['error'=>'New password must be 8+ characters'],422);
  q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($in['new'],PASSWORD_DEFAULT),$u['id']]); out(['ok'=>true]);
 
 case 'crew_alerts': // rolling 28-day flight time, duty time and days worked per crew member
  need();
  $rows=q("SELECT c.id,c.name,c.position,COALESCE(SUM(l.flight_hours),0) fh,COALESCE(SUM(l.duty_hours),0) dh,COUNT(DISTINCT l.log_date) days
           FROM crew c LEFT JOIN crew_log l ON l.crew_id=c.id AND l.log_date>DATE_SUB(CURDATE(),INTERVAL 28 DAY) AND l.log_date<=CURDATE()
           GROUP BY c.id ORDER BY fh DESC")->fetchAll();
  foreach($rows as &$r){$r['fh']=(float)$r['fh'];$r['dh']=(float)$r['dh'];$r['days']=(int)$r['days'];
   $r['fh_s']=$lvl($r['fh'],LIMIT_FLIGHT);$r['dh_s']=$lvl($r['dh'],LIMIT_DUTY);$r['days_s']=$lvl($r['days'],LIMIT_DAYS);}
  out(['limits'=>['flight'=>LIMIT_FLIGHT,'duty'=>LIMIT_DUTY,'days'=>LIMIT_DAYS],'crew'=>$rows]);
 
 case 'upload': // PDF/JPG/PNG attached to a dispatcher or aircraft document record
  need(); $t=$_GET['table']??''; $id=(int)($_GET['id']??0);
  $UPD=['dispatchers'=>'dispatchers','aircraft_docs'=>'aircraft']; // table => uploads sub-folder
  if(!isset($UPD[$t])||!$id)out(['error'=>'Bad upload request'],422);
  $f=$_FILES['file']??null;
  if(!$f||$f['error']!==UPLOAD_ERR_OK)out(['error'=>'No file received (it may be larger than the server upload limit)'],422);
  if($f['size']>5*1024*1024)out(['error'=>'File must be under 5 MB'],422);
  $okTypes=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
  if(!isset($okTypes[$mime]))out(['error'=>'Only PDF, JPG or PNG files are allowed'],422);
  $sub=$UPD[$t]; $dir=__DIR__."/uploads/$sub";
  if(!is_dir($dir)&&!mkdir($dir,0755,true))out(['error'=>'Could not create the uploads folder'],500);
  $name=$id.'_'.bin2hex(random_bytes(6)).'.'.$okTypes[$mime];
  if(!move_uploaded_file($f['tmp_name'],"$dir/$name"))out(['error'=>'Could not save the file'],500);
  $old=q("SELECT upload_file FROM $t WHERE id=?",[$id])->fetch();
  q("UPDATE $t SET upload_file=? WHERE id=?",["uploads/$sub/$name",$id]);
  if(!empty($old['upload_file']))@unlink("$dir/".basename($old['upload_file']));
  out(['ok'=>true,'file'=>"uploads/$sub/$name"]);
 
 case 'list': case 'create': case 'update': case 'delete':
  $me=need(); $t=$_GET['table']??''; if(!isset($T[$t]))out(['error'=>'Unknown table'],404); $cols=$T[$t];
  $d=[]; foreach($cols as $c) if(array_key_exists($c,$in)) $d[$c]=trim((string)$in[$c])===''?null:trim((string)$in[$c]);
 
  if($t==='users'){ // only admins may manage who can use the system
   if($me['role']!=='admin')out(['error'=>'Admin access required'],403);
   if($a==='delete'&&(int)($_GET['id']??0)===$me['id'])out(['error'=>'You cannot delete your own account'],422);
   if($a==='create'||$a==='update'){
    if(isset($d['role'])&&!in_array($d['role'],['admin','staff','viewer'],true))out(['error'=>'Invalid right'],422);
    if($a==='update'&&(int)($_GET['id']??0)===$me['id']&&isset($d['role'])&&$d['role']!=='admin')out(['error'=>'You cannot remove your own admin right'],422);
    if($a==='create'&&empty($d['username']))out(['error'=>'User Name is required'],422);
    $pw=(string)($in['password']??'');
    if($pw!==''){ if(strlen($pw)<8)out(['error'=>'Password must be 8+ characters'],422); $d['password_hash']=password_hash($pw,PASSWORD_DEFAULT); }
    elseif($a==='create')out(['error'=>'Password is required'],422);
   }
  }
 
  try{ switch($a){
   case 'list': $w=[];$p=[];
    foreach($cols as $c) if(isset($_GET[$c])&&$_GET[$c]!==''){$w[]="$c=?";$p[]=$_GET[$c];}
    if(!empty($_GET['from'])&&!empty($_GET['date_col'])&&in_array($_GET['date_col'],$cols)){$w[]=$_GET['date_col'].'>=?';$p[]=$_GET['from'];}
    if(!empty($_GET['to'])&&!empty($_GET['date_col'])&&in_array($_GET['date_col'],$cols)){$w[]=$_GET['date_col'].'<=?';$p[]=$_GET['to'];}
    $sel=$t==='users'?$USER_LIST:'*';
    out(q("SELECT $sel FROM $t".($w?' WHERE '.implode(' AND ',$w):'').' ORDER BY id DESC LIMIT 500',$p)->fetchAll());
   case 'create': if(!$d)out(['error'=>'No data'],422);
    q("INSERT INTO $t(".implode(',',array_keys($d)).') VALUES('.implode(',',array_fill(0,count($d),'?')).')',array_values($d));
    out(['id'=>(int)$GLOBALS['pdo']->lastInsertId()],201);
   case 'update': if(!$d)out(['error'=>'No data'],422);
    q("UPDATE $t SET ".implode(',',array_map(fn($c)=>"$c=?",array_keys($d))).' WHERE id=?',[...array_values($d),(int)($_GET['id']??0)]); out(['ok'=>true]);
   case 'delete': q("DELETE FROM $t WHERE id=?",[(int)($_GET['id']??0)]); out(['ok'=>true]);
  }}catch(PDOException $e){
   if(($e->errorInfo[1]??0)==1062)out(['error'=>$t==='users'?'That User Name or Email is already in use':($t==='dispatcher_roster'?'That dispatcher already has a roster entry for that date':'That record already exists')],422);
   out(['error'=>'Could not save: check required fields and values'],422);
  }
 default: out(['error'=>'Unknown action'],404);
}