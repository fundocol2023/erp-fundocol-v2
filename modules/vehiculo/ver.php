<?php
include '../../includes/navbar.php';
include_once '../../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM vehiculos WHERE id = ?");
$stmt->execute([$id]);
$v = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$v) {
    echo "<div style='color:red;text-align:center;padding:60px'>Vehículo no encontrado.</div>";
    exit;
}

$uploads_dir = '../../uploads/vehiculos/';
$foto_path = $v['foto'] && file_exists($uploads_dir . $v['foto']) ? $uploads_dir . $v['foto'] : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Vehículo</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
</head>
<body class="vehiculos-ver-page">
<div class="navbar-spacer"></div>
<div class="vehiculo-detalle-box">
    <?php if($foto_path): ?>
        <img src="<?= $foto_path ?>" class="vehiculo-foto-detalle" alt="Foto vehículo">
    <?php else: ?>
        <div class="vehiculo-foto-detalle" style="display:flex; align-items:center; justify-content:center; background:#e0f2fe;">
            <i class="bi bi-truck" style="font-size:3.1em; color:#94a3b8;"></i>
        </div>
    <?php endif; ?>
    <div class="vehiculo-nombre-detalle"><?= htmlspecialchars($v['nombre']) ?></div>
    <div class="vehiculo-placa-detalle"><?= htmlspecialchars($v['placa']) ?></div>
    <table class="vehiculo-info-table">
        <tr>
            <td class="tit">SOAT:</td>
            <td class="val"><?= htmlspecialchars($v['soat_vigencia']) ?></td>
        </tr>
        <tr>
            <td class="tit">Tecnomecánica:</td>
            <td class="val"><?= htmlspecialchars($v['tecno_vigencia']) ?></td>
        </tr>
    </table>
    <?php if ($v['descripcion']): ?>
        <div class="vehiculo-desc"><b>Descripción:</b> <?= nl2br(htmlspecialchars($v['descripcion'])) ?></div>
    <?php endif; ?>
    <div class="vehiculo-botones-detalle">
        <a href="editar.php?id=<?= $v['id'] ?>" class="vehiculo-btn">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="eliminar.php?id=<?= $v['id'] ?>"
           class="vehiculo-btn vehiculo-btn-eliminar"
           onclick="return confirm('¿Seguro que deseas eliminar este vehículo? Esta acción no se puede deshacer.')">
            <i class="bi bi-trash"></i> Eliminar
        </a>
        <a href="index.php" class="vehiculo-btn vehiculo-btn-volver">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>
</body>
</html>
