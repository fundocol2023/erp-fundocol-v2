<?php
include '../../includes/navbar.php';
include_once '../../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

// Consultar datos actuales para mostrar
$stmt = $pdo->prepare("SELECT * FROM vehiculos WHERE id = ?");
$stmt->execute([$id]);
$vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehiculo) {
    echo "<div style='color:red;text-align:center;padding:60px'>Vehículo no encontrado.</div>";
    exit;
}

$uploads_dir = '../../uploads/vehiculos/';
$foto_path = $vehiculo['foto'] ? $uploads_dir . $vehiculo['foto'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eliminar foto física si existe
    if ($foto_path && file_exists($foto_path)) {
        unlink($foto_path);
    }
    // Eliminar de la base de datos
    $del = $pdo->prepare("DELETE FROM vehiculos WHERE id=?");
    $del->execute([$id]);
    header("Location: index.php?msg=vehiculo_eliminado");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Vehículo</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- estilos movidos a assets/css/style.css -->
</head>
<body class="vehiculos-eliminar-page">
<div class="navbar-spacer"></div>
<div class="eliminar-box">
    <h2><i class="bi bi-exclamation-triangle"></i> Eliminar Vehículo</h2>
    <?php if ($vehiculo['foto'] && file_exists($foto_path)): ?>
        <img src="<?= $foto_path ?>" class="eliminar-foto" alt="Foto">
    <?php endif; ?>
    <p>¿Seguro que quieres eliminar el vehículo <b><?= htmlspecialchars($vehiculo['nombre']) ?></b>
    <?php if ($vehiculo['placa']): ?>
        (placa <b><?= htmlspecialchars($vehiculo['placa']) ?></b>)
    <?php endif; ?>?<br>
    <span style="color:#dc2626;font-size:.99em;">Esta acción no se puede deshacer.</span></p>
    <form method="post">
        <div class="btns-eliminar">
            <button type="submit" class="btn-borrar"><i class="bi bi-trash"></i> Sí, eliminar</button>
            <a href="index.php" class="btn-cancel">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
