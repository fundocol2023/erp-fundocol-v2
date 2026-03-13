<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documento = trim($_POST['documento'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? null;
    $estado = $_POST['estado'] ?? 'activo';
    $notas = trim($_POST['notas'] ?? '');

    if ($documento === '' || $nombres === '' || $apellidos === '') {
        $mensaje = 'Documento, nombres y apellidos son obligatorios.';
    } else {
        $sql = "INSERT INTO inventario_empleados (documento, nombres, apellidos, cargo, area, correo, telefono, fecha_ingreso, estado, notas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([$documento, $nombres, $apellidos, $cargo, $area, $correo, $telefono, $fecha_ingreso ?: null, $estado, $notas]);
        if ($ok) {
            header('Location: index.php?msg=ok');
            exit;
        }
        $mensaje = 'Error al guardar el empleado.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Empleado</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-empleados-page">
<div class="navbar-spacer"></div>
<div class="form-contenedor">
    <div class="form-titulo">Agregar Empleado</div>
    <?php if ($mensaje): ?><div class="msg-error"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label class="form-label" for="documento">Documento *</label>
            <input type="text" name="documento" id="documento" class="form-control" required maxlength="30">
        </div>
        <div class="form-group">
            <label class="form-label" for="nombres">Nombres *</label>
            <input type="text" name="nombres" id="nombres" class="form-control" required maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="apellidos">Apellidos *</label>
            <input type="text" name="apellidos" id="apellidos" class="form-control" required maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="cargo">Cargo</label>
            <input type="text" name="cargo" id="cargo" class="form-control" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="area">Area</label>
            <input type="text" name="area" id="area" class="form-control" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="correo">Correo</label>
            <input type="email" name="correo" id="correo" class="form-control" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label" for="telefono">Telefono</label>
            <input type="text" name="telefono" id="telefono" class="form-control" maxlength="40">
        </div>
        <div class="form-group">
            <label class="form-label" for="fecha_ingreso">Fecha de ingreso</label>
            <input type="date" name="fecha_ingreso" id="fecha_ingreso" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label" for="estado">Estado</label>
            <select name="estado" id="estado" class="form-control">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="notas">Notas</label>
            <textarea name="notas" id="notas" class="form-control" rows="2" maxlength="500"></textarea>
        </div>
        <div class="form-btns">
            <button type="submit" class="btn-principal"><i class="bi bi-check2-circle"></i> Guardar</button>
            <a href="index.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
