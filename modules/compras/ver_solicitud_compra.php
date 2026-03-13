<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../../login.php'); exit(); }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo "<div class='alert alert-danger'>ID inválido</div>"; exit(); }

$rol = $_SESSION['usuario_rol'];

// 1. Traer la solicitud
$sql = "SELECT sc.*, 
               c.proveedor, 
               c.precio AS cot_precio, 
               c.archivo AS cot_archivo, 
               u.nombre AS solicitante
        FROM solicitudes_compra sc
        JOIN solicitudes_cotizacion_cotizaciones c ON sc.cotizacion_aprobada_id = c.id
        JOIN usuarios u ON sc.solicitante_id = u.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) { echo "<div class='alert alert-danger'>Solicitud no encontrada.</div>"; exit(); }

// 2. Traer productos
$sql = "SELECT * FROM solicitudes_compra_productos WHERE solicitud_compra_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Comentario de rechazo
$comentario_rechazo = $solicitud['comentario_rechazo'] ?? '';

// Adjuntos
$certificacion = $solicitud['certificacion_bancaria'] ?? null;
$rut = $solicitud['rut_proveedor'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ver Solicitud de Compra | ERP Fundocol</title>

<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<script>
function showRechazo() {
    document.getElementById('comentario_rechazo').classList.add('show-rechazo');
    document.getElementById('btn_aprobar').disabled = true;
}
function hideRechazo() {
    document.getElementById('comentario_rechazo').classList.remove('show-rechazo');
    document.getElementById('btn_aprobar').disabled = false;
}
</script>

</head>
<body class="compras-ver-solicitud-page">

<?php include '../../includes/navbar.php'; ?>

<div class="container-fluid">
<div class="erp-card">

    <div class="solicitud-header">
        <div class="erp-title mb-1">
            <i class="bi bi-file-earmark-text"></i>
            Solicitud de Compra #<?= $solicitud['id'] ?>
        </div>
        <div class="solicitud-subtitle">Revisión y aprobación de compra</div>
    </div>

    <div class="solicitud-section">
        <div class="section-title"><i class="bi bi-info-circle"></i> Resumen</div>
        <div class="solicitud-meta-grid">
            <div class="meta-item">
                <span class="meta-label">Solicitante</span>
                <span class="meta-value"><?= htmlspecialchars($solicitud['solicitante']) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Fecha</span>
                <span class="meta-value"><?= htmlspecialchars($solicitud['fecha']) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Proyecto/Oficina</span>
                <span class="meta-value"><?= htmlspecialchars($solicitud['proyecto_oficina']) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Necesidad</span>
                <span class="meta-value"><?= htmlspecialchars($solicitud['necesidad']) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Proveedor</span>
                <span class="meta-value"><?= htmlspecialchars($solicitud['proveedor']) ?></span>
            </div>
            <div class="meta-item meta-item-wide">
                <span class="meta-label">Observaciones</span>
                <span class="meta-value"><?= htmlspecialchars($solicitud['observaciones_compra']) ?></span>
            </div>
        </div>
    </div>

    <!-- Adjuntos -->
    <div class="solicitud-section">
        <div class="section-title"><i class="bi bi-paperclip"></i> Soportes</div>
        <div class="adjuntos-list">
            <?php if ($certificacion): ?>
                <a class="btn-adjunto" href="../../uploads/certificaciones/<?= urlencode($certificacion) ?>" target="_blank">
                    <i class="bi bi-bank"></i> Certificación Bancaria
                </a>
            <?php endif; ?>

            <?php if ($rut): ?>
                <a class="btn-adjunto" href="../../uploads/rut/<?= urlencode($rut) ?>" target="_blank">
                    <i class="bi bi-person-vcard"></i> RUT Proveedor
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Productos -->
    <div class="solicitud-section">
        <div class="section-title"><i class="bi bi-box-seam"></i> Productos solicitados</div>
        <div class="table-responsive">
            <table class="erp-prod-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Cantidad</th>
                        <th>Descripción</th>
                        <th>Precio</th>
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

    <!-- Cotización aprobada -->
    <div class="solicitud-section">
        <div class="section-title"><i class="bi bi-file-earmark-check"></i> Cotización aprobada</div>
        <div class="cotiz-block">
            <div>
                <div><b>Proveedor:</b> <?= htmlspecialchars($solicitud['proveedor']) ?></div>
                <div><b>Precio:</b> <span class="precio-aprobado">
                    <?= '$' . number_format($solicitud['cot_precio'],0,',','.') ?>
                </span></div>
            </div>
            <a href="uploads/cotizaciones/<?= urlencode($solicitud['cot_archivo']) ?>" target="_blank" class="btn-pdf-pill">
                <i class="bi bi-file-earmark-pdf"></i> Ver PDF Cotización
            </a>
        </div>
    </div>


    <!-- ========================= -->
    <!--      FORMULARIO ÚNICO    -->
    <!-- ========================= -->

    <form method="post" action="procesar_aprobacion_compra.php" enctype="multipart/form-data" class="solicitud-acciones">

        <input type="hidden" name="id_solicitud" value="<?= $solicitud['id'] ?>">

        <?php if ($rol == 5): ?>
        <div class="factura-siggo-wrap">
            <label class="data-label" for="factura_siggo">Adjuntar Factura SIGGO (PDF):</label>
            <input type="file" name="factura_siggo" id="factura_siggo"
                   accept=".pdf,.jpg,.png,.jpeg"
                   class="factura-siggo-input">
        </div>
        <?php endif; ?>

        <?php if($comentario_rechazo): ?>
            <div class="alert alert-danger mt-4 alert-rechazo">
                <strong>Motivo de rechazo:</strong> <?= htmlspecialchars($comentario_rechazo) ?>
            </div>
        <?php endif; ?>

        <div class="aprob-rechazo-btns solicitud-actions">
            <button type="submit" name="aprobar" class="btn-erp" id="btn_aprobar" onclick="hideRechazo()">
                <i class="bi bi-check-circle"></i> Aprobar
            </button>

            <button type="button" class="btn-erp btn-rechazar" onclick="showRechazo()">
                <i class="bi bi-x-circle"></i> Rechazar
            </button>
        </div>

        <div id="comentario_rechazo" class="comentario-rechazo mt-4">
            <label for="comentario" class="label-rechazo">Motivo de rechazo:</label>
            <textarea name="comentario" id="comentario" class="textarea-rechazo"
                      rows="3" placeholder="Explica el motivo del rechazo..."></textarea>

            <div class="rechazo-btns mt-2">
                <button type="submit" name="rechazar" class="btn-rechazar">
                    <i class="bi bi-x-circle"></i> Confirmar Rechazo
                </button>
                <button type="button" class="btn-cancelar" onclick="hideRechazo()">Cancelar</button>
            </div>
        </div>

    </form>

</div>
</div>

</body>
</html>




