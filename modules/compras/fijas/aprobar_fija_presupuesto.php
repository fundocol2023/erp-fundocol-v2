<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';
require_once '../../../includes/mailer.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar sesion y rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 4) {
    header('Location: ../../login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo "Solicitud no especificada."; exit; }

// Obtener solicitud
$stmt = $pdo->prepare("
    SELECT cf.*, u.nombre AS solicitante_nombre, u.email AS solicitante_email
    FROM compras_fijas cf
    INNER JOIN usuarios u ON cf.solicitante_id = u.id
    WHERE cf.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) { echo "Solicitud no encontrada."; exit; }

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $accion = $_POST['accion'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');

    /* ======================================================
       APROBAR
    ====================================================== */
    if ($accion == 'aprobar') {

        $pdo->prepare("
            UPDATE compras_fijas
            SET estado = 'aprobado_presupuesto',
                fecha_aprobacion_presupuesto = NOW(),
                observaciones_presupuesto = ?
            WHERE id = ?
        ")->execute([
            $observaciones ?: null,
            $id
        ]);

        /* ===============================
           CORREO A DIRECCION
        =============================== */

        $correo_direccion = "direccion@fundocol.org";
        $nombre_direccion = "Direccion Fundocol";

        $asunto = "Compra Fija pendiente de aprobacion (Direccion)";
        $link = "https://erp.fundocol.org/modules/pendientes/index.php?tab=comprasfijas";

        $obs_html = '';
        if (!empty($observaciones)) {
            $obs_html = "
            <div style='background:#fff7e6;border:1px solid #ffe1a7;border-radius:10px;padding:12px;margin-top:15px;'>
                <b>Observaciones de Presupuesto:</b><br>
                ".nl2br(htmlspecialchars($observaciones))."
            </div>";
        }

        $mensaje_html = "
        <div style='font-family:Arial, sans-serif; color:#1f2937; line-height:1.6; max-width:600px;'>

            <h2 style='color:#0ea5e9; margin-bottom:15px;'>
                Nueva Compra Fija para aprobar en Dirección
            </h2>

            <div style='background:#f1f5f9; padding:15px 20px; border-radius:12px; margin-bottom:20px;'>
                <p><b>Categoria:</b> ".htmlspecialchars($solicitud['categoria'])."</p>
                <p><b>Proveedor:</b> ".htmlspecialchars($solicitud['proveedor'])."</p>
                <p><b>Monto:</b> $".number_format($solicitud['monto'], 0, ',', '.')."</p>
                <p><b>Descripcion:</b><br>".nl2br(htmlspecialchars($solicitud['descripcion']))."</p>
            </div>

            $obs_html

            <div style='text-align:center; margin:30px 0;'>
                <a href='$link'
                   style='background:#0ea5e9; color:#ffffff; padding:14px 32px;
                          font-size:16px; font-weight:700; text-decoration:none;
                          border-radius:10px; display:inline-block;'>
                    Revisar y aprobar en ERP Fundocol
                </a>
            </div>

            <p style='font-size:13px; color:#6b7280; text-align:center;'>
                Este es un mensaje automático del sistema ERP Fundocol.
            </p>

        </div>
        ";

        /* ===============================
           ADJUNTOS
        =============================== */

        $uploads_dir = '../../../uploads/compras_fijas/';
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

        if ($solicitud['categoria'] === 'Cuenta de cobro') {

            if (!empty($solicitud['archivo_seguridad_social'])) {
                $adjuntos[] = $uploads_dir . $solicitud['archivo_seguridad_social'];
            }

            if (!empty($solicitud['archivo_bitacora'])) {
                $adjuntos[] = $uploads_dir . $solicitud['archivo_bitacora'];
            }
        }

        enviarCorreoFundocol(
            $correo_direccion,
            $nombre_direccion,
            $asunto,
            $mensaje_html,
            $adjuntos
        );

        header("Location: https://erp.fundocol.org/modules/pendientes/index.php");
        exit;
    }

    /* ======================================================
       RECHAZAR
    ====================================================== */
    if ($accion == 'rechazar') {

        if (empty($observaciones)) {
            $mensaje = "Debes indicar el motivo del rechazo.";
        } else {

            $pdo->prepare("
                UPDATE compras_fijas
                SET estado = 'rechazado_presupuesto',
                    comentario_rechazo = ?,
                    usuario_rechazo = ?,
                    fecha_rechazo = NOW()
                WHERE id = ?
            ")->execute([
                $observaciones,
                $_SESSION['usuario_id'],
                $id
            ]);

            if (!empty($solicitud['solicitante_email'])) {

                $asunto = "Tu solicitud de compra fija fue rechazada por Presupuesto";

                $mensaje_html = "
                    <h2>Solicitud rechazada</h2>
                    <p>Tu solicitud de compra fija fue rechazada por Presupuesto.</p>
                    <p><b>Motivo:</b><br>".nl2br(htmlspecialchars($observaciones))."</p>
                    <br><small>ERP Fundocol</small>
                ";

                enviarCorreoFundocol(
                    $solicitud['solicitante_email'],
                    $solicitud['solicitante_nombre'],
                    $asunto,
                    $mensaje_html
                );
            }

            header("Location: /modules/pendientes/index.php?tab=comprasfijas");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisión Compra Fija (Presupuesto)</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="compras-fijas-aprobar-presupuesto-page">
<div class="navbar-spacer"></div>
<div class="aprobacion-box">
    <div class="aprob-title"><i class="bi bi-clipboard-check"></i> Revisión de Compra Fija (Presupuesto)</div>
    <?php if($mensaje): ?><div class="msg-error"><?= $mensaje ?></div><?php endif; ?>

    <div class="aprob-section">
        <div class="aprob-section-title"><i class="bi bi-info-circle"></i> Resumen</div>
        <div class="aprobacion-grid">
            <div class="label-erp">Solicitante:<span class="valor-erp"><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></span></div>
    <div class="label-erp">Categoría:<span class="valor-erp"><?= htmlspecialchars($solicitud['categoria']) ?></span></div>
    <div class="label-erp">Proveedor:<span class="valor-erp"><?= htmlspecialchars($solicitud['proveedor']) ?></span></div>
    <div class="label-erp">Monto:<span class="valor-erp">$<?= number_format($solicitud['monto'], 0, ',', '.') ?></span></div>
    <div class="label-erp">Descripción:<span class="valor-erp"><?= htmlspecialchars($solicitud['descripcion']) ?></span></div>
    <?php if($solicitud['observaciones']): ?>
        <div class="label-erp">Observaciones:<span class="valor-erp"><?= htmlspecialchars($solicitud['observaciones']) ?></span></div>
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


    <form method="post" autocomplete="off">
        <div class="form-group">
            <label class="label-erp" for="observaciones">Observaciones (si rechaza):</label>
            <textarea name="observaciones" id="observaciones" class="form-control" maxlength="500" rows="2"></textarea>
        </div>
        <div class="acciones-aprobar">
            <button type="submit" name="accion" value="aprobar" class="btn-aprobar"><i class="bi bi-check2-circle"></i> Aprobar</button>
            <button type="submit" name="accion" value="rechazar" class="btn-rechazar" onclick="return confirm('¿Seguro que deseas rechazar esta solicitud?')"><i class="bi bi-x-circle"></i> Rechazar</button>
        </div>
    </form>
</div>
</body>
</html>



