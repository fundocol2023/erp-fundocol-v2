<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';
require_once '../../../includes/mailer.php';

// Mostrar errores durante desarrollo (quítalo en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Solo rol Contabilidad (5)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 5) {
    header('Location: ../../login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "Solicitud no especificada.";
    exit;
}

// Buscar la solicitud
$stmt = $pdo->prepare("
    SELECT cf.*, u.nombre AS solicitante_nombre, u.email AS solicitante_email
    FROM compras_fijas cf
    INNER JOIN usuarios u ON cf.solicitante_id = u.id
    WHERE cf.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo "Solicitud no encontrada.";
    exit;
}

$mensaje = "";

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $factura_siggo = $solicitud['archivo_factura'] ?? null;

    // Procesar archivo de factura Siggo si se adjunta
    if ($accion === 'aprobar' && isset($_FILES['factura_siggo']) && $_FILES['factura_siggo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['factura_siggo']['name'], PATHINFO_EXTENSION));
        $permitidas = ['pdf', 'jpg', 'jpeg', 'png'];

        if (in_array($ext, $permitidas, true)) {
            $uploads_dir = '../../../uploads/compras_fijas/';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
            }

            $nombre_archivo = uniqid('factura_') . '.' . $ext;

            if (move_uploaded_file($_FILES['factura_siggo']['tmp_name'], $uploads_dir . $nombre_archivo)) {
                $factura_siggo = $nombre_archivo;
            } else {
                $mensaje = "⚠️ No se pudo guardar la factura Siggo.";
            }
        } else {
            $mensaje = "⚠️ Formato de archivo de factura no permitido.";
        }
    }

    // === APROBAR ===
    if ($accion === 'aprobar') {
        if (!$factura_siggo) {
            $mensaje = "⚠️ Debes adjuntar la factura Siggo para aprobar.";
        } else {
            try {
                $pdo->prepare("
                    UPDATE compras_fijas 
                    SET estado = 'aprobado_contabilidad',
                        fecha_aprobacion_contabilidad = NOW(),
                        archivo_factura = ?,
                        observaciones_contabilidad = ?
                    WHERE id = ?
                ")->execute([
                    $factura_siggo,
                    $observaciones ?: null,
                    $id
                ]);

                // Notificar a Pagos
                $correo_pagos = "pagos@fundocol.org";
                $nombre_pagos = "Pagos Fundocol";
                $asunto = "Compra Fija pendiente de pago";
                $link = "https://erp.fundocol.org/modules/pendientes/index.php?tab=comprasfijas";

                $bloque_obs_presupuesto = '';
                if (!empty($solicitud['observaciones_presupuesto'])) {
                    $bloque_obs_presupuesto = "
                    <div style='background:#eef6ff; padding:12px 15px; border-radius:10px; margin-bottom:12px; border-left:4px solid #0ea5e9;'>
                        <p style='margin:0;'><b>Observaciones de Presupuesto:</b><br>" . nl2br(htmlspecialchars($solicitud['observaciones_presupuesto'])) . "</p>
                    </div>";
                }

                $bloque_obs_direccion = '';
                if (!empty($solicitud['observaciones_direccion'])) {
                    $bloque_obs_direccion = "
                    <div style='background:#f5f3ff; padding:12px 15px; border-radius:10px; margin-bottom:12px; border-left:4px solid #8b5cf6;'>
                        <p style='margin:0;'><b>Observaciones de Dirección:</b><br>" . nl2br(htmlspecialchars($solicitud['observaciones_direccion'])) . "</p>
                    </div>";
                }

                $bloque_obs_contabilidad = '';
                if (!empty($observaciones)) {
                    $bloque_obs_contabilidad = "
                    <div style='background:#fff7e6; padding:12px 15px; border-radius:10px; margin-bottom:12px; border-left:4px solid #f59e0b;'>
                        <p style='margin:0;'><b>Observaciones de Contabilidad:</b><br>" . nl2br(htmlspecialchars($observaciones)) . "</p>
                    </div>";
                }

                $mensaje_html = "
                <div style='font-family:Arial, sans-serif; color:#1f2937; line-height:1.6; max-width:600px;'>

                    <h2 style='color:#0ea5e9; margin-bottom:15px;'>
                        Compra Fija pendiente de pago
                    </h2>

                    <div style='background:#f1f5f9; padding:15px 20px; border-radius:12px; margin-bottom:20px;'>
                        <p style='margin:6px 0;'><b>Categoría:</b> " . htmlspecialchars($solicitud['categoria']) . "</p>
                        <p style='margin:6px 0;'><b>Proveedor:</b> " . htmlspecialchars($solicitud['proveedor']) . "</p>
                        <p style='margin:6px 0;'><b>Monto:</b> $" . number_format($solicitud['monto'], 0, ',', '.') . "</p>
                        <p style='margin:6px 0;'><b>Descripción:</b><br>" . nl2br(htmlspecialchars($solicitud['descripcion'])) . "</p>
                    </div>

                    $bloque_obs_presupuesto
                    $bloque_obs_direccion
                    $bloque_obs_contabilidad

                    <div style='text-align:center; margin:30px 0;'>
                        <a href='$link'
                           style='background:#0ea5e9; color:#ffffff; padding:14px 32px;
                                  font-size:16px; font-weight:700; text-decoration:none;
                                  border-radius:10px; display:inline-block;
                                  box-shadow:0 4px 12px rgba(14,165,233,0.35);'>
                            Revisar y pagar en ERP Fundocol
                        </a>
                    </div>

                    <hr style='border:none; border-top:1px solid #e5e7eb; margin:25px 0;'>

                    <p style='font-size:13px; color:#6b7280; text-align:center;'>
                        Este es un mensaje automático del sistema ERP Fundocol.
                        No responda a este correo.
                    </p>

                </div>
                ";

                $uploads_dir = '../../../uploads/compras_fijas/';
                $adjuntos = array_filter([
                    !empty($solicitud['archivo_cotizacion']) ? $uploads_dir . $solicitud['archivo_cotizacion'] : null,
                    !empty($solicitud['archivo_rut']) ? $uploads_dir . $solicitud['archivo_rut'] : null,
                    !empty($solicitud['archivo_certificacion']) ? $uploads_dir . $solicitud['archivo_certificacion'] : null,
                    !empty($factura_siggo) ? $uploads_dir . $factura_siggo : null
                ]);

                // Si es cuenta de cobro, adjunta también estos
                if ($solicitud['categoria'] === 'Cuenta de cobro') {
                    if (!empty($solicitud['archivo_seguridad_social'])) {
                        $adjuntos[] = $uploads_dir . $solicitud['archivo_seguridad_social'];
                    }
                    if (!empty($solicitud['archivo_bitacora'])) {
                        $adjuntos[] = $uploads_dir . $solicitud['archivo_bitacora'];
                    }
                }

                enviarCorreoFundocol(
                    $correo_pagos,
                    $nombre_pagos,
                    $asunto,
                    $mensaje_html,
                    $adjuntos
                );

                header("Location: https://erp.fundocol.org/modules/pendientes/index.php?tab=comprasfijas");
                exit;
            } catch (Exception $e) {
                $mensaje = "❌ Error al aprobar: " . $e->getMessage();
            }
        }
    }

    // === RECHAZAR ===
    if ($accion === 'rechazar') {
        if (empty($observaciones)) {
            $mensaje = "⚠️ Debes indicar el motivo del rechazo.";
        } else {
            $pdo->prepare("
                UPDATE compras_fijas 
                SET estado = 'rechazado_contabilidad',
                    comentario_rechazo = ?,
                    usuario_rechazo = ?,
                    fecha_rechazo = NOW()
                WHERE id = ?
            ")->execute([
                $observaciones,
                $_SESSION['usuario_id'],
                $id
            ]);

            // Notificar al solicitante
            if (!empty($solicitud['solicitante_email'])) {
                $asunto = "Tu solicitud de compra fija fue rechazada";
                $mensaje_html = "
                    <h2>Solicitud rechazada</h2>
                    <p>Tu solicitud fue rechazada por el área de Contabilidad.</p>
                    <p><b>Motivo:</b><br>" . nl2br(htmlspecialchars($observaciones)) . "</p>
                    <br><small>ERP Fundocol</small>
                ";

                enviarCorreoFundocol(
                    $solicitud['solicitante_email'],
                    $solicitud['solicitante_nombre'],
                    $asunto,
                    $mensaje_html
                );
            }

            header("Location: https://erp.fundocol.org/modules/pendientes/index.php?tab=comprasfijas");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisión de Compra Fija (Contabilidad)</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="compras-fijas-aprobar-contabilidad-page">
<div class="navbar-spacer"></div>
<div class="aprobacion-box">
    <div class="aprob-title"><i class="bi bi-file-earmark-check"></i> Revisión de Compra Fija (Contabilidad)</div>
    <?php if ($mensaje): ?>
        <div class="msg-error"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="aprob-section">
        <div class="aprob-section-title"><i class="bi bi-info-circle"></i> Resumen</div>
        <div class="aprobacion-grid">
            <div class="label-erp">Solicitante:<span class="valor-erp"><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></span></div>
            <div class="label-erp">Categoría:<span class="valor-erp"><?= htmlspecialchars($solicitud['categoria']) ?></span></div>
            <div class="label-erp">Proveedor:<span class="valor-erp"><?= htmlspecialchars($solicitud['proveedor']) ?></span></div>
            <div class="label-erp">Monto:<span class="valor-erp">$<?= number_format($solicitud['monto'], 0, ',', '.') ?></span></div>
            <div class="label-erp">Descripción:<span class="valor-erp"><?= htmlspecialchars($solicitud['descripcion']) ?></span></div>

            <?php if (!empty($solicitud['observaciones'])): ?>
                <div class="label-erp">Observaciones del solicitante:<span class="valor-erp"><?= nl2br(htmlspecialchars($solicitud['observaciones'])) ?></span></div>
            <?php endif; ?>

            <?php if (!empty($solicitud['observaciones_presupuesto'])): ?>
                <div class="label-erp">Observaciones de Presupuesto:<span class="valor-erp"><?= nl2br(htmlspecialchars($solicitud['observaciones_presupuesto'])) ?></span></div>
            <?php endif; ?>

            <?php if (!empty($solicitud['observaciones_direccion'])): ?>
                <div class="label-erp">Observaciones de Dirección:<span class="valor-erp"><?= nl2br(htmlspecialchars($solicitud['observaciones_direccion'])) ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="aprob-section">
        <div class="aprob-section-title"><i class="bi bi-paperclip"></i> Soportes</div>
        <div class="archivos-row">
            <?php if ($solicitud['archivo_cotizacion']): ?>
                <a href="../../../uploads/compras_fijas/<?= urlencode($solicitud['archivo_cotizacion']) ?>" target="_blank" class="archivo-link">
                    <i class="bi bi-paperclip"></i> Cotización
                </a>
            <?php endif; ?>

            <?php if ($solicitud['archivo_rut']): ?>
                <a href="../../../uploads/compras_fijas/<?= urlencode($solicitud['archivo_rut']) ?>" target="_blank" class="archivo-link">
                    <i class="bi bi-paperclip"></i> RUT
                </a>
            <?php endif; ?>

            <?php if ($solicitud['archivo_certificacion']): ?>
                <a href="../../../uploads/compras_fijas/<?= urlencode($solicitud['archivo_certificacion']) ?>" target="_blank" class="archivo-link">
                    <i class="bi bi-paperclip"></i> Certificación
                </a>
            <?php endif; ?>

            <?php if (!empty($solicitud['archivo_factura'])): ?>
                <a href="../../../uploads/compras_fijas/<?= urlencode($solicitud['archivo_factura']) ?>" target="_blank" class="archivo-link">
                    <i class="bi bi-paperclip"></i> Factura Siggo
                </a>
            <?php endif; ?>

            <?php if ($solicitud['categoria'] === 'Cuenta de cobro' && !empty($solicitud['archivo_seguridad_social'])): ?>
                <a href="../../../uploads/compras_fijas/<?= urlencode($solicitud['archivo_seguridad_social']) ?>" target="_blank" class="archivo-link">
                    <i class="bi bi-paperclip"></i> Seguridad Social
                </a>
            <?php endif; ?>

            <?php if ($solicitud['categoria'] === 'Cuenta de cobro' && !empty($solicitud['archivo_bitacora'])): ?>
                <a href="../../../uploads/compras_fijas/<?= urlencode($solicitud['archivo_bitacora']) ?>" target="_blank" class="archivo-link">
                    <i class="bi bi-paperclip"></i> Bitácora
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" autocomplete="off" id="formContabilidad">
        <div class="form-group mt-3">
            <label class="label-erp" for="factura_siggo">Factura Siggo (PDF/JPG/PNG):</label>
            <input type="file" name="factura_siggo" id="factura_siggo" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="form-group mt-3">
            <label class="label-erp" for="observaciones">Observaciones (opcional si aprueba, obligatorias si rechaza):</label>
            <textarea name="observaciones" id="observaciones" class="form-control" maxlength="500" rows="2"></textarea>
        </div>

        <div class="acciones-aprobar">
            <button type="submit" name="accion" value="aprobar" class="btn-aprobar" id="btnAprobar">
                <i class="bi bi-check2-circle"></i> Aprobar
            </button>
            <button type="submit" name="accion" value="rechazar" class="btn-rechazar" id="btnRechazar" formnovalidate>
                <i class="bi bi-x-circle"></i> Rechazar
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const facturaInput = document.getElementById('factura_siggo');
    const btnAprobar = document.getElementById('btnAprobar');
    const btnRechazar = document.getElementById('btnRechazar');

    btnAprobar.addEventListener('click', function () {
        facturaInput.setAttribute('required', 'required');
    });

    btnRechazar.addEventListener('click', function () {
        facturaInput.removeAttribute('required');
    });
});
</script>

</body>
</html>