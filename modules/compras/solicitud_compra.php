<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}
$usuario_id = $_SESSION['usuario_id'];

$sql = "
    SELECT sc.*, c.proveedor, c.precio
    FROM solicitudes_cotizacion sc
    JOIN solicitudes_cotizacion_cotizaciones c ON sc.cotizacion_aprobada_id = c.id
    LEFT JOIN solicitudes_compra scp ON sc.id = scp.solicitud_cotizacion_id
    WHERE sc.solicitante_id = ? 
      AND sc.estado = 'aprobada'
      AND scp.id IS NULL
    ORDER BY sc.fecha DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$aprobadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes de Compra | ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="compras-solicitud-compra-page">
    <?php include '../../includes/navbar.php'; ?>
    <div class="erp-card">
        <div class="erp-title mb-4">
            <i class="bi bi-cart-check"></i>
            Solicitudes de Compra Disponibles
        </div>
        <?php if (empty($aprobadas)): ?>
            <div class="alert alert-info">No tienes cotizaciones aprobadas disponibles para solicitud de compra.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table erp-table align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha Solicitud</th>
                        <th>Proyecto/Oficina</th>
                        <th>Necesidad</th>
                        <th>Proveedor</th>
                        <th>Precio</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aprobadas as $i => $row): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($row['fecha']) ?></td>
                        <td><?= htmlspecialchars($row['proyecto_oficina']) ?></td>
                        <td><?= htmlspecialchars($row['necesidad']) ?></td>
                        <td><?= htmlspecialchars($row['proveedor']) ?></td>
                        <td class="precio-aprobado">
                            <?= '$' . number_format($row['precio'],0,',','.') ?>
                        </td>
                        <td>
                            <a href="crear_solicitud_compra.php?id=<?= $row['id'] ?>" class="btn btn-solicitar">
                                <i class="bi bi-plus-circle"></i> Crear Solicitud de Compra
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

