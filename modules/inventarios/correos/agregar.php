<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $plataforma = trim($_POST['plataforma'] ?? '');
    $proveedor = trim($_POST['proveedor'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');
    $estado = $_POST['estado'] ?? 'activo';
    $fecha_creacion = $_POST['fecha_creacion'] ?? null;
    $ultimo_acceso = $_POST['ultimo_acceso'] ?? null;
    $notas = trim($_POST['notas'] ?? '');

    if ($correo === '') {
        $mensaje = 'El correo es obligatorio.';
    } else {
        $sql = "INSERT INTO inventario_correos (correo, usuario, plataforma, proveedor, area, responsable, estado, fecha_creacion, ultimo_acceso, notas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
            $correo,
            $usuario ?: null,
            $plataforma ?: null,
            $proveedor ?: null,
            $area ?: null,
            $responsable ?: null,
            $estado,
            $fecha_creacion ?: null,
            $ultimo_acceso ?: null,
            $notas ?: null
        ]);
        if ($ok) {
            header('Location: index.php?msg=ok');
            exit;
        }
        $mensaje = 'Error al guardar el correo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Correo</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-correos-page">
<div class="navbar-spacer"></div>
<div class="form-contenedor correos-form">
    <div class="correos-form-head">
        <div class="correos-form-icon"><i class="bi bi-envelope-at"></i></div>
        <div>
            <div class="form-titulo">Agregar Correo</div>
            <div class="correos-form-sub">Registra la cuenta, responsable y estado de acceso.</div>
        </div>
    </div>
    <?php if ($mensaje): ?><div class="msg-error"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <form method="post" autocomplete="off" class="form-grid">
        <div class="form-section-title">Cuenta</div>
        <div class="form-group span-2">
            <label class="form-label" for="correo">Correo *</label>
            <input type="email" name="correo" id="correo" class="form-control" required maxlength="160" placeholder="nombre@empresa.com">
        </div>
        <div class="form-group">
            <label class="form-label" for="usuario">Usuario</label>
            <input type="text" name="usuario" id="usuario" class="form-control" maxlength="120" placeholder="Usuario en plataforma">
        </div>
        <div class="form-group">
            <label class="form-label" for="plataforma">Plataforma</label>
            <input type="text" name="plataforma" id="plataforma" class="form-control" maxlength="120" placeholder="Gmail, Outlook, Zoho...">
        </div>
        <div class="form-group">
            <label class="form-label" for="proveedor">Proveedor</label>
            <input type="text" name="proveedor" id="proveedor" class="form-control" maxlength="120" placeholder="Google, Microsoft, etc.">
        </div>
        <div class="form-section-title">Responsable</div>
        <div class="form-group">
            <label class="form-label" for="responsable">Responsable</label>
            <input type="text" name="responsable" id="responsable" class="form-control" maxlength="120" placeholder="Nombre responsable">
        </div>
        <div class="form-group">
            <label class="form-label" for="area">Area</label>
            <input type="text" name="area" id="area" class="form-control" maxlength="120" placeholder="Area o proceso">
        </div>
        <div class="form-section-title">Fechas y estado</div>
        <div class="form-group">
            <label class="form-label" for="fecha_creacion">Fecha de creacion</label>
            <input type="date" name="fecha_creacion" id="fecha_creacion" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label" for="ultimo_acceso">Ultimo acceso</label>
            <input type="date" name="ultimo_acceso" id="ultimo_acceso" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label" for="estado">Estado</label>
            <select name="estado" id="estado" class="form-control">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>
        </div>
        <div class="form-section-title">Notas</div>
        <div class="form-group form-group-full">
            <label class="form-label" for="notas">Notas</label>
            <textarea name="notas" id="notas" class="form-control" rows="3" maxlength="600" placeholder="Observaciones, configuracion, etc."></textarea>
        </div>
        <div class="form-btns form-group-full">
            <button type="submit" class="btn-principal"><i class="bi bi-check2-circle"></i> Guardar</button>
            <a href="index.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
