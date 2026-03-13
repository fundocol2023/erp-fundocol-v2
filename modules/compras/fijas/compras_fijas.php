<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$rol = $_SESSION['usuario_rol'] ?? null;

/* ================================
   FILTROS
================================ */
$filtro_estado = $_GET['estado'] ?? '';
$filtro_mes = $_GET['mes'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$export_excel  = $_GET['export'] ?? '';

// Categorías disponibles
$categorias = $pdo->query("SELECT DISTINCT categoria FROM compras_fijas WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria ASC")
                  ->fetchAll(PDO::FETCH_COLUMN);

/* ================================
   CONSULTA BASE
================================ */
$sql = "
    SELECT cf.*, u.nombre AS solicitante
    FROM compras_fijas cf
    INNER JOIN usuarios u ON cf.solicitante_id = u.id
    WHERE 1=1
";
$params = [];

if ($filtro_estado !== '') {
    $sql .= " AND cf.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_mes !== '') {
    $sql .= " AND DATE_FORMAT(cf.fecha_solicitud, '%Y-%m') = ?";
    $params[] = $filtro_mes;
}

if ($filtro_categoria !== '') {
    $sql .= " AND cf.categoria = ?";
    $params[] = $filtro_categoria;
}

$sql .= " ORDER BY cf.fecha_solicitud DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_registros = count($compras);
$total_monto = 0;
foreach ($compras as $c) {
    $total_monto += (float)$c['monto'];
}

/* ================================
   EXPORTAR EXCEL
================================ */
if ($export_excel === 'excel') {

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=compras_fijas_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";

    echo "<table border='1'>
        <tr style='background:#e0f2fe;font-weight:bold;'>
            <th>Fecha</th>
            <th>Categoria</th>
            <th>Proveedor</th>
            <th>Monto</th>
            <th>Solicitante</th>
            <th>Estado</th>
        </tr>";

    $total = 0;

    foreach ($compras as $c) {
        $total += $c['monto'];
        echo "<tr>
            <td>".date('Y-m-d', strtotime($c['fecha_solicitud']))."</td>
            <td>".htmlspecialchars($c['categoria'])."</td>
            <td>".htmlspecialchars($c['proveedor'])."</td>
            <td>".number_format($c['monto'],0,',','.')."</td>
            <td>".htmlspecialchars($c['solicitante'])."</td>
            <td>".strtoupper($c['estado'])."</td>
        </tr>";
    }

    echo "
        <tr style='font-weight:bold;background:#f1f5f9;'>
            <td colspan='3' align='right'>TOTAL</td>
            <td>".number_format($total,0,',','.')."</td>
            <td colspan='2'></td>
        </tr>
    </table>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compras Fijas</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>
<body class="compras-fijas-index-page">

<div class="navbar-spacer"></div>

<div class="compras-header">
    <div class="titulo-compras">Compras Fijas</div>
    <div class="compras-subtitle">Listado y seguimiento de compras fijas</div>
</div>

<div class="acciones-compras">
    <button class="btn-agregar" onclick="window.location.href='agregar.php'">
        <i class="bi bi-plus-circle"></i> Agregar compra fija
    </button>

    <button class="btn-imprimir" onclick="window.print()">
        <i class="bi bi-printer"></i> Imprimir
    </button>

    <a class="btn-imprimir" href="?estado=<?= urlencode($filtro_estado) ?>&mes=<?= urlencode($filtro_mes) ?>&categoria=<?= urlencode($filtro_categoria) ?>&export=excel">
        <i class="bi bi-file-earmark-excel"></i> Excel
    </a>
</div>

<div class="resumen-compras">
    <div class="resumen-card">
        <span class="resumen-label">Registros</span>
        <span class="resumen-value"><?= $total_registros ?></span>
    </div>
    <div class="resumen-card">
        <span class="resumen-label">Monto total</span>
        <span class="resumen-value">$<?= number_format($total_monto, 0, ',', '.') ?></span>
    </div>
</div>

<div class="filtros-wrap">
    <form method="GET" class="filtros-form">
        <div class="filtros-grid">
            <div class="filtro-item">
                <label>Mes</label>
                <input type="month" name="mes" value="<?= htmlspecialchars($filtro_mes) ?>" class="form-control filtros-input">
            </div>
            <div class="filtro-item">
                <label>Estado</label>
                <select name="estado" class="form-select filtros-select">
                    <option value="">-- Todos --</option>
                    <option value="pendiente_presupuesto" <?= $filtro_estado=='pendiente_presupuesto'?'selected':'' ?>>Pendiente Presupuesto</option>
                    <option value="aprobado_presupuesto" <?= $filtro_estado=='aprobado_presupuesto'?'selected':'' ?>>Aprobado Presupuesto</option>
                    <option value="aprobado_direccion" <?= $filtro_estado=='aprobado_direccion'?'selected':'' ?>>Aprobado Dirección</option>
                    <option value="aprobado_contabilidad" <?= $filtro_estado=='aprobado_contabilidad'?'selected':'' ?>>Aprobado Contabilidad</option>
                    <option value="aprobado_pagos" <?= $filtro_estado=='aprobado_pagos'?'selected':'' ?>>Aprobado Pagos</option>
                </select>
            </div>
            <div class="filtro-item">
                <label>Categoría</label>
                <select name="categoria" class="form-select filtros-select">
                    <option value="">-- Todas --</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filtro_categoria===$cat?'selected':'' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="filtros-actions">
            <button type="submit" class="btn-filtrar">
                <i class="bi bi-funnel"></i> Filtrar
            </button>
            <a class="btn-limpiar" href="compras_fijas.php">
                <i class="bi bi-arrow-clockwise"></i> Limpiar
            </a>
        </div>
    </form>
</div>

<div class="tabla-fijas">
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Categoría</th>
            <th>Proveedor</th>
            <th>Monto</th>
            <th>Solicitante</th>
            <th>Cotización</th>
            <th>RUT</th>
            <th>Certificación</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>

    <tbody>
    <?php foreach ($compras as $c): ?>
        <tr>
            <td><?= date('Y-m-d', strtotime($c['fecha_solicitud'])) ?></td>
            <td><?= htmlspecialchars($c['categoria']) ?></td>
            <td><?= htmlspecialchars($c['proveedor']) ?></td>
            <td>$<?= number_format($c['monto'], 0, ',', '.') ?></td>
            <td><?= htmlspecialchars($c['solicitante']) ?></td>

            <td>
                <?php if ($c['archivo_cotizacion']): ?>
                    <a href="../../../uploads/compras_fijas/<?= $c['archivo_cotizacion'] ?>" target="_blank" class="archivo-link">
                        <i class="bi bi-file-earmark"></i>
                    </a>
                <?php endif; ?>
            </td>

            <td>
                <?php if ($c['archivo_rut']): ?>
                    <a href="../../../uploads/compras_fijas/<?= $c['archivo_rut'] ?>" target="_blank" class="archivo-link">
                        <i class="bi bi-file-earmark"></i>
                    </a>
                <?php endif; ?>
            </td>

            <td>
                <?php if ($c['archivo_certificacion']): ?>
                    <a href="../../../uploads/compras_fijas/<?= $c['archivo_certificacion'] ?>" target="_blank" class="archivo-link">
                        <i class="bi bi-file-earmark"></i>
                    </a>
                <?php endif; ?>
            </td>

            <td>
                <?php $class = strtolower(str_replace(" ", "_", $c['estado'])); ?>
                <span class="estado-label <?= $class ?>">
                    <?= strtoupper($c['estado']) ?>
                </span>
            </td>

            <td>
                <!-- VER -->
                <a href="detalle.php?id=<?= $c['id'] ?>" class="archivo-link" title="Ver detalle">
                    <i class="bi bi-eye"></i>
                </a>

                <!-- SOLO SISTEMAS (ROL 7) -->
                <?php if ($rol == 7): ?>
                    <a href="eliminar.php?id=<?= $c['id'] ?>"
                       class="btn-eliminar"
                       title="Eliminar registro"
                       onclick="return confirm('⚠️ Esta acción elimina el registro definitivamente. ¿Desea continuar?')">
                        <i class="bi bi-trash"></i>
                    </a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

</body>
</html>
