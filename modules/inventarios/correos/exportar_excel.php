<?php
include_once '../../../config/db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inventario_correos.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['Correo', 'Usuario', 'Plataforma', 'Proveedor', 'Responsable', 'Area', 'Estado', 'Fecha creacion', 'Ultimo acceso']);

$stmt = $pdo->prepare("SELECT * FROM inventario_correos ORDER BY correo ASC");
$stmt->execute();
$correos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($correos as $c) {
    fputcsv($output, [
        $c['correo'],
        $c['usuario'],
        $c['plataforma'],
        $c['proveedor'],
        $c['responsable'],
        $c['area'],
        $c['estado'],
        $c['fecha_creacion'],
        $c['ultimo_acceso']
    ]);
}

fclose($output);
exit;
