<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';
require_once '../../../includes/mailer.php';

/* ===============================
   ✅ OBTENER ROL DESDE BD (usuarios.rol_id)
   =============================== */
$usuario_id_sesion = (int)($_SESSION['usuario_id'] ?? 0);
$rol = 0;

if ($usuario_id_sesion > 0) {
    $stRol = $pdo->prepare("SELECT rol_id FROM usuarios WHERE id = ?");
    $stRol->execute([$usuario_id_sesion]);
    $rol = (int)($stRol->fetchColumn() ?? 0);
}

/* ===============================
   CONFIGURACION INICIAL
   =============================== */
$mensaje = "";
$categorias = ["Papeleria", "Aseo", "Dotacion", "Factura", "Cuenta de cobro", "Otros"];

/* ===============================
   LISTA FIJA DE CONSORCIOS (COMO CATEGORIAS)
   =============================== */
$consorcios = [
    "Negocios Verdes",
    "Consorcio Aburra 2025",
    "Bellos Amaneceres Arbolado C_179 Talas",
    "Plaza Caicedo",
    "Arbol de vida",
    "Bellos Amaneceres HMP"
];

$uploads_dir = __DIR__ . '/../../../uploads/compras_fijas_consorcios/';
if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

/* ===============================
   ESTADOS OFICIALES
   =============================== */
$estados_oficiales = [
    'pendiente_presupuesto',
    'aprobado_presupuesto',
    'aprobado_direccion',
    'aprobado_contabilidad',
    'aprobado_pagos'
];

/* ===============================
   PROCESAR FORMULARIO
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $solicitante_id = $usuario_id_sesion > 0 ? $usuario_id_sesion : 1;

    $consorcio     = trim($_POST['consorcio'] ?? '');
    $categoria     = trim($_POST['categoria'] ?? '');
    $proveedor     = trim($_POST['proveedor'] ?? '');
    $descripcion   = trim($_POST['descripcion'] ?? '');
    $monto         = floatval($_POST['monto'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (!$consorcio || !$categoria || !$proveedor || !$descripcion || $monto <= 0) {
        $mensaje = "Por favor completa todos los campos obligatorios.";
    } else {

        $permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        $errores_archivos = [];

        $cotizacion = null;
        $rut = null;
        $certificacion = null;
        $seguridad_social = null;
        $bitacora = null;

        function guardarArchivo($campo, $uploads_dir, $prefijo, &$errores, $permitidas) {
            if (!empty($_FILES[$campo]['name']) && $_FILES[$campo]['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $permitidas)) {
                    $errores[] = "Formato no permitido para $campo.";
                    return null;
                }
                $nombre = uniqid($prefijo . '_') . '.' . $ext;
                if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $uploads_dir . $nombre)) {
                    $errores[] = "No se pudo guardar $campo.";
                    return null;
                }
                return $nombre;
            }
            return null;
        }

        $cotizacion    = guardarArchivo('cotizacion', $uploads_dir, 'cotizacion', $errores_archivos, $permitidas);
        $rut           = guardarArchivo('rut', $uploads_dir, 'rut', $errores_archivos, $permitidas);
        $certificacion = guardarArchivo('certificacion', $uploads_dir, 'certificacion', $errores_archivos, $permitidas);

        if ($categoria === 'Cuenta de cobro') {
            $seguridad_social = guardarArchivo('seguridad_social', $uploads_dir, 'seguridad_social', $errores_archivos, $permitidas);
            $bitacora         = guardarArchivo('bitacora', $uploads_dir, 'bitacora', $errores_archivos, $permitidas);
        }

        if (!empty($errores_archivos)) {
            $mensaje = implode(' ', $errores_archivos);
        } else {

            /* ==========================================================
               ✅ ESTADO INICIAL
               - Por defecto: pendiente_presupuesto
               - Solo rol 13 puede escoger y solo de la lista oficial
               ========================================================== */
            $estado_inicial = 'pendiente_presupuesto';

            if (in_array($rol, [13, 15], true)) {
                $estado_post = trim($_POST['estado'] ?? '');
                if ($estado_post && in_array($estado_post, $estados_oficiales, true)) {
                    $estado_inicial = $estado_post;
                }
            }

            $sql = "INSERT INTO compras_fijas_consorcios (
                        consorcio,
                        solicitante_id,
                        categoria,
                        proveedor,
                        descripcion,
                        monto,
                        archivo_cotizacion,
                        archivo_rut,
                        archivo_certificacion,
                        archivo_seguridad_social,
                        archivo_bitacora,
                        observaciones_solicitante,
                        estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute([
                $consorcio,
                $solicitante_id,
                $categoria,
                $proveedor,
                $descripcion,
                $monto,
                $cotizacion,
                $rut,
                $certificacion,
                $seguridad_social,
                $bitacora,
                $observaciones,
                $estado_inicial
            ]);

            if ($ok) {

                $id_insertado = $pdo->lastInsertId();

                /* ==========================================================
                   ✅ ENVIO DE CORREO SOLO SI QUEDA EN pendiente_presupuesto
                   ========================================================== */
                if ($estado_inicial === 'pendiente_presupuesto') {

                    if (mb_strtolower($consorcio) === mb_strtolower("Consorcio Aburra 2025")) {
                        $correo_destino = "ingeniero.civil@fundocol.org";
                        $nombre_destino = "Ingeniero Civil Fundocol";
                    } else {
                        $correo_destino = "presupuesto@fundocol.org";
                        $nombre_destino = "Area de Presupuesto Fundocol";
                    }

                    $link_aprobar = "https://erp.fundocol.org/modules/consorcios/fijas/aprobar_fija_presupuesto.php?id=" . $id_insertado;
                    $asunto = "Nueva Compra Fija de Consorcio pendiente de aprobacion";

                    $mensaje_html = "
                    <div style='font-family:Arial,sans-serif;background:#f6fafd;padding:25px;'>
                      <div style='max-width:650px;margin:auto;background:#ffffff;border-radius:14px;
                                  padding:30px;box-shadow:0 6px 28px #0001;'>

                        <h2 style='color:#0ea5e9;margin-bottom:18px;'>
                            Nueva Compra Fija de Consorcio
                        </h2>

                        <p><b>Consorcio:</b> ".htmlspecialchars($consorcio)."</p>
                        <p><b>Categoria:</b> ".htmlspecialchars($categoria)."</p>
                        <p><b>Proveedor:</b> ".htmlspecialchars($proveedor)."</p>
                        <p><b>Monto:</b> $".number_format($monto,0,',','.')."</p>
                        <p><b>Descripcion:</b><br>".nl2br(htmlspecialchars($descripcion))."</p>";

                    if ($observaciones) {
                        $mensaje_html .= "
                        <p><b>Observaciones:</b><br>".nl2br(htmlspecialchars($observaciones))."</p>";
                    }

                    if ($categoria === 'Cuenta de cobro') {
                        $mensaje_html .= "
                        <p style='color:#0369a1;font-weight:600;'>
                            Esta solicitud corresponde a una CUENTA DE COBRO
                        </p>";
                    }

                    $mensaje_html .= "
                        <div style='margin-top:28px;text-align:center;'>
                            <a href='$link_aprobar'
                               style='display:inline-block;
                                      background:#0ea5e9;
                                      color:#ffffff;
                                      padding:14px 32px;
                                      border-radius:12px;
                                      font-weight:700;
                                      text-decoration:none;'>
                                Revisar y aprobar en el ERP
                            </a>
                        </div>

                        <p style='margin-top:30px;font-size:0.85rem;color:#64748b;text-align:center;'>
                            Mensaje automatico del ERP Fundocol
                        </p>
                      </div>
                    </div>";

                    $adjuntos = array_filter([
                        $cotizacion ? $uploads_dir . $cotizacion : null,
                        $rut ? $uploads_dir . $rut : null,
                        $certificacion ? $uploads_dir . $certificacion : null,
                        $seguridad_social ? $uploads_dir . $seguridad_social : null,
                        $bitacora ? $uploads_dir . $bitacora : null
                    ]);

                    enviarCorreoFundocol(
                        $correo_destino,
                        $nombre_destino,
                        $asunto,
                        $mensaje_html,
                        $adjuntos
                    );
                }

                header("Location: compras_fijas.php?msg=ok");
                exit;

            } else {
                $mensaje = "Error al guardar la compra fija.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Agregar Compra Fija - Consorcio</title>

<link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../../../assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="consorcios-fijas-agregar-page">
<div class="navbar-spacer"></div>

<div class="form-contenedor">
    <div class="form-titulo"><i class="bi bi-diagram-3"></i> Agregar Compra Fija - Consorcio</div>
    <div class="form-sub">Completa los datos del consorcio y adjunta la documentación.</div>

    <?php if ($mensaje): ?><div class="msg-error"><?= $mensaje ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" autocomplete="off">

        <div class="form-group">
            <label class="form-label" for="consorcio">Consorcio / Proyecto *</label>
            <select name="consorcio" id="consorcio" class="form-select" required>
                <option value="">Seleccione...</option>
                <?php foreach ($consorcios as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= (!empty($_POST['consorcio']) && $_POST['consorcio'] == $c) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="categoria">Categoría *</label>
            <select name="categoria" id="categoria" class="form-select" required>
                <option value="">Seleccione...</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= (!empty($_POST['categoria']) && $_POST['categoria'] == $cat) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="proveedor">Proveedor *</label>
            <input type="text" name="proveedor" id="proveedor" class="form-control" required maxlength="120" placeholder="Nombre proveedor" value="<?= htmlspecialchars($_POST['proveedor'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label" for="descripcion">Descripción / Necesidad *</label>
            <textarea name="descripcion" id="descripcion" class="form-control" required maxlength="500" rows="2" placeholder="¿Qué se va a comprar y por qué?"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="monto">Monto (COP) *</label>
            <input type="number" name="monto" id="monto" class="form-control" required min="0.01" step="0.01"
                   placeholder="Ejemplo: 250000.50" value="<?= htmlspecialchars($_POST['monto'] ?? '') ?>">
        </div>

        <!-- ✅ SOLO ROL 13 PUEDE ELEGIR ESTADO -->
        <?php if (in_array($rol, [13, 15], true)): ?>
        <div class="form-group">
            <label class="form-label" for="estado">Estado (solo rol 13)</label>
            <select name="estado" id="estado" class="form-select">
                <option value="pendiente_presupuesto" <?= (!empty($_POST['estado']) && $_POST['estado'] === 'pendiente_presupuesto') ? 'selected' : '' ?>>pendiente_presupuesto</option>
                <option value="aprobado_presupuesto" <?= (!empty($_POST['estado']) && $_POST['estado'] === 'aprobado_presupuesto') ? 'selected' : '' ?>>aprobado_presupuesto</option>
                <option value="aprobado_direccion" <?= (!empty($_POST['estado']) && $_POST['estado'] === 'aprobado_direccion') ? 'selected' : '' ?>>aprobado_direccion</option>
                <option value="aprobado_contabilidad" <?= (!empty($_POST['estado']) && $_POST['estado'] === 'aprobado_contabilidad') ? 'selected' : '' ?>>aprobado_contabilidad</option>
                <option value="aprobado_pagos" <?= (!empty($_POST['estado']) && $_POST['estado'] === 'aprobado_pagos') ? 'selected' : '' ?>>aprobado_pagos</option>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label" for="cotizacion">Cotización <span class="form-nota">(PDF/JPG/PNG)</span></label>
            <input type="file" name="cotizacion" id="cotizacion" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="form-group">
            <label class="form-label" for="rut">RUT proveedor <span class="form-nota">(PDF/JPG/PNG)</span></label>
            <input type="file" name="rut" id="rut" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="form-group">
            <label class="form-label" for="certificacion">Certificación bancaria <span class="form-nota">(PDF/JPG/PNG)</span></label>
            <input type="file" name="certificacion" id="certificacion" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="form-group campo-oculto" id="campo_seguridad_social">
            <label class="form-label">Seguridad social</label>
            <input type="file" name="seguridad_social" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="form-group campo-oculto" id="campo_bitacora">
            <label class="form-label">Informe o bitacora</label>
            <input type="file" name="bitacora" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="form-group">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
        </div>

        <div class="form-btns">
            <button type="submit" class="btn-principal">Guardar</button>
            <a href="compras_fijas.php" class="btn-sec">Cancelar</a>
        </div>

    </form>
</div>

<!-- MODAL CARGANDO -->
<div id="modalCargando" class="modal-cargando">
    <div class="modal-cargando-contenido">
        <h4 class="modal-cargando-titulo">Procesando solicitud</h4>
        <p class="modal-cargando-texto">
            Tu solicitud se está cargando.<br>
            Por favor no cierres esta ventana.
        </p>
        <div class="modal-cargando-barra">
            <div class="barra-carga"></div>
        </div>
    </div>
</div>

<!-- JS MINIMO - NO TOCA CSS -->
<script>
document.getElementById('categoria').addEventListener('change', function () {
    const mostrar = this.value === 'Cuenta de cobro';
    document.getElementById('campo_seguridad_social').style.display = mostrar ? 'block' : 'none';
    document.getElementById('campo_bitacora').style.display = mostrar ? 'block' : 'none';
});

/* ===============================
   BLOQUEAR DOBLE SUBMIT
   =============================== */
const form = document.querySelector("form");
let enviado = false;

form.addEventListener("submit", function () {
    if (enviado) return false;
    enviado = true;

    document.getElementById("modalCargando").style.display = "flex";

    form.querySelectorAll("button, input[type=submit]").forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = "0.7";
        btn.style.cursor = "not-allowed";
    });
});
</script>

</body>
</html>




