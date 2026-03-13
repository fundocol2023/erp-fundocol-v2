<?php
include '../../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compras | ERP</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    <main class="main-dashboard">
        <div class="odoo-menu" style="margin-top:36px;">
            <div class="odoo-menu-title">
                <h1>Compras</h1>
                <p>Selecciona el tipo de proceso:</p>
            </div>
            <div class="odoo-menu-options">
                <a href="solicitud_cotizacion.php" class="odoo-module-card" style="width:260px; margin:12px;">
                    <i class="bi bi-receipt odoo-module-icon"></i>
                    <span class="odoo-module-name">Solicitud de cotización</span>
                </a>
                <a href="solicitud_compra.php" class="odoo-module-card" style="width:260px; margin:12px;">
                    <i class="bi bi-file-earmark-plus odoo-module-icon"></i>
                    <span class="odoo-module-name">Solicitud de compra</span>
                </a>
            </div>
            <div style="margin-top:38px;">
                <a href="index.php" class="odoo-btn"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>
    </main>
</body>
</html>
