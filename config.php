<?php
const DB_HOST='localhost',DB_NAME='aerojet',DB_USER='root',DB_PASS='';
// Crew limits over a rolling 28-day window (confirm against your operations manual / regulator)
const LIMIT_FLIGHT=105, LIMIT_DUTY=160, LIMIT_DAYS=28, WARN_RATIO=0.9; // warn at 90% of a limit
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); session_start();
header('Content-Type: application/json');
try{$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
catch(PDOException $e){http_response_code(500);exit(json_encode(['error'=>'Database connection failed']));}
function out($d,$c=200){http_response_code($c);echo json_encode($d);exit;}
function q($s,$p=[]){global $pdo;$st=$pdo->prepare($s);$st->execute($p);return $st;}
function need(){if(empty($_SESSION['user']))out(['error'=>'Please sign in'],401);return $_SESSION['user'];}
