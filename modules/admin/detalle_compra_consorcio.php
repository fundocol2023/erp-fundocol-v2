<?php
include '../../includes/navbar.php';
require_once '../../config/db.php';

/* ================================
   1) Validar ID
================================ */
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "Solicitud no especificada.";
    exit;
}

/* ================================
   2) Consulta principal
================================ */
$sql = "
SELECT 
    scc.*,
    u.nombre AS solicitante_nombre,
    u.email AS solicitante_email,

    c.archivo   AS archivo_cotizacion_aprobada,
    c.proveedor AS proveedor_aprobado,
    c.precio    AS monto_aprobado

FROM solicitudes_compra_consorcios scc
INNER JOIN usuarios u 
    ON scc.solicitante_id = u.id
LEFT JOIN solicitudes_cotizacion_consorcios_cotizaciones c
    ON scc.cotizacion_aprobada_id = c.id
WHERE scc.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo "Solicitud no encontrada.";
    exit;
}

// Base URL para cotizaciones de consorcios
$cotizacion_base = '../../modules/consorcios/uploads/cotizaciones/';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Solicitud Compra Consorcio #<?= $solicitud['id'] ?></title>

<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="admin-detalle-compra-consorcio-page">
<div class="navbar-spacer"></div>

<div class="detalle-container">

    <div class="detalle-header">
        <h2>
            <i class="bi bi-file-earmark-text"></i>
            Solicitud Compra Consorcio #<?= $solicitud['id'] ?>
        </h2>
        <span class="estado <?= strtolower($solicitud['estado']) ?>">
            <?= strtoupper($solicitud['estado']) ?>
        </span>
    </div>

    <!-- INFO GENERAL -->
    <div class="detalle-section">
        <p><span class="label">Solicitante:</span> 
            <span class="valor"><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></span>
        </p>

        <p><span class="label">Consorcio:</span> 
            <span class="valor"><?= htmlspecialchars($solicitud['consorcio']) ?></span>
        </p>

        <p><span class="label">Fecha solicitud:</span> 
            <span class="valor"><?= date('Y-m-d', strtotime($solicitud['fecha'])) ?></span>
        </p>

        <p><span class="label">Necesidad:</span> 
            <span class="valor"><?= htmlspecialchars($solicitud['necesidad']) ?></span>
        </p>

        <p><span class="label">Proveedor aprobado:</span> 
            <span class="valor"><?= htmlspecialchars($solicitud['proveedor_aprobado'] ?? '-') ?></span>
        </p>

        <p><span class="label">Monto aprobado:</span> 
            <span class="valor">
                $<?= number_format($solicitud['monto_aprobado'] ?? 0,0,',','.') ?>
            </span>
        </p>
    </div>

    <!-- ARCHIVOS -->
    <div class="detalle-section">
        <h5><i class="bi bi-paperclip"></i> Archivos adjuntos</h5>

        <div class="file-list">

            <?php if (!empty($solicitud['archivo_cotizacion_aprobada'])): ?>
                <a class="file-link" target="_blank"
                   href="<?= $cotizacion_base . rawurlencode($solicitud['archivo_cotizacion_aprobada']) ?>">
                   <i class="bi bi-file-earmark-check"></i> Cotización aprobada
                </a>
            <?php endif; ?>

            <?php if (!empty($solicitud['rut_proveedor'])): ?>
                <a class="file-link" target="_blank"
                   href="../../uploads/rut/<?= rawurlencode($solicitud['rut_proveedor']) ?>">
                   <i class="bi bi-person-vcard"></i> RUT proveedor
                </a>
            <?php endif; ?>

            <?php if (!empty($solicitud['certificacion_bancaria'])): ?>
                <a class="file-link" target="_blank"
                   href="../../uploads/certificaciones/<?= rawurlencode($solicitud['certificacion_bancaria']) ?>">
                   <i class="bi bi-bank"></i> Certificación bancaria
                </a>
            <?php endif; ?>

            <?php if (!empty($solicitud['factura_siggo'])): ?>
                <a class="file-link" target="_blank"
                   href="../../uploads/facturas_siggo/<?= rawurlencode($solicitud['factura_siggo']) ?>">
                   <i class="bi bi-file-earmark-spreadsheet"></i> Factura SIGGO
                </a>
            <?php endif; ?>

            <?php if (!empty($solicitud['soporte_pago'])): ?>
                <a class="file-link" target="_blank"
                   href="../../uploads/soportes_pago/<?= rawurlencode($solicitud['soporte_pago']) ?>">
                   <i class="bi bi-receipt"></i> Comprobante de pago
                </a>
            <?php endif; ?>

            <?php if (
                empty($solicitud['archivo_cotizacion_aprobada']) &&
                empty($solicitud['rut_proveedor']) &&
                empty($solicitud['certificacion_bancaria']) &&
                empty($solicitud['factura_siggo']) &&
                empty($solicitud['soporte_pago'])
            ): ?>
                <span style="color:#64748b;">No hay archivos adjuntos</span>
            <?php endif; ?>

        </div>
    </div>

    <!-- COMENTARIOS DE PAGOS -->
    <?php if (!empty($solicitud['observaciones_pagos'])): ?>
    <div class="detalle-section">
        <h5><i class="bi bi-chat-left-text"></i> Comentarios de pagos</h5>
        <div class="comentario">
            <?= nl2br(htmlspecialchars($solicitud['observaciones_pagos'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- FECHAS DE APROBACION -->
    <div class="detalle-section">
        <h5><i class="bi bi-clock-history"></i> Fechas de aprobación</h5>

        <?php if (!empty($solicitud['fecha_aprob_presupuesto'])): ?>
            <p><b>Presupuesto:</b> <?= $solicitud['fecha_aprob_presupuesto'] ?></p>
        <?php endif; ?>

        <?php if (!empty($solicitud['fecha_aprob_direccion'])): ?>
            <p><b>Dirección:</b> <?= $solicitud['fecha_aprob_direccion'] ?></p>
        <?php endif; ?>

        <?php if (!empty($solicitud['fecha_aprob_contabilidad'])): ?>
            <p><b>Contabilidad:</b> <?= $solicitud['fecha_aprob_contabilidad'] ?></p>
        <?php endif; ?>

        <?php if (!empty($solicitud['fecha_aprob_pagos'])): ?>
            <p><b>Pagos:</b> <?= $solicitud['fecha_aprob_pagos'] ?></p>
        <?php endif; ?>
    </div>

    <a href="index.php" class="volver">
        <i class="bi bi-arrow-left"></i> Volver al panel
    </a>

</div>
</body>
</html>