<?php
// app/views/auth/login.php
// Importante: NO incluir db.php/Auth.php/Helpers.php aquí.
// Ya vienen cargados desde public/index.php

if (Auth::check()) redirect('index.php?route=dashboard');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');

  list($ok, $msg) = Auth::attempt($email, $pass);
  if ($ok) redirect('index.php?route=dashboard');
  $error = $msg ?: 'No se pudo iniciar sesión.';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login · GeoActivos</title>

  <!-- AdminLTE 3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition login-page">

<div class="login-box">

  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <a href="#" class="h1"><b>Geo</b>Activos</a>
      <div class="text-muted text-sm">Gestión de Activos</div>
    </div>

    <div class="card-body">
      <p class="login-box-msg">Inicia sesión</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger text-sm">
          <i class="fas fa-exclamation-triangle mr-1"></i> <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <div class="input-group mb-3">
          <input type="email" name="email" class="form-control" placeholder="Email" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-envelope"></span></div>
          </div>
        </div>

        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
          <i class="fas fa-sign-in-alt mr-1"></i> Entrar
        </button>
      </form>

      <hr>

      <p class="mb-0 text-sm text-muted">
        Demo: <b>admin@demo.com</b> / <b>Admin123*</b>
      </p>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
