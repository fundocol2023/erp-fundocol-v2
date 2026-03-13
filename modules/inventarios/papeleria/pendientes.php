<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$mensaje = "";

// Procesar solicitud si se envía por POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['procesar_id'])) {
    $id = intval($_POST['procesar_id']);
    // Traer la solicitud
    $stmt = $pdo->prepare("SELECT * FROM solicitudes_papeleria WHERE id = ? AND estado = 'pendiente'");
    $stmt->execute([$id]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($solicitud) {
        $productos = json_decode($solicitud['productos'], true);
        foreach($productos as $p) {
            if (!$p['nuevo']) {
                // Producto existente: sumar al stock
                $stmt2 = $pdo->prepare("UPDATE inventario_papeleria SET cantidad = cantidad + ? WHERE id = ?");
                $stmt2->execute([$p['cantidad'], $p['id']]);
            } else {
                // Producto nuevo: crearlo en inventario
                $stmt3 = $pdo->prepare("INSERT INTO inventario_papeleria (nombre, cantidad) VALUES (?, ?)");
                $stmt3->execute([$p['nombre'], $p['cantidad']]);
            }
        }
        // Marcar como procesada
        $stmt4 = $pdo->prepare("UPDATE solicitudes_papeleria SET estado = 'procesada' WHERE id = ?");
        $stmt4->execute([$id]);
        $mensaje = "<span style='color:#22c55e;font-weight:600'>Solicitud procesada correctamente y stock actualizado.</span>";
    } else {
        $mensaje = "<span style='color:#ef4444;font-weight:600'>Solicitud no encontrada o ya procesada.</span>";
    }
}

// Traer solicitudes pendientes
$stmt = $pdo->prepare("SELECT * FROM solicitudes_papeleria WHERE estado = 'pendiente' ORDER BY fecha ASC");
$stmt->execute();
$pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes Pendientes de Papelería</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
</head>
<body class="inventarios-papeleria-pendientes-page">
<div class="navbar-spacer"></div>
<div class="panel-contenedor">
    <div class="panel-titulo">Solicitudes Pendientes de Papelería</div>
    <?php if($mensaje): ?><div class="msg-nota"><?= $mensaje ?></div><?php endif; ?>
    <?php if(empty($pendientes)): ?>
        <div class="msg-nota" style="color:#64748b;">No hay solicitudes pendientes por procesar.</div>
    <?php endif; ?>
    <?php foreach($pendientes as $sol): 
        $productos = json_decode($sol['productos'], true);
    ?>
        <div class="solicitud-box">
            <div class="solicitud-fecha"><i class="bi bi-calendar-event"></i> <?= date('Y-m-d H:i', strtotime($sol['fecha'])) ?></div>
            <table class="solicitud-table">
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Tipo</th>
                </tr>
                <?php foreach($productos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td><?= intval($p['cantidad']) ?></td>
                    <td><?= $p['nuevo'] ? "<span style='color:#ef4444;'>Nuevo</span>" : "Existente" ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <form method="post" onsubmit="return confirm('¿Deseas procesar y actualizar el inventario con esta solicitud?')">
                <input type="hidden" name="procesar_id" value="<?= $sol['id'] ?>">
                <button type="submit" class="procesar-btn"><i class="bi bi-check2-circle"></i> Procesar solicitud</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>