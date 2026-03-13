<?php
include '../../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo de Compras | ERP</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    <main class="main-dashboard">
        <div class="odoo-menu" style="margin-top:36px;">
            <div class="odoo-menu-title">
                <h1>Módulo de Compras</h1>
            </div>
            <div class="odoo-menu-options">
                <a href="tipocompra.php" class="odoo-module-card" style="width:260px; margin:12px;">
                    <i class="bi bi-cart-check odoo-module-icon"></i>
                    <span class="odoo-module-name">Compras</span>
                </a>
                <a href="fijas/compras_fijas.php" class="odoo-module-card" style="width:260px; margin:12px;">
                    <i class="bi bi-cash-coin odoo-module-icon"></i>
                    <span class="odoo-module-name">Compras fijas</span>
                </a>
            </div>
        </div>
    </main>
</body>
</html>