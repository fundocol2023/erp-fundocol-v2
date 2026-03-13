<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM inventario_empleados WHERE estado='activo') AS activos,
    (SELECT COUNT(*) FROM inventario_empleado_asignaciones WHERE estado='activa') AS asignados,
    (SELECT COUNT(*) FROM inventario_empleado_asignaciones WHERE estado='activa' AND fecha_devolucion IS NULL) AS pendientes
")->fetch(PDO::FETCH_ASSOC);

$sql = "SELECT e.*, 
    (SELECT COUNT(*) FROM inventario_empleado_asignaciones a WHERE a.empleado_id = e.id AND a.estado='activa') AS items_asignados
FROM inventario_empleados e
ORDER BY e.nombres ASC, e.apellidos ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

function iniciales($nombres, $apellidos) {
    $ini1 = $nombres ? strtoupper(substr(trim($nombres), 0, 1)) : '';
    $ini2 = $apellidos ? strtoupper(substr(trim($apellidos), 0, 1)) : '';
    $ini = $ini1 . $ini2;
    return $ini !== '' ? $ini : 'NA';
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario de Empleados</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-empleados-page">
    <div class="navbar-spacer"></div>

    <header class="empleados-header">
        <div class="empleados-title">Inventario de Empleados</div>
        <div class="empleados-subtitle">Control visual de equipos, dotacion y asignaciones</div>
    </header>

    <div class="empleados-actions">
        <button class="btn-empleado primary" onclick="window.location.href='agregar.php'">
            <i class="bi bi-plus-circle"></i> Agregar empleado
        </button>
        <button class="btn-empleado" type="button" onclick="window.location.href='exportar_excel.php'">
            <i class="bi bi-file-earmark-excel"></i> Exportar Excel
        </button>
        <button class="btn-empleado" type="button" onclick="window.location.href='asignaciones.php'">
            <i class="bi bi-clipboard-check"></i> Ver asignaciones
        </button>
        <button class="btn-empleado" type="button" onclick="window.location.href='items.php'">
            <i class="bi bi-box-seam"></i> Items y equipos
        </button>
    </div>

    <?php if ($msg === 'ok'): ?>
        <div class="alert alert-success" style="margin: 0 7%;">Registro guardado correctamente.</div>
    <?php elseif ($msg === 'eliminado'): ?>
        <div class="alert alert-success" style="margin: 0 7%;">Empleado eliminado.</div>
    <?php elseif ($msg === 'asignaciones'): ?>
        <div class="alert alert-warning" style="margin: 0 7%;">No se puede eliminar: tiene asignaciones activas.</div>
    <?php endif; ?>

    <section class="empleados-stats">
        <div class="stat-card">
            <span class="stat-label">Empleados activos</span>
            <span class="stat-value"><?= (int)($stats['activos'] ?? 0) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Items asignados</span>
            <span class="stat-value"><?= (int)($stats['asignados'] ?? 0) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Pendientes por entrega</span>
            <span class="stat-value"><?= (int)($stats['pendientes'] ?? 0) ?></span>
        </div>
    </section>

    <section class="empleados-grid">
        <?php foreach ($empleados as $emp): ?>
            <?php
                $estado = $emp['estado'] === 'activo' ? 'activo' : 'pendiente';
                $items = (int)$emp['items_asignados'];
                $nombre = trim($emp['nombres'].' '.$emp['apellidos']);
            ?>
            <article class="empleado-card">
                <div class="empleado-avatar"><?= htmlspecialchars(iniciales($emp['nombres'], $emp['apellidos'])) ?></div>
                <div class="empleado-info">
                    <h3><?= htmlspecialchars($nombre) ?></h3>
                    <p class="empleado-rol"><?= htmlspecialchars($emp['cargo'] ?? 'Sin cargo') ?></p>
                    <div class="empleado-meta">
                        <span><i class="bi bi-building"></i> Area: <?= htmlspecialchars($emp['area'] ?? 'Sin area') ?></span>
                        <span><i class="bi bi-laptop"></i> <?= $items ?> items asignados</span>
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <span class="empleado-estado <?= $estado ?>"><?= $emp['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?></span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.location.href='editar.php?id=<?= (int)$emp['id'] ?>'" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('¿Eliminar empleado?'))window.location.href='eliminar.php?id=<?= (int)$emp['id'] ?>'" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</body>
</html>
