<?php
session_start();
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$rol = $_SESSION['usuario_rol'] ?? null;

/* ================================
   1️⃣ Filtros seleccionados
================================ */
$filtro_estado    = $_GET['estado'] ?? '';
$filtro_mes       = $_GET['mes'] ?? '';
$filtro_consorcio = $_GET['consorcio'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$export_excel     = $_GET['export'] ?? '';

/* ================================
   2️⃣ Lista de consorcios NORMALIZADOS
================================ */
$sqlConsorcios = "
    SELECT 
        LOWER(
            REPLACE(
                REPLACE(
                    REPLACE(consorcio, 'bioexpo', ''),
                'consorcio', ''),
            'proyecto', '')
        ) AS consorcio_normalizado,
        MIN(consorcio) AS consorcio_visible
    FROM compras_fijas_consorcios
    GROUP BY consorcio_normalizado
    ORDER BY consorcio_visible ASC
";
$stmtCons = $pdo->prepare($sqlConsorcios);
$stmtCons->execute();
$consorcios_lista = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

/* ================================
   3️⃣ Lista de meses disponibles
================================ */
$sqlMeses = "
    SELECT DISTINCT DATE_FORMAT(fecha, '%Y-%m') AS mes
    FROM compras_fijas_consorcios
    ORDER BY mes DESC
";
$stmtMes = $pdo->prepare($sqlMeses);
$stmtMes->execute();
$meses_lista = $stmtMes->fetchAll(PDO::FETCH_COLUMN);

/* ================================
   3.5️⃣ Lista de categorías disponibles
================================ */
$categorias = $pdo->query("SELECT DISTINCT categoria FROM compras_fijas_consorcios WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria ASC")
                  ->fetchAll(PDO::FETCH_COLUMN);

/* ================================
   4️⃣ Consulta base
================================ */
$sql = "
    SELECT 
        cf.id, -- 🔥 ESTO ES LO QUE FALTABA
        cf.fecha,
        cf.consorcio,
        cf.categoria,
        cf.proveedor,
        cf.monto,
        cf.estado,
        cf.archivo_cotizacion,
        cf.archivo_rut,
        cf.archivo_certificacion,
        u.nombre AS solicitante
    FROM compras_fijas_consorcios cf
    INNER JOIN usuarios u ON cf.solicitante_id = u.id
    WHERE 1 = 1
";


$params = [];

/* ---- filtro estado ---- */
if ($filtro_estado !== '') {
    $sql .= " AND cf.estado = ?";
    $params[] = $filtro_estado;
}

/* ---- filtro mes ---- */
if ($filtro_mes !== '') {
    $sql .= " AND DATE_FORMAT(cf.fecha, '%Y-%m') = ?";
    $params[] = $filtro_mes;
}

/* ---- filtro categoria ---- */
if ($filtro_categoria !== '') {
    $sql .= " AND cf.categoria = ?";
    $params[] = $filtro_categoria;
}

/* ---- filtro consorcio NORMALIZADO ---- */
if ($filtro_consorcio !== '') {
    $sql .= "
        AND LOWER(
            REPLACE(
                REPLACE(
                    REPLACE(cf.consorcio, 'bioexpo', ''),
                'consorcio', ''),
            'proyecto', '')
        ) = ?
    ";
    $params[] = $filtro_consorcio;
}

$sql .= " ORDER BY cf.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_registros = count($compras);
$total_monto = 0;
foreach ($compras as $c) {
    $total_monto += (float)$c['monto'];
}

/* ================================
   5️⃣ EXPORTAR A EXCEL
================================ */
if ($export_excel === 'excel') {

    $filename = "compras_fijas_consorcios_" . date('Ymd_His') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // BOM para acentos
    echo "\xEF\xBB\xBF";

    echo "<table border='1'>";
    echo "<thead>
            <tr style='background:#e0f2fe;font-weight:bold;'>
                <th>Fecha</th>
                <th>Consorcio</th>
                <th>Categoria</th>
                <th>Proveedor</th>
                <th>Monto</th>
                <th>Solicitante</th>
                <th>Estado</th>
            </tr>
          </thead>
          <tbody>";

    $total_monto = 0;

    foreach ($compras as $c) {
        $total_monto += floatval($c['monto']);

        echo "<tr>
                <td>" . date('Y-m-d', strtotime($c['fecha'])) . "</td>
                <td>" . htmlspecialchars($c['consorcio']) . "</td>
                <td>" . htmlspecialchars($c['categoria']) . "</td>
                <td>" . htmlspecialchars($c['proveedor']) . "</td>
                <td>" . number_format($c['monto'], 0, ',', '.') . "</td>
                <td>" . htmlspecialchars($c['solicitante']) . "</td>
                <td>" . strtoupper($c['estado']) . "</td>
              </tr>";
    }

    /* ===== FILA TOTAL ===== */
    echo "
        <tr style='font-weight:bold;background:#f1f5f9;'>
            <td colspan='4' style='text-align:right;'>TOTAL</td>
            <td>" . number_format($total_monto, 0, ',', '.') . "</td>
            <td colspan='2'></td>
        </tr>
    ";

    echo "</tbody></table>";
    exit;
}

?>


<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Compras Fijas Consorcios</title>

<link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../../../assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>
function imprimirTabla() {
    window.print();
}

function exportarExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = '?' + params.toString();
}
</script>

</head>
<body class="consorcios-fijas-index-page">

<div class="navbar-spacer"></div>

<div class="compras-header">
    <div class="titulo-compras">Compras Fijas - Consorcios</div>
    <div class="compras-subtitle">Control y aprobación de compras fijas por consorcio</div>
</div>

<div class="acciones-compras">
    <button class="btn-agregar" onclick="window.location.href='agregar_consorcios.php'">
        <i class="bi bi-plus-circle"></i> Agregar compra fija de consorcio
    </button>

    <button class="btn-imprimir" onclick="imprimirTabla()">
        <i class="bi bi-printer"></i> Imprimir
    </button>

    <!-- NUEVO BOTON EXCEL -->
    <button class="btn-imprimir btn-exportar" onclick="exportarExcel()">
        <i class="bi bi-file-earmark-excel"></i> Exportar Excel
    </button>
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

<!-- FILTROS -->
<div class="filtros-container">
    <form method="GET" class="filtros-form">
        <div class="filtros-grid">
            <div class="filtro-item">
                <label>Consorcio</label>
                <select name="consorcio" class="form-select filtros-select filtros-consorcio">
                    <option value="">-- Todos --</option>
                    <?php foreach ($consorcios_lista as $c): ?>
                        <option value="<?= htmlspecialchars($c['consorcio_normalizado']) ?>"
                            <?= ($filtro_consorcio === $c['consorcio_normalizado'] ? 'selected' : '') ?>>
                            <?= htmlspecialchars(ucwords(trim($c['consorcio_visible']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-item">
                <label>Mes</label>
                <select name="mes" class="form-select filtros-select filtros-mes">
                    <option value="">-- Todos --</option>
                    <?php foreach ($meses_lista as $m): ?>
                        <option value="<?= $m ?>" <?= ($filtro_mes === $m ? 'selected' : '') ?>>
                            <?= date('F Y', strtotime($m . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-item">
                <label>Estado</label>
                <select name="estado" class="form-select filtros-select filtros-estado">
                    <option value="">-- Todos --</option>
                    <option value="pendiente_presupuesto" <?= ($filtro_estado === 'pendiente_presupuesto' ? 'selected' : '') ?>>
                        Pendiente Presupuesto
                    </option>
                    <option value="aprobado_presupuesto" <?= ($filtro_estado === 'aprobado_presupuesto' ? 'selected' : '') ?>>
                        Aprobado Presupuesto
                    </option>
                    <option value="aprobado_direccion" <?= ($filtro_estado === 'aprobado_direccion' ? 'selected' : '') ?>>
                        Aprobado Dirección
                    </option>
                    <option value="aprobado_contabilidad" <?= ($filtro_estado === 'aprobado_contabilidad' ? 'selected' : '') ?>>
                        Aprobado Contabilidad
                    </option>
                    <option value="aprobado_pagos" <?= ($filtro_estado === 'aprobado_pagos' ? 'selected' : '') ?>>
                        Aprobado Pagos
                    </option>
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
    <th>Consorcio</th>
    <th>Categoria</th>
    <th>Proveedor</th>
    <th>Monto</th>
    <th>Solicitante</th>
    <th>Cotizacion</th>
    <th>RUT</th>
    <th>Certificacion</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr>
</thead>

<tbody>
<?php foreach($compras as $c): ?>
<tr>
    <td><?= date('Y-m-d', strtotime($c['fecha'])) ?></td>
    <td><?= htmlspecialchars($c['consorcio']) ?></td>
    <td><?= htmlspecialchars($c['categoria']) ?></td>
    <td><?= htmlspecialchars($c['proveedor']) ?></td>
    <td>$<?= number_format($c['monto'], 0, ',', '.') ?></td>
    <td><?= htmlspecialchars($c['solicitante']) ?></td>

    <td>
        <?php if ($c['archivo_cotizacion']): ?>
            <a class="archivo-link" target="_blank" href="../../../uploads/compras_fijas_consorcios/<?= urlencode($c['archivo_cotizacion']) ?>">
                <i class="bi bi-file-earmark-arrow-down"></i>
            </a>
        <?php endif; ?>
    </td>

    <td>
        <?php if ($c['archivo_rut']): ?>
            <a class="archivo-link" target="_blank" href="../../../uploads/compras_fijas_consorcios/<?= urlencode($c['archivo_rut']) ?>">
                <i class="bi bi-file-earmark-arrow-down"></i>
            </a>
        <?php endif; ?>
    </td>

    <td>
        <?php if ($c['archivo_certificacion']): ?>
            <a class="archivo-link" target="_blank" href="../../../uploads/compras_fijas_consorcios/<?= urlencode($c['archivo_certificacion']) ?>">
                <i class="bi bi-file-earmark-arrow-down"></i>
            </a>
        <?php endif; ?>
    </td>

    <td>
        <?php $estado_class = strtolower($c['estado']); ?>
        <span class="estado-label <?= $estado_class ?>"><?= ucwords($c['estado']) ?></span>
    </td>

    <td class="acciones-cell">
        <a href="detalle_consorcios.php?id=<?= $c['id'] ?>" class="archivo-link" title="Ver detalle">
            <i class="bi bi-eye"></i>
        </a>

        <?php if ($rol == 7): ?>
            <a href="eliminar.php?id=<?= $c['id'] ?>"
               class="archivo-link delete-link"
               title="Eliminar"
               onclick="return confirm('Seguro que deseas eliminar esta compra fija de consorcio? Esta accion no se puede deshacer.')">
                <i class="bi bi-trash"></i>
            </a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>

<?php if(empty($compras)): ?>
<tr>
    <td colspan="11" class="text-center text-muted">No hay registros.</td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>

</body>
</html>
