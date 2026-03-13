<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../../login.php'); exit(); }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo "<div class='alert alert-danger'>ID inválido</div>"; exit(); }

// Obtener datos de la compra fija
$sql = "SELECT cfc.*, u.nombre AS solicitante
        FROM compras_fijas_consorcios cfc
        INNER JOIN usuarios u ON cfc.solicitante_id = u.id
        WHERE cfc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) { echo "<div class='alert alert-danger'>Solicitud no encontrada.</div>"; exit(); }

$estado = strtolower($solicitud['estado']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compra Fija Consorcio #<?= $solicitud['id'] ?> | ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- estilos movidos a assets/css/style.css -->
</head>
<body class="admin-detalle-fija-consorcio-page">
    <?php include '../../includes/navbar.php'; ?>

    <div class="erp-card">
        <div class="erp-title">
            <i class="bi bi-file-earmark-text"></i>
            Compra Fija Consorcio #<?= $solicitud['id'] ?>
            <span class="estado-badge <?= htmlspecialchars($estado) ?>">
                <?= strtoupper($solicitud['estado']) ?>
            </span>
        </div>

        <div class="data-block">
            <div><span class="data-label">Solicitante:</span> <?= htmlspecialchars($solicitud['solicitante']) ?></div>
            <div><span class="data-label">Consorcio:</span> <?= htmlspecialchars($solicitud['consorcio']) ?></div>
            <div><span class="data-label">Fecha:</span> <?= htmlspecialchars($solicitud['fecha']) ?></div>
            <div><span class="data-label">Categoría:</span> <?= htmlspecialchars($solicitud['categoria']) ?></div>
            <div><span class="data-label">Proveedor:</span> <?= htmlspecialchars($solicitud['proveedor']) ?></div>
            <div><span class="data-label">Monto:</span> $<?= number_format($solicitud['monto'],0,',','.') ?></div>
        </div>

        <!-- Archivos adjuntos -->
        <h6><i class="bi bi-paperclip"></i> Archivos adjuntos</h6>
        <div class="adjuntos">
            <?php
            $base = "https://erp.fundocol.org/uploads/compras_fijas_consorcios/";

            if (!empty($solicitud['archivo_cotizacion'])) {
                echo "<a href='{$base}".urlencode($solicitud['archivo_cotizacion'])."' target='_blank' class='btn-archivo'><i class='bi bi-file-earmark-pdf'></i> Cotización</a>";
            }
            if (!empty($solicitud['archivo_rut'])) {
                echo "<a href='{$base}".urlencode($solicitud['archivo_rut'])."' target='_blank' class='btn-archivo'><i class='bi bi-file-earmark-pdf'></i> RUT</a>";
            }
            if (!empty($solicitud['archivo_certificacion'])) {
                echo "<a href='{$base}".urlencode($solicitud['archivo_certificacion'])."' target='_blank' class='btn-archivo'><i class='bi bi-file-earmark-pdf'></i> Certificación Bancaria</a>";
            }
            if (!empty($solicitud['soporte_pago'])) {
                echo "<a href='{$base}".urlencode($solicitud['soporte_pago'])."' target='_blank' class='btn-archivo'><i class='bi bi-file-earmark-pdf'></i> Factura SIGGO</a>";
            }
            if (!empty($solicitud['factura_proveedor'])) {
                echo "<a href='{$base}".urlencode($solicitud['factura_proveedor'])."' target='_blank' class='btn-archivo'><i class='bi bi-file-earmark-pdf'></i> Comprobante de Pago</a>";
            }
            ?>
        </div>

        <!-- Seguimiento -->
        <div class="seguimiento">
            <h5><i class="bi bi-clipboard-check"></i> Seguimiento del proceso</h5>

            <div class="seguimiento-item">
                <i class="bi bi-check-circle-fill text-success"></i> <strong>Presupuesto:</strong> Aprobado
            </div>
            <div class="seguimiento-item">
                <i class="bi bi-check-circle-fill text-success"></i> <strong>Dirección:</strong> Aprobado
            </div>
            <div class="seguimiento-item">
                <i class="bi bi-check-circle-fill text-success"></i> <strong>Contabilidad:</strong> Aprobado
            </div>

            <?php if ($estado === 'aprobado_pagos'): ?>
                <div class="seguimiento-item">
                    <i class="bi bi-check-circle-fill text-success"></i> <strong>Pagos:</strong> Aprobado
                </div>
            <?php else: ?>
                <div class="seguimiento-item">
                    <i class="bi bi-hourglass-split text-warning"></i> <strong>Pagos:</strong> En proceso
                </div>
            <?php endif; ?>
        </div>

        <a href="index.php" class="btn-volver">
            <i class="bi bi-arrow-left"></i> Volver al panel
        </a>
    </div>
</body>
</html>



