<?php
include '../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventarios - Submenú</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-index-page">
    <!-- Espaciador debajo del navbar fijo -->
    <div class="navbar-spacer"></div>
    <div class="subtitulo">Seleccione el inventario que desea gestionar</div>
    <div class="inventarios-grid">
        <a class="card-inventario" href="centro.php">
            <div class="inventario-icon"><i class="bi bi-building"></i></div>
            <div class="inventario-nombre">Centro</div>
            <div class="inventario-desc">Inventario del centro administrativo o principal.</div>
        </a>
        <a class="card-inventario" href="papeleria/index.php">
            <div class="inventario-icon"><i class="bi bi-pen"></i></div>
            <div class="inventario-nombre">Papelería</div>
            <div class="inventario-desc">Papelería y materiales de oficina.</div>
        </a>
        <a class="card-inventario" href="aseo.php">
            <div class="inventario-icon"><i class="bi bi-droplet"></i></div>
            <div class="inventario-nombre">Aseo</div>
            <div class="inventario-desc">Elementos de aseo y limpieza.</div>
        </a>
        <a class="card-inventario" href="bodega_oficina.php">
            <div class="inventario-icon"><i class="bi bi-box"></i></div>
            <div class="inventario-nombre">Bodega Oficina</div>
            <div class="inventario-desc">Bodega y almacenamiento general.</div>
        </a>
        <a class="card-inventario" href="equipos/index.php">
            <div class="inventario-icon"><i class="bi bi-laptop"></i></div>
            <div class="inventario-nombre">Equipo de Cómputo</div>
            <div class="inventario-desc">Computadores, portátiles y tecnología.</div>
        </a>
        <a class="card-inventario" href="empleados/index.php">
            <div class="inventario-icon"><i class="bi bi-person-vcard"></i></div>
            <div class="inventario-nombre">Empleados</div>
            <div class="inventario-desc">Dotación y equipos asignados a empleados.</div>
        </a>
        <a class="card-inventario" href="correos/index.php">
            <div class="inventario-icon"><i class="bi bi-envelope"></i></div>
            <div class="inventario-nombre">Correos</div>
            <div class="inventario-desc">Cuentas de correo y credenciales.</div>
        </a>
    </div>
</body>
</html>


