<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

// Traer todos los productos de papelería
$sql = "SELECT * FROM inventario_papeleria ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario de Papelería</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-papeleria-index-page">
<div class="navbar-spacer"></div>
<div class="titulo-inv">Inventario de Papelería</div>
<div class="acciones-inventario">
    <a href="exportar_excel.php" class="boton-custom excel" title="Descargar inventario en Excel">
        <i class="bi bi-file-earmark-excel"></i> Exportar Excel
    </a>
    <a href="solicitar_stock.php" class="boton-custom solicitar" title="Solicitar productos al encargado">
        <i class="bi bi-bag-plus"></i> Solicitar Stock
    </a>
    <a href="pendientes.php" class="boton-custom pendientes" title="Ver solicitudes pendientes">
        <i class="bi bi-clock-history"></i> Solicitudes Pendientes
    </a>
    <button class="boton-custom agregar" onclick="window.location.href='agregar.php'" title="Agregar producto nuevo">
        <i class="bi bi-plus-lg"></i> Agregar producto
    </button>
</div>
<div class="inv-cards">
    <?php foreach($productos as $prod): ?>
        <?php
        // Determinar color de stock
        $stock_class = "verde";
        if ($prod['cantidad'] == 0) $stock_class = "rojo";
        elseif ($prod['cantidad'] < 4) $stock_class = "amarillo";
        ?>
        <div class="inv-card">
            <div class="inv-acciones">
                <button class="inv-btn" title="Editar" onclick="window.location.href='editar.php?id=<?= $prod['id'] ?>'"><i class="bi bi-pencil"></i></button>
                <button class="inv-btn" title="Eliminar" onclick="if(confirm('¿Eliminar este producto?'))window.location.href='eliminar.php?id=<?= $prod['id'] ?>'"><i class="bi bi-trash"></i></button>
            </div>
            <?php if ($prod['imagen'] && file_exists("../../../uploads/papeleria/".$prod['imagen'])): ?>
                <img src="../../../uploads/papeleria/<?= htmlspecialchars($prod['imagen']) ?>" class="inv-img" alt="Producto">
            <?php else: ?>
                <div class="inv-img" style="display:flex; align-items:center; justify-content:center;"><i class="bi bi-archive" style="font-size:2em; color:#94a3b8;"></i></div>
            <?php endif; ?>
            <div class="inv-nombre"><?= htmlspecialchars($prod['nombre']) ?></div>
            <div class="inv-desc"><?= htmlspecialchars($prod['descripcion']) ?></div>
            <div class="inv-cant">Stock: <span class="inv-stock <?= $stock_class ?>"><?= $prod['cantidad'] ?></span></div>
            <?php if($prod['observaciones']): ?>
                <div style="font-size:.92em; color:#64748b; margin-top:5px;"><?= htmlspecialchars($prod['observaciones']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>


