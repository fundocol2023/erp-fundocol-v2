<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';

// ✅ NUEVO: Graph mailer (reemplaza phpmailer.php)
require_once '../../../includes/mailer.php';

$mensaje = "";

/* ===============================
   CATEGORIAS
   =============================== */
$categorias = [
    "Papeleria",
    "Aseo",
    "Dotacion",
    "Caja menor",
    "Factura",
    "Cuenta de cobro",
    "Toner",
    "Otros"
];

/* ===============================
   DIRECTORIO DE SUBIDA
   =============================== */
$uploads_dir = '../../../uploads/compras_fijas/';
if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

/* ===============================
   PROCESAR FORMULARIO
   =============================== */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $solicitante_id = $_SESSION['usuario_id'] ?? 1;
    $categoria      = trim($_POST['categoria'] ?? '');
    $proveedor      = trim($_POST['proveedor'] ?? '');
    $descripcion    = trim($_POST['descripcion'] ?? '');
    $monto          = floatval($_POST['monto'] ?? 0);
    $observaciones  = trim($_POST['observaciones'] ?? '');

    if (!$categoria || !$proveedor || !$descripcion || $monto <= 0) {
        $mensaje = "Por favor completa todos los campos obligatorios.";
    } else {

        /* ===============================
           VARIABLES DE ARCHIVOS
           =============================== */
        $cotizacion = null;
        $rut = null;
        $certificacion = null;
        $seguridad_social = null;
        $bitacora = null;

        $errores_archivos = [];
        $permitidas = ['pdf', 'jpg', 'jpeg', 'png'];

        /* ===============================
           FUNCION GUARDAR ARCHIVO
           =============================== */
        function guardarArchivo($campo, $uploads_dir, $prefijo, &$errores_archivos, $permitidas) {
            if (!empty($_FILES[$campo]['name']) && $_FILES[$campo]['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $permitidas)) {
                    $errores_archivos[] = "Formato no permitido para $campo.";
                    return null;
                }

                $nombre = uniqid($prefijo . '_') . '.' . $ext;
                if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $uploads_dir . $nombre)) {
                    $errores_archivos[] = "No se pudo guardar el archivo $campo.";
                    return null;
                }
                return $nombre;
            }
            return null;
        }

        /* ===============================
           ARCHIVOS BASE
           =============================== */
        $cotizacion    = guardarArchivo('cotizacion', $uploads_dir, 'cotizacion', $errores_archivos, $permitidas);
        $rut           = guardarArchivo('rut', $uploads_dir, 'rut', $errores_archivos, $permitidas);
        $certificacion = guardarArchivo('certificacion', $uploads_dir, 'certificacion', $errores_archivos, $permitidas);

        /* ===============================
           CUENTA DE COBRO
           =============================== */
        if ($categoria === 'Cuenta de cobro') {
            $seguridad_social = guardarArchivo('seguridad_social', $uploads_dir, 'seguridad_social', $errores_archivos, $permitidas);
            $bitacora         = guardarArchivo('bitacora', $uploads_dir, 'bitacora', $errores_archivos, $permitidas);
        }

        if (!empty($errores_archivos)) {
            $mensaje = implode(" ", $errores_archivos);
        } else {

            /* ===============================
               INSERT
               =============================== */
            $sql = "INSERT INTO compras_fijas (
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
                        observaciones,
                        estado,
                        fecha_solicitud
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente_presupuesto', NOW())";

            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute([
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
                $observaciones
            ]);

            if ($ok) {

                $id_insertado = $pdo->lastInsertId();

                /* ===============================
                   ✅ CORREO (POR AHORA SOLO PRUEBA)
                   =============================== */
                // ✅ Por ahora lo mandamos a ti para probar
                $correo_presupuesto = "presupuesto@fundocol.org";
                $nombre_presupuesto = "Martin Reyes";

                $link_aprobar = "https://erp.fundocol.org/modules/compras/fijas/aprobar_fija_presupuesto.php?id=$id_insertado";

                $asunto = "Nueva Compra Fija pendiente de aprobación (PRUEBA GRAPH)";

                $mensaje_html = "
                <div style='font-family:Arial, sans-serif; color:#1f2937; line-height:1.6; max-width:600px;'>

                    <h2 style='color:#0ea5e9;'>Nueva Compra Fija para aprobar (PRUEBA GRAPH)</h2>

                    <div style='background:#f1f5f9; padding:15px 20px; border-radius:12px;'>
                        <p><b>Categoria:</b> ".htmlspecialchars($categoria)."</p>
                        <p><b>Proveedor:</b> ".htmlspecialchars($proveedor)."</p>
                        <p><b>Monto:</b> $".number_format($monto,0,',','.')."</p>
                        <p><b>Descripcion:</b><br>".nl2br(htmlspecialchars($descripcion))."</p>
                        ".($observaciones ? "<p><b>Observaciones:</b><br>".nl2br(htmlspecialchars($observaciones))."</p>" : "")."
                    </div>

                    <div style='text-align:center; margin:30px 0;'>
                        <a href='$link_aprobar'
                           style='background:#0ea5e9;color:#fff;padding:14px 32px;
                                  border-radius:10px;font-weight:700;text-decoration:none;'>
                            Revisar y aprobar en ERP Fundocol
                        </a>
                    </div>

                    <p style='font-size:13px;color:#6b7280;text-align:center;'>
                        Mensaje automático del ERP Fundocol (Graph)
                    </p>
                </div>";

                /* ===============================
                   ADJUNTOS
                   =============================== */
                $adjuntos = array_filter([
                    $cotizacion ? $uploads_dir.$cotizacion : null,
                    $rut ? $uploads_dir.$rut : null,
                    $certificacion ? $uploads_dir.$certificacion : null,
                    $seguridad_social ? $uploads_dir.$seguridad_social : null,
                    $bitacora ? $uploads_dir.$bitacora : null
                ]);

                // ✅ Envío con Graph usando tu nueva función
                $enviado = enviarCorreoFundocol(
                    $correo_presupuesto,
                    $nombre_presupuesto,
                    $asunto,
                    $mensaje_html,
                    $adjuntos
                );

                // Si quieres, puedes controlar si falló:
                // if (!$enviado) { $mensaje = "Se guardó la solicitud, pero falló el correo."; }

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
    <title>Agregar Compra Fija</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="compras-fijas-agregar-page">

<div class="navbar-spacer"></div>

<div class="form-contenedor">
    <div class="form-titulo"><i class="bi bi-folder-plus"></i> Agregar Compra Fija</div>
    <div class="form-sub">Registra una compra fija y adjunta los soportes requeridos.</div>

    <?php if($mensaje): ?>
        <div class="msg-error"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" autocomplete="off">

        <div class="form-group">
            <label class="form-label" for="categoria">Categoria *</label>
            <select name="categoria" id="categoria" class="form-select" required>
                <option value="">Seleccione...</option>
                <?php foreach($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= (isset($_POST['categoria']) && $_POST['categoria'] == $cat) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="proveedor">Proveedor *</label>
            <input type="text" name="proveedor" id="proveedor" class="form-control" required maxlength="120"
                   value="<?= htmlspecialchars($_POST['proveedor'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label" for="descripcion">Descripcion / Necesidad *</label>
            <textarea name="descripcion" id="descripcion" class="form-control" required maxlength="500" rows="2"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="monto">Monto (COP) *</label>
            <input type="number" name="monto" id="monto" class="form-control" required min="0.01" step="0.01"
                   value="<?= htmlspecialchars($_POST['monto'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Cotizacion / Factura</label>
            <input type="file" name="cotizacion" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="form-group">
            <label class="form-label">RUT</label>
            <input type="file" name="rut" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="form-group">
            <label class="form-label">Certificacion bancaria</label>
            <input type="file" name="certificacion" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <!-- ===============================
             SOLO CUENTA DE COBRO
        =============================== -->
        <div id="campos-cuenta-cobro" class="campos-cuenta-cobro">

            <div class="form-group">
                <label class="form-label">Seguridad social</label>
                <input type="file" name="seguridad_social" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            </div>

            <div class="form-group">
                <label class="form-label">Bitacora / Informe</label>
                <input type="file" name="bitacora" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            </div>

        </div>

        <div class="form-group">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control" maxlength="500" rows="2"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
        </div>

        <div class="form-btns">
            <button type="submit" class="btn-principal">Guardar y Enviar a Presupuesto</button>
            <a href="index.php" class="btn-sec">Cancelar</a>
        </div>

    </form>
</div>
<!-- MODAL CARGANDO -->
<div id="modalCargando" class="modal-cargando">
    <div class="modal-cargando-contenido">
        <h4 class="modal-cargando-titulo">
            Procesando solicitud
        </h4>

        <p class="modal-cargando-texto">
            Tu solicitud se está cargando.<br>
            Por favor no cierres esta ventana.
        </p>

        <div class="modal-cargando-barra">
            <div class="barra-carga"></div>
        </div>
    </div>
</div>



<script>
const categoria = document.getElementById('categoria');
const cuentaCobro = document.getElementById('campos-cuenta-cobro');

function validarCategoria() {
    cuentaCobro.style.display = (categoria.value === 'Cuenta de cobro') ? 'block' : 'none';
}

categoria.addEventListener('change', validarCategoria);
validarCategoria();

/* ===============================
   BLOQUEAR DOBLE SUBMIT (FIX REAL)
   =============================== */
const form = document.querySelector("form");
const submitBtn = form.querySelector("button[type='submit']");
let enviado = false;

form.addEventListener("submit", function () {

    if (enviado) {
        return false;
    }

    enviado = true;

    // Mostrar modal
    document.getElementById("modalCargando").style.display = "flex";

    // 🔒 SOLO deshabilitar botón (NO inputs)
    submitBtn.disabled = true;
    submitBtn.innerHTML = "Enviando...";
    submitBtn.style.opacity = "0.8";
    submitBtn.style.cursor = "not-allowed";
});
</script>


</body>
</html>




