<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: items.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM inventario_empleado_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    header('Location: items.php');
    exit;
}

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $serial = trim($_POST['serial'] ?? '');
    $estado = $_POST['estado'] ?? 'disponible';
    $observaciones = trim($_POST['observaciones'] ?? '');

    $categoria = $categoria !== '' ? $categoria : null;
    $marca = $marca !== '' ? $marca : null;
    $modelo = $modelo !== '' ? $modelo : null;
    $serial = $serial !== '' ? $serial : null;
    $observaciones = $observaciones !== '' ? $observaciones : null;

    if ($nombre === '') {
        $mensaje = 'El nombre del item es obligatorio.';
    } else {
        $sql = "UPDATE inventario_empleado_items SET nombre=?, categoria=?, marca=?, modelo=?, serial=?, estado=?, observaciones=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([$nombre, $categoria, $marca, $modelo, $serial, $estado, $observaciones, $id]);
        if ($ok) {
            header('Location: items.php?msg=ok');
            exit;
        }
        $mensaje = 'Error al actualizar el item.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Item</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-empleados-page">
<div class="navbar-spacer"></div>
<div class="form-contenedor">
    <div class="form-titulo">Editar Item</div>
    <?php if ($mensaje): ?><div class="msg-error"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label class="form-label" for="nombre">Nombre *</label>
            <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="120" value="<?= htmlspecialchars($item['nombre']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="categoria">Categoria</label>
            <input type="text" name="categoria" id="categoria" class="form-control" maxlength="120" value="<?= htmlspecialchars($item['categoria'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="marca">Marca</label>
            <input type="text" name="marca" id="marca" class="form-control" maxlength="120" value="<?= htmlspecialchars($item['marca'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="modelo">Modelo</label>
            <input type="text" name="modelo" id="modelo" class="form-control" maxlength="120" value="<?= htmlspecialchars($item['modelo'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="serial">Serial</label>
            <input type="text" name="serial" id="serial" class="form-control" maxlength="120" value="<?= htmlspecialchars($item['serial'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="estado">Estado</label>
            <select name="estado" id="estado" class="form-control">
                <option value="disponible" <?= $item['estado'] === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                <option value="asignado" <?= $item['estado'] === 'asignado' ? 'selected' : '' ?>>Asignado</option>
                <option value="mantenimiento" <?= $item['estado'] === 'mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
                <option value="baja" <?= $item['estado'] === 'baja' ? 'selected' : '' ?>>Baja</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="observaciones">Observaciones</label>
            <textarea name="observaciones" id="observaciones" class="form-control" rows="2" maxlength="500"><?= htmlspecialchars($item['observaciones'] ?? '') ?></textarea>
        </div>
        <div class="form-btns">
            <button type="submit" class="btn-principal"><i class="bi bi-check2-circle"></i> Guardar</button>
            <a href="items.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
