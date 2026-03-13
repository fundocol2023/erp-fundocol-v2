<?php
include '../../includes/navbar.php';
include_once '../../config/db.php';

// Traer solicitudes PENDIENTES (de cualquier vehículo)
$stmt = $pdo->query(
    "SELECT s.*, v.nombre AS nombre_vehiculo, v.placa, u.nombre AS solicitante
     FROM vehiculos_solicitudes s
     INNER JOIN vehiculos v ON s.vehiculo_id = v.id
     LEFT JOIN usuarios u ON s.solicitante_id = u.id
     WHERE s.estado = 'pendiente'
     ORDER BY s.fecha_inicio ASC"
);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes de Vehículo Pendientes</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
</head>
<body class="vehiculos-solicitudes-page">
<div class="navbar-spacer"></div>
<div class="sol-pend-box">
    <div class="sol-pend-titulo"><i class="bi bi-clock-history"></i> Solicitudes de vehículos pendientes por aprobar</div>
    <?php if(empty($solicitudes)): ?>
        <div class="empty-msg">No hay solicitudes pendientes por aprobar.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-pendientes align-middle mb-0">
                <thead>
                    <tr>
                        <th>Vehículo</th>
                        <th>Placa</th>
                        <th>Solicitante</th>
                        <th>Fecha inicio</th>
                        <th>Fecha final</th>
                        <th>Motivo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($solicitudes as $s): ?>
                    <tr>
                        <td>
                            <span class="vehiculo-pend-vehiculo"><?= htmlspecialchars($s['nombre_vehiculo']) ?></span>
                        </td>
                        <td>
                            <span class="vehiculo-pend-placa"><?= htmlspecialchars($s['placa']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($s['solicitante'] ?? '---') ?></td>
                        <td><?= htmlspecialchars($s['fecha_inicio']) ?></td>
                        <td><?= htmlspecialchars($s['fecha_fin']) ?></td>
                        <td><?= htmlspecialchars($s['motivo']) ?></td>
                        <td>
                            <a href="ver.php?id=<?= $s['id'] ?>" class="vehiculo-btn">
                                <i class="bi bi-eye"></i> Ver solicitud
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>


