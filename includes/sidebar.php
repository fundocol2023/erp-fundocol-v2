<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION)) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Traer módulos según permisos del rol
$stmt = $pdo->prepare("
    SELECT m.nombre, m.icono, m.ruta 
    FROM modulos m
    INNER JOIN permisos p ON m.id = p.modulo_id
    WHERE p.rol_id = ? AND p.puede_ver = 1
    ORDER BY m.nombre
");
$stmt->execute([$_SESSION['usuario_rol']]);
$modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="<?= htmlspecialchars(erp_asset_url('assets/img/logo.svg')) ?>" alt="Logo" class="sidebar-logo-img">
        <span class="sidebar-title">ERP</span>
    </div>
    <nav class="sidebar-menu">
        <?php foreach ($modulos as $mod): ?>
            <a href="<?= htmlspecialchars($mod['ruta']) ?>" class="sidebar-link">
                <i class="bi bi-<?= htmlspecialchars($mod['icono']) ?>"></i>
                <?= htmlspecialchars($mod['nombre']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-link logout"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
    </div>
</aside>
