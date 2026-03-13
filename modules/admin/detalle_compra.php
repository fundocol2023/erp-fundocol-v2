<?php
include '../../includes/navbar.php';
require_once '../../config/db.php';

// ================================
// 1️⃣ Validar ID recibido
// ================================
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "Solicitud no especificada.";
    exit;
}

// ================================
// 2️⃣ Consultar solicitud de compra
// ================================
$sql = "
SELECT 
    sc.*, 
    u.nombre AS solicitante_nombre, 
    u.email AS solicitante_email
FROM solicitudes_compra sc
INNER JOIN usuarios u ON sc.solicitante_id = u.id
WHERE sc.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo "Solicitud no encontrada.";
    exit;
}

// ================================
// 3️⃣ Archivos adjuntos
// ================================
$uploads_dir = '../../uploads/solicitudes_compra/';
$archivos = [
    'archivo_cotizacion1' => 'Cotización #1',
    'archivo_cotizacion2' => 'Cotización #2',
    'archivo_cotizacion3' => 'Cotización #3',
    'archivo_soporte' => 'Soporte adicional'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle Solicitud de Compra #<?= $solicitud['id'] ?></title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="admin-detalle-compra-page">
<div class="navbar-spacer"></div>

<div class="detalle-container">
    <div class="detalle-header">
        <h2><i class="bi bi-file-earmark-text"></i> Solicitud de Compra #<?= $solicitud['id'] ?></h2>
        <span class="estado <?= strtolower($solicitud['estado']) ?>"><?= strtoupper($solicitud['estado']) ?></span>
    </div>

    <div class="detalle-section">
        <p><span class="label">Solicitante:</span> <span class="valor"><?= htmlspecialchars($solicitud['solicitante_nombre']) ?></span></p>
        <p><span class="label">Fecha:</span> <span class="valor"><?= date('Y-m-d', strtotime($solicitud['fecha'])) ?></span></p>
        <p><span class="label">Necesidad:</span> <span class="valor"><?= htmlspecialchars($solicitud['necesidad']) ?></span></p>
        <p><span class="label">Proveedor:</span> <span class="valor"><?= htmlspecialchars($solicitud['proveedor']) ?></span></p>
        <?php if (!empty($solicitud['observaciones'])): ?>
            <p><span class="label">Observaciones:</span> <span class="valor"><?= htmlspecialchars($solicitud['observaciones']) ?></span></p>
        <?php endif; ?>
    </div>

    <div class="detalle-section">
        <h5><i class="bi bi-paperclip"></i> Archivos adjuntos</h5>
        <div class="file-list">
            <?php 
            foreach ($archivos as $campo => $nombre) {
                if (!empty($solicitud[$campo])) {
                    echo "<a href='{$uploads_dir}".urlencode($solicitud[$campo])."' target='_blank' class='file-link'>
                            <i class='bi bi-file-earmark-arrow-down'></i> {$nombre}
                          </a>";
                }
            }
            if (empty(array_filter(array_map(fn($k) => $solicitud[$k] ?? null, array_keys($archivos))))) {
                echo "<p class='no-archivos-adjuntos'>No hay archivos adjuntos.</p>";
            }
            ?>
        </div>
    </div>

    <!-- 🔧 Seguimiento -->
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
                } elseif (strpos($estado_actual, 'aprobado') !== false) {
                    echo "<div class='tracking-step'><i class='bi bi-check-circle-fill text-success'></i> <b>{$etiqueta}:</b> Aprobado</div>";
                } else {
                    echo "<div class='tracking-step'><i class='bi bi-hourglass-split text-warning'></i> <b>{$etiqueta}:</b> En proceso</div>";
                }
            } else {
                echo "<div class='tracking-step'><i class='bi bi-hourglass text-secondary'></i> <b>{$etiqueta}:</b> Pendiente</div>";
            }
        }
        ?>
    </div>

    <a href="lista_compras.php" class="volver"><i class="bi bi-arrow-left"></i> Volver al panel</a>
</div>
</body>
</html>

