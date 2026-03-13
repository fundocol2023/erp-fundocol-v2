<?php include '../../includes/navbar.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes - ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
</head>
<body class="solicitudes-index-page">
    <div class="navbar-spacer"></div>
    <div class="titulo-submenu">Seleccione el tipo de solicitud</div>
    <div class="cards-submenu">

        <!-- Permiso laboral -->
        <div class="card-sm">
            <div class="card-icon"><i class="bi bi-briefcase"></i></div>
            <div class="card-title">Permiso Laboral</div>
            <div class="card-desc">Solicita permisos laborales por tiempo definido con aprobación.</div>
            <a href="permiso_laboral/index.php" class="card-link"><i class="bi bi-arrow-right-short"></i> Ingresar</a>
        </div>
        
        <!-- Vacaciones -->
        <div class="card-sm">
            <div class="card-icon"><i class="bi bi-sun"></i></div>
            <div class="card-title">Solicitud de Vacaciones</div>
            <div class="card-desc">Pide tus vacaciones de manera formal y sigue el estado de aprobación.</div>
            <a href="vacaciones/index.php" class="card-link"><i class="bi bi-arrow-right-short"></i> Ingresar</a>
        </div>
        
        <div class="card-sm">
            <div class="card-icon"><i class="bi bi-pen"></i></div>
            <div class="card-title">Firma de Documentos</div>
            <div class="card-desc">Sube documentos PDF y solicita firma final.</div>
            <a href="firma_documentos/index.php" class="card-link"><i class="bi bi-arrow-right-short"></i> Ingresar</a>
        </div>
        
        <!-- Vehículo -->
        <div class="card-sm">
            <div class="card-icon"><i class="bi bi-truck"></i></div>
            <div class="card-title">Solicitud de Vehículo</div>
            <div class="card-desc">Reserva un vehiculo para tus actividades laborales.</div>
            <a href="vehiculo/index.php" class="card-link"><i class="bi bi-arrow-right-short"></i> Ingresar</a>
        </div>
        
    </div>
</body>
</html>
