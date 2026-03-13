<?php
include '../../includes/navbar.php';
include_once '../../config/db.php';

// Traer vehículos
$stmt = $pdo->query("SELECT * FROM vehiculos ORDER BY nombre ASC");
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vehículos | ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
</head>
<body class="vehiculos-index-page">
    <div class="navbar-spacer"></div>
    <div class="vehiculos-titulo"><i class="bi bi-truck"></i> Gestión de Vehículos</div>
    <button class="vehiculos-agregar-btn" onclick="window.location.href='agregar.php'">
        <i class="bi bi-plus-circle"></i> Agregar vehículo
    </button>
    <div class="vehiculos-grid">
        <?php if(empty($vehiculos)): ?>
            <div class="vehiculo-empty-msg">No hay vehículos registrados. Haz clic en "Agregar vehículo" para crear uno.</div>
        <?php else: ?>
            <?php foreach($vehiculos as $v): ?>
            <div class="vehiculo-card">
                <?php if ($v['foto'] && file_exists("../../uploads/vehiculos/" . $v['foto'])): ?>
                    <img src="../../uploads/vehiculos/<?= htmlspecialchars($v['foto']) ?>" class="vehiculo-foto" alt="Vehículo">
                <?php else: ?>
                    <div class="vehiculo-foto" style="display:flex; align-items:center; justify-content:center;"><i class="bi bi-truck" style="font-size:2em; color:#94a3b8;"></i></div>
                <?php endif; ?>
                <div class="vehiculo-info">
                    <div class="vehiculo-nombre"><?= htmlspecialchars($v['nombre']) ?></div>
                    <div class="vehiculo-placa"><?= htmlspecialchars($v['placa']) ?></div>
                    <div class="vehiculo-datos">SOAT: <?= htmlspecialchars($v['soat_vigencia']) ?></div>
                    <div class="vehiculo-datos">Tecnomecánica: <?= htmlspecialchars($v['tecno_vigencia']) ?></div>
                </div>
                <div class="vehiculo-btns">
                    <a href="ver.php?id=<?= $v['id'] ?>" class="vehiculo-btn">
                        <i class="bi bi-eye"></i> Ver
                    </a>
                    <a href="solicitudes.php?vehiculo=<?= $v['id'] ?>" class="vehiculo-btn"
                       style="background: linear-gradient(90deg,#10b981 70%, #059669 100%);">
                        <i class="bi bi-calendar-check"></i> Solicitudes
                    </a>
                    <a href="editar.php?id=<?= $v['id'] ?>" class="vehiculo-btn"
                       style="background: linear-gradient(90deg,#0ea5e9 70%, #38bdf8 100%);">
                        <i class="bi bi-pencil"></i> Editar
                    </a>
                    <a href="eliminar.php?id=<?= $v['id'] ?>"
                       class="vehiculo-btn vehiculo-btn-eliminar"
                       onclick="return confirm('¿Seguro que deseas eliminar este vehículo?\nEsta acción no se puede deshacer.')">
                        <i class="bi bi-trash"></i> Eliminar
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>

