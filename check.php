<?php
// Diagnostic page. Open http://localhost/Aerojet/check.php then DELETE this file.
header('Content-Type: text/plain');
echo "PHP version: ".PHP_VERSION.(version_compare(PHP_VERSION,'7.4','<')?"  <-- TOO OLD, need 7.4 or newer":"")."\n";
echo "pdo_mysql:   ".(extension_loaded('pdo_mysql')?'OK':'MISSING - enable extension=pdo_mysql in php.ini')."\n";
foreach(['config.php','api.php','setup.php','dashboard.html','js/api.js'] as $f)
  echo str_pad($f.':',16).(file_exists(__DIR__."/$f")?'found':'MISSING')."\n";
$raw=@file_get_contents(__DIR__.'/config.php'); $bom=substr((string)$raw,0,3)==="\xEF\xBB\xBF";
echo "config.php BOM: ".($bom?"YES <-- re-save as UTF-8 without BOM":"no")."\n";
$cfg=[];
foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $k){preg_match("/$k\s*=\s*['\"]([^'\"]*)['\"]/",(string)$raw,$m);$cfg[$k]=$m[1]??'';}
echo "DB host / name / user: {$cfg['DB_HOST']} / {$cfg['DB_NAME']} / {$cfg['DB_USER']}\n";
try{
  $pdo=new PDO("mysql:host={$cfg['DB_HOST']};dbname={$cfg['DB_NAME']};charset=utf8mb4",$cfg['DB_USER'],$cfg['DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  echo "Database connection: OK\n";
  $tabs=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  echo "Tables: ".implode(', ',$tabs)."\n";
  foreach(['users','voyages','crew','crew_log','flight_types'] as $t) if(!in_array($t,$tabs)) echo "MISSING TABLE: $t  (import database.sql)\n";
  if(in_array('users',$tabs)){
    $u=$pdo->query("SELECT password_hash FROM users WHERE email='admin@aerojet.test'")->fetch(PDO::FETCH_ASSOC);
    echo "Admin account: ".($u?(password_verify('ChangeMe123!',$u['password_hash'])?'OK (default password works)':'exists, but password is not the default'):'NOT FOUND - open setup.php once')."\n";
  }
}catch(PDOException $e){ echo "Database connection FAILED: ".$e->getMessage()."\n"; }
echo "\nDelete check.php when you are done.\n";