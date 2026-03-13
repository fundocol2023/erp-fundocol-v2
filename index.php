<?php
require_once __DIR__ . '/includes/bootstrap.php';

session_start();
erp_send_private_page_headers();

if (!isset($_SESSION['usuario_id'])) {
    erp_redirect('login.php');
}

require_once 'config/db.php';

// Carga módulos según rol
$stmt = $pdo->prepare("
    SELECT nombre, icono, ruta 
    FROM modulos m
    INNER JOIN permisos p ON m.id = p.modulo_id
    WHERE p.rol_id = ? AND p.puede_ver = 1
    ORDER BY m.nombre
");
$stmt->execute([$_SESSION['usuario_rol']]);
$modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Panel principal del ERP de Fundocol para acceder a los módulos internos según el rol del usuario.">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Panel principal | ERP Fundocol</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(erp_asset_url('assets/css/style.css')) ?>">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(erp_asset_url('assets/img/Fundocol_favicon.png')) ?>">

</head>
<body>
    <?php // Puedes quitar el sidebar en esta vista o dejarlo minimalista ?>
    <!-- <?php // include 'includes/sidebar.php'; ?> -->
    <main class="main-dashboard">
        <?php include 'includes/navbar.php'; ?>
        <section class="odoo-menu">
            <div class="odoo-menu-title">
                <img src="<?= htmlspecialchars(erp_asset_url('assets/img/logo.svg')) ?>" alt="Logo de Fundocol" class="odoo-menu-logo" width="60" height="60" fetchpriority="high">
                <h1>Bienvenido, <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></h1>
                <p>Selecciona un módulo para continuar</p>
            </div>
            <div class="odoo-modules-grid">
                <?php foreach ($modulos as $mod): ?>
                    <a href="<?= htmlspecialchars($mod['ruta']) ?>" class="odoo-module-card">
                        <div class="odoo-module-icon">
                            <i class="bi bi-<?= htmlspecialchars($mod['icono']) ?>"></i>
                        </div>
                        <div class="odoo-module-name">
                            <?= htmlspecialchars($mod['nombre']) ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</body>
</html>
