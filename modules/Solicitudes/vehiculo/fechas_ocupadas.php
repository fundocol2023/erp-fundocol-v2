<?php
require_once '../../../config/db.php';

$vehiculo_id = isset($_GET['vehiculo_id']) ? intval($_GET['vehiculo_id']) : 0;
$resultado = [];

// 1. Bloquear fechas de solicitudes pendientes o aprobadas
if ($vehiculo_id > 0) {
    $sql = "SELECT fecha_inicio, fecha_fin FROM vehiculos_solicitudes 
            WHERE vehiculo_id = ? AND estado IN ('pendiente', 'aprobado')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vehiculo_id]);
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($solicitudes as $s) {
        $inicio = strtotime($s['fecha_inicio']);
        $fin = strtotime($s['fecha_fin']);
        for ($d = $inicio; $d <= $fin; $d += 86400) {
            $resultado[] = date('Y-m-d', $d);
        }
    }
}

// 2. Bloquear todos los domingos (próximos 2 años, puedes cambiar rango)
$hoy = strtotime(date('Y-m-d'));
$limite = strtotime('+2 years', $hoy);
for ($dia = $hoy; $dia <= $limite; $dia += 86400) {
    // 0 = Domingo en PHP
    if (date('w', $dia) == 0) {
        $resultado[] = date('Y-m-d', $dia);
    }
}

header('Content-Type: application/json');
echo json_encode(array_values(array_unique($resultado)));
