<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

// Si venimos desde "Agregar componente" en detalle
$activoPadreIdGet = (int)($_GET['activo_padre_id'] ?? 0);

// Tipos de activo
$tipoSt = db()->prepare("SELECT id, nombre FROM tipos_activo WHERE tenant_id=:t ORDER BY nombre ASC");
$tipoSt->execute([':t'=>$tenantId]);
$tipos = $tipoSt->fetchAll();

// Categorías
$catSt = db()->prepare("SELECT id, nombre FROM categorias_activo WHERE tenant_id=:t ORDER BY nombre ASC");
$catSt->execute([':t'=>$tenantId]);
$categorias = $catSt->fetchAll();

// Marcas
$marcaSt = db()->prepare("SELECT id, nombre FROM marcas WHERE tenant_id=:t ORDER BY nombre ASC");
$marcaSt->execute([':t'=>$tenantId]);
$marcas = $marcaSt->fetchAll();

// Proveedores
$provSt = db()->prepare("SELECT id, nombre FROM proveedores WHERE tenant_id=:t ORDER BY nombre ASC");
$provSt->execute([':t'=>$tenantId]);
$proveedores = $provSt->fetchAll();

// Áreas con sede
$areaSt = db()->prepare("
  SELECT a.id, a.nombre AS area, s.nombre AS sede
  FROM areas a
  LEFT JOIN sedes s ON s.id = a.sede_id AND s.tenant_id = a.tenant_id
  WHERE a.tenant_id = :t
  ORDER BY s.nombre ASC, a.nombre ASC
");
$areaSt->execute([':t'=>$tenantId]);
$areas = $areaSt->fetchAll();

/* =========================
   Helpers locales
========================= */
function normalize_mac($mac){
  $mac = trim((string)$mac);
  if ($mac === '') return '';
  $mac = strtoupper($mac);
  $mac = str_replace(['-', '.', ' '], [':', '', ''], $mac);
  $mac = preg_replace('/[^A-F0-9:]/', '', $mac);

  // si viene sin separadores (12 hex)
  $hexOnly = preg_replace('/[^A-F0-9]/', '', $mac);
  if (strlen($hexOnly) === 12) {
    return implode(':', str_split($hexOnly, 2));
  }

  // si ya viene con :
  return $mac;
}

function is_valid_mac($mac){
  if ($mac === '') return true;
  return (bool)preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac);
}

function is_valid_ipv4($ip){
  if ($ip === '') return true;
  return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

$data = [
  'categoria_id' => 0,
  'tipo_activo_id' => null,
  'activo_padre_id' => ($activoPadreIdGet > 0 ? $activoPadreIdGet : null),

  'marca_id' => null,
  'area_id' => null,
  'proveedor_id' => null,

  'codigo_interno' => '',
  'nombre' => '',

  'hostname' => '',
  'usa_dhcp' => 1,
  'ip_fija' => '',
  'mac' => '',

  'modelo' => '',
  'serial' => '',
  'placa' => '',

  'fecha_compra' => '',
  'fecha_instalacion' => '',
  'garantia_hasta' => '',

  'estado' => 'ACTIVO',
  'observaciones' => '',
];

if ($id > 0) {
  $st = db()->prepare("
    SELECT categoria_id, tipo_activo_id, activo_padre_id, marca_id, area_id, proveedor_id,
           codigo_interno, nombre, hostname, usa_dhcp, ip_fija, mac,
           modelo, serial, placa,
           fecha_compra, fecha_instalacion, garantia_hasta,
           estado, observaciones
    FROM activos
    WHERE id = :id AND tenant_id = :t
    LIMIT 1
  ");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "No encontrado"; exit; }

  $data = $row;

  foreach (['fecha_compra','fecha_instalacion','garantia_hasta'] as $f) {
    if (!empty($data[$f])) $data[$f] = substr((string)$data[$f], 0, 10);
  }
  $data['usa_dhcp'] = (int)$data['usa_dhcp'];
}

// Si es nuevo y viene activo_padre_id por GET, lo fijamos
if ($id === 0 && $activoPadreIdGet > 0) {
  $data['activo_padre_id'] = $activoPadreIdGet;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $categoriaId = (int)($_POST['categoria_id'] ?? 0);
  $tipoId      = (int)($_POST['tipo_activo_id'] ?? 0);

  $marcaId     = (int)($_POST['marca_id'] ?? 0);
  $areaId      = (int)($_POST['area_id'] ?? 0);
  $provId      = (int)($_POST['proveedor_id'] ?? 0);

  $codigo = trim($_POST['codigo_interno'] ?? '');
  $nombre = trim($_POST['nombre'] ?? '');

  // Padre (composición)
  $activoPadreId = (int)($_POST['activo_padre_id'] ?? 0);
  $activoPadreId = ($activoPadreId > 0 ? $activoPadreId : null);

  // Red
  $hostname = trim($_POST['hostname'] ?? '');
  $usaDhcp  = isset($_POST['usa_dhcp']) ? 1 : 0;
  $ipFija   = trim($_POST['ip_fija'] ?? '');
  $mac      = normalize_mac($_POST['mac'] ?? '');

  $modelo = trim($_POST['modelo'] ?? '');
  $serial = trim($_POST['serial'] ?? '');
  $placa  = trim($_POST['placa'] ?? '');

  $fechaCompra = trim($_POST['fecha_compra'] ?? '');
  $fechaInst   = trim($_POST['fecha_instalacion'] ?? '');
  $garantia    = trim($_POST['garantia_hasta'] ?? '');

  $estado = $_POST['estado'] ?? 'ACTIVO';
  $obs = trim($_POST['observaciones'] ?? '');

  // Normalizar código interno: mayúsculas y sin espacios extremos
  $codigo = strtoupper(trim($codigo));

  if ($categoriaId <= 0) {
    $error = 'Debes seleccionar una categoría.';
  } elseif ($codigo === '' || $nombre === '') {
    $error = 'Código interno y nombre son obligatorios.';
  } else {

    // Validar MAC
    if ($error === '' && !is_valid_mac($mac)) {
      $error = 'La MAC no tiene un formato válido. Ej: 00:11:22:33:44:55';
    }

    // Red: si no usa DHCP, debe tener IP y válida
    if ($error === '' && $usaDhcp === 0) {
      if ($ipFija === '') {
        $error = 'Si no usa DHCP, debes indicar la IP fija.';
      } elseif (!is_valid_ipv4($ipFija)) {
        $error = 'La IP fija no es válida. Ej: 192.168.1.50';
      }
    }

    // Si usa DHCP, limpiamos IP
    if ($error === '' && $usaDhcp === 1) {
      $ipFija = '';
    }

    // Validar tenant de FK: categoría
    if ($error === '') {
      $chk = db()->prepare("SELECT id FROM categorias_activo WHERE id=:id AND tenant_id=:t LIMIT 1");
      $chk->execute([':id'=>$categoriaId, ':t'=>$tenantId]);
      if (!$chk->fetch()) $error = 'Categoría inválida.';
    }

    // Tipo de activo
    if ($error === '' && $tipoId > 0) {
      $chk = db()->prepare("SELECT id FROM tipos_activo WHERE id=:id AND tenant_id=:t LIMIT 1");
      $chk->execute([':id'=>$tipoId, ':t'=>$tenantId]);
      if (!$chk->fetch()) $error = 'Tipo de activo inválido.';
    }

    // Marca
    if ($error === '' && $marcaId > 0) {
      $chk = db()->prepare("SELECT id FROM marcas WHERE id=:id AND tenant_id=:t LIMIT 1");
      $chk->execute([':id'=>$marcaId, ':t'=>$tenantId]);
      if (!$chk->fetch()) $error = 'Marca inválida.';
    }

    // Área
    if ($error === '' && $areaId > 0) {
      $chk = db()->prepare("SELECT id FROM areas WHERE id=:id AND tenant_id=:t LIMIT 1");
      $chk->execute([':id'=>$areaId, ':t'=>$tenantId]);
      if (!$chk->fetch()) $error = 'Área inválida.';
    }

    // Proveedor
    if ($error === '' && $provId > 0) {
      $chk = db()->prepare("SELECT id FROM proveedores WHERE id=:id AND tenant_id=:t LIMIT 1");
      $chk->execute([':id'=>$provId, ':t'=>$tenantId]);
      if (!$chk->fetch()) $error = 'Proveedor inválido.';
    }

    // Validación: padre debe existir y ser del mismo tenant (y no puede ser él mismo)
    if ($error === '' && $activoPadreId !== null) {
      if ($id > 0 && (int)$activoPadreId === (int)$id) {
        $error = 'Un activo no puede ser padre de sí mismo.';
      } else {
        $chk = db()->prepare("SELECT id FROM activos WHERE id=:id AND tenant_id=:t LIMIT 1");
        $chk->execute([':id'=>$activoPadreId, ':t'=>$tenantId]);
        if (!$chk->fetch()) $error = 'Activo padre inválido.';
      }
    }

    // Validar código interno único por tenant
    if ($error === '') {
      if ($id > 0) {
        $chk = db()->prepare("
          SELECT id
          FROM activos
          WHERE tenant_id=:t AND codigo_interno=:c AND id<>:id
          LIMIT 1
        ");
        $chk->execute([':t'=>$tenantId, ':c'=>$codigo, ':id'=>$id]);
      } else {
        $chk = db()->prepare("
          SELECT id
          FROM activos
          WHERE tenant_id=:t AND codigo_interno=:c
          LIMIT 1
        ");
        $chk->execute([':t'=>$tenantId, ':c'=>$codigo]);
      }
      if ($chk->fetch()) {
        $error = 'Ya existe un activo con ese Código interno en este cliente. Usa otro (o genera uno nuevo).';
      }
    }

    if ($error === '') {
      $tipoId  = ($tipoId > 0 ? $tipoId : null);
      $marcaId = ($marcaId > 0 ? $marcaId : null);
      $areaId  = ($areaId > 0 ? $areaId : null);
      $provId  = ($provId > 0 ? $provId : null);

      $fechaCompra = ($fechaCompra !== '' ? $fechaCompra : null);
      $fechaInst   = ($fechaInst !== '' ? $fechaInst : null);
      $garantia    = ($garantia !== '' ? $garantia : null);

      $hostname = ($hostname !== '' ? $hostname : null);
      $macDb    = ($mac !== '' ? $mac : null);

      $ipDb = ($usaDhcp === 1) ? null : ($ipFija !== '' ? $ipFija : null);

      try {
        if ($id > 0) {
          $up = db()->prepare("
            UPDATE activos
            SET categoria_id=:cat, tipo_activo_id=:tipo, activo_padre_id=:padre,
                marca_id=:mar, area_id=:ar, proveedor_id=:pr,
                codigo_interno=:c, nombre=:n,
                hostname=:h, usa_dhcp=:dhcp, ip_fija=:ip, mac=:mac,
                modelo=:m, serial=:s, placa=:p,
                fecha_compra=:fc, fecha_instalacion=:fi, garantia_hasta=:gh,
                estado=:e, observaciones=:o
            WHERE id=:id AND tenant_id=:t
          ");
          $up->execute([
            ':cat'=>$categoriaId, ':tipo'=>$tipoId, ':padre'=>$activoPadreId,
            ':mar'=>$marcaId, ':ar'=>$areaId, ':pr'=>$provId,
            ':c'=>$codigo, ':n'=>$nombre,
            ':h'=>$hostname, ':dhcp'=>$usaDhcp, ':ip'=>$ipDb, ':mac'=>$macDb,
            ':m'=>$modelo, ':s'=>$serial, ':p'=>$placa,
            ':fc'=>$fechaCompra, ':fi'=>$fechaInst, ':gh'=>$garantia,
            ':e'=>$estado, ':o'=>$obs,
            ':id'=>$id, ':t'=>$tenantId
          ]);
        } else {
          $ins = db()->prepare("
            INSERT INTO activos
              (tenant_id, categoria_id, tipo_activo_id, activo_padre_id,
               marca_id, area_id, proveedor_id,
               codigo_interno, nombre,
               hostname, usa_dhcp, ip_fija, mac,
               modelo, serial, placa,
               fecha_compra, fecha_instalacion, garantia_hasta,
               estado, observaciones)
            VALUES
              (:t, :cat, :tipo, :padre,
               :mar, :ar, :pr,
               :c, :n,
               :h, :dhcp, :ip, :mac,
               :m, :s, :p,
               :fc, :fi, :gh,
               :e, :o)
          ");
          $ins->execute([
            ':t'=>$tenantId, ':cat'=>$categoriaId, ':tipo'=>$tipoId, ':padre'=>$activoPadreId,
            ':mar'=>$marcaId, ':ar'=>$areaId, ':pr'=>$provId,
            ':c'=>$codigo, ':n'=>$nombre,
            ':h'=>$hostname, ':dhcp'=>$usaDhcp, ':ip'=>$ipDb, ':mac'=>$macDb,
            ':m'=>$modelo, ':s'=>$serial, ':p'=>$placa,
            ':fc'=>$fechaCompra, ':fi'=>$fechaInst, ':gh'=>$garantia,
            ':e'=>$estado, ':o'=>$obs
          ]);
        }
      } catch (Exception $e) {
        // Si existe UNIQUE(tenant_id,codigo_interno) y ocurrió carrera
        $error = 'No se pudo guardar. Es posible que el Código interno ya exista. Intenta generar uno nuevo.';
      }

      if ($error === '') {
        if ($activoPadreId !== null) {
          redirect('index.php?route=activo_detalle&id=' . (int)$activoPadreId);
        } else {
          redirect('index.php?route=activos');
        }
      }
    }
  }

  // Repintar datos si hubo error
  $data = [
    'categoria_id'=>$categoriaId,
    'tipo_activo_id'=>($tipoId > 0 ? $tipoId : null),
    'activo_padre_id'=>$activoPadreId,

    'marca_id'=>($marcaId > 0 ? $marcaId : null),
    'area_id'=>($areaId > 0 ? $areaId : null),
    'proveedor_id'=>($provId > 0 ? $provId : null),

    'codigo_interno'=>$codigo,
    'nombre'=>$nombre,

    'hostname'=>$hostname ?: '',
    'usa_dhcp'=>$usaDhcp,
    'ip_fija'=>$ipFija ?: '',
    'mac'=>$mac ?: '',

    'modelo'=>$modelo,
    'serial'=>$serial,
    'placa'=>$placa,

    'fecha_compra'=>$fechaCompra ?: '',
    'fecha_instalacion'=>$fechaInst ?: '',
    'garantia_hasta'=>$garantia ?: '',

    'estado'=>$estado,
    'observaciones'=>$obs,
  ];
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <?= $id>0 ? 'Editar activo' : 'Nuevo activo' ?>
      <?php if (!empty($data['activo_padre_id'])): ?>
        <small class="text-muted"> (Componente de #<?= (int)$data['activo_padre_id'] ?>)</small>
      <?php endif; ?>
    </h3>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="activo_padre_id" value="<?= (int)($data['activo_padre_id'] ?: 0) ?>">

      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Categoría *</label>
          <select class="form-control" name="categoria_id" id="categoria_id" required>
            <option value="">-- Selecciona --</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)$data['categoria_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                <?= e($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group col-md-4">
          <label>Tipo de activo</label>
          <select class="form-control" name="tipo_activo_id" id="tipo_activo_id">
            <option value="0">— Sin tipo —</option>
            <?php foreach ($tipos as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= ((int)$data['tipo_activo_id'] === (int)$t['id']) ? 'selected' : '' ?>>
                <?= e($t['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Configura tipos en: Configuración → Tipos de activo</small>
        </div>

        <div class="form-group col-md-4">
          <label>Estado</label>
          <select class="form-control" name="estado">
            <?php foreach (['ACTIVO','EN_MANTENIMIENTO','BAJA'] as $op): ?>
              <option value="<?= e($op) ?>" <?= ($data['estado']===$op)?'selected':'' ?>><?= e($op) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Código interno *</label>
          <input class="form-control" name="codigo_interno" id="codigo_interno" value="<?= e($data['codigo_interno']) ?>" required>
          <small id="codigo_hint" class="text-muted" style="display:none;">Se asigna automáticamente según el tipo.</small>
        </div>

        <div class="form-group col-md-8">
          <label>Nombre *</label>
          <input class="form-control" name="nombre" value="<?= e($data['nombre']) ?>" required>
        </div>
      </div>

      <hr>

      <div id="bloque_red">
        <h6 class="text-muted"><i class="fas fa-network-wired"></i> Red / Identificación</h6>

        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Hostname / Nombre en red</label>
            <input class="form-control" name="hostname" id="hostname" value="<?= e($data['hostname']) ?>" placeholder="Ej: PC-OFI-01">
          </div>

          <div class="form-group col-md-2" style="padding-top:32px">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="usa_dhcp" name="usa_dhcp" <?= ((int)$data['usa_dhcp'] === 1) ? 'checked' : '' ?>>
              <label class="custom-control-label" for="usa_dhcp">Usa DHCP</label>
            </div>
          </div>

          <div class="form-group col-md-3">
            <label>IP fija</label>
            <input class="form-control" id="ip_fija" name="ip_fija" value="<?= e($data['ip_fija']) ?>" placeholder="Ej: 192.168.1.50">
            <small class="text-muted">Si usa DHCP, se deja vacío.</small>
          </div>

          <div class="form-group col-md-3">
            <label>MAC</label>
            <input class="form-control" name="mac" id="mac" value="<?= e($data['mac']) ?>" placeholder="Ej: 00:11:22:33:44:55">
            <small class="text-muted">Se normaliza automáticamente (usa :).</small>
          </div>
        </div>
      </div>

      <script src="<?= e(base_url()) ?>/assets/js/activo-dhcp.js"></script>
      <div id="js-activo-codigo" data-is-edit="<?= ($id > 0 ? 'true' : 'false') ?>" data-base-url="<?= e(base_url()) ?>"></div>
      <script src="<?= e(base_url()) ?>/assets/js/activo-codigo.js"></script>

      <hr>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Marca</label>
          <select class="form-control" name="marca_id">
            <option value="0">— Sin marca —</option>
            <?php foreach ($marcas as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= ((int)$data['marca_id'] === (int)$m['id']) ? 'selected' : '' ?>>
                <?= e($m['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group col-md-4">
          <label>Área (Sede - Área)</label>
          <select class="form-control" name="area_id">
            <option value="0">— Sin área —</option>
            <?php foreach ($areas as $a): ?>
              <?php $txt = ($a['sede'] ? $a['sede'] . ' - ' : '') . $a['area']; ?>
              <option value="<?= (int)$a['id'] ?>" <?= ((int)$data['area_id'] === (int)$a['id']) ? 'selected' : '' ?>>
                <?= e($txt) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group col-md-4">
          <label>Proveedor</label>
          <select class="form-control" name="proveedor_id">
            <option value="0">— Sin proveedor —</option>
            <?php foreach ($proveedores as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ((int)$data['proveedor_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                <?= e($p['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Modelo</label>
          <input class="form-control" name="modelo" value="<?= e($data['modelo']) ?>">
        </div>
        <div class="form-group col-md-4">
          <label>Serial</label>
          <input class="form-control" name="serial" value="<?= e($data['serial']) ?>">
        </div>
        <div class="form-group col-md-4">
          <label>Placa</label>
          <input class="form-control" name="placa" value="<?= e($data['placa']) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Fecha compra</label>
          <input type="date" class="form-control" name="fecha_compra" value="<?= e($data['fecha_compra']) ?>">
        </div>
        <div class="form-group col-md-4">
          <label>Fecha instalación</label>
          <input type="date" class="form-control" name="fecha_instalacion" value="<?= e($data['fecha_instalacion']) ?>">
        </div>
        <div class="form-group col-md-4">
          <label>Garantía hasta</label>
          <input type="date" class="form-control" name="garantia_hasta" value="<?= e($data['garantia_hasta']) ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Observaciones</label>
        <textarea class="form-control" name="observaciones" rows="3"><?= e($data['observaciones']) ?></textarea>
      </div>

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>

      <?php if (!empty($data['activo_padre_id'])): ?>
        <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=activo_detalle&id=<?= (int)$data['activo_padre_id'] ?>">
          Volver al padre
        </a>
      <?php else: ?>
        <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=activos">Volver</a>
      <?php endif; ?>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
