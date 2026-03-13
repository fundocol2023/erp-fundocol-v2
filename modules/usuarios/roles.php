<?php
require_once '../../config/db.php';

// AGREGAR NUEVO ROL
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_rol'])) {
    $nombre = trim($_POST['nombre']);
    if ($nombre !== '') {
        $stmt = $pdo->prepare("INSERT INTO roles (nombre) VALUES (?)");
        $stmt->execute([$nombre]);
        echo "<script>location.href='?seccion=roles';</script>";
        exit;
    }
}

// EDITAR ROL
if (isset($_POST['edit_rol_id'])) {
    $id = intval($_POST['edit_rol_id']);
    $nombre = trim($_POST['edit_nombre']);
    if ($nombre !== '') {
        $stmt = $pdo->prepare("UPDATE roles SET nombre=? WHERE id=?");
        $stmt->execute([$nombre, $id]);
        echo "<script>location.href='?seccion=roles';</script>";
        exit;
    }
}

// ELIMINAR ROL (solo si no tiene usuarios asignados)
if (isset($_POST['delete_rol_id'])) {
    $id = intval($_POST['delete_rol_id']);
    $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol_id=?");
    $check->execute([$id]);
    if ($check->fetchColumn() == 0) {
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id=?");
        $stmt->execute([$id]);
    }
    echo "<script>location.href='?seccion=roles';</script>";
    exit;
}

// TRAER TODOS LOS ROLES
$stmt = $pdo->query("SELECT * FROM roles ORDER BY id");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="usuarios-section usuarios-roles">
    <h2 class="usuarios-title">Roles del sistema</h2>

    <!-- Formulario agregar -->
    <form method="post" class="odoo-inline-form usuarios-form usuarios-form-add">
        <input type="text" name="nombre" placeholder="Nuevo rol" required maxlength="40" class="usuarios-input">
        <button type="submit" name="nuevo_rol" class="odoo-btn usuarios-btn-primary">
            <i class="bi bi-plus-circle"></i> Agregar rol
        </button>
    </form>

    <!-- Tabla de roles -->
    <table class="odoo-table usuarios-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre del Rol</th>
                <th style="text-align:center;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $rol): ?>
                <tr>
                    <td><?= $rol['id'] ?></td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit']==$rol['id']): ?>
                            <form method="post" class="usuarios-edit-form">
                                <input type="hidden" name="edit_rol_id" value="<?= $rol['id'] ?>">
                                <input type="text" name="edit_nombre" value="<?= htmlspecialchars($rol['nombre']) ?>" required maxlength="40" class="usuarios-input-sm">
                                <button type="submit" class="odoo-btn-min usuarios-btn-icon"><i class="bi bi-check2"></i></button>
                                <a href="?seccion=roles" class="odoo-btn-min usuarios-btn-icon usuarios-btn-neutral"><i class="bi bi-x"></i></a>
                            </form>
                        <?php else: ?>
                            <?= htmlspecialchars($rol['nombre']) ?>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <a href="?seccion=roles&edit=<?= $rol['id'] ?>" class="odoo-btn-min usuarios-btn-icon" title="Editar"><i class="bi bi-pencil-square"></i></a>
                        <form method="post" class="usuarios-inline-form" onsubmit="return confirm('¿Eliminar este rol?');">
                            <input type="hidden" name="delete_rol_id" value="<?= $rol['id'] ?>">
                            <button type="submit" class="odoo-btn-min usuarios-btn-icon usuarios-btn-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>