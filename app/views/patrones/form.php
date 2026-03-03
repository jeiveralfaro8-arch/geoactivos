<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$user = Auth::user();
$userId = isset($user['id']) ? (int)$user['id'] : null;

$id = (int)($_GET['id'] ?? 0);

/* =========================
   Cargar patrón si existe
========================= */
$patron = [
  'id' => 0,
  'nombre' => '',
  'marca' => '',
  'modelo' => '',
  'serial' => '',
  'magnitudes' => '',
  'rango' => '',
  'resolucion' => '',
  'certificado_numero' => '',
  'certificado_emisor' => '',
  'certificado_fecha' => '',
  'certificado_vigencia_hasta' => '',
  'incertidumbre_ref' => '',
  'estado' => 'ACTIVO',
  'archivo_certificado_path' => '',
  'archivo_certificado_mime' => '',
  'archivo_certificado_updated_en' => ''
];

if ($id > 0) {
  $st = db()->prepare("
    SELECT *
    FROM patrones
    WHERE id=:id AND tenant_id=:t AND eliminado=0
    LIMIT 1
  ");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();

  if (!$row) {
    http_response_code(404);
    echo 'Patrón no encontrado';
    exit;
  }
  $patron = array_merge($patron, $row);
}

/* =========================
   Guardar (POST)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $data = [
    ':nombre' => trim($_POST['nombre'] ?? ''),
    ':marca'  => trim($_POST['marca'] ?? ''),
    ':modelo' => trim($_POST['modelo'] ?? ''),
    ':serial' => trim($_POST['serial'] ?? ''),
    ':magnitudes' => trim($_POST['magnitudes'] ?? ''),
    ':rango' => trim($_POST['rango'] ?? ''),
    ':resolucion' => trim($_POST['resolucion'] ?? ''),
    ':certificado_numero' => trim($_POST['certificado_numero'] ?? ''),
    ':certificado_emisor' => trim($_POST['certificado_emisor'] ?? ''),
    ':certificado_fecha' => ($_POST['certificado_fecha'] ?? null) ?: null,
    ':certificado_vigencia_hasta' => ($_POST['certificado_vigencia_hasta'] ?? null) ?: null,
    ':incertidumbre_ref' => trim($_POST['incertidumbre_ref'] ?? ''),
    ':estado' => (($_POST['estado'] ?? '') === 'INACTIVO') ? 'INACTIVO' : 'ACTIVO',
  ];

  if ($data[':nombre'] === '') {
    $error = 'El nombre del patrón es obligatorio.';
  } else {

    if ($id > 0) {

      $up = db()->prepare("
        UPDATE patrones SET
          nombre=:nombre,
          marca=:marca,
          modelo=:modelo,
          serial=:serial,
          magnitudes=:magnitudes,
          rango=:rango,
          resolucion=:resolucion,
          certificado_numero=:certificado_numero,
          certificado_emisor=:certificado_emisor,
          certificado_fecha=:certificado_fecha,
          certificado_vigencia_hasta=:certificado_vigencia_hasta,
          incertidumbre_ref=:incertidumbre_ref,
          estado=:estado
        WHERE id=:id AND tenant_id=:t
        LIMIT 1
      ");
      $data[':id'] = $id;
      $data[':t']  = $tenantId;
      $up->execute($data);

    } else {

      $ins = db()->prepare("
        INSERT INTO patrones (
          tenant_id, nombre, marca, modelo, serial,
          magnitudes, rango, resolucion,
          certificado_numero, certificado_emisor,
          certificado_fecha, certificado_vigencia_hasta,
          incertidumbre_ref, estado,
          creado_por
        ) VALUES (
          :t, :nombre, :marca, :modelo, :serial,
          :magnitudes, :rango, :resolucion,
          :certificado_numero, :certificado_emisor,
          :certificado_fecha, :certificado_vigencia_hasta,
          :incertidumbre_ref, :estado,
          :u
        )
      ");
      $data[':t'] = $tenantId;
      $data[':u'] = $userId;
      $ins->execute($data);

      $id = (int)db()->lastInsertId();
      header('Location: '.base_url().'/index.php?route=patron_form&id='.$id);
      exit;
    }

    header('Location: '.base_url().'/index.php?route=patrones');
    exit;
  }
}

/* URL certificado actual */
$certPath = trim((string)($patron['archivo_certificado_path'] ?? ''));
$certMime = trim((string)($patron['archivo_certificado_mime'] ?? ''));
$certUrl  = ($certPath !== '') ? (e(base_url()).'/'.ltrim($certPath,'/')) : '';

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card card-outline card-primary">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-balance-scale"></i>
      <?= $id > 0 ? 'Editar patrón' : 'Nuevo patrón' ?>
    </h3>
  </div>

  <form method="post">
    <div class="card-body">

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>Nombre del patrón *</label>
            <input type="text" name="nombre" class="form-control" required
                   value="<?= e($patron['nombre']) ?>">
          </div>
        </div>

        <div class="col-md-3">
          <div class="form-group">
            <label>Marca</label>
            <input type="text" name="marca" class="form-control"
                   value="<?= e($patron['marca']) ?>">
          </div>
        </div>

        <div class="col-md-3">
          <div class="form-group">
            <label>Modelo</label>
            <input type="text" name="modelo" class="form-control"
                   value="<?= e($patron['modelo']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label>Serial</label>
            <input type="text" name="serial" class="form-control"
                   value="<?= e($patron['serial']) ?>">
          </div>
        </div>

        <div class="col-md-4">
          <div class="form-group">
            <label>Magnitudes</label>
            <input type="text" name="magnitudes" class="form-control"
                   placeholder="Presión, Temperatura, Flujo"
                   value="<?= e($patron['magnitudes']) ?>">
          </div>
        </div>

        <div class="col-md-4">
          <div class="form-group">
            <label>Rango</label>
            <input type="text" name="rango" class="form-control"
                   placeholder="0 – 300 mmHg"
                   value="<?= e($patron['rango']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label>Resolución</label>
            <input type="text" name="resolucion" class="form-control"
                   value="<?= e($patron['resolucion']) ?>">
          </div>
        </div>

        <div class="col-md-4">
          <div class="form-group">
            <label>Incertidumbre de referencia</label>
            <input type="text" name="incertidumbre_ref" class="form-control"
                   placeholder="U = 0.2 mmHg (k=2)"
                   value="<?= e($patron['incertidumbre_ref']) ?>">
          </div>
        </div>

        <div class="col-md-4">
          <div class="form-group">
            <label>Estado</label>
            <select name="estado" class="form-control">
              <option value="ACTIVO" <?= $patron['estado']==='ACTIVO'?'selected':'' ?>>ACTIVO</option>
              <option value="INACTIVO" <?= $patron['estado']==='INACTIVO'?'selected':'' ?>>INACTIVO</option>
            </select>
          </div>
        </div>
      </div>

      <hr>

      <h5><i class="fas fa-certificate"></i> Certificación</h5>

      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label>Número de certificado</label>
            <input type="text" name="certificado_numero" class="form-control"
                   value="<?= e($patron['certificado_numero']) ?>">
          </div>
        </div>

        <div class="col-md-4">
          <div class="form-group">
            <label>Entidad emisora</label>
            <input type="text" name="certificado_emisor" class="form-control"
                   value="<?= e($patron['certificado_emisor']) ?>">
          </div>
        </div>

        <div class="col-md-2">
          <div class="form-group">
            <label>Fecha</label>
            <input type="date" name="certificado_fecha" class="form-control"
                   value="<?= e($patron['certificado_fecha']) ?>">
          </div>
        </div>

        <div class="col-md-2">
          <div class="form-group">
            <label>Vigencia hasta</label>
            <input type="date" name="certificado_vigencia_hasta" class="form-control"
                   value="<?= e($patron['certificado_vigencia_hasta']) ?>">
          </div>
        </div>
      </div>

      <hr>

      <h5><i class="fas fa-file-upload"></i> Archivo del certificado (PDF/Imagen)</h5>

      <?php if ($id <= 0): ?>
        <div class="alert alert-warning mb-0">
          Guarda primero el patrón para poder cargar el certificado.
        </div>
      <?php else: ?>
        <div id="certAlert" class="alert alert-danger text-sm" style="display:none;"></div>

        <div class="d-flex align-items-center flex-wrap">
          <div class="mr-3 mb-2">
            <input type="file" id="inpCert" accept="application/pdf,image/*" class="form-control">
            <small class="text-muted">Máx 10MB. Formatos: PDF/JPG/PNG/WEBP.</small>
          </div>

          <div class="mb-2">
            <button type="button" id="btnCertSubir" class="btn btn-primary">
              <i class="fas fa-upload"></i> Subir archivo
            </button>

            <?php if ($certPath !== ''): ?>
              <a class="btn btn-outline-info"
                 target="_blank"
                 href="<?= e(base_url()) ?>/index.php?route=ajax_patron_cert_preview&id=<?= (int)$id ?>">
                <i class="fas fa-eye"></i> Ver
              </a>

              <a class="btn btn-outline-primary"
                 href="<?= e(base_url()) ?>/index.php?route=ajax_patron_cert_download&id=<?= (int)$id ?>">
                <i class="fas fa-download"></i> Descargar
              </a>

              <button type="button" id="btnCertEliminar" class="btn btn-outline-danger">
                <i class="fas fa-trash"></i> Eliminar
              </button>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($certPath !== ''): ?>
          <div class="text-muted text-sm mt-2">
            Archivo actual: <b><?= e(basename($certPath)) ?></b>
            <?php if ($certMime !== ''): ?> · <span class="badge badge-light"><?= e($certMime) ?></span><?php endif; ?>
          </div>
        <?php endif; ?>

        <div id="js-patron-cert" data-patron-id="<?= (int)$id ?>" data-base-url="<?= e(base_url()) ?>"></div>
        <script src="<?= e(base_url()) ?>/assets/js/patron-cert.js"></script>

      <?php endif; ?>

    </div>

    <div class="card-footer">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Guardar
      </button>

      <a href="<?= e(base_url()) ?>/index.php?route=patrones" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver
      </a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
