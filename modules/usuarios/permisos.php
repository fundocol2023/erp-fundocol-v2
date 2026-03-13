<?php
require_once '../../config/db.php';

// --- Obtener roles y módulos ---
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$modulos = $pdo->query("SELECT * FROM modulos ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// --- Seleccionar rol ---
$rol_id = isset($_GET['rol_id']) ? intval($_GET['rol_id']) : ($roles[0]['id'] ?? 1);

// --- Guardar permisos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_perms'])) {
    $rol_id = intval($_POST['rol_id']);
    // Borra todos los permisos actuales del rol
    $pdo->prepare("DELETE FROM permisos WHERE rol_id=?")->execute([$rol_id]);

    // Recibe nuevos permisos
    foreach ($modulos as $modulo) {
        $mid = $modulo['id'];
        $ver    = isset($_POST["ver_$mid"])    ? 1 : 0;
        $crear  = isset($_POST["crear_$mid"])  ? 1 : 0;
        $editar = isset($_POST["editar_$mid"]) ? 1 : 0;
        $eliminar = isset($_POST["eliminar_$mid"]) ? 1 : 0;
        if ($ver || $crear || $editar || $eliminar) {
            $stmt = $pdo->prepare("INSERT INTO permisos (rol_id, modulo_id, puede_ver, puede_crear, puede_editar, puede_eliminar) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$rol_id, $mid, $ver, $crear, $editar, $eliminar]);
        }
    }
    echo "<script>location.href='?seccion=permisos&rol_id=$rol_id';</script>";
    exit;
}

// --- Traer permisos actuales del rol seleccionado ---
$perms = [];
$stmt = $pdo->prepare("SELECT * FROM permisos WHERE rol_id=?");
$stmt->execute([$rol_id]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $perm) {
    $perms[$perm['modulo_id']] = $perm;
}
?>

<div class="usuarios-section usuarios-permisos">
    <h2 class="usuarios-title">Asignar permisos a roles</h2>
    <form method="get" class="usuarios-form usuarios-form-filter">
        <input type="hidden" name="seccion" value="permisos">
        <label for="rol_id" class="usuarios-label-inline">Selecciona un rol:</label>
        <select name="rol_id" id="rol_id" onchange="this.form.submit()" class="usuarios-select">
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $r['id']==$rol_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <form method="post">
        <input type="hidden" name="rol_id" value="<?= $rol_id ?>">
        <table class="odoo-table usuarios-table usuarios-permisos-table">
            <thead>
                <tr>
                    <th>Módulo</th>
                    <th style="text-align:center;">Ver</th>
                    <th style="text-align:center;">Crear</th>
                    <th style="text-align:center;">Editar</th>
                    <th style="text-align:center;">Eliminar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modulos as $mod): 
                    $p = $perms[$mod['id']] ?? ['puede_ver'=>0,'puede_crear'=>0,'puede_editar'=>0,'puede_eliminar'=>0];
                ?>
                    <tr>
                        <td>
                            <i class="bi bi-<?= htmlspecialchars($mod['icono']) ?>"></i> 
                            <?= htmlspecialchars($mod['nombre']) ?>
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" class="usuarios-checkbox" name="ver_<?= $mod['id'] ?>"   <?= $p['puede_ver'] ? 'checked' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" class="usuarios-checkbox" name="crear_<?= $mod['id'] ?>" <?= $p['puede_crear'] ? 'checked' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" class="usuarios-checkbox" name="editar_<?= $mod['id'] ?>" <?= $p['puede_editar'] ? 'checked' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" class="usuarios-checkbox" name="eliminar_<?= $mod['id'] ?>" <?= $p['puede_eliminar'] ? 'checked' : '' ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" name="save_perms" class="odoo-btn usuarios-btn-primary"><i class="bi bi-floppy"></i> Guardar permisos</button>
    </form>
</div>