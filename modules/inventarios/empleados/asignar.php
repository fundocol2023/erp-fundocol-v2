<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$empleados = $pdo->query("SELECT id, nombres, apellidos FROM inventario_empleados WHERE estado='activo' ORDER BY nombres ASC")->fetchAll(PDO::FETCH_ASSOC);
$items = $pdo->query("SELECT id, nombre, serial FROM inventario_empleado_items WHERE estado='disponible' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empleado_id = (int)($_POST['empleado_id'] ?? 0);
    $item_id = (int)($_POST['item_id'] ?? 0);
    $fecha_asignacion = $_POST['fecha_asignacion'] ?? date('Y-m-d');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if ($empleado_id <= 0 || $item_id <= 0) {
        $mensaje = 'Debe seleccionar un empleado y un item.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO inventario_empleado_asignaciones (empleado_id, item_id, fecha_asignacion, observaciones) VALUES (?, ?, ?, ?)");
            $stmt->execute([$empleado_id, $item_id, $fecha_asignacion, $observaciones]);

            $stmt = $pdo->prepare("UPDATE inventario_empleado_items SET estado='asignado' WHERE id = ?");
            $stmt->execute([$item_id]);

            $pdo->commit();
            header('Location: asignaciones.php?msg=ok');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = 'Error al asignar el item.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Asignacion</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-empleados-page">
<div class="navbar-spacer"></div>
<div class="form-contenedor">
    <div class="form-titulo">Nueva asignacion</div>
    <?php if ($mensaje): ?><div class="msg-error"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label class="form-label" for="empleado_id">Empleado *</label>
            <select name="empleado_id" id="empleado_id" class="form-control" required>
                <option value="">Seleccione</option>
                <?php foreach ($empleados as $emp): ?>
                    <option value="<?= (int)$emp['id'] ?>"><?= htmlspecialchars(trim($emp['nombres'].' '.$emp['apellidos'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="item_id">Item disponible *</label>
            <select name="item_id" id="item_id" class="form-control" required>
                <option value="">Seleccione</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= (int)$item['id'] ?>"><?= htmlspecialchars($item['nombre'].' '.($item['serial'] ? '(' . $item['serial'] . ')' : '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="fecha_asignacion">Fecha de asignacion</label>
            <input type="date" name="fecha_asignacion" id="fecha_asignacion" class="form-control" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="observaciones">Observaciones</label>
            <textarea name="observaciones" id="observaciones" class="form-control" rows="2" maxlength="500"></textarea>
        </div>
        <div class="form-btns">
            <button type="submit" class="btn-principal"><i class="bi bi-check2-circle"></i> Asignar</button>
            <a href="asignaciones.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
