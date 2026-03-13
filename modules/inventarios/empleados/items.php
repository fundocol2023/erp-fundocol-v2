<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM inventario_empleado_items) AS total,
    (SELECT COUNT(*) FROM inventario_empleado_items WHERE estado='disponible') AS disponibles,
    (SELECT COUNT(*) FROM inventario_empleado_items WHERE estado='asignado') AS asignados
")->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM inventario_empleado_items ORDER BY nombre ASC");
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Items y Equipos</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-empleados-page">
<div class="navbar-spacer"></div>
<div class="titulo-inv">Items y equipos</div>
<div class="acciones-inventario">
    <a href="item_agregar.php" class="boton-custom agregar"><i class="bi bi-plus-lg"></i> Agregar item</a>
    <a href="asignaciones.php" class="boton-custom pendientes"><i class="bi bi-clipboard-check"></i> Ver asignaciones</a>
    <a href="index.php" class="boton-custom"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<?php if ($msg === 'asignado'): ?>
    <div class="alert alert-warning" style="margin: 0 7%;">No se puede eliminar: item con asignacion activa.</div>
<?php elseif ($msg === 'ok'): ?>
    <div class="alert alert-success" style="margin: 0 7%;">Operacion realizada correctamente.</div>
<?php endif; ?>

<div class="empleados-stats" style="margin-top: 10px;">
    <div class="stat-card">
        <span class="stat-label">Total items</span>
        <span class="stat-value"><?= (int)($stats['total'] ?? 0) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Disponibles</span>
        <span class="stat-value"><?= (int)($stats['disponibles'] ?? 0) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Asignados</span>
        <span class="stat-value"><?= (int)($stats['asignados'] ?? 0) ?></span>
    </div>
</div>

<div class="table-responsive" style="margin: 20px 7%;">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Categoria</th>
                <th>Marca/Modelo</th>
                <th>Serial</th>
                <th>Estado</th>
                <th style="width:140px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['nombre']) ?></td>
                    <td><?= htmlspecialchars($item['categoria'] ?? '') ?></td>
                    <td><?= htmlspecialchars(trim(($item['marca'] ?? '').' '.($item['modelo'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars($item['serial'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['estado']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary" onclick="window.location.href='item_editar.php?id=<?= (int)$item['id'] ?>'"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('¿Eliminar item?'))window.location.href='item_eliminar.php?id=<?= (int)$item['id'] ?>'"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
