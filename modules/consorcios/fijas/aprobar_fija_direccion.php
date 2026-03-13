<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';
require_once '../../../includes/mailer.php';

session_start();

// Solo Direccion (rol 3)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 3) {
    header('Location: ../../login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "Solicitud no especificada.";
    exit;
}

// Traer solicitud (incluye observaciones de presupuesto)
$stmt = $pdo->prepare("
    SELECT 
        cf.*,
        cf.observaciones_presupuesto,
        u.nombre AS solicitante_nombre,
        u.email AS solicitante_email
    FROM compras_fijas_consorcios cf
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


/* =========================================
   PROCESAR FORMULARIO
   ========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');

    /* ===============================
       APROBAR
       =============================== */
    if ($accion === 'aprobar') {

        $pdo->prepare("
            UPDATE compras_fijas_consorcios 
            SET estado = 'aprobado_direccion',
                aprobador_direccion_id = ?,
                fecha_aprob_direccion = NOW(),
                observaciones_direccion = ?
            WHERE id = ?
        ")->execute([
            $_SESSION['usuario_id'],
            $observaciones,
            $id
        ]);

        /* ===============================
           CORREO A CONTABILIDAD
           =============================== */
        $correo_contabilidad = "analista.consorcios@fundocol.org";
        $nombre_contabilidad = "Contabilidad Fundocol";
        $asunto = "Compra fija de consorcio pendiente de aprobacion (Contabilidad)";

        $link = "https://erp.fundocol.org/modules/consorcios/fijas/aprobar_fija_contabilidad.php?id={$id}";

        $mensaje_html = "
        <div style='font-family:Segoe UI,Arial,sans-serif;background:#f6fafd;padding:24px;'>
          <div style='max-width:650px;margin:0 auto;background:#ffffff;
                      border-radius:18px;box-shadow:0 8px 28px #2176ff22;
                      padding:30px 32px;'>

            <h2 style='color:#0ea5e9;margin-top:0;'>Compra fija aprobada por Direccion</h2>

            <p>
              La siguiente solicitud fue <b>aprobada por Direccion</b>
              y queda pendiente de revision por <b>Contabilidad</b>.
            </p>

            <hr>

            <h3>Informacion de la solicitud</h3>
            <p><b>ID:</b> {$solicitud['id']}</p>
            <p><b>Consorcio / Proyecto:</b> ".htmlspecialchars($solicitud['consorcio'])."</p>
            <p><b>Categoria:</b> ".htmlspecialchars($solicitud['categoria'])."</p>
            <p><b>Proveedor:</b> ".htmlspecialchars($solicitud['proveedor'])."</p>
            <p><b>Monto:</b> $".number_format($solicitud['monto'],0,',','.')."</p>
            <p><b>Descripcion:</b><br>".nl2br(htmlspecialchars($solicitud['descripcion']))."</p>

            ".(!empty($solicitud['observaciones_solicitante']) ? "
            <p><b>Observaciones del solicitante:</b><br>
            ".nl2br(htmlspecialchars($solicitud['observaciones_solicitante']))."</p>" : "")."

            ".(!empty($observaciones) ? "
            <p><b>Observaciones de Direccion:</b><br>
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
                Revisar y aprobar en ERP Fundocol
              </a>
            </div>

            <p style='margin-top:30px;font-size:0.9rem;color:#94a3b8;'>
              Este es un mensaje automatico del ERP Fundocol.
            </p>

          </div>
        </div>";

        /* ===============================
           ADJUNTOS
           =============================== */
        $uploads_dir = '../../../uploads/compras_fijas_consorcios/';
        $adjuntos = [];

        if ($solicitud['archivo_cotizacion']) {
            $adjuntos[] = $uploads_dir.$solicitud['archivo_cotizacion'];
        }
        if ($solicitud['archivo_rut']) {
            $adjuntos[] = $uploads_dir.$solicitud['archivo_rut'];
        }
        if ($solicitud['archivo_certificacion']) {
            $adjuntos[] = $uploads_dir.$solicitud['archivo_certificacion'];
        }

        // SOLO SI ES CUENTA DE COBRO
        if ($solicitud['categoria'] === 'Cuenta de cobro') {
            if ($solicitud['archivo_seguridad_social']) {
                $adjuntos[] = $uploads_dir.$solicitud['archivo_seguridad_social'];
            }
            if ($solicitud['archivo_bitacora']) {
                $adjuntos[] = $uploads_dir.$solicitud['archivo_bitacora'];
            }
        }

        enviarCorreoFundocol(
            $correo_contabilidad,
            $nombre_contabilidad,
            $asunto,
            $mensaje_html,
            $adjuntos
        );

        header("Location: https://erp.fundocol.org/modules/pendientes/index.php?tab=comprasfijas");
        exit;
    }

    /* ===============================
       RECHAZAR
       =============================== */
    if ($accion === 'rechazar') {

        if (!$observaciones) {
            $mensaje = "Debes indicar el motivo del rechazo.";
        } else {

            $pdo->prepare("
                UPDATE compras_fijas_consorcios 
                SET estado = 'rechazado_direccion',
                    aprobador_direccion_id = ?,
                    fecha_aprob_direccion = NOW(),
                    observaciones_direccion = ?
                WHERE id = ?
            ")->execute([
                $_SESSION['usuario_id'],
                $observaciones,
                $id
            ]);

            if (!empty($solicitud['solicitante_email'])) {

                $asunto = "Compra fija rechazada por Direccion";

                $mensaje_html = "
                <div style='font-family:Segoe UI,Arial,sans-serif;background:#fff5f5;padding:24px;'>
                  <div style='max-width:650px;margin:0 auto;background:#ffffff;
                              border-radius:18px;box-shadow:0 8px 28px #ef444422;
                              padding:30px 32px;'>

                    <h2 style='color:#dc2626;margin-top:0;'>Compra fija rechazada</h2>

                    <p>
                      Tu solicitud fue <b>rechazada por Direccion</b>.
                    </p>

                    <hr>

                    <p><b>ID:</b> {$solicitud['id']}</p>
                    <p><b>Consorcio:</b> ".htmlspecialchars($solicitud['consorcio'])."</p>
                    <p><b>Categoria:</b> ".htmlspecialchars($solicitud['categoria'])."</p>
                    <p><b>Proveedor:</b> ".htmlspecialchars($solicitud['proveedor'])."</p>
                    <p><b>Monto:</b> $".number_format($solicitud['monto'],0,',','.')."</p>

                    <p><b>Motivo:</b><br>".nl2br(htmlspecialchars($observaciones))."</p>

                  </div>
                </div>";

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
    <title>Revisar Compra Fija Consorcio (Direccion)</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="consorcios-fijas-aprobar-direccion-page">
<div class="navbar-spacer"></div>
<div class="aprobacion-box">
    <div class="aprob-title"><i class="bi bi-file-earmark-check"></i> Revision Compra Fija Consorcio (Direccion)</div>
    <?php if($mensaje): ?><div class="msg-error"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <div class="aprob-section">
        <div class="aprob-section-title"><i class="bi bi-info-circle"></i> Resumen</div>
        <div class="aprobacion-grid">
            <div class="label-erp">Consorcio / Proyecto:<span class="valor-erp"><?= htmlspecialchars($solicitud['consorcio']) ?></span></div>
            <div class="label-erp">Solicitante:<span class="valor-erp"><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></span></div>
            <div class="label-erp">Categoria:<span class="valor-erp"><?= htmlspecialchars($solicitud['categoria']) ?></span></div>
            <div class="label-erp">Proveedor:<span class="valor-erp"><?= htmlspecialchars($solicitud['proveedor']) ?></span></div>
            <div class="label-erp">Monto:<span class="valor-erp">$<?= number_format($solicitud['monto'], 0, ',', '.') ?></span></div>
            <div class="label-erp">Descripcion:<span class="valor-erp"><?= htmlspecialchars($solicitud['descripcion']) ?></span></div>
            <?php if($solicitud['observaciones_solicitante']): ?>
            <div class="label-erp">Observaciones del solicitante:<span class="valor-erp"><?= htmlspecialchars($solicitud['observaciones_solicitante']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($solicitud['observaciones_presupuesto'])): ?>
            <div class="label-erp observaciones-block">
                Observaciones de Presupuesto:
                <div class="observaciones-box">
                    <?= nl2br(htmlspecialchars($solicitud['observaciones_presupuesto'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="aprob-section">
        <div class="aprob-section-title"><i class="bi bi-paperclip"></i> Soportes</div>
        <div class="archivos-row">

    <?php if ($solicitud['archivo_cotizacion']): ?>
        <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_cotizacion']) ?>"
           target="_blank" class="archivo-link">
           <i class="bi bi-paperclip"></i> Cotizacion
        </a>
    <?php endif; ?>

    <?php if ($solicitud['archivo_rut']): ?>
        <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_rut']) ?>"
           target="_blank" class="archivo-link">
           <i class="bi bi-paperclip"></i> RUT
        </a>
    <?php endif; ?>

    <?php if ($solicitud['archivo_certificacion']): ?>
        <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_certificacion']) ?>"
           target="_blank" class="archivo-link">
           <i class="bi bi-paperclip"></i> Certificacion
        </a>
    <?php endif; ?>

    <?php if ($solicitud['categoria'] === 'Cuenta de cobro'): ?>

        <?php if (!empty($solicitud['archivo_seguridad_social'])): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_seguridad_social']) ?>"
               target="_blank" class="archivo-link">
               <i class="bi bi-paperclip"></i> Seguridad social
            </a>
        <?php endif; ?>

        <?php if (!empty($solicitud['archivo_bitacora'])): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_bitacora']) ?>"
               target="_blank" class="archivo-link">
               <i class="bi bi-paperclip"></i> Bitacora
            </a>
        <?php endif; ?>

    <?php endif; ?>

        </div>
    </div>


    <form method="post" autocomplete="off">
        <div class="form-group">
            <label class="label-erp" for="observaciones">Observaciones (obligatorias si rechaza, opcionales si aprueba):</label>
            <textarea name="observaciones" id="observaciones" class="form-control" maxlength="500" rows="2"></textarea>
        </div>
        <div class="acciones-aprobar">
            <button type="submit" name="accion" value="aprobar" class="btn-aprobar"><i class="bi bi-check2-circle"></i> Aprobar</button>
            <button type="submit" name="accion" value="rechazar" class="btn-rechazar" onclick="return confirm('Seguro que deseas rechazar esta solicitud?')"><i class="bi bi-x-circle"></i> Rechazar</button>
        </div>
    </form>
</div>
</body>
</html>
