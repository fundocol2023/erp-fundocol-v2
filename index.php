<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
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
    <title>Dashboard | ERP</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp.fundocol.org/assets/img/Fundocol_favicon.png">

</head>
<body>
    <?php // Puedes quitar el sidebar en esta vista o dejarlo minimalista ?>
    <!-- <?php // include 'includes/sidebar.php'; ?> -->
    <main class="main-dashboard">
        <?php include 'includes/navbar.php'; ?>
        <section class="odoo-menu">
            <div class="odoo-menu-title">
                <img src="assets/img/logo.svg" alt="Logo" class="odoo-menu-logo">
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
