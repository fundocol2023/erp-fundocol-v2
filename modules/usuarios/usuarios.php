<?php
require_once '../../config/db.php';

// Traer roles
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// --- AGREGAR USUARIO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_usuario'])) {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $rol_id = intval($_POST['rol_id']);
    $password = $_POST['password'];

    if ($nombre !== '' && $email !== '' && $rol_id > 0 && $password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $email, $hash, $rol_id]);
        echo "<script>location.href='?seccion=usuarios';</script>";
        exit;
    }
}

// --- EDITAR USUARIO ---
if (isset($_POST['edit_usuario_id'])) {
    $id = intval($_POST['edit_usuario_id']);
    $nombre = trim($_POST['edit_nombre']);
    $email = trim($_POST['edit_email']);
    $rol_id = intval($_POST['edit_rol_id']);
    $cambiar_pass = $_POST['edit_password'] ?? '';

    if ($nombre !== '' && $email !== '' && $rol_id > 0) {
        if ($cambiar_pass !== '') {
            $hash = password_hash($cambiar_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, email=?, rol_id=?, password=? WHERE id=?");
            $stmt->execute([$nombre, $email, $rol_id, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, email=?, rol_id=? WHERE id=?");
            $stmt->execute([$nombre, $email, $rol_id, $id]);
        }
        echo "<script>location.href='?seccion=usuarios';</script>";
        exit;
    }
}

// --- ACTIVAR/DESACTIVAR USUARIO ---
if (isset($_POST['toggle_activo_id'])) {
    $id = intval($_POST['toggle_activo_id']);
    $activo = intval($_POST['toggle_activo_val']);
    $stmt = $pdo->prepare("UPDATE usuarios SET activo=? WHERE id=?");
    $stmt->execute([$activo, $id]);
    echo "<script>location.href='?seccion=usuarios';</script>";
    exit;
}

// --- ELIMINAR USUARIO ---
if (isset($_POST['delete_usuario_id'])) {
    $id = intval($_POST['delete_usuario_id']);
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id=?");
    $stmt->execute([$id]);
    echo "<script>location.href='?seccion=usuarios';</script>";
    exit;
}

// Traer todos los usuarios y su rol
$stmt = $pdo->query("SELECT u.*, r.nombre as rol_nombre FROM usuarios u INNER JOIN roles r ON u.rol_id=r.id ORDER BY u.id");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="usuarios-section usuarios-list">
    <h2 class="usuarios-title">Usuarios del sistema</h2>

    <!-- Formulario agregar -->
    <form method="post" class="odoo-inline-form usuarios-form usuarios-form-add">
        <input type="text" name="nombre" placeholder="Nombre" required maxlength="80" class="usuarios-input">
        <input type="email" name="email" placeholder="Email" required maxlength="100" class="usuarios-input">
        <input type="password" name="password" placeholder="Contraseña" required minlength="5" maxlength="40" class="usuarios-input">
        <select name="rol_id" required class="usuarios-select">
            <option value="">Rol</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="nuevo_usuario" class="odoo-btn usuarios-btn-primary">
            <i class="bi bi-plus-circle"></i> Agregar usuario
        </button>
    </form>

    <!-- Tabla de usuarios -->
    <table class="odoo-table usuarios-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
                <th style="text-align:center;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit']==$u['id']): ?>
                            <form method="post" class="usuarios-edit-form">
                                <input type="hidden" name="edit_usuario_id" value="<?= $u['id'] ?>">
                                <input type="text" name="edit_nombre" value="<?= htmlspecialchars($u['nombre']) ?>" required maxlength="80" class="usuarios-input-sm">
                        <?php else: ?>
                            <?= htmlspecialchars($u['nombre']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit']==$u['id']): ?>
                            <input type="email" name="edit_email" value="<?= htmlspecialchars($u['email']) ?>" required maxlength="100" class="usuarios-input-sm">
                        <?php else: ?>
                            <?= htmlspecialchars($u['email']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit']==$u['id']): ?>
                            <select name="edit_rol_id" class="usuarios-select-sm">
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= $r['id']==$u['rol_id'] ? 'selected':'' ?>>
                                        <?= htmlspecialchars($r['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?= htmlspecialchars($u['rol_nombre']) ?>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($u['activo']): ?>
                            <span class="usuarios-status usuarios-status-activo">Activo</span>
                        <?php else: ?>
                            <span class="usuarios-status usuarios-status-inactivo">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if (isset($_GET['edit']) && $_GET['edit']==$u['id']): ?>
                            <input type="password" name="edit_password" placeholder="Nueva contraseña (opcional)" minlength="5" maxlength="40" class="usuarios-input-sm">
                            <button type="submit" class="odoo-btn-min usuarios-btn-icon"><i class="bi bi-check2"></i></button>
                            <a href="?seccion=usuarios" class="odoo-btn-min usuarios-btn-icon usuarios-btn-neutral"><i class="bi bi-x"></i></a>
                            </form>
                        <?php else: ?>
                            <a href="?seccion=usuarios&edit=<?= $u['id'] ?>" class="odoo-btn-min usuarios-btn-icon" title="Editar"><i class="bi bi-pencil-square"></i></a>
                            <form method="post" class="usuarios-inline-form" onsubmit="return confirm('¿Eliminar este usuario?');">
                                <input type="hidden" name="delete_usuario_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="odoo-btn-min usuarios-btn-icon usuarios-btn-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                            <form method="post" class="usuarios-inline-form">
                                <input type="hidden" name="toggle_activo_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="toggle_activo_val" value="<?= $u['activo'] ? 0 : 1 ?>">
                                <button type="submit" class="odoo-btn-min usuarios-btn-icon usuarios-btn-success" title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                    <i class="bi <?= $u['activo'] ? 'bi-person-dash' : 'bi-person-check' ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
