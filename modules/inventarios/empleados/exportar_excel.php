<?php
include_once '../../../config/db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inventario_empleados.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['Documento', 'Nombres', 'Apellidos', 'Cargo', 'Area', 'Correo', 'Telefono', 'Estado', 'Items asignados']);

$sql = "SELECT e.*, 
    (SELECT COUNT(*) FROM inventario_empleado_asignaciones a WHERE a.empleado_id = e.id AND a.estado='activa') AS items_asignados
FROM inventario_empleados e
ORDER BY e.nombres ASC, e.apellidos ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($empleados as $e) {
    fputcsv($output, [
        $e['documento'],
        $e['nombres'],
        $e['apellidos'],
        $e['cargo'],
        $e['area'],
        $e['correo'],
        $e['telefono'],
        $e['estado'],
        $e['items_asignados']
    ]);
}

fclose($output);
exit;
