<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';
require_once '../../../includes/mailer.php';

session_start();
// Solo Pagos (rol 6)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 6) {
    header('Location: ../../login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo "Solicitud no especificada."; exit; }

// Traer solicitud
$stmt = $pdo->prepare("
    SELECT 
        cf.*,
        cf.observaciones_presupuesto,
        cf.observaciones_direccion,
        cf.observaciones_contabilidad,
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

// Usaremos factura_proveedor para guardar el comprobante de pago
$comprobante_pago = $solicitud['factura_proveedor'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $accion = $_POST['accion'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $comprobante_pago = null;

    /* ===============================
       SUBIR COMPROBANTE DE PAGO
    =============================== */
    if ($accion == 'aprobar' && isset($_FILES['comprobante_pago']) && $_FILES['comprobante_pago']['error'] == 0) {

        $ext = strtolower(pathinfo($_FILES['comprobante_pago']['name'], PATHINFO_EXTENSION));
        $permitidas = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $permitidas)) {
            $mensaje = "Formato de archivo de comprobante no permitido.";
        } else {
            $uploads_dir = '../../../uploads/compras_fijas_consorcios/';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
            }

            $comprobante_pago = uniqid('comprobante_pago_') . '.' . $ext;
            move_uploaded_file($_FILES['comprobante_pago']['tmp_name'], $uploads_dir . $comprobante_pago);
        }
    }

    /* ===============================
       APROBAR (PAGO REALIZADO)
    =============================== */
    if ($accion == 'aprobar') {

        if (!$comprobante_pago) {
            $mensaje = "Debes adjuntar el comprobante de pago para finalizar.";
        } else {

            $pdo->prepare("UPDATE compras_fijas_consorcios 
                           SET estado = 'aprobado_pagos',
                               aprobador_pagos_id = ?,
                               fecha_aprob_pagos = NOW(),
                               observaciones_pagos = ?,
                               factura_proveedor = ?
                           WHERE id = ?")
                ->execute([$_SESSION['usuario_id'], $observaciones, $comprobante_pago, $id]);

            /* ===============================
               CORREO FINAL AL SOLICITANTE
            =============================== */
            if (!empty($solicitud['solicitante_email'])) {

                $asunto = "Compra fija de consorcio pagada";

                $mensaje_html = "
                <div style='font-family:Segoe UI,Arial,sans-serif;background:#f0fdf4;padding:24px;'>
                  <div style='max-width:650px;margin:0 auto;background:#ffffff;
                              border-radius:18px;box-shadow:0 8px 28px #22c55e33;
                              padding:30px 32px;'>

                    <h2 style='color:#16a34a;margin-top:0;'>Compra fija pagada</h2>

                    <p>
                      Te informamos que tu solicitud de compra fija fue <b>pagada y finalizada</b>
                      por el area de Pagos.
                    </p>

                    <hr>

                    <h3>Informacion de la solicitud</h3>
                    <p><b>ID:</b> {$solicitud['id']}</p>
                    <p><b>Consorcio / Proyecto:</b> ".htmlspecialchars($solicitud['consorcio'])."</p>
                    <p><b>Categoria:</b> ".htmlspecialchars($solicitud['categoria'])."</p>
                    <p><b>Proveedor:</b> ".htmlspecialchars($solicitud['proveedor'])."</p>
                    <p><b>Monto:</b> $".number_format($solicitud['monto'],0,',','.')."</p>
                    <p><b>Descripcion:</b><br>".nl2br(htmlspecialchars($solicitud['descripcion']))."</p>

                    ".(!empty($observaciones) ? "
                    <p><b>Observaciones de Pagos:</b><br>
                    ".nl2br(htmlspecialchars($observaciones))."</p>" : "")."

                    <p style='margin-top:25px;font-size:0.95rem;color:#64748b;'>
                      Se adjunta el comprobante de pago para tu soporte.
                    </p>

                    <p style='margin-top:15px;font-size:0.9rem;color:#94a3b8;'>
                      Este es un mensaje automatico del ERP Fundocol.
                    </p>

                  </div>
                </div>";

                $uploads_dir = '../../../uploads/compras_fijas_consorcios/';
                $adjuntos = [];
                if ($comprobante_pago) {
                    $adjuntos[] = $uploads_dir . $comprobante_pago;
                }

                enviarCorreoFundocol(
                    $solicitud['solicitante_email'],
                    $solicitud['solicitante_nombre'],
                    $asunto,
                    $mensaje_html,
                    $adjuntos
                );
            }

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

            $pdo->prepare("UPDATE compras_fijas_consorcios 
                           SET estado = 'rechazado_pagos',
                               aprobador_pagos_id = ?,
                               fecha_aprob_pagos = NOW(),
                               observaciones_pagos = ?
                           WHERE id = ?")
                ->execute([$_SESSION['usuario_id'], $observaciones, $id]);

            /* ===============================
               CORREO AL SOLICITANTE
            =============================== */
            if (!empty($solicitud['solicitante_email'])) {

                $asunto = "Compra fija de consorcio rechazada por Pagos";

                $mensaje_html = "
                <div style='font-family:Segoe UI,Arial,sans-serif;background:#fff5f5;padding:24px;'>
                  <div style='max-width:650px;margin:0 auto;background:#ffffff;
                              border-radius:18px;box-shadow:0 8px 28px #ef444422;
                              padding:30px 32px;'>

                    <h2 style='color:#dc2626;margin-top:0;'>Compra fija rechazada</h2>

                    <p>
                      Tu solicitud de compra fija fue <b>rechazada por el area de Pagos</b>.
                    </p>

                    <hr>

                    <h3>Informacion de la solicitud</h3>
                    <p><b>ID:</b> {$solicitud['id']}</p>
                    <p><b>Consorcio / Proyecto:</b> ".htmlspecialchars($solicitud['consorcio'])."</p>
                    <p><b>Categoria:</b> ".htmlspecialchars($solicitud['categoria'])."</p>
                    <p><b>Proveedor:</b> ".htmlspecialchars($solicitud['proveedor'])."</p>
                    <p><b>Monto:</b> $".number_format($solicitud['monto'],0,',','.')."</p>

                    <p><b>Motivo del rechazo:</b><br>
                    ".nl2br(htmlspecialchars($observaciones))."</p>

                    <p style='margin-top:25px;font-size:0.9rem;color:#64748b;'>
                      Puedes comunicarte con el area correspondiente para mas informacion.
                    </p>

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
    <title>Pago Compra Fija Consorcio (Pagos)</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="consorcios-fijas-aprobar-pagos-page">
<div class="navbar-spacer"></div>
<div class="aprobacion-box">
    <div class="aprob-title"><i class="bi bi-cash-coin"></i> Pago de Compra Fija de Consorcio</div>
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
            <?php if (!empty($solicitud['observaciones_contabilidad'])): ?>
            <div class="label-erp observaciones-block observaciones-direccion">
                Observaciones de Contabilidad:
                <div class="observaciones-box observaciones-box-contabilidad">
                    <?= nl2br(htmlspecialchars($solicitud['observaciones_contabilidad'])) ?>
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
                <i class="bi bi-paperclip"></i> Cotización
            </a>
        <?php endif; ?>
        <?php if ($solicitud['archivo_rut']): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_rut']) ?>" target="_blank" class="archivo-link">
                <i class="bi bi-paperclip"></i> RUT
            </a>
        <?php endif; ?>
        <?php if ($solicitud['archivo_certificacion']): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_certificacion']) ?>" target="_blank" class="archivo-link">
                <i class="bi bi-paperclip"></i> Certificación
            </a>
        <?php endif; ?>
        <?php if ($solicitud['soporte_pago']): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['soporte_pago']) ?>" target="_blank" class="archivo-link">
                <i class="bi bi-paperclip"></i> Factura Siggo
            </a>
        <?php endif; ?>
        <?php if ($solicitud['factura_proveedor']): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['factura_proveedor']) ?>" target="_blank" class="archivo-link">
                <i class="bi bi-paperclip"></i> Comprobante de pago
            </a>
        <?php endif; ?>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="form-group">
            <label class="label-erp" for="comprobante_pago">Comprobante de pago (PDF/JPG/PNG):</label>
            <input 
                type="file" 
                name="comprobante_pago" 
                id="comprobante_pago" 
                class="form-control" 
                accept=".pdf,.jpg,.jpeg,.png"
                <?= $solicitud['factura_proveedor'] ? "" : "required" ?>
            >
        </div>
        <div class="form-group">
            <label class="label-erp" for="observaciones">Observaciones (si rechaza, obligatorio):</label>
            <textarea name="observaciones" id="observaciones" class="form-control" maxlength="500" rows="2"></textarea>
        </div>
        <div class="acciones-aprobar">
            <button type="submit" name="accion" value="aprobar" class="btn-aprobar">
                <i class="bi bi-check2-circle"></i> Confirmar Pago
            </button>
            <button type="submit" name="accion" value="rechazar" class="btn-rechazar" onclick="return confirm('¿Seguro que deseas rechazar esta solicitud?')">
                <i class="bi bi-x-circle"></i> Rechazar
            </button>
        </div>
    </form>
</div>
</body>
</html>
