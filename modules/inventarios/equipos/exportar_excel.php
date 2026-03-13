<?php
include_once '../../../config/db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inventario_equipos.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['Tipo', 'Marca', 'Modelo', 'Serial', 'Hostname', 'SO', 'Procesador', 'RAM', 'Almacenamiento', 'Ubicacion', 'Area', 'Responsable', 'Estado', 'Fecha compra', 'Garantia hasta']);

$stmt = $pdo->prepare("SELECT * FROM inventario_equipos ORDER BY tipo ASC, marca ASC, modelo ASC");
$stmt->execute();
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($equipos as $e) {
    fputcsv($output, [
        $e['tipo'],
        $e['marca'],
        $e['modelo'],
        $e['serial'],
        $e['hostname'],
        $e['so'],
        $e['procesador'],
        $e['ram'],
        $e['almacenamiento'],
        $e['ubicacion'],
        $e['area'],
        $e['responsable'],
        $e['estado'],
        $e['fecha_compra'],
        $e['garantia_fin']
    ]);
}

fclose($output);
exit;
