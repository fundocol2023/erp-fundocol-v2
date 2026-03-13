<?php
include_once '../../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_empleado_asignaciones WHERE empleado_id = ? AND estado='activa'");
$stmt->execute([$id]);
$activos = (int)$stmt->fetchColumn();

if ($activos > 0) {
    header('Location: index.php?msg=asignaciones');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM inventario_empleados WHERE id = ?");
$stmt->execute([$id]);

header('Location: index.php?msg=eliminado');
exit;
