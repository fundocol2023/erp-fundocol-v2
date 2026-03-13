<?php
session_start();

require_once '../../../config/db.php';
require_once '../../../includes/mailer.php';
include '../../../includes/navbar.php';

// ✅ Presupuesto (rol 4) y Ingeniero Civil (rol 13)
if (!isset($_SESSION['usuario_id']) || !in_array(($_SESSION['usuario_rol'] ?? 0), [4, 13])) {
    header('Location: ../../login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo "Solicitud no especificada."; exit; }

// Traer solicitud
$stmt = $pdo->prepare("
    SELECT cf.*, u.nombre AS solicitante_nombre, u.email AS solicitante_email
    FROM compras_fijas_consorcios cf
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

    /* ===============================
       APROBAR
       =============================== */
    if ($accion == 'aprobar') {

        $pdo->prepare("
            UPDATE compras_fijas_consorcios 
            SET estado = 'aprobado_presupuesto',
                aprobador_presupuesto_id = ?,
                fecha_aprob_presupuesto = NOW(),
                observaciones_presupuesto = ?
            WHERE id = ?
        ")->execute([
            $_SESSION['usuario_id'],
            $observaciones,
            $id
        ]);

        /* ===============================
           CORREO A DIRECCION
           =============================== */
        $correo_direccion = "direccion@fundocol.org";
        $nombre_direccion = "Direccion Fundocol";
        $asunto = "Compra Fija de Consorcio pendiente de aprobacion (Direccion)";

        $link = "https://erp.fundocol.org/modules/consorcios/fijas/aprobar_fija_direccion.php?id={$id}";

        $mensaje_html = "
        <div style='font-family:Segoe UI,Arial,sans-serif;background:#f6fafd;padding:24px;'>
          <div style='max-width:650px;margin:0 auto;background:#ffffff;
                      border-radius:18px;box-shadow:0 8px 28px #2176ff22;
                      padding:30px 32px;'>

            <h2 style='color:#0ea5e9;margin-top:0;'>
                Compra Fija de Consorcio aprobada por Presupuesto
            </h2>

            <p>
              La siguiente solicitud fue <b>aprobada por Presupuesto</b>
              y queda pendiente de revision por <b>Direccion</b>.
            </p>

            <hr>

            <h3>Informacion de la solicitud</h3>
            <p><b>ID:</b> {$solicitud['id']}</p>
            <p><b>Consorcio:</b> ".htmlspecialchars($solicitud['consorcio'])."</p>
            <p><b>Categoria:</b> ".htmlspecialchars($solicitud['categoria'])."</p>
            <p><b>Proveedor:</b> ".htmlspecialchars($solicitud['proveedor'])."</p>
            <p><b>Monto:</b> $".number_format($solicitud['monto'],0,',','.')."</p>
            <p><b>Descripcion:</b><br>".nl2br(htmlspecialchars($solicitud['descripcion']))."</p>

            ".($solicitud['categoria'] === 'Cuenta de cobro' ? "
                <p style='color:#0369a1;font-weight:600;'>
                    Esta solicitud corresponde a una CUENTA DE COBRO
                </p>
            " : "")."

            ".(!empty($observaciones) ? "
            <p><b>Observaciones de Presupuesto:</b><br>
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
            $correo_direccion,
            $nombre_direccion,
            $asunto,
            $mensaje_html,
            $adjuntos
        );

        header("Location: compras_fijas.php?msg=presu_ok");
        exit;
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
                SET estado = 'rechazado_presupuesto',
                    aprobador_presupuesto_id = ?,
                    fecha_aprob_presupuesto = NOW(),
                    observaciones_presupuesto = ?
                WHERE id = ?
            ")->execute([
                $_SESSION['usuario_id'],
                $observaciones,
                $id
            ]);

            if (!empty($solicitud['solicitante_email'])) {

                $asunto = "Compra Fija de Consorcio rechazada por Presupuesto";

                $mensaje_html = "
                <div style='font-family:Segoe UI,Arial,sans-serif;background:#fff5f5;padding:24px;'>
                  <div style='max-width:650px;margin:0 auto;background:#ffffff;
                              border-radius:18px;box-shadow:0 8px 28px #ef444422;
                              padding:30px 32px;'>

                    <h2 style='color:#dc2626;margin-top:0;'>Compra Fija rechazada</h2>

                    <p>Tu solicitud fue <b>rechazada por Presupuesto</b>.</p>

                    <hr>

                    <p><b>ID:</b> {$solicitud['id']}</p>
                    <p><b>Consorcio:</b> ".htmlspecialchars($solicitud['consorcio'])."</p>
                    <p><b>Categoria:</b> ".htmlspecialchars($solicitud['categoria'])."</p>
                    <p><b>Proveedor:</b> ".htmlspecialchars($solicitud['proveedor'])."</p>
                    <p><b>Monto:</b> $".number_format($solicitud['monto'],0,',','.')."</p>

                    <p><b>Motivo del rechazo:</b><br>
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

            header("Location: compras_fijas.php?msg=presu_rechazo");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisar Compra Fija Consorcio (Presupuesto)</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="consorcios-fijas-aprobar-presupuesto-page">
<div class="navbar-spacer"></div>
<div class="aprobacion-box">
    <div class="aprob-title"><i class="bi bi-file-earmark-check"></i> Revision de Compra Fija Consorcio (Presupuesto)</div>

    <?php if($mensaje): ?>
        <div class="msg-error"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="aprob-section">
        <div class="aprob-section-title"><i class="bi bi-info-circle"></i> Resumen</div>
        <div class="aprobacion-grid">
    <div class="label-erp">Consorcio / Proyecto:
        <span class="valor-erp"><?= htmlspecialchars($solicitud['consorcio']) ?></span>
    </div>

    <div class="label-erp">Solicitante:
        <span class="valor-erp"><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></span>
    </div>

    <div class="label-erp">Fecha de solicitud:
        <span class="valor-erp"><?= date('Y-m-d H:i', strtotime($solicitud['fecha'])) ?></span>
    </div>

    <div class="label-erp">Categoria:
        <span class="valor-erp"><?= htmlspecialchars($solicitud['categoria']) ?></span>
    </div>

    <div class="label-erp">Proveedor:
        <span class="valor-erp"><?= htmlspecialchars($solicitud['proveedor']) ?></span>
    </div>

    <div class="label-erp">Monto:
        <span class="valor-erp">$<?= number_format($solicitud['monto'], 0, ',', '.') ?></span>
    </div>

    <div class="label-erp">Descripcion:
        <span class="valor-erp"><?= htmlspecialchars($solicitud['descripcion']) ?></span>
    </div>

    <?php if($solicitud['observaciones_solicitante']): ?>
    <div class="label-erp">Observaciones del solicitante:
        <span class="valor-erp"><?= htmlspecialchars($solicitud['observaciones_solicitante']) ?></span>
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

    <?php if ($solicitud['categoria'] === 'Cuenta de cobro'): ?>

        <?php if ($solicitud['archivo_seguridad_social']): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_seguridad_social']) ?>" target="_blank" class="archivo-link">
                <i class="bi bi-paperclip"></i> Seguridad Social
            </a>
        <?php endif; ?>

        <?php if ($solicitud['archivo_bitacora']): ?>
            <a href="../../../uploads/compras_fijas_consorcios/<?= urlencode($solicitud['archivo_bitacora']) ?>" target="_blank" class="archivo-link">
                <i class="bi bi-paperclip"></i> Bitacora
            </a>
        <?php endif; ?>

    <?php endif; ?>
</div>

    </div>


    <form method="post" autocomplete="off">
        <div class="form-group">
            <label class="label-erp" for="observaciones">Observaciones (si rechaza, obligatorio):</label>
            <textarea name="observaciones" id="observaciones" class="form-control" maxlength="500" rows="2"></textarea>
        </div>

        <div class="acciones-aprobar">
            <button type="submit" name="accion" value="aprobar" class="btn-aprobar">
                <i class="bi bi-check2-circle"></i> Aprobar
            </button>

            <button type="submit" name="accion" value="rechazar" class="btn-rechazar" 
                onclick="return confirm('Seguro que deseas rechazar esta solicitud?')">
                <i class="bi bi-x-circle"></i> Rechazar
            </button>
        </div>
    </form>
</div>
</body>
</html>


