<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM inventario_correos WHERE id = ?");
$stmt->execute([$id]);
$correo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$correo) {
    header('Location: index.php');
    exit;
}

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['correo'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $plataforma = trim($_POST['plataforma'] ?? '');
    $proveedor = trim($_POST['proveedor'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');
    $estado = $_POST['estado'] ?? 'activo';
    $fecha_creacion = $_POST['fecha_creacion'] ?? null;
    $ultimo_acceso = $_POST['ultimo_acceso'] ?? null;
    $notas = trim($_POST['notas'] ?? '');

    if ($email === '') {
        $mensaje = 'El correo es obligatorio.';
    } else {
        $sql = "UPDATE inventario_correos SET correo=?, usuario=?, plataforma=?, proveedor=?, area=?, responsable=?, estado=?, fecha_creacion=?, ultimo_acceso=?, notas=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
            $email,
            $usuario ?: null,
            $plataforma ?: null,
            $proveedor ?: null,
            $area ?: null,
            $responsable ?: null,
            $estado,
            $fecha_creacion ?: null,
            $ultimo_acceso ?: null,
            $notas ?: null,
            $id
        ]);
        if ($ok) {
            header('Location: index.php?msg=ok');
            exit;
        }
        $mensaje = 'Error al actualizar el correo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Correo</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-correos-page">
<div class="navbar-spacer"></div>
<div class="form-contenedor correos-form">
    <div class="form-titulo">Editar Correo</div>
    <?php if ($mensaje): ?><div class="msg-error"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <form method="post" autocomplete="off" class="form-grid">
        <div class="form-group">
            <label class="form-label" for="correo">Correo *</label>
            <input type="email" name="correo" id="correo" class="form-control" required maxlength="160" value="<?= htmlspecialchars($correo['correo']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="usuario">Usuario</label>
            <input type="text" name="usuario" id="usuario" class="form-control" maxlength="120" value="<?= htmlspecialchars($correo['usuario'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="plataforma">Plataforma</label>
            <input type="text" name="plataforma" id="plataforma" class="form-control" maxlength="120" value="<?= htmlspecialchars($correo['plataforma'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="proveedor">Proveedor</label>
            <input type="text" name="proveedor" id="proveedor" class="form-control" maxlength="120" value="<?= htmlspecialchars($correo['proveedor'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="responsable">Responsable</label>
            <input type="text" name="responsable" id="responsable" class="form-control" maxlength="120" value="<?= htmlspecialchars($correo['responsable'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="area">Area</label>
            <input type="text" name="area" id="area" class="form-control" maxlength="120" value="<?= htmlspecialchars($correo['area'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="fecha_creacion">Fecha de creacion</label>
            <input type="date" name="fecha_creacion" id="fecha_creacion" class="form-control" value="<?= htmlspecialchars($correo['fecha_creacion'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="ultimo_acceso">Ultimo acceso</label>
            <input type="date" name="ultimo_acceso" id="ultimo_acceso" class="form-control" value="<?= htmlspecialchars($correo['ultimo_acceso'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="estado">Estado</label>
            <select name="estado" id="estado" class="form-control">
                <option value="activo" <?= $correo['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                <option value="inactivo" <?= $correo['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>
        <div class="form-group form-group-full">
            <label class="form-label" for="notas">Notas</label>
            <textarea name="notas" id="notas" class="form-control" rows="3" maxlength="600"><?= htmlspecialchars($correo['notas'] ?? '') ?></textarea>
        </div>
        <div class="form-btns form-group-full">
            <button type="submit" class="btn-principal"><i class="bi bi-check2-circle"></i> Guardar</button>
            <a href="index.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
