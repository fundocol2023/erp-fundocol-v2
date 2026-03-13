<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

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
        $sql = "INSERT INTO inventario_empleado_items (nombre, categoria, marca, modelo, serial, estado, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([$nombre, $categoria, $marca, $modelo, $serial, $estado, $observaciones]);
        if ($ok) {
            header('Location: items.php?msg=ok');
            exit;
        }
        $mensaje = 'Error al guardar el item.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Item</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-empleados-page">
<div class="navbar-spacer"></div>
<div class="form-contenedor">
    <div class="form-titulo">Agregar Item</div>
    <?php if ($mensaje): ?><div class="msg-error"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label class="form-label" for="nombre">Nombre *</label>
            <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="categoria">Categoria</label>
            <input type="text" name="categoria" id="categoria" class="form-control" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="marca">Marca</label>
            <input type="text" name="marca" id="marca" class="form-control" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="modelo">Modelo</label>
            <input type="text" name="modelo" id="modelo" class="form-control" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="serial">Serial</label>
            <input type="text" name="serial" id="serial" class="form-control" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="estado">Estado</label>
            <select name="estado" id="estado" class="form-control">
                <option value="disponible">Disponible</option>
                <option value="asignado">Asignado</option>
                <option value="mantenimiento">Mantenimiento</option>
                <option value="baja">Baja</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="observaciones">Observaciones</label>
            <textarea name="observaciones" id="observaciones" class="form-control" rows="2" maxlength="500"></textarea>
        </div>
        <div class="form-btns">
            <button type="submit" class="btn-principal"><i class="bi bi-check2-circle"></i> Guardar</button>
            <a href="items.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
