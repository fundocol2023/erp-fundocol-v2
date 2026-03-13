<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../../login.php'); exit(); }
$rol = $_SESSION['usuario_rol'];
if ($rol != 6) { echo "Sin permisos."; exit(); } // SOLO pagos

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo "<div class='alert alert-danger'>ID inválido</div>"; exit(); }

// Traer solicitud
$sql = "SELECT sc.*, c.proveedor, c.precio AS cot_precio, c.archivo AS cot_archivo, u.nombre AS solicitante, u.email AS email_solicitante
        FROM solicitudes_compra sc
        JOIN solicitudes_cotizacion_cotizaciones c ON sc.cotizacion_aprobada_id = c.id
        JOIN usuarios u ON sc.solicitante_id = u.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$solicitud) { echo "<div class='alert alert-danger'>Solicitud no encontrada.</div>"; exit(); }

// Productos asociados
$sql = "SELECT * FROM solicitudes_compra_productos WHERE solicitud_compra_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago de Solicitud de Compra | ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        .archivo-link { display:block; margin-bottom:7px;}
    </style>
</head>
<body class="compras-pagos-page">
    <?php include '../../includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="erp-card">
            <div class="solicitud-header">
                <div class="erp-title mb-1">
                    <i class="bi bi-credit-card"></i>
                    Pago Solicitud de Compra #<?= $solicitud['id'] ?>
                </div>
                <div class="solicitud-subtitle">Gestión y confirmación de pago</div>
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
                        <span class="meta-label">Proveedor</span>
                        <span class="meta-value"><?= htmlspecialchars($solicitud['proveedor']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Necesidad</span>
                        <span class="meta-value"><?= htmlspecialchars($solicitud['necesidad']) ?></span>
                    </div>
                    <div class="meta-item meta-item-wide">
                        <span class="meta-label">Observaciones</span>
                        <span class="meta-value"><?= htmlspecialchars($solicitud['observaciones_compra']) ?></span>
                    </div>
                </div>
            </div>

            <div class="solicitud-section">
                <div class="section-title"><i class="bi bi-paperclip"></i> Documentos</div>
                <div class="adjuntos-list">
                    <a class="btn-adjunto" href="uploads/cotizaciones/<?= urlencode($solicitud['cot_archivo']) ?>" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Cotización aprobada
                    </a>
                    <?php if ($solicitud['certificacion_bancaria']): ?>
                    <a class="btn-adjunto" href="../../uploads/certificaciones/<?= urlencode($solicitud['certificacion_bancaria']) ?>" target="_blank">
                        <i class="bi bi-bank"></i> Certificación Bancaria
                    </a>
                    <?php endif; ?>
                    <?php if ($solicitud['rut_proveedor']): ?>
                    <a class="btn-adjunto" href="../../uploads/rut/<?= urlencode($solicitud['rut_proveedor']) ?>" target="_blank">
                        <i class="bi bi-person-vcard"></i> RUT Proveedor
                    </a>
                    <?php endif; ?>
                    <?php if ($solicitud['factura_siggo']): ?>
                    <a class="btn-adjunto" href="../../uploads/facturas_siggo/<?= urlencode($solicitud['factura_siggo']) ?>" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Factura SIGGO
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="solicitud-section">
                <div class="section-title"><i class="bi bi-box-seam"></i> Productos</div>
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
            <!-- Formulario para subir soporte y confirmar pago -->
<form method="post" action="procesar_pago.php" enctype="multipart/form-data" class="solicitud-acciones">
    <input type="hidden" name="id_solicitud" value="<?= $solicitud['id'] ?>">
    <div class="mb-3">
        <label for="soporte_pago" class="data-label">Adjuntar soporte de pago (PDF, JPG, PNG):</label>
        <input type="file" name="soporte_pago" id="soporte_pago" accept=".pdf,.jpg,.jpeg,.png" required>
    </div>
    <br>
    <div class="mb-3">
        <label for="comentario_pagos" class="data-label">Comentario de pago (opcional):</label>
        <textarea name="comentario_pagos" id="comentario_pagos" rows="3" class="form-control" maxlength="300" placeholder="Escriba aquí el motivo de demora o información relevante (si aplica)"></textarea>
    </div>
    <br>
    <div class="acciones-pago solicitud-actions">
        <button type="submit" name="confirmar_pago" class="btn-erp btn-erp-lg">
            <i class="bi bi-check-circle"></i> Confirmar Pago y Notificar
        </button>
    </div>
</form>

        </div>
    </div>
</body>
</html>


