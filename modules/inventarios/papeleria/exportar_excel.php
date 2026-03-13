<?php
include_once '../../../config/db.php';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="inventario_papeleria.xls"');

echo "Nombre\tDescripción\tCantidad\tObservaciones\n";

$sql = "SELECT nombre, descripcion, cantidad, observaciones FROM inventario_papeleria ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Escapamos las tabs y saltos de línea por si acaso
    $nombre = str_replace(array("\t","\n"), " ", $row['nombre']);
    $descripcion = str_replace(array("\t","\n"), " ", $row['descripcion']);
    $observaciones = str_replace(array("\t","\n"), " ", $row['observaciones']);
    echo "{$nombre}\t{$descripcion}\t{$row['cantidad']}\t{$observaciones}\n";
}
exit;
