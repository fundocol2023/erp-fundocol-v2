<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

/* =======================================================
   1. SOLICITUDES APROBADAS DISPONIBLES PARA SOLICITUD DE COMPRA
=========================================================== */
$sqlAprobadas = "
    SELECT sc.*, c.proveedor, c.precio
    FROM solicitudes_cotizacion_consorcios sc
    JOIN solicitudes_cotizacion_consorcios_cotizaciones c 
        ON sc.cotizacion_aprobada_id = c.id
    LEFT JOIN solicitudes_compra_consorcios scp 
        ON sc.id = scp.solicitud_cotizacion_id
    WHERE sc.solicitante_id = ? 
      AND sc.estado = 'aprobada'
      AND scp.id IS NULL
    ORDER BY sc.fecha DESC
";
$stmt = $pdo->prepare($sqlAprobadas);
$stmt->execute([$usuario_id]);
$aprobadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =======================================================
   2. SOLICITUDES RECHAZADAS (estado = 'rechazado')
      Compatible con tu tabla real (no existe fecha_rechazo)
=========================================================== */
$sqlRechazadas = "
    SELECT sc.*, 
           c.proveedor, 
           c.precio,
           scp.estado AS estado_compra,
           scp.comentario_rechazo,
           scp.fecha_creacion AS fecha_rechazo
    FROM solicitudes_cotizacion_consorcios sc
    JOIN solicitudes_cotizacion_consorcios_cotizaciones c 
        ON sc.cotizacion_aprobada_id = c.id
    JOIN solicitudes_compra_consorcios scp 
        ON sc.id = scp.solicitud_cotizacion_id
    WHERE sc.solicitante_id = ?
      AND scp.estado = 'rechazado'
    ORDER BY scp.fecha_creacion DESC
";
$stmt2 = $pdo->prepare($sqlRechazadas);
$stmt2->execute([$usuario_id]);
$rechazadas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes de Compra | ERP Fundocol</title>

    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="consorcios-solicitud-compra-page">
<?php include '../../includes/navbar.php'; ?>

<div class="erp-card">
    
    <div class="erp-title">
        <i class="bi bi-cart-check"></i>
        Solicitudes de Compra
    </div>

    <!-- ===========================
         NAV TABS
    ============================ -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aprobadas">Aprobadas Disponibles</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#rechazadas">Rechazadas</button></li>
    </ul>

    <div class="tab-content mt-4">

        <!-- ====================================================
             TAB: APROBADAS
        ==================================================== -->
        <div class="tab-pane fade show active" id="aprobadas">

            <?php if (empty($aprobadas)): ?>
                <div class="alert alert-info">No tienes cotizaciones aprobadas disponibles.</div>
            <?php else: ?>

            <div class="table-responsive">
                <table class="table erp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Consorcio</th>
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
                            <td><?= $row['fecha'] ?></td>
                            <td><?= $row['consorcio'] ?></td>
                            <td><?= $row['necesidad'] ?></td>
                            <td><?= $row['proveedor'] ?></td>
                            <td class="precio-aprobado">$<?= number_format($row['precio'], 0, ',', '.') ?></td>
                            <td>
                                <a href="crear_solicitud_compra.php?id=<?= $row['id'] ?>" class="btn btn-solicitar">
                                    <i class="bi bi-plus-circle"></i> Crear Solicitud
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ====================================================
             TAB: RECHAZADAS
        ==================================================== -->
        <div class="tab-pane fade" id="rechazadas">

            <?php if (empty($rechazadas)): ?>
                <div class="alert alert-info">No tienes solicitudes rechazadas.</div>
            <?php else: ?>

            <div class="table-responsive">
                <table class="table erp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha Rechazo</th>
                            <th>Consorcio</th>
                            <th>Necesidad</th>
                            <th>Proveedor</th>
                            <th>Comentario Rechazo</th>
                            <th>Rehacer</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($rechazadas as $i => $row): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= $row['fecha_rechazo'] ?></td>
                            <td><?= $row['consorcio'] ?></td>
                            <td><?= $row['necesidad'] ?></td>
                            <td><?= $row['proveedor'] ?></td>

                            <td class="comentario-rechazo-text">
                                <?= htmlspecialchars($row['comentario_rechazo'] ?: 'Sin comentario') ?>
                            </td>

                            <td>
                                <a href="reenviar_solicitud.php?id=<?= $row['id'] ?>" class="btn btn-solicitar">
                                    <i class="bi bi-arrow-repeat"></i> Rehacer Solicitud
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>

            <?php endif; ?>
        </div>

    </div>

</div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

