<?php
// seed_admin.php - ejecutar una sola vez
require_once __DIR__ . '/../app/config/db.php';

$tenantId = 1;         // cambia si tu tenant es otro
$rolId    = 1;         // cambia si tu rol ADMIN es otro
$nombre   = 'Administrador';
$email    = 'admin@demo.com';
$pass     = 'Admin123*';

$hash = password_hash($pass, PASSWORD_BCRYPT);

$pdo = db();

// validar si ya existe
$chk = $pdo->prepare("SELECT id FROM usuarios WHERE tenant_id=? AND email=? LIMIT 1");
$chk->execute([$tenantId, $email]);
if ($chk->fetch()) {
  echo "Ya existe el usuario $email en tenant $tenantId. No se hizo nada.";
  exit;
}

$st = $pdo->prepare("
  INSERT INTO usuarios (tenant_id, rol_id, nombre, email, pass_hash, estado)
  VALUES (?, ?, ?, ?, ?, 'ACTIVO')
");
$st->execute([$tenantId, $rolId, $nombre, $email, $hash]);

echo "OK. Usuario creado: $email / $pass (tenant_id=$tenantId rol_id=$rolId)";
