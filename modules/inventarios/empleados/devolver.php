<?php
include_once '../../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: asignaciones.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM inventario_empleado_asignaciones WHERE id = ?");
$stmt->execute([$id]);
$asignacion = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$asignacion || $asignacion['estado'] !== 'activa') {
    header('Location: asignaciones.php');
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE inventario_empleado_asignaciones SET estado='devuelta', fecha_devolucion=CURDATE() WHERE id = ?");
    $stmt->execute([$id]);

    $stmt = $pdo->prepare("UPDATE inventario_empleado_items SET estado='disponible' WHERE id = ?");
    $stmt->execute([$asignacion['item_id']]);

    $pdo->commit();
    header('Location: asignaciones.php?msg=ok');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: asignaciones.php');
    exit;
}
