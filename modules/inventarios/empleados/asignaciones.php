<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$sql = "SELECT a.*, e.nombres, e.apellidos, i.nombre AS item_nombre, i.serial
        FROM inventario_empleado_asignaciones a
        INNER JOIN inventario_empleados e ON e.id = a.empleado_id
        INNER JOIN inventario_empleado_items i ON i.id = a.item_id
        ORDER BY a.fecha_asignacion DESC, a.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignaciones</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-empleados-page">
<div class="navbar-spacer"></div>
<div class="titulo-inv">Asignaciones de items</div>
<div class="acciones-inventario">
    <a href="asignar.php" class="boton-custom agregar"><i class="bi bi-plus-lg"></i> Nueva asignacion</a>
    <a href="items.php" class="boton-custom"><i class="bi bi-box-seam"></i> Items</a>
    <a href="index.php" class="boton-custom"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<?php if ($msg === 'ok'): ?>
    <div class="alert alert-success" style="margin: 0 7%;">Operacion realizada correctamente.</div>
<?php endif; ?>

<div class="table-responsive" style="margin: 20px 7%;">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Empleado</th>
                <th>Item</th>
                <th>Serial</th>
                <th>Fecha asignacion</th>
                <th>Fecha devolucion</th>
                <th>Estado</th>
                <th style="width:120px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($asignaciones as $a): ?>
                <tr>
                    <td><?= htmlspecialchars(trim($a['nombres'].' '.$a['apellidos'])) ?></td>
                    <td><?= htmlspecialchars($a['item_nombre']) ?></td>
                    <td><?= htmlspecialchars($a['serial'] ?? '') ?></td>
                    <td><?= htmlspecialchars($a['fecha_asignacion']) ?></td>
                    <td><?= htmlspecialchars($a['fecha_devolucion'] ?? '') ?></td>
                    <td><?= htmlspecialchars($a['estado']) ?></td>
                    <td>
                        <?php if ($a['estado'] === 'activa'): ?>
                            <button class="btn btn-sm btn-outline-success" onclick="window.location.href='devolver.php?id=<?= (int)$a['id'] ?>'">Devolver</button>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
