<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/mailer.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../../login.php'); exit(); }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo "<div class='alert alert-danger'>ID inválido</div>"; exit(); }

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'];

// 1. Traer la solicitud de compra + cotización aprobada
$sql = "SELECT sc.*, c.proveedor, c.precio AS cot_precio, c.archivo AS cot_archivo, 
               u.nombre AS solicitante, sc.certificacion_bancaria, sc.rut_proveedor, sc.factura_siggo
        FROM solicitudes_compra sc
        JOIN solicitudes_cotizacion_cotizaciones c ON sc.cotizacion_aprobada_id = c.id
        JOIN usuarios u ON sc.solicitante_id = u.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) { echo "<div class='alert alert-danger'>Solicitud no encontrada.</div>"; exit(); }

// 2. Productos asociados
$sql = "SELECT * FROM solicitudes_compra_productos WHERE solicitud_compra_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Proceso de subir factura
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['factura_proveedor'])) {
    if ($_FILES['factura_proveedor']['error'] === UPLOAD_ERR_OK) {
        $dir = "../../uploads/facturas_proveedor/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $ext = pathinfo($_FILES['factura_proveedor']['name'], PATHINFO_EXTENSION);
        $nombre_archivo = "factura_proveedor_" . $id . "_" . time() . "." . $ext;
        $ruta = $dir . $nombre_archivo;

        if (move_uploaded_file($_FILES['factura_proveedor']['tmp_name'], $ruta)) {
            // 1. Guarda el nombre en la base de datos Y cambia el estado
            $sql = "UPDATE solicitudes_compra SET factura_proveedor = ?, estado = 'factura_subida' WHERE id = ?";
            $pdo->prepare($sql)->execute([$nombre_archivo, $id]);

            // 2. INSERTAR EN TRACKING
            $sql = "INSERT INTO solicitudes_tracking (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
                    VALUES (?, ?, ?, NOW(), ?)";
            $pdo->prepare($sql)->execute([
                $id,
                'factura_subida',
                $usuario_id,
                'Factura del proveedor subida por el usuario'
            ]);

            $msg = "<div class='alert alert-success mt-3'>Factura del proveedor subida exitosamente.</div>";

            // Notifica a contabilidad por correo
            $tabla = "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse; font-size:1rem; margin-top:18px;'>
                <tr>
                    <th>Producto</th><th>Cantidad</th><th>Descripción</th><th>Precio Unitario</th><th>Precio Total</th>
                </tr>";
            foreach ($productos as $prod) {
                $tabla .= "<tr>
                    <td>" . htmlspecialchars($prod['nombre']) . "</td>
                    <td align='center'>" . intval($prod['cantidad']) . "</td>
                    <td>" . htmlspecialchars($prod['descripcion']) . "</td>
                    <td align='right'>$" . number_format($prod['precio_unitario'], 0, ',', '.') . "</td>
                    <td align='right'>$" . number_format($prod['precio_total'], 0, ',', '.') . "</td>
                </tr>";
            }
            $tabla .= "</table>";

            $body = '
            <div style="font-family:\'Segoe UI\', Arial, sans-serif; background:#f5f7fb; padding:30px 0;">
                <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:18px;box-shadow:0 8px 32px #2176ff16;padding:36px 35px 28px 35px;">
                    <div style="display:flex;align-items:center;gap:11px;margin-bottom:24px;">
                        <img src="https://i.imgur.com/2d33SUe.png" width="40" height="40" alt="ERP Fundocol" style="border-radius:50%;background:#2176ff18;">
                        <span style="font-weight:700;font-size:1.21rem;color:#2176ff;">ERP Fundocol</span>
                    </div>
                    <div style="font-size:1.33rem;font-weight:700;letter-spacing:-.5px;color:#183357;margin-bottom:18px;">
                        Se ha subido la factura del proveedor
                    </div>
                    <div style="font-size:1.08rem;color:#24374e;margin-bottom:14px;">
                        <b>Solicitud:</b> #' . $solicitud['id'] . '<br>
                        <b>Solicitante:</b> ' . htmlspecialchars($solicitud['solicitante']) . '<br>
                        <b>Proyecto/Oficina:</b> ' . htmlspecialchars($solicitud['proyecto_oficina']) . '<br>
                        <b>Proveedor:</b> ' . htmlspecialchars($solicitud['proveedor']) . '<br>
                        <b>Necesidad:</b> ' . htmlspecialchars($solicitud['necesidad']) . '<br>
                        <b>Precio cotización:</b> $' . number_format($solicitud['cot_precio'], 0, ',', '.') . '
                    </div>
                    <div style="background:#f6fafd;border-radius:11px;padding:14px 18px 8px 18px;margin-bottom:18px;">
                        <b>Productos:</b> ' . $tabla . '
                    </div>
                    <div style="font-size:1.08rem;margin-bottom:10px"><b>Documentos adjuntos:</b></div>
                    <ul style="font-size:1.08rem;margin-bottom:18px;">
                        <li><a href="https://erp.fundocol.org/modules/compras/uploads/cotizaciones/' . urlencode($solicitud['cot_archivo']) . '" target="_blank">Cotización Aprobada</a></li>' .
                        ($solicitud['certificacion_bancaria'] ? '<li><a href="https://erp.fundocol.org/uploads/certificados_bancarios/' . urlencode($solicitud['certificado_bancario']) . '" target="_blank">Certificado Bancario</a></li>' : '') .
                        ($solicitud['rut'] ? '<li><a href="https://erp.fundocol.org/uploads/ruts/' . urlencode($solicitud['rut']) . '" target="_blank">RUT</a></li>' : '') .
                        ($solicitud['factura_siggo'] ? '<li><a href="https://erp.fundocol.org/uploads/facturas_siggo/' . urlencode($solicitud['factura_siggo']) . '" target="_blank">Factura SIGGO</a></li>' : '') .
                        '<li><a href="https://erp.fundocol.org/uploads/facturas_proveedor/' . urlencode($nombre_archivo) . '" target="_blank">Factura Proveedor</a></li>
                    </ul>
                    <div style="margin-top:27px;font-size:0.98rem;color:#93a3ba;">
                        No responda este correo. ERP Fundocol.
                    </div>
                </div>
            </div>';

            enviarCorreoFundocol(
                'analista.contable@fundocol.org',
                'Contabilidad Fundocol',
                'Se subió la factura del proveedor para la solicitud #' . $solicitud['id'],
                $body
            );
        } else {
            $msg = "<div class='alert alert-danger mt-3'>Hubo un error al subir el archivo. Intente de nuevo.</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger mt-3'>Debe seleccionar un archivo PDF o imagen válido.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir factura proveedor | ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="compras-subir-factura-page">
    <?php include '../../includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="erp-card">
            <div class="erp-title mb-3">
                <i class="bi bi-upload"></i> Subir factura del proveedor (Solicitud #<?= $solicitud['id'] ?>)
            </div>
            <div class="data-block">
                <div><span class="data-label">Solicitante:</span> <?= htmlspecialchars($solicitud['solicitante']) ?></div>
                <div><span class="data-label">Fecha:</span> <?= htmlspecialchars($solicitud['fecha']) ?></div>
                <div><span class="data-label">Proyecto/Oficina:</span> <?= htmlspecialchars($solicitud['proyecto_oficina']) ?></div>
                <div><span class="data-label">Proveedor:</span> <?= htmlspecialchars($solicitud['proveedor']) ?></div>
                <div><span class="data-label">Necesidad:</span> <?= htmlspecialchars($solicitud['necesidad']) ?></div>
            </div>
            <!-- Productos -->
            <div class="mb-4">
                <strong class="data-label">Productos solicitados:</strong>
                <div class="table-responsive mt-2">
                    <table class="erp-prod-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Cantidad</th>
                                <th>Descripción</th>
                                <th>Precio Unitario</th>
                                <th>Precio Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $i => $prod): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                <td><?= htmlspecialchars($prod['cantidad']) ?></td>
                                <td><?= htmlspecialchars($prod['descripcion']) ?></td>
                                <td>$<?= number_format($prod['precio_unitario'],0,',','.') ?></td>
                                <td>$<?= number_format($prod['precio_total'],0,',','.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Documentos -->
            <div class="doc-list mb-3">
                <strong class="data-label">Documentos de la solicitud:</strong>
                <ul>
                    <li><a href="uploads/cotizaciones/<?= urlencode($solicitud['cot_archivo']) ?>" target="_blank">Cotización Aprobada</a></li>
                    <?php if ($solicitud['certificacion_bancaria']): ?>
                    <li><a href="../../uploads/certificaciones/<?= urlencode($solicitud['certificacion_bancaria']) ?>" target="_blank">Certificado Bancario</a></li>
                    <?php endif; ?>
                    <?php if ($solicitud['rut_proveedor']): ?>
                    <li><a href="../../uploads/rut/<?= urlencode($solicitud['rut_proveedor']) ?>" target="_blank">RUT</a></li>
                    <?php endif; ?>
                    <?php if ($solicitud['factura_siggo']): ?>
                    <li><a href="uploads/facturas_siggo/<?= urlencode($solicitud['factura_siggo']) ?>" target="_blank">Factura SIGGO</a></li>
                    <?php endif; ?>
                    <?php if ($solicitud['factura_proveedor']): ?>
                    <li><a href="../../uploads/facturas_proveedor/<?= urlencode($solicitud['factura_proveedor']) ?>" target="_blank">Factura Proveedor (subida)</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <!-- Subida de factura -->
            <?php if (empty($solicitud['factura_proveedor'])): ?>
            <form method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="data-label" for="factura_proveedor">Adjuntar Factura del Proveedor (PDF/JPG/PNG):</label>
                    <input type="file" name="factura_proveedor" id="factura_proveedor" required accept=".pdf,.jpg,.jpeg,.png" class="form-control factura-proveedor-input">
                </div>
                <button type="submit" class="btn-upload"><i class="bi bi-upload"></i> Subir factura</button>
            </form>
            <?php endif; ?>
            <?= $msg ?>
        </div>
    </div>
</body>
</html>

