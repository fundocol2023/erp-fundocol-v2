<?php
require_once '../../includes/navbar.php';
require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// --- Obtener datos del usuario ---
$stmt = $pdo->prepare("SELECT u.nombre, u.email, r.nombre AS rol 
                       FROM usuarios u 
                       LEFT JOIN roles r ON u.rol_id = r.id
                       WHERE u.id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

$mensaje = "";
$tipo_mensaje = "";

// --- Cambio de contraseña ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = $_POST['actual'] ?? '';
    $nueva = $_POST['nueva'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if (!$actual || !$nueva || !$confirmar) {
        $mensaje = "Por favor completa todos los campos.";
        $tipo_mensaje = "error";
    } elseif ($nueva !== $confirmar) {
        $mensaje = "Las contraseñas nuevas no coinciden.";
        $tipo_mensaje = "error";
    } else {
        // Verificar contraseña actual
        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($actual, $hash)) {
            $mensaje = "La contraseña actual es incorrecta.";
            $tipo_mensaje = "error";
        } else {
            $nuevo_hash = password_hash($nueva, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            if ($stmt->execute([$nuevo_hash, $usuario_id])) {
                $mensaje = "Contraseña actualizada exitosamente.";
                $tipo_mensaje = "exito";
            } else {
                $mensaje = "Error al actualizar la contraseña.";
                $tipo_mensaje = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mi Perfil</title>

<!-- Estilos globales -->
<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../../assets/css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700&display=swap" rel="stylesheet">

<!-- estilos movidos a assets/css/style.css -->
</head>
<body class="usuarios-perfil-page">
<div class="perfil-container">
    <div class="perfil-header">
        <i class="bi bi-person-circle perfil-icono"></i>
        <div class="perfil-nombre"><?= htmlspecialchars($usuario['nombre']) ?></div>
        <div class="perfil-email"><?= htmlspecialchars($usuario['email']) ?></div>
        <div class="perfil-rol"><?= htmlspecialchars($usuario['rol'] ?? 'Sin rol asignado') ?></div>
    </div>

    <?php if ($mensaje): ?>
        <div class="msg <?= $tipo_mensaje ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <hr>

    <h5><i class="bi bi-shield-lock"></i> Cambiar contraseña</h5>
    <form method="POST" autocomplete="off">
        <div class="form-group mb-3 password-wrapper">
            <label class="form-label" for="actual">Contraseña actual</label>
            <input type="password" id="actual" name="actual" class="form-control" required>
            <i class="bi bi-eye" onclick="togglePassword('actual', this)"></i>
        </div>
        <div class="form-group mb-3 password-wrapper">
            <label class="form-label" for="nueva">Nueva contraseña</label>
            <input type="password" id="nueva" name="nueva" class="form-control" required>
            <i class="bi bi-eye" onclick="togglePassword('nueva', this)"></i>
        </div>
        <div class="form-group mb-4 password-wrapper">
            <label class="form-label" for="confirmar">Confirmar nueva contraseña</label>
            <input type="password" id="confirmar" name="confirmar" class="form-control" required>
            <i class="bi bi-eye" onclick="togglePassword('confirmar', this)"></i>
        </div>
        <div class="text-center">
            <button type="submit" class="btn btn-guardar">
                <i class="bi bi-check2-circle"></i> Guardar cambios
            </button>
            <br>
            <a href="../../index.php" class="btn-volver"><i class="bi bi-arrow-left"></i> Volver al menú</a>
        </div>
    </form>
</div>

<script>
function togglePassword(id, icon) {
    const input = document.getElementById(id);
    const isPassword = input.type === "password";
    input.type = isPassword ? "text" : "password";
    icon.classList.toggle("bi-eye");
    icon.classList.toggle("bi-eye-slash");
}
</script>
</body>
</html>

