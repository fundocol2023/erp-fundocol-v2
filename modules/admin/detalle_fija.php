<?php
include '../../includes/navbar.php';
require_once '../../config/db.php';

// ================================
// 1️⃣ Validación de ID
// ================================
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "Solicitud no especificada.";
    exit;
}

// ================================
// 2️⃣ Consulta principal de la compra fija
// ================================
$sql = "
SELECT 
    cf.*, 
    u.nombre AS solicitante_nombre, 
    u.email AS solicitante_email
FROM compras_fijas cf
INNER JOIN usuarios u ON cf.solicitante_id = u.id
WHERE cf.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo "Solicitud no encontrada.";
    exit;
}

// ================================
// 3️⃣ Configuración base de archivos
// ================================
$uploads_dir = '../../uploads/compras_fijas/';
$archivos = [
    'archivo_cotizacion' => 'Cotización',
    'archivo_rut' => 'RUT',
    'archivo_certificacion' => 'Certificación bancaria',
    'archivo_factura' => 'Factura Siggo',
    'archivo_comprobante_pago' => 'Comprobante de Pago'
];

// ================================
// 4️⃣ Configuración de observaciones
// ================================
$comentarios = [
    [
        'titulo' => 'Observaciones del solicitante',
        'campo'  => 'observaciones',
        'clase'  => 'solicitante',
        'icono'  => 'bi-person-lines-fill'
    ],
    [
        'titulo' => 'Observaciones Presupuesto',
        'campo'  => 'observaciones_presupuesto',
        'clase'  => 'presupuesto',
        'icono'  => 'bi-cash-coin'
    ],
    [
        'titulo' => 'Observaciones Dirección',
        'campo'  => 'observaciones_direccion',
        'clase'  => 'direccion',
        'icono'  => 'bi-person-badge'
    ],
    [
        'titulo' => 'Observaciones Contabilidad',
        'campo'  => 'observaciones_contabilidad',
        'clase'  => 'contabilidad',
        'icono'  => 'bi-calculator'
    ],
    [
        'titulo' => 'Observaciones Pagos',
        'campo'  => 'observaciones_pagos',
        'clase'  => 'pagos',
        'icono'  => 'bi-credit-card'
    ]
];

$hay_comentarios = false;
foreach ($comentarios as $c) {
    if (!empty($solicitud[$c['campo']])) {
        $hay_comentarios = true;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Compra Fija #<?= intval($solicitud['id']) ?></title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="admin-detalle-fija-page">
<div class="navbar-spacer"></div>

<div class="detalle-container">
    <div class="detalle-header">
        <h2><i class="bi bi-file-earmark-text"></i> Compra Fija #<?= intval($solicitud['id']) ?></h2>
        <span class="estado <?= htmlspecialchars(strtolower($solicitud['estado'])) ?>">
            <?= htmlspecialchars(strtoupper($solicitud['estado'])) ?>
        </span>
    </div>

    <div class="detalle-section">
        <p><span class="label">Solicitante:</span> <span class="valor"><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></span></p>
        <p><span class="label">Fecha de Solicitud:</span> <span class="valor"><?= !empty($solicitud['fecha_solicitud']) ? date('Y-m-d', strtotime($solicitud['fecha_solicitud'])) : '' ?></span></p>
        <p><span class="label">Categoría:</span> <span class="valor"><?= htmlspecialchars($solicitud['categoria']) ?></span></p>
        <p><span class="label">Proveedor:</span> <span class="valor"><?= htmlspecialchars($solicitud['proveedor']) ?></span></p>
        <p><span class="label">Monto:</span> <span class="valor">$<?= number_format((float)$solicitud['monto'], 0, ',', '.') ?></span></p>

        <?php if (!empty($solicitud['descripcion'])): ?>
            <p><span class="label">Descripción:</span> <span class="valor"><?= htmlspecialchars($solicitud['descripcion']) ?></span></p>
        <?php endif; ?>
    </div>

    <?php if ($hay_comentarios): ?>
        <div class="detalle-section">
            <h5><i class="bi bi-chat-left-text"></i> Observaciones del proceso</h5>

            <div class="comentarios-box">
                <?php foreach ($comentarios as $c): ?>
                    <?php if (!empty($solicitud[$c['campo']])): ?>
                        <div class="comentario-area <?= htmlspecialchars($c['clase']) ?>">
                            <div class="comentario-titulo">
                                <i class="bi <?= htmlspecialchars($c['icono']) ?>"></i>
                                <?= htmlspecialchars($c['titulo']) ?>
                            </div>
                            <div class="comentario-texto">
                                <?= nl2br(htmlspecialchars($solicitud[$c['campo']])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="detalle-section">
        <h5><i class="bi bi-paperclip"></i> Archivos adjuntos</h5>
        <div class="file-list">
            <?php 
            foreach ($archivos as $campo => $nombre) {
                if (!empty($solicitud[$campo])) {
                    echo "<a href='{$uploads_dir}" . urlencode($solicitud[$campo]) . "' target='_blank' class='file-link'>
                            <i class='bi bi-file-earmark-arrow-down'></i> " . htmlspecialchars($nombre) . "
                          </a>";
                }
            }

            if (empty(array_filter(array_map(fn($k) => $solicitud[$k] ?? null, array_keys($archivos))))) {
                echo "<p style='color:#64748b;'>No hay archivos adjuntos.</p>";
            }
            ?>
        </div>
    </div>

    <div class="detalle-section tracking">
        <h5><i class="bi bi-clock-history"></i> Seguimiento del proceso</h5>
        <?php
        $etapas = ['presupuesto', 'direccion', 'contabilidad', 'pagos'];
        $estado_actual = strtolower($solicitud['estado']);
        $indice_actual = null;

        foreach ($etapas as $i => $etapa) {
            if (strpos($estado_actual, $etapa) !== false) {
                $indice_actual = $i;
                break;
            }
        }

        foreach ($etapas as $i => $etapa) {
            $etiqueta = ucfirst($etapa);

            if ($indice_actual === null) {
                echo "<div class='tracking-step'><i class='bi bi-hourglass text-secondary'></i> <b>{$etiqueta}:</b> Pendiente</div>";
            } elseif ($i < $indice_actual) {
                echo "<div class='tracking-step'><i class='bi bi-check-circle-fill text-success'></i> <b>{$etiqueta}:</b> Aprobado</div>";
            } elseif ($i === $indice_actual) {
                if (strpos($estado_actual, 'rechazado') !== false) {
                    echo "<div class='tracking-step'><i class='bi bi-x-circle-fill text-danger'></i> <b>{$etiqueta}:</b> Rechazado</div>";
                } else {
                    echo "<div class='tracking-step'><i class='bi bi-hourglass-split text-warning'></i> <b>{$etiqueta}:</b> En proceso</div>";
                }
            } else {
                echo "<div class='tracking-step'><i class='bi bi-hourglass text-secondary'></i> <b>{$etiqueta}:</b> Pendiente</div>";
            }
        }
        ?>
    </div>

    <a href="index.php" class="volver"><i class="bi bi-arrow-left"></i> Volver al panel</a>
</div>
</body>
</html>

