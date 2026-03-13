<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';
require_once '../../../includes/mailer.php';

session_start();
// Solo Contabilidad (rol 8)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 8) {
    header('Location: ../../login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo "Solicitud no especificada."; exit; }

// Traer solicitud (incluye observaciones previas)
$stmt = $pdo->prepare("
    SELECT 
        cf.*,
        cf.observaciones_presupuesto,
        cf.observaciones_direccion,
        u.nombre AS solicitante_nombre,
        u.email AS solicitante_email
    FROM compras_fijas_consorcios cf
    INNER JOIN usuarios u ON cf.solicitante_id = u.id
    WHERE cf.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo 'Solicitud no encontrada.';
    exit;
}

$mensaje = '';


// Factura siggo existente
$factura_siggo = $solicitud['soporte_pago'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $accion = $_POST['accion'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $factura_siggo = null;

    /* ===============================
       SUBIR FACTURA SIGGO
    =============================== */
    if ($accion == 'aprobar' && isset($_FILES['factura_siggo']) && $_FILES['factura_siggo']['error'] == 0) {

        $ext = strtolower(pathinfo($_FILES['factura_siggo']['name'], PATHINFO_EXTENSION));
        $permitidas = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $permitidas)) {
            $mensaje = "Formato de archivo de factura no permitido.";
        } else {
            $uploads_dir = '../../../uploads/compras_fijas_consorcios/';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
            }

            $factura_siggo = uniqid('factura_siggo_') . '.' . $ext;
            move_uploaded_file($_FILES['factura_siggo']['tmp_name'], $uploads_dir . $factura_siggo);
        }
    }

    /* ===============================
       APROBAR
    =============================== */
    if ($accion == 'aprobar') {

        if (!$factura_siggo) {
            $mensaje = "Debes adjuntar la factura siggo para aprobar.";
        } else {

            $pdo->prepare("
                UPDATE compras_fijas_consorcios 
                SET estado = 'aprobado_contabilidad',
                    aprobador_contabilidad_id = ?,
                    fecha_aprob_contabilidad = NOW(),
                    observaciones_contabilidad = ?,
                    soporte_pago = ?
                WHERE id = ?
            ")->execute([
                $_SESSION['usuario_id'],
                $observaciones,
                $factura_siggo,
                $id
            ]);

            /* ===============================
               CORREO A PAGOS
            =============================== */
            $correo_pagos = "pagos@fundocol.org";
            $nombre_pagos = "Pagos Fundocol";
            $asunto = "Compra Fija de Consorcio pendiente de pago";

            $link = "https://erp.fundocol.org/modules/consorcios/fijas/aprobar_fija_pagos.php?id={$id}";

            $mensaje_html = "
            <div style='font-family:Segoe UI,Arial,sans-serif;background:#f6fafd;padding:24px;'>
              <div style='max-width:650px;margin:0 auto;background:#ffffff;
                          border-radius:18px;box-shadow:0 8px 28px #2176ff22;
                          padding:30px 32px;'>

                <h2 style='color:#0ea5e9;margin-top:0;'>Compra fija lista para pago</h2>

                <p>
                  La siguiente compra fija fue <b>aprobada por Contabilidad</b>
                  y queda pendiente de pago.
                </p>

                <hr>

                <h3>Informacion de la solicitud</h3>
                <p><b>ID:</b> {$solicitud['id']}</p>
                <p><b>Consorcio:</b> ".htmlspecialchars($solicitud['consorcio'])."</p>
                <p><b>Categoria:</b> ".htmlspecialchars($solicitud['categoria'])."</p>
                <p><b>Proveedor:</b> ".htmlspecialchars($solicitud['proveedor'])."</p>
                <p><b>Monto:</b> $".number_format($solicitud['monto'],0,',','.')."</p>

                ".(!empty($observaciones) ? "
                <p><b>Observaciones de Contabilidad:</b><br>
                ".nl2br(htmlspecialchars($observaciones))."</p>" : "")."

                <div style='margin-top:30px;text-align:center;'>
                  <a href='{$link}' style='
                      display:inline-block;
                      background:#0ea5e9;
                      color:#fff;
                      padding:14px 30px;
                      border-radius:14px;
                      text-decoration:none;
                      font-weight:700;'>
                    Registrar pago en ERP Fundocol
                  </a>
                </div>

              </div>
            </div>";

            /* ===============================
               ADJUNTOS
            =============================== */
            $uploads_dir = '../../../uploads/compras_fijas_consorcios/';
            $adjuntos = [];

            if ($solicitud['archivo_cotizacion']) {
                $adjuntos[] = $uploads_dir . $solicitud['archivo_cotizacion'];
            }
            if ($solicitud['archivo_rut']) {
                $adjuntos[] = $uploads_dir . $solicitud['archivo_rut'];
            }
            if ($solicitud['archivo_certificacion']) {
                $adjuntos[] = $uploads_dir . $solicitud['archivo_certificacion'];
            }

            // SOLO SI ES CUENTA DE COBRO
            if ($solicitud['categoria'] === 'Cuenta de cobro') {
                if (!empty($solicitud['archivo_seguridad_social'])) {
                    $adjuntos[] = $uploads_dir . $solicitud['archivo_seguridad_social'];
                }
                if (!empty($solicitud['archivo_bitacora'])) {
                    $adjuntos[] = $uploads_dir . $solicitud['archivo_bitacora'];
                }
            }

            if ($factura_siggo) {
                $adjuntos[] = $uploads_dir . $factura_siggo;
            }

            enviarCorreoFundocol(
                $correo_pagos,
                $nombre_pagos,
                $asunto,
                $mensaje_html,
                $adjuntos
            );

            header("Location: https://erp.fundocol.org/modules/pendientes/index.php");
            exit;
        }
    }

    /* ===============================
       RECHAZAR
    =============================== */
    if ($accion == 'rechazar') {

        if (!$observaciones) {
            $mensaje = "Debes indicar el motivo del rechazo.";
        } else {

            $pdo->prepare("
                UPDATE compras_fijas_consorcios 
                SET estado = 'rechazado_contabilidad',
                    aprobador_contabilidad_id = ?,
                    fecha_aprob_contabilidad = NOW(),
                    observaciones_contabilidad = ?
                WHERE id = ?
            ")->execute([
                $_SESSION['usuario_id'],
                $observaciones,
                $id
            ]);

            if (!empty($solicitud['solicitante_email'])) {

                $asunto = "Compra fija rechazada por Contabilidad";

                $mensaje_html = "
                <div style='font-family:Segoe UI,Arial,sans-serif;background:#fff5f5;padding:24px;'>
                  <div style='max-width:650px;margin:0 auto;background:#ffffff;
                              border-radius:18px;box-shadow:0 8px 28px #ef444422;
                              padding:30px 32px;'>

                    <h2 style='color:#dc2626;'>Compra fija rechazada</h2>

                    <p>Tu solicitud fue rechazada por Contabilidad.</p>

                    <p><b>Motivo:</b><br>
                    ".nl2br(htmlspecialchars($observaciones))."</p>

                  </div>
                </div>";

                enviarCorreoFundocol(
                    $solicitud['solicitante_email'],
                    $solicitud['solicitante_nombre'],
                    $asunto,
                    $mensaje_html
                );
            }

            header("Location: https://erp.fundocol.org/modules/pendientes/index.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisar Compra Fija Consorcio (Contabilidad)</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="consorcios-fijas-aprobar-contabilidad-page">
<div class="navbar-spacer"></div>
<div class="aprobacion-box">
    <div class="aprob-title"><i class="bi bi-file-earmark-check"></i> Revisión de Compra Fija Consorcio (Contabilidad)</div>
    <?php if($mensaje): ?><div class="msg-error"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <div class="aprob-section">
        <div class="aprob-section-title"><i class="bi bi-info-circle"></i> Resumen</div>
        <div class="aprobacion-grid">
            <div class="label-erp">Consorcio / Proyecto:<span class="valor-erp"><?= htmlspecialchars($solicitud['consorcio']) ?></span></div>
            <div class="label-erp">Solicitante:<span class="valor-erp"><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></span></div>
            <div class="label-erp">Categoría:<span class="valor-erp"><?= htmlspecialchars($solicitud['categoria']) ?></span></div>
            <div class="label-erp">Proveedor:<span class="valor-erp"><?= htmlspecialchars($solicitud['proveedor']) ?></span></div>
            <div class="label-erp">Monto:<span class="valor-erp">$<?= number_format($solicitud['monto'], 0, ',', '.') ?></span></div>
            <div class="label-erp">Descripción:<span class="valor-erp"><?= htmlspecialchars($solicitud['descripcion']) ?></span></div>
            <?php if($solicitud['observaciones_solicitante']): ?>
            <div class="label-erp">Observaciones del solicitante:<span class="valor-erp"><?= htmlspecialchars($solicitud['observaciones_solicitante']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($solicitud['observaciones_presupuesto'])): ?>
            <div class="label-erp observaciones-block observaciones-presupuesto">
                Observaciones de Presupuesto:
                <div class="observaciones-box observaciones-box-presupuesto">
                    <?= nl2br(htmlspecialchars($solicitud['observaciones_presupuesto'])) ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($solicitud['observaciones_direccion'])): ?>
            <div class="label-erp observaciones-block observaciones-direccion">
                Observaciones de Dirección:
                <div class="observaciones-box observaciones-box-direccion">
                    <?= nl2br(htmlspecialchars($solicitud['observaciones_direccion'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="aprob-section">
        <div class="aprob-section-title"><i class="bi bi-paperclip"></i> Soportes</div>
        <div class="archivos-row">

    <?php if ($solicitud['archivo_cotizacion']): ?>
        <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_cotizacion']) ?>" target="_blank" class="archivo-link">
            <i class="bi bi-paperclip"></i> Cotizacion
        </a>
    <?php endif; ?>

    <?php if ($solicitud['archivo_rut']): ?>
        <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_rut']) ?>" target="_blank" class="archivo-link">
            <i class="bi bi-paperclip"></i> RUT
        </a>
    <?php endif; ?>

    <?php if ($solicitud['archivo_certificacion']): ?>
        <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_certificacion']) ?>" target="_blank" class="archivo-link">
            <i class="bi bi-paperclip"></i> Certificacion
        </a>
    <?php endif; ?>

    <?php if ($factura_siggo): ?>
        <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($factura_siggo) ?>" target="_blank" class="archivo-link">
            <i class="bi bi-paperclip"></i> Factura Siggo
        </a>
    <?php endif; ?>

    <?php if ($solicitud['categoria'] === 'Cuenta de cobro'): ?>

        <?php if (!empty($solicitud['archivo_seguridad_social'])): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_seguridad_social']) ?>" target="_blank" class="archivo-link">
                <i class="bi bi-paperclip"></i> Seguridad Social
            </a>
        <?php endif; ?>

        <?php if (!empty($solicitud['archivo_bitacora'])): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_bitacora']) ?>" target="_blank" class="archivo-link">
                <i class="bi bi-paperclip"></i> Bitacora
            </a>
        <?php endif; ?>

    <?php endif; ?>

        </div>
    </div>


    <form method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="form-group">
            <label class="label-erp" for="factura_siggo">Factura Siggo (PDF/JPG/PNG):</label>
            <input type="file" name="factura_siggo" id="factura_siggo" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?= $factura_siggo ? "" : "required" ?>>
        </div>
        <div class="form-group">
            <label class="label-erp" for="observaciones">Observaciones (si rechaza, obligatorio):</label>
            <textarea name="observaciones" id="observaciones" class="form-control" maxlength="500" rows="2"></textarea>
        </div>
        <div class="acciones-aprobar">
            <button type="submit" name="accion" value="aprobar" class="btn-aprobar">
                <i class="bi bi-check2-circle"></i> Aprobar
            </button>
            <button type="submit" name="accion" value="rechazar" class="btn-rechazar" onclick="return confirm('¿Seguro que deseas rechazar esta solicitud?')">
                <i class="bi bi-x-circle"></i> Rechazar
            </button>
        </div>
    </form>
</div>
</body>
</html>