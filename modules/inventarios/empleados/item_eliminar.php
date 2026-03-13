<?php
include_once '../../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: items.php');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_empleado_asignaciones WHERE item_id = ? AND estado='activa'");
$stmt->execute([$id]);
$activos = (int)$stmt->fetchColumn();

if ($activos > 0) {
    header('Location: items.php?msg=asignado');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM inventario_empleado_items WHERE id = ?");
$stmt->execute([$id]);

header('Location: items.php?msg=ok');
exit;
