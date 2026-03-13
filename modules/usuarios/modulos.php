<?php
require_once '../../config/db.php';

// AGREGAR NUEVO MÓDULO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_modulo'])) {
    $nombre = trim($_POST['nombre']);
    $icono = trim($_POST['icono']);
    $ruta = trim($_POST['ruta']);
    if ($nombre !== '' && $icono !== '' && $ruta !== '') {
        $stmt = $pdo->prepare("INSERT INTO modulos (nombre, icono, ruta) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $icono, $ruta]);
        echo "<script>location.href='?seccion=modulos';</script>";
        exit;
    }
}

// EDITAR MÓDULO
if (isset($_POST['edit_modulo_id'])) {
    $id = intval($_POST['edit_modulo_id']);
    $nombre = trim($_POST['edit_nombre']);
    $icono = trim($_POST['edit_icono']);
    $ruta = trim($_POST['edit_ruta']);
    if ($nombre !== '' && $icono !== '' && $ruta !== '') {
        $stmt = $pdo->prepare("UPDATE modulos SET nombre=?, icono=?, ruta=? WHERE id=?");
        $stmt->execute([$nombre, $icono, $ruta, $id]);
        echo "<script>location.href='?seccion=modulos';</script>";
        exit;
    }
}

// ELIMINAR MÓDULO (sólo si no tiene permisos asociados)
if (isset($_POST['delete_modulo_id'])) {
    $id = intval($_POST['delete_modulo_id']);
    $check = $pdo->prepare("SELECT COUNT(*) FROM permisos WHERE modulo_id=?");
    $check->execute([$id]);
    if ($check->fetchColumn() == 0) {
        $stmt = $pdo->prepare("DELETE FROM modulos WHERE id=?");
        $stmt->execute([$id]);
    }
    echo "<script>location.href='?seccion=modulos';</script>";
    exit;
}

// TRAER TODOS LOS MÓDULOS
$stmt = $pdo->query("SELECT * FROM modulos ORDER BY id");
$modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="usuarios-section usuarios-modulos">
    <h2 class="usuarios-title">Módulos del sistema</h2>

    <!-- Formulario agregar -->
    <form method="post" class="odoo-inline-form usuarios-form usuarios-form-add">
        <input type="text" name="nombre" placeholder="Nombre del módulo" required maxlength="60" class="usuarios-input">
        <input type="text" name="icono" placeholder="Icono (ej: cart-check)" required maxlength="30" class="usuarios-input">
        <input type="text" name="ruta" placeholder="Ruta (ej: modules/compras/index.php)" required maxlength="120" class="usuarios-input">
        <button type="submit" name="nuevo_modulo" class="odoo-btn usuarios-btn-primary">
            <i class="bi bi-plus-circle"></i> Agregar módulo
        </button>
    </form>

    <!-- Tabla de módulos -->
    <table class="odoo-table usuarios-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Icono</th>
                <th>Ruta</th>
                <th style="text-align:center;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($modulos as $mod): ?>
                <tr>
                    <td><?= $mod['id'] ?></td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit']==$mod['id']): ?>
                            <form method="post" class="usuarios-edit-form">
                                <input type="hidden" name="edit_modulo_id" value="<?= $mod['id'] ?>">
                                <input type="text" name="edit_nombre" value="<?= htmlspecialchars($mod['nombre']) ?>" required maxlength="60" class="usuarios-input-sm">
                        <?php else: ?>
                            <?= htmlspecialchars($mod['nombre']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit']==$mod['id']): ?>
                            <input type="text" name="edit_icono" value="<?= htmlspecialchars($mod['icono']) ?>" required maxlength="30" class="usuarios-input-sm">
                        <?php else: ?>
                            <i class="bi bi-<?= htmlspecialchars($mod['icono']) ?>"></i> <?= htmlspecialchars($mod['icono']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit']==$mod['id']): ?>
                            <input type="text" name="edit_ruta" value="<?= htmlspecialchars($mod['ruta']) ?>" required maxlength="120" class="usuarios-input-sm">
                            <button type="submit" class="odoo-btn-min usuarios-btn-icon"><i class="bi bi-check2"></i></button>
                            <a href="?seccion=modulos" class="odoo-btn-min usuarios-btn-icon usuarios-btn-neutral"><i class="bi bi-x"></i></a>
                            </form>
                        <?php else: ?>
                            <?= htmlspecialchars($mod['ruta']) ?>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <a href="?seccion=modulos&edit=<?= $mod['id'] ?>" class="odoo-btn-min usuarios-btn-icon" title="Editar"><i class="bi bi-pencil-square"></i></a>
                        <form method="post" class="usuarios-inline-form" onsubmit="return confirm('¿Eliminar este módulo?');">
                            <input type="hidden" name="delete_modulo_id" value="<?= $mod['id'] ?>">
                            <button type="submit" class="odoo-btn-min usuarios-btn-icon usuarios-btn-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

