<?php
$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

function db() {
  static $pdo = null;
  if ($pdo !== null) return $pdo;

  $c = (require __DIR__ . '/config.php')['db'];
  $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
  $opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];
  $pdo = new PDO($dsn, $c['user'], $c['pass'], $opt);
  return $pdo;
}
